"""Measure what caching saves: latency and estimated cost, before (cold) and after (cached).

    cd ai-service
    python -m evals.cache_benchmark --user <demo user id>

Needs the AI service running on --url with REDIS_URL set, plus Laravel, Qdrant and Redis. Writes
docs/evidence/caching.md. Costs a few cents: the cold runs are real model calls.

Cold answers are forced by bumping the user's data version (the same thing a note change does), so the run
also exercises the invalidation path. Embedding timings call the embedder directly, with the keys for the
measured texts deleted first.
"""

import argparse
import asyncio
import statistics
import time
from datetime import datetime
from pathlib import Path

import httpx
import redis.asyncio as redis

from app.cache import CachedEmbedder, RedisCache, _hash, bump_data_version
from app.config import get_settings
from app.embeddings import OpenAIEmbedder

# gpt-4o-mini and text-embedding-3-small list prices (USD per 1M tokens) when this was written; check before quoting.
PRICE_IN, PRICE_OUT, PRICE_EMBED = 0.15, 0.60, 0.02

QUESTIONS = [
    ("Who should I follow up with today?", "Who do I need to follow up with today?"),
    ("Who wanted a pet-friendly place?", "Which client has a dog and needs a pet-friendly unit?"),
    ("What did I promise Elena Castillo?", "What did I tell Elena Castillo I would send?"),
    ("What is Kenji Watanabe's housing allowance?", "How much housing allowance does Kenji Watanabe get?"),
    ("Draft a follow-up email to Andrea Villanueva about the shortlist I promised",
     "Write an email to Andrea Villanueva following up on the studio shortlist"),
]


def cost(usage: dict) -> float:
    return usage.get("input_tokens", 0) / 1e6 * PRICE_IN + usage.get("output_tokens", 0) / 1e6 * PRICE_OUT


async def ask(client: httpx.AsyncClient, user: str, message: str) -> tuple[float, dict]:
    started = time.perf_counter()
    response = await client.post("/assistant/chat", json={"user_id": user, "message": message, "owner_name": "Maria Santos"})
    response.raise_for_status()
    return (time.perf_counter() - started) * 1000, response.json()


async def main(user: str, url: str, out: Path) -> None:
    settings = get_settings()
    if not settings.redis_url:
        raise SystemExit("Set REDIS_URL in ai-service/.env first.")
    raw = redis.Redis.from_url(settings.redis_url)
    await raw.ping()
    cache = RedisCache(raw)

    rows = []
    async with httpx.AsyncClient(base_url=url, headers={"X-Internal-Token": settings.internal_token}, timeout=120) as client:
        await bump_data_version(cache, user)  # start cold: no cached answers for this user
        for question, rephrased in QUESTIONS:
            cold_ms, cold = await ask(client, user, question)
            hit_ms, hit = await ask(client, user, question)
            similar_ms, similar = await ask(client, user, rephrased)
            rows.append((question, cold_ms, cold, hit_ms, hit, similar_ms, similar))
            print(f"{question[:45]:45} cold {cold_ms:6.0f} ms | repeat {hit_ms:5.0f} ms cached={hit['cached']} | rephrased {similar_ms:6.0f} ms cached={similar['cached']}")

    # Embeddings: one search query, and re-indexing every note of the demo account.
    embedder = CachedEmbedder(OpenAIEmbedder(settings), cache, settings.embedding_model)
    query = "client who needs a pet-friendly unit near a park"
    note_texts = await demo_note_texts(settings, user)
    keys = [f"emb:{settings.embedding_model}:{_hash(t)}" for t in [query, *note_texts]]
    await raw.delete(*keys)

    async def timed(texts):
        started = time.perf_counter()
        await embedder.embed(texts)
        return (time.perf_counter() - started) * 1000

    query_cold, query_warm = await timed([query]), await timed([query])
    reindex_cold, reindex_warm = await timed(note_texts), await timed(note_texts)
    embed_tokens = sum(len(t) for t in note_texts) / 4  # ~4 characters per token
    await raw.aclose()

    write_report(out, settings, rows, query_cold, query_warm, reindex_cold, reindex_warm, len(note_texts), embed_tokens)
    print(f"\nReport written to {out}")


async def demo_note_texts(settings, user: str) -> list[str]:
    """The demo account's notes as stored in the index (what re-indexing would embed)."""
    from app.deps import _notes_index

    index = _notes_index(settings.qdrant_url, settings.qdrant_api_key, settings.embedding_dimensions)
    points, _ = index._client.scroll("contact-notes", scroll_filter=index._scope(user), limit=500, with_payload=True)
    return [point.payload["body"] for point in points]


def write_report(out, settings, rows, query_cold, query_warm, reindex_cold, reindex_warm, notes, embed_tokens):
    cold_ms = [r[1] for r in rows]
    hit_ms = [r[3] for r in rows]
    similar_ms = [r[5] for r in rows]
    cold_cost = [cost(r[2]["usage"]) for r in rows]
    similar_cost = [cost(r[6].get("usage", {})) for r in rows]
    hits = sum(r[4]["cached"] for r in rows)
    similar_hits = sum(r[6]["cached"] for r in rows)
    n = len(rows)

    lines = [
        "# Caching: before and after",
        "",
        f"Measured {datetime.now():%Y-%m-%d %H:%M} on a development laptop, through the running AI service (Laravel tools,",
        f"Qdrant and Redis local; OpenAI `{settings.chat_model}` and `{settings.embedding_model}` remote). Reproduce with",
        "`python -m evals.cache_benchmark` (see its docstring). Costs use list prices of $0.15 / $0.60 per 1M input / output",
        "tokens (chat) and $0.02 per 1M tokens (embeddings); check current prices before quoting them.",
        "",
        "## Summary",
        "",
        "| Scenario | Before (no cache) | After (cache) | Saving |",
        "|---|---|---|---|",
        f"| Same question asked again (×{n}) | {statistics.mean(cold_ms):,.0f} ms, ${statistics.mean(cold_cost):.5f} | "
        f"{statistics.mean(hit_ms):,.0f} ms, $0 ({hits}/{n} served from cache) | "
        f"{(1 - statistics.mean(hit_ms) / statistics.mean(cold_ms)):.0%} faster, 100% of model cost |",
        f"| Similar but reworded question (×{n}) | {statistics.mean(cold_ms):,.0f} ms | {statistics.mean(similar_ms):,.0f} ms, "
        f"${statistics.mean(similar_cost):.5f} ({similar_hits}/{n} from cache) | none: exact-match only (see below) |",
        f"| Embed a repeated search query | {query_cold:,.0f} ms | {query_warm:,.1f} ms | {(1 - query_warm / query_cold):.0%} faster, no API call |",
        f"| Re-index all {notes} demo notes (unchanged) | {reindex_cold:,.0f} ms, 1 API call, ~{embed_tokens:,.0f} tokens | "
        f"{reindex_warm:,.1f} ms, 0 API calls | {(1 - reindex_warm / reindex_cold):.0%} faster, ${embed_tokens / 1e6 * PRICE_EMBED:.6f} saved |",
        "",
        "## Per question",
        "",
        "| Question | Cold | Repeat | Reworded | Cold tokens (in / out) |",
        "|---|---|---|---|---|",
    ]
    for question, c_ms, cold, h_ms, hit, s_ms, similar in rows:
        u = cold["usage"]
        lines.append(f"| {question} | {c_ms:,.0f} ms | {h_ms:,.0f} ms {'✅ cached' if hit['cached'] else '❌'} | "
                     f"{s_ms:,.0f} ms {'cached' if similar['cached'] else 'miss'} | {u.get('input_tokens', 0):,} / {u.get('output_tokens', 0):,} |")
    lines += [
        "",
        "## Invalidation policy",
        "",
        "- **Embeddings** are keyed by model + SHA-256 of the exact text, so a cached vector can't be stale: an edited",
        "  note is a new key. Unchanged notes and repeated queries are never embedded twice. 30-day TTL bounds memory.",
        "- **Answers** are keyed by user, prompt variant, model, today's date (Manila), the user's data version, the",
        "  question (case and spacing ignored) and the earlier turns. Indexing or removing a note bumps the data version,",
        "  so all of that user's cached answers stop matching at once (this benchmark uses that to start cold). A new day",
        "  starts fresh, because follow-ups move with the date. A 10-minute TTL bounds staleness from contact edits",
        "  (follow-up dates), which don't pass through the AI service.",
        "- **Never cached:** refusals, failures, other users' answers. Input guardrails run on every request, cached or not.",
        "- **If Redis is down**, every lookup is a miss and the service works uncached.",
        "",
        "## Limitations",
        "",
        "- Exact-match only: a reworded question is a miss (row 2). A semantic cache could catch those, but a wrong",
        "  semantic hit would show one contact's answer for a different question; for personal data, exactness won.",
        "- Most questions in real use are new, so the biggest steady win is the embedding cache (re-indexing, repeated",
        "  searches) rather than the answer cache.",
    ]
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text("\n".join(lines) + "\n", encoding="utf-8")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--user", required=True, help="The demo account's user id")
    parser.add_argument("--url", default="http://127.0.0.1:8002")
    parser.add_argument("--out", default="../docs/evidence/caching.md")
    args = parser.parse_args()
    asyncio.run(main(args.user, args.url, Path(args.out)))
