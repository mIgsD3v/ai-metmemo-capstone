from fastapi.testclient import TestClient

from app import card_reader
from app.config import Settings, get_settings
from app.main import app
from app.schemas import ScanResponse

client = TestClient(app)

PHOTO = ("card.jpg", b"fake-jpeg-bytes", "image/jpeg")


def use_settings(**overrides):
    # _env_file=None: tests never read the real .env (API key, internal token).
    app.dependency_overrides[get_settings] = lambda: Settings(_env_file=None, **overrides)


def teardown_function():
    app.dependency_overrides.clear()


def test_scanner_without_api_key_is_unavailable():
    use_settings(openai_api_key="")
    response = client.post("/scan", files={"image": PHOTO})
    assert response.status_code == 503


def test_scanner_rejects_non_images():
    use_settings(openai_api_key="test-key")
    response = client.post("/scan", files={"image": ("notes.pdf", b"%PDF", "application/pdf")})
    assert response.status_code == 415


def test_scanner_returns_reviewable_fields(monkeypatch):
    use_settings(openai_api_key="test-key")

    async def fake_read_card(image, mime_type, settings):
        return card_reader.to_scan_response({
            "full_name": {"value": "Juan Dela Cruz", "confidence": 0.98, "needs_review": False},
            "email": {"value": "juan@example.ph", "confidence": 0.6, "needs_review": False},
            "phone": {"value": None, "confidence": 0.4, "needs_review": True},
            "company": {"value": "Cruz Trading", "confidence": 0.9, "needs_review": True},
            "job_title": {"value": "  ", "confidence": 0.9, "needs_review": False},
        })

    monkeypatch.setattr(card_reader, "read_card", fake_read_card)
    body = client.post("/scan", files={"image": PHOTO}).json()

    assert body["full_name"] == {"value": "Juan Dela Cruz", "confidence": 0.98, "needs_review": False}
    # Low confidence is flagged even when the model did not flag it.
    assert body["email"]["needs_review"] is True
    # Missing fields are empty, not flagged, and never invented.
    assert body["phone"] == {"value": None, "confidence": 0.0, "needs_review": False}
    assert body["job_title"]["value"] is None
    # The model's own flag is kept.
    assert body["company"]["needs_review"] is True


def test_scanner_model_failure_is_a_clean_error(monkeypatch):
    use_settings(openai_api_key="test-key")

    async def broken_read_card(image, mime_type, settings):
        raise ValueError("model returned invalid JSON")

    monkeypatch.setattr(card_reader, "read_card", broken_read_card)
    assert client.post("/scan", files={"image": PHOTO}).status_code == 502


def test_to_scan_response_clamps_confidence():
    result = card_reader.to_scan_response({"full_name": {"value": "A", "confidence": 3, "needs_review": False}})
    assert isinstance(result, ScanResponse)
    assert result.full_name.confidence == 1.0
    assert result.email.value is None
