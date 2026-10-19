# MetMemo: Every Meeting, Remembered

A digital business card with an AI assistant that remembers everyone you meet and tells you when to follow up.

## Repository layout

```
/                 Laravel 12 + Jetstream (Inertia + Vue 3): auth, cards, contacts, QR, vCard
ai-service/       FastAPI (Python): card scanner, follow-up assistant (tools + RAG over notes)
```

Laravel never calls OpenAI directly. All AI goes through `App\Services\Ai\AiServiceClient` to the FastAPI
service, authenticated with a shared `X-Internal-Token` header.

## Laravel architecture

Requests flow **Controller → Service → Repository → Model**:

| Layer | Folder | Responsibility |
|---|---|---|
| Model | `app/Models` | Eloquent models, relations, casts |
| Repository | `app/Repositories/Contracts` (interfaces), `app/Repositories/Eloquent` | All queries and persistence |
| Service | `app/Services` | Business rules: slugs, transactions, lead capture, vCard, AI client |
| Controller | `app/Http/Controllers` | Authorize (policies), validate (form requests), call a service, render Inertia |

Repository interfaces are bound in `App\Providers\RepositoryServiceProvider`.

## Run locally

```bash
# Laravel
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed     # demo login: test@example.com / password
composer run dev

# AI service
cd ai-service
python -m venv .venv && .venv/Scripts/activate   # macOS/Linux: source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
uvicorn app.main:app --reload --port 8001
```

Demo public card: open **My Cards** and use **Open page** (card links are `/c/<uuid>`).

## Tests

```bash
php artisan test
cd ai-service && python -m pytest
```
