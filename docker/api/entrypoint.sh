#!/bin/sh
set -e

cd /var/www/html

build_database_url() {
    export DATABASE_URL="$(php -r "
        \$user = getenv('MYSQL_USER') ?: 'super_cargo';
        \$pass = rawurlencode(getenv('MYSQL_PASSWORD') ?: '');
        \$host = getenv('MYSQL_HOST') ?: 'mysql';
        \$port = getenv('MYSQL_PORT') ?: '3306';
        \$db = getenv('MYSQL_DATABASE') ?: 'super_cargo';
        echo 'mysql://' . \$user . ':' . \$pass . '@' . \$host . ':' . \$port . '/' . \$db . '?serverVersion=8.0&charset=utf8mb4';
    ")"
}

write_env_file() {
    build_database_url

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

mysql_ping() {
    host="${MYSQL_HOST:-mysql}"
    port="${MYSQL_PORT:-3306}"

    if [ -n "${MYSQL_ROOT_PASSWORD:-}" ]; then
        if MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysqladmin ping \
            -h "$host" \
            -P "$port" \
            -u root \
            --silent 2>/dev/null
        then
            return 0
        fi
    fi

    if [ -n "${MYSQL_PASSWORD:-}" ]; then
        if MYSQL_PWD="${MYSQL_PASSWORD}" mysqladmin ping \
            -h "$host" \
            -P "$port" \
            -u "${MYSQL_USER:-super_cargo}" \
            --silent 2>/dev/null
        then
            return 0
        fi
    fi

    return 1
}

wait_for_database() {
    echo "Waiting for MySQL at ${MYSQL_HOST:-mysql}:${MYSQL_PORT:-3306}..."
    attempt=0
    max_attempts=90

    if command -v getent >/dev/null 2>&1; then
        echo "DNS mysql -> $(getent hosts "${MYSQL_HOST:-mysql}" 2>/dev/null || echo 'unresolved')"
    fi

    until mysql_ping; do
        attempt=$((attempt + 1))
        if [ "$attempt" -ge "$max_attempts" ]; then
            echo "ERROR: MySQL not reachable after ${max_attempts} attempts."
            echo "Host=${MYSQL_HOST:-mysql} user=${MYSQL_USER:-super_cargo}"
            if [ -n "${MYSQL_ROOT_PASSWORD:-}" ]; then
                MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysqladmin ping \
                    -h "${MYSQL_HOST:-mysql}" \
                    -P "${MYSQL_PORT:-3306}" \
                    -u root 2>&1 || true
            fi
            exit 1
        fi
        if [ $((attempt % 5)) -eq 0 ]; then
            echo "Still waiting for MySQL... (attempt ${attempt}/${max_attempts})"
        fi
        sleep 2
    done

    echo "Database is ready."
}

count_table_rows() {
    table="$1"
    php bin/console doctrine:query:sql "SELECT COUNT(*) AS cnt FROM ${table}" 2>/dev/null \
        | grep -oE '[0-9]+' \
        | head -1
}

write_env_file

if [ "${RUN_MIGRATIONS:-true}" = "true" ] || [ "${RUN_LOAD_FIXTURES:-false}" = "true" ]; then
    wait_for_database
fi

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
