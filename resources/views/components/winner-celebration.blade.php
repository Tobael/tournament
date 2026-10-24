@props(['tournament'])
@php
    $standings = $tournament->standings();
    $winners = $standings->where('place', 1);
@endphp
@if($winners->isNotEmpty() && $tournament->matches->contains(fn ($match) => $match->isCompleted()))
    <section class="winner-celebration" aria-label="Siegerehrung">
        <div class="winner-rays" aria-hidden="true"></div>
        <svg class="winner-crown" viewBox="0 0 96 80" fill="none" aria-hidden="true"><path d="M12 22L30 38L48 10L66 38L84 22L75 62H21L12 22Z" fill="currentColor"/><path d="M22 72H74" stroke="currentColor" stroke-width="6" stroke-linecap="round"/><circle cx="12" cy="17" r="5" fill="currentColor"/><circle cx="48" cy="7" r="5" fill="currentColor"/><circle cx="84" cy="17" r="5" fill="currentColor"/></svg>
        <p class="eyebrow">{{ $winners->count() > 1 ? 'GEMEINSAM AN DER SPITZE' : 'DIE KRONE GEHT AN' }}</p>
        <h2>{{ $winners->pluck('name')->join(' & ') }}</h2>
        <p class="winner-points">{{ $winners->first()['points'] }} Punkte · {{ $winners->count() > 1 ? 'Geteilter Turniersieg' : 'Turniersieg' }}</p>
        <p class="muted">Gut gespielt. Danke an die ganze Runde!</p>
        <div class="winner-podium">
            @foreach($standings->where('place', '<=', 3)->groupBy('place') as $place => $players)
                <div class="podium-place" data-place="{{ $place }}"><span class="podium-number">{{ $place }}</span><strong>{{ $players->pluck('name')->join(' & ') }}</strong><span>{{ $players->first()['points'] }} Punkte</span></div>
            @endforeach
        </div>
    </section>
@endif
