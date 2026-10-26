"""Langfuse tracing: every assistant turn, its model calls and its tool calls, inspectable in one trace.

Off unless LANGFUSE_PUBLIC_KEY and LANGFUSE_SECRET_KEY are set. Tracing must never break a request:
when it's off, or if Langfuse is unreachable, the helpers do nothing. Everything sent to Langfuse goes
through the guardrails' PII masker first, so traces contain no phone numbers or email addresses.
"""

import logging
from collections.abc import Iterator
from contextlib import contextmanager
from typing import Any

from app.config import Settings
from app.guardrails import mask_data

logger = logging.getLogger("ai-service.tracing")

_client = None


def configure(settings: Settings) -> None:
    global _client
    if not (settings.langfuse_public_key and settings.langfuse_secret_key):
        _client = None
        return
    try:
        from langfuse import Langfuse

        _client = Langfuse(
            public_key=settings.langfuse_public_key,
            secret_key=settings.langfuse_secret_key,
            host=settings.langfuse_host,
            environment=settings.app_env,
            mask=lambda *, data, **_: mask_data(data),
            # Only our own spans: FastAPI's built-in request tracing adds noise, not information.
            blocked_instrumentation_scopes=["fastapi"],
        )
    except Exception as error:  # a tracing problem must not stop the service starting
        logger.warning("tracing disabled: %s", type(error).__name__)
        _client = None


def enabled() -> bool:
    return _client is not None


class _Nothing:
    """Stands in for a span when tracing is off."""

    def update(self, **_: Any) -> None:
        pass


@contextmanager
def span(name: str, *, generation: bool = False, **fields: Any) -> Iterator[Any]:
    """A span (or, with generation=True, an LLM generation) around a block. Yields an object with .update()."""
    if _client is None:
        yield _Nothing()
        return
    start = _client.start_as_current_generation if generation else _client.start_as_current_span
    with start(name=name, **fields) as observation:
        yield observation


def update_trace(**fields: Any) -> None:
    if _client is not None:
        _client.update_current_trace(**fields)


def event(name: str, **fields: Any) -> None:
    if _client is not None:
        _client.create_event(name=name, **fields)


def trace_url() -> str | None:
    return _client.get_trace_url() if _client is not None else None


def flush() -> None:
    if _client is not None:
        _client.flush()
