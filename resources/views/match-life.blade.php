<!DOCTYPE html>
<html lang="de" class="dark">
<head>@include('partials.head', ['arena' => true, 'title' => 'Lebenspunkte · '.$tournament->name])</head>
<body class="arena-body life-body">
<main class="life-screen" data-life-counter data-commander="{{ $tournament->format === 'commander' ? '1' : '0' }}" data-invite="{{ $tournament->invite_token }}" data-state-url="{{ route('play.life.state', [$tournament->invite_token, $match->id]) }}" data-update-url="{{ route('play.life.update', [$tournament->invite_token, $match->id]) }}" data-can-control="{{ $canControl ? '1' : '0' }}" data-state="{{ json_encode($state) }}">
    <header class="life-toolbar">
        <a class="life-toolbar-button" href="{{ route('play', $tournament->invite_token) }}" aria-label="Zurück zum Turnier" title="Zurück zum Turnier">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14 6-6 6 6 6M8 12h12" /></svg>
        </a>
        <div class="life-clock-group">
            <span class="life-clock" data-life-clock role="timer" aria-label="Verbleibende Matchzeit">{{ $match->timer_ends_at ? 'Timer läuft' : 'Ohne Timer' }}</span>
            <span class="life-connection-warning" data-life-connection hidden role="status" aria-label="Verbindung unterbrochen" title="Verbindung unterbrochen. Verbindung wird wiederhergestellt.">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m3 3 18 18M2 8a17 17 0 0 1 3-2m4-1a17 17 0 0 1 13 3M5 12a11 11 0 0 1 4-2m5 0a11 11 0 0 1 5 2M8 16a6 6 0 0 1 6-1" /><circle cx="12" cy="20" r="1" fill="currentColor" stroke="none" /></svg>
            </span>
        </div>
        <button type="button" class="life-toolbar-button" data-life-reload aria-label="Zähler neu laden" title="Zähler neu laden">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 4v6h-6M20 10a8 8 0 1 0 0 5" /></svg>
        </button>
    </header>
    @unless($canControl)<span class="sr-only">Zuschauermodus. Nur die Personen am Tisch und die Organisation können ändern.</span>@endunless
    <div class="life-start" data-life-start hidden><div>
        <h1>Bereit am Tisch?</h1>
        <p>Matchzeit und Lebenszähler gemeinsam starten. Das Display bleibt nach Möglichkeit an.</p>
        @if($canControl)
            <label class="life-duration" data-life-duration>Matchzeit
                <select data-life-minutes aria-label="Matchzeit in Minuten">
                    @foreach([25, 50, 60, 90] as $minutes)<option value="{{ $minutes }}" @selected($minutes === ($tournament->format === 'commander' ? 90 : 50))>{{ $minutes }} Minuten</option>@endforeach
                </select>
            </label>
        @endif
        <p data-life-start-error role="alert"></p>
        <button type="button" class="utility-button" data-life-start-button>Zähler starten</button>
    </div></div>
    <p class="notice" data-life-error role="alert" hidden></p>
    @if($tournament->format === 'commander' && $canControl)
        <svg class="damage-drag-overlay" data-damage-overlay hidden aria-hidden="true"><line data-damage-line /></svg>
        <div class="damage-drag-ghost" data-damage-ghost hidden aria-hidden="true"></div>
        <dialog class="damage-dialog" data-damage-dialog aria-labelledby="damage-dialog-title">
            <form>
                <p id="damage-dialog-title" class="damage-direction"><span data-damage-dealer></span> an <span data-damage-recipient-name></span> <small>DMG</small></p>
                <div class="damage-counter">
                    <button type="button" data-damage-step="-1" aria-label="Ein Commander-Schaden weniger">-</button>
                    <output data-damage-amount aria-label="Änderung des Commander-Schadens" aria-live="polite">0</output>
                    <button type="button" data-damage-step="1" aria-label="Ein Commander-Schaden mehr">+</button>
                </div>
                <p data-damage-dialog-error role="alert"></p>
                <div class="damage-dialog-actions"><button type="button" data-damage-cancel aria-label="Abbrechen">Abbrechen</button><button type="submit" data-damage-apply aria-label="Commander-Schaden anwenden" disabled>Übernehmen</button></div>
            </form>
        </dialog>
    @endif
    @if($canControl)
        <dialog class="life-finish-dialog" data-life-finish aria-labelledby="life-finish-title">
            <form>
                <h2 id="life-finish-title">Ist <strong data-life-winner-name></strong> der Gewinner dieses Spiels?</h2>
                <p class="muted" data-life-win-description></p>
                <p data-life-finish-error role="alert"></p>
                <div><button type="button" class="utility-button" data-life-finish-cancel>Nein, weiterspielen</button><button type="submit" class="utility-button" data-life-finish-confirm>Ja, Sieg bestätigen</button></div>
            </form>
        </dialog>
    @endif
    @if($canControl)
        <dialog class="life-entry-dialog" data-life-entry aria-labelledby="life-entry-title">
            <form>
                <h2 id="life-entry-title">Leben für <span data-life-entry-name></span></h2>
                <p class="muted">Aktuell: <output data-life-entry-current></output></p>
                <label>Neuer Lebensstand<input data-life-entry-value type="number" inputmode="numeric" min="-999" max="9999" step="1" required autofocus /></label>
                <p data-life-entry-error role="alert"></p>
                <div><button type="button" class="utility-button" data-life-entry-cancel>Abbrechen</button><button type="submit" class="utility-button" data-life-entry-save>Übernehmen</button></div>
            </form>
        </dialog>
    @endif
    <div class="life-grid" data-seats="{{ count($state['players']) }}">
        <div class="life-quick-actions" data-quick-actions>
            <button type="button" class="life-quick-toggle" data-quick-toggle aria-expanded="false" aria-controls="life-quick-menu" aria-label="Schnellaktionen" title="Würfeln oder Spieler auswählen"><x-dice-icon :sides="20" /></button>
            <div id="life-quick-menu" class="life-quick-menu" data-quick-menu hidden role="group" aria-label="Schnellaktionen">
                @foreach([2, 4, 6, 8, 10, 12, 20] as $die)
                    <button type="button" data-quick-die="{{ $die }}" aria-label="{{ $die === 2 ? 'Münze werfen' : 'd'.$die.' würfeln' }}"><x-dice-icon :sides="$die" /><span>d{{ $die }}{{ $die === 2 ? ' Münze' : '' }}</span></button>
                @endforeach
                <button type="button" class="quick-random-player" data-quick-player aria-label="Zufälligen Spieler auswählen"><svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="20" cy="12" r="5"/><path d="M10 34v-5a10 10 0 0 1 20 0v5M7 11a4 4 0 1 0 0 8m26-8a4 4 0 1 1 0 8M3 32v-5a7 7 0 0 1 6-7m28 12v-5a7 7 0 0 0-6-7"/></svg><span>Spieler wählen</span></button>
                @if($canControl)
                    <button type="button" data-life-reset>Neues Spiel</button>
                    <button type="button" data-life-finish-reopen hidden>Spiel beenden</button>
                @endif
            </div>
            <output class="life-quick-result" data-quick-result hidden role="status" aria-live="polite"></output>
        </div>
        @foreach($state['players'] as $seat)
            <section class="life-seat" data-life-seat="{{ $seat['id'] }}" @if($tournament->format === 'commander' && $canControl) data-damage-tile @endif style="--seat-color: {{ ['#c6fb65', '#bca0ff', '#65d5fb', '#ffba75', '#fb83b4'][$loop->index % 5] }}; --seat-background: {{ ['#526f26', '#624584', '#236988', '#8d5522', '#843759'][$loop->index % 5] }}">
                <div class="life-seat-content">
                <div class="life-seat-heading"><h1 class="life-player-name {{ $tournament->format === 'commander' ? 'life-commander-name' : '' }}" @if($tournament->format === 'commander' && $canControl) data-damage-handle tabindex="0" role="button" aria-label="{{ $seat['name'] }}: halten und zum Schadensempfänger ziehen; mit Enter und Pfeiltasten Empfänger wählen" @endif>{{ $seat['name'] }}</h1><button type="button" class="life-rotate" data-life-rotate aria-label="Anzeige für {{ $seat['name'] }} drehen" title="Drehen"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 8V3m0 5h-5M20 8a8 8 0 1 0 0 8"/></svg></button></div>
                <div class="life-total-row">
                    <button type="button" @disabled(!$canControl) data-life-delta="-1" data-player="{{ $seat['id'] }}" aria-label="{{ $seat['name'] }}: ein Leben weniger">-</button>
                    <output data-life-total @if($canControl) data-life-edit-target tabindex="0" role="button" aria-label="Lebenspunkte für {{ $seat['name'] }} ändern" title="Doppelklicken oder doppeltippen, um Leben einzugeben" @endif>{{ $seat['life'] }}</output>
                    <button type="button" @disabled(!$canControl) data-life-delta="1" data-player="{{ $seat['id'] }}" aria-label="{{ $seat['name'] }}: ein Leben mehr">+</button>
                </div>
                @if($tournament->format !== 'commander')<span class="life-game-wins">Spielsiege <output data-life-game-wins>{{ $seat['wins'] }}</output></span>@endif
                <output class="life-transaction" data-life-transaction aria-label="Letzte Lebenspunkteänderung" aria-live="polite"></output>
                <p data-life-warning class="life-warning"></p>
                @if($tournament->format === 'commander')
                    <div class="life-damage-readout" aria-label="Erhaltener Commander-Schaden">
                        @foreach($state['players'] as $source)
                            @if($source['id'] !== $seat['id'])
                                <span title="Schaden von {{ $source['name'] }}">{{ $source['name'] }} <output data-damage="{{ $source['id'] }}">0</output></span>
                            @endif
                        @endforeach
                    </div>
                @endif
                </div>
            </section>
        @endforeach
    </div>
</main>
</body>
</html>
