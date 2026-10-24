@props(['match', 'number', 'active', 'canReport' => false])
<article class="commander-table {{ $canReport ? 'own-table' : '' }}" id="match-{{ $match->id }}" wire:key="commander-table-{{ $match->id }}">
    <div class="section-heading"><h3>Tisch {{ $number }}</h3><span class="status-pill">{{ $match->players->count() }} Personen · {{ $match->isCompleted() ? 'Abgeschlossen' : 'Ein Spiel' }}</span></div>
    <ol class="table-seats">
        @foreach($match->players as $player)
            <li><span class="seat-number">{{ $loop->iteration }}</span><strong>{{ $player->user->name }}</strong>
                @if($match->isCompleted())<span class="seat-points">{{ $match->commander_scores[$player->id] ?? 0 }} Punkte</span>@endif
            </li>
        @endforeach
    </ol>
    @if($active && !$match->isCompleted())
        <x-match-timer :match="$match" commander />
        @if($canReport)
            <div class="commander-report" x-data="{ winner: '', survivors: [] }">
                <p class="muted">Eine Person am Tisch meldet das Ergebnis für alle. Die erste Bestätigung zählt.</p>
                <label for="winner-{{ $match->id }}">Wer hat gewonnen?</label>
                <div class="timer-controls">
                    <select id="winner-{{ $match->id }}" x-model="winner">
                        <option value="">Person auswählen</option>
                        @foreach($match->players as $player)<option value="{{ $player->id }}">{{ $player->user->name }}</option>@endforeach
                    </select>
                    <button class="utility-button" :disabled="!winner" x-on:click="if (confirm('Sieg für die ausgewählte Person speichern?')) $wire.reportCommanderWinner({{ $match->id }}, Number(winner))" wire:loading.attr="disabled">Sieg bestätigen · 3 Punkte</button>
                </div>
                <button class="text-link" wire:click="reportCommanderDraw({{ $match->id }})" wire:confirm="Vereinbartes Remis: alle am Tisch erhalten 1 Punkt. Speichern?" wire:loading.attr="disabled">Vereinbartes Remis für alle</button>
                @if($match->timer_ends_at?->isPast())
                    <fieldset class="timeout-survivors"><legend>Zeit abgelaufen · wer war noch im Spiel?</legend>
                        @foreach($match->players as $player)
                            <label><input type="checkbox" value="{{ $player->id }}" x-model="survivors"> {{ $player->user->name }}</label>
                        @endforeach
                        <button class="utility-button" :disabled="!survivors.length" x-on:click="if (confirm('Zeitlimit-Ergebnis speichern? Die markierten Personen erhalten je 1 Punkt, alle anderen 0.')) $wire.reportCommanderTimeout({{ $match->id }}, survivors)" wire:loading.attr="disabled">Zeitlimit-Ergebnis bestätigen</button>
                    </fieldset>
                @endif
            </div>
        @endif
    @endif
</article>
