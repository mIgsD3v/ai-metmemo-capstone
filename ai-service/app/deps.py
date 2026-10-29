import logging
import secrets
from collections.abc import AsyncIterator, Callable
from functools import lru_cache
from typing import Annotated

from fastapi import Depends, Header, HTTPException, status
from qdrant_client.http.exceptions import ResponseHandlingException, UnexpectedResponse

from app.agent.llm import ChatModel, OpenAIChatModel
from app.agent.tools import ContactData, LaravelContactData
from app.config import Settings, get_settings
from app.cache import Cache, CachedEmbedder, NoCache, RedisCache
from app.embeddings import Embedder, OpenAIEmbedder
from app.guardrails import Moderator, NoModerator, OpenAIModerator
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


def get_notes_index_opener(settings: Annotated[Settings, Depends(get_settings)]) -> Callable[[], NotesIndex]:
    """For the assistant: opens the index only if search_notes is called, so a vector store outage
    disables search instead of the whole assistant."""
    return lambda: _notes_index(settings.qdrant_url, settings.qdrant_api_key, settings.embedding_dimensions)


def get_chat_model(settings: Annotated[Settings, Depends(get_settings)]) -> ChatModel:
    if not settings.openai_api_key:
        raise HTTPException(status.HTTP_503_SERVICE_UNAVAILABLE, "The assistant is not configured")
    return OpenAIChatModel(settings)


async def get_contact_data(settings: Annotated[Settings, Depends(get_settings)]) -> AsyncIterator[ContactData]:
    data = LaravelContactData(settings.laravel_url, settings.internal_token)
    try:
        yield data
    finally:
        await data.aclose()


@lru_cache
def _cache(url: str) -> Cache:
    return RedisCache.from_url(url) if url else NoCache()


def get_cache(settings: Annotated[Settings, Depends(get_settings)]) -> Cache:
    """Redis when REDIS_URL is set (tests override this with an in-memory fake)."""
    return _cache(settings.redis_url)


def get_moderator(settings: Annotated[Settings, Depends(get_settings)]) -> Moderator:
    return OpenAIModerator(settings) if settings.openai_api_key else NoModerator()


def get_embedder(settings: Annotated[Settings, Depends(get_settings)], cache: Annotated[Cache, Depends(get_cache)]) -> Embedder:
    if not settings.openai_api_key:
        raise HTTPException(status.HTTP_503_SERVICE_UNAVAILABLE, "Notes search is not configured")
    # Unchanged notes and repeated queries are embedded once (see app/cache.py).
    return CachedEmbedder(OpenAIEmbedder(settings), cache, settings.embedding_model)


def verify_internal_token(
    settings: Annotated[Settings, Depends(get_settings)],
    x_internal_token: Annotated[str | None, Header()] = None,
) -> None:
    """Only the Laravel app may call the AI endpoints."""
    if not settings.internal_token:
        return
    if not x_internal_token or not secrets.compare_digest(x_internal_token, settings.internal_token):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid internal token")
