from fastapi.testclient import TestClient

from app.config import Settings, get_settings
from app.main import app

client = TestClient(app)


def test_health_is_open():
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}


def test_ai_endpoints_require_internal_token():
    app.dependency_overrides[get_settings] = lambda: Settings(_env_file=None, internal_token="secret", openai_api_key="")
    try:
        payload = {"user_id": "0195f2a4-7c1e-7b3a-9d2f-1a2b3c4d5e6f", "message": "Who should I follow up with?"}
        assert client.post("/assistant/chat", json=payload).status_code == 401
        assert client.post("/assistant/chat", json=payload, headers={"X-Internal-Token": "secret"}).status_code == 503  # past the token check; no OpenAI key here
    finally:
        app.dependency_overrides.clear()
