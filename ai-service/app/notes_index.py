"""The owner's meeting notes as vectors (Qdrant), for RAG search.

One note is one vector: notes are short (at most a couple of thousand characters), so they are
embedded whole rather than chunked. Every vector carries the owner's user_id, and every search
and delete requires it, so one user can never retrieve another user's notes.
"""

import uuid
import warnings

from qdrant_client import QdrantClient, models

from app.schemas import NoteHit, NoteIn

COLLECTION = "contact-notes"

# Qdrant point ids must be UUIDs or integers; this maps any note id to a stable UUID,
# so re-indexing the same note overwrites its point instead of adding a duplicate.
_POINT_NAMESPACE = uuid.UUID("6f1c3b0e-5d2a-4c8e-9f1a-2b7d4e6a8c10")


def note_text(note: NoteIn) -> str:
    """What gets embedded for a note. Kept in one place so retrieval experiments change one line."""
    return note.body


def point_id(note_id: str) -> str:
    return str(uuid.uuid5(_POINT_NAMESPACE, note_id))


class NotesIndex:
    def __init__(self, client: QdrantClient, dimensions: int):
        self._client = client
        if not client.collection_exists(COLLECTION):
            client.create_collection(
                COLLECTION,
                vectors_config=models.VectorParams(size=dimensions, distance=models.Distance.COSINE),
            )
            # Every search filters on user_id (and sometimes contact_id): index them on the server.
            # (Qdrant's in-memory mode used by the tests ignores payload indexes and warns; that's fine.)
            with warnings.catch_warnings():
                warnings.simplefilter("ignore")
                for field in ("user_id", "contact_id", "note_id"):
                    client.create_payload_index(COLLECTION, field, models.PayloadSchemaType.KEYWORD)

    @classmethod
    def connect(cls, location: str, api_key: str = "", dimensions: int = 1536) -> "NotesIndex":
        """location is the server URL (http://localhost:6333), or ":memory:" for tests."""
        return cls(QdrantClient(location=location, api_key=api_key or None), dimensions)

    def upsert(self, notes: list[NoteIn], vectors: list[list[float]]) -> None:
        """Add or replace notes. Re-indexing the same note id overwrites it, so retries are safe."""
        self._client.upsert(
            COLLECTION,
            points=[
                models.PointStruct(id=point_id(note.note_id), vector=vector, payload=note.model_dump())
                for note, vector in zip(notes, vectors, strict=True)
            ],
        )

    def search(self, user_id: str, vector: list[float], limit: int = 5, contact_id: str | None = None) -> list[NoteHit]:
        """The owner's most similar notes, best first. score is cosine similarity (1 = identical)."""
        points = self._client.query_points(
            COLLECTION,
            query=vector,
            query_filter=self._scope(user_id, contact_id),
            limit=limit,
            with_payload=True,
        ).points
        return [
            NoteHit(
                note_id=point.payload["note_id"],
                contact_id=point.payload["contact_id"],
                contact_name=point.payload["contact_name"],
                created_at=point.payload["created_at"],
                body=point.payload["body"],
                score=round(point.score, 4),
            )
            for point in points
        ]

    def forget(self, user_id: str, contact_id: str | None = None, note_id: str | None = None) -> None:
        """Remove one note, all of a contact's notes, or all of a user's notes."""
        self._client.delete(
            COLLECTION,
            points_selector=models.FilterSelector(filter=self._scope(user_id, contact_id, note_id)),
        )

    def count(self, user_id: str) -> int:
        return self._client.count(COLLECTION, count_filter=self._scope(user_id), exact=True).count

    @staticmethod
    def _scope(user_id: str, contact_id: str | None = None, note_id: str | None = None) -> models.Filter:
        fields = {"user_id": user_id, "contact_id": contact_id, "note_id": note_id}
        return models.Filter(
            must=[
                models.FieldCondition(key=key, match=models.MatchValue(value=value))
                for key, value in fields.items()
                if value
            ]
        )
