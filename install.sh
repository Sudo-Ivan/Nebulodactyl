#!/usr/bin/env bash
# Nebulodactyl installer
#
# Sets up the panel with Docker Compose: generates secrets, writes the
# compose file and .env, pulls the image, and starts the stack.
#
# Usage:
#   curl -fsSL https://raw.githubusercontent.com/Sudo-Ivan/Nebulodactyl/master/install.sh | bash
#
# Non-interactive:
#   NEBULODACTYL_DIR=/opt/nebulodactyl APP_URL=https://panel.example.com \
#   TRUSTED_PROXIES='*' ./install.sh --yes

set -euo pipefail

REPO_RAW="https://raw.githubusercontent.com/Sudo-Ivan/Nebulodactyl/master"
COMPOSE_FILE="docker-compose.example.yml"

# ----------------------------------------------------------------------------
# Output helpers
# ----------------------------------------------------------------------------

if [ -t 1 ] && command -v tput >/dev/null 2>&1 && [ "$(tput colors 2>/dev/null || echo 0)" -ge 8 ]; then
    C_RESET="$(tput sgr0)"
    C_BOLD="$(tput bold)"
    C_DIM="$(tput dim)"
    C_RED="$(tput setaf 1)"
    C_GREEN="$(tput setaf 2)"
    C_YELLOW="$(tput setaf 3)"
    C_BLUE="$(tput setaf 4)"
    C_CYAN="$(tput setaf 6)"
else
    C_RESET="" C_BOLD="" C_DIM="" C_RED="" C_GREEN="" C_YELLOW="" C_BLUE="" C_CYAN=""
fi

info()    { printf '%s==>%s %s\n' "$C_BLUE" "$C_RESET" "$1"; }
ok()      { printf '%s ok %s %s\n' "$C_GREEN" "$C_RESET" "$1"; }
warn()    { printf '%swarn%s %s\n' "$C_YELLOW" "$C_RESET" "$1" >&2; }
err()     { printf '%serror%s %s\n' "$C_RED" "$C_RESET" "$1" >&2; }
step()    { printf '\n%s%s%s\n' "$C_BOLD" "$1" "$C_RESET"; }

ask() {
    # ask "Prompt" "default" -> echoes answer
    local prompt="$1" default="$2" answer
    if [ "$ASSUME_YES" = "1" ]; then
        printf '%s' "$default"
        return
    fi
    printf '%s?%s %s %s[%s]%s ' "$C_CYAN" "$C_RESET" "$prompt" "$C_DIM" "$default" "$C_RESET" >&2
    read -r answer </dev/tty || answer=""
    printf '%s' "${answer:-$default}"
}

confirm() {
    # confirm "Prompt" default_yes|default_no -> returns 0 for yes
    local prompt="$1" default="${2:-no}" answer hint
    if [ "$ASSUME_YES" = "1" ]; then
        [ "$default" = "yes" ]
        return
    fi
    if [ "$default" = "yes" ]; then hint="Y/n"; else hint="y/N"; fi
    printf '%s?%s %s %s[%s]%s ' "$C_CYAN" "$C_RESET" "$prompt" "$C_DIM" "$hint" "$C_RESET" >&2
    read -r answer </dev/tty || answer=""
    case "${answer:-$default}" in
        y|Y|yes|YES) return 0 ;;
        *) return 1 ;;
    esac
}

die() { err "$1"; exit 1; }

# ----------------------------------------------------------------------------
# Arguments and environment
# ----------------------------------------------------------------------------

ASSUME_YES=0
INSTALL_DIR="${NEBULODACTYL_DIR:-}"
APP_URL="${APP_URL:-}"
TRUSTED_PROXIES="${TRUSTED_PROXIES:-}"
HTTP_PORT="${HTTP_PORT:-}"
HTTPS_PORT="${HTTPS_PORT:-}"

while [ $# -gt 0 ]; do
    case "$1" in
        -y|--yes) ASSUME_YES=1 ;;
        -d|--dir) INSTALL_DIR="$2"; shift ;;
        -u|--url) APP_URL="$2"; shift ;;
        -h|--help)
            sed -n '2,14p' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *) die "unknown argument: $1 (try --help)" ;;
    esac
    shift
done

# ----------------------------------------------------------------------------
# Preflight checks
# ----------------------------------------------------------------------------

printf '\n%s%s  Nebulodactyl installer%s\n\n' "$C_BOLD" "$C_CYAN" "$C_RESET"

step "Checking requirements"

# Prefer podman, fall back to docker. COMPOSE carries the full compose command.
if command -v podman >/dev/null 2>&1; then
    if podman compose version >/dev/null 2>&1; then
        COMPOSE="podman compose"
    elif command -v podman-compose >/dev/null 2>&1; then
        COMPOSE="podman-compose"
    else
        die "podman is installed but has no compose support. Install podman-compose or enable the podman compose provider."
    fi
    ENGINE="podman"
elif command -v docker >/dev/null 2>&1; then
    docker info >/dev/null 2>&1 || die "cannot talk to the Docker daemon. Is it running, and is your user in the docker group?"
    docker compose version >/dev/null 2>&1 || die "the Docker Compose plugin is missing. See https://docs.docker.com/compose/install/"
    COMPOSE="docker compose"
    ENGINE="docker"
else
    die "neither podman nor docker is installed. Podman is preferred: https://podman.io/docs/installation"
fi
command -v curl >/dev/null 2>&1 || die "curl is not installed"
ok "$ENGINE with compose support found"

# ----------------------------------------------------------------------------
# Questions
# ----------------------------------------------------------------------------

step "Setup"

INSTALL_DIR="${INSTALL_DIR:-$(ask "Install directory" "./nebulodactyl")}"
APP_URL="${APP_URL:-$(ask "Panel URL (the address users open in a browser)" "http://localhost")}"

case "$APP_URL" in
    http://*|https://*) ;;
    *) die "APP_URL must start with http:// or https://, got: $APP_URL" ;;
esac

if [ -z "$TRUSTED_PROXIES" ]; then
    if confirm "Will the panel sit behind a reverse proxy (Traefik, nginx, Caddy, Coolify)?" "no"; then
        TRUSTED_PROXIES="$(ask "Trusted proxy IPs or CIDRs (comma separated, * only if proxy-only)" "*")"
    else
        TRUSTED_PROXIES=""
    fi
fi

if [ -z "$HTTP_PORT" ]; then
    HTTP_PORT="$(ask "HTTP port to expose" "80")"
fi
HTTPS_PORT="${HTTPS_PORT:-443}"

# ----------------------------------------------------------------------------
# Write files
# ----------------------------------------------------------------------------

step "Writing files to $INSTALL_DIR"

if [ -d "$INSTALL_DIR" ] && [ -f "$INSTALL_DIR/docker-compose.yml" ]; then
    warn "an install already exists at $INSTALL_DIR"
    if ! confirm "Keep existing files and just start the stack?" "yes"; then
        die "aborted. Existing files were left untouched."
    fi
    cd "$INSTALL_DIR"
else
    mkdir -p "$INSTALL_DIR/data"/{database,var,nginx,certs,logs,storage}
    cd "$INSTALL_DIR"

    if [ -f "$OLDPWD/$COMPOSE_FILE" ]; then
        cp "$OLDPWD/$COMPOSE_FILE" docker-compose.yml
    else
        info "downloading compose file"
        curl -fsSL "$REPO_RAW/$COMPOSE_FILE" -o docker-compose.yml
    fi

    if [ -f .env ]; then
        warn ".env already exists, keeping it (secrets are not regenerated)"
    else
        gen_pw() { tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32; }
        TZ_NAME="UTC"
        [ -r /etc/timezone ] && TZ_NAME="$(head -1 /etc/timezone)"
        umask 077
        cat > .env <<EOF
# Generated by install.sh on $(date -u +%Y-%m-%d)
APP_URL=$APP_URL
APP_TIMEZONE=$TZ_NAME
TRUSTED_PROXIES=$TRUSTED_PROXIES
HTTP_PORT=$HTTP_PORT
HTTPS_PORT=$HTTPS_PORT
DB_PASSWORD=$(gen_pw)
DB_ROOT_PASSWORD=$(gen_pw)
EOF
        umask 022
        ok "generated .env with random database passwords"
    fi
fi

# ----------------------------------------------------------------------------
# Start
# ----------------------------------------------------------------------------

step "Starting the panel"

$COMPOSE pull
$COMPOSE up -d

info "waiting for the panel to come up (first start runs migrations, can take a minute)"

HEALTH_URL="http://127.0.0.1:${HTTP_PORT}/healthz"
TRIES=0
until curl -fsS "$HEALTH_URL" >/dev/null 2>&1; do
    TRIES=$((TRIES + 1))
    if [ "$TRIES" -gt 60 ]; then
        warn "the panel is not answering on $HEALTH_URL yet"
        warn "check logs with: $COMPOSE logs panel"
        break
    fi
    sleep 5
done

step "Done"

if curl -fsS "$HEALTH_URL" >/dev/null 2>&1; then
    ok "panel is healthy"
fi

SETUP_URL="$($COMPOSE exec -T panel php artisan p:setup:link 2>/dev/null | grep -o 'http[^ ]*/setup?key=[^ ]*' | head -1 || true)"

cat <<EOF

  Panel URL:        ${C_BOLD}${APP_URL}${C_RESET}
  Install dir:      ${INSTALL_DIR}
  Compose file:     ${INSTALL_DIR}/docker-compose.yml
  Settings/secrets: ${INSTALL_DIR}/.env

EOF

if [ -n "$SETUP_URL" ]; then
    printf '  %sCreate your admin account here:%s\n\n' "$C_BOLD" "$C_RESET"
    printf '      %s%s%s\n\n' "$C_GREEN" "$SETUP_URL" "$C_RESET"
    printf '  The link expires in one hour. If it lapses, mint a new one:\n\n'
    printf '      %scd %s && %s exec panel php artisan p:setup:link%s\n\n' "$C_CYAN" "$INSTALL_DIR" "$COMPOSE" "$C_RESET"
else
    printf '  An account already exists, or the setup link could not be read.\n'
    printf '  Mint a fresh link any time with:\n\n'
    printf '      %scd %s && %s exec panel php artisan p:setup:link%s\n\n' "$C_CYAN" "$INSTALL_DIR" "$COMPOSE" "$C_RESET"
fi

cat <<EOF
  Useful commands:

      $COMPOSE logs -f panel     # watch the panel logs
      $COMPOSE restart panel     # restart after changing .env
      $COMPOSE down              # stop everything

EOF

if [ "$APP_URL" = "http://localhost" ]; then
    printf '%snote%s http://localhost is fine for a first look. For real use, put the\n' "$C_YELLOW" "$C_RESET"
    printf 'panel on a domain with HTTPS, update APP_URL in .env, and set TRUSTED_PROXIES.\n'
fi
