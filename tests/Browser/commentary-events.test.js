import test from 'node:test';
import assert from 'node:assert/strict';
import { createDamageCommentator, createScoreboardCommentator, scoreboardChange } from '../../resources/js/commentary/events.js';
import { damageCallout, scoreboardCallout } from '../../resources/js/commentary/callouts.js';

function state(revision = 0, life = 40, more = {}) {
    return { matchId: 7, revision, gameNumber: 1, editable: true, completed: false, operation: 'life',
        players: [{ id: 1, name: 'Tobi', life, damage: {} }], ...more };
}
function harness({ random = () => 0, ...options } = {}) {
    let clock = 0;
    let nextId = 0;
    const jobs = new Map();
    const lines = [];
    const commentator = createDamageCommentator({ initial: state(), firstBloodSeen: true, notify: text => lines.push(text), random,
        now: () => clock, schedule: (fn, delay) => { jobs.set(++nextId, { fn, at: clock + delay }); return nextId; }, cancel: id => jobs.delete(id), ...options });
    return { ...commentator, lines, jobs, advance(ms) {
        clock += ms;
        for (const [id, job] of jobs) if (job.at <= clock) { jobs.delete(id); job.fn(); }
    } };
}

test('rapid taps become one damage-based line and duplicate/stale events do not add damage', () => {
    const h = harness();
    h.update(state(1, 39)); h.advance(100);
    h.update(state(2, 35)); h.advance(100);
    h.update(state(2, 35)); h.update(state(1, 39));
    h.advance(900);
    assert.equal(h.lines.length, 1);
    assert.match(h.lines[0].detail, /Tobi/);
    assert.match(h.lines[0].detail, /−5 Leben/);
    h.update(state(2, 35)); h.advance(1000);
    assert.equal(h.lines.length, 1);
});

test('cooldown is global and random draws can suppress even lethal damage', () => {
    const h = harness();
    h.update(state(1, 39)); h.advance(900);
    h.update(state(2, 30)); h.advance(900);
    assert.equal(h.lines.length, 1);
    h.advance(25000); h.update(state(3, 20)); h.advance(900);
    assert.equal(h.lines.length, 2);
    const quiet = harness({ random: () => .99 });
    quiet.update(state(1, 0)); quiet.advance(900);
    assert.equal(quiet.lines.length, 0);
});

test('probability scales with the size of the hit', () => {
    for (const [life, chance, expected] of [[39, .25, false], [35, .25, true], [30, .5, true], [0, .65, true]]) {
        let first = true;
        const h = harness({ random: () => { const value = first ? chance : 0; first = false; return value; } });
        h.update(state(1, life)); h.advance(900);
        assert.equal(h.lines.length > 0, expected);
    }
});

test('healing and undo reduce the pending hit rather than invent damage', () => {
    const h = harness();
    h.update(state(1, 30)); h.update(state(2, 40)); h.advance(900);
    assert.equal(h.lines.length, 0);
    h.update(state(3, 30)); h.update(state(4, 35)); h.advance(900);
    assert.match(h.lines[0].detail, /−5 Leben/);
    const healed = harness(); healed.update(state(1, 50)); healed.advance(900);
    assert.equal(healed.lines.length, 0);
});

test('snapshots, revision gaps, reset, manual correction and a new game stay quiet', () => {
    for (const [next, announce] of [
        [state(1, 10), false], [state(4, 10), true], [state(1, 0, { operation: 'set-life' }), true],
        [state(1, 20, { operation: 'reset' }), true], [state(1, 20, { operation: 'game-win', gameNumber: 2 }), true],
        [state(1, 0, { completed: true, editable: false, operation: 'finish' }), true],
    ]) {
        const h = harness(); h.update(next, announce); h.advance(1000); assert.equal(h.lines.length, 0);
    }
    const h = harness(); h.update(state(1, 35)); h.update(state(1, 35), false); h.advance(1000);
    assert.equal(h.lines.length, 1); // An unchanged snapshot must not cancel a confirmed hit.
});

test('a commander lethal line uses commander damage even with life remaining', () => {
    const h = harness({ initial: state(0, 30, { players: [{ id: 1, name: 'Tobi', life: 30, damage: { 2: 20 } }] }) });
    h.update(state(1, 29, { operation: 'damage', players: [{ id: 1, name: 'Tobi', life: 29, damage: { 2: 21 } }] }));
    h.advance(900);
    assert.match(h.lines[0].detail, /21 Commander-Schaden/);
    assert.ok(['fatality', 'wasted'].includes(h.lines[0].id));
});

test('one line for simultaneous losses picks the largest hit and clears timers on teardown', () => {
    const h = harness({ initial: state(0, 40, { players: [
        { id: 1, name: 'Small', life: 40 }, { id: 2, name: 'Big', life: 40 },
    ] }) });
    h.update(state(1, 40, { players: [{ id: 1, name: 'Small', life: 39 }, { id: 2, name: 'Big', life: 25 }] }));
    h.advance(900); assert.equal(h.lines.length, 1); assert.match(h.lines[0].detail, /Big.*15/);
    h.update(state(2, 30)); h.destroy(); h.advance(1000); assert.equal(h.jobs.size, 0);
});

test('long held presses flush after at most 2.5 seconds and do not repeat a line immediately', () => {
    const h = harness();
    for (let rev = 1; rev <= 10; rev++) { h.update(state(rev, 40 - rev)); h.advance(250); }
    assert.equal(h.lines.length, 1);
    const first = damageCallout({ name: 'Tobi', amount: 1, life: 39 }, () => 0);
    const second = damageCallout({ name: 'Tobi', amount: 1, life: 38 }, () => 0, first.id);
    assert.notEqual(first.id, second.id);
});

function board(more = {}) {
    return { event: 'one', round: 1, closed: false, standings: [
        { id: 1, place: 1, name: 'Tobi', points: 0, games: 0 }, { id: 2, place: 1, name: 'Ada', points: 0, games: 0 },
    ], matches: [{ id: 'match-1', value: 'null:null:null' }], ...more };
}

test('TV reacts to real scores, game results, rounds and completion; ignores same state and new events', () => {
    const initial = board();
    assert.equal(scoreboardChange(initial, board()), null);
    assert.equal(scoreboardChange(initial, board({ event: 'two', round: 2 })), null);
    assert.equal(scoreboardChange(initial, board({ standings: initial.standings.slice().reverse() })), null);
    assert.equal(scoreboardChange(initial, board({ matches: [{ id: 'match-2', value: 'null' }] })), null);
    assert.equal(scoreboardChange(initial, board({ standings: [...initial.standings, { id: 3, name: 'New', place: 1, points: 0, games: 0 }] })), null);
    assert.equal(scoreboardChange(initial, board({ round: 2 })).kind, 'round');
    assert.equal(scoreboardChange(initial, board({ closed: true })).kind, 'finished');
    assert.equal(scoreboardChange(initial, board({ matches: [{ id: 'match-1', value: 'null:null:{"1":1}' }] })).kind, 'result');
    const scored = board({ standings: [{ id: 1, name: 'Tobi', place: 1, points: 3, games: 1 }] });
    assert.deepEqual(scoreboardChange(initial, scored), { kind: 'result', round: 1, leader: 'Tobi' });
});

test('TV sounds once for score changes; reconnects and initial baselines stay quiet', () => {
    let sounds = 0; const lines = []; let time = 0;
    const h = createScoreboardCommentator({ initial: board(), chime: () => sounds++, notify: text => lines.push(text), random: () => 0, now: () => time });
    const next = board({ round: 2 });
    h.update(next); h.update(next);
    assert.equal(sounds, 1); assert.equal(lines.length, 1);
    h.update(board({ round: 3 }), false);
    time = 10000; h.update(board({ round: 4 }));
    assert.equal(sounds, 2); assert.equal(lines.length, 1);
    time = 50000; h.update(board({ round: 5 }));
    assert.equal(sounds, 3); assert.equal(lines.length, 2);
    assert.doesNotMatch(scoreboardCallout({ kind: 'result' }).detail, /führt/);
});

test('delayed live messages establish a silent baseline instead of announcing old damage', () => {
    const h = harness({ initial: state(0, 40, { serverTime: 1000 }) });
    h.advance(20000);
    h.update(state(1, 10, { serverTime: 2000 })); h.advance(1000);
    assert.equal(h.lines.length, 0);
});

test('First Blood appears once from a clean game, resets for a new game and is not replayed on reconnect', () => {
    const h = harness({ firstBloodSeen: false });
    h.update(state(1, 39)); h.update(state(1, 39)); h.advance(900);
    assert.equal(h.lines[0].id, 'first-blood');
    h.advance(26000); h.update(state(2, 38)); h.advance(900);
    assert.notEqual(h.lines[1].id, 'first-blood');
    h.update(state(3, 40, { operation: 'reset' })); h.advance(26000);
    h.update(state(4, 39)); h.advance(900);
    assert.equal(h.lines[2].id, 'first-blood');
    const resumed = harness({ firstBloodSeen: false });
    resumed.update(state(3, 30), false); resumed.update(state(4, 29)); resumed.advance(900);
    assert.notEqual(resumed.lines[0]?.id, 'first-blood');
});

test('undoing the first damage burst does not consume First Blood', () => {
    const h = harness({ firstBloodSeen: false });
    h.update(state(1, 39)); h.update(state(2, 40)); h.advance(900);
    assert.equal(h.lines.length, 0);
    h.update(state(3, 39)); h.advance(900);
    assert.equal(h.lines[0].id, 'first-blood');
});


test('a skipped damage revision cannot claim First Blood on the next live hit', () => {
    const h = harness({ firstBloodSeen: false });
    h.update(state(3, 30)); h.advance(1000);
    h.update(state(4, 29)); h.advance(900);
    assert.notEqual(h.lines[0]?.id, 'first-blood');
});

test('slow initial page delivery does not suppress sounds from later faster confirmations', () => {
    const h = harness({ initial: state(0, 40, { serverTime: -20000 }) });
    h.update(state(1, 39, { serverTime: 0 })); h.advance(180);
    assert.equal(h.lines.length, 1);
});

test('standings gains compare real places, ignore same-place ties and ignore roster changes', () => {
    const before = board({ standings: [{ id: 1, name: 'Tobi', place: 3, points: 0, games: 0 }, { id: 2, name: 'Ada', place: 1, points: 3, games: 1 }] });
    const next = board({ standings: [{ id: 1, name: 'Tobi', place: 1, points: 6, games: 2 }, { id: 2, name: 'Ada', place: 2, points: 3, games: 1 }] });
    assert.deepEqual(scoreboardChange(before, next).risers, [{ id: 1, name: 'Tobi', from: 3, to: 1 }]);
    const tie = board({ standings: before.standings.map(player => ({ ...player, points: player.points + 1 })) });
    assert.equal(scoreboardChange(before, tie).risers, undefined);
    const joined = { ...next, standings: [...next.standings, { id: 3, name: 'New', place: 3, points: 0, games: 0 }] };
    assert.equal(scoreboardChange(before, joined).risers, undefined);
});

test('completed matches and best-of-three games identify losers, while draws and byes do not', () => {
    const players = [{ id: 1, name: 'Tobi', score: 0 }, { id: 2, name: 'Ada', score: 0 }];
    const before = board({ matches: [{ id: 'match-1', value: 'old', result: { completed: false, players } }] });
    const make = (scores, completed = true) => board({ matches: [{ id: 'match-1', value: scores.join('-'), result: { completed, players: players.map((player, i) => ({ ...player, score: scores[i] })) } }] });
    assert.deepEqual(scoreboardChange(before, make([3, 0])).losses.map(player => player.name), ['Ada']);
    assert.deepEqual(scoreboardChange(before, make([1, 0], false)).losses.map(player => player.name), ['Ada']);
    assert.equal(scoreboardChange(before, make([1, 1])).losses, undefined);
    const bye = board({ matches: [{ id: 'match-1', value: 'done', result: { completed: true, players: [{ ...players[0], score: 2 }] } }] });
    assert.equal(scoreboardChange(before, bye).losses, undefined);
});

test('rank-up highlights/callouts are deterministic and duplicate or silent resyncs stay quiet', () => {
    const initial = board({ standings: [{ id: 1, name: 'Tobi', place: 2, points: 0, games: 0 }, { id: 2, name: 'Ada', place: 1, points: 3, games: 1 }] });
    const next = board({ standings: [{ id: 1, name: 'Tobi', place: 1, points: 6, games: 2 }, { id: 2, name: 'Ada', place: 2, points: 3, games: 1 }] });
    const moves = [], cues = [], popups = [];
    const engine = createScoreboardCommentator({ initial, chime: change => cues.push(change), notify: callout => popups.push(callout), moved: rows => moves.push(rows), random: () => .99 });
    engine.update(next); engine.update(next);
    assert.equal(moves.length, 1); assert.equal(cues.length, 1); assert.equal(popups[0].id, 'rank-up');
    const quiet = createScoreboardCommentator({ initial, chime: () => assert.fail('resync sound'), notify: () => assert.fail('resync popup'), moved: () => assert.fail('resync highlight') });
    quiet.update(next, false); quiet.update(next);
});

test('TV lethal damage bypasses ordinary random suppression and damage cooldown', () => {
    const h = harness({ alwaysLethal: true, random: () => .99 });
    h.update(state(1, 0)); h.advance(180);
    assert.equal(h.lines.length, 1); assert.equal(h.lines[0].tone, 'knockout');
});
