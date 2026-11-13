#!/usr/bin/env bash
#
# The full quality gate, the same checks a CI server would run.
#
# GitHub Actions isn't available on this repo's account, so this is what runs before every
# merge instead. It needs the Sail containers up (`./vendor/bin/sail up -d`) and node_modules
# installed on the host.
#
#   ./scripts/ci.sh          # everything
#   ./scripts/ci.sh --php    # backend only
#   ./scripts/ci.sh --js     # frontend only

set -euo pipefail
cd "$(dirname "$0")/.."

run_php=true
run_js=true
case "${1:-}" in
    --php) run_js=false ;;
    --js) run_php=false ;;
esac

step() { printf '\n\033[1;33m▸ %s\033[0m\n' "$1"; }

if $run_php; then
    step "PHP: Pint, Larastan and Pest (inside Sail)"
    docker compose exec -T laravel.test composer ci
fi

if $run_js; then
    step "Frontend: lint and formatting"
    npm run check

    step "Frontend: type check"
    npm run types:check

    step "Frontend: production build"
    npm run build
fi

printf '\n\033[1;32m✔ All checks passed\033[0m\n'
