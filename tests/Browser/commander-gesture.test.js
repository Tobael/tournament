import test from 'node:test';
import assert from 'node:assert/strict';
import { createDamageDrag } from '../../resources/js/commander-gesture.js';

function fixture() {
    let timer;
    let target = 2;
    const drops = [];
    let cleared = 0;
    let started = 0;
    const drag = createDamageDrag({ schedule: callback => { timer = callback; return 1; }, cancel: () => { timer = null; }, hitTest: () => target, start: () => { started++; }, move: () => {}, end: (...args) => drops.push(args), clear: () => { cleared++; } });
    return { drag, drops, hold: () => timer?.(), set target(value) { target = value; }, get started() { return started; }, get cleared() { return cleared; } };
}

test('hold then drag selects the source and recipient without directly applying damage', () => {
    const f = fixture();
    f.drag.down(1, 7, 10, 10);
    f.hold();
    f.drag.move(1, 200, 300);
    f.drag.up(1, 200, 300);
    assert.equal(f.started, 1);
    assert.deepEqual(f.drops, [[7, 2]]);
    assert.equal(f.cleared, 1);
});

test('short taps and movement before holding never select damage', () => {
    const f = fixture();
    f.drag.down(1, 7, 10, 10);
    f.drag.up(1, 10, 10);
    f.drag.down(1, 7, 10, 10);
    f.drag.move(1, 25, 10);
    f.hold();
    f.drag.up(1, 200, 300);
    assert.deepEqual(f.drops, []);
    assert.equal(f.started, 0);
});

test('self-drops, outside drops, cancellation and multitouch never select damage', () => {
    for (const target of [7, null]) {
        const f = fixture(); f.target = target;
        f.drag.down(1, 7, 10, 10); f.hold(); f.drag.up(1, 200, 300);
        assert.deepEqual(f.drops, []);
    }
    const f = fixture();
    f.drag.down(1, 7, 10, 10); f.hold(); f.drag.cancel(); f.drag.up(1, 200, 300);
    f.drag.down(1, 7, 10, 10); f.drag.down(2, 9, 10, 10); f.hold(); f.drag.up(1, 200, 300);
    assert.deepEqual(f.drops, []);
});
