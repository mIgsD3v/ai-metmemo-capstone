# Failure log

Failures found while building and testing the assistant: what happened, the root cause, the fix, and
the proof. The capstone's "debugged failure" and "targeted improvement" come from here.

## F1. Asked about a person by name, the assistant searched by topic and found nothing (open)

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

**Planned fix (to measure in the evaluation, Oct 29).**
- Embed the contact's name with the note (`note_text()` returns `"{contact_name}: {body}"`) and re-index.
- Measure the RAG metrics and the agent scenarios before and after. Langfuse traces show the tool choice
  and the retrieved notes for this question, before and after.

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

## F4. Stale server answered with an old token (fixed, operations)

**Found:** 2026-10-25. Swagger kept returning `401 Invalid internal token` although both `.env` files matched.

**Root cause.** A uvicorn server from earlier in the day was never stopped. Its worker process kept port 8002
open, and Windows let the new server bind the same port, so requests reached the old process and its old token.

**Fix.** Stopped the orphaned worker. One listener was left, and the same request returned 200.
Check with `netstat -ano | grep ":8002 " | grep LISTEN`: there should be one line.
