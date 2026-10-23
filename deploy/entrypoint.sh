#!/bin/sh
set -eu
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs /var/www/html/database/runtime
if [ ! -f /var/www/html/database/runtime/tournament.sqlite ]; then
    touch /var/www/html/database/runtime/tournament.sqlite
fi
chown -R www-data:www-data storage bootstrap/cache /var/www/html/database/runtime
if [ "$#" -gt 0 ]; then
    exec "$@"
fi
php-fpm -F &
fpm_pid=$!
nginx -g 'daemon off;' &
nginx_pid=$!
trap 'kill "$fpm_pid" "$nginx_pid" 2>/dev/null; wait; exit 0' TERM INT
while kill -0 "$fpm_pid" && kill -0 "$nginx_pid"; do
    sleep 2
done
kill "$fpm_pid" "$nginx_pid" 2>/dev/null || true
wait || true
exit 1
