#!/bin/sh
set -eu
docker compose config --quiet
docker compose up --build -d --wait
docker compose exec -T app php artisan migrate:status --no-interaction
docker compose exec -T app php artisan schedule:list --no-interaction
docker compose exec -T app php artisan scanner:diagnose --no-interaction
