@props(['format', 'sets', 'tablePreference' => 'balanced', 'roundLimit' => 3])
<flux:select wire:model.live="format" label="Format">
    <option value="sealed">Sealed</option>
    <option value="commander">Commander · Multiplayer-Liga</option>
    <option value="constructed">Constructed / anderes Format</option>
</flux:select>
@if($format === 'sealed')
    <flux:select wire:model="setCode" label="Sealed-Set">
        <option value="">Offen / gemischte Sets</option>
        @foreach($sets as $set)
            <option value="{{ $set['code'] }}">{{ $set['name'] }} ({{ strtoupper($set['code']) }}){{ isset($set['released_at']) && $set['released_at'] > now()->toDateString() ? ' · angekündigt' : '' }}</option>
        @endforeach
    </flux:select>
    <flux:error name="setCode" />
    <p class="muted">{{ count($sets) ? 'Set-Auswahl von Scryfall · täglich aktualisiert, neueste zuerst.' : 'Set-Auswahl gerade nicht erreichbar. Du kannst das Set später ergänzen.' }}</p>
@endif

@if($format === 'commander')
    <flux:select wire:model.live="tablePreference" label="Tischaufteilung">
        <option value="balanced">Ausgewogen · möglichst 4 Personen</option>
        <option value="small">Kleinere Tische · möglichst 3 Personen</option>
        <option value="large">Größere Tische · möglichst 5 Personen</option>
    </flux:select>
    <flux:input wire:model="roundLimit" type="number" min="1" max="10" label="Anzahl Runden" />
    <p class="muted">Alle spielen jede Runde. Automatisch 3–5 Personen pro Tisch, bei nur zwei Teilnehmenden ein Duell. Kleinere Tische verkürzen meist die Wartezeit nach dem Ausscheiden. Keine Freilose. Deckwechsel zwischen den Runden sind frei.</p>
    <p class="muted">Sieg: 3 Punkte · Zeitlimit: 1 Punkt pro verbliebener Person · ausgeschieden: 0 Punkte. Bei vereinbartem Remis erhalten alle am Tisch 1 Punkt.</p>
@endif
