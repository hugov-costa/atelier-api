#!/bin/sh

#
# Pre-commit quality gate executed inside the application container.
#
# CaptainHook chains the steps in order (pint > phpstan > test) and stops at the
# first failing one, printing the reason to the terminal so the commit aborts.
#
#   pint     formats the staged PHP files and re-stages them; it does not block
#            the commit, only failing on an unfixable parse error
#   phpstan  static analysis at the maximum level
#   test     request tests
#
# Usage: quality-gate.sh <pint|phpstan|test>
#

set -u

cd "$(dirname "$0")/../.." || exit 1

fail() {
    printf '\n  ✖ failed %s:\n\n%s\n\n' "$1" "$2"
    exit 1
}

run_pint() {
    git config --global --add safe.directory /app >/dev/null 2>&1 || true

    staged=$(git diff --cached --name-only --diff-filter=ACMR -- '*.php')

    if [ -z "$staged" ]; then
        printf '  ✔ pint (no staged PHP files)\n'
        return 0
    fi

    if ! output=$(printf '%s\n' "$staged" | xargs -d '\n' -r vendor/bin/pint 2>&1); then
        fail "pint" "$output"
    fi

    printf '%s\n' "$staged" | xargs -d '\n' -r git add --

    printf '  ✔ pint\n'
}

run_check() {
    label="$1"
    shift

    if ! output=$("$@" 2>&1); then
        fail "$label" "$output"
    fi

    printf '  ✔ %s\n' "$label"
}

case "${1:-}" in
    pint)
        run_pint
        ;;
    phpstan)
        run_check "phpstan" vendor/bin/phpstan analyse --memory-limit=1G --no-progress
        ;;
    test)
        run_check "test" php artisan test
        ;;
    *)
        printf 'quality-gate: unknown step "%s" (expected pint, phpstan or test)\n' "${1:-}"
        exit 1
        ;;
esac
