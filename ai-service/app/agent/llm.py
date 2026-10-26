"""The chat model behind the agent. Behind a small interface so tests can script its replies."""

from dataclasses import dataclass, field
from typing import Protocol

from openai import AsyncOpenAI

from app.config import Settings


@dataclass
class ToolCall:
    id: str
    name: str
    arguments: str  # JSON text, exactly as the model wrote it (it may be invalid)


@dataclass
class ModelReply:
    content: str | None
    tool_calls: list[ToolCall] = field(default_factory=list)


class ChatModel(Protocol):
    async def complete(self, messages: list[dict], tools: list[dict], allow_tools: bool = True) -> ModelReply: ...


class OpenAIChatModel:
    def __init__(self, settings: Settings):
        self._client = AsyncOpenAI(api_key=settings.openai_api_key, timeout=30, max_retries=1)
        self._model = settings.chat_model

    async def complete(self, messages: list[dict], tools: list[dict], allow_tools: bool = True) -> ModelReply:
        response = await self._client.chat.completions.create(
            model=self._model,
            messages=messages,
            tools=tools,
            # On the last step the tools stay declared (earlier tool messages need them) but can't be called.
            tool_choice="auto" if allow_tools else "none",
            temperature=0,  # as repeatable as possible: the evaluation compares runs
        )
        message = response.choices[0].message
        return ModelReply(
            content=message.content,
            tool_calls=[ToolCall(call.id, call.function.name, call.function.arguments) for call in message.tool_calls or []],
        )
