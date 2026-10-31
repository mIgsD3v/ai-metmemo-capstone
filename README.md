# MetMemo: Every Meeting, Remembered

A digital business card with an AI assistant that remembers everyone you meet and tells you when to follow up.

**Live:** https://metmemo.app · demo login **test@example.com / password** (a real-estate broker with
25 contacts and 42 meeting notes; all data is made up)

## The problem

Professionals who network (brokers, agents, consultants, founders) meet many people at events and lose most of
them. Business cards end up in a drawer, the "I'll send you the brochure" promise is forgotten, and the follow-up
that would have closed a deal never happens. CRMs exist, but they are built for sales teams and are too heavy for
one person who just wants to remember who they met, what was said, and who to message today.

MetMemo is that lighter tool: share your own card by QR code, save the people you meet (scan their card or let
them leave their details on yours), jot a note after each conversation, and ask the assistant what to do next.

### Why generative AI, and where it isn't used

| Job | Approach | Why |
|---|---|---|
| Read a photographed business card | Vision LLM (`gpt-4o-mini`) → validated JSON | Card layouts vary too much for templates or plain OCR |
| "Who wanted a pet-friendly unit?" | RAG: semantic search over the user's notes (Qdrant) | Notes are free text; keyword search misses "allows dogs" |
| "Who should I follow up with?", "What did I promise Elena?" | Agent with tools that read the database | The answer needs real dates and records, not the model's memory |
| Draft a follow-up email or Viber message | LLM, grounded in that contact's notes, with citations | Personal drafts in seconds; the user reviews and sends them |
| Due dates, reminders, exports, QR cards | Plain code | Deterministic jobs don't need a model |

The assistant never sends anything and never chooses whose data to read: it drafts, and the user decides.

### Data

- **The user's own data:** contacts and the notes they write (or that visitors leave through their public card),
  stored in PostgreSQL. Notes are also embedded (`text-embedding-3-small`) into Qdrant, filtered by user, so
  one user's search can never return another user's notes.
- **Demo and evaluation data:** a seeded, fictional broker account ([DemoContactsSeeder](database/seeders/DemoContactsSeeder.php)),
  a hand-written golden set of 20 questions with reference answers, and 15 agent scenarios
  ([ai-service/evals/](ai-service/evals/)).
- **Privacy:** phone numbers and emails are masked before the model sees notes, in answers, and in Langfuse
  traces. Secrets are environment variables only ([.env.example](.env.example), [ai-service/.env.example](ai-service/.env.example)).

## What's in it

- **Cards:** create digital business cards, share them by QR code or link, vCard download.
- **Contacts:** scan a card with the camera, capture leads from your public card, notes, follow-up dates, a
  daily reminder, export to Excel, CSV or vCard, an insights page.
- **Assistant:** chat about your contacts. It finds who is due, answers from your notes with citations
  (`[N1]`), and drafts follow-up emails or Viber messages. Chats are saved and can be continued.

## Architecture

```
Browser ──HTTPS──> Laravel 12 (Inertia + Vue 3) ──> PostgreSQL
                      │      ▲
   X-Internal-Token   │      │  /internal API (the assistant's tools read contacts and notes)
                      ▼      │
                 FastAPI AI service ──> OpenAI (gpt-4o-mini, embeddings, moderation)
                      ├──> Qdrant (note vectors)    ├──> Redis (cache)    └──> Langfuse (traces)
```

| Part | Folder | Responsibility |
|---|---|---|
| Web app | `/` (Laravel 12 + Jetstream) | Auth, cards, contacts, QR, vCard, the Assistant page |
| AI service | [ai-service/](ai-service/) (FastAPI) | Card scanner, notes index, the follow-up agent, guardrails, cache, tracing |

Laravel never calls OpenAI directly. All AI goes through `App\Services\Ai\AiServiceClient` to the FastAPI
service, authenticated with a shared `X-Internal-Token` header.

**The assistant** ([ai-service/app/agent/](ai-service/app/agent/)) is an OpenAI function-calling loop
(temperature 0, at most 5 steps) with five tools: `get_overdue_followups`, `find_contact`, `get_contact_notes`,
`search_notes` (RAG) and `draft_followup_message`. Each request goes:

1. Input guardrails: injection patterns, bulk-data requests, moderation, PII masking.
2. Answer cache (Redis), keyed by user, prompt, model, date, the user's data version and the question.
3. Agent loop. Tool results are PII-masked and note text is labelled as data, not instructions.
4. Output guardrails: moderation, PII, citation checks (only notes the tools returned; drafts only from that contact's notes).
5. Everything is traced in Langfuse.

**Laravel layering:** Controller → Service → Repository → Model. Repository interfaces are bound in
`App\Providers\RepositoryServiceProvider`.

## Evidence

| Requirement | Where | Headline |
|---|---|---|
| Prompt variants (3) | [prompt-decision.md](docs/evidence/prompt-decision.md), [prompt-comparison.md](docs/evidence/prompt-comparison.md) | `baseline` 4/10, `structured` 5/10, **`grounded` 10/10**, kept as the default |
| Evaluation: RAGAS + agent | [evaluation.md](docs/evidence/evaluation.md) | 20-question golden set, 15 scenarios (8 failure cases), traced in Langfuse |
| Improvement iteration | [evaluation.md](docs/evidence/evaluation.md), [eval-comparison.md](docs/evidence/eval-comparison.md) | Task completion 13/15 → **15/15**; retrieval recall@5 (named) 0.89 → **1.00**; a first attempt that regressed is documented too |
| Debugged failures | [failure-log.md](docs/evidence/failure-log.md) | F1–F9: symptom, root cause, fix, proof |
| Guardrails | [guardrails.md](docs/evidence/guardrails.md) | Blocked injection with 0 model calls; logs and tests |
| Caching | [caching.md](docs/evidence/caching.md) | Repeated question 5.4 s → 0.7 s at $0; query embedding 667 ms → 0.5 ms; TTL + data-version invalidation |
| Deployment | [deploy/README.md](deploy/README.md) | Docker Compose on a DigitalOcean droplet, automatic HTTPS, only 80/443 public |
| Architecture | [docs/architecture.md](docs/architecture.md) | Workflow, system, Laravel layers, one assistant request, database (diagrams) |

## Run locally

Needs PHP 8.3, Composer, Node 22, PostgreSQL, Python 3.13+, a Qdrant server (on 6333) and, optionally, Redis.

```bash
# Laravel
composer install && npm install
cp .env.example .env && php artisan key:generate     # set DB_* and AI_SERVICE_TOKEN
php artisan migrate:fresh --seed     # demo login: test@example.com / password
composer run dev                     # app on :8000, Vite, and a second server on :8003 for the AI service's calls

# AI service
cd ai-service
python -m venv .venv && .venv/Scripts/activate   # macOS/Linux: source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env                 # OPENAI_API_KEY, INTERNAL_TOKEN (= AI_SERVICE_TOKEN), LANGFUSE_* optional
uvicorn app.main:app --port 8002
php artisan metmemo:index-notes      # (from the repo root) embed the demo notes into Qdrant
```

Why the second Laravel server on 8003: `php artisan serve` answers one request at a time, and the main one is busy
waiting for the assistant while the assistant's tools call back into Laravel (failure F6). Production uses
FrankenPHP, which serves requests in parallel.

## Tests and evaluation

```bash
php artisan test                     # Laravel feature tests
cd ai-service && python -m pytest    # AI service: scanner, notes, assistant, guardrails, cache

# Evaluation (needs the services running; costs a few cents). Install: see requirements-eval.txt
python -m evals.compare_prompts --user <demo user id>
python -m evals.run_eval --user <demo user id> --label improved --compare baseline
python -m evals.cache_benchmark --user <demo user id>
```

## Limitations

- **Small evaluation sets** over one fictional account: enough to find and fix failures, not to estimate rates
  precisely. The same model (`gpt-4o-mini`) answers and judges.
- **One known over-refusal:** "What yield did the ROI sheet I sent Raffy Mendoza show?" is treated as off-topic
  (F7). Left as is rather than fitting the prompt to one test question.
- **Saved chats keep copies of note text**, so deleting a note doesn't remove it from old conversations.
- **Single small server:** no replicas or managed database; backups are the droplet's own. Fine for a demo,
  not for real customers.
- **The cache is exact-match only:** a reworded question is a miss, by design (a wrong cached answer is worse
  than a slow one).
