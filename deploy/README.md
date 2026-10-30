# Deployment

MetMemo runs on one DigitalOcean droplet (Ubuntu 24.04, 2 GB RAM, Singapore) with Docker Compose
([compose.prod.yml](../compose.prod.yml)). Live at **https://178.128.19.191.nip.io** (nip.io maps the name to the
droplet's IP, so Caddy can get a real HTTPS certificate without buying a domain).

```
Internet ──443──> app (FrankenPHP: Caddy + Laravel) ──> postgres
                     │   ▲
          AI_SERVICE_URL │ LARAVEL_URL (/internal, token)
                     ▼   │
                    ai (FastAPI) ──> qdrant (notes vectors), redis (cache), OpenAI, Langfuse
```

Only ports 22, 80 and 443 are open. Postgres, Redis, Qdrant and the AI service have no public ports.

## First deploy

```bash
# 1. Server basics: swap (the 2 GB droplet needs it for the build), firewall, Docker
fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
ufw allow OpenSSH && ufw allow 80 && ufw allow 443 && ufw --force enable
curl -fsSL https://get.docker.com | sh

# 2. Code and secrets
git clone https://github.com/mIgsD3v/ai-metmemo-capstone.git /opt/metmemo && cd /opt/metmemo
deploy/setup-env.sh 178.128.19.191.nip.io   # generates app key, DB password, internal token
nano ai-service/.env.production             # add OPENAI_API_KEY and the LANGFUSE keys

# 3. Build and start (first build: about 10 minutes)
deploy/deploy.sh

# 4. Demo data: the seeded broker account (test@example.com / password) and its notes index
docker compose -f compose.prod.yml exec app php artisan db:seed --force
docker compose -f compose.prod.yml exec app php artisan metmemo:index-notes
```

## Updates

```bash
cd /opt/metmemo && deploy/deploy.sh
```

## Operations

| Task | Command (from `/opt/metmemo`) |
|---|---|
| Status | `docker compose -f compose.prod.yml ps` |
| Logs (guardrail blocks, cache hits, errors) | `docker compose -f compose.prod.yml logs -f ai app` |
| Restart one service | `docker compose -f compose.prod.yml restart ai` |
| Re-index notes | `docker compose -f compose.prod.yml exec app php artisan metmemo:index-notes` |
| Clear the AI cache | `docker compose -f compose.prod.yml exec redis redis-cli FLUSHDB` |

**Secrets** are only in `.env.production` and `ai-service/.env.production` on the server (mode 600, git-ignored).
To rotate the internal token, change `AI_SERVICE_TOKEN` and `INTERNAL_TOKEN` to the same new value and run
`deploy/deploy.sh`. Logs go to stdout (`LOG_CHANNEL=stderr`); the AI service masks PII before logging and tracing.
