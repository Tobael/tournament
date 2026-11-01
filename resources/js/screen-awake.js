// Native wake locks are released on backgrounding and can also be revoked by the OS.
export function createScreenAwake({ document, navigator, fallback, notify, schedule = setTimeout, cancel = clearTimeout }) {
    let lock;
    let acquiring;
    let active = false;
    let destroyed = false;
    let retry;
    const visible = () => document.visibilityState === 'visible';
    function update(mode) { active = mode === 'active' || mode === 'fallback'; notify(mode); }
    async function enable() {
        if (destroyed || !visible() || active) return;
        if (acquiring) return acquiring;
        cancel(retry);
        acquiring = (async () => {
            try {
                if (navigator.wakeLock) {
                    const acquired = await navigator.wakeLock.request('screen');
                    if (destroyed || !visible()) { await acquired.release(); return; }
                    lock = acquired;
                    lock.addEventListener('release', () => {
                        if (lock !== acquired) return;
                        lock = null;
                        if (destroyed) return;
                        update(visible() ? 'blocked' : 'paused');
                        if (visible()) retry = schedule(() => void enable(), 1000);
                    }, { once: true });
                    update('active');
                } else if (fallback) {
                    await fallback.enable();
                    if (destroyed || !visible()) { fallback.disable(); return; }
                    update('fallback');
                } else update('blocked');
            } catch { if (!destroyed) update(visible() ? 'blocked' : 'paused'); }
        })();
        try { await acquiring; } finally { acquiring = null; }
    }
    function visibility() {
        if (visible()) void enable();
        else {
            cancel(retry);
            const previous = lock;
            lock = null;
            void previous?.release();
            fallback?.disable();
            update('paused');
        }
    }
    const fullscreen = () => { if (visible()) void enable(); };
    document.addEventListener('visibilitychange', visibility);
    document.addEventListener('fullscreenchange', fullscreen);
    return {
        enable,
        get active() { return active; },
        destroy() {
            destroyed = true;
            active = false;
            cancel(retry);
            document.removeEventListener('visibilitychange', visibility);
            document.removeEventListener('fullscreenchange', fullscreen);
            void lock?.release();
            lock = null;
            fallback?.disable();
        },
    };
}
