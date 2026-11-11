import test from 'node:test';
import assert from 'node:assert/strict';
import { createTableAudio } from '../../resources/js/commentary/audio.js';
import { SOUND_CLIPS } from '../../resources/js/commentary/callouts.js';
import { readFileSync } from 'node:fs';

function browser({ unsupported = false, failed = false } = {}) {
    const calls = []; const listeners = new Map(); const fetched = [];
    const document = { visibilityState: 'visible', addEventListener: (event, fn) => listeners.set(event, fn), removeEventListener: event => listeners.delete(event) };
    const parameter = { setValueAtTime() {}, linearRampToValueAtTime(value) { assert.ok(value <= .13); }, exponentialRampToValueAtTime() {} };
    class Context {
        state = 'running'; currentTime = 0; destination = {};
        createOscillator() { return { frequency: {}, connect() {}, disconnect() {}, start(at) { calls.push(['tone', at]); }, stop() { calls.push(['stop-tone']); } }; }
        createBufferSource() { return { connect() {}, disconnect() {}, start() { calls.push(['sample', this.buffer]); }, stop(at) { calls.push(['stop-sample', at]); } }; }
        createGain() { return { gain: { ...parameter, value: 0 }, connect() {}, disconnect() {} }; }
        decodeAudioData() { return Promise.resolve({ duration: 2 }); }
        close() { calls.push(['close']); return Promise.resolve(); }
    }
    const host = { document, AudioContext: unsupported ? undefined : Context,
        fetch: async (url, options) => { fetched.push({ url, signal: options.signal }); return { ok: !failed, arrayBuffer: async () => new ArrayBuffer(8) }; },
    };
    // Browser speech must never be consulted, even on devices that expose it.
    Object.defineProperty(host, 'speechSynthesis', { get() { throw new Error('Speech was accessed'); } });
    return { host, document, calls, listeners, fetched };
}
const loaded = audio => Promise.all(Object.keys(SOUND_CLIPS).map(id => audio.preload(id)));

test('locally hosted short samples require activation, preload once and never use browser speech', async () => {
    const b = browser(); const a = createTableAudio({ host: b.host });
    assert.equal(a.play('first-blood'), false); assert.equal(b.fetched.length, 0);
    a.activate(); await loaded(a);
    assert.equal(b.fetched.length, 6);
    assert.ok(b.fetched.every(item => item.url.startsWith('/assets/callouts/')));
    assert.equal(a.play('first-blood'), true);
    assert.equal(b.calls.filter(call => call[0] === 'sample').length, 1);
    a.activate(); await loaded(a); assert.equal(b.fetched.length, 6);
    a.destroy(); assert.equal(b.listeners.size, 0); assert.equal(b.calls.at(-1)[0], 'close');
});

test('mute, zero volume and hidden screens suppress and stop sample playback', async () => {
    const b = browser(); const a = createTableAudio({ host: b.host }); a.activate(); await loaded(a);
    a.play('bruh');
    a.set({ enabled: false }); assert.equal(a.play('bruh'), false);
    assert.ok(b.calls.some(call => call[0] === 'stop-sample'));
    a.set({ enabled: true, volume: 0 }); assert.equal(a.play('bruh'), false);
    a.set({ volume: .5 }); b.document.visibilityState = 'hidden'; b.listeners.get('visibilitychange')();
    assert.equal(a.play('bruh'), false); assert.equal(a.chime(), false);
    a.destroy();
});

test('missing or disabled samples use a short original cue and never queue late playback', async () => {
    const b = browser({ failed: true }); const a = createTableAudio({ host: b.host }); a.activate(); await loaded(a);
    assert.equal(a.play('bruh'), true); assert.equal(b.calls.filter(call => call[0] === 'tone').length, 2);
    assert.equal(b.calls.filter(call => call[0] === 'sample').length, 0); a.destroy();
    const other = browser(); const off = createTableAudio({ host: other.host, settings: { clips: false } }); off.activate();
    assert.equal(other.fetched.length, 0); off.play('bruh');
    assert.equal(other.calls.filter(call => call[0] === 'sample').length, 0); off.destroy();
    const unsupported = createTableAudio({ host: browser({ unsupported: true }).host });
    unsupported.activate(); assert.equal(unsupported.play('bruh'), false); unsupported.destroy();
});

test('TV chime remains two notes, and obsolete voice preferences are discarded', () => {
    const b = browser(); const a = createTableAudio({ host: b.host, settings: { clips: false, speech: true, voice: 'old', volume: .2 } });
    a.activate(); a.chime();
    assert.deepEqual(b.calls.filter(call => call[0] === 'tone'), [['tone', 0], ['tone', .09]]);
    assert.deepEqual(a.settings, { enabled: true, clips: false, popups: true, volume: .2 }); a.destroy();
});

test('each configured callout is a bundled, decodable MP3 rather than an external embed', () => {
    for (const clip of Object.values(SOUND_CLIPS)) {
        const bytes = readFileSync(new URL(`../../public${clip.file}`, import.meta.url));
        assert.ok(bytes.length > 5000 && bytes.length < 100000);
        assert.equal(bytes.subarray(0, 3).toString(), 'ID3');
    }
});

test('samples can be fetched before activation and decoded from cache on the first normal interaction', async () => {
    const b = browser(); const a = createTableAudio({ host: b.host });
    await a.prepare(); assert.equal(b.fetched.length, 6);
    assert.equal(a.play('first-blood'), false);
    a.activate(); await loaded(a);
    assert.equal(b.fetched.length, 6); a.play('first-blood');
    assert.equal(b.calls.filter(call => call[0] === 'sample').length, 1); a.destroy();
});

test('audio is not reported as activated while the browser still blocks its context', async () => {
    const b = browser(); const Base = b.host.AudioContext; let context;
    b.host.AudioContext = class extends Base {
        state = 'suspended';
        constructor() { super(); context = this; }
        resume() { return Promise.resolve(); }
    };
    const a = createTableAudio({ host: b.host, settings: { clips: false } });
    assert.equal(await a.activate(), false); assert.equal(a.activated, false); assert.equal(a.chime(), false);
    context.state = 'running'; assert.equal(a.activated, true); assert.equal(a.chime(), true); a.destroy();
});

test('TV defeat and rank gain cues have distinct descending and ascending notes', async () => {
    const b = browser(); const Base = b.host.AudioContext; const notes = [];
    b.host.AudioContext = class extends Base {
        createOscillator() {
            const source = super.createOscillator(); const start = source.start;
            source.start = at => { notes.push(source.frequency.value); start.call(source, at); };
            return source;
        }
    };
    const audio = createTableAudio({ host: b.host, settings: { clips: false } });
    await audio.activate(); audio.signal('defeat');
    assert.deepEqual(notes, [293.66, 220, 146.83]);
    notes.length = 0; audio.signal('rank-up');
    assert.deepEqual(notes, [523.25, 659.25, 783.99, 1046.5]);
    notes.length = 0; audio.signal('turnaround'); assert.equal(notes.length, 7); audio.destroy();
});
