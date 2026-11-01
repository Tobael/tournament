# Spielrunde · Magic tournaments

A mobile-first app for a Magic playgroup. The stack is PHP 8.4.1+, Laravel 12, Livewire 4, SQLite, Tailwind CSS and free Flux components. Node 22 builds CSS; production runs PHP-FPM and nginx in one container. No paid UI credentials, websocket server, Redis or queue worker are required. A small JavaScript module handles copying links and remembering participation on the device.

## Playing

Organizers share the event link shown in the Organisation panel. Participants enter a display name without registering an account. The device stores the event-scoped return token in local storage as well as the server session. Opening the event restores participation automatically after session expiry. The homepage returns to the most recent active round; use the logo to open another round. Disabled local storage falls back to the normal session and private return link. A private return link restores it on another phone or after the session expires; bookmark it and keep it private. Only a SHA-256 hash of the random return token is stored on the participant record. Return links pass the token in a fragment, then submit by POST.

Players see their current match first and choose results from their own perspective. Either player may report; the first confirmed submission wins. Standings and rounds refresh every ten seconds while a tournament is active; background tabs are throttled by Livewire, and completed tournaments stop polling. For Sealed and Constructed, points count individual game wins (2–1 awards two points and one point); a draw awards one each. Tied players share a place. Spectators can view an event link but cannot report results.

New tournaments default to Sealed. Organizers can choose a set when creating or editing an event, or leave it open for mixed pools. The Scryfall catalog refreshes on demand every 24 hours, lists paper core, expansion, Masters and special draft sets newest first, and retains the last successful response during outages. Announced sets are marked. Saved event set names remain available even if the catalog is unavailable. The selected set appears with its original symbol on compact event cards, a page-wide atmospheric backdrop that fades down into the page, and a live selection preview. Symbols are cached locally for a month; artwork metadata refreshes daily, keeps the last successful response during outages and retries failures after five minutes. Artist credits and a Scryfall source link accompany every illustration. The backdrop scrolls more slowly than the content with a passive, frame-throttled parallax handler. Reduced-motion mode uses a static background. Imagery loads separately from match reporting; unavailable imagery falls back to the set name and code.

Every unfinished match in the current round has a shared 25, 50, 60 or 90 minute timer started exclusively inside its life counter by a table participant or organizer. Runden & Ergebnisse contains only the counter link and a read-only countdown once running. The server stores its deadline; refreshes and other devices show the same countdown. A timer can only start once and cannot be reset. At zero it displays a time-limit message and extra-turn guidance; players still report the actual result. Timers do not award automatic wins or draws. Closing an event displays a crown and podium for the final standings, including shared winners. Podium heights stay fixed in first/second/third order even on small screens or with long tied names; full names remain in the standings.

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

Each table plays one multiplayer game with a shared timer, defaulting to 90 minutes. A win gives the winner three points and everyone else zero. Once time expires, a table member selects the players still in the game: they receive one point each, eliminated players zero. An agreed draw gives every player at the table one point. A table member or organizer submits for the whole table; the first confirmed result is final. Spectators can view counters and timers, but cannot start timers or submit scores. The next round starts once all tables finish; after the configured round count the organizer closes the event for the crown and podium. Format, table preference and round count cannot change after play starts.

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


### Live life-counter POC and livestream TV

Every table has a **Lebenszähler öffnen** link in the round view. Participants also get a shortcut in their **Spiel** tab. Any participant at that table (including recovered guests) can control all seats on a shared phone; organizers can control any table. Observers can view the counter. The layout automatically arranges and rotates two to five seats around the device, adapts to portrait/landscape, and lets each seat adjust its orientation with the **Drehen** SVG icon. Only **− / +** controls are shown. **Vollbild** expands the display, and **Neues Spiel** resets that table after confirmation. Duels start at 20 life; Commander tables start at 40. Each round/table stores its own state. On phones the counter fills the viewport; tap **Zähler starten** to request fullscreen and keep-awake mode.

Commander damage is recorded by the receiving player and opponent, with a single commander per opponent. Adding damage subtracts life; correcting damage restores life. Life at zero and 21 damage from one opponent are highlighted. When exactly one player remains (positive life and, in Commander, less than 21 damage from every opponent), controlling participants are asked directly whether that player won this game. Confirming records that player as the Commander table winner (3 points) and returns to the tournament. Sealed/Constructed uses persisted best-of-three game wins: each confirmation adds exactly one game (1–0 or 0–1 initially). Before either player reaches two wins, both life totals reset to 20 and the counter stays open with the same timer. The final match result is recorded only at two wins (2–0, 0–2, 2–1 or 1–2), then clients return to the tournament. Declining leaves the counters editable and the central quick-action menu can reopen the prompt. Confirmation checks the current revision and lone survivor on the server; stale or duplicate confirmations cannot overwrite a result. Controlling counter clients leave only after the match is complete. The TV shows all active tables’ life totals and their highest commander-damage total.

The TV button **Livestream groß** gives the existing YouTube player about three quarters of the screen, with tournament information alongside it. **Turnieransicht** returns to the board layout. This choice is saved on the TV browser and changing layouts keeps playback running. YouTube live/video/playlist links use the existing **TV-Musik steuern** phone controls; providers other than YouTube are outside this POC.

Local setup for an existing checkout:

```sh
composer install
npm ci
php artisan migrate
npm run build
```

Copy the `REVERB_*` and `BROADCAST_CONNECTION=reverb` settings from `.env.example` into `.env`. Generate distinct random values for `REVERB_APP_KEY` and `REVERB_APP_SECRET`, for example with `openssl rand -hex 32`. Keep the secret server-side. `REVERB_ALLOWED_ORIGINS` lists browser hostnames without schemes or ports. For local `artisan serve`, `REVERB_BROWSER_PORT=8080` connects the browser directly to Reverb; ensure your chosen hostname is allowed.

```sh
composer dev
```

This starts Laravel, Vite and Reverb. Alternatively start `php artisan reverb:start` alongside the web server. The browser key and port are rendered at runtime, so changing credentials does not require a Vite rebuild. If using Laravel's config cache, run `php artisan config:clear` after changing settings.

Docker starts and monitors Reverb alongside PHP-FPM and nginx. Set `REVERB_APP_KEY` and `REVERB_APP_SECRET` in the production `.env` before rebuilding. Compose configures internal broadcasts over `127.0.0.1:8080`; nginx proxies browser `/app/` WebSocket connections on the existing HTTPS origin. No additional public port or queue worker is needed. Apply migrations using the existing deployment procedure, then restart the container after changing Reverb configuration.

Life snapshots are pushed over tournament-specific WebSocket channels. TV/round changes emit a data-free notification on `tv-board`, after which clients request a fresh authorized snapshot. Initial subscription, reconnect and returning to the TV/counter trigger resync; there is no periodic state polling. WebSocket disconnects are shown explicitly. Commands use CSRF-protected HTTP POSTs with server-side membership validation and compare-and-swap revisions to preserve simultaneous taps; reset requires the displayed revision. Failed commands are never blindly replayed.

Validation: `php artisan test`, `npm test`, and `npm run build`. `tests/Feature/MatchLifeTest.php` covers spectators, table boundaries, recovered identities, resets, damage corrections, event payloads, TV totals and closed rounds.

To populate a local checkout for interactive testing, run:

```sh
php artisan db:seed --class=LocalTournamentSeeder
```

This local/testing-only seeder creates four running tournaments: Commander with **3**, **4** and **5** players (one table each), plus Sealed with **4** players (two duels). The first existing organizer is seated in all four tournaments; their password and two-factor settings are preserved. If no organizer exists, the seeder creates `organizer@tournament.test` with password `local-dev-password`. Re-running preserves existing fixture games and life counters. Start the app with `composer dev`, sign in as the organizer, and open the tournament list; open `/tv` in a second window.

For phone testing on the same LAN, start Laravel and Reverb with `--host=0.0.0.0` and add the computer’s LAN IP to `REVERB_ALLOWED_ORIGINS` in `.env`. Open `http://<LAN-IP>:8000` on the phone; the browser connects to port 8080 for WebSockets. Restart Reverb after changing allowed origins. The counter requests a native screen wake lock on HTTPS and reacquires it when returning to the page or if released while visible. Local HTTP uses NoSleep.js’s video fallback after a tap. Wake-lock requests retry automatically on interaction; there is no permanent wake-status control. Browsers/OS power policies can refuse or revoke keep-awake; keep the counter in the foreground. Fullscreen needs a tap and depends on browser support; the viewport layout also works without it.

Commander gesture: hold anywhere on a player's **tile** (including the life number) for 450 ms, drag onto the receiving player's panel, and release. The dealer is outlined, the current recipient highlights, and a line follows the drag. The only damage-entry UI is a translucent counter labeled **dealer an recipient · DMG**, with −/+ taps, **Übernehmen** to apply and **Abbrechen** to cancel. It starts at zero and can also subtract previously recorded damage. Applying submits one atomic update to commander damage and life. Releasing on the dealer, outside the table, or cancelling applies nothing. Keyboard users can focus the dealer’s name, press Enter/Space, use arrows to highlight the recipient, and press Enter/Space again to open the same counter. Per-opponent damage totals on seats are read-only. Life buttons, rotation and menus keep their own controls and do not start a drag. Tile text and numbers cannot be selected or natively dragged.

If phone access is blocked by the workstation's firewalld public zone, this checkout includes a temporary home-subnet exception for the current test address. From the project directory run `sudo sh deploy/local-phone-access.sh`. It permits only TCP 8000 and 8080 from `10.0.10.0/24` for two hours, without changing permanent rules. Remove it early with `sudo sh deploy/local-phone-access.sh close`. The app and Reverb must still be running on `0.0.0.0`. Phones on mobile data, guest Wi-Fi with device isolation, or another subnet need connectivity to this LAN; this URL is not a public internet address.


Counter interactions: tapping changes one life; holding a life or damage −/+ button repeats after 350 ms and stops on release, pointer cancellation, leaving the button, or backgrounding. Life-repeat waits for each server response so it cannot queue an unbounded burst. Every counter client and the TV show each player’s net life change during a burst for five seconds after the last change; opposite taps cancel to an empty indicator. Reset clears these indicators. Desktop arithmetic controls have explicit sizes and contrast; observers see them disabled and remain read-only.

The centered dice SVG button opens d2 (coin: Kopf/Zahl), d4, d6, d8, d10, d12 and d20, plus **Zufälliger Spieler**. Results appear locally for five seconds; player selection includes everyone seated at this table. Rolls use browser cryptographic randomness with rejection sampling. These quick actions do not change life totals or tournament results.

Quick-action SVGs use distinct shapes for each die and a coin for d2. Dice/coin results render solid 3D meshes on Canvas with perspective, face lighting, projected labels and a moving contact shadow. d4/d6/d8/d10/d12/d20 use their respective polyhedra; the coin has two faces and a ridged cylindrical rim. The roll settles with the chosen result face up; a regular d4 instead rests on a face and shows the selected number at its upper vertex. Random-player selection cycles the seat highlight and slows before settling. The random result is chosen once before animation starts. Reduced-motion preferences skip the animation, and leaving the page cancels pending animation callbacks. Commander names stay underlined on desktop and in read-only counter views; editing still requires table membership or organizer access.


The tournament page provides only the life-counter entry; there is no separate timer-start control or action. Opening the counter without an existing timer offers a combined timer/counter start (Commander defaults to 90 minutes; Sealed/Constructed to 50, with 25/50/60/90 available). The shared remaining time is shown in the counter toolbar and updates across devices via the existing WebSocket notifications. Countdown ticks are local, with a server-clock offset; there is no time polling. Starting again or resetting life for the next game preserves the original match deadline. On expiry the clock changes color and its tooltip contains the format-specific next steps.

The life-counter toolbar contains only a back icon, the shared timer and a full-page reload icon. A crossed-out connection SVG appears only while WebSocket connectivity or state fetching is disrupted (including browser offline status); no healthy-connection label or separate toolbar menu is shown. Screen wake lock/fullscreen activation still happens automatically through the start flow. **Neues Spiel** and reopening a declined winner confirmation remain in the existing central dice/quick-action menu.

Die labels use area-weighted face centroids, fitted ink bounds within the face’s inscribed safe radius, and analytic projection tangents. Side labels remain dark; only the actual result becomes white near the end of a roll. Regression checks cover every result of every die: the result face must be uppermost (d4: upper vertex and level supporting face), and highlighted labels must match that result.

Rebuild only generated local fixtures with `php artisan app:seed-local --reset` (local/testing environments only). This removes tournaments named `Lokaler Test…` inside the dedicated `Lokale Testrunde` group and recreates the four demo events. Other tournaments and organizer credentials remain intact. Without `--reset`, `php artisan app:seed-local` preserves existing demo progress. Apply migrations before starting the updated best-of-three counter.

Double-click or double-tap the life number to enter an absolute new life total; keyboard users can focus it and press Enter/Space. The modal shows the current total, accepts integers from −999 to 9999 and requires **Übernehmen**. Only table participants/organizers can edit. The server checks the displayed revision before applying, preserves commander damage, game wins and the match timer, then broadcasts the change. If the game changes while editing, the modal closes; concurrent changes require checking and reconfirming. Short double taps are separate from holding and dragging the tile for commander damage, and numbers remain unselectable outside the input.
