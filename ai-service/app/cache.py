"""Redis caching: embeddings (never re-embed the same text) and the assistant's answers (exact repeats).

Invalidation policy
- Embeddings are keyed by model + SHA-256 of the exact text. A changed note is a different key, so a cached
  vector can never be stale; unchanged notes and repeated search queries are never embedded twice.
  30-day TTL only bounds memory.
- Answers are keyed by user, prompt variant, model, today's date (Manila), the user's data version, the question
  and the earlier turns. Indexing or removing a note bumps the user's data version, so all of their cached
  answers stop matching at once; a new day starts fresh (follow-ups move with the date); a 10-minute TTL bounds
  staleness from contact edits (follow-up dates) that don't pass through this service.

The cache is an optimisation, never a dependency: if Redis is down, every call is a miss and the service works
uncached. Cached values never include secrets; answers contain only what the user was already shown.
"""

import hashlib
import json
import logging
from array import array
from datetime import datetime, timedelta, timezone
from typing import Protocol

from app.embeddings import Embedder

logger = logging.getLogger("ai-service.cache")

EMBEDDING_TTL = 30 * 24 * 3600
ANSWER_TTL = 10 * 60
MANILA = timezone(timedelta(hours=8))


class Cache(Protocol):
    async def get_many(self, keys: list[str]) -> list[bytes | None]: ...
    async def set_many(self, items: dict[str, bytes], ttl: int) -> None: ...
    async def incr(self, key: str) -> None: ...


class NoCache:
    async def get_many(self, keys):
        return [None] * len(keys)

    async def set_many(self, items, ttl):
        pass

    async def incr(self, key):
        pass


class RedisCache:
    """Fails open: any Redis error is logged (once per kind of failure) and treated as a miss."""

    def __init__(self, client):
        self._redis = client
        self._warned = False

    @classmethod
    def from_url(cls, url: str) -> "RedisCache":
        import redis.asyncio as redis

        return cls(redis.Redis.from_url(url, socket_timeout=1, socket_connect_timeout=1))

    async def get_many(self, keys):
        if not keys:
            return []
        try:
            return await self._redis.mget(keys)
        except Exception as error:
            self._down(error)
            return [None] * len(keys)

    async def set_many(self, items, ttl):
        if not items:
            return
        try:
            async with self._redis.pipeline(transaction=False) as pipe:
                for key, value in items.items():
                    pipe.set(key, value, ex=ttl)
                await pipe.execute()
        except Exception as error:
            self._down(error)

    async def incr(self, key):
        try:
            await self._redis.incr(key)
        except Exception as error:
            self._down(error)

    def _down(self, error: Exception) -> None:
        if not self._warned:
            logger.warning("cache unavailable, working uncached: %s", type(error).__name__)
            self._warned = True


def _hash(*parts: str) -> str:
    return hashlib.sha256("\x1f".join(parts).encode()).hexdigest()


# ---- Embeddings ------------------------------------------------------------------------------------

class CachedEmbedder:
    """Wraps an Embedder: only texts it has never seen (for this model) go to the API."""

    def __init__(self, inner: Embedder, cache: Cache, model: str):
        self._inner, self._cache, self._model = inner, cache, model
        self.hits = self.misses = 0  # per instance, for logs and the benchmark

    async def embed(self, texts: list[str]) -> list[list[float]]:
        keys = [f"emb:{self._model}:{_hash(text)}" for text in texts]
        cached = await self._cache.get_many(keys)
        missing = [i for i, value in enumerate(cached) if value is None]
        self.hits += len(texts) - len(missing)
        self.misses += len(missing)

        vectors: list[list[float] | None] = [array("f", value).tolist() if value else None for value in cached]
        if missing:
            fresh = await self._inner.embed([texts[i] for i in missing])
            for i, vector in zip(missing, fresh, strict=True):
                vectors[i] = vector
            # float32 is plenty for cosine similarity and a quarter of the size of JSON.
            await self._cache.set_many({keys[i]: array("f", vectors[i]).tobytes() for i in missing}, EMBEDDING_TTL)
        if missing or texts:
            logger.info("embeddings: %d cached, %d new", len(texts) - len(missing), len(missing))
        return vectors  # type: ignore[return-value]


# ---- Answers ---------------------------------------------------------------------------------------

def _version_key(user_id: str) -> str:
    return f"ver:{user_id}"


async def bump_data_version(cache: Cache, user_id: str) -> None:
    """The user's notes changed: their cached answers no longer match."""
    await cache.incr(_version_key(user_id))


async def answer_key(cache: Cache, *, user_id: str, variant: str, model: str, message: str, history: list[dict]) -> str:
    [version] = await cache.get_many([_version_key(user_id)])
    today = datetime.now(MANILA).date().isoformat()
    turns = json.dumps(history, sort_keys=True, ensure_ascii=False)
    question = " ".join(message.lower().split())  # "Who is due?" and "who  is due? " are the same question
    return f"answer:{user_id}:{_hash(variant, model, today, (version or b'0').decode(), question, turns)}"


async def get_answer(cache: Cache, key: str) -> dict | None:
    [value] = await cache.get_many([key])
    return json.loads(value) if value else None


async def put_answer(cache: Cache, key: str, answer: dict) -> None:
    await cache.set_many({key: json.dumps(answer, ensure_ascii=False).encode()}, ANSWER_TTL)
