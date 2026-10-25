import secrets
from functools import lru_cache
from typing import Annotated

from fastapi import Depends, Header, HTTPException, status

from app.config import Settings, get_settings
from app.embeddings import Embedder, OpenAIEmbedder
from app.notes_index import NotesIndex


@lru_cache
def _notes_index(path: str) -> NotesIndex:
    return NotesIndex.persistent(path)


def get_notes_index(settings: Annotated[Settings, Depends(get_settings)]) -> NotesIndex:
    """One index per process, opened on first use (tests override this with an in-memory one)."""
    return _notes_index(settings.chroma_path)


def get_embedder(settings: Annotated[Settings, Depends(get_settings)]) -> Embedder:
    if not settings.openai_api_key:
        raise HTTPException(status.HTTP_503_SERVICE_UNAVAILABLE, "Notes search is not configured")
    return OpenAIEmbedder(settings)


def verify_internal_token(
    settings: Annotated[Settings, Depends(get_settings)],
    x_internal_token: Annotated[str | None, Header()] = None,
) -> None:
    """Only the Laravel app may call the AI endpoints."""
    if not settings.internal_token:
        return
    if not x_internal_token or not secrets.compare_digest(x_internal_token, settings.internal_token):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid internal token")
