# Homelab deployment

This app uses its own `~/mtg` checkout, like `~/sili-telegram-bot`, with homelab's `external_network`, `security-headers@file` middleware and `externalresolver` TLS certificate resolver. Compose uses project `mtg`, service `laravel` and container `magic_tournament`.

## Manual workflow

Run **Deploy tournament** on `main`. Pushes never deploy. Deployments are serialized, use WireGuard and pinned SSH host keys, and always disconnect WireGuard. Required repository secrets:

- `WIREGUARD_CONF`, `SSH_HOST`, `SSH_USER`, `DEPLOY_SECRET_KEY`
- `SSH_KNOWN_HOSTS`: independently verified host keys, as in homelab
- `ENV_FILE`: production Laravel configuration, including a persistent `APP_KEY` and `APP_URL=https://mtg.betz.coffee`

No Flux credentials or other build secrets are needed. The host needs Docker and Compose v2 with `--wait` support. The image also builds with the legacy Docker builder. `.env`, private files and runtime data are excluded from rsync deletion and image context.

## Fresh state and organizer setup

The new deployment deliberately uses a fresh SQLite database at `~/mtg/data/database/tournament.sqlite` and uploads at `~/mtg/data/storage`. The legacy `/docker-compose-services/mtg` database is not imported. The entrypoint initializes an absent SQLite file inside the mounted database directory and sets write permissions. First deployment and subsequent updates use the same workflow.

Compose enforces production mode, debug off, SQLite at `/var/www/html/database/runtime/tournament.sqlite`, database sessions/cache, secure session cookies and a 30-day idle session lifetime. `TOURNAMENT_HOST` defaults to `mtg.betz.coffee`. Change `APP_URL` and `TOURNAMENT_HOST` together for a different hostname. Existing Reverb, broadcast and Flux environment variables can be removed from `ENV_FILE`; they are no longer used.

After the first deployment, create an organizer on the host:

```sh
cd ~/mtg
docker compose exec laravel php artisan app:organizer organizer@example.com --name="Organizer"
```

Enter the password in the interactive prompt. Public registration is disabled. Configure SMTP in `ENV_FILE` for organizer password reset. The initial event-link flow requires no participant credentials or mail delivery.

## Traefik password investigation

The old app labels defined an `mtg-auth` Basic Auth middleware and applied it to the `mtg` HTTP router. This was an additional shared password in front of Laravel. It did not configure TLS and did not protect the Traefik dashboard. It is removed from this app's Compose file, including the embedded password hash.

The homelab Traefik service has `api.insecure=false`; its dashboard router uses `internal-app@file`. That chain is LAN-only access plus error handling and security headers. Homelab's `websecure` entrypoint adds `global-noindex@file`, not Basic Auth. The app therefore needs no shared Traefik password, and removing its app-specific middleware does not remove the dashboard's separate network restriction. This conclusion is based on the checked-out homelab configuration; running production labels have not been inspected.

The app still uses HTTPS, homelab security headers, CSRF protection, rate limits on joining/recovery, server-side organizer checks, and scoped private participant tokens. Event links grant spectator access; private return links grant permission to report a particular participant's matches. There is no websocket route or exposed port 8080.

## Backup and failure recovery

Updates build the image first. When an existing app container is running, the script takes a consistent SQLite snapshot with `VACUUM INTO` into private `~/mtg/backups`, puts the old app in maintenance mode, migrates using the new image, recreates the service and waits for health. First installation skips the existing-container backup and initializes fresh state. No Docker pruning or orphan-service cleanup is performed.

A migration or health failure fails deployment. The EXIT handler attempts to bring the app out of maintenance, but there is no automatic rollback. Inspect logs before retrying. Backups contain participant information; include `~/mtg/backups` and `~/mtg/data` in the host backup policy.

To restore a snapshot, stop the app, retain the current database and any WAL/SHM files, replace the stopped database file with the selected snapshot, ensure `www-data` can write to its directory, and start the matching app image. Never overwrite a live SQLite file.
