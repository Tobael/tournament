import Pusher from 'pusher-js';

let client;
const subscriptions = new Map();
export function realtime() {
    if (client) return client;
    const key = document.querySelector('meta[name="reverb-key"]')?.content;
    if (!key) return null;
    const port = Number(document.querySelector('meta[name="reverb-port"]')?.content) || Number(location.port) || (location.protocol === 'https:' ? 443 : 80);
    client = new Pusher(key, {
        cluster: 'mtg', wsHost: location.hostname, wsPort: port, wssPort: port,
        forceTLS: location.protocol === 'https:', enabledTransports: ['ws', 'wss'],
        disableStats: true,
    });
    return client;
}

export function subscribe(channelName, eventName, changed, ready = () => {}, status = () => {}) {
    const socket = realtime();
    if (!socket) { status(false); return () => {}; }
    const channel = socket.subscribe(channelName);
    subscriptions.set(channelName, (subscriptions.get(channelName) || 0) + 1);
    const connectionChanged = ({ current }) => status(current === 'connected');
    channel.bind(eventName, changed);
    channel.bind('pusher:subscription_succeeded', ready);
    socket.connection.bind('state_change', connectionChanged);
    status(socket.connection.state === 'connected');
    if (channel.subscribed) ready();
    let active = true;
    return () => {
        if (!active) return;
        active = false;
        channel.unbind(eventName, changed);
        channel.unbind('pusher:subscription_succeeded', ready);
        socket.connection.unbind('state_change', connectionChanged);
        const remaining = subscriptions.get(channelName) - 1;
        if (remaining) subscriptions.set(channelName, remaining);
        else { subscriptions.delete(channelName); socket.unsubscribe(channelName); }
    };
}

const rounds = new WeakMap();
export function setupRoundRealtime() {
    const root = document.querySelector('[data-round-realtime]');
    if (!root || rounds.has(root)) return;
    let busy = false;
    let pending = false;
    let connected = false;
    const refresh = async () => {
        if (!root.isConnected) return;
        if (busy) { pending = true; return; }
        busy = true;
        try { await window.Livewire?.find(root.getAttribute('wire:id'))?.call('refresh'); }
        finally { busy = false; status(connected); if (pending) { pending = false; void refresh(); } }
    };
    const status = value => {
        connected = value;
        const label = root.querySelector('[data-round-connection]');
        if (label) label.textContent = connected ? 'Live · WebSocket' : 'Live-Verbindung unterbrochen · verbindet erneut';
    };
    const stop = subscribe('tv-board', 'board.changed', refresh, refresh, status);
    const cleanup = () => { stop(); rounds.delete(root); };
    rounds.set(root, cleanup);
    document.addEventListener('livewire:navigating', cleanup, { once: true });
    window.addEventListener('pagehide', cleanup, { once: true });
}
