import test from 'node:test';
import assert from 'node:assert/strict';
import { formatPrice, lookupCard, cardElement } from '../../resources/js/set-card-tools.js';

test('missing prices are never presented as a zero price', () => {
    assert.equal(formatPrice(null), 'Kein EUR-Preis');
    assert.equal(formatPrice(undefined), 'Kein EUR-Preis');
    assert.match(formatPrice(12.5), /12,50/);
});

test('collector lookup submits the selected subset and tolerates copied printed numbers', async () => {
    let requested;
    const result = await lookupCard('/set-cards/dsk/lookup', { subset: 'spg', number: '#0066/100' }, async url => {
        requested = url;
        return { ok: true, json: async () => ({ card: { name: 'Damnation' } }) };
    });
    assert.equal(requested, '/set-cards/dsk/lookup?subset=spg&number=0066');
    assert.equal(result.card.name, 'Damnation');
    await assert.rejects(lookupCard('/lookup', { subset: 'dsk', number: '1' }, async () => ({ ok: false, json: async () => ({ message: 'Nicht gefunden' }) })), /Nicht gefunden/);
});

test('card names use text nodes and lookup shows distinct normal and foil prices', () => {
    const doc = { createElement(tag) { return { tag, children: [], append(...children) { this.children.push(...children); } }; } };
    const card = { name: '<unsafe>', set: 'spg', number: '66', image: 'https://cards.scryfall.io/card.jpg', source: 'https://scryfall.com/card/spg/66', eur: 12.5, eur_foil: null };
    const element = cardElement(card, { lookup: true }, doc);
    const text = element.children[1];
    assert.equal(text.children[0].textContent, '<unsafe>');
    assert.match(text.children[1].textContent, /SPG · #66 · Special Guests/);
    assert.match(text.children[2].textContent, /Normal: 12,50/);
    assert.equal(text.children[3].textContent, 'Foil: Kein EUR-Preis');
});
