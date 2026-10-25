@php
    $closed = $tournament->status === \App\Enums\Status::CLOSED;
    $commander = $tournament->format === 'commander';
@endphp
<header class="tv-event">
    <div><p class="eyebrow">{{ $tournament->group->name }} · {{ $tournament->users->count() }} TEILNEHMENDE · {{ $commander ? 'COMMANDER' : strtoupper($tournament->format) }}</p><h1>{{ $tournament->name }}</h1></div>
    <div class="tv-round-label">{{ $closed ? 'Turnier beendet' : ($currentRound ? 'Runde '.$currentRound->round.($commander ? ' / '.$tournament->round_limit : '') : 'Anmeldung läuft') }}
        @if($tournament->set_name)<small>{{ $tournament->set_name }}</small>@endif
        @if($tournament->scheduled_on)<small>{{ $tournament->scheduled_on->format('d.m.Y') }}</small>@endif
    </div>
</header>
@if($closed && $standings->isNotEmpty())
    <div class="tv-winners" data-tv-key="winners">♛ {{ $standings->where('place', 1)->pluck('name')->join(' & ') }} <span>· {{ $standings->where('place', 1)->count() > 1 ? 'Geteilter Turniersieg' : 'Turniersieg' }}</span></div>
@endif
<div class="tv-columns">
    <section class="tv-panel tv-standings">
        <div class="tv-panel-heading"><h2>{{ $closed ? 'Endstand' : 'Tabelle' }}</h2><span>Punkte</span></div>
        <div class="tv-fit"><div class="tv-fit-content">
            @forelse($standings as $standing)
                <div class="tv-standing" data-tv-key="standing-{{ $standing['id'] }}" data-tv-value="{{ $standing['place'] }}:{{ $standing['points'] }}:{{ $standing['games'] }}">
                    <span class="tv-place">{{ $standing['place'] }}</span><strong>{{ $standing['name'] }}</strong><b>{{ $standing['points'] }}</b>
                </div>
            @empty
                <p class="tv-empty">Die ersten Mitspielenden erscheinen hier, sobald sie sich anmelden.</p>
            @endforelse
        </div></div>
    </section>
    <section class="tv-panel tv-tables">
        <div class="tv-panel-heading"><h2>{{ $currentRound ? 'Tische · Runde '.$currentRound->round : 'Die Tische' }}</h2><span>{{ $currentRound ? $currentRound->matches->filter(fn ($match) => $match->isCompleted())->count().' / '.$currentRound->matches->count().' beendet' : 'Gleich geht’s los' }}</span></div>
        <div class="tv-fit"><div class="tv-fit-content tv-match-list">
            @forelse($currentRound?->matches ?? [] as $match)
                <article class="tv-match {{ $match->isCompleted() ? 'is-complete' : '' }}" data-tv-key="match-{{ $match->id }}" data-tv-value="{{ $match->result?->value }}:{{ json_encode($match->commander_scores) }}">
                    <div class="tv-match-heading"><strong>Tisch {{ $loop->iteration }}</strong><span>
                        @if($match->isCompleted()) Beendet
                        @elseif($closed) Ohne Ergebnis
                        @elseif($match->timer_ends_at)<span data-tv-deadline="{{ $match->timer_ends_at->getTimestampMs() }}">{{ $match->timer_ends_at->isPast() ? 'Zeit abgelaufen' : 'Timer läuft' }}</span>
                        @else Bereit @endif
                    </span></div>
                    @if($commander)
                        <div class="tv-seats">
                            @foreach($match->players as $player)
                                <div><strong>{{ $player->user->name }}</strong>@if($match->isCompleted())<b>{{ $match->commander_scores[$player->id] ?? 0 }} P</b>@endif</div>
                            @endforeach
                        </div>
                    @else
                        <div class="tv-duel"><strong>{{ $match->playerA?->user->name }}</strong><b>{{ $match->result?->label() ?? 'vs.' }}</b><strong>{{ $match->playerB?->user->name ?? 'Freilos' }}</strong></div>
                    @endif
                </article>
            @empty
                <p class="tv-empty">Sobald die Organisation die erste Runde startet, erscheinen die Paarungen automatisch hier.</p>
            @endforelse
            @if(!$closed && $currentRound?->isCompleted())<p class="tv-next">Alle Ergebnisse sind da. Die Organisation kann die nächste Runde starten oder das Turnier beenden.</p>@endif
        </div></div>
    </section>
</div>
