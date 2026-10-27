<!DOCTYPE html>
<html lang="de" class="dark">
<head>@include('partials.head', ['arena' => true, 'title' => ($tournament?->name ?? 'Spielrunde').' · TV · Spielrunde', 'referrerPolicy' => 'strict-origin-when-cross-origin'])</head>
<body class="arena-body tv-body">
    <main class="tv-screen" data-tv data-music="{{ json_encode(['url' => $tournament?->tv_youtube_url, 'revision' => $tournament?->tv_music_revision]) }}" data-state-url="{{ ($automatic ?? false) ? route('tv.state') : route('play.tv.state', $tournament->invite_token) }}" data-automatic="{{ ($automatic ?? false) ? '1' : '0' }}" data-event="{{ ($automatic ?? false) ? 'automatic' : $tournament->invite_token }}" data-server-time="{{ now()->getTimestampMs() }}" data-closed="{{ $tournament?->status === \App\Enums\Status::CLOSED ? '1' : '0' }}">
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
                <p class="muted">Musik am Handy auf der Turnierseite steuern → TV-Musik steuern.</p>
                <p class="muted" data-tv-music-message role="status">Teilnehmende können einen YouTube-Link senden. Falls nötig, einmal hier auf Play drücken, um Ton zu erlauben.</p>
                <div data-tv-player></div>
            </aside>
        </div>
        <footer class="tv-footer"><span>Ergebnisse & TV-Musik am Handy steuern · Turnierseite öffnen.</span><span data-tv-clock></span></footer>
    </main>
</body>
</html>
