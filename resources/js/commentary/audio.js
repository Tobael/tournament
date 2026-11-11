import { t } from '../i18n.js';
import { SOUND_CLIPS } from './callouts.js';

// Short, locally hosted samples only. Nothing here synthesizes or queues speech.
export function createTableAudio({ host = globalThis, document = host.document, settings = {}, changed = () => {} }) {
    const prefs = {
        enabled: settings.enabled !== false,
        clips: settings.clips !== false,
        popups: settings.popups !== false,
        volume: Number.isFinite(settings.volume) ? Math.max(0, Math.min(1, settings.volume)) : .65,
    };
    let context;
    let activated = false;
    let destroyed = false;
    const active = new Set();
    const buffers = new Map();
    const encoded = new Map();
    const loading = new Map();
    const requests = new Set();
    const visible = () => !destroyed && document?.visibilityState !== 'hidden';
    function stop() {
        for (const source of active) { try { source.stop(); } catch {} }
        active.clear();
    }
    function preload(id) {
        if (!SOUND_CLIPS[id] || !host.fetch || buffers.has(id)) return Promise.resolve();
        if (loading.has(id)) return loading.get(id);
        const controller = new AbortController();
        requests.add(controller);
        const timeout = setTimeout(() => controller.abort(), 5000);
        const bytes = encoded.has(id) ? Promise.resolve(encoded.get(id))
            : host.fetch(SOUND_CLIPS[id].file, { signal: controller.signal, credentials: 'same-origin' })
                .then(response => { if (!response.ok) throw new Error('Sample unavailable'); return response.arrayBuffer(); });
        const job = bytes.then(data => {
            if (destroyed) return null;
            encoded.set(id, data);
            return context ? context.decodeAudioData(data.slice(0)) : null;
        })
            .then(buffer => { if (buffer && !destroyed) buffers.set(id, buffer); })
            .catch(() => { /* An unavailable clip falls back to the original arcade cue. */ })
            .finally(() => { clearTimeout(timeout); loading.delete(id); requests.delete(controller); });
        loading.set(id, job);
        return job;
    }
    function activate() {
        if (!visible() || !prefs.enabled) return Promise.resolve(false);
        let resumed = Promise.resolve(false);
        try {
            const AudioContext = host.AudioContext || host.webkitAudioContext;
            if (!context && AudioContext) {
                context = new AudioContext({ latencyHint: 'interactive' });
                context.addEventListener?.('statechange', changed);
            }
            resumed = context?.state === 'running' ? Promise.resolve(true)
                : context && context.state !== 'closed' ? context.resume().then(() => context.state === 'running').catch(() => false) : Promise.resolve(false);
            // Preload after user activation; playback never waits in a stale-event queue.
            if (!activated && prefs.clips) for (const id of Object.keys(SOUND_CLIPS)) void preload(id);
        } catch { /* Popups work without audio support. */ }
        activated = true;
        changed();
        return resumed.then(ok => { if (!destroyed) changed(); return !destroyed && ok; });
    }
    function ready() { return visible() && activated && prefs.enabled && prefs.volume > 0 && context?.state === 'running'; }
    function tone(kind = 'score') {
        if (!ready()) return false;
        const sequences = {
            score: [[659.25, 0], [987.77, .09]],
            'rank-up': [[523.25, 0], [659.25, .09], [783.99, .18], [1046.5, .3]],
            defeat: [[293.66, 0], [220, .13], [146.83, .28]],
            turnaround: [[293.66, 0], [220, .13], [146.83, .26], [523.25, .6], [659.25, .69], [783.99, .78], [1046.5, .9]],
            hit: [[180, 0], [90, .08]],
            critical: [[330, 0], [165, .08], [82.5, .16]],
            knockout: [[220, 0], [164.8, .12], [110, .24]],
            victory: [[523.25, 0], [659.25, .09], [783.99, .18], [1046.5, .3]],
        };
        try {
            const start = context.currentTime;
            for (const [frequency, offset] of sequences[kind] || sequences.score) {
                const source = context.createOscillator();
                const gain = context.createGain();
                source.type = kind === 'hit' || kind === 'critical' ? 'triangle' : 'sine';
                source.frequency.value = frequency;
                gain.gain.setValueAtTime(0, start + offset);
                gain.gain.linearRampToValueAtTime(prefs.volume * .13, start + offset + .008);
                gain.gain.exponentialRampToValueAtTime(.0001, start + offset + .22);
                source.connect(gain); gain.connect(context.destination);
                source.onended = () => { active.delete(source); source.disconnect(); gain.disconnect(); };
                active.add(source);
                source.start(start + offset); source.stop(start + offset + .24);
            }
            return true;
        } catch { return false; }
    }
    function play(id, fallback = 'hit') {
        if (!ready()) return false;
        stop();
        const buffer = prefs.clips && buffers.get(id);
        if (!buffer) return tone(fallback);
        try {
            const source = context.createBufferSource();
            const gain = context.createGain();
            source.buffer = buffer;
            gain.gain.value = prefs.volume * .75;
            source.connect(gain); gain.connect(context.destination);
            source.onended = () => { active.delete(source); source.disconnect(); gain.disconnect(); };
            active.add(source);
            source.start(); source.stop(context.currentTime + Math.min(buffer.duration, 4.2));
            return true;
        } catch { return tone(fallback); }
    }
    function set(values) {
        for (const key of ['enabled', 'clips', 'popups']) if (key in values) prefs[key] = Boolean(values[key]);
        if ('volume' in values) prefs.volume = Math.max(0, Math.min(1, Number(values.volume) || 0));
        if (!prefs.enabled || !prefs.volume) stop();
        if (!prefs.clips) stop();
        else if (activated) for (const id of Object.keys(SOUND_CLIPS)) void preload(id);
        changed();
    }
    const onHidden = () => { if (!visible()) stop(); };
    document?.addEventListener('visibilitychange', onHidden);
    return {
        get settings() { return { ...prefs }; },
        get activated() { return activated && context?.state === 'running'; },
        get supported() { return Boolean(host.AudioContext || host.webkitAudioContext); },
        activate, set, play, preload, signal: kind => play(null, kind), prepare: () => prefs.enabled && prefs.clips && Promise.all(Object.keys(SOUND_CLIPS).map(preload)), chime: () => tone('score'), stop,
        destroy() {
            stop(); destroyed = true;
            for (const controller of requests) controller.abort();
            requests.clear(); buffers.clear(); encoded.clear(); loading.clear();
            document?.removeEventListener('visibilitychange', onHidden);
            context?.removeEventListener?.('statechange', changed);
            void context?.close().catch(() => {});
        },
    };
}

export function setupCommentary(root, { scope }) {
    const controls = root.querySelector('[data-commentary-controls]');
    const popup = root.querySelector('[data-callout]');
    const toggle = controls.querySelector('[data-commentary-toggle]');
    const clips = controls.querySelector('[data-commentary-clips]');
    const popups = controls.querySelector('[data-commentary-popups]');
    const sample = controls.querySelector('[data-commentary-sample]');
    const volume = controls.querySelector('[data-commentary-volume]');
    const tvPrompt = root.querySelector('[data-tv-audio-prompt]');
    const tvEnable = root.querySelector('[data-tv-audio-enable]');
    const gestureTarget = scope === 'tv' ? document : root;
    const key = `arcane-table.commentary.${scope}`;
    let settings;
    let storedSettings;
    let popupTimer;
    let highlighted;
    let generation = 0;
    try { settings = JSON.parse(localStorage.getItem(key)); } catch {}
    // Only enabled/volume migrate from the previous settings; obsolete voice fields are dropped.
    const audio = createTableAudio({ host: window, settings: settings || {}, changed: render });
    function render() {
        const prefs = audio.settings;
        toggle.textContent = !prefs.enabled ? t('Ton aus') : !audio.activated ? t('Ton aktivieren') : t('Ton an');
        toggle.setAttribute('aria-pressed', String(prefs.enabled && audio.activated));
        if (tvPrompt) tvPrompt.hidden = !prefs.enabled || audio.activated || !audio.supported;
        clips.checked = prefs.clips; popups.checked = prefs.popups;
        volume.value = Math.round(prefs.volume * 100);
        const serialized = JSON.stringify(prefs);
        if (serialized !== storedSettings) {
            try { localStorage.setItem(key, serialized); storedSettings = serialized; } catch {}
        }
    }
    function clearPopup() {
        clearTimeout(popupTimer);
        popup.hidden = true;
        popup.querySelector('[data-commentary-text]').textContent = '';
        highlighted?.classList.remove('callout-hit');
        highlighted = null;
    }
    function show(callout, withSound = scope !== 'tv') {
        if (document.visibilityState === 'hidden' || !root.isConnected) return;
        clearPopup();
        if (audio.settings.popups) {
            popup.dataset.tone = callout.tone;
            popup.dataset.calloutId = callout.id;
            popup.querySelector('[data-callout-title]').textContent = callout.title;
            popup.querySelector('[data-commentary-text]').textContent = callout.detail;
            popup.querySelector('[data-callout-badge]').textContent = callout.badge;
            popup.hidden = false;
            for (const animation of popup.getAnimations({ subtree: true })) { animation.cancel(); animation.play(); }
            if (Number.isSafeInteger(callout.playerId)) {
                highlighted = root.querySelector(`[data-life-seat="${callout.playerId}"]`);
                highlighted?.classList.add('callout-hit');
            }
            popupTimer = setTimeout(clearPopup, 2400);
        }
        if (withSound) audio.play(callout.sound, callout.tone);
    }
    const enableTvAudio = () => {
        audio.set({ enabled: true, volume: audio.settings.volume || .65 });
        // Resume synchronously inside the TV click/remote key gesture, then confirm audibly.
        void audio.activate().then(ok => { if (ok) audio.chime(); });
    };
    const toggleAudio = () => {
        if (audio.settings.enabled && audio.activated) audio.set({ enabled: false });
        else if (scope === 'tv') enableTvAudio();
        else { audio.set({ enabled: true }); audio.activate(); }
    };
    const onClips = () => audio.set({ clips: clips.checked });
    const onPopups = () => { audio.set({ popups: popups.checked }); if (!popups.checked) clearPopup(); };
    const onVolume = () => audio.set({ volume: Number(volume.value) / 100 });
    const test = async () => {
        const current = ++generation;
        const id = sample.value;
        audio.set({ enabled: true, clips: true }); audio.activate();
        await audio.preload(id);
        if (current !== generation) return;
        show({ id, title: SOUND_CLIPS[id].title, detail: t('Soundcheck'), badge: '♪', sound: id, tone: 'hit' }, true);
    };
    const gesture = event => {
        if (event.isTrusted === false || event.target.closest?.('[data-commentary-controls], [data-tv-audio-enable]') || (event.type === 'keydown' && !['Enter', ' ', 'Select'].includes(event.key) && event.keyCode !== 13)) return;
        audio.activate();
    };
    const testButton = controls.querySelector('[data-commentary-test]');
    toggle.addEventListener('click', toggleAudio); clips.addEventListener('change', onClips);
    popups.addEventListener('change', onPopups); volume.addEventListener('input', onVolume);
    testButton.addEventListener('click', test);
    for (const type of ['pointerdown', 'pointerup', 'click', 'keydown']) gestureTarget.addEventListener(type, gesture, true);
    const hidden = () => { if (document.visibilityState === 'hidden') { generation++; clearPopup(); } };
    document.addEventListener('visibilitychange', hidden);
    tvEnable?.addEventListener('click', enableTvAudio);
    render();
    void audio.prepare();
    return {
        chime: () => audio.chime(), signal: kind => audio.signal(kind), show,
        clear() { generation++; clearPopup(); audio.stop(); },
        destroy() {
            generation++; clearPopup(); audio.destroy();
            document.removeEventListener('visibilitychange', hidden);
            for (const type of ['pointerdown', 'pointerup', 'click', 'keydown']) gestureTarget.removeEventListener(type, gesture, true);
            toggle.removeEventListener('click', toggleAudio); clips.removeEventListener('change', onClips);
            popups.removeEventListener('change', onPopups); volume.removeEventListener('input', onVolume);
            testButton.removeEventListener('click', test);
            tvEnable?.removeEventListener('click', enableTvAudio);
        },
    };
}
