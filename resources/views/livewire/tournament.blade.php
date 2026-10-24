@php
    use App\Enums\Status;
    use App\Enums\RoundMatchResult;
    $participant = $tournament->participant();
    $currentRound = $tournament->currentRound();
    $myMatch = $currentRound?->getMatchForUser();
    $deviceToken = session('participants.'.$tournament->id.'.token', '');
    if (!$participant || !hash_equals((string) $participant->recovery_hash, hash('sha256', $deviceToken))) {
        $deviceToken = '';
    }
    $isOrganizer = auth()->user()?->is_admin;
    $isCommander = $tournament->format === 'commander';
@endphp
<div @if($tournament->status !== Status::CLOSED) wire:poll.10s="refresh" @endif>
    <div data-device-participant data-invite="{{ $tournament->invite_token }}" data-title="{{ $tournament->name }}" data-name="{{ $participant?->user->name }}" data-token="{{ $deviceToken }}" data-participating="{{ $participant ? '1' : '0' }}" data-active="{{ $tournament->status !== Status::CLOSED ? '1' : '0' }}">
        <p class="muted" data-device-message role="status"></p>
    </div>
    <section class="page-intro">
        <p class="eyebrow">{{ $tournament->group->name }} · {{ $tournament->users->count() }} TEILNEHMENDE</p>
        <h1>{{ $tournament->name }}</h1>
        <p class="event-format">{{ match($tournament->format) { 'sealed' => 'Sealed', 'commander' => 'Commander', default => 'Constructed' } }}{{ $tournament->set_name ? ' · '.$tournament->set_name : '' }}</p>
        @if($isCommander)
            <p class="muted">{{ $tournament->round_limit }} Runden · {{ match($tournament->table_preference) { 'small' => 'Möglichst 3 Personen pro Tisch', 'large' => 'Möglichst 5 Personen pro Tisch', default => 'Möglichst 4 Personen pro Tisch' } }} · keine Freilose</p>
            @if($tournament->status === Status::OPEN && $tournament->users->count() >= 2)
                <p class="table-preview">Geplante Tische: {{ implode(' + ', app(\App\Services\CommanderTournamentService::class)->tableSizes($tournament->users->count(), $tournament->table_preference)) }} Personen · alle spielen mit</p>
            @endif
            <p class="muted">Deckwechsel zwischen den Runden sind frei. Sieg: 3 Punkte · bei Zeitlimit: 1 Punkt pro verbliebener Person.</p>
        @endif
        <p class="muted">{{ match($tournament->status) { Status::OPEN => 'Die Anmeldung läuft. Mach es dir am Tisch gemütlich.', Status::IN_PROGRESS => 'Gute Spiele! Hier findest du dein Match und alle Ergebnisse.', Status::CLOSED => 'Alle Spiele sind gespielt. Danke fürs Mitmachen!' } }}</p>
    </section>

    @if($tournament->status === Status::CLOSED)
        <x-winner-celebration :tournament="$tournament" />
    @endif

    <nav class="event-navigation" aria-label="Im Turnier">
        @if($participant)<a href="#my-match">Mein Spiel</a>@endif
    @if(!$participant && $tournament->status === Status::OPEN)<a href="#join">Mitspielen</a>@endif
    <a href="#standings">Tabelle</a>
    @if($currentRound)<a href="#rounds">Runden</a>@endif
    @if($isOrganizer)<a href="#organization">Organisation</a>@endif
</nav>
    @if(!$participant && $tournament->status === Status::OPEN)
        <section id="join" class="join-card">
            <p class="eyebrow">DU BIST DRAN</p><h2>{{ $isOrganizer ? 'Organisieren und mitspielen.' : 'Mitspielen. Ohne Passwort.' }}</h2>
            <p class="muted">{{ auth()->check() ? 'Du spielst mit deinem Konto mit. Die Organisationsfunktionen bleiben verfügbar.' : 'Dein Name am Tisch reicht. Danach bekommst du einen privaten Rückkehr-Link.' }}</p>
            <form method="POST" action="{{ route('play.join', $tournament->invite_token) }}" class="join-form">
                @csrf
                @auth
                    <p>Du spielst als <strong>{{ auth()->user()->name }}</strong>. <a class="text-link" href="{{ route('profile.edit') }}">Namen ändern</a></p>
                @else
                    <flux:input name="name" label="Dein Anzeigename" :value="old('name')" required maxlength="80" autocomplete="nickname" placeholder="So nennen dich die anderen" />
                @endauth
                <flux:button type="submit" variant="primary">Ich bin dabei ↗</flux:button>
            </form>
            <a class="text-link" href="{{ route('play.return') }}">Schon angemeldet? Mit Rückkehr-Link fortsetzen</a>
        </section>
    @elseif(!$participant)
        <p class="notice">Die Anmeldung ist geschlossen. Du kannst die Spiele und Tabelle hier verfolgen.</p>
    @endif

    @if($participant)
        <section id="my-match" class="my-match">
            <p class="eyebrow">DEIN SPIEL · {{ $participant->user->name }}</p>
            @if($myMatch && $isCommander)
                <h2>Dein Tisch {{ $currentRound->matches->values()->search(fn ($match) => $match->id === $myMatch->id) + 1 }} · Runde {{ $currentRound->round }} / {{ $tournament->round_limit }}</h2>
                <p>Mit {{ $myMatch->players->reject(fn ($player) => $player->id === $participant->id)->pluck('user.name')->join(', ') }}</p>
                @if($myMatch->isCompleted())
                    <p class="recorded-score">{{ $myMatch->commander_scores[$participant->id] ?? 0 }} Punkte</p>
                    <p role="status">Ergebnis gespeichert. {{ $tournament->status === Status::CLOSED ? 'Das Turnier ist beendet.' : ($currentRound->round >= $tournament->round_limit ? 'Alle geplanten Runden sind gespielt.' : 'Die nächste Runde erscheint automatisch hier.') }}</p>
                @else
                    <a class="text-link" href="#match-{{ $myMatch->id }}">Zum Tisch · Timer & Ergebnis ↓</a>
                @endif
            @elseif($myMatch)
                @if(!$myMatch->player_b_id)
                    <h2>Diese Runde hast du ein Freilos.</h2><p>2:0 für dich. Die nächste Runde erscheint automatisch hier.</p>
                @else
                    @php
                        $isPlayerA = $myMatch->player_a_id === $participant->id;
                        $opponent = $isPlayerA ? $myMatch->playerB->user->name : $myMatch->playerA->user->name;
                    @endphp
                    <h2>Du <span class="muted">gegen</span> {{ $opponent }}</h2>
                    @if($myMatch->result)
                        <p class="muted">Dein Ergebnis</p>
                        <p class="recorded-score">{{ ($isPlayerA ? $myMatch->result : $myMatch->result->reversed())->label() }}</p>
                        <p role="status">Ergebnis gespeichert. {{ $tournament->status === Status::CLOSED ? 'Das Turnier ist beendet.' : 'Die nächste Runde erscheint automatisch hier.' }}</p>
                    @elseif($tournament->status === Status::IN_PROGRESS)
                        <p><a class="text-link" href="#match-{{ $myMatch->id }}">Zum Match-Timer ↓</a></p>
                        <p>Wie ist dein Match ausgegangen? Wähle das Ergebnis aus deiner Sicht.</p>
                        <div class="score-grid">
                            @foreach(RoundMatchResult::cases() as $result)
                                <button class="score-button" data-outcome="{{ $result === RoundMatchResult::DRAW ? 'draw' : (in_array($result, [RoundMatchResult::TWOZERO, RoundMatchResult::TWOONE]) ? 'win' : 'loss') }}" wire:click="updateCurrentMatchForUser('{{ ($isPlayerA ? $result : $result->reversed())->value }}')" wire:confirm="Dein Ergebnis {{ $result->label() }} gegen {{ $opponent }} speichern?" wire:loading.attr="disabled">
                                    {{ $result->label() }}
                                    @if($result !== RoundMatchResult::DRAW)<small>{{ in_array($result, [RoundMatchResult::TWOZERO, RoundMatchResult::TWOONE]) ? 'Dein Sieg' : 'Deine Niederlage' }}</small>@endif
                                </button>
                            @endforeach
                        </div>
                        <p class="score-help">Einmal bestätigen, dann ist es gespeichert. Es reicht, wenn eine Person das Ergebnis meldet.</p>
                    @endif
                @endif
            @elseif($tournament->status === Status::OPEN)
                <h2>Du bist dabei.</h2><p>Die Organisation startet gleich die erste Runde. Dein Match erscheint automatisch hier.</p>
            @else
                <h2>Danke fürs Mitspielen.</h2>
            @endif
        </section>
        @if($deviceToken)
            <details class="recovery-panel" wire:ignore><summary>Deinen privaten Rückkehr-Link sichern</summary><p class="muted">Speichere diesen Link als Lesezeichen. Wer ihn hat, kann unter deinem Namen Ergebnisse melden. Teile ihn nicht mit der Runde.</p><x-share-link :url="route('play.return').'#'.$deviceToken" label="Privater Rückkehr-Link" /></details>
        @endif
    @endif

    @if($isOrganizer)
        <section id="organization" class="event-card organizer-panel">
            <h2>Organisation</h2>
            @if(session('replacement_links.'.$tournament->id))
                <div class="notice" role="status">Neuer privater Link für {{ session('replacement_links.'.$tournament->id.'.name') }}. Nur an diese Person weitergeben. Der bisherige Link und bestehende Gerätezugriffe sind ungültig.
                    <x-share-link :url="session('replacement_links.'.$tournament->id.'.url')" label="Neuer privater Rückkehr-Link" />
                </div>
            @endif
            <details><summary>Verlorenen Rückkehr-Link ersetzen</summary>
                @foreach($tournament->users as $person)
                    <form method="POST" action="{{ route('participant.recovery', [$tournament, $person]) }}" class="recovery-action" onsubmit="return confirm('Privaten Link für diese Person ersetzen? Bisherige Gerätezugriffe werden beendet.')">
                        @csrf <span>{{ $person->user->name }}</span><flux:button type="submit">Neuer Link</flux:button>
                    </form>
                @endforeach
            </details>
            <p class="muted">Diesen Link mit den Teilnehmenden teilen</p>
            <x-share-link :url="route('play', $tournament->invite_token)" label="Event-Link" share />
            <div class="card-actions">
                @if($tournament->status === Status::OPEN && $tournament->users->count() >= 2)
                    <flux:button variant="primary" wire:click="startNextRound" wire:loading.attr="disabled">Erste Runde starten</flux:button>
                @elseif($tournament->status === Status::OPEN)
                    <p class="muted">Zum Start werden mindestens zwei Teilnehmende benötigt.</p>
                @elseif($tournament->status === Status::IN_PROGRESS)
                    @if($currentRound?->isCompleted() && !$tournament->allMatchesGenerated() && !$hideNextRoundButton)
                        <flux:button variant="primary" wire:click="startNextRound" wire:loading.attr="disabled">Nächste Runde starten</flux:button>
                    @endif
                    @if($isCommander && $currentRound?->isCompleted() && $tournament->allMatchesGenerated())
                        <p class="muted">Alle {{ $tournament->round_limit }} Runden sind gespielt. Zeit für die Siegerehrung!</p>
                    @endif
                    <flux:button wire:click="openFinishModal">Turnier beenden</flux:button>
                @endif
            </div>
        </section>
    @endif

    <section id="standings" class="results-section">
        <div class="section-heading"><h2>{{ $tournament->status === Status::CLOSED ? 'Endstand' : 'Tabelle' }}</h2><span class="muted">{{ $tournament->status === Status::CLOSED ? 'Turnier beendet' : 'Wird automatisch aktualisiert' }}</span></div>
        <x-standings :tournament="$tournament" />
    </section>
    @if($currentRound)
        <section id="rounds" class="results-section"><h2>Runden & Ergebnisse</h2>
        @foreach($tournament->rounds->sortByDesc('round') as $round)
            <details class="round-panel" wire:key="round-{{ $round->id }}" @if($round->id === $currentRound->id) open @endif>
                <summary>Runde {{ $round->round }} <span class="muted">{{ $round->isCompleted() ? 'Abgeschlossen' : 'Läuft' }}</span></summary>
                @foreach($round->matches as $match)
                    @if($isCommander)
                        <x-commander-table :match="$match" :number="$loop->iteration" :active="$tournament->status === Status::IN_PROGRESS && $round->id === $currentRound->id" :can-report="$isOrganizer || ($participant && $match->players->contains('id', $participant->id))" />
                    @else
                    <div id="match-{{ $match->id }}" class="match-card">
                    <div class="match-row"><span>{{ $match->playerA->user->name }}</span><strong>{{ $match->result === RoundMatchResult::DRAW ? 'Remis' : ($match->result?->value ?? 'vs.') }}</strong><span>{{ $match->playerB?->user->name ?? 'Freilos' }}</span></div>
                    @if($tournament->status === Status::IN_PROGRESS && $round->id === $currentRound->id && $match->player_b_id && !$match->result)
                        <x-match-timer :match="$match" />
                    @endif
                    </div>
                    @endif
                @endforeach
            </details>
        @endforeach
        </section>
    @endif
    <flux:modal name="finish-tournament"><div class="space-y-6"><flux:heading size="lg">Turnier beenden?</flux:heading><p>{{ $currentRound && !$currentRound->isCompleted() ? 'Achtung: Es fehlen noch Ergebnisse in der aktuellen Runde.' : 'Die Tabelle wird als Endstand gespeichert.' }}</p><flux:button variant="danger" wire:click="finishTournament">Jetzt beenden</flux:button></div></flux:modal>
    <flux:modal name="no-more-matches"><div class="space-y-6"><flux:heading size="lg">Keine weiteren Paarungen</flux:heading><p>Es konnten keine neuen Paarungen für alle Teilnehmenden gefunden werden. Du kannst das Turnier beenden.</p></div></flux:modal>
</div>
