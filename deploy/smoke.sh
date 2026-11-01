#!/bin/sh
set -eu
export APP_ENV=testing APP_DEBUG=false APP_URL=http://localhost
export DB_CONNECTION=sqlite DB_DATABASE=/var/www/html/database/runtime/tournament.sqlite
export SESSION_DRIVER=database CACHE_STORE=database QUEUE_CONNECTION=sync
APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
export APP_KEY
export BROADCAST_CONNECTION=reverb REVERB_APP_ID=tournament
REVERB_APP_KEY="$(php -r 'echo bin2hex(random_bytes(32));')"
REVERB_APP_SECRET="$(php -r 'echo bin2hex(random_bytes(32));')"
export REVERB_APP_KEY REVERB_APP_SECRET
export REVERB_HOST=127.0.0.1 REVERB_PORT=8080 REVERB_SCHEME=http
export REVERB_SERVER_HOST=127.0.0.1 REVERB_SERVER_PORT=8080
# The container has no mounted production state or external network access.
tournament-entrypoint php artisan migrate --force >/tmp/migrations.log
php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$group = App\Models\Group::create(["name" => "Smoke test"]);
$event = App\Models\Tournament::create(["name" => "Mobile test", "status" => App\Enums\Status::OPEN, "group_id" => $group->id]);
echo $event->invite_token;
' >/tmp/event-token
tournament-entrypoint >/tmp/application.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true; wait "$server_pid" 2>/dev/null || true' EXIT
ready=false
for attempt in $(seq 1 30); do
    if curl -fs http://127.0.0.1/up >/dev/null 2>&1; then ready=true; break; fi
    sleep 1
done
if [ "$ready" != true ]; then cat /tmp/application.log; exit 1; fi
# Exercise the production nginx upgrade route, not only the HTTP health page.
php -r '
$socket = fsockopen("127.0.0.1", 80, $errno, $error, 5);
if (! $socket) { fwrite(STDERR, "WebSocket proxy unreachable.\n"); exit(1); }
stream_set_timeout($socket, 5);
$key = base64_encode(random_bytes(16));
$path = "/app/".getenv("REVERB_APP_KEY")."?protocol=7&client=smoke&version=1";
fwrite($socket, "GET $path HTTP/1.1\r\nHost: localhost\r\nOrigin: http://localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\n\r\n");
$response = "";
while (! feof($socket) && ! str_contains($response, "pusher:connection_established")) {
    $chunk = fread($socket, 8192);
    if ($chunk === false || $chunk === "") { break; }
    $response .= $chunk;
}
fclose($socket);
if (! str_contains($response, "101 Switching Protocols") || ! str_contains($response, "pusher:connection_established")) {
    fwrite(STDERR, "WebSocket upgrade or Reverb handshake failed: ".$response."\n"); exit(1);
}
'
curl -fs http://127.0.0.1/ >/tmp/welcome.html
grep -q 'Ohne Anmeldung' /tmp/welcome.html
event_token="$(cat /tmp/event-token)"
curl -fs -c /tmp/cookies "http://127.0.0.1/play/$event_token" >/tmp/page.html
grep -q 'Ohne Passwort' /tmp/page.html
csrf="$(php -r 'preg_match("/name=\"_token\" value=\"([^\"]+)\"/", file_get_contents("/tmp/page.html"), $matches); echo $matches[1] ?? "";')"
test -n "$csrf"
status="$(curl -s -o /tmp/join-response -w '%{http_code}' -b /tmp/cookies -c /tmp/cookies \
    --data-urlencode "_token=$csrf" --data-urlencode 'name=Mobile player' \
    "http://127.0.0.1/play/$event_token/join")"
test "$status" = 302
curl -fs -b /tmp/cookies "http://127.0.0.1/play/$event_token" >/tmp/joined.html
grep -q 'Du bist dabei' /tmp/joined.html
grep -q 'Privater Rückkehr-Link' /tmp/joined.html
curl -fs http://127.0.0.1/login >/tmp/login.html
grep -q 'Organisation anmelden' /tmp/login.html
test "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/register)" = 404
printf 'Fresh production image: migration, HTTP health, WebSocket handshake, event join, private link and organizer login verified.\n'
