@props(['match', 'commander' => false])
@if($match->timer_ends_at)
<div class="match-timer" wire:key="timer-{{ $match->id }}-{{ $match->timer_ends_at?->timestamp ?? 'ready' }}">
    @if($match->timer_ends_at)
        <div x-data="{
            remaining: {{ max(0, $match->timer_ends_at->timestamp - now()->timestamp) }},
            deadline: Date.now() + {{ max(0, $match->timer_ends_at->timestamp - now()->timestamp) * 1000 }},
            interval: null,
            init() { this.interval = setInterval(() => this.remaining = Math.max(0, Math.ceil((this.deadline - Date.now()) / 1000)), 250) },
            destroy() { clearInterval(this.interval) }
        }" x-init="$watch('remaining', value => { if (value === 0) $wire.refresh() })" :class="{ 'timer-expired': remaining === 0 }">
            <span class="timer-digits" role="timer" aria-label="{{ __('Verbleibende Matchzeit') }}" x-text="remaining === 0 ? {{ \Illuminate\Support\Js::from(__('Zeit abgelaufen')) }} : Math.floor(remaining / 60).toString().padStart(2, '0') + ':' + (remaining % 60).toString().padStart(2, '0')">{{ $match->timer_ends_at->isPast() ? __('Zeit abgelaufen') : __('Timer läuft') }}</span>
            <p class="muted" x-show="remaining === 0" x-cloak>{{ $commander ? __('Zeitlimit erreicht. Spiel beenden und die noch im Spiel verbliebenen Personen melden: je 1 Punkt.') : __('Zeitlimit erreicht. Laufenden Zug beenden, dann fünf Extrazüge spielen und das Ergebnis melden.') }}</p>
        </div>
    @endif
</div>
@endif
