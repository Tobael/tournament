import { copyLink, shareLink, readParticipants, rememberParticipant, forgetParticipant, restoreParticipant } from './browser-actions';

// Delegation survives Livewire polling and page navigation without stale URLs.
document.addEventListener('click', event => {
    const button = event.target.closest('[data-copy-link], [data-share-link-button]');
    if (!button) return;
    const container = button.closest('[data-share-link]');
    const input = container.querySelector('[data-share-url]');
    const message = container.querySelector('[data-copy-message]');
    if (button.hasAttribute('data-copy-link')) void copyLink(input, button, message);
    else void shareLink(input, button, message);
});

async function setupDeviceReturn() {
    let storage;
    try { storage = window.localStorage; } catch { return; }
    const event = document.querySelector('[data-device-participant]');
    if (event && !event.dataset.initialized) {
        event.dataset.initialized = 'true';
        const { invite, token, title, name, active, participating } = event.dataset;
        if (participating === '1') {
            if (token && rememberParticipant(storage, { invite, token, title, name, active: active === '1' })) {
                event.querySelector('[data-device-message]').textContent = 'Teilnahme auf diesem Gerät gespeichert.';
            }
        } else {
            const identity = readParticipants(storage).find(item => item.invite === invite);
            if (identity) {
                const message = event.querySelector('[data-device-message]');
                message.textContent = 'Deine gespeicherte Teilnahme wird wiederhergestellt …';
                try {
                    const result = await restoreParticipant(identity, document.querySelector('meta[name="csrf-token"]').content);
                    if (!event.isConnected) return;
                    if (result.expired) {
                        forgetParticipant(storage, invite);
                        message.textContent = 'Dein gespeicherter Rückkehr-Link ist nicht mehr gültig. Nutze einen neuen Link oder melde dich an.';
                    } else window.location.replace(result.path);
                } catch {
                    if (event.isConnected) message.textContent = 'Automatische Rückkehr gerade nicht möglich. Lade die Seite erneut oder nutze deinen Rückkehr-Link.';
                }
            }
        }
    }
    const home = document.querySelector('[data-device-home]');
    if (home && !home.dataset.initialized) {
        home.dataset.initialized = 'true';
        const list = home.querySelector('[data-saved-events]');
        for (const identity of readParticipants(storage)) {
            const link = document.createElement('a');
            link.className = 'utility-button';
            link.href = `/play/${identity.invite}`;
            link.textContent = `${identity.title || 'Spielrunde'} · Weiter als ${identity.name || 'Mitspieler'}`;
            list.append(link);
        }
        home.hidden = !list.children.length;
        const latest = readParticipants(storage).find(identity => identity.active !== false);
        if (home.dataset.autoReturn === '1' && latest) {
            try {
                const path = `/play/${latest.invite}`;
                const response = await fetch(path, { method: 'HEAD', credentials: 'same-origin' });
                if (!home.isConnected) return;
                if (response.ok) window.location.replace(path);
                else if (response.status === 404) forgetParticipant(storage, latest.invite);
            } catch { /* Saved links remain available when the connection is unreliable. */ }
        }
    }
}

document.addEventListener('livewire:navigated', setupDeviceReturn);
document.addEventListener('DOMContentLoaded', setupDeviceReturn);
void setupDeviceReturn();
