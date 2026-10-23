<x-layouts.app title="Spielrunde · Zusammen spielen">
    <section class="welcome-intro">
        <p class="eyebrow">KARTEN AUF DEN TISCH.</p>
        <h1>Deine Runde.<br><span class="hero-accent">Dein nächstes Match.</span></h1>
        <p class="muted">Paarungen, Ergebnisse und Tabelle – alles auf deinem Handy.</p>
    </section>
    <section class="saved-events" data-device-home data-auto-return="{{ request()->boolean('home') ? '0' : '1' }}" hidden><h2>Deine letzten Spielrunden</h2><p class="muted">Auf diesem Gerät gespeichert. Deine Teilnahme wird beim Öffnen automatisch wiederhergestellt.</p><div class="card-actions" data-saved-events></div></section>
    <div class="welcome-grid">
        <section class="join-card">
            <span class="step-number" aria-hidden="true">01</span><h2>Ohne Anmeldung mitspielen.</h2>
            <p class="muted">Öffne den Event-Link deiner Organisation oder füge ihn hier ein. Dann brauchst du nur noch deinen Namen.</p>
            <form method="GET" action="{{ route('play.open') }}" class="join-form">
                <flux:input name="invite" label="Event-Link oder Einladungscode" :value="old('invite')" required maxlength="500" placeholder="https://…/play/…" autocomplete="off" />
                <flux:button type="submit" variant="primary">Meine Runde öffnen ↗</flux:button>
            </form>
        </section>
        <div class="welcome-side">
            <section class="event-card"><span class="step-number" aria-hidden="true">↩</span><h2>Schon dabei?</h2><p class="muted">Mit deinem privaten Rückkehr-Link kommst du direkt zu deinem Spiel.</p><a class="text-link" href="{{ route('play.return') }}">Mit Rückkehr-Link fortsetzen</a></section>
            <section class="welcome-organizer"><h2>Du organisierst?</h2><p class="muted">Turnier erstellen, die Einladung teilen und die Runden starten.</p><a class="text-link" href="{{ auth()->check() ? route('tournaments') : route('login') }}">Zur Organisation ↗</a></section>
        </div>
    </div>
</x-layouts.app>
