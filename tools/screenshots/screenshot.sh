#!/usr/bin/env bash
# Captures panel screenshots into showcase/.
#
# Spins up the panel on a throwaway sqlite database, seeds demo data,
# then drives a headless Chromium through the login page, dashboard,
# and server console.
#
# Requirements: php 8.4+ with pdo_sqlite, composer dependencies installed,
# frontend assets built (pnpm build), node, and a Chromium binary.
#
# A mock daemon (mock-daemon.mjs) runs alongside the panel so the console
# and resource views show a live server.
#
# Usage:
#   tools/screenshots/screenshot.sh
#
# Environment:
#   PORT              port for the temporary panel (default 8899)
#   MOCK_DAEMON_PORT  port for the mock daemon (default 8898)
#   CHROMIUM_PATH     path to a chromium/chrome binary

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

PORT="${PORT:-8899}"
MOCK_DAEMON_PORT="${MOCK_DAEMON_PORT:-8898}"
DB_FILE="$(mktemp -u /tmp/nebulodactyl-shots.XXXXXX.sqlite)"
SERVER_PID=""
DAEMON_PID=""

cleanup() {
    [ -n "$SERVER_PID" ] && kill "$SERVER_PID" 2>/dev/null || true
    [ -n "$DAEMON_PID" ] && kill "$DAEMON_PID" 2>/dev/null || true
    rm -f "$DB_FILE"
}
trap cleanup EXIT

info() { printf '==> %s\n' "$1"; }
die()  { printf 'error: %s\n' "$1" >&2; exit 1; }

php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);' \
    || die "php extension pdo_sqlite is not loaded (tip: PHP_INI_SCAN_DIR or php -d extension=pdo_sqlite)"
command -v node >/dev/null || die "node is not installed"
[ -f vendor/autoload.php ] || die "run composer install first"
[ -d public/build ] || die "run pnpm build first"

if [ ! -d node_modules/.pnpm ] || [ ! -e tools/screenshots/node_modules/playwright-core ]; then
    info "installing playwright-core"
    pnpm install --ignore-scripts
fi

export APP_ENV=local APP_DEBUG=false
APP_KEY="base64:$(head -c32 /dev/urandom | base64)"
export APP_KEY
export DB_CONNECTION=sqlite DB_DATABASE="$DB_FILE"
export CACHE_DRIVER=file SESSION_DRIVER=file QUEUE_CONNECTION=sync
export APP_URL="http://127.0.0.1:${PORT}"
export MOCK_DAEMON_PORT

touch "$DB_FILE"

info "migrating and seeding database"
php artisan migrate --seed --force --quiet

info "creating demo data"
SERVER_ID="$(php tools/screenshots/seed.php | grep '^SERVER_ID=' | cut -d= -f2)"
[ -n "$SERVER_ID" ] || die "demo seed did not return a server id"

info "starting mock daemon on port $MOCK_DAEMON_PORT"
node tools/screenshots/mock-daemon.mjs "$MOCK_DAEMON_PORT" >/tmp/nebulodactyl-shots-daemon.log 2>&1 &
DAEMON_PID=$!

info "serving panel on port $PORT"
php artisan serve --port="$PORT" >/tmp/nebulodactyl-shots-serve.log 2>&1 &
SERVER_PID=$!

TRIES=0
until curl -fsS "http://127.0.0.1:${PORT}/healthz" >/dev/null 2>&1 \
    || curl -fsS -o /dev/null "http://127.0.0.1:${PORT}/auth/login" 2>/dev/null; do
    TRIES=$((TRIES + 1))
    [ "$TRIES" -gt 30 ] && die "panel did not start; see /tmp/nebulodactyl-shots-serve.log"
    sleep 1
done

mkdir -p showcase
info "capturing screenshots"
SERVER_ID="$SERVER_ID" PANEL_URL="http://127.0.0.1:${PORT}" \
    node tools/screenshots/capture.mjs

info "done. Screenshots are in showcase/"
ls -la showcase/
