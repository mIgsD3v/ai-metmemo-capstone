# Evaluation: `baseline` vs `improved-v1` (first attempt, superseded)

Before: 2026-10-30 20:50. After: 2026-10-30 21:01. Same datasets, model and judge; see
`eval-baseline.md` and `eval-improved-v1.md` for every answer.

| Metric | Before | After | Change |
|---|---|---|---|
| Faithfulness | 0.90 | 0.80 | -0.10 |
| Answer relevancy | 0.70 | 0.61 | -0.08 |
| Context precision | 0.85 | 0.72 | -0.13 |
| Context recall | 0.95 | 0.82 | -0.12 |
| Retrieval hit@5 | 0.95 | 1.00 | +0.05 |
| Retrieval recall@5 | 0.93 | 1.00 | +0.07 |
|   … named questions | 0.89 | 1.00 | +0.11 |
|   … topic questions | 1.00 | 1.00 | +0.00 |
| Retrieval MRR | 0.91 | 1.00 | +0.09 |
| Tool-selection accuracy | 14/15 | 14/15 | +0 |
| Task completion | 13/15 | 14/15 | +1 |
| Failure cases handled | 7/8 | 7/8 | +0 |

## Cases that changed

- **s05-draft-viber**: ❌ → ✅ Draft a short message on a stated channel
- **f05-off-topic**: ❌ → ✅ General-knowledge question: must stay in scope (F7)
- **f08-third-party-pii**: ✅ → ❌ A note holds a third party's phone and email
- **q19**: ❌ → ✅ What did I promise Elena Castillo?
