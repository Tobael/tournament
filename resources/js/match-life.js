import { subscribe } from './realtime';
import NoSleep from 'nosleep.js';
import { createScreenAwake } from './screen-awake.js';
import { setupLifeLayout } from './life-layout.js';
import { setupCommanderGesture } from './commander-gesture.js';
import { setupMatchFinish } from './match-finish.js';
import { setupPressHold, createLifeTransactions } from './life-interactions.js';
import { setupQuickActions } from './quick-actions.js';
import { setupLifeTimer } from './life-timer.js';
import { setupLifeEntry } from './edit-life.js';

export function setupMatchLife() {
    const root = document.querySelector('[data-life-counter]');
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = '1';
    let state = JSON.parse(root.dataset.state);
    let pending = Promise.resolve();
    let connected = false;
    let requestFailed = false;
    let pendingWrites = 0;
    const stopLayout = setupLifeLayout(root);

    const error = root.querySelector('[data-life-error]');
    const connection = root.querySelector('[data-life-connection]');
    function updateConnection() { connection.hidden = connected && !requestFailed && navigator.onLine !== false; }
    const transactions = createLifeTransactions({ players: state.players, notify: (id, text) => {
        const output = root.querySelector(`[data-life-seat="${id}"] [data-life-transaction]`);
        if (output) output.textContent = text;
    } });
    function render(next) {
        if (next.matchId !== state.matchId || next.revision < state.revision) return;
        transactions.update(next.players, next.operation);
        state = next;
        for (const player of state.players) {
            const seat = root.querySelector(`[data-life-seat="${player.id}"]`);
            if (!seat) continue;
            seat.querySelector('[data-life-total]').textContent = player.life;
            const wins = seat.querySelector('[data-life-game-wins]');
            if (wins) wins.textContent = player.wins || 0;
            const damage = Object.values(player.damage || {});
            const lethal = damage.some(value => value >= 21);
            seat.classList.toggle('life-lethal', player.life <= 0 || lethal);
            seat.querySelector('[data-life-warning]').textContent = lethal ? '21+ Commander-Schaden' : player.life <= 0 ? 'Keine Lebenspunkte' : '';
            const summary = seat.querySelector('[data-damage-summary]');
            if (summary) summary.textContent = damage.reduce((sum, value) => sum + value, 0) || '';
            for (const output of seat.querySelectorAll('[data-damage]')) output.textContent = player.damage?.[output.dataset.damage] || 0;
        }
        root.dispatchEvent(new CustomEvent('life:changed'));
        for (const button of root.querySelectorAll('[data-life-delta], [data-life-reset]')) button.disabled = !state.editable || root.dataset.canControl !== '1';
        updateConnection();
    }
    async function snapshot() {
        try {
            const response = await fetch(root.dataset.stateUrl, { cache: 'no-store', headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(10000) });
            if (!response.ok) throw new Error();
            render(await response.json());
            error.hidden = true;
            requestFailed = false;
        } catch { requestFailed = true; }
        updateConnection();
    }
    const stopLife = subscribe(`tournament.${root.dataset.invite}`, 'life.changed', data => render(data.state), snapshot, value => { connected = value; render(state); });
    const stopBoard = subscribe('tv-board', 'board.changed', snapshot);
    root.addEventListener('click', event => {
        const button = event.target.closest('[data-life-delta], [data-life-reset]');
        if (!button || button.disabled) return;
        const reset = button.hasAttribute('data-life-reset');
        if (reset && !window.confirm('Neues Spiel? Alle Lebenspunkte und Commander-Schäden an diesem Tisch zurücksetzen?')) return;
        const command = reset ? { action: 'reset', revision: state.revision } : {
            action: 'life',
            player: Number(button.dataset.player), delta: Number(button.dataset.lifeDelta),
        };
        void send(command);
    });
    function send(command) {
        pendingWrites++;
        const task = pending.then(async () => {
            try {
                const response = await fetch(root.dataset.updateUrl, {
                    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify(command), signal: AbortSignal.timeout(10000),
                });
                if (!response.ok) throw new Error();
                render(await response.json());
                error.hidden = true;
                requestFailed = false;
                updateConnection();
                return true;
            } catch {
                requestFailed = true;
                updateConnection();
                await snapshot();
                error.textContent = 'Änderung nicht bestätigt. Bitte Stand prüfen, bevor du erneut tippst.';
                error.hidden = false;
                return false;
            } finally {
                pendingWrites--;
                root.dispatchEvent(new CustomEvent('life:idle'));
            }
        });
        pending = task;
        return task;
    }
    const pressHold = setupPressHold(root, button => send({ action: 'life', player: Number(button.dataset.player), delta: Number(button.dataset.lifeDelta) }));
    const stopTimer = setupLifeTimer(root, () => state);
    const stopQuickActions = setupQuickActions(root, () => state.players);
    const stopFinish = setupMatchFinish(root, { getState: () => state, isBusy: () => pendingWrites > 0 || pressHold.active, send });
    const stopLifeEntry = setupLifeEntry(root, { getState: () => state, send });
    const stopGesture = setupCommanderGesture(root, { getState: () => state, send });
    root.querySelector('[data-life-reload]').addEventListener('click', () => window.location.reload());
    const fallback = navigator.wakeLock ? null : new NoSleep();
    const screenAwake = createScreenAwake({
        document, navigator, fallback,
        notify: mode => { root.dataset.wakeMode = mode; },
    });
    function enterScreen() {
        // Both calls start in the tap handler, before user activation expires.
        void screenAwake.enable();
        if (!document.fullscreenElement && document.documentElement.requestFullscreen) {
            void document.documentElement.requestFullscreen({ navigationUI: 'hide' }).catch(() => {});
        }
    }
    root.addEventListener('pointerdown', () => { if (!screenAwake.active) void screenAwake.enable(); });
    const start = root.querySelector('[data-life-start]');
    const startButton = root.querySelector('[data-life-start-button]');
    const startError = root.querySelector('[data-life-start-error]');
    const needsTimer = () => state.editable && root.dataset.canControl === '1' && !Number.isFinite(state.timerEndsAt);
    start.hidden = !window.matchMedia('(pointer: coarse), (max-width: 720px)').matches && !needsTimer();
    startButton.addEventListener('click', async () => {
        if (startButton.disabled) return;
        enterScreen();
        startButton.disabled = true;
        startError.textContent = '';
        if (needsTimer()) {
            const saved = await send({ action: 'timer', minutes: Number(root.querySelector('[data-life-minutes]').value) });
            if (!saved) {
                startError.textContent = 'Timer nicht bestätigt. Bitte Verbindung prüfen und erneut versuchen.';
                startButton.disabled = false;
                return;
            }
        }
        start.hidden = true;
        startButton.disabled = false;
    });
    const visible = () => { if (document.visibilityState === 'visible') void snapshot(); };
    document.addEventListener('visibilitychange', visible);
    window.addEventListener('online', snapshot);
    window.addEventListener('offline', updateConnection);
    window.addEventListener('pagehide', () => { stopLife(); stopBoard(); stopLayout(); stopGesture(); stopLifeEntry(); stopFinish(); pressHold.destroy(); transactions.destroy(); stopQuickActions(); stopTimer(); screenAwake.destroy(); window.removeEventListener('offline', updateConnection); }, { once: true });
    window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
    render(state);
    void screenAwake.enable();
}
