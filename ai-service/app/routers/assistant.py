"""The owner's follow-up assistant (Laravel calls this for the Assistant page)."""

import logging
import time
from collections.abc import Callable
from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, status
from openai import OpenAIError

from app.agent.llm import ChatModel
from app.agent.loop import FollowUpAgent
from app.agent.tools import ContactData, Toolbox
from app.config import Settings, get_settings
from app.deps import get_chat_model, get_contact_data, get_embedder, get_notes_index_opener
from app.embeddings import Embedder
from app.notes_index import NotesIndex
from app.schemas import AssistantRequest, AssistantResponse

router = APIRouter(prefix="/assistant", tags=["assistant"])
logger = logging.getLogger("ai-service.assistant")


@router.post("/chat", response_model=AssistantResponse)
async def assistant_chat(
    request: AssistantRequest,
    settings: Annotated[Settings, Depends(get_settings)],
    model: Annotated[ChatModel, Depends(get_chat_model)],
    data: Annotated[ContactData, Depends(get_contact_data)],
    index: Annotated[Callable[[], NotesIndex], Depends(get_notes_index_opener)],
    embedder: Annotated[Embedder, Depends(get_embedder)],
) -> AssistantResponse:
    """Plans, calls contact tools and note search, and drafts follow-ups. Drafts are never sent."""
    started = time.perf_counter()
    tools = Toolbox(request.user_id, data, index, embedder)
    agent = FollowUpAgent(model, settings.assistant_prompt)
    try:
        result = await agent.run(tools, request.message, request.history, request.owner_name)
    except OpenAIError as error:
        logger.warning("assistant failed: %s", type(error).__name__)
        raise HTTPException(status.HTTP_502_BAD_GATEWAY, "The assistant is unavailable right now") from error

    # Tool names, counts and timing only: messages and notes are personal data and never logged.
    logger.info(
        "assistant ok in %.0f ms: steps=%d tools=%s drafts=%d sources=%d dropped_citations=%d",
        (time.perf_counter() - started) * 1000,
        result.steps,
        result.tool_calls,
        len(result.drafts),
        len(result.sources),
        len(result.dropped_citations),
    )
    return AssistantResponse(
        answer=result.answer, tool_calls=result.tool_calls, drafts=result.drafts, sources=result.sources, steps=result.steps
    )
