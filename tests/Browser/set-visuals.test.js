import test from 'node:test';
import assert from 'node:assert/strict';
import { setupSetVisuals } from '../../resources/js/set-visuals.js';

function figure(url) {
    const controls = Object.fromEntries(['symbol', 'symbol-fallback', 'art-image', 'credit', 'source', 'art'].map(name => {
        const handlers = {};
        return [name, { hidden: true, complete: false, naturalWidth: 0, handlers, addEventListener(type, callback) { handlers[type] = callback; } }];
    }));
    return { controls, isConnected: true, dataset: { artworkUrl: url }, querySelector(selector) { return controls[selector.slice('[data-set-'.length, -1)]; } };
}

test('artwork survives Livewire remounts with one request and credits are plain text', async () => {
    const originalFetch = globalThis.fetch;
    let requests = 0;
    globalThis.fetch = async () => {
        requests++;
        return { ok: true, json: async () => ({ image: 'https://cards.scryfall.io/art.jpg', artist: '<Artist>', card: 'Card', source: 'https://scryfall.com/card/test' }) };
    };
    try {
        const first = figure('/set-visuals/tst/artwork');
        setupSetVisuals({ querySelectorAll: () => [first] });
        setupSetVisuals({ querySelectorAll: () => [first] });
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(requests, 1);
        assert.equal(first.controls['art-image'].src, 'https://cards.scryfall.io/art.jpg');
        assert.equal(first.controls.credit.textContent, 'Illustration: <Artist> · Card');
        first.controls['art-image'].handlers.load();
        assert.equal(first.controls.art.hidden, false);
        first.controls.symbol.handlers.load();
        assert.equal(first.controls.symbol.hidden, false);
        assert.equal(first.controls['symbol-fallback'].hidden, true);
        const remounted = figure('/set-visuals/tst/artwork');
        setupSetVisuals({ querySelectorAll: () => [remounted] });
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(requests, 1);
        assert.equal(remounted.controls['art-image'].src, first.controls['art-image'].src);
    } finally { globalThis.fetch = originalFetch; }
});

test('unavailable art leaves the set identity visible without breaking initialization', async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async () => { throw new Error('offline'); };
    try {
        const fallback = figure('/set-visuals/offline/artwork');
        setupSetVisuals({ querySelectorAll: () => [fallback] });
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(fallback.controls.art.hidden, true);
        assert.equal(fallback.controls['art-image'].src, undefined);
    } finally { globalThis.fetch = originalFetch; }
});
