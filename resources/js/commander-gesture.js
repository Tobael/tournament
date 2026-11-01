export function createDamageDrag({ schedule = setTimeout, cancel = clearTimeout, hitTest, start, move, end, clear }) {
    let drag;
    function stop() {
        if (!drag) return;
        cancel(drag.timer);
        drag = null;
        clear();
    }
    return {
        down(pointer, source, x, y) {
            if (drag) { stop(); return; }
            drag = { pointer, source, x, y, active: false };
            drag.timer = schedule(() => { if (drag) { drag.active = true; start(source, x, y); } }, 450);
        },
        move(pointer, x, y) {
            if (!drag || drag.pointer !== pointer) return;
            if (!drag.active) { if (Math.hypot(x - drag.x, y - drag.y) > 12) stop(); return; }
            const target = hitTest(x, y);
            move(drag.source, target !== drag.source ? target : null, x, y);
        },
        up(pointer, x, y) {
            if (!drag || drag.pointer !== pointer) return;
            const source = drag.source;
            const target = drag.active ? hitTest(x, y) : null;
            stop();
            if (target && target !== source) end(source, target);
        },
        cancel: stop,
    };
}

export function setupCommanderGesture(root, { getState, send }) {
    if (root.dataset.commander !== '1' || root.dataset.canControl !== '1') return () => {};
    const doc = root.ownerDocument;
    const dialog = root.querySelector('[data-damage-dialog]');
    const form = dialog.querySelector('form');
    const amount = dialog.querySelector('[data-damage-amount]');
    const submit = dialog.querySelector('[data-damage-apply]');
    const ghost = root.querySelector('[data-damage-ghost]');
    const line = root.querySelector('[data-damage-line]');
    const overlay = root.querySelector('[data-damage-overlay]');
    let sourceId;
    let recipientId;
    let change = 0;
    let keyboardSource = null;
    let keyboardTarget = 0;
    let capture;
    function clear() {
        overlay.toggleAttribute('hidden', true);
        ghost.hidden = true;
        for (const seat of root.querySelectorAll('.damage-source, .damage-target')) seat.classList.remove('damage-source', 'damage-target');
        if (capture) {
            const previous = capture;
            capture = null;
            try { previous.element.releasePointerCapture(previous.pointer); } catch {}
        }
    }
    function pick(source, target) {
        const state = getState();
        if (!state.editable || dialog.open) return;
        sourceId = source;
        dialog.querySelector('[data-damage-dialog-error]').textContent = '';
        const player = state.players.find(player => player.id === source);
        if (!player) return;
        dialog.querySelector('[data-damage-dealer]').textContent = player.name;
        const receiver = state.players.find(player => player.id === target && player.id !== source);
        if (!receiver) return;
        recipientId = receiver.id;
        dialog.querySelector('[data-damage-recipient-name]').textContent = receiver.name;
        change = 0;
        current();
        dialog.showModal();
    }
    function current() {
        const player = getState().players.find(player => player.id === recipientId);
        const existing = Number(player?.damage?.[sourceId] || 0);
        change = Math.max(-Math.min(99, existing), Math.min(99, 999 - existing, change));
        amount.textContent = change > 0 ? `+${change}` : change;
        submit.disabled = Boolean(submit.dataset.pending) || change === 0 || !getState().editable;
        dialog.querySelector('[data-damage-step="-1"]').disabled = change <= -Math.min(99, existing);
        dialog.querySelector('[data-damage-step="1"]').disabled = change >= Math.min(99, 999 - existing);
    }
    function step(event) {
        const button = event.target.closest('[data-damage-step]');
        if (!button || button.disabled || submit.dataset.pending) return;
        change += Number(button.dataset.damageStep);
        current();
    }
    const drag = createDamageDrag({
        hitTest: (x, y) => Number(doc.elementFromPoint(x, y)?.closest('[data-life-seat]')?.dataset.lifeSeat) || null,
        start: (source, x, y) => {
            if (!getState().editable) { drag.cancel(); return; }
            const seat = root.querySelector(`[data-life-seat="${source}"]`);
            seat.classList.add('damage-source');
            const name = getState().players.find(player => player.id === source)?.name || '';
            ghost.textContent = `${name}: Empfänger wählen`;
            ghost.hidden = false;
            overlay.toggleAttribute('hidden', false);
            line.setAttribute('x1', x); line.setAttribute('y1', y);
            line.setAttribute('x2', x); line.setAttribute('y2', y);
            ghost.style.left = `${x}px`; ghost.style.top = `${y}px`;
        },
        move: (source, target, x, y) => {
            for (const seat of root.querySelectorAll('[data-life-seat]')) seat.classList.toggle('damage-target', Number(seat.dataset.lifeSeat) === target);
            line.setAttribute('x2', x); line.setAttribute('y2', y);
            ghost.style.left = `${Math.max(90, Math.min(innerWidth - 90, x))}px`;
            ghost.style.top = `${Math.max(30, Math.min(innerHeight - 50, y))}px`;
        },
        end: pick, clear,
    });
    function down(event) {
        if (event.isPrimary === false) { drag.cancel(); return; }
        if (event.target.closest('button, a, input, select, textarea, summary, dialog, [data-quick-actions]')) return;
        const handle = event.target.closest('[data-damage-tile]');
        if (!handle || event.button !== 0 || !getState().editable || dialog.open) return;
        event.preventDefault();
        keyboardSource = null;
        const source = Number(handle.dataset.lifeSeat);
        try { handle.setPointerCapture(event.pointerId); capture = { element: handle, pointer: event.pointerId }; } catch {}
        drag.down(event.pointerId, source, event.clientX, event.clientY);
    }
    const move = event => drag.move(event.pointerId, event.clientX, event.clientY);
    const up = event => drag.up(event.pointerId, event.clientX, event.clientY);
    const cancel = () => { keyboardSource = null; drag.cancel(); clear(); };
    const context = event => {
        if (event.target.closest?.('[data-life-seat]') || event.target.parentElement?.closest('[data-life-seat]')) event.preventDefault();
    };
    const keyboard = event => {
        if (event.key === 'Escape') { keyboardSource = null; drag.cancel(); clear(); return; }
        if (dialog.open) return;
        const handle = event.target.closest('[data-damage-handle]');
        if (!handle || !getState().editable) return;
        if (['Enter', ' ', 'ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp'].includes(event.key)) {
            event.preventDefault();
            const players = getState().players;
            if (keyboardSource === null) {
                if (!['Enter', ' '].includes(event.key)) return;
                keyboardSource = Number(handle.closest('[data-life-seat]').dataset.lifeSeat);
                keyboardTarget = 0;
            } else if (['Enter', ' '].includes(event.key)) {
                const target = players.filter(player => player.id !== keyboardSource)[keyboardTarget];
                const source = keyboardSource;
                keyboardSource = null;
                clear();
                pick(source, target.id);
                return;
            } else {
                const count = players.length - 1;
                const delta = ['ArrowRight', 'ArrowDown'].includes(event.key) ? 1 : -1;
                keyboardTarget = (keyboardTarget + delta + count) % count;
            }
            const target = players.filter(player => player.id !== keyboardSource)[keyboardTarget];
            for (const seat of root.querySelectorAll('[data-life-seat]')) {
                seat.classList.toggle('damage-source', Number(seat.dataset.lifeSeat) === keyboardSource);
                seat.classList.toggle('damage-target', Number(seat.dataset.lifeSeat) === target.id);
            }
        }
    };
    async function apply(event) {
        event.preventDefault();
        if (submit.disabled || change === 0) return;
        if (!getState().editable) { dialog.close(); return; }
        const delta = change;
        submit.dataset.pending = '1';
        submit.disabled = true;
        const success = await send({ action: 'damage', source: sourceId, player: recipientId, delta });
        if (success) dialog.close();
        else {
            change = 0;
            dialog.querySelector('[data-damage-dialog-error]').textContent = 'Nicht bestätigt. Zählerstand prüfen.';
        }
        delete submit.dataset.pending;
        current();
    }
    const close = () => dialog.close();
    root.addEventListener('pointerdown', down);
    root.addEventListener('contextmenu', context);
    root.addEventListener('selectstart', context);
    root.addEventListener('dragstart', context);
    root.addEventListener('keydown', keyboard);
    doc.addEventListener('pointermove', move);
    doc.addEventListener('pointerup', up);
    doc.addEventListener('pointercancel', cancel);
    root.addEventListener('lostpointercapture', cancel);
    root.addEventListener('life:changed', current);
    dialog.addEventListener('click', step);
    doc.addEventListener('visibilitychange', cancel);
    window.addEventListener('resize', cancel);
    form.addEventListener('submit', apply);
    dialog.querySelector('[data-damage-cancel]').addEventListener('click', close);
    return () => {
        drag.cancel();
        if (dialog.open) dialog.close();
        root.removeEventListener('pointerdown', down);
        root.removeEventListener('contextmenu', context);
        root.removeEventListener('selectstart', context);
        root.removeEventListener('dragstart', context);
        root.removeEventListener('keydown', keyboard);
        doc.removeEventListener('pointermove', move);
        doc.removeEventListener('pointerup', up);
        doc.removeEventListener('pointercancel', cancel);
        root.removeEventListener('lostpointercapture', cancel);
        root.removeEventListener('life:changed', current);
        dialog.removeEventListener('click', step);
        doc.removeEventListener('visibilitychange', cancel);
        window.removeEventListener('resize', cancel);
        form.removeEventListener('submit', apply);
        dialog.querySelector('[data-damage-cancel]').removeEventListener('click', close);
    };
}
