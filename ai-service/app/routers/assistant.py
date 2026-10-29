"""The owner's follow-up assistant (Laravel calls this for the Assistant page)."""

import logging
import time
from collections.abc import Callable
from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, status
from openai import OpenAIError

from app import tracing
from app.cache import Cache, answer_key, get_answer, put_answer
from app.agent.llm import ChatModel
from app.agent.loop import FollowUpAgent
from app.agent.tools import ContactData, Toolbox
from app.config import Settings, get_settings
from app.deps import get_cache, get_chat_model, get_contact_data, get_embedder, get_moderator, get_notes_index_opener
from app.embeddings import Embedder
from app.guardrails import Moderator, check_input, check_output, mask_pii
from app.notes_index import NotesIndex
from app.schemas import AssistantRequest, AssistantResponse, ChatMessage

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
    moderator: Annotated[Moderator, Depends(get_moderator)],
    cache: Annotated[Cache, Depends(get_cache)],
) -> AssistantResponse:
    """Plans, calls contact tools and note search, and drafts follow-ups. Drafts are never sent."""
    started = time.perf_counter()
    with tracing.span("assistant-chat", input={"message": request.message, "history_turns": len(request.history)}) as root:
        tracing.update_trace(
            name="assistant-chat",
            user_id=request.user_id,
            session_id=request.session_id,
            tags=[f"prompt:{settings.assistant_prompt}"],
        )

        # Input guardrails: unsafe or manipulative requests never reach the model.
        verdict = await check_input(request.message, moderator)
        if verdict.refusal:
            response = AssistantResponse(answer=verdict.refusal, guardrails=verdict.events)
            _finish(root, response, started, blocked=True)
            return response

        history = [ChatMessage(role=turn.role, content=mask_pii(turn.content)[0]) for turn in request.history]

        # Exact repeat of a question the user asked today, against the same notes: answer from the cache.
        key = await answer_key(
            cache, user_id=request.user_id, variant=settings.assistant_prompt, model=settings.chat_model,
            message=verdict.text, history=[turn.model_dump() for turn in history],
        )
        if (hit := await get_answer(cache, key)) is not None:
            response = AssistantResponse(**(hit | {"guardrails": verdict.events + hit.get("guardrails", []), "cached": True, "usage": {}}))
            tracing.update_trace(tags=["cache:hit"])
            _finish(root, response, started)
            return response

        tools = Toolbox(request.user_id, data, index, embedder)
        agent = FollowUpAgent(model, settings.assistant_prompt)
        try:
            result = await agent.run(tools, verdict.text, history, request.owner_name)
        except OpenAIError as error:
            logger.warning("assistant failed: %s", type(error).__name__)
            root.update(level="ERROR", status_message=type(error).__name__)
            raise HTTPException(status.HTTP_502_BAD_GATEWAY, "The assistant is unavailable right now") from error

        # Output guardrails: moderation, and no contact details in the answer.
        output = await check_output(result.answer, [draft.body for draft in result.drafts], moderator)
        events = verdict.events + result.guardrails + output.events
        usage = {"input_tokens": result.input_tokens, "output_tokens": result.output_tokens}
        if output.refusal:
            response = AssistantResponse(answer=output.refusal, tool_calls=result.tool_calls, steps=result.steps, guardrails=events)
        else:
            response = AssistantResponse(
                answer=output.text,
                tool_calls=result.tool_calls,
                drafts=result.drafts,
                sources=result.sources,
                steps=result.steps,
                guardrails=events,
                usage=usage,
            )
            # Only good answers are cached (never refusals or failures); input guardrail events are re-checked each time.
            await put_answer(cache, key, response.model_dump(exclude={"cached", "usage"}) | {"guardrails": result.guardrails + output.events})
        _finish(root, response, started)
        return response


def _finish(root, response: AssistantResponse, started: float, blocked: bool = False) -> None:
    ms = (time.perf_counter() - started) * 1000
    root.update(
        output={"answer": response.answer, "tool_calls": response.tool_calls, "drafts": len(response.drafts)},
        level="WARNING" if response.guardrails and any(".pii_masked" not in g for g in response.guardrails) else "DEFAULT",
        metadata={"latency_ms": round(ms), "steps": response.steps, "guardrails": response.guardrails},
    )
    if response.guardrails:
        tracing.update_trace(tags=sorted({f"guardrail:{g.split(' ')[0]}" for g in response.guardrails}))
    # Tool names, counts and timing only: messages and notes are personal data and never logged.
    logger.info(
        "assistant %s in %.0f ms: steps=%d tools=%s drafts=%d sources=%d guardrails=%s",
        "blocked" if blocked else ("cached" if response.cached else "ok"),
        ms,
        response.steps,
        response.tool_calls,
        len(response.drafts),
        len(response.sources),
        response.guardrails,
    )
