"""Keeps the notes index in sync with Laravel, and searches it (the assistant's search_notes tool)."""

import asyncio
import logging
import time
from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, status
from openai import OpenAIError

from app.deps import get_embedder, get_notes_index
from app.embeddings import Embedder
from app.notes_index import NotesIndex, note_text
from app.schemas import ForgetNotesRequest, IndexNotesRequest, SearchNotesRequest, SearchNotesResponse

router = APIRouter(prefix="/notes", tags=["notes"])
logger = logging.getLogger("ai-service.notes")

Index = Annotated[NotesIndex, Depends(get_notes_index)]


async def embed_or_502(embedder: Embedder, texts: list[str]) -> list[list[float]]:
    try:
        return await embedder.embed(texts)
    except OpenAIError as error:
        logger.warning("embedding failed: %s", type(error).__name__)
        raise HTTPException(status.HTTP_502_BAD_GATEWAY, "Notes could not be processed right now") from error


@router.post("/index", status_code=status.HTTP_204_NO_CONTENT)
async def index_notes(request: IndexNotesRequest, index: Index, embedder: Annotated[Embedder, Depends(get_embedder)]) -> None:
    """Add or update notes (Laravel calls this when a note is saved, and in bulk to re-index)."""
    started = time.perf_counter()
    vectors = await embed_or_502(embedder, [note_text(note) for note in request.notes])
    await asyncio.to_thread(index.upsert, request.notes, vectors)
    # Counts and timing only; note text is personal data and never logged.
    logger.info("indexed %d notes in %.0f ms", len(request.notes), (time.perf_counter() - started) * 1000)


@router.post("/forget", status_code=status.HTTP_204_NO_CONTENT)
async def forget_notes(request: ForgetNotesRequest, index: Index) -> None:
    """Remove a note, a deleted contact's notes, or a deleted account's notes."""
    await asyncio.to_thread(index.forget, request.user_id, request.contact_id, request.note_id)


@router.post("/search", response_model=SearchNotesResponse)
async def search_notes(request: SearchNotesRequest, index: Index, embedder: Annotated[Embedder, Depends(get_embedder)]) -> SearchNotesResponse:
    """The owner's notes most related to the query, best first. Always scoped to request.user_id."""
    started = time.perf_counter()
    [vector] = await embed_or_502(embedder, [request.query])
    hits = await asyncio.to_thread(index.search, request.user_id, vector, request.limit, request.contact_id)
    logger.info("search returned %d hits in %.0f ms", len(hits), (time.perf_counter() - started) * 1000)
    return SearchNotesResponse(hits=hits)
