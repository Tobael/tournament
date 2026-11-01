import test from 'node:test';
import assert from 'node:assert/strict';
import { createScreenAwake } from '../../resources/js/screen-awake.js';

function fixture(native = true) {
    const document = new EventTarget();
    document.visibilityState = 'visible';
    const locks = [];
    const modes = [];
    const timers = new Map();
    let attempts = 0;
    const navigator = native ? { wakeLock: { request: async () => {
        attempts++;
        const lock = new EventTarget();
        lock.release = async () => { lock.released = true; lock.dispatchEvent(new Event('release')); };
        locks.push(lock);
        return lock;
    } } } : {};
    const fallback = { enable: async () => { attempts++; }, disable: () => {} };
    const manager = createScreenAwake({ document, navigator, fallback, notify: value => modes.push(value), schedule: callback => { timers.set(1, callback); return 1; }, cancel: id => timers.delete(id) });
    return { document, locks, modes, timers, manager, fallback, get attempts() { return attempts; } };
}
const settle = async () => { await new Promise(resolve => setImmediate(resolve)); };

test('native wake lock recovers from revocation and returning from the background', async () => {
    const f = fixture();
    await f.manager.enable();
    assert.equal(f.manager.active, true);
    await f.locks[0].release();
    assert.equal(f.manager.active, false);
    f.timers.get(1)();
    await settle();
    assert.equal(f.attempts, 2);
    f.document.visibilityState = 'hidden';
    f.document.dispatchEvent(new Event('visibilitychange'));
    assert.equal(f.manager.active, false);
    assert.equal(f.locks[1].released, true);
    f.document.visibilityState = 'visible';
    f.document.dispatchEvent(new Event('visibilitychange'));
    await settle();
    assert.equal(f.attempts, 3);
    f.manager.destroy();
    assert.equal(f.locks[2].released, true);
    assert.equal(f.timers.size, 0);
});

test('HTTP fallback failures are visible and a later gesture can retry successfully', async () => {
    const f = fixture(false);
    f.fallback.enable = async () => { throw new Error('User gesture required'); };
    await f.manager.enable();
    assert.equal(f.manager.active, false);
    assert.equal(f.modes.at(-1), 'blocked');
    f.fallback.enable = async () => {};
    await f.manager.enable();
    assert.equal(f.manager.active, true);
    assert.equal(f.modes.at(-1), 'fallback');
    f.manager.destroy();
});

test('a pending wake request is released if the page closes before it resolves', async () => {
    const document = new EventTarget();
    document.visibilityState = 'visible';
    let resolve;
    let released = false;
    const manager = createScreenAwake({ document, navigator: { wakeLock: { request: () => new Promise(r => resolve = r) } }, notify: () => {} });
    const pending = manager.enable();
    manager.destroy();
    resolve({ release: async () => { released = true; } });
    await pending;
    assert.equal(released, true);
    assert.equal(manager.active, false);
});
