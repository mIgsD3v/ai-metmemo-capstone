# Failure log

Failures found while building and testing the assistant: what happened, the root cause, the fix, and
the proof. The capstone's "debugged failure" and "targeted improvement" come from here.

## F1. Asked about a person by name, the assistant searched by topic and found nothing (fixed)

**Found:** 2026-10-26, manual test against the seeded demo account, model gpt-4o-mini, prompt `grounded`.

**What happened.** In the middle of a conversation (after drafting a text to Juan), the user asked:

> What did I promise Elena?

The assistant called `search_notes` instead of `get_contact_notes`, and answered:

> I couldn't find any notes regarding a promise made to someone named Elena. It seems there are no relevant records in your notes.

But Elena Castillo's note says: *"Seminar confirmed: November 20, 2 PM ... I promised to send the outline and a sign-up form."*
Asked in a fresh conversation, the same question worked: `find_contact`, then `get_contact_notes`, then the right answer citing the note.

**Root cause (two parts).**
1. *Tool selection.* With chat history present, the model treated "promise" as a topic and chose semantic
   search, although the prompt says a person's name goes to `get_contact_notes`.
2. *Retrieval blind spot.* The notes index embeds only the note text (`note_text()` in
   `ai-service/app/notes_index.py`). The contact's name is stored as metadata, not embedded, and Elena's note
   never says "Elena". So a query naming a person can't find that person's notes by meaning.

**Fix (2026-10-30).** `note_text()` now embeds `"{contact_name}: {body}"`; the demo notes were re-indexed.
Measured with the golden set's retrieval metrics (Qdrant search alone, no LLM):

| | Before | After |
|---|---|---|
| Retrieval recall@5, questions naming a contact | 0.89 | 1.00 |
| Retrieval MRR (all 20) | 0.91 | 1.00 |
| q19 "What did I promise Elena Castillo?" top-5 | no Elena note | both Elena notes, ranks 1 and 2 |

Scenario f04 (the mid-conversation question) passed in both runs: with temperature 0 and the F3 fix, the model
now picks `get_contact_notes`. The retrieval fix makes `search_notes` correct too when it is chosen.

*Debugging note.* The first re-index changed nothing: two old `uvicorn --reload` workers were still serving port
8002 with the old code (killing a reloader leaves its worker running, as in F4). Found by comparing the stored
vector with fresh embeddings of the old and new text (0.9998 vs 0.88), then stopping the orphaned workers.

## F7. An off-topic question was answered from the model's own (outdated) knowledge (fixed, one case open)

**Found:** 2026-10-29, manual test on the Assistant page, model gpt-4o-mini, prompt `grounded`.

**What happened.** The user asked:

> who is the current president in philippines?

The assistant called no tool and answered:

> As of October 2023, the current president of the Philippines is Ferdinand "Bongbong" Marcos Jr. ...

**Why it matters.** MetMemo's assistant is for the owner's contacts and notes. An answer from the model's
training data is outside that scope, can't cite a note, and can be out of date ("As of October 2023"), so it
breaks the grounding promise the rest of the prompt makes. The input guardrails let it through correctly:
the question is neither unsafe nor an injection, just out of scope.

**Root cause.** No prompt variant says what the assistant must *not* answer. The `grounded` rules ("if the
notes don't say, answer I don't know") only cover questions about notes; a general-knowledge question never
triggers them.

**Fix, in two attempts (2026-10-30).** Baseline: scenario f05 ❌ (the model answered "I don't know." instead
of saying what it can help with).
1. *First attempt* (`eval-improved-v1.md`): a scope rule, "only help with contacts, notes and follow-ups". f05
   passed, but the model now refused real questions about clients: Kenji's housing allowance (q15), what Hannah
   can afford (q18), how to reach Anton's brother (f08). It read money and family questions as general
   knowledge. Context recall fell from 0.95 to 0.82 and task completion only rose by one. **Over-refusal:** a
   tighter guardrail broke the main job.
2. *Second attempt* (kept): the rule says what *is* in scope (anything mentioning a contact, including money,
   family and how to reach someone), lists what is out (news, trivia, homework, coding), and says "if unsure,
   check the tools first". f05 ✅, f08 ✅ again, q15 and q18 answered again.

**Still open:** q06 ("What yield did the ROI sheet I sent Raffy Mendoza show?") is still refused as off-topic, so
1 of 20 golden questions is over-refused. Not tuned further on purpose: more prompt edits aimed at one test
question would overfit the evaluation. A next step would be a small classifier or a "call a tool before
refusing" check in code rather than in the prompt.

## F8. A contact asked about by nickname was "not found" (fixed)

**Found:** 2026-10-30, baseline evaluation (`eval-baseline.md`): golden question q06 and scenario s05.

**What happened.** "What yield did the ROI sheet I sent Raffy Mendoza show?" was answered with
*"I couldn't find any contact named Raffy Mendoza"*, and "Write a short Viber message to Raffy Mendoza..." ended
with the assistant asking which Mendoza was meant, without drafting anything. The contact exists, saved as
`Rafael "Raffy" Mendoza`.

**Root cause.** `find_contact` searches with one `LIKE '%Raffy Mendoza%'` over the whole phrase, so the words must
appear side by side, in that order. "Raffy Mendoza" never does in `Rafael "Raffy" Mendoza`. The model then retried
with just "Mendoza", got two matches (Raffy and Liza) and asked. `LIKE` is also case-sensitive on PostgreSQL, so
"raffy" would fail even alone.

**Fix (2026-10-30).** `ContactRepository::whereMatches()` matches every word separately (each must appear in the
name, company, email or event), case-insensitively (`whereLike`). New test:
`test_contact_search_matches_each_word_in_any_order_and_case`. Scenario s05 now drafts the Viber message to Raffy
(❌ → ✅). The contacts page search gets the same improvement.

## F9. The evaluation judged answers against note text without the contact's name (fixed, evaluation)

**Found:** 2026-10-30, first baseline run. Faithfulness was 0.74, with correct answers scoring 0.00 (q03 "Juan
Miguel Cruz can pre-qualify buyers within 48 hours", q08 "The client with bad knees is Grace Tan").

**Root cause.** The RAGAS contexts were the note bodies alone. The agent's tools return each note *with* the
contact's name, so the model rightly said "Grace Tan", but the judge saw a context that never names her and
marked the claim unsupported. The same blind spot as F1, in the measurement instead of the index.

**Fix.** Contexts are now what the model saw: `Note about <contact>: <note>`. Rerunning the unchanged app gave
faithfulness 0.90; that corrected run is the baseline. Lesson: check low scores by reading the answer and the
context before trusting the metric.

## F2. A contact with no notes was reported as a failure to check (fixed)

**Found:** 2026-10-26. "What does Isabel Navarro want?" (she has no notes) once answered
*"I couldn't check Isabel Navarro's notes at the moment."* The tool had returned `notes: []` correctly.

**Root cause.** Two things together: sampling at temperature 0.2 made the answer vary between runs, and the
empty result could be read as an error, because the prompt says to report tool errors.

**Fix.** Temperature 0, and the empty result now says *"This contact has no notes yet (this is not an
error)"*. Three runs out of three then answered that the notes don't say yet. Covered by
`test_a_contact_with_no_notes_is_reported_not_invented`.

## F3. Follow-up turns guessed a contact id and burned the step budget (fixed)

**Found:** 2026-10-26. After "Which Juan?" and the answer "the first one", the agent made up a `contact_id`,
got an error, then looked Juan up again. It used all 5 steps for a simple draft.

**Root cause.** The chat history carries names, not ids, and `get_contact_notes` accepted only an id.
A prompt rule ("never make up a contact_id") didn't stop it.

**Fix.** `get_contact_notes` also accepts a name, and resolves it when exactly one contact matches. The same
turn went from 5 steps to 3. Covered by `test_notes_can_be_read_by_name_in_one_step`.

## F5. "Who should I follow up with today?" left out every overdue contact (fixed)

**Found:** 2026-10-26, prompt comparison run 1. All three prompt variants failed this case.

**What happened.** The answer listed only the 2 people due exactly today (Jessa, Kenji) and left out the
10 overdue people, who are the most urgent. The tool returned all 12 in one `contacts` list, and the model filtered
it on the word "today".

**Root cause.** Every variant failed, so the cause wasn't the prompt. It was the tool's output: one flat list put
the urgency judgement on the model.

**Fix.** `get_overdue_followups` returns `overdue`, `due_today` and `upcoming` separately, with a note that overdue
contacts are the most urgent. Comparison run 2: the case passes for all three variants. Covered by
`test_followups_are_split_by_urgency`.

## F6. The Assistant page deadlocked on Laravel's dev server (fixed, operations)

**Found:** 2026-10-26, first end-to-end test through the Assistant page (browser → Laravel → AI service).

**What happened.** "Who should I follow up with today?" took **27 s** and answered *"I couldn't check for overdue
follow-ups at the moment."* A question that only used `search_notes` (Qdrant) worked in 6.5 s.

**Root cause.** The request goes browser → Laravel `/assistant/ask` → AI service → back to Laravel
`/internal/.../followups`. `php artisan serve` (PHP's built-in server) answers one request at a time, and it can't run
extra workers on Windows. It was still busy with `/assistant/ask`, so the AI service's call back waited in line until
it timed out. The tool turned the timeout into an error message, so the assistant degraded gracefully instead of crashing.

**Fix.** A second dev server only for the AI service's internal calls: `php artisan serve --port=8003` (added to
`composer run dev` as "internal"), with `LARAVEL_URL=http://127.0.0.1:8003` for the AI service. The same question then
answered with real data in **6.8 s**. In production, php-fpm handles requests in parallel, so this is a
development-only setup.

## F4. Stale server answered with an old token (fixed, operations)

**Found:** 2026-10-25. Swagger kept returning `401 Invalid internal token` although both `.env` files matched.

**Root cause.** A uvicorn server from earlier in the day was never stopped. Its worker process kept port 8002
open, and Windows let the new server bind the same port, so requests reached the old process and its old token.

**Fix.** Stopped the orphaned worker. One listener was left, and the same request returned 200.
Check with `netstat -ano | grep ":8002 " | grep LISTEN`: there should be one line.
