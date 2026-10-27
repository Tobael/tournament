# Spielrunde · Magic tournaments

A mobile-first app for a Magic playgroup. The stack is PHP 8.4.1+, Laravel 12, Livewire 4, SQLite, Tailwind CSS and free Flux components. Node 22 builds CSS; production runs PHP-FPM and nginx in one container. No paid UI credentials, websocket server, Redis or queue worker are required. A small JavaScript module handles copying links and remembering participation on the device.

## Playing

Organizers share the event link shown in the Organisation panel. Participants enter a display name without registering an account. The device stores the event-scoped return token in local storage as well as the server session. Opening the event restores participation automatically after session expiry. The homepage returns to the most recent active round; use the logo to open another round. Disabled local storage falls back to the normal session and private return link. A private return link restores it on another phone or after the session expires; bookmark it and keep it private. Only a SHA-256 hash of the random return token is stored on the participant record. Return links pass the token in a fragment, then submit by POST.

Players see their current match first and choose results from their own perspective. Either player may report; the first confirmed submission wins. Standings and rounds refresh every ten seconds while a tournament is active; background tabs are throttled by Livewire, and completed tournaments stop polling. For Sealed and Constructed, points count individual game wins (2–1 awards two points and one point); a draw awards one each. Tied players share a place. Spectators can view an event link but cannot report results.

New tournaments default to Sealed. Organizers can choose a set when creating or editing an event, or leave it open for mixed pools. The Scryfall catalog refreshes on demand every 24 hours, lists paper core, expansion, Masters and special draft sets newest first, and retains the last successful response during outages. Announced sets are marked. Saved event set names remain available even if the catalog is unavailable. The selected set appears with its original symbol on compact event cards, a page-wide atmospheric backdrop that fades down into the page, and a live selection preview. Symbols are cached locally for a month; artwork metadata refreshes daily, keeps the last successful response during outages and retries failures after five minutes. Artist credits and a Scryfall source link accompany every illustration. The backdrop scrolls more slowly than the content with a passive, frame-throttled parallax handler. Reduced-motion mode uses a static background. Imagery loads separately from match reporting; unavailable imagery falls back to the set name and code.

Every unfinished match in the current round has a shared 25, 50, 60 or 90 minute timer under Runden & Ergebnisse. Anyone with the event link can start it, without signing in. The server stores its deadline; refreshes and other devices show the same countdown. A timer can only start once and cannot be reset. At zero it displays a time-limit message and extra-turn guidance; players still report the actual result. Timers do not award automatic wins or draws. Closing an event displays a crown and podium for the final standings, including shared winners. Podium heights stay fixed in first/second/third order even on small screens or with long tied names; full names remain in the standings.

The interface includes copy/share buttons, search and status filters, and large score buttons. Tournament pages use four tabs: Spiel, Tabelle, Runden and Mehr. More contains the private return link, collapsible music controls, card tools and organizer controls. Tabs and expanded panels remain stable during live refreshes. Narrow cover displays down to 320 CSS pixels use compact spacing, wrapping names and stacked forms; return-link and music cards have consistent separation. If someone loses a private link, the organizer can issue a replacement, revoking the old link and guest sessions for that identity.

Sealed events include **Karten & Werte**: enter a collector number (leading zeros, pasted `#119/276` and number suffixes are supported) to see the card and separate EUR normal/foil prices. Use the set selector for bonus sheets and Special Guests. Top Pulls ranks the eight highest priced printings independently for normal and foil finishes, including related paper bonus sets and Special Guests scoped to the main set's release date. Commander decks, promos and tokens are excluded from related sets. Product-specific variants are labelled by set code and collector number; some are Collector-only, so the panel states that availability depends on the booster product. Price metadata is fetched every six hours, missing values stay unavailable, and cached top pulls retain their original timestamp during outages. Requests are independent of Livewire match reporting.

Organizers can choose an optional date for the entire play evening when creating or editing a tournament. The date appears on event cards and the event page; the list can be sorted by the playing date. Existing events keep an open date until an organizer selects one.

## TV display

Bookmark **https://mtg.betz.coffee/tv** in Amazon Silk on the Fire TV Stick. This permanent address always selects the newest created tournament that is currently running; if none is running, it shows the newest created tournament regardless of status. Scheduled playing dates do not affect this selection. It keeps checking every ten seconds, even after an event closes, and automatically switches tournaments, including their shared music selection. An empty installation displays a waiting screen until its first tournament is created. For a display pinned to one event, append `/tv` to its public `/play/{invitation}` link. Standings and the current round's tables stay side by side, with shared countdowns and ten-second updates. Results animate into place; reduced-motion preferences disable the animations. The board scales down to fit the available screen height instead of rotating views. Large events will have smaller text. Closed events show the final standings and all tied winners. Both TV addresses keep polling after an event closes so phone music controls still work; the permanent `/tv` address also keeps following new events. Connection failures keep the last board visible and retry automatically.

On your phone, open the selected tournament as a participant, choose **Mehr**, and expand **TV-Musik steuern**. TV display links are intentionally absent from the app navigation; the permanent address remains available directly. Paste a YouTube video or playlist URL and tap **Auf TV abspielen**, or tap **Musik stoppen**. All participants of the tournament currently selected by `/tv` can control its music, including guests using their private return link. Spectators and participants of other events cannot change playback. Changes reach the TV within ten seconds. Sending the same link again restarts it. Music is stored with the tournament and restored when the TV reloads; switching events loads the new event's selection. Run `php artisan migrate` when deploying this update.

The TV attempts playback automatically. If the browser blocks sound, press Play once in the TV's visible player. **Musik** toggles the player panel without stopping playback; stop music from the phone. Score refreshes leave the player running. Videos that disallow embedding need another selection. No YouTube requests are made until a participant supplies a link. The TV page sends only the site's origin to YouTube, as required for embedded playback.

**Vollbild** is available where the browser supports it. The page also requests a screen wake lock where available; verify Silk/Fire TV sleep and screensaver behavior on the actual device before an evening. TV mode is a read-only spectator display with no result or organizer controls.

## Commander league

Choose **Commander · Multiplayer-Liga** when creating an event. Pick balanced tables (prefer four players), smaller tables (prefer three), or larger tables (prefer five), and one to ten rounds (three by default). The event previews the table sizes as players join. Starting each round automatically assigns everyone once, with no byes or elimination stage. Table sizes stay between three and five whenever there are at least three players; two participants play a duel. Six players become 3+3, seven become 3+4, and eight become 4+4 with balanced tables. The selected size is a preference, so awkward player counts still give everyone a game.

Assignments strongly favor fewer repeat opponents, then similar scores. Seating order is randomized. Everyone stays in the league for every round. Smaller tables can reduce waiting after an early elimination. Deck names are no longer requested, stored on new signups, or shown in standings; deck switches between rounds are free. Existing historical deck values are left untouched in the database.

Each table plays one multiplayer game with a shared timer, defaulting to 90 minutes. A win gives the winner three points and everyone else zero. Once time expires, a table member selects the players still in the game: they receive one point each, eliminated players zero. An agreed draw gives every player at the table one point. A table member or organizer submits for the whole table; the first confirmed result is final. Spectators may start timers but cannot submit scores. The next round starts once all tables finish; after the configured round count the organizer closes the event for the crown and podium. Format, table preference and round count cannot change after play starts.

## Organizing

Organizers can join directly on the event page using their signed-in account, while retaining all organizer controls. A guest participation also remains usable after signing in as organizer. Recovering a participation never signs in to its associated user account or grants organizer privileges.

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
npm test
composer audit
npm audit
bash -n deploy/remote.sh
sh -n deploy/entrypoint.sh
docker compose config --quiet
docker build -t tournament:review .
```

CI runs the tests, builds the production image, and exercises an isolated fresh installation. Deployment is manual-only and starts with a new database under `~/mtg/data/database` instead of importing legacy data. Subsequent deployments preserve that directory and back it up before migration.

Result corrections, opponent approval, per-event organizer roles and advanced tie-breakers are future work.
