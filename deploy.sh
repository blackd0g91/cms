#!/usr/bin/env bash
#
# Update production to the latest code.
#
#   ./deploy.sh
#
# Run it from anywhere, as the user that owns the checkout (root is fine).
# Settings can be overridden with environment variables:
#
#   PHP_BIN    PHP binary to use                 (default: php8.4, else php)
#   WEB_USER   user the web server runs as       (default: www-data)
#   BRANCH     branch to deploy                  (default: the current one)
#
# The site is put in maintenance mode while updating, and brought back up
# even if a step fails.

set -euo pipefail

cd "$(dirname "$(readlink -f "$0")")"

PHP_BIN="${PHP_BIN:-$(command -v php8.4 || command -v php)}"
WEB_USER="${WEB_USER:-www-data}"
BRANCH="${BRANCH:-$(git rev-parse --abbrev-ref HEAD)}"

step() { printf '\n\033[1;34m==> %s\033[0m\n' "$1"; }
fail() { printf '\n\033[1;31m%s\033[0m\n' "$1" >&2; exit 1; }

artisan() { "$PHP_BIN" artisan "$@"; }

# The server changes file permissions (see the end of this script), which git
# would otherwise report as local changes and refuse to update over.
git() { command git -c core.fileMode=false "$@"; }

# --- Checks -----------------------------------------------------------------

step "Checking requirements"

[[ -f .env ]] || fail "No .env file. Set up the server first (see the deployment steps)."

"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);' \
    || fail "$PHP_BIN is PHP $("$PHP_BIN" -r 'echo PHP_VERSION;'), but 8.4.1 or newer is required. Set PHP_BIN."

COMPOSER="$(command -v composer)" || fail "Composer is not installed."
command -v npm >/dev/null || fail "npm is not installed."

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    fail "There are local changes to tracked files. Commit or discard them first:
$(git status --short --untracked-files=no)"
fi

echo "PHP:    $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"
echo "Branch: $BRANCH"

# --- Update -----------------------------------------------------------------

step "Entering maintenance mode"
artisan down --retry=15 || true
trap 'step "Leaving maintenance mode"; artisan up' EXIT

step "Pulling the latest code"
before="$(git rev-parse --short HEAD)"
git fetch --quiet origin "$BRANCH"
git merge --ff-only "origin/$BRANCH"
after="$(git rev-parse --short HEAD)"
if [[ "$before" == "$after" ]]; then
    echo "Already up to date ($after)."
else
    git --no-pager log --oneline "$before..$after"
fi

step "Installing PHP dependencies"
"$PHP_BIN" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --no-progress

step "Building the frontend"
# The build runs `php artisan wayfinder:generate`, so make sure `php` means $PHP_BIN.
shim="$(mktemp -d)"
ln -s "$PHP_BIN" "$shim/php"
npm ci --no-audit --no-fund
PATH="$shim:$PATH" npm run build
rm -rf "$shim"

step "Running database migrations"
artisan migrate --force

if [[ ! -e public/storage ]]; then
    step "Linking the storage folder"
    artisan storage:link
fi

step "Caching configuration, routes and views"
artisan optimize:clear
artisan optimize

# Files created above by this user (caches, compiled views) must stay writable
# by the web server. The database folder too, for SQLite's temporary files.
if [[ "$(id -u)" -eq 0 ]] && id "$WEB_USER" >/dev/null 2>&1; then
    step "Fixing permissions for $WEB_USER"
    chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache database
    chmod -R ug+rwX storage bootstrap/cache database
fi

printf '\n\033[1;32mDeployed %s.\033[0m\n' "$after"
