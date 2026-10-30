# Evaluation

How MetMemo's follow-up assistant was evaluated, what the baseline showed, the improvement iteration, and the
result. Model `gpt-4o-mini`, temperature 0, prompt `grounded`, seeded demo account (a real-estate broker's 25
contacts and 42 notes, `database/seeders/DemoContactsSeeder.php`).

## What is measured

| Suite | Dataset | Metrics |
|---|---|---|
| RAG | 20 golden questions with reference answers and the notes that hold them ([golden_set.json](../../ai-service/evals/golden_set.json)); 13 name a contact, 7 only describe a topic | **RAGAS**: faithfulness, answer relevancy, context precision, context recall. **Retrieval** (Qdrant alone, no LLM): hit@5, recall@5, MRR |
| Agent | 15 scenarios ([scenarios.json](../../ai-service/evals/scenarios.json)): 7 normal tasks, 8 failure cases (ambiguous name, contact without notes, unknown contact, mid-conversation question, off-topic question, injection in a note, direct injection, third-party PII) | **Tool-selection accuracy**, **task completion** (automatic checks per scenario) |

Each question runs the same steps as `POST /assistant/chat` (input guardrails, agent, output guardrails), without
the answer cache. Runner: [`evals/run_eval.py`](../../ai-service/evals/run_eval.py). A run costs about $0.02 in
assistant tokens plus the RAGAS judge.

**Langfuse.** Every case is one trace tagged `eval:rag` or `eval:agent`, `run:<label>` and `case:<id>`, with its
scores attached (faithfulness, context recall, tool_selection, task_completed...). Filtering by `run:baseline`
vs `run:improved` shows each case's tool calls, retrieved notes and model calls side by side.

## Result: baseline → improved

| Metric | Baseline | Improved | Change |
|---|---|---|---|
| Faithfulness | 0.90 | 0.91 | +0.02 |
| Answer relevancy | 0.70 | 0.67 | -0.02 |
| Context precision | 0.85 | 0.88 | +0.03 |
| Context recall | 0.95 | 0.95 | 0.00 |
| Retrieval recall@5, named questions | 0.89 | **1.00** | +0.11 |
| Retrieval MRR | 0.91 | **1.00** | +0.09 |
| Tool-selection accuracy | 14/15 | **15/15** | +1 |
| Task completion | 13/15 | **15/15** | +2 |
| Failure cases handled | 7/8 | **8/8** | +1 |

Details: [eval-baseline.md](eval-baseline.md), [eval-improved.md](eval-improved.md),
[eval-comparison.md](eval-comparison.md) (generated), every answer included.

## The iteration

The baseline failed on three things, each traced to a root cause in the [failure log](failure-log.md):

| Failure | Root cause | Fix |
|---|---|---|
| **F1** "What did I promise Elena Castillo?": her note was not in the top 5 | Only the note text was embedded; Elena's note never says "Elena" | Embed `"{contact name}: {note}"`, re-index |
| **F7** "Who is the current president?" answered from model knowledge | No rule said what is out of scope | Scope rule in the `grounded` prompt |
| **F8** "Raffy Mendoza" not found (saved as `Rafael "Raffy" Mendoza`) | Contact search used one `LIKE` over the whole phrase | Match each word, case-insensitively |

**The first attempt regressed** ([eval-comparison-v1.md](eval-comparison-v1.md)). The scope rule, "only help with
contacts, notes and follow-ups", made the model refuse real client questions about money and family (Kenji's
housing allowance, what Hannah can afford, how to reach Anton's brother): context recall fell from 0.95 to 0.82.
Rewording the rule to say what *is* in scope, and to check the tools when unsure, removed the regression. This is
why the evaluation runs after every change: the fix for one failure caused three others, and only the numbers
showed it.

**The measurement was debugged too (F9).** The first baseline gave faithfulness 0.74, with correct answers
scoring 0. The judge was given note text without the contact's name, while the model had the name from its tools.
Fixing the contexts gave 0.90 on the unchanged app; that corrected run is the baseline above.

## Limitations

- **One over-refusal remains:** q06 ("What yield did the ROI sheet I sent Raffy Mendoza show?") is still treated
  as off-topic. Not tuned further, to avoid fitting the prompt to the test set.
- **Small datasets.** 20 questions and 15 scenarios over one demo account: enough to catch the failures above,
  not to estimate rates precisely. A ±0.02 change in a RAGAS average is noise.
- **Same model judges and answers** (`gpt-4o-mini`), which can be lenient with its own style. Answer relevancy in
  particular scores detailed answers lower ("Who wanted a pet-friendly place?" → 0.43 for a correct answer).
- **Single runs at temperature 0.** Model updates can change results; rerun before quoting numbers.
