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
    $canControlTv = $participant && \App\Models\Tournament::forTv()?->id === $tournament->id;
    $isCommander = $tournament->format === 'commander';
@endphp
<div class="tournament-page" x-data="{
    tab: 'spiel',
    followHash(hash) {
        if (!hash) return;
        if (hash === '#standings') this.tab = 'tabelle';
        else if (hash === '#rounds' || hash.startsWith('#match-')) this.tab = 'runden';
        else if (['#tv-remote', '#set-cards', '#organization', '#recovery'].includes(hash)) this.tab = 'mehr';
        else if (['#my-match', '#join'].includes(hash)) this.tab = 'spiel';
        this.$nextTick(() => document.getElementById(hash.slice(1))?.scrollIntoView({ block: 'start' }));
    }
}" x-init="followHash(window.location.hash)" @hashchange.window="followHash(window.location.hash)" @click="followHash($event.target.closest('a')?.hash)" data-round-realtime>
    <div data-device-participant data-invite="{{ $tournament->invite_token }}" data-title="{{ $tournament->name }}" data-name="{{ $participant?->user->name }}" data-token="{{ $deviceToken }}" data-participating="{{ $participant ? '1' : '0' }}" data-active="{{ $tournament->status !== Status::CLOSED ? '1' : '0' }}">
        <p class="muted" data-device-message role="status"></p>
    </div>
    <p class="muted" data-round-connection role="status">{{ __('Live-Verbindung wird aufgebaut …') }}</p>
    <section class="page-intro">
        <x-set-visual :code="$tournament->format === 'sealed' ? $tournament->set_code : null" :name="$tournament->set_name" variant="background" :context="'background-'.$tournament->id">
        <p class="eyebrow">{{ $tournament->group->name }} · {{ $tournament->users->count() }} {{ __('TEILNEHMENDE') }}</p>
        <h1>{{ $tournament->name }}</h1>
        @if($tournament->scheduled_on)
            <p class="event-date"> <time datetime="{{ $tournament->scheduled_on->format('Y-m-d') }}">{{ $tournament->scheduled_on->format('d.m.Y') }}</time> {{ __('· Euer Spielabend') }}</p>
        @endif
        <p class="event-format">{{ match($tournament->format) { 'sealed' => __('Sealed'), 'commander' => __('Commander'), default => __('Constructed') } }}{{ $tournament->set_name ? ' · '.$tournament->set_name : '' }}</p>
        @if($isCommander)
            <p class="muted">{{ $tournament->round_limit }} {{ __('Runden ·') }} {{ match($tournament->table_preference) { 'small' => 'Möglichst 3 Personen pro Tisch', 'large' => 'Möglichst 5 Personen pro Tisch', default => 'Möglichst 4 Personen pro Tisch' } }} {{ __('· keine Freilose') }}</p>
            @if($tournament->status === Status::OPEN && $tournament->users->count() >= 2)
                <p class="table-preview">{{ __('Geplante Tische:') }} {{ implode(' + ', app(\App\Services\CommanderTournamentService::class)->tableSizes($tournament->users->count(), $tournament->table_preference)) }} {{ __('Personen · alle spielen mit') }}</p>
            @endif
            <p class="muted">{{ __('Deckwechsel zwischen den Runden sind frei. Sieg: 3 Punkte · bei Zeitlimit: 1 Punkt pro verbliebener Person.') }}</p>
        @endif
        <p class="muted">{{ match($tournament->status) { Status::OPEN => __('Die Anmeldung läuft. Mach es dir am Tisch gemütlich.'), Status::IN_PROGRESS => __('Gute Spiele! Hier findest du dein Match und alle Ergebnisse.'), Status::CLOSED => __('Alle Spiele sind gespielt. Danke fürs Mitmachen!') } }}</p>
        </x-set-visual>
    </section>

    @if($tournament->status === Status::CLOSED)
        <x-winner-celebration :tournament="$tournament" />
    @endif

    <nav class="event-navigation" role="tablist" aria-label="{{ __('Im Turnier') }}"
        @keydown.arrow-right.prevent="const tabs = [...$el.querySelectorAll('[role=tab]')]; const next = tabs[(tabs.indexOf(document.activeElement) + 1) % tabs.length]; next.focus(); next.click()"
        @keydown.arrow-left.prevent="const tabs = [...$el.querySelectorAll('[role=tab]')]; const next = tabs[(tabs.indexOf(document.activeElement) + tabs.length - 1) % tabs.length]; next.focus(); next.click()"
        @keydown.home.prevent="$el.firstElementChild.focus(); $el.firstElementChild.click()"
        @keydown.end.prevent="$el.lastElementChild.focus(); $el.lastElementChild.click()">
        @foreach(['spiel' => 'Spiel', 'tabelle' => 'Tabelle', 'runden' => 'Runden', 'mehr' => 'Mehr'] as $tab => $label)
            <button type="button" role="tab" id="tab-{{ $tab }}" aria-controls="panel-{{ $tab }}" :aria-selected="tab === '{{ $tab }}'" :tabindex="tab === '{{ $tab }}' ? 0 : -1" @click="tab = '{{ $tab }}'">{{ $label }}</button>
        @endforeach
    </nav>
    <div id="panel-spiel" class="event-tab-panel" role="tabpanel" aria-labelledby="tab-spiel" x-show="tab === 'spiel'">
    @if(!$participant && $tournament->status === Status::OPEN)
        <section id="join" class="join-card">
            <p class="eyebrow">{{ __('DU BIST DRAN') }}</p><h2>{{ $isOrganizer ? __('Organisieren und mitspielen.') : __('Mitspielen. Ohne Passwort.') }}</h2>
            <p class="muted">{{ auth()->check() ? __('Du spielst mit deinem Konto mit. Die Organisationsfunktionen bleiben verfügbar.') : __('Dein Name am Tisch reicht. Danach bekommst du einen privaten Rückkehr-Link.') }}</p>
            <form method="POST" action="{{ route('play.join', $tournament->invite_token) }}" class="join-form">
                @csrf
                @auth
                    <p>{{ __('Du spielst als') }} <strong>{{ auth()->user()->name }}</strong>. <a class="text-link" href="{{ route('profile.edit') }}">{{ __('Namen ändern') }}</a></p>
                @else
                    <flux:input name="name" label="{{ __('Dein Anzeigename') }}" :value="old('name')" required maxlength="80" autocomplete="nickname" placeholder="{{ __('So nennen dich die anderen') }}" />
                @endauth
                <flux:button type="submit" variant="primary">{{ __('Ich bin dabei') }}</flux:button>
            </form>
            <a class="text-link" href="{{ route('play.return') }}">{{ __('Schon angemeldet? Mit Rückkehr-Link fortsetzen') }}</a>
        </section>
    @elseif(!$participant)
        <p class="notice">{{ __('Die Anmeldung ist geschlossen. Du kannst die Spiele und Tabelle hier verfolgen.') }}</p>
    @endif

    @if($myMatch?->isLifeCounterActive($tournament))
        <p class="life-counter-link"><a class="utility-button" href="{{ route('play.life', [$tournament->invite_token, $myMatch->id]) }}">{{ __('Lebenszähler öffnen') }}</a></p>
    @endif
    @if($participant)
        <section id="my-match" class="my-match">
            <p class="eyebrow">{{ __('DEIN SPIEL ·') }} {{ $participant->user->name }}</p>
            @if($myMatch && $isCommander)
                <h2>{{ __('Dein Tisch') }} {{ $currentRound->matches->values()->search(fn ($match) => $match->id === $myMatch->id) + 1 }} {{ __('· Runde') }} {{ $currentRound->round }} / {{ $tournament->round_limit }}</h2>
                <p>{{ __('Mit') }} {{ $myMatch->players->reject(fn ($player) => $player->id === $participant->id)->pluck('user.name')->join(', ') }}</p>
                @if($myMatch->isCompleted())
                    <p class="recorded-score">{{ $myMatch->commander_scores[$participant->id] ?? 0 }} {{ __('Punkte') }}</p>
                    <p role="status">{{ __('Ergebnis gespeichert.') }} {{ $tournament->status === Status::CLOSED ? __('Das Turnier ist beendet.') : ($currentRound->round >= $tournament->round_limit ? __('Alle geplanten Runden sind gespielt.') : __('Die nächste Runde erscheint automatisch hier.')) }}</p>
                @else
                    <a class="text-link" href="#match-{{ $myMatch->id }}">{{ __('Tisch und Ergebnis') }}</a>
                @endif
            @elseif($myMatch)
                @if(!$myMatch->player_b_id)
                    <h2>{{ __('Diese Runde hast du ein Freilos.') }}</h2><p>{{ __('2:0 für dich. Die nächste Runde erscheint automatisch hier.') }}</p>
                @else
                    @php
                        $isPlayerA = $myMatch->player_a_id === $participant->id;
                        $opponent = $isPlayerA ? $myMatch->playerB->user->name : $myMatch->playerA->user->name;
                    @endphp
                    <h2>{{ __('Du') }} <span class="muted">{{ __('gegen') }}</span> {{ $opponent }}</h2>
                    @if($myMatch->result)
                        <p class="muted">{{ __('Dein Ergebnis') }}</p>
                        <p class="recorded-score">{{ ($isPlayerA ? $myMatch->result : $myMatch->result->reversed())->label() }}</p>
                        <p role="status">{{ __('Ergebnis gespeichert.') }} {{ $tournament->status === Status::CLOSED ? __('Das Turnier ist beendet.') : __('Die nächste Runde erscheint automatisch hier.') }}</p>
                    @elseif($tournament->status === Status::IN_PROGRESS)
                        <p>{{ __('Wie ist dein Match ausgegangen? Wähle das Ergebnis aus deiner Sicht.') }}</p>
                        <div class="score-grid">
                            @foreach(RoundMatchResult::cases() as $result)
                                <button class="score-button" data-outcome="{{ $result === RoundMatchResult::DRAW ? 'draw' : (in_array($result, [RoundMatchResult::TWOZERO, RoundMatchResult::TWOONE]) ? 'win' : 'loss') }}" wire:click="updateCurrentMatchForUser('{{ ($isPlayerA ? $result : $result->reversed())->value }}')" wire:confirm="{{ __('Dein Ergebnis :score gegen :name speichern?', ['score' => $result->label(), 'name' => $opponent]) }}" wire:loading.attr="disabled">
                                    {{ $result->label() }}
                                    @if($result !== RoundMatchResult::DRAW)<small>{{ in_array($result, [RoundMatchResult::TWOZERO, RoundMatchResult::TWOONE]) ? __('Dein Sieg') : __('Deine Niederlage') }}</small>@endif
                                </button>
                            @endforeach
                        </div>
                        <p class="score-help">{{ __('Einmal bestätigen, dann ist es gespeichert. Es reicht, wenn eine Person das Ergebnis meldet.') }}</p>
                    @endif
                @endif
            @elseif($tournament->status === Status::OPEN)
                <h2>{{ __('Du bist dabei.') }}</h2><p>{{ __('Die Organisation startet gleich die erste Runde. Dein Match erscheint automatisch hier.') }}</p>
            @else
                <h2>{{ __('Danke fürs Mitspielen.') }}</h2>
            @endif
        </section>

    @endif

    </div>
    <div id="panel-mehr" class="event-tab-panel event-extras" role="tabpanel" aria-labelledby="tab-mehr" x-show="tab === 'mehr'" x-cloak>
        @if($participant && $deviceToken)
            <details id="recovery" class="recovery-panel" wire:ignore.self><summary>{{ __('Deinen privaten Rückkehr-Link sichern') }}</summary><p class="muted">{{ __('Speichere diesen Link als Lesezeichen. Wer ihn hat, kann unter deinem Namen Ergebnisse melden. Teile ihn nicht mit der Runde.') }}</p><x-share-link :url="route('play.return').'#'.$deviceToken" label="{{ __('Privater Rückkehr-Link') }}" /></details>
        @endif
    @if($canControlTv)
        <details id="tv-remote" class="event-card music-remote" wire:ignore.self>
            <summary>{{ __('TV-Musik steuern') }} <span class="muted">{{ __('YouTube') }}</span></summary>
            <div class="music-remote-content">
            <p class="muted">{{ __('Alle Teilnehmenden dieses Turniers können die Musik steuern. YouTube-Link am Handy einfügen und auf dem TV abspielen.') }}</p>
            <form wire:submit="controlTvMusic" class="join-form">
                <flux:input wire:model="tvYoutube" type="url" label="{{ __('YouTube-Video oder Playlist') }}" placeholder="https://www.youtube.com/watch?v=…" required maxlength="500" />
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Auf TV abspielen') }}</flux:button>
            </form>
            <flux:button type="button" wire:click="controlTvMusic(true)" wire:loading.attr="disabled">{{ __('Musik stoppen') }}</flux:button>
            @if(session('tv-music-status'))<p role="status">{{ session('tv-music-status') }}</p>@endif
            <p class="muted">{{ __('Änderungen erscheinen innerhalb von 10 Sekunden. Falls der Browser Ton blockiert, einmal am TV auf Play drücken. Manche Videos erlauben keine Einbettung.') }}</p>
            </div>
        </details>
    @endif

    @if($isOrganizer)
        <section id="organization" class="event-card organizer-panel">
            <h2>{{ __('Organisation') }}</h2>
            @if(session('replacement_links.'.$tournament->id))
                <div class="notice" role="status">{{ __('Neuer privater Link für') }} {{ session('replacement_links.'.$tournament->id.'.name') }}{{ __('. Nur an diese Person weitergeben. Der bisherige Link und bestehende Gerätezugriffe sind ungültig.') }}
                    <x-share-link :url="session('replacement_links.'.$tournament->id.'.url')" label="{{ __('Neuer privater Rückkehr-Link') }}" />
                </div>
            @endif
            <details><summary>{{ __('Verlorenen Rückkehr-Link ersetzen') }}</summary>
                @foreach($tournament->users as $person)
                    <form method="POST" action="{{ route('participant.recovery', [$tournament, $person]) }}" class="recovery-action" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Privaten Link für diese Person ersetzen? Bisherige Gerätezugriffe werden beendet.')) }})">
                        @csrf <span>{{ $person->user->name }}</span><flux:button type="submit">{{ __('Neuer Link') }}</flux:button>
                    </form>
                @endforeach
            </details>
            <p class="muted">{{ __('Diesen Link mit den Teilnehmenden teilen') }}</p>
            <x-share-link :url="route('play', $tournament->invite_token)" label="{{ __('Event-Link') }}" share />
            <div class="card-actions">
                @if($tournament->status === Status::OPEN && $tournament->users->count() >= 2)
                    <flux:button variant="primary" wire:click="startNextRound" wire:loading.attr="disabled">{{ __('Erste Runde starten') }}</flux:button>
                @elseif($tournament->status === Status::OPEN)
                    <p class="muted">{{ __('Zum Start werden mindestens zwei Teilnehmende benötigt.') }}</p>
                @elseif($tournament->status === Status::IN_PROGRESS)
                    @if($currentRound?->isCompleted() && !$tournament->allMatchesGenerated() && !$hideNextRoundButton)
                        <flux:button variant="primary" wire:click="startNextRound" wire:loading.attr="disabled">{{ __('Nächste Runde starten') }}</flux:button>
                    @endif
                    @if($isCommander && $currentRound?->isCompleted() && $tournament->allMatchesGenerated())
                        <p class="muted">{{ __('Alle') }} {{ $tournament->round_limit }} {{ __('Runden sind gespielt. Zeit für die Siegerehrung!') }}</p>
                    @endif
                    <flux:button wire:click="openFinishModal">{{ __('Turnier beenden') }}</flux:button>
                @endif
            </div>
        </section>
    @endif

    @if($tournament->format === 'sealed' && $tournament->set_code)
        <x-set-card-tools :code="$tournament->set_code" :name="$tournament->set_name" />
    @endif

        @if(!$participant && !$isOrganizer && !($tournament->format === 'sealed' && $tournament->set_code))
            <p class="muted">{{ __('Hier findest du als Teilnehmer deinen Rückkehr-Link und die Musiksteuerung.') }}</p>
        @endif
    </div>
    <div id="panel-tabelle" class="event-tab-panel" role="tabpanel" aria-labelledby="tab-tabelle" x-show="tab === 'tabelle'" x-cloak>
    <section id="standings" class="results-section">
        <div class="section-heading"><h2>{{ $tournament->status === Status::CLOSED ? __('Endstand') : __('Tabelle') }}</h2><span class="muted">{{ $tournament->status === Status::CLOSED ? __('Turnier beendet') : __('Wird automatisch aktualisiert') }}</span></div>
        <x-standings :tournament="$tournament" />
    </section>
    </div>
    <div id="panel-runden" class="event-tab-panel" role="tabpanel" aria-labelledby="tab-runden" x-show="tab === 'runden'" x-cloak>
    @if($currentRound)
        <section id="rounds" class="results-section"><h2>{{ __('Runden & Ergebnisse') }}</h2>
        @foreach($tournament->rounds->sortByDesc('round') as $round)
            <details class="round-panel" wire:key="round-{{ $round->id }}" @if($round->id === $currentRound->id) open @endif>
                <summary>{{ __('Runde') }} {{ $round->round }} <span class="muted">{{ $round->isCompleted() ? __('Abgeschlossen') : __('Läuft') }}</span></summary>
                @foreach($round->matches as $match)
                    @if($isCommander)
                        <x-commander-table :match="$match" :number="$loop->iteration" :active="$match->isLifeCounterActive($tournament)" :can-report="$isOrganizer || ($participant && $match->players->contains('id', $participant->id))" />
                    @else
                    <div id="match-{{ $match->id }}" class="match-card">
                    <div class="match-row"><span>{{ $match->playerA->user->name }}</span><strong>{{ $match->result === RoundMatchResult::DRAW ? __('Remis') : ($match->result?->value ?? ($match->game_wins ? ($match->game_wins[$match->player_a_id] ?? 0).'–'.($match->game_wins[$match->player_b_id] ?? 0) : 'vs.')) }}</strong><span>{{ $match->playerB?->user->name ?? __('Freilos') }}</span></div>
                    @if($match->isLifeCounterActive($tournament))
                        <p><a class="text-link" href="{{ route('play.life', [$tournament->invite_token, $match->id]) }}">{{ __('Lebenszähler öffnen') }}</a></p>
                        <x-match-timer :match="$match" />
                    @endif
                    </div>
                    @endif
                @endforeach
            </details>
        @endforeach
        </section>
    @endif
        @if(!$currentRound)<p class="notice">{{ __('Noch keine Runde gestartet. Sobald es losgeht, erscheinen die Paarungen hier.') }}</p>@endif
    </div>
    <flux:modal name="finish-tournament"><div class="space-y-6"><flux:heading size="lg">{{ __('Turnier beenden?') }}</flux:heading><p>{{ $currentRound && !$currentRound->isCompleted() ? __('Achtung: Es fehlen noch Ergebnisse in der aktuellen Runde.') : __('Die Tabelle wird als Endstand gespeichert.') }}</p><flux:button variant="danger" wire:click="finishTournament">{{ __('Jetzt beenden') }}</flux:button></div></flux:modal>
    <flux:modal name="no-more-matches"><div class="space-y-6"><flux:heading size="lg">{{ __('Keine weiteren Paarungen') }}</flux:heading><p>{{ __('Es konnten keine neuen Paarungen für alle Teilnehmenden gefunden werden. Du kannst das Turnier beenden.') }}</p></div></flux:modal>
</div>
