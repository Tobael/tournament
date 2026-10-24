import test from 'node:test';
import assert from 'node:assert/strict';
import { setupSetParallax } from '../../resources/js/set-parallax.js';

test('parallax coalesces scroll events and stops moving when reduced motion is enabled', () => {
    const frames = [];
    const events = {};
    const motion = { matches: false, addEventListener(type, callback) { this.change = callback; } };
    const browser = {
        scrollY: 0, matchMedia: () => motion,
        requestAnimationFrame(callback) { frames.push(callback); },
        addEventListener(type, callback, options) { events[type] = callback; assert.equal(options.passive, true); },
    };
    let position;
    const layer = { style: { setProperty(name, value) { position = value; } } };
    setupSetParallax(browser, { querySelectorAll: () => [layer] });
    frames.shift()();
    assert.equal(position, '0px');
    browser.scrollY = 300;
    events.scroll(); events.scroll(); events.resize();
    assert.equal(frames.length, 1);
    frames.shift()();
    assert.equal(position, '-195px');
    motion.matches = true;
    motion.change();
    frames.shift()();
    assert.equal(position, '0px');
});
