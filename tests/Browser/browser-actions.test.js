import test from 'node:test';
import assert from 'node:assert/strict';
import { copyLink, shareLink, readParticipants, rememberParticipant, forgetParticipant, restoreParticipant, deviceStorageKey } from '../../resources/js/browser-actions.js';

function controls() {
    return {
        input: { value: 'https://example.com/play/one', focus() { this.focused = true; }, select() { this.selected = true; }, setSelectionRange(start, end) { this.range = [start, end]; } },
        button: { textContent: 'Link kopieren' }, message: { textContent: '' },
    };
}
function storage() {
    const values = new Map();
    return { getItem: key => values.get(key) ?? null, setItem: (key, value) => values.set(key, value) };
}
const identity = { invite: '12345678-1234-1234-1234-123456789012', token: 'a'.repeat(64), title: 'Friday', name: 'Alex' };

test('clipboard receives the current input URL, including after a rerender', async () => {
    const { input, button, message } = controls();
    const copied = [];
    const browser = { navigator: { clipboard: { writeText: async value => copied.push(value) } } };
    assert.equal(await copyLink(input, button, message, browser), true);
    input.value = 'https://example.com/return#new-token';
    await copyLink(input, button, message, browser);
    assert.deepEqual(copied, ['https://example.com/play/one', input.value]);
    assert.equal(input.selected, undefined);
    assert.match(message.textContent, /kopiert/);
});

test('denied clipboard permission falls back to selection and the legacy copy API', async () => {
    const { input, button, message } = controls();
    const browser = { navigator: { clipboard: { writeText: async () => { throw new Error('Denied'); } } }, document: { execCommand: command => command === 'copy' } };
    assert.equal(await copyLink(input, button, message, browser), true);
    assert.equal(input.focused, true);
    assert.deepEqual(input.range, [0, input.value.length]);
});

test('unsupported copying leaves the link selected and never claims success', async () => {
    const { input, button, message } = controls();
    assert.equal(await copyLink(input, button, message, { navigator: {}, document: { execCommand: () => false } }), false);
    assert.equal(input.selected, true);
    assert.equal(button.textContent, 'Link kopieren');
    assert.match(message.textContent, /markiert/);
});

test('cancelling native sharing does not unexpectedly copy the private link', async () => {
    const { input, button, message } = controls();
    const browser = { navigator: { share: async () => { throw { name: 'AbortError' }; }, clipboard: { writeText: () => assert.fail('No copy on cancellation') } } };
    await shareLink(input, button, message, browser);
    assert.equal(message.textContent, '');
});

test('device identities survive session loss and are stored per tournament', () => {
    const device = storage();
    assert.equal(rememberParticipant(device, identity), true);
    const second = { ...identity, invite: '87654321-1234-1234-1234-123456789012', name: 'Sam' };
    rememberParticipant(device, second);
    rememberParticipant(device, { ...identity, token: 'b'.repeat(64) });
    assert.equal(readParticipants(device).length, 2);
    assert.equal(readParticipants(device)[0].token, 'b'.repeat(64));
    forgetParticipant(device, identity.invite);
    assert.deepEqual(readParticipants(device), [second]);
});

test('corrupted or disabled local storage does not prevent normal participation', () => {
    const device = storage();
    device.setItem(deviceStorageKey, '{broken');
    assert.deepEqual(readParticipants(device), []);
    device.setItem(deviceStorageKey, JSON.stringify([{ invite: 'https://evil.example', token: 'a'.repeat(64) }]));
    assert.deepEqual(readParticipants(device), []);
    const blocked = { getItem() { throw new Error('Disabled'); }, setItem() { throw new Error('Disabled'); } };
    assert.equal(rememberParticipant(blocked, identity), false);
    assert.deepEqual(readParticipants(blocked), []);
});

test('automatic recovery sends only the scoped event token using CSRF protection', async () => {
    const result = await restoreParticipant(identity, 'csrf-value', async (url, options) => {
        assert.equal(url, '/return');
        assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-value');
        assert.equal(options.credentials, 'same-origin');
        assert.deepEqual(JSON.parse(options.body), { token: identity.token });
        return { ok: true, status: 200, json: async () => ({ url: `/play/${identity.invite}` }) };
    });
    assert.deepEqual(result, { path: `/play/${identity.invite}` });
});

test('expired tokens are discarded while temporary failures remain retryable', async () => {
    assert.deepEqual(await restoreParticipant(identity, 'csrf', async () => ({ status: 422 })), { expired: true });
    await assert.rejects(() => restoreParticipant(identity, 'csrf', async () => ({ ok: false, status: 503 })));
    assert.deepEqual(await restoreParticipant(identity, 'csrf', async () => ({ ok: true, status: 200, json: async () => ({ url: '/play/another-event' }) })), { expired: true });
});
