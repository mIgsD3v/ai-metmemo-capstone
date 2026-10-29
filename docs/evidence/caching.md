# Caching: before and after

Measured 2026-10-29 13:22 on a development laptop, through the running AI service (Laravel tools,
Qdrant and Redis local; OpenAI `gpt-4o-mini` and `text-embedding-3-small` remote). Reproduce with
`python -m evals.cache_benchmark` (see its docstring). Costs use list prices of $0.15 / $0.60 per 1M input / output
tokens (chat) and $0.02 per 1M tokens (embeddings); check current prices before quoting them.

## Summary

| Scenario | Before (no cache) | After (cache) | Saving |
|---|---|---|---|
| Same question asked again (×5) | 5,403 ms, $0.00054 | 742 ms, $0 (5/5 served from cache) | 86% faster, 100% of model cost |
| Similar but reworded question (×5) | 5,403 ms | 5,474 ms, $0.00061 (0/5 from cache) | none: exact-match only (see below) |
| Embed a repeated search query | 667 ms | 0.5 ms | 1,334× faster, no API call |
| Re-index all 42 demo notes (unchanged) | 862 ms, 1 API call, ~1,592 tokens | 1.9 ms, 0 API calls | 454× faster, $0.000032 saved |

## Per question

| Question | Cold | Repeat | Reworded | Cold tokens (in / out) |
|---|---|---|---|---|
| Who should I follow up with today? | 7,956 ms | 376 ms ✅ cached | 6,876 ms miss | 3,844 / 210 |
| Who wanted a pet-friendly place? | 4,433 ms | 560 ms ✅ cached | 3,861 ms miss | 2,469 / 65 |
| What did I promise Elena Castillo? | 3,729 ms | 429 ms ✅ cached | 3,501 ms miss | 2,245 / 68 |
| What is Kenji Watanabe's housing allowance? | 5,111 ms | 872 ms ✅ cached | 6,608 ms miss | 3,514 / 82 |
| Draft a follow-up email to Andrea Villanueva about the shortlist I promised | 5,785 ms | 1,473 ms ✅ cached | 6,523 ms miss | 3,725 / 153 |

## Invalidation policy

- **Embeddings** are keyed by model + SHA-256 of the exact text, so a cached vector can't be stale: an edited
  note is a new key. Unchanged notes and repeated queries are never embedded twice. 30-day TTL bounds memory.
- **Answers** are keyed by user, prompt variant, model, today's date (Manila), the user's data version, the
  question (case and spacing ignored) and the earlier turns. Indexing or removing a note bumps the data version,
  so all of that user's cached answers stop matching at once (this benchmark uses that to start cold). A new day
  starts fresh, because follow-ups move with the date. A 10-minute TTL bounds staleness from contact edits
  (follow-up dates), which don't pass through the AI service.
- **Never cached:** refusals, failures, other users' answers. Input guardrails run on every request, cached or not.
- **If Redis is down**, every lookup is a miss and the service works uncached.

## Limitations

- Exact-match only: a reworded question is a miss (row 2). A semantic cache could catch those, but a wrong
  semantic hit would show one contact's answer for a different question; for personal data, exactness won.
- A cached answer still takes a few hundred ms: input guardrails (regex, then the OpenAI moderation call) run
  before the cache lookup on purpose, so a cache hit can never skip a safety check. Moderation is free, so this
  costs latency, not money.
- Most questions in real use are new, so the biggest steady win is the embedding cache (re-indexing, repeated
  searches) rather than the answer cache.
