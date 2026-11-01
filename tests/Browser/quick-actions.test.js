import test from 'node:test';
import assert from 'node:assert/strict';
import { randomIndex, rollDie, selectionFrames, animateFrames } from '../../resources/js/quick-actions.js';

test('dice include their first and last faces and the coin uses text labels', () => {
    for (const sides of [2, 4, 6, 8, 10, 12, 20]) {
        assert.equal(rollDie(sides, () => 0).value, 1);
        assert.equal(rollDie(sides, () => sides - 1).value, sides);
    }
    assert.equal(rollDie(2, () => 0).label, 'Münze: Kopf');
    assert.equal(rollDie(2, () => 1).label, 'Münze: Zahl');
    assert.throws(() => rollDie(3), RangeError);
});

test('selection discards the incomplete random range to avoid modulo bias', () => {
    const values = [0xffffffff, 4];
    assert.equal(randomIndex(5, () => values.shift()), 4);
    assert.equal(values.length, 0);
    for (const count of [2, 3, 4, 5]) for (let index = 0; index < count; index++) assert.equal(randomIndex(count, () => index), index);
    assert.throws(() => randomIndex(0), RangeError);
});


test('player selection visits every seat and settles on the already chosen player', () => {
    for (const count of [1, 2, 3, 4, 5]) for (let selected = 0; selected < count; selected++) {
        const frames = selectionFrames(count, selected);
        assert.equal(frames.at(-1), selected);
        assert.equal(new Set(frames).size, count);
        assert.ok(frames.length >= 8);
    }
});

test('animation finishes once, and cancellation prevents pending frames and results', () => {
    let pending;
    const seen = [];
    let finished = 0;
    const clock = { schedule: callback => { pending = callback; return 1; }, cancel: () => { pending = null; } };
    animateFrames([0, 1, 2], frame => seen.push(frame), () => finished++, clock);
    while (pending) { const next = pending; pending = null; next(); }
    assert.deepEqual(seen, [0, 1, 2]);
    assert.equal(finished, 1);
    const stop = animateFrames([3, 4, 5], frame => seen.push(frame), () => finished++, clock);
    const stale = pending;
    stop();
    stale();
    assert.equal(pending, null);
    assert.deepEqual(seen, [0, 1, 2, 3]);
    assert.equal(finished, 1);
});
