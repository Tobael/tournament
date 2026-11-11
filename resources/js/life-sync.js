import { acceptsMatchState } from './match-state.js';

export function projectLife(state, commands) {
    const players = state.players.map(player => ({ ...player, damage: { ...player.damage } }));
    for (const command of commands) {
        const player = players.find(player => player.id === command.player);
        if (!player) continue;
        if (command.action === 'life') player.life = Math.max(-999, Math.min(9999, player.life + command.delta));
        if (command.action === 'damage') {
            const before = player.damage[command.source] || 0;
            const after = Math.max(0, Math.min(999, before + command.delta));
            player.damage[command.source] = after;
            player.life -= after - before;
        }
    }
    return { ...state, players, operation: commands.at(-1)?.action || state.operation };
}

function operationId() {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
    const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
    const hex = [...bytes].map(value => value.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

// One mutation in flight. Remote states wait until it is acknowledged, so the same
// delta is never displayed once from a WebSocket and again as a pending local tap.
export function createLifeSync({ initial, request, resync, changed, confirmed = () => {}, failed = () => {}, id = operationId }) {
    let base = initial;
    let active;
    let deferred;
    let stopped = false;
    const queue = [];
    function view() {
        const commands = [...(active && !active.acked ? [active.command] : []), ...queue.map(entry => entry.command)];
        return { ...projectLife(base, commands), pending: Boolean(active || queue.length) };
    }
    function paint() { if (!stopped) changed(view()); }
    function accept(next, live) {
        if (!acceptsMatchState(base, next)) return;
        const newer = next.revision > base.revision;
        if (next.gameNumber !== base.gameNumber || !next.editable || next.completed
            || (newer && ['reset', 'game-win'].includes(next.operation))) {
            for (const entry of queue.splice(0)) for (const resolve of entry.waiters) resolve(false);
        }
        base = next;
        if (newer) confirmed(next, live);
    }
    function applyDeferred() {
        if (!deferred) return;
        const { state, live } = deferred; deferred = null; accept(state, live);
    }
    function receive(next, live = false) {
        if (stopped || !acceptsMatchState(base, next)) return;
        if (active && !active.acked && next.operationId !== active.id) {
            if (!deferred || next.revision > deferred.state.revision) deferred = { state: next, live };
            return;
        }
        if (active && next.operationId === active.id) active.acked = true;
        accept(next, live);
        if (active?.acked) applyDeferred();
        paint();
    }
    async function pump() {
        if (active || stopped || !queue.length) return;
        active = queue.shift();
        const entry = active;
        paint();
        try {
            if (!base.editable || base.completed) throw new Error('Inactive match');
            const next = await request({ ...entry.command, operation_id: entry.id });
            if (stopped) return;
            entry.acked = true;
            accept(next, true); applyDeferred();
            for (const resolve of entry.waiters) resolve(true);
        } catch {
            if (stopped) return;
            if (entry.acked) {
                // A matching live echo already confirmed the commit, even if HTTP failed.
                for (const resolve of entry.waiters) resolve(true);
                applyDeferred();
            } else {
                for (const pending of [entry, ...queue.splice(0)]) for (const resolve of pending.waiters) resolve(false);
                deferred = null;
                // Never retry an ambiguous mutation: it may already have been committed.
                try { const next = await resync(); if (!stopped) accept(next, false); } catch {}
                if (!stopped) failed();
            }
        } finally {
            if (active === entry) active = null;
            paint();
            if (!stopped) void pump();
        }
    }
    return {
        get state() { return view(); },
        get busy() { return Boolean(active || queue.length); },
        receive,
        send(command) {
            if (stopped || !base.editable || base.completed) return Promise.resolve(false);
            if (active || queue.length) {
                // Absolute edits/reset/results depend on a confirmed revision.
                if (!['life', 'damage'].includes(command.action)) return Promise.resolve(false);
            }
            return new Promise(resolve => {
                const tail = queue.at(-1);
                const merge = command.action === 'life' && tail?.command.action === 'life'
                    && tail.command.player === command.player && Math.sign(tail.command.delta) === Math.sign(command.delta) && Math.abs(tail.command.delta + command.delta) <= 99;
                if (merge) {
                    tail.command.delta += command.delta;
                    tail.waiters.push(resolve);
                    if (!tail.command.delta) { queue.pop(); for (const done of tail.waiters) done(true); }
                } else if (queue.length >= 32) { resolve(false); return; }
                else queue.push({ id: id(), command: { ...command }, waiters: [resolve], acked: false });
                paint(); void pump();
            });
        },
        destroy() {
            stopped = true;
            for (const entry of [active, ...queue].filter(Boolean)) for (const resolve of entry.waiters) resolve(false);
            queue.length = 0;
        },
    };
}
