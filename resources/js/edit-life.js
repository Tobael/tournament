export function createDoubleTap({ open, now = () => performance.now() }) {
    let pressed;
    let previous;
    return {
        down(pointer, player, x, y) { pressed = { pointer, player, x, y, at: now(), moved: false }; },
        move(pointer, x, y) { if (pressed?.pointer === pointer && Math.hypot(x - pressed.x, y - pressed.y) > 12) pressed.moved = true; },
        up(pointer, player, x, y) {
            if (pressed?.pointer !== pointer) return false;
            const tap = pressed;
            pressed = null;
            const at = now();
            if (tap.moved || at - tap.at > 280 || player !== tap.player || Math.hypot(x - tap.x, y - tap.y) > 12) { previous = null; return false; }
            if (previous?.player === player && at - previous.at <= 350 && Math.hypot(x - previous.x, y - previous.y) <= 24) {
                previous = null;
                open(player);
            } else previous = { player, at, x, y };
            return true;
        },
        cancel() { pressed = null; previous = null; },
    };
}

export function setupLifeEntry(root, { getState, send }) {
    if (root.dataset.canControl !== '1') return () => {};
    const dialog = root.querySelector('[data-life-entry]');
    const form = dialog.querySelector('form');
    const input = dialog.querySelector('[data-life-entry-value]');
    const current = dialog.querySelector('[data-life-entry-current]');
    const error = dialog.querySelector('[data-life-entry-error]');
    const submit = dialog.querySelector('[data-life-entry-save]');
    const cancelButton = dialog.querySelector('[data-life-entry-cancel]');
    let playerId;
    let revision;
    let game;
    let saving = false;
    let lastShortTap = false;
    function open(id) {
        const state = getState();
        if (!state.editable || root.querySelector('dialog[open]')) return;
        const player = state.players.find(player => player.id === id);
        if (!player) return;
        playerId = id; revision = state.revision; game = state.gameNumber;
        dialog.querySelector('[data-life-entry-name]').textContent = player.name;
        input.value = player.life;
        current.textContent = player.life;
        error.textContent = '';
        submit.disabled = false;
        dialog.showModal();
        input.focus({ preventScroll: true });
        input.select();
    }
    const taps = createDoubleTap({ open });
    function target(element) { return element?.closest?.('[data-life-edit-target]'); }
    const player = element => Number(element?.closest('[data-life-seat]')?.dataset.lifeSeat) || null;
    function down(event) {
        lastShortTap = false;
        const total = target(event.target);
        if (!total || event.button !== 0 || event.isPrimary === false || !getState().editable) { taps.cancel(); return; }
        taps.down(event.pointerId, player(total), event.clientX, event.clientY);
    }
    const move = event => taps.move(event.pointerId, event.clientX, event.clientY);
    function up(event) {
        const total = target(root.ownerDocument.elementFromPoint(event.clientX, event.clientY));
        lastShortTap = taps.up(event.pointerId, player(total), event.clientX, event.clientY);
    }
    const nativeDouble = event => {
        const total = target(event.target);
        if (total) { event.preventDefault(); if (lastShortTap) open(player(total)); }
    };
    function keyboard(event) {
        const total = target(event.target);
        if (total && ['Enter', ' '].includes(event.key)) { event.preventDefault(); open(player(total)); }
    }
    function sync() {
        if (!dialog.open) return;
        const state = getState();
        if (!state.editable || state.gameNumber !== game) { dialog.close(); return; }
        current.textContent = state.players.find(player => player.id === playerId)?.life ?? '';
    }
    async function save(event) {
        event.preventDefault();
        if (saving || !form.reportValidity()) return;
        const state = getState();
        if (!state.editable || state.gameNumber !== game) { dialog.close(); return; }
        if (state.revision !== revision) {
            revision = state.revision;
            error.textContent = 'Der Stand wurde geändert. Bitte Wert prüfen und erneut bestätigen.';
            return;
        }
        saving = true; submit.disabled = true;
        const saved = await send({ action: 'set-life', player: playerId, value: Number(input.value), revision });
        saving = false;
        if (saved) dialog.close();
        else {
            revision = getState().revision;
            error.textContent = 'Wert nicht bestätigt. Aktuellen Stand prüfen und erneut versuchen.';
        }
        submit.disabled = false;
    }
    const cancel = () => dialog.close();
    const stopTap = () => taps.cancel();
    root.addEventListener('pointerdown', down, true);
    root.ownerDocument.addEventListener('pointermove', move, true);
    root.ownerDocument.addEventListener('pointerup', up, true);
    root.ownerDocument.addEventListener('pointercancel', stopTap);
    root.ownerDocument.addEventListener('visibilitychange', stopTap);
    root.addEventListener('dblclick', nativeDouble);
    root.addEventListener('keydown', keyboard);
    root.addEventListener('life:changed', sync);
    form.addEventListener('submit', save);
    cancelButton.addEventListener('click', cancel);
    return () => {
        taps.cancel();
        root.removeEventListener('pointerdown', down, true);
        root.ownerDocument.removeEventListener('pointermove', move, true);
        root.ownerDocument.removeEventListener('pointerup', up, true);
        root.ownerDocument.removeEventListener('pointercancel', stopTap);
        root.ownerDocument.removeEventListener('visibilitychange', stopTap);
        root.removeEventListener('dblclick', nativeDouble);
        root.removeEventListener('keydown', keyboard);
        root.removeEventListener('life:changed', sync);
        form.removeEventListener('submit', save);
        cancelButton.removeEventListener('click', cancel);
        if (dialog.open) dialog.close();
    };
}
