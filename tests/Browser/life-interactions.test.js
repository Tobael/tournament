import test from 'node:test';
import assert from 'node:assert/strict';
import { createHoldRepeat, createLifeTransactions } from '../../resources/js/life-interactions.js';

const settle = async () => { await new Promise(resolve => setImmediate(resolve)); };
function timers() {
    let next = 0;
    const pending = new Map();
    return { pending, schedule: (run, delay) => { const id = ++next; pending.set(id, { run, delay }); return id; }, cancel: id => pending.delete(id), fire() { const [id, entry] = pending.entries().next().value; pending.delete(id); entry.run(); return entry.delay; } };
}

test('holding repeats after a delay and stops immediately on release', async () => {
    const clock = timers(); let count = 0;
    const hold = createHoldRepeat({ activate: async () => { count++; return true; }, ...clock });
    hold.start(); await settle();
    assert.equal(count, 1);
    assert.equal(clock.fire(), 350); await settle();
    assert.equal(count, 2);
    assert.equal(clock.fire(), 180); await settle();
    assert.equal(count, 3);
    hold.stop();
    assert.equal(hold.active, false);
    assert.equal(clock.pending.size, 0);
});

test('a slow or failed update cannot queue additional hold commands', async () => {
    const clock = timers(); let complete; let count = 0;
    const hold = createHoldRepeat({ activate: () => { count++; return new Promise(resolve => complete = resolve); }, ...clock });
    hold.start();
    assert.equal(count, 1); assert.equal(clock.pending.size, 0);
    hold.stop(); complete(true); await settle();
    assert.equal(clock.pending.size, 0);
    hold.start(); complete(false); await settle();
    assert.equal(hold.active, false); assert.equal(clock.pending.size, 0);
});

test('life transactions show a five-second per-player net, cancel opposite taps, and ignore duplicate snapshots', () => {
    const clock = timers(); const display = new Map();
    const tracker = createLifeTransactions({ players: [{ id: 1, life: 40 }, { id: 2, life: 40 }], notify: (id, text) => display.set(id, text), ...clock });
    for (const life of [41, 42, 41, 40]) tracker.update([{ id: 1, life }]);
    assert.equal(display.get(1), '');
    tracker.update([{ id: 1, life: 35 }, { id: 2, life: 38 }]);
    assert.equal(display.get(1), '-5'); assert.equal(display.get(2), '-2');
    tracker.update([{ id: 1, life: 35 }, { id: 2, life: 38 }]);
    assert.equal(clock.pending.size, 2);
    assert.equal(clock.fire(), 5000);
    assert.equal(display.get(1), ''); assert.equal(display.get(2), '-2');
    clock.fire(); assert.equal(display.get(2), '');
    tracker.update([{ id: 1, life: 40 }, { id: 2, life: 40 }], 'reset');
    assert.equal(clock.pending.size, 0); assert.equal(display.get(1), '');
});
