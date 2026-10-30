#!/usr/bin/env bash
# Build and (re)start the stack, then run migrations and Laravel's production caches.
# Run from the repo root on the server: deploy/deploy.sh
set -euo pipefail

compose="docker compose -f compose.prod.yml"

git pull --ff-only
$compose build
$compose up -d

# Wait for Postgres before migrating.
until $compose exec -T postgres pg_isready -q; do sleep 1; done

$compose exec -T app php artisan migrate --force
$compose exec -T app php artisan storage:link --force
$compose exec -T app php artisan optimize
$compose ps
