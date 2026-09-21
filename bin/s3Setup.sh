#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${1:-.env}"

# Parse .env
get() { grep -m1 "^$1=" "$ENV_FILE" | cut -d= -f2-; }

ACCESS_KEY=$(get AWS_ACCESS_KEY_ID)
SECRET_KEY=$(get AWS_SECRET_ACCESS_KEY)
ENDPOINT=$(get AWS_ENDPOINT)
BUCKET=$(get AWS_BUCKET)
PATH_STYLE=$(get AWS_USE_PATH_STYLE_ENDPOINT)

# Normalize path style to 1/0
[[ "${PATH_STYLE,,}" == "true" ]] && PATH_STYLE=1 || PATH_STYLE=0

php artisan p:bucket:make \
  --access-key="$ACCESS_KEY" \
  --secret-key="$SECRET_KEY" \
  --endpoint="$ENDPOINT" \
  --bucket-name="$BUCKET" \
  --use-path-style-endpoint="$PATH_STYLE" \
  --enabled=1 \
  --name="Nebulodactyl" \
  --description="Nebulodactyl"
