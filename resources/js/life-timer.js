export function matchTime(deadline, now) {
    if (!Number.isFinite(deadline)) return null;
    const seconds = Math.max(0, Math.ceil((deadline - now) / 1000));
    return seconds ? `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}` : 'Zeit abgelaufen';
}

export function setupLifeTimer(root, getState) {
    const clock = root.querySelector('[data-life-clock]');
    const duration = root.querySelector('[data-life-duration]');
    const enter = root.querySelector('[data-life-start-button]');
    let offset = 0;
    function tick() {
        const state = getState();
        const now = Date.now() + offset;
        clock.textContent = matchTime(state.timerEndsAt, now) || 'Ohne Timer';
        const expired = Number.isFinite(state.timerEndsAt) && state.timerEndsAt <= now;
        clock.classList.toggle('is-expired', expired);
        clock.title = expired ? (state.format === 'commander'
            ? 'Zeitlimit erreicht. Verbleibende Personen auf der Turnierseite melden.'
            : 'Zeitlimit erreicht. Laufenden Zug beenden, dann fünf Extrazüge spielen.') : 'Verbleibende Matchzeit';
    }
    function sync() {
        const state = getState();
        if (Number.isFinite(state.serverTime)) offset = state.serverTime - Date.now();
        const needsTimer = !Number.isFinite(state.timerEndsAt) && state.editable && root.dataset.canControl === '1';
        if (duration) duration.hidden = !needsTimer;
        enter.textContent = needsTimer ? 'Timer und Zähler starten' : 'Zähler öffnen';
        tick();
    }
    root.addEventListener('life:changed', sync);
    document.addEventListener('visibilitychange', tick);
    const interval = setInterval(tick, 1000);
    sync();
    return () => { clearInterval(interval); root.removeEventListener('life:changed', sync); document.removeEventListener('visibilitychange', tick); };
}
