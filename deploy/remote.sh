#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
test -s .env
docker compose config --quiet
docker network inspect external_network >/dev/null
docker compose build --pull
install -d -m 700 backups
backup="none (fresh installation)"
if [ -n "$(docker compose ps -q laravel)" ]; then
backup="backups/$(date -u +%Y%m%dT%H%M%SZ).sqlite"
umask 077
# VACUUM INTO produces a consistent snapshot, including committed WAL data.
docker compose exec -T laravel php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config("database.default") !== "sqlite") { fwrite(STDERR, "Only SQLite backups are supported by this deployment.\n"); exit(1); }
$pathToDatabase = config("database.connections.sqlite.database");
if (! is_file($pathToDatabase)) { fwrite(STDERR, "Existing SQLite database missing.\n"); exit(1); }
$db = new PDO("sqlite:".$pathToDatabase);
$path = "/tmp/tournament-backup-".bin2hex(random_bytes(12)).".sqlite";
$db->exec("VACUUM INTO ".$db->quote($path));
readfile($path);
unlink($path);
' > "$backup"
test -s "$backup"
docker compose exec -T laravel php artisan down
trap 'docker compose exec -T laravel php artisan up || true' EXIT
fi
# Migrate with the new image while preserving the running container for failure recovery.
docker compose run --rm --no-deps laravel php artisan migrate --force
docker compose up -d --wait --wait-timeout 180 laravel
docker compose exec -T laravel php artisan up
trap - EXIT
printf 'Tournament healthy; backup snapshot: %s\n' "$backup"
