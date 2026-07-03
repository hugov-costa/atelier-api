#!/bin/sh
set -e

cd /app

ROLE="${CONTAINER_ROLE:-app}"

log() {
    echo "[entrypoint][${ROLE}] $*"
}

ensure_dependencies() {
    if [ ! -f vendor/autoload.php ]; then
        log "vendor not found, installing dependencies"
        composer install --no-interaction --prefer-dist
    fi
}

bootstrap_application() {
    # Render and similar platforms inject env vars directly — no .env file needed.
    # Only generate .env when neither file nor env vars are present.
    if php -r 'exit(empty($_SERVER["APP_KEY"]) && file_exists(".env") === false ? 0 : 1);' 2>/dev/null; then
        if [ -f .env.example ]; then
            cp .env.example .env
            log "generated .env from .env.example"
        else
            log "WARNING: no .env.example found and APP_KEY is not set as an env var"
            log "Set APP_KEY via the platform's env-variable dashboard."
        fi
    else
        log "env vars already configured (or .env present), skipping generation"
    fi

    if grep -qE '^APP_KEY=base64:' .env 2>/dev/null; then
        log "APP_KEY already set, skipping key generation"
    elif [ -n "${APP_KEY:-}" ]; then
        log "APP_KEY provided via environment, skipping key generation"
    else
        log "generating application key"
        php artisan key:generate --force --no-interaction
    fi

    php artisan app:ensure-database

    log "applying pending migrations"
    php artisan migrate --force --no-interaction
}

install_git_hooks() {
    if [ ! -d .git ]; then
        log "no .git directory present, skipping git hook installation"
        return
    fi

    if [ ! -x vendor/bin/captainhook ]; then
        log "CaptainHook not installed, skipping git hook installation"
        return
    fi

    log "installing CaptainHook git hooks"
    vendor/bin/captainhook install --force --only-enabled --no-interaction \
        || log "warning: CaptainHook git hook installation failed"
}

ensure_dependencies

if [ "$ROLE" = "app" ]; then
    bootstrap_application
    install_git_hooks
fi

log "starting process: $*"
exec "$@"
