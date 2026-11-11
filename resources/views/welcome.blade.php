<x-layouts.app title="{{ __('Arcane Table · Zusammen spielen') }}">
    <section class="welcome-intro">
        <p class="eyebrow">{{ __('KARTEN AUF DEN TISCH.') }}</p>
        <h1>{{ __('Deine Runde.') }}<br><span class="hero-accent">{{ __('Dein nächstes Match.') }}</span></h1>
        <p class="muted">{{ __('Paarungen, Ergebnisse und Tabelle – alles auf deinem Handy.') }}</p>
    </section>
    <section class="saved-events" data-device-home data-auto-return="{{ request()->boolean('home') ? '0' : '1' }}" hidden><h2>{{ __('Deine letzten Spielrunden') }}</h2><p class="muted">{{ __('Auf diesem Gerät gespeichert. Deine Teilnahme wird beim Öffnen automatisch wiederhergestellt.') }}</p><div class="card-actions" data-saved-events></div></section>
    <div class="welcome-grid">
        <section class="join-card">
            <span class="step-number" aria-hidden="true">01</span><h2>{{ __('Ohne Anmeldung mitspielen.') }}</h2>
            <p class="muted">{{ __('Öffne den Event-Link deiner Organisation oder füge ihn hier ein. Dann brauchst du nur noch deinen Namen.') }}</p>
            <form method="GET" action="{{ route('play.open') }}" class="join-form">
                <flux:input name="invite" label="{{ __('Event-Link oder Einladungscode') }}" :value="old('invite')" required maxlength="500" placeholder="https://…/play/…" autocomplete="off" />
                <flux:button type="submit" variant="primary">{{ __('Meine Runde öffnen') }}</flux:button>
            </form>
        </section>
        <div class="welcome-side">
            <section class="event-card"><h2>{{ __('Schon dabei?') }}</h2><p class="muted">{{ __('Mit deinem privaten Rückkehr-Link kommst du direkt zu deinem Spiel.') }}</p><a class="text-link" href="{{ route('play.return') }}">{{ __('Mit Rückkehr-Link fortsetzen') }}</a></section>
            <section class="welcome-organizer"><h2>{{ __('Du organisierst?') }}</h2><p class="muted">{{ __('Turnier erstellen, die Einladung teilen und die Runden starten.') }}</p><a class="text-link" href="{{ auth()->check() ? route('tournaments') : route('login') }}">{{ __('Zur Organisation') }}</a></section>
        </div>
    </div>
</x-layouts.app>
