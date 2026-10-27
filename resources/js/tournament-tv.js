// Only canonical YouTube destinations become iframe URLs; never embed pasted HTML.
export function youtubeEmbed(value) {
    let url;
    try { url = new URL(value); } catch { return null; }
    if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password || url.port) return null;
    const host = url.hostname.toLowerCase();
    if (!['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtu.be', 'www.youtu.be'].includes(host)) return null;
    const parts = url.pathname.split('/').filter(Boolean);
    const video = host.endsWith('youtu.be') ? parts[0]
        : ['embed', 'shorts', 'live'].includes(parts[0]) ? parts[1] : url.searchParams.get('v');
    const playlist = url.searchParams.get('list');
    if (video && !/^[a-zA-Z0-9_-]{11}$/.test(video)) return null;
    if (playlist && !/^[a-zA-Z0-9_-]{10,100}$/.test(playlist)) return null;
    if (!video && !playlist) return null;
    const embed = new URL(`https://www.youtube.com/embed/${video || 'videoseries'}`);
    if (playlist) embed.searchParams.set('list', playlist);
    embed.searchParams.set('playsinline', '1');
    return embed.href;
}

export function formatCountdown(deadline, now) {
    const seconds = Math.ceil((deadline - now) / 1000);
    if (seconds <= 0) return 'Zeit abgelaufen · Extrazüge';
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

export function setupTournamentTv() {
    const screen = document.querySelector('[data-tv]');
    if (!screen || screen.dataset.initialized) return;
    screen.dataset.initialized = '1';
    const board = screen.querySelector('[data-tv-board]');
    const connection = screen.querySelector('[data-tv-connection]');
    const music = screen.querySelector('#tv-music');
    const toggle = screen.querySelector('[data-tv-music-toggle]');
    const player = screen.querySelector('[data-tv-player]');
    const message = screen.querySelector('[data-tv-music-message]');
    let musicRevision;
    const automatic = screen.dataset.automatic === '1';
    let clockOffset = Number(screen.dataset.serverTime) - Date.now();
    let closed = screen.dataset.closed === '1';
    let wakeLock;
    let refreshing = false;
    let stopped = false;
    let refreshTimer;
    let request;

    function fit() {
        for (const viewport of board.querySelectorAll('.tv-fit')) {
            const content = viewport.firstElementChild;
            content.style.transform = '';
            const scale = Math.min(1, viewport.clientHeight / Math.max(1, content.scrollHeight));
            content.style.transform = `scale(${scale})`;
        }
    }
    function tick() {
        const now = Date.now() + clockOffset;
        for (const timer of board.querySelectorAll('[data-tv-deadline]')) {
            timer.textContent = formatCountdown(Number(timer.dataset.tvDeadline), now);
            timer.classList.toggle('tv-expired', Number(timer.dataset.tvDeadline) <= now);
        }
        screen.querySelector('[data-tv-clock]').textContent = new Date(now).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    }
    function update(html) {
        const previous = new Map(Array.from(board.querySelectorAll('[data-tv-key]'), node => [node.dataset.tvKey, {
            top: node.getBoundingClientRect().top, value: node.dataset.tvValue,
        }]));
        board.innerHTML = html;
        tick();
        fit();
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        for (const node of board.querySelectorAll('[data-tv-key]')) {
            const before = previous.get(node.dataset.tvKey);
            const offset = before ? before.top - node.getBoundingClientRect().top : 12;
            if (node.animate && (!before || offset || before.value !== node.dataset.tvValue)) {
                node.animate([
                    { translate: `0 ${offset}px`, opacity: .5, backgroundColor: '#c6fb6528' },
                    { translate: '0 0', opacity: 1, backgroundColor: 'transparent' },
                ], { duration: 650, easing: 'ease-out' });
            }
        }
    }
    async function refresh() {
        if (refreshing || stopped) return;
        clearTimeout(refreshTimer);
        refreshing = true;
        request = new AbortController();
        const timeout = setTimeout(() => request.abort(), 15000);
        try {
            const response = await fetch(screen.dataset.stateUrl, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: request.signal });
            if (!response.ok) throw new Error('Refresh failed');
            const data = await response.json();
            if (typeof data.html !== 'string' || !Number.isFinite(data.serverTime) || typeof data.closed !== 'boolean') throw new Error('Invalid state');
            if (stopped) return;
            clockOffset = data.serverTime - Date.now();
            update(data.html);
            syncMusic(data.music);
            closed = data.closed;
            if (data.title) document.title = data.title;
            if (data.playUrl) screen.querySelector('[data-tv-event-link]').href = data.playUrl;
            connection.textContent = closed ? 'Endstand · Turnier beendet' : 'Live · Aktualisiert alle 10 Sekunden';
            if (automatic) connection.textContent += ' · Automatische Turnierwahl';
            connection.classList.remove('tv-offline');
        } catch {
            if (!stopped) {
                connection.textContent = 'Verbindung unterbrochen · Neuer Versuch in 10 Sekunden';
                connection.classList.add('tv-offline');
            }
        } finally {
            clearTimeout(timeout);
            refreshing = false;
            if (!stopped) refreshTimer = setTimeout(refresh, 10000);
        }
    }
    async function keepAwake() {
        if (!navigator.wakeLock || document.visibilityState !== 'visible' || wakeLock || stopped) return;
        try {
            wakeLock = await navigator.wakeLock.request('screen');
            wakeLock.addEventListener('release', () => { wakeLock = null; });
        } catch { /* Fire TV power settings remain the fallback. */ }
    }
    toggle.addEventListener('click', () => {
        music.hidden = !music.hidden;
        toggle.setAttribute('aria-expanded', String(!music.hidden));
        screen.classList.toggle('tv-with-music', !music.hidden);
        fit();
    });
    function syncMusic(state) {
        if (!state || musicRevision === state.revision) return;
        musicRevision = state.revision;
        player.replaceChildren();
        const src = state.url && youtubeEmbed(state.url);
        if (!src) {
            message.textContent = 'Musik gestoppt. Teilnehmende können am Handy einen YouTube-Link senden.';
            music.hidden = true;
        } else {
            const iframe = document.createElement('iframe');
            iframe.src = `${src}&autoplay=1`;
            iframe.title = 'YouTube · Musik für den Spielabend';
            iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
            iframe.allowFullscreen = true;
            iframe.referrerPolicy = 'strict-origin-when-cross-origin';
            player.replaceChildren(iframe);
            message.textContent = 'Vom Handy gesendet. Falls kein Ton startet, einmal im Player auf Play drücken.';
            music.hidden = false;
        }
        toggle.setAttribute('aria-expanded', String(!music.hidden));
        screen.classList.toggle('tv-with-music', !music.hidden);
        fit();
    }
    try { syncMusic(JSON.parse(screen.dataset.music)); } catch { /* Polling retries the shared state. */ }
    const fullscreen = screen.querySelector('[data-tv-fullscreen]');
    fullscreen.hidden = !document.documentElement.requestFullscreen;
    fullscreen.addEventListener('click', async () => {
        try {
            if (document.fullscreenElement) await document.exitFullscreen();
            else await document.documentElement.requestFullscreen();
            void keepAwake();
        } catch { connection.textContent = 'Vollbild nicht verfügbar · Desktop-Ansicht im Browser verwenden.'; }
    });
    document.addEventListener('fullscreenchange', () => {
        fullscreen.textContent = document.fullscreenElement ? 'Vollbild beenden' : 'Vollbild';
        fit();
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') { void refresh(); void keepAwake(); }
    });
    window.addEventListener('online', refresh);
    window.addEventListener('resize', fit);
    const observer = new ResizeObserver(fit);
    observer.observe(board);
    document.fonts?.ready.then(fit);
    tick();
    fit();
    void keepAwake();
    const clockTimer = setInterval(tick, 1000);
    refreshTimer = setTimeout(refresh, 10000);
    window.addEventListener('pagehide', () => {
        stopped = true;
        clearTimeout(refreshTimer);
        clearInterval(clockTimer);
        observer.disconnect();
        request?.abort();
        void wakeLock?.release();
    });
    window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
}
