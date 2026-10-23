# Spielrunde · Magic tournaments

A mobile-first app for a Magic playgroup. The stack is PHP 8.4.1+, Laravel 12, Livewire 4, SQLite, Tailwind CSS and free Flux components. Node 22 builds CSS; production runs PHP-FPM and nginx in one container. No paid UI credentials, websocket server, Redis, queue worker or custom JavaScript bundle are required.

## Playing

Organizers share the event link shown in the Organisation panel. Participants enter a display name and optional deck without registering an account. The device remembers their identity for the session lifetime. A private return link restores it on another phone or after the session expires; bookmark it and keep it private. Only a SHA-256 hash of the random return token is stored on the participant record. Return links pass the token in a fragment, then submit by POST.

Players see their current match first and choose results from their own perspective. Either player may report; the first confirmed submission wins. Standings and rounds refresh every ten seconds while a tournament is active; background tabs are throttled by Livewire, and completed tournaments stop polling. Points count individual game wins (2–1 awards two points and one point); a draw awards one each. Tied players share a place. Spectators can view an event link but cannot report results.

The interface includes copy/share buttons, search and status filters, mobile navigation, and large score buttons. If someone loses a private link, the organizer can issue a replacement, revoking the old link and guest sessions for that identity.

## Organizing

Public account registration is disabled. Provision organizers through the container or local console:

```sh
php artisan app:organizer organizer@example.com --name="Organizer"
```

The command prompts for a password; it is never passed as a command-line argument. Organizers sign in through Organisation and can enable two-factor authentication in Settings. Organizer permissions currently apply across all tournaments. The first tournament initializes a default group if none exists. Configure SMTP if organizers should receive password-reset emails.

The Traefik shared password is unnecessary for this app: organizers use Laravel authentication, participants use private event identities, and spectators follow invitation links. HTTPS, security headers and homelab's dashboard access rules are separate. See [deployment and proxy notes](deploy/README.md).

## Local development

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm ci
npm run build
php artisan app:organizer organizer@example.com --name="Organizer"
composer dev
```

## Verification

```sh
php artisan test
npm run build
composer audit
npm audit
bash -n deploy/remote.sh
sh -n deploy/entrypoint.sh
docker compose config --quiet
docker build -t tournament:review .
```

CI runs the tests, builds the production image, and exercises an isolated fresh installation. Deployment is manual-only and starts with a new database under `~/mtg/data/database` instead of importing legacy data. Subsequent deployments preserve that directory and back it up before migration.

Result corrections, opponent approval, per-event organizer roles and advanced tie-breakers are future work.
