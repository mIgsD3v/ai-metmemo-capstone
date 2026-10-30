#!/usr/bin/env bash
# Creates .env.production and ai-service/.env.production with fresh random secrets (app key, database
# password, internal token). Run once on the server from the repo root: deploy/setup-env.sh <domain>
# Then add the OpenAI and Langfuse keys by hand with nano. Never commit these files.
set -euo pipefail

domain="${1:?usage: deploy/setup-env.sh <domain>, e.g. 178.128.19.191.nip.io}"

if [ -e .env.production ] || [ -e ai-service/.env.production ]; then
    echo "An .env.production already exists; not overwriting it." >&2
    exit 1
fi

app_key="base64:$(openssl rand -base64 32)"
db_password="$(openssl rand -hex 24)"
internal_token="$(openssl rand -hex 32)"

umask 077

cat > .env.production <<ENV
APP_NAME=MetMemo
APP_ENV=production
APP_KEY=${app_key}
APP_DEBUG=false
APP_URL=https://${domain}
# FrankenPHP/Caddy serves this host and fetches its HTTPS certificate.
SERVER_NAME=${domain}

LOG_CHANNEL=stderr
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=metmemo
DB_USERNAME=metmemo
DB_PASSWORD=${db_password}
POSTGRES_DB=metmemo
POSTGRES_USER=metmemo
POSTGRES_PASSWORD=${db_password}

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
MAIL_MAILER=log

AI_SERVICE_URL=http://ai:8002
AI_SERVICE_TOKEN=${internal_token}
AI_INDEX_NOTES=true
ENV

cat > ai-service/.env.production <<ENV
APP_ENV=production
INTERNAL_TOKEN=${internal_token}

# Fill these in with nano (same values as your local ai-service/.env):
OPENAI_API_KEY=
LANGFUSE_PUBLIC_KEY=
LANGFUSE_SECRET_KEY=
LANGFUSE_HOST=https://us.cloud.langfuse.com

CHAT_MODEL=gpt-4o-mini
EMBEDDING_MODEL=text-embedding-3-small
EMBEDDING_DIMENSIONS=1536
ASSISTANT_PROMPT=grounded
QDRANT_URL=http://qdrant:6333
REDIS_URL=redis://redis:6379/0
# The assistant's tools call the app's /internal API through its public URL (token-protected).
LARAVEL_URL=https://${domain}
ENV

echo "Created .env.production and ai-service/.env.production for https://${domain}"
echo "Next: nano ai-service/.env.production  (add OPENAI_API_KEY and the LANGFUSE keys)"
