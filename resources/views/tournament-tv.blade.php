<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="dark">
<head>@include('partials.head', ['arena' => true, 'title' => ($tournament?->name ?? 'Arcane Table').' · TV · Arcane Table', 'referrerPolicy' => 'strict-origin-when-cross-origin'])</head>
<body class="arena-body tv-body">
    <main class="tv-screen" data-tv data-invite="{{ $tournament?->invite_token }}" data-music="{{ json_encode(['url' => $tournament?->tv_youtube_url, 'revision' => $tournament?->tv_music_revision]) }}" data-state-url="{{ ($automatic ?? false) ? route('tv.state') : route('play.tv.state', $tournament->invite_token) }}" data-automatic="{{ ($automatic ?? false) ? '1' : '0' }}" data-event="{{ ($automatic ?? false) ? 'automatic' : $tournament->invite_token }}" data-server-time="{{ now()->getTimestampMs() }}" data-closed="{{ $tournament?->status === \App\Enums\Status::CLOSED ? '1' : '0' }}">
        <header class="tv-toolbar">
            <a href="{{ $tournament ? route('play', $tournament->invite_token) : route('home', ['home' => 1]) }}" class="tv-brand" data-tv-event-link>{{ __('Arcane Table') }} <span>{{ __('TV') }}</span></a>
            <p data-tv-connection role="status">{{ $tournament?->status === \App\Enums\Status::CLOSED ? __('Endstand') : 'Live · WebSocket' }}{{ ($automatic ?? false) ? __(' · Automatische Turnierwahl') : '' }}</p>
            <div class="tv-controls">
                <x-table-commentary-controls /><x-language-switch />
                <button type="button" class="utility-button" data-tv-music-toggle aria-expanded="false" aria-controls="tv-music">{{ __('Musik / Video') }}</button>
                <button type="button" class="utility-button" data-tv-video-toggle aria-pressed="false">{{ __('Livestream groß') }}</button>
                <button type="button" class="utility-button" data-tv-fullscreen>{{ __('Vollbild') }}</button>
            </div>
        </header>
        <div class="tv-audio-prompt" data-tv-audio-prompt hidden role="status">
            <span>{{ __('Ton einmal direkt am TV aktivieren. Danach laufen Treffer-Sounds und Ergebnis-Signale automatisch.') }}</span>
            <button type="button" class="utility-button" data-tv-audio-enable>{{ __('TV-Ton aktivieren') }}</button>
        </div>
        <div class="tv-layout">
            <div class="tv-board" data-tv-board>@if($tournament) @include('partials.tv-board') @else @include('partials.tv-empty') @endif</div>
            <aside id="tv-music" class="tv-panel tv-music" hidden>
                <h2>{{ __('Musik & Livestream') }}</h2>
                <p class="muted">{{ __('Musik am Handy auf der Turnierseite steuern: TV-Musik steuern.') }}</p>
                <p class="muted" data-tv-music-message role="status">{{ __('Teilnehmende können einen YouTube-Link senden. Falls nötig, einmal hier auf Play drücken, um Ton zu erlauben.') }}</p>
                <div data-tv-player></div>
            </aside>
        </div>
        <x-table-callout />
        <footer class="tv-footer"><span>{{ __('Ergebnisse & TV-Musik am Handy steuern · Turnierseite öffnen.') }}</span><span data-tv-clock></span></footer>
    </main>
</body>
</html>
