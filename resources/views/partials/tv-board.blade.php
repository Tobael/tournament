@php
    $closed = $tournament->status === \App\Enums\Status::CLOSED;
    $commander = $tournament->format === 'commander';
@endphp
<header class="tv-event" data-tv-scoreboard data-event="{{ $tournament->id }}" data-round="{{ $currentRound?->round }}" data-closed="{{ $closed ? '1' : '0' }}">
    <div><p class="eyebrow">{{ $tournament->group->name }} · {{ $tournament->users->count() }} {{ __('TEILNEHMENDE ·') }} {{ $commander ? 'COMMANDER' : strtoupper($tournament->format) }}</p><h1>{{ $tournament->name }}</h1></div>
    <div class="tv-round-label">{{ $closed ? __('Turnier beendet') : ($currentRound ? __('Runde ').$currentRound->round.($commander ? ' / '.$tournament->round_limit : '') : __('Anmeldung läuft')) }}
        @if($tournament->set_name)<small>{{ $tournament->set_name }}</small>@endif
        @if($tournament->scheduled_on)<small>{{ $tournament->scheduled_on->format('d.m.Y') }}</small>@endif
    </div>
</header>
@if($closed && $standings->isNotEmpty())
    <div class="tv-winners" data-tv-key="winners">{{ $standings->where('place', 1)->pluck('name')->join(' & ') }} <span>· {{ $standings->where('place', 1)->count() > 1 ? __('Geteilter Turniersieg') : 'Turniersieg' }}</span></div>
@endif
<div class="tv-columns">
    <section class="tv-panel tv-standings">
        <div class="tv-panel-heading"><h2>{{ $closed ? __('Endstand') : __('Tabelle') }}</h2><span>{{ __('Punkte') }}</span></div>
        <div class="tv-fit"><div class="tv-fit-content">
            @forelse($standings as $standing)
                <div class="tv-standing" data-tv-standing="{{ $standing['id'] }}" data-points="{{ $standing['points'] }}" data-games="{{ $standing['games'] }}" data-place="{{ $standing['place'] }}" data-tv-key="standing-{{ $standing['id'] }}" data-tv-value="{{ $standing['place'] }}:{{ $standing['points'] }}:{{ $standing['games'] }}">
                    <span class="tv-place">{{ $standing['place'] }}</span><strong>{{ $standing['name'] }}</strong><b>{{ $standing['points'] }}</b>
                </div>
            @empty
                <p class="tv-empty">{{ __('Die ersten Mitspielenden erscheinen hier, sobald sie sich anmelden.') }}</p>
            @endforelse
        </div></div>
    </section>
    <section class="tv-panel tv-tables">
        <div class="tv-panel-heading"><h2>{{ $currentRound ? 'Tische · Runde '.$currentRound->round : __('Die Tische') }}</h2><span>{{ $currentRound ? $currentRound->matches->filter(fn ($match) => $match->isCompleted())->count().' / '.$currentRound->matches->count().' beendet' : __('Gleich geht’s los') }}</span></div>
        <div class="tv-fit"><div class="tv-fit-content tv-match-list">
            @forelse($currentRound?->matches ?? [] as $match)
                @php
                    $counterActive = $match->isLifeCounterActive($tournament);
                    $tvPlayers = $commander ? $match->players : collect([$match->playerA, $match->playerB])->filter()->values();
                    $tvScores = $commander ? ($match->commander_scores ?? []) : ($match->game_wins ?? []);
                    if (!$commander && $match->result && !$tvScores) {
                        $resultScores = explode('-', $match->result->value);
                        $tvScores = [$match->player_a_id => (int) $resultScores[0], $match->player_b_id => (int) ($resultScores[1] ?? 0)];
                    }
                    $tvResult = ['completed' => $match->isCompleted(), 'players' => $tvPlayers->map(fn ($player) => ['id' => $player->id, 'name' => $player->user->name, 'score' => (int) ($tvScores[$player->id] ?? 0)])->values()->all()];
                @endphp
                <article class="tv-match {{ $match->isCompleted() ? 'is-complete' : '' }}" data-tv-result="{{ json_encode($tvResult) }}" data-tv-key="match-{{ $match->id }}" data-tv-value="{{ $match->result?->value }}:{{ json_encode($match->commander_scores) }}:{{ json_encode($match->game_wins) }}">
                    <div class="tv-match-heading"><strong>{{ __('Tisch') }} {{ $loop->iteration }}</strong>@if(!$commander && $match->player_b_id && !$match->isCompleted())<span data-tv-game-score>{{ __('Spiele') }} {{ $match->game_wins[$match->player_a_id] ?? 0 }}–{{ $match->game_wins[$match->player_b_id] ?? 0 }}</span>@endif<span>
                        @if($match->isCompleted()) {{ __('Beendet') }}
                        @elseif($closed) {{ __('Ohne Ergebnis') }}
                        @elseif($match->timer_ends_at)<span data-tv-deadline="{{ $match->timer_ends_at->getTimestampMs() }}">{{ $match->timer_ends_at->isPast() ? __('Zeit abgelaufen') : __('Timer läuft') }}</span>
                        @else {{ __('Bereit') }} @endif
                    </span></div>
                    @if(!$counterActive)
                    @if($commander)
                        <div class="tv-seats">
                            @foreach($match->players as $player)
                                <div><strong>{{ $player->user->name }}</strong>@if($match->isCompleted())<b>{{ $match->commander_scores[$player->id] ?? 0 }} {{ __('P') }}</b>@endif</div>
                            @endforeach
                        </div>
                    @else
                        <div class="tv-duel"><strong>{{ $match->playerA?->user->name }}</strong><b>{{ $match->result?->label() ?? 'vs.' }}</b><strong>{{ $match->playerB?->user->name ?? __('Freilos') }}</strong></div>
                    @endif
                    @endif
                    @if($counterActive)
                        @php($life = app(\App\Services\MatchLife::class)->snapshot($tournament, $match))
                        <div class="tv-life-seats" data-tv-life-match="{{ $match->id }}" data-life-revision="{{ $life['revision'] }}" data-tv-life-state="{{ json_encode($life) }}">
                            @foreach($life['players'] as $seat)
                                @php($maxDamage = max([0, ...array_values($seat['damage'])]))
                                <div class="{{ $seat['life'] <= 0 || $maxDamage >= 21 ? 'life-lethal' : '' }}" data-tv-life-player="{{ $seat['id'] }}">
                                    <span>{{ $seat['name'] }}</span><strong><b data-tv-life-total>{{ $seat['life'] }}</b></strong><output class="tv-life-transaction" data-tv-life-transaction aria-live="polite"></output>
                                    @if($commander)<small data-tv-life-damage>{{ $maxDamage ? 'CMDR '.$maxDamage.'/21' : '' }}</small>@endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>
            @empty
                <p class="tv-empty">{{ __('Sobald die Organisation die erste Runde startet, erscheinen die Paarungen automatisch hier.') }}</p>
            @endforelse
            @if(!$closed && $currentRound?->isCompleted())<p class="tv-next">{{ __('Alle Ergebnisse sind da. Die Organisation kann die nächste Runde starten oder das Turnier beenden.') }}</p>@endif
        </div></div>
    </section>
</div>
