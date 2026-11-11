import { damageCallout, scoreboardCallout } from './callouts.js';

const maximum = player => Math.max(0, ...Object.values(player.damage || {}));
const alive = player => player.life > 0 && maximum(player) < 21;

// Announce confirmed, consecutive live mutations only. Snapshots establish a quiet baseline.
export function createDamageCommentator({ initial, notify, random = Math.random, now = Date.now, schedule = setTimeout, cancel = clearTimeout, cooldown = 25000, firstBloodSeen = false, alwaysLethal = false }) {
    let state = initial;
    let clockOffset = Number.isFinite(initial.serverTime) ? now() - initial.serverTime : null;
    let timer;
    let started;
    let lastAt = -Infinity;
    let lastCallout;
    const startingLife = initial.format === 'commander' || initial.players.some(player => player.life === 40) ? 40 : 20;
    const pristine = players => players.every(player => player.life === startingLife && maximum(player) === 0);
    let firstBloodAvailable = !firstBloodSeen && pristine(initial.players);
    const pending = new Map();
    function clear() { cancel(timer); timer = undefined; started = undefined; pending.clear(); }
    function flush() {
        const hits = [...pending.values()].map(({ before, after }) => ({
            name: after.name, playerId: after.id, amount: before.life - after.life, life: after.life,
            lethal: !alive(after), commanderLethal: maximum(after) >= 21,
        })).filter(hit => hit.amount > 0);
        clear();
        if (!hits.length) return;
        const firstBlood = firstBloodAvailable;
        firstBloodAvailable = false;
        if (now() - lastAt < cooldown && !(alwaysLethal && hits.some(hit => hit.lethal))) return;
        hits.sort((a, b) => Number(b.lethal) - Number(a.lethal) || b.amount - a.amount);
        const hit = hits[0];
        hit.firstBlood = firstBlood;
        const chance = firstBlood ? 1 : hit.lethal ? .7 : hit.amount >= 10 ? .55 : hit.amount >= 5 ? .38 : .22;
        if (!(alwaysLethal && hit.lethal) && random() >= chance) return;
        const callout = damageCallout(hit, random, lastCallout);
        lastCallout = callout.id;
        lastAt = now();
        notify(callout);
    }
    return {
        update(next, announce = true) {
            if (next.matchId !== state.matchId || next.revision < state.revision) return;
            if (next.revision === state.revision) return;
            const previous = state;
            state = next;
            if (next.gameNumber !== previous.gameNumber || ['reset', 'game-win'].includes(next.operation)) firstBloodAvailable = pristine(next.players);
            else if (!announce || next.operation === 'set-life') firstBloodAvailable &&= pristine(next.players);
            const receivedOffset = Number.isFinite(next.serverTime) ? now() - next.serverTime : null;
            // Initial HTML may have spent seconds in transit. A faster response improves
            // the clock estimate; it must not make every later sound look 'stale'.
            if (receivedOffset !== null) clockOffset = clockOffset === null ? receivedOffset : Math.min(clockOffset, receivedOffset);
            const stale = receivedOffset === null ? clockOffset !== null : receivedOffset - clockOffset > 10000;
            const liveDamage = announce && !stale && next.revision === previous.revision + 1
                && next.gameNumber === previous.gameNumber && next.editable && !next.completed
                && ['life', 'damage'].includes(next.operation);
            if (!liveDamage) { firstBloodAvailable &&= pristine(next.players); clear(); return; }
            const before = new Map(previous.players.map(player => [player.id, player]));
            for (const after of next.players) {
                const old = before.get(after.id);
                if (!old || !Number.isFinite(old.life) || !Number.isFinite(after.life)) continue;
                const entry = pending.get(after.id);
                if (entry) entry.after = after;
                else if (old.life > after.life && alive(old)) pending.set(after.id, { before: old, after });
            }
            if (!pending.size) return;
            started ??= now();
            cancel(timer);
            timer = schedule(flush, Math.max(0, Math.min(180, 600 - (now() - started))));
        },
        clear,
        destroy: clear,
    };
}

export function scoreboardChange(before, next) {
    if (!before || !next || before.event !== next.event) return null;
    let kind;
    if (!before.closed && next.closed) kind = 'finished';
    else if (before.round !== next.round && next.round) kind = 'round';
    else {
        const previous = new Map(before.standings.map(row => [row.id, row]));
        const changedStanding = next.standings.some(row => {
            const old = previous.get(row.id);
            return old && (row.points !== old.points || row.games !== old.games);
        });
        const matches = new Map(before.matches.map(row => [row.id, row.value]));
        const changedMatch = next.matches.some(row => matches.has(row.id) && matches.get(row.id) !== row.value);
        if (changedStanding || changedMatch) kind = 'result';
    }
    const previousRanks = new Map(before.standings.map(row => [String(row.id), row]));
    const sameRoster = before.standings.length === next.standings.length
        && next.standings.every(row => previousRanks.has(String(row.id)));
    const risers = sameRoster ? next.standings.flatMap(row => {
        const old = previousRanks.get(String(row.id));
        return row.place > 0 && row.place < old.place ? [{ id: row.id, name: row.name, from: old.place, to: row.place }] : [];
    }).sort((a, b) => a.to - b.to || (b.from - b.to) - (a.from - a.to)) : [];
    const oldMatches = new Map(before.matches.map(match => [match.id, match.result]));
    const losses = next.matches.flatMap(match => {
        const old = oldMatches.get(match.id), result = match.result;
        if (!old || !result || old.completed || result.players.length < 2) return [];
        const oldScores = new Map(old.players.map(player => [player.id, player.score]));
        const maximum = Math.max(...result.players.map(player => player.score));
        if (result.completed) return result.players.filter(player => player.score < maximum);
        const winner = result.players.find(player => oldScores.has(player.id) && player.score > oldScores.get(player.id));
        return winner ? result.players.filter(player => oldScores.has(player.id) && player.score === oldScores.get(player.id) && player.id !== winner.id) : [];
    });
    if (!kind && (risers.length || losses.length)) kind = 'result';
    if (!kind) return null;
    const leaders = next.standings.filter(row => row.place === 1);
    return { kind, round: next.round, leader: leaders.length === 1 ? leaders[0].name : null,
        ...(risers.length ? { risers } : {}), ...(losses.length ? { losses } : {}) };

}

export function readScoreboard(board) {
    const info = board.querySelector('[data-tv-scoreboard]');
    if (!info) return null;
    return {
        event: info.dataset.event, round: Number(info.dataset.round) || null, closed: info.dataset.closed === '1',
        standings: [...board.querySelectorAll('[data-tv-standing]')].map(row => ({
            id: row.dataset.tvStanding, name: row.querySelector('strong').textContent,
            points: Number(row.dataset.points), games: Number(row.dataset.games), place: Number(row.dataset.place),
        })),
        matches: [...board.querySelectorAll('[data-tv-key^="match-"]')].map(row => {
            let result; try { result = JSON.parse(row.dataset.tvResult); } catch {}
            return { id: row.dataset.tvKey, value: row.dataset.tvValue, result };
        }),
    };
}

export function createScoreboardCommentator({ initial, chime, notify, moved = () => {}, random = Math.random, now = Date.now }) {
    let current = initial;
    let lastSound = -Infinity;
    let lastCallout = -Infinity;
    return {
        update(next, announce = true) {
            const change = scoreboardChange(current, next);
            current = next;
            if (!announce || !change) return;
            const important = Boolean(change.risers?.length || change.losses?.length || change.kind === 'finished');
            if (change.risers?.length) moved(change.risers);
            if (important || now() - lastSound >= 2000) { lastSound = now(); chime(change); }
            if (!important && (now() - lastCallout < 45000 || random() >= .3)) return;
            lastCallout = now();
            notify(scoreboardCallout(change));
        },
    };
}
