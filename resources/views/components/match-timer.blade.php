@props(['match', 'commander' => false])
<div class="match-timer" wire:key="timer-{{ $match->id }}-{{ $match->timer_ends_at?->timestamp ?? 'ready' }}">
    @if($match->timer_ends_at)
        <div x-data="{
            remaining: {{ max(0, $match->timer_ends_at->timestamp - now()->timestamp) }},
            deadline: Date.now() + {{ max(0, $match->timer_ends_at->timestamp - now()->timestamp) * 1000 }},
            interval: null,
            init() { this.interval = setInterval(() => this.remaining = Math.max(0, Math.ceil((this.deadline - Date.now()) / 1000)), 250) },
            destroy() { clearInterval(this.interval) }
        }" :class="{ 'timer-expired': remaining === 0 }">
            <span class="timer-digits" role="timer" aria-label="Verbleibende Matchzeit" x-text="remaining === 0 ? 'Zeit abgelaufen' : Math.floor(remaining / 60).toString().padStart(2, '0') + ':' + (remaining % 60).toString().padStart(2, '0')">{{ $match->timer_ends_at->isPast() ? 'Zeit abgelaufen' : 'Timer läuft' }}</span>
            <p class="muted" x-show="remaining === 0" x-cloak>{{ $commander ? 'Zeitlimit erreicht. Spiel beenden und die noch im Spiel verbliebenen Personen melden: je 1 Punkt.' : 'Zeitlimit erreicht. Laufenden Zug beenden, dann fünf Extrazüge spielen und das Ergebnis melden.' }}</p>
        </div>
    @else
        <div class="timer-controls" x-data="{ minutes: '{{ $commander ? 90 : 50 }}' }">
            <label for="duration-{{ $match->id }}">Matchzeit</label>
            <select id="duration-{{ $match->id }}" x-model="minutes">
                <option value="25">25 Min.</option><option value="50">50 Min.</option><option value="60">60 Min.</option><option value="90">90 Min.</option>
            </select>
            <button class="utility-button" x-on:click="$wire.startMatchTimer({{ $match->id }}, Number(minutes))" wire:loading.attr="disabled">Timer starten</button>
        </div>
        <p class="muted">Für alle sichtbar. Jede Person kann starten; die Zeit lässt sich danach nicht zurücksetzen.</p>
    @endif
</div>
