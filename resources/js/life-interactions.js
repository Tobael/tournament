export function createHoldRepeat({ activate, changed = () => {}, schedule = setTimeout, cancel = clearTimeout, delay = 350, interval = 180 }) {
    let active = false;
    let generation = 0;
    let timer;
    function stop() {
        generation++;
        cancel(timer);
        if (active) { active = false; changed(false); }
    }
    return {
        get active() { return active; },
        start() {
            stop();
            active = true;
            changed(true);
            const run = generation;
            async function pulse(wait) {
                let success;
                try { success = await activate(); } catch { success = false; }
                if (!active || generation !== run) return;
                if (success === false) { stop(); return; }
                timer = schedule(() => void pulse(interval), wait);
            }
            void pulse(delay);
        },
        stop,
    };
}

export function setupPressHold(root, activateLife) {
    let button;
    let pointer;
    let lastPointerButton;
    const repeater = createHoldRepeat({
        activate: () => {
            if (!button?.isConnected || button.disabled) return false;
            if (button.hasAttribute('data-life-delta')) return activateLife(button);
            button.click();
            return true;
        },
        changed: () => root.dispatchEvent(new CustomEvent('life:idle')),
    });
    function stop() {
        repeater.stop();
        const previous = button;
        button = null;
        try { if (previous?.hasPointerCapture(pointer)) previous.releasePointerCapture(pointer); } catch {}
    }
    function down(event) {
        if (event.isPrimary === false) { stop(); return; }
        const target = event.target.closest('[data-life-delta], [data-damage-step]');
        if (!target || target.disabled || event.button !== 0) return;
        event.preventDefault();
        stop();
        button = target;
        lastPointerButton = target;
        pointer = event.pointerId;
        try { button.setPointerCapture(pointer); } catch {}
        repeater.start();
    }
    function up(event) { if (event.pointerId === pointer) stop(); }
    function move(event) {
        if (!button || event.pointerId !== pointer) return;
        const rect = button.getBoundingClientRect();
        if (event.clientX < rect.left - 12 || event.clientX > rect.right + 12 || event.clientY < rect.top - 12 || event.clientY > rect.bottom + 12) stop();
    }
    function click(event) {
        if (event.detail > 0 && event.target.closest('[data-life-delta], [data-damage-step]') === lastPointerButton) {
            event.preventDefault(); event.stopImmediatePropagation();
        }
    }
    function context(event) { if (event.target.closest('[data-life-delta], [data-damage-step]')) event.preventDefault(); }
    root.addEventListener('pointerdown', down);
    root.addEventListener('click', click, true);
    root.addEventListener('contextmenu', context);
    root.addEventListener('lostpointercapture', stop);
    document.addEventListener('pointerup', up);
    document.addEventListener('pointercancel', up);
    document.addEventListener('pointermove', move);
    document.addEventListener('visibilitychange', stop);
    window.addEventListener('blur', stop);
    return {
        get active() { return repeater.active; },
        destroy() {
            stop();
            root.removeEventListener('pointerdown', down);
            root.removeEventListener('click', click, true);
            root.removeEventListener('contextmenu', context);
            root.removeEventListener('lostpointercapture', stop);
            document.removeEventListener('pointerup', up);
            document.removeEventListener('pointercancel', up);
            document.removeEventListener('pointermove', move);
            document.removeEventListener('visibilitychange', stop);
            window.removeEventListener('blur', stop);
        },
    };
}

export function createLifeTransactions({ players, notify, schedule = setTimeout, cancel = clearTimeout }) {
    const previous = new Map(players.map(player => [player.id, player.life]));
    const changes = new Map();
    function reset(next) {
        for (const [id, entry] of changes) { cancel(entry.timer); notify(id, ''); }
        changes.clear(); previous.clear();
        for (const player of next) previous.set(player.id, player.life);
    }
    return {
        update(next, operation) {
            if (operation === 'reset' || operation === 'game-win') { reset(next); return; }
            for (const player of next) {
                const before = previous.get(player.id);
                previous.set(player.id, player.life);
                if (before === undefined || before === player.life) continue;
                const entry = changes.get(player.id) || { amount: 0 };
                cancel(entry.timer);
                entry.amount += player.life - before;
                notify(player.id, entry.amount > 0 ? `+${entry.amount}` : entry.amount < 0 ? String(entry.amount) : '');
                entry.timer = schedule(() => { changes.delete(player.id); notify(player.id, ''); }, 5000);
                changes.set(player.id, entry);
            }
        },
        render() { for (const [id, entry] of changes) notify(id, entry.amount > 0 ? `+${entry.amount}` : entry.amount < 0 ? String(entry.amount) : ''); },
        destroy() { reset([]); },
    };
}
