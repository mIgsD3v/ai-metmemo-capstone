"""The owner's meeting notes as vectors (Chroma), for RAG search.

One note is one vector: notes are short (at most a couple of thousand characters), so they are
embedded whole rather than chunked. Every vector carries the owner's user_id, and every search
and delete requires it, so one user can never retrieve another user's notes.
"""

import chromadb
from chromadb.config import Settings as ChromaSettings

from app.schemas import NoteHit, NoteIn

COLLECTION = "contact-notes"


def note_text(note: NoteIn) -> str:
    """What gets embedded for a note. Kept in one place so retrieval experiments change one line."""
    return note.body


class NotesIndex:
    def __init__(self, client: chromadb.api.ClientAPI):
        # Vectors are always supplied by us (OpenAI embeddings), so Chroma's built-in model is never used.
        self._notes = client.get_or_create_collection(
            COLLECTION,
            configuration={"hnsw": {"space": "cosine"}},
            embedding_function=None,
        )

    @classmethod
    def persistent(cls, path: str) -> "NotesIndex":
        # (Chroma's in-memory clients share state within a process, so tests use a temp folder instead.)
        return cls(chromadb.PersistentClient(path=path, settings=ChromaSettings(anonymized_telemetry=False)))

    def upsert(self, notes: list[NoteIn], vectors: list[list[float]]) -> None:
        """Add or replace notes. Re-indexing the same note id overwrites it, so retries are safe."""
        self._notes.upsert(
            ids=[note.note_id for note in notes],
            embeddings=vectors,
            documents=[note.body for note in notes],
            metadatas=[
                {
                    "user_id": note.user_id,
                    "contact_id": note.contact_id,
                    "contact_name": note.contact_name,
                    "created_at": note.created_at,
                }
                for note in notes
            ],
        )

    def search(self, user_id: str, vector: list[float], limit: int = 5, contact_id: str | None = None) -> list[NoteHit]:
        """The owner's most similar notes, best first. score is cosine similarity (1 = identical)."""
        result = self._notes.query(
            query_embeddings=[vector],
            n_results=limit,
            where=self._scope(user_id, contact_id),
            include=["documents", "metadatas", "distances"],
        )
        return [
            NoteHit(
                note_id=note_id,
                contact_id=meta["contact_id"],
                contact_name=meta["contact_name"],
                created_at=meta["created_at"],
                body=body,
                score=round(1 - distance, 4),
            )
            for note_id, body, meta, distance in zip(
                result["ids"][0], result["documents"][0], result["metadatas"][0], result["distances"][0], strict=True
            )
        ]

    def forget(self, user_id: str, contact_id: str | None = None, note_id: str | None = None) -> None:
        """Remove one note, all of a contact's notes, or all of a user's notes."""
        scope = [{"user_id": user_id}]
        if contact_id:
            scope.append({"contact_id": contact_id})
        if note_id:
            self._notes.delete(ids=[note_id], where={"$and": scope} if len(scope) > 1 else scope[0])
            return
        self._notes.delete(where={"$and": scope} if len(scope) > 1 else scope[0])

    def count(self, user_id: str) -> int:
        return len(self._notes.get(where={"user_id": user_id}, include=[])["ids"])

    @staticmethod
    def _scope(user_id: str, contact_id: str | None) -> dict:
        if contact_id:
            return {"$and": [{"user_id": user_id}, {"contact_id": contact_id}]}
        return {"user_id": user_id}
