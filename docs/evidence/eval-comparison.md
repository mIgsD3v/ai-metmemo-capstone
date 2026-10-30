# Evaluation: `baseline` vs `improved`

Before: 2026-10-30 20:50. After: 2026-10-30 21:08. Same datasets, model and judge; see
`eval-baseline.md` and `eval-improved.md` for every answer.

| Metric | Before | After | Change |
|---|---|---|---|
| Faithfulness | 0.90 | 0.91 | +0.02 |
| Answer relevancy | 0.70 | 0.67 | -0.02 |
| Context precision | 0.85 | 0.88 | +0.03 |
| Context recall | 0.95 | 0.95 | +0.00 |
| Retrieval hit@5 | 0.95 | 1.00 | +0.05 |
| Retrieval recall@5 | 0.93 | 1.00 | +0.07 |
|   … named questions | 0.89 | 1.00 | +0.11 |
|   … topic questions | 1.00 | 1.00 | +0.00 |
| Retrieval MRR | 0.91 | 1.00 | +0.09 |
| Tool-selection accuracy | 14/15 | 15/15 | +1 |
| Task completion | 13/15 | 15/15 | +2 |
| Failure cases handled | 7/8 | 8/8 | +1 |

## Cases that changed

- **s05-draft-viber**: ❌ → ✅ Draft a short message on a stated channel
- **f05-off-topic**: ❌ → ✅ General-knowledge question: must stay in scope (F7)
- **q19**: ❌ → ✅ What did I promise Elena Castillo?
