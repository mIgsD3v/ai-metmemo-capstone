import logging
import secrets
from functools import lru_cache
from typing import Annotated

from fastapi import Depends, Header, HTTPException, status
from qdrant_client.http.exceptions import ResponseHandlingException, UnexpectedResponse

from app.config import Settings, get_settings
from app.embeddings import Embedder, OpenAIEmbedder
from app.notes_index import NotesIndex

logger = logging.getLogger("ai-service.notes")


@lru_cache
def _notes_index(url: str, api_key: str, dimensions: int) -> NotesIndex:
    return NotesIndex.connect(url, api_key, dimensions)


def get_notes_index(settings: Annotated[Settings, Depends(get_settings)]) -> NotesIndex:
    """One connection per process, opened on first use (tests override this with an in-memory one)."""
    try:
        return _notes_index(settings.qdrant_url, settings.qdrant_api_key, settings.embedding_dimensions)
    except (ResponseHandlingException, UnexpectedResponse) as error:
        logger.warning("vector store unavailable: %s", type(error).__name__)
        raise HTTPException(status.HTTP_503_SERVICE_UNAVAILABLE, "Notes search is unavailable right now") from error


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
