import { showDie } from './dice-scene.js';

export function randomIndex(count, draw = () => crypto.getRandomValues(new Uint32Array(1))[0]) {
    if (!Number.isInteger(count) || count < 1 || count > 0x100000000) throw new RangeError('Invalid choice count');
    const limit = Math.floor(0x100000000 / count) * count;
    let value;
    do { value = draw(); } while (value >= limit);
    return value % count;
}

export function rollDie(sides, draw) {
    if (![2, 4, 6, 8, 10, 12, 20].includes(sides)) throw new RangeError('Unsupported die');
    const value = randomIndex(sides, draw) + 1;
    return { value, label: sides === 2 ? `Münze: ${value === 1 ? 'Kopf' : 'Zahl'}` : `d${sides}: ${value}` };
}

export function selectionFrames(count, target) {
    if (!Number.isInteger(count) || count < 1 || !Number.isInteger(target) || target < 0 || target >= count) throw new RangeError('Invalid selection');
    return Array.from({ length: Math.ceil(8 / count) * count + target + 1 }, (_, index) => index % count);
}

export function animateFrames(frames, tick, finish, { schedule = setTimeout, cancel = clearTimeout } = {}) {
    let timer;
    let active = true;
    let index = 0;
    function next() {
        if (!active) return;
        if (index === frames.length) { active = false; finish(); return; }
        tick(frames[index]);
        timer = schedule(next, 45 + index++ * 7);
    }
    next();
    return () => { active = false; cancel(timer); };
}

export function setupQuickActions(root, getPlayers) {
    const container = root.querySelector('[data-quick-actions]');
    const toggle = container.querySelector('[data-quick-toggle]');
    const menu = container.querySelector('[data-quick-menu]');
    const result = container.querySelector('[data-quick-result]');
    let timer;
    let stopAnimation = () => {};
    let busy = false;
    function close() { menu.hidden = true; toggle.setAttribute('aria-expanded', 'false'); }
    function highlight(id) {
        for (const seat of root.querySelectorAll('[data-life-seat]')) seat.classList.toggle('life-random-selected', Number(seat.dataset.lifeSeat) === id);
    }
    function clear() {
        result.hidden = true;
        result.classList.remove('is-rolling', 'is-coin');
        root.classList.remove('is-selecting-player');
        highlight(null);
    }
    function finish(label, id) {
        busy = false;
        toggle.disabled = false;
        toggle.setAttribute('aria-busy', 'false');
        result.classList.remove('is-rolling', 'is-coin');
        root.classList.remove('is-selecting-player');
        result.setAttribute('aria-live', 'polite');
        result.querySelector('[data-quick-result-text]').textContent = label;
        toggle.focus({ preventScroll: true });
        if (id) highlight(id);
        timer = setTimeout(clear, 5000);
    }
    function act(event) {
        if (event.target.closest('[data-life-reset], [data-life-finish-reopen]')) { close(); return; }
        if (busy) return;
        const die = event.target.closest('[data-quick-die]');
        const random = event.target.closest('[data-quick-player]');
        if (!die && !random) return;
        clearTimeout(timer);
        stopAnimation();
        clear();
        const text = root.ownerDocument.createElement('span');
        text.dataset.quickResultText = '';
        result.replaceChildren(text);
        if (random) {
            const icon = random.querySelector('svg').cloneNode(true);
            icon.classList.add('quick-result-icon');
            result.prepend(icon);
        }
        result.setAttribute('aria-live', 'off');
        result.hidden = false;
        busy = true;
        toggle.disabled = true;
        toggle.setAttribute('aria-busy', 'true');
        close();
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (die) {
            const sides = Number(die.dataset.quickDie);
            const roll = rollDie(sides);
            text.textContent = sides === 2 ? 'Münzwurf' : `d${sides}`;
            stopAnimation = showDie(result, sides, roll.value, { reduced, finish: () => finish(roll.label) });
        } else {
            const players = getPlayers();
            if (!players.length) { busy = false; toggle.disabled = false; toggle.setAttribute('aria-busy', 'false'); clear(); return; }
            const target = randomIndex(players.length);
            const player = players[target];
            if (reduced) { finish(`Spieler: ${player.name}`, player.id); return; }
            root.classList.add('is-selecting-player');
            stopAnimation = animateFrames(selectionFrames(players.length, target), index => {
                text.textContent = players[index].name;
                highlight(players[index].id);
            }, () => finish(`Spieler: ${player.name}`, player.id));
        }
    }
    function toggleMenu() {
        if (busy) return;
        if (menu.hidden) { clearTimeout(timer); stopAnimation(); clear(); }
        menu.hidden = !menu.hidden;
        toggle.setAttribute('aria-expanded', String(!menu.hidden));
    }
    const outside = event => { if (!container.contains(event.target)) close(); };
    const escape = event => { if (event.key === 'Escape' && !menu.hidden) { close(); toggle.focus(); } };
    toggle.addEventListener('click', toggleMenu);
    menu.addEventListener('click', act);
    document.addEventListener('pointerdown', outside);
    document.addEventListener('keydown', escape);
    return () => {
        stopAnimation(); clearTimeout(timer); clear();
        toggle.removeEventListener('click', toggleMenu);
        menu.removeEventListener('click', act);
        document.removeEventListener('pointerdown', outside);
        document.removeEventListener('keydown', escape);
    };
}
