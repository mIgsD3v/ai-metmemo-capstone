"""Notes index (RAG): finds the right note, and only ever the owner's own notes."""

import hashlib
import math
import re

import pytest
from fastapi.testclient import TestClient
from openai import APIConnectionError

from app.config import Settings, get_settings
from app.deps import get_embedder, get_notes_index
from app.main import app
from app.notes_index import NotesIndex
from app.schemas import NoteIn

ALICE = "0195f2a4-0000-7000-8000-00000000a11c"
BOB = "0195f2a4-0000-7000-8000-000000000b0b"


class FakeEmbedder:
    """Bag-of-words hashed into a small vector: texts sharing words land close together. No API calls."""

    calls = 0

    async def embed(self, texts):
        FakeEmbedder.calls += 1
        return [self.vector(text) for text in texts]

    @staticmethod
    def vector(text, size=128):
        v = [0.0] * size
        for word in re.findall(r"[a-z0-9]+", text.lower()):
            v[int(hashlib.md5(word.encode()).hexdigest(), 16) % size] += 1.0
        norm = math.sqrt(sum(x * x for x in v)) or 1.0
        return [x / norm for x in v]


class BrokenEmbedder:
    async def embed(self, texts):
        raise APIConnectionError(request=None)


def note(note_id, user_id, contact_id, name, body, created_at="2026-10-17T09:00:00+08:00"):
    return NoteIn(note_id=note_id, user_id=user_id, contact_id=contact_id, contact_name=name, body=body, created_at=created_at)


NOTES = [
    note("n1", ALICE, "c-juan", "Juan Dela Cruz", "Asked for a quote for 3 branches. Wants it before Friday."),
    note("n2", ALICE, "c-juan", "Juan Dela Cruz", "Met at PhilConstruct 2026. Interested in the warehouse package."),
    note("n3", ALICE, "c-maria", "Maria Santos", "Prefers email. Budget approved for Q4 logistics software."),
    note("n4", BOB, "c-other", "Someone Else", "Asked for a quote for 3 branches too, but this is Bob's note."),
]


@pytest.fixture
def index(tmp_path):
    notes_index = NotesIndex.persistent(str(tmp_path / "chroma"))
    notes_index.upsert(NOTES, [FakeEmbedder.vector(n.body) for n in NOTES])
    return notes_index


def search(index, user_id, query, **kwargs):
    return index.search(user_id, FakeEmbedder.vector(query), **kwargs)


def test_search_finds_the_relevant_note_first(index):
    hits = search(index, ALICE, "quote for branches")

    assert hits[0].note_id == "n1"
    assert hits[0].contact_name == "Juan Dela Cruz"
    assert hits[0].created_at.startswith("2026-10-17")
    assert hits[0].score > hits[-1].score


def test_search_never_returns_another_users_notes(index):
    alice = search(index, ALICE, "quote for 3 branches", limit=10)
    bob = search(index, BOB, "quote for 3 branches", limit=10)

    assert "n4" not in {hit.note_id for hit in alice}
    assert {hit.note_id for hit in bob} == {"n4"}


def test_search_can_be_limited_to_one_contact(index):
    hits = search(index, ALICE, "budget email quote", contact_id="c-maria", limit=10)

    assert {hit.note_id for hit in hits} == {"n3"}


def test_reindexing_a_note_replaces_it(index):
    edited = note("n3", ALICE, "c-maria", "Maria Santos", "Now wants a demo of the mobile app.")
    index.upsert([edited], [FakeEmbedder.vector(edited.body)])

    hits = search(index, ALICE, "demo mobile app", contact_id="c-maria")
    assert len(hits) == 1 and hits[0].body.startswith("Now wants a demo")


def test_forget_removes_a_contacts_notes_and_only_theirs(index):
    index.forget(ALICE, contact_id="c-juan")

    assert {hit.note_id for hit in search(index, ALICE, "quote branches", limit=10)} == {"n3"}
    assert index.count(BOB) == 1


def test_forget_one_users_notes_leaves_others(index):
    index.forget(ALICE)

    assert index.count(ALICE) == 0
    assert index.count(BOB) == 1


# ---- HTTP endpoints --------------------------------------------------------------------------

@pytest.fixture
def client(tmp_path):
    notes_index = NotesIndex.persistent(str(tmp_path / "chroma-api"))
    app.dependency_overrides[get_settings] = lambda: Settings(_env_file=None, internal_token="secret", openai_api_key="test")
    app.dependency_overrides[get_notes_index] = lambda: notes_index
    app.dependency_overrides[get_embedder] = lambda: FakeEmbedder()
    yield TestClient(app, headers={"X-Internal-Token": "secret"})
    app.dependency_overrides.clear()


def as_json(n: NoteIn) -> dict:
    return n.model_dump()


def test_index_then_search_over_http(client):
    assert client.post("/notes/index", json={"notes": [as_json(n) for n in NOTES]}).status_code == 204

    response = client.post("/notes/search", json={"user_id": ALICE, "query": "quote for branches"})

    assert response.status_code == 200
    hits = response.json()["hits"]
    assert hits[0]["note_id"] == "n1"
    assert all(hit["contact_id"] != "c-other" for hit in hits)


def test_index_embeds_a_batch_in_one_call(client):
    FakeEmbedder.calls = 0

    client.post("/notes/index", json={"notes": [as_json(n) for n in NOTES]})

    assert FakeEmbedder.calls == 1


def test_forget_over_http(client):
    client.post("/notes/index", json={"notes": [as_json(n) for n in NOTES]})

    assert client.post("/notes/forget", json={"user_id": ALICE, "contact_id": "c-juan"}).status_code == 204
    hits = client.post("/notes/search", json={"user_id": ALICE, "query": "quote", "limit": 10}).json()["hits"]
    assert {hit["note_id"] for hit in hits} == {"n3"}


def test_notes_endpoints_require_the_internal_token(client):
    anonymous = TestClient(app)

    assert anonymous.post("/notes/search", json={"user_id": ALICE, "query": "quote"}).status_code == 401
    assert anonymous.post("/notes/index", json={"notes": [as_json(NOTES[0])]}).status_code == 401


def test_search_requires_a_user(client):
    assert client.post("/notes/search", json={"query": "quote"}).status_code == 422


def test_embedding_outage_is_a_clear_error_not_a_crash(client):
    app.dependency_overrides[get_embedder] = lambda: BrokenEmbedder()

    response = client.post("/notes/search", json={"user_id": ALICE, "query": "quote"})

    assert response.status_code == 502
    assert response.json()["detail"] == "Notes could not be processed right now"


def test_missing_api_key_reports_not_configured(tmp_path):
    app.dependency_overrides[get_settings] = lambda: Settings(_env_file=None, internal_token="", openai_api_key="", chroma_path=str(tmp_path / "c"))
    try:
        response = TestClient(app).post("/notes/search", json={"user_id": ALICE, "query": "quote"})
        assert response.status_code == 503
    finally:
        app.dependency_overrides.clear()
