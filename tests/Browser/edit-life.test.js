import test from 'node:test';
import assert from 'node:assert/strict';
import { createDoubleTap } from '../../resources/js/edit-life.js';

function fixture() {
    let time = 0;
    const opened = [];
    const taps = createDoubleTap({ open: id => opened.push(id), now: () => time });
    return { taps, opened, at(value) { time = value; } };
}

test('two short taps on the same life total open one editor, including changing touch pointer IDs', () => {
    const f = fixture();
    f.taps.down(1, 7, 10, 10); f.at(80); f.taps.up(1, 7, 10, 10);
    assert.deepEqual(f.opened, []);
    f.at(160); f.taps.down(2, 7, 12, 10); f.at(210); f.taps.up(2, 7, 12, 10);
    assert.deepEqual(f.opened, [7]);
});

test('holding, dragging and tapping different players never opens the life editor', () => {
    const f = fixture();
    f.taps.down(1, 7, 10, 10); f.at(500); f.taps.up(1, 7, 10, 10);
    f.at(600); f.taps.down(2, 7, 10, 10); f.taps.move(2, 50, 10); f.at(650); f.taps.up(2, 7, 10, 10);
    assert.deepEqual(f.opened, []);
    f.at(700); f.taps.down(3, 7, 10, 10); f.at(750); f.taps.up(3, 7, 10, 10);
    f.at(800); f.taps.down(4, 8, 10, 10); f.at(850); f.taps.up(4, 8, 10, 10);
    assert.deepEqual(f.opened, []);
});

test('slow taps and cancelled gestures are not treated as a double tap', () => {
    const f = fixture();
    f.taps.down(1, 7, 10, 10); f.at(50); f.taps.up(1, 7, 10, 10);
    f.at(500); f.taps.down(2, 7, 10, 10); f.at(550); f.taps.up(2, 7, 10, 10);
    assert.deepEqual(f.opened, []);
    f.taps.cancel();
    f.at(600); f.taps.down(3, 7, 10, 10); f.at(650); f.taps.up(3, 7, 10, 10);
    assert.deepEqual(f.opened, []);
});
