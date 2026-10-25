"""Turns text into vectors for the notes index. Behind a small interface so tests can use a fake."""

from typing import Protocol

from openai import AsyncOpenAI

from app.config import Settings


class Embedder(Protocol):
    async def embed(self, texts: list[str]) -> list[list[float]]: ...


class OpenAIEmbedder:
    """text-embedding-3-small by default: cheap, good enough for short meeting notes."""

    def __init__(self, settings: Settings):
        self._client = AsyncOpenAI(api_key=settings.openai_api_key)
        self._model = settings.embedding_model

    async def embed(self, texts: list[str]) -> list[list[float]]:
        # One request for the whole batch; the API returns vectors in the same order.
        response = await self._client.embeddings.create(model=self._model, input=texts)
        return [item.embedding for item in sorted(response.data, key=lambda item: item.index)]
