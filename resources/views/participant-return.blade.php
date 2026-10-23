<x-layouts.app title="Zurück zur Spielrunde">
    <section class="join-card" x-data="{ token: window.location.hash.slice(1), link: '' }" x-init="if (token) history.replaceState(null, '', window.location.pathname)">
        <p class="eyebrow">WILLKOMMEN ZURÜCK</p><h1>Weiter geht’s.</h1><p class="muted">Öffne deinen gespeicherten privaten Rückkehr-Link oder füge ihn hier ein.</p>
        <form method="POST" action="{{ route('play.recover') }}" class="join-form" x-on:submit="$refs.returnToken.value = link ? link.split('#').pop() : token">
            @csrf
            <input type="hidden" name="token" x-ref="returnToken" x-bind:value="token">
            <label x-show="!token">Privater Rückkehr-Link<input type="text" x-model="link" class="link-field" autocomplete="off" placeholder="https://…/return#…"></label>
            @error('token')<p role="alert">{{ $message }}</p>@enderror
            <flux:button type="submit" variant="primary">Zurück zu meinem Turnier</flux:button>
        </form><p class="muted">Link verloren? Bitte die Organisation um Hilfe. Dein Anzeigename allein gibt keinen Zugriff auf deine Anmeldung.</p>
    </section>
</x-layouts.app>
