#!/usr/bin/env bash
# One-command setup for a fresh clone. Needs only Docker (with the compose plugin); no PHP or Node on the host.
#
#   scripts/bootstrap.sh                          # build, install, migrate, seed the flag catalogue
#   scripts/bootstrap.sh "Your Name" you@x.com    # ...and create your login (password is printed once)
#
# Safe to re-run: every step skips what is already done.
set -euo pipefail

cd "$(dirname "$0")/.."

say() { printf '\n==> %s\n' "$*"; }
die() { printf 'error: %s\n' "$*" >&2; exit 1; }
artisan() { docker compose run --rm --no-deps app php artisan "$@"; }

command -v docker >/dev/null || die "docker not found. Install Docker Desktop (enable WSL integration on Windows) and retry."
docker compose version >/dev/null 2>&1 || die "the 'docker compose' plugin is missing."
docker info >/dev/null 2>&1 || die "the Docker daemon is not running."

say "Environment file"
if [ ! -f .env ]; then
    cp .env.example .env
    echo "created .env from .env.example"
fi
# Containers run as the host user so bind-mounted files stay editable.
sed -i "s/^HOST_UID=.*/HOST_UID=$(id -u)/; s/^HOST_GID=.*/HOST_GID=$(id -g)/" .env
# The project path as the user sees it (a \\wsl.localhost\... path on Windows), so the app can say where exports land.
HOST_DIR="$PWD"
command -v wslpath >/dev/null && HOST_DIR="$(wslpath -w "$PWD")"
grep -q '^HOST_PROJECT_DIR=' .env || echo 'HOST_PROJECT_DIR=' >> .env
HOST_DIR_ESCAPED="$(printf '%s' "$HOST_DIR" | sed 's/[\\&|]/\\&/g')"
# Single quotes: dotenv treats backslashes in double-quoted values as escapes and refuses \w, \U...
sed -i "s|^HOST_PROJECT_DIR=.*|HOST_PROJECT_DIR='${HOST_DIR_ESCAPED}'|" .env

say "Building the app image and starting the database and redis"
docker compose build app
docker compose up -d --wait pgsql redis

say "PHP dependencies"
docker compose run --rm --no-deps app composer install --no-interaction --prefer-dist

say "Application key"
if ! grep -q '^APP_KEY=.\+' .env; then
    artisan key:generate --force
else
    echo "APP_KEY already set"
fi

say "OAuth keys (Passport, used by the MCP login)"
if [ ! -f storage/oauth-private.key ]; then
    artisan passport:keys --force
else
    echo "keys already present"
fi

say "Starting the full stack (app, worker, reverb, assets)"
docker compose up -d

say "Waiting for the front-end build (first run installs npm packages, 1-3 minutes)"
for _ in $(seq 1 90); do
    [ -f public/build/manifest.json ] && break
    sleep 4
done
[ -f public/build/manifest.json ] || die "assets never built. Check: docker compose logs assets"

say "Database"
docker compose exec -T app php artisan migrate --seed --force

if [ "$#" -ge 2 ]; then
    say "Creating login for $1"
    docker compose exec -T app php artisan sectf:user "$1" "$2"
fi

APP_URL="$(grep '^APP_URL=' .env | cut -d= -f2-)"
cat <<EOF

Done. Dashboard: ${APP_URL}
$([ "$#" -ge 2 ] || echo "No login yet. Create one:  docker compose exec app php artisan sectf:user \"Name\" you@example.com")
Next: connect Claude Code. See docs/CONTESTANT.md.
EOF
