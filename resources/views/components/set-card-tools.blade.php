@props(['code', 'name'])
<section id="set-cards" class="set-card-tools event-card" data-set-card-tools data-code="{{ $code }}" data-top-url="{{ route('set.top-pulls', $code) }}" data-lookup-url="{{ route('set.card-lookup', $code) }}" wire:key="set-card-tools-{{ $code }}" wire:ignore>
    <p class="eyebrow">DEIN SEALED-POOL</p>
    <h2>Karten & Werte</h2>
    <form class="card-number-search" data-card-search>
        <label>Set / Bonus-Sheet<select name="subset" data-card-subset><option value="{{ $code }}">{{ $name }} ({{ strtoupper($code) }})</option></select></label>
        <label>Kartennummer<input name="number" type="text" inputmode="text" placeholder="z. B. 0119" required maxlength="16" autocomplete="off" /></label>
        <button type="submit" class="arena-button">Wert prüfen ↗</button>
    </form>
    <p class="card-search-status muted" data-card-search-status role="status"></p>
    <div class="card-lookup-result" data-card-search-result aria-live="polite" hidden></div>
    <details class="top-pulls-panel" data-top-pulls>
        <summary>Top Pulls nach Wert <span class="muted">{{ $name }}</span></summary>
        <div class="section-heading"><label>Preise für <select data-pull-finish><option value="normal">Normal</option><option value="foil">Foil</option></select></label><span class="muted" data-price-updated></span></div>
        <p class="muted" data-pull-scope></p>
        <p class="muted" data-pull-status role="status">Kartenwerte werden geladen …</p>
        <div class="top-pulls-grid" data-pull-grid></div>
    </details>
    <p class="price-note">EUR-Richtpreise via Scryfall / Cardmarket · Preisabruf alle 6 Stunden. Inklusive Sondervarianten; welche davon ziehbar sind, hängt vom Boosterprodukt ab. Fehlende Preise werden nicht als 0 € gewertet.</p>
</section>
