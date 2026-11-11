import test from 'node:test';
import assert from 'node:assert/strict';
import { createLifeSync, projectLife } from '../../resources/js/life-sync.js';

const state = (revision = 0, life = 40, extra = {}) => ({ matchId: 7, revision, gameNumber: 1, editable: true, completed: false, players: [{ id: 1, name: 'A', life, damage: {} }], ...extra });
const settle = () => new Promise(resolve => setImmediate(resolve));
function harness() {
    const requests = [], views = [], confirmed = []; let failures = 0; let counter = 0;
    let snapshot = state();
    const sync = createLifeSync({ initial: state(), id: () => `op-${++counter}`, changed: next => views.push(next), confirmed: (...args) => confirmed.push(args),
        request: command => new Promise((resolve, reject) => requests.push({ command, resolve, reject })),
        resync: async () => snapshot, failed: () => failures++,
    });
    return { sync, requests, views, confirmed, get failures() { return failures; }, snapshot(next) { snapshot = next; } };
}

test('taps paint immediately and queued same-player taps are combined behind one slow request', async () => {
    const h = harness(); const saves = [];
    for (let n = 0; n < 6; n++) saves.push(h.sync.send({ action: 'life', player: 1, delta: -1 }));
    assert.equal(h.sync.state.players[0].life, 34); assert.equal(h.requests.length, 1);
    h.requests[0].resolve(state(1, 39)); await settle();
    assert.equal(h.sync.state.players[0].life, 34); assert.equal(h.requests.length, 2);
    assert.equal(h.requests[1].command.delta, -5);
    h.requests[1].resolve(state(2, 34)); await Promise.all(saves);
    assert.equal(h.sync.state.players[0].life, 34); assert.equal(h.sync.busy, false);
});

test('a matching live echo acknowledges a pending delta once; stale HTTP cannot undo newer remote changes', async () => {
    const h = harness(); const saved = h.sync.send({ action: 'life', player: 1, delta: -1 });
    h.sync.receive(state(1, 39, { operationId: 'op-1' }), true);
    assert.equal(h.sync.state.players[0].life, 39);
    h.sync.receive(state(2, 36, { operationId: 'remote' }), true);
    h.requests[0].resolve(state(1, 39)); await saved; await settle();
    assert.equal(h.sync.state.players[0].life, 36); assert.equal(h.confirmed.length, 2);
});

test('unidentified snapshots/remote updates wait for the in-flight ack instead of double counting its effect', async () => {
    const h = harness(); const save = h.sync.send({ action: 'life', player: 1, delta: -1 });
    h.sync.receive(state(2, 34), true);
    assert.equal(h.sync.state.players[0].life, 39);
    h.requests[0].resolve(state(1, 39)); await save; await settle();
    assert.equal(h.sync.state.players[0].life, 34);
});

test('failed or ambiguous writes discard unsent taps and reconcile without retrying a mutation', async () => {
    const h = harness(); const first = h.sync.send({ action: 'life', player: 1, delta: -1 });
    const second = h.sync.send({ action: 'life', player: 1, delta: -1 });
    h.snapshot(state(1, 39)); h.requests[0].reject(new Error('Response lost'));
    assert.equal(await first, false); assert.equal(await second, false); await settle();
    assert.equal(h.sync.state.players[0].life, 39); assert.equal(h.requests.length, 1); assert.equal(h.failures, 1);
});

test('a lost HTTP response after the matching live ack remains a confirmed success', async () => {
    const h = harness(); const save = h.sync.send({ action: 'life', player: 1, delta: -1 });
    h.sync.receive(state(1, 39, { operationId: 'op-1' }), true);
    h.requests[0].reject(new Error('HTTP lost')); assert.equal(await save, true); await settle();
    assert.equal(h.failures, 0); assert.equal(h.sync.state.players[0].life, 39);
});

test('commands from an old game are discarded, and revision-sensitive edits cannot jump pending taps', async () => {
    const h = harness(); const first = h.sync.send({ action: 'life', player: 1, delta: -1 });
    const second = h.sync.send({ action: 'life', player: 1, delta: -1 });
    assert.equal(await h.sync.send({ action: 'reset', revision: 0 }), false);
    h.sync.receive(state(2, 20, { gameNumber: 2, operation: 'game-win' }), true);
    h.requests[0].resolve(state(1, 39)); await first; assert.equal(await second, false); await settle();
    assert.equal(h.sync.state.players[0].life, 20); assert.equal(h.requests.length, 1);
});

test('commander damage is projected atomically with life, and opposite bounded taps preserve clamp order', async () => {
    const projected = projectLife(state(0, 40), [{ action: 'damage', player: 1, source: 2, delta: 7 }]);
    assert.equal(projected.players[0].life, 33); assert.equal(projected.players[0].damage[2], 7);
    assert.equal(state().players[0].damage[2], undefined);
    const h = harness(); const first = h.sync.send({ action: 'life', player: 1, delta: 1 });
    const second = h.sync.send({ action: 'life', player: 1, delta: 1 });
    const third = h.sync.send({ action: 'life', player: 1, delta: -1 });
    h.requests[0].resolve(state(1, 9999)); await first; await settle();
    assert.equal(h.requests[1].command.delta, 1); assert.equal(h.sync.state.players[0].life, 9998);
    h.requests[1].resolve(state(2, 9999)); await second; await settle();
    h.requests[2].resolve(state(3, 9998)); await third;
});
