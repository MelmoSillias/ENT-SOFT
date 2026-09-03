#!/bin/sh
set -e

export API_UPSTREAM_HOST="${API_UPSTREAM_HOST:-entsoft-api}"
export API_UPSTREAM_PORT="${API_UPSTREAM_PORT:-80}"

envsubst '${API_UPSTREAM_HOST} ${API_UPSTREAM_PORT}' \
    < /etc/nginx/templates/default.conf.template \
    > /etc/nginx/conf.d/default.conf

echo "Frontend proxy: /api/ -> http://${API_UPSTREAM_HOST}:${API_UPSTREAM_PORT}"

exec "$@"
