#!/bin/sh
set -e

cd /var/www/html

resolve_database_url() {
    if [ -n "${DATABASE_URL:-}" ]; then
        export DATABASE_URL
        return
    fi

    # Default: SQLite file under var/ (persisted via volume in compose)
    db_path="${SQLITE_DATABASE_PATH:-/var/www/html/var/database/data.db}"
    export DATABASE_URL="sqlite:///${db_path}"
}

write_env_file() {
    resolve_database_url

    echo "Writing .env from container environment..."
    php <<'PHP'
<?php

$vars = [
    'APP_ENV' => getenv('APP_ENV') ?: 'prod',
    'APP_DEBUG' => getenv('APP_DEBUG') ?: '0',
    'APP_SECRET' => getenv('APP_SECRET') ?: '',
    'DATABASE_URL' => getenv('DATABASE_URL') ?: '',
    'DEFAULT_URI' => getenv('DEFAULT_URI') ?: 'http://localhost',
    'CORS_ALLOW_ORIGIN' => getenv('CORS_ALLOW_ORIGIN') ?: "'^https?://(.*)$'",
    'FRANKFURTER_API_URL' => getenv('FRANKFURTER_API_URL') ?: 'https://api.frankfurter.dev',
    'FRANKFURTER_CACHE_TTL' => getenv('FRANKFURTER_CACHE_TTL') ?: '3600',
    'JWT_ACCESS_SECRET' => getenv('JWT_ACCESS_SECRET') ?: (getenv('APP_SECRET') ?: ''),
    'SYMFONY_TRUSTED_PROXIES' => getenv('SYMFONY_TRUSTED_PROXIES') ?: 'REMOTE_ADDR',
    'SYMFONY_TRUSTED_HEADERS' => getenv('SYMFONY_TRUSTED_HEADERS') ?: 'x-forwarded-for,x-forwarded-host,x-forwarded-proto',
];

$lines = [];
foreach ($vars as $key => $value) {
    if ($value === '') {
        continue;
    }
    $lines[] = $key . '=' . $value;
}

file_put_contents('.env', implode("\n", $lines) . "\n");
PHP

    if [ -z "${JWT_ACCESS_SECRET:-}" ] && [ -n "${APP_SECRET:-}" ]; then
        export JWT_ACCESS_SECRET="$APP_SECRET"
    fi
}

ensure_sqlite_dir() {
    db_url="${DATABASE_URL:-}"
    case "$db_url" in
        sqlite:*)
            # sqlite:////absolute/path or sqlite:///relative
            db_path="$(php -r "
                \$u = getenv('DATABASE_URL') ?: '';
                if (preg_match('#^sqlite:///(.+)$#', \$u, \$m)) {
                    echo \$m[1];
                }
            ")"
            if [ -n "$db_path" ]; then
                mkdir -p "$(dirname "$db_path")"
                echo "SQLite database path: ${db_path}"
            fi
            ;;
    esac
}

count_table_rows() {
    table="$1"
    php bin/console doctrine:query:sql "SELECT COUNT(*) AS cnt FROM ${table}" 2>/dev/null \
        | grep -oE '[0-9]+' \
        | head -1
}

write_env_file
ensure_sqlite_dir

mkdir -p var/cache var/log var/share public/uploads/logos
chown -R www-data:www-data var public/uploads 2>/dev/null || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "Running migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
    echo "Bootstrapping referentiel (idempotent)..."
    php bin/console app:referentiel:bootstrap
fi

if [ "${RUN_LOAD_FIXTURES:-false}" = "true" ]; then
    user_count="$(count_table_rows utilisateurs)"
    if [ "${user_count:-0}" = "0" ]; then
        echo "Loading demo fixtures..."
        php bin/console doctrine:fixtures:load --no-interaction
        echo "Fixtures loaded."
    else
        echo "Fixtures skipped: database already contains data (${user_count} user(s))."
    fi
fi

if [ "$APP_ENV" = "prod" ]; then
    php bin/console cache:clear --no-warmup
    php bin/console cache:warmup
fi

mkdir -p public/uploads/logos
chown -R www-data:www-data var public/uploads

exec "$@"
