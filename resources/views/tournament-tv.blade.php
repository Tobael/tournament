<!DOCTYPE html>
<html lang="de" class="dark">
<head>@include('partials.head', ['arena' => true, 'title' => ($tournament?->name ?? 'Spielrunde').' · TV · Spielrunde', 'referrerPolicy' => 'strict-origin-when-cross-origin'])</head>
<body class="arena-body tv-body">
    <main class="tv-screen" data-tv data-state-url="{{ ($automatic ?? false) ? route('tv.state') : route('play.tv.state', $tournament->invite_token) }}" data-automatic="{{ ($automatic ?? false) ? '1' : '0' }}" data-event="{{ ($automatic ?? false) ? 'automatic' : $tournament->invite_token }}" data-server-time="{{ now()->getTimestampMs() }}" data-closed="{{ $tournament?->status === \App\Enums\Status::CLOSED ? '1' : '0' }}">
        <header class="tv-toolbar">
            <a href="{{ $tournament ? route('play', $tournament->invite_token) : route('home', ['home' => 1]) }}" class="tv-brand" data-tv-event-link>Spielrunde <span>TV</span></a>
            <p data-tv-connection role="status">{{ $tournament?->status === \App\Enums\Status::CLOSED ? 'Endstand' : 'Live · Aktualisiert alle 10 Sekunden' }}{{ ($automatic ?? false) ? ' · Automatische Turnierwahl' : '' }}</p>
            <div class="tv-controls">
                <button type="button" class="utility-button" data-tv-music-toggle aria-expanded="false" aria-controls="tv-music">Musik</button>
                <button type="button" class="utility-button" data-tv-fullscreen>Vollbild</button>
            </div>
        </header>
        <div class="tv-layout">
            <div class="tv-board" data-tv-board>@if($tournament) @include('partials.tv-board') @else @include('partials.tv-empty') @endif</div>
            <aside id="tv-music" class="tv-panel tv-music" hidden>
                <h2>Musik für den Spielabend</h2>
                <form data-tv-music-form>
                    <label for="tv-youtube">YouTube-Video oder Playlist</label>
                    <input id="tv-youtube" class="link-field" type="url" required placeholder="https://www.youtube.com/watch?v=…" autocomplete="off">
                    <div class="tv-controls"><button class="utility-button" type="submit">Laden</button><button class="utility-button" type="button" data-tv-music-stop>Stoppen</button></div>
                </form>
                <p class="muted" data-tv-music-message role="status">Link einfügen, laden und im Player auf Play drücken. Manche Videos erlauben keine Einbettung.</p>
                <div data-tv-player></div>
                <p class="muted">Beim Schließen von Musik wird die Wiedergabe gestoppt. Der Link bleibt auf diesem Gerät gespeichert.</p>
            </aside>
        </div>
        <footer class="tv-footer"><span>Ergebnisse am Handy melden · Diese Ansicht bleibt automatisch aktuell.</span><span data-tv-clock></span></footer>
    </main>
</body>
</html>
