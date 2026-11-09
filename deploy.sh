#!/usr/bin/env bash
# Deploys the dcc-guide system. Executed by the GitHub Actions
# self-hosted runner after every push to main.
set -euo pipefail
cd /opt/dcc-guide

echo "==> Pulling latest code"
git pull --ff-only origin main

echo "==> Rebuilding & restarting containers"
docker compose up -d --build

echo "==> Running database migrations"
docker compose exec -T app php artisan migrate --force

echo "==> Pruning dangling images"
docker image prune -f

echo "==> Health check (retrying up to 60s)"
OK=""
for i in $(seq 1 12); do
  if curl -sf -o /dev/null http://192.168.1.2:8086/; then OK=1; break; fi
  echo "   attempt $i: not responding yet, waiting 5s..."
  sleep 5
done
if [ -n "$OK" ]; then
  echo "Health check OK - dcc-guide responding on :8086"
else
  echo "Health check FAILED after 60s - dcc-guide not responding" >&2
  exit 1
fi
echo "==> Deploy complete"
