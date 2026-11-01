import { subscribe } from './realtime.js';
import { createLifeTransactions } from './life-interactions.js';
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
    let refreshPending = false;
    let stopLife = () => {};
    let lifeInvite;
    let connected = false;
    const lifeStates = new Map();
    const transactions = new Map();
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
        for (const state of lifeStates.values()) applyLife(state);
        syncTransactions();
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
        if (stopped) return;
        if (refreshing) { refreshPending = true; return; }
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
            watchLife(data.invite);
            if (data.title) document.title = data.title;
            if (data.playUrl) screen.querySelector('[data-tv-event-link]').href = data.playUrl;
            connection.textContent = !connected ? 'Live-Verbindung unterbrochen · verbindet erneut' : closed ? 'Endstand · Turnier beendet' : 'Live · WebSocket';
            if (automatic) connection.textContent += ' · Automatische Turnierwahl';
            connection.classList.toggle('tv-offline', !connected);
        } catch {
            if (!stopped) {
                connection.textContent = 'Stand konnte nicht geladen werden · Neu laden oder auf Verbindung warten';
                connection.classList.add('tv-offline');
            }
        } finally {
            clearTimeout(timeout);
            refreshing = false;
            if (!stopped && refreshPending) { refreshPending = false; void refresh(); }
        }
    }
    async function keepAwake() {
        if (!navigator.wakeLock || document.visibilityState !== 'visible' || wakeLock || stopped) return;
        try {
            wakeLock = await navigator.wakeLock.request('screen');
            wakeLock.addEventListener('release', () => { wakeLock = null; });
        } catch { /* Fire TV power settings remain the fallback. */ }
    }
    const videoToggle = screen.querySelector('[data-tv-video-toggle]');
    let videoMode = false;
    try { videoMode = localStorage.getItem('tv-video-mode') === '1'; } catch {}
    function layout() {
        screen.classList.toggle('tv-video-focus', videoMode && !music.hidden);
        videoToggle.setAttribute('aria-pressed', String(videoMode));
        videoToggle.textContent = videoMode ? 'Turnieransicht' : 'Livestream groß';
        fit();
    }
    videoToggle.addEventListener('click', () => {
        videoMode = !videoMode;
        if (videoMode) { music.hidden = false; screen.classList.add('tv-with-music'); toggle.setAttribute('aria-expanded', 'true'); }
        try { localStorage.setItem('tv-video-mode', videoMode ? '1' : '0'); } catch {}
        layout();
    });
    toggle.addEventListener('click', () => {
        music.hidden = !music.hidden;
        toggle.setAttribute('aria-expanded', String(!music.hidden));
        screen.classList.toggle('tv-with-music', !music.hidden);
        layout();
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
        layout();
    }
    try { syncMusic(JSON.parse(screen.dataset.music)); } catch { /* A reconnect reloads the shared state. */ }
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
    function tablePlayers(table) {
        return [...table.querySelectorAll('[data-tv-life-player]')].map(row => ({ id: Number(row.dataset.tvLifePlayer), life: Number(row.querySelector('[data-tv-life-total]').textContent) }));
    }
    function tracker(table) {
        const id = table.dataset.tvLifeMatch;
        if (!transactions.has(id)) transactions.set(id, createLifeTransactions({
            players: tablePlayers(table),
            notify: (player, text) => {
                const output = board.querySelector(`[data-tv-life-match="${id}"] [data-tv-life-player="${player}"] [data-tv-life-transaction]`);
                if (output) output.textContent = text;
            },
        }));
        return transactions.get(id);
    }
    function syncTransactions() {
        const present = new Set();
        for (const table of board.querySelectorAll('[data-tv-life-match]')) {
            present.add(table.dataset.tvLifeMatch);
            const current = tracker(table);
            current.update(tablePlayers(table));
            current.render();
        }
        for (const [id, current] of transactions) if (!present.has(id)) { current.destroy(); transactions.delete(id); }
    }
    function applyLife(state) {
        const table = board.querySelector(`[data-tv-life-match="${state.matchId}"]`);
        if (!table || state.revision < Number(table.dataset.lifeRevision)) return;
        tracker(table).update(state.players, state.operation);
        const score = table.closest('[data-tv-key]')?.querySelector('[data-tv-game-score]');
        if (score) score.textContent = `Spiele ${state.players.map(player => player.wins || 0).join('–')}`;
        table.dataset.lifeRevision = state.revision;
        for (const seat of state.players) {
            const row = table.querySelector(`[data-tv-life-player="${seat.id}"]`);
            if (!row) continue;
            row.querySelector('[data-tv-life-total]').textContent = seat.life;
            const maximum = Math.max(0, ...Object.values(seat.damage || {}));
            const damage = row.querySelector('[data-tv-life-damage]');
            if (damage) damage.textContent = maximum ? `CMDR ${maximum}/21` : '';
            row.classList.toggle('life-lethal', seat.life <= 0 || maximum >= 21);
        }
    }
    function watchLife(invite) {
        if (invite === lifeInvite) return;
        stopLife();
        lifeInvite = invite;
        if (!invite) return;
        lifeStates.clear();
        stopLife = subscribe(`tournament.${invite}`, 'life.changed', ({ state }) => {
            const previous = lifeStates.get(state.matchId);
            if (previous && previous.revision > state.revision) return;
            lifeStates.set(state.matchId, state);
            applyLife(state);
        }, refresh);
    }
    const stopBoard = subscribe('tv-board', 'board.changed', refresh, refresh, value => {
        connected = value;
        connection.textContent = connected ? 'Live · WebSocket' : 'Live-Verbindung unterbrochen · verbindet erneut';
        connection.classList.toggle('tv-offline', !connected);
    });
    syncTransactions();
    watchLife(screen.dataset.invite);
    window.addEventListener('pagehide', () => {
        stopped = true;
        stopBoard();
        stopLife();
        clearInterval(clockTimer);
        for (const current of transactions.values()) current.destroy();
        transactions.clear();
        observer.disconnect();
        request?.abort();
        void wakeLock?.release();
    });
    window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
}
