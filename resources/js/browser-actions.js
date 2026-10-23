export async function copyLink(input, button, message, browser = globalThis) {
    const url = input.value;
    let copied = false;
    try {
        if (browser.navigator.clipboard?.writeText) {
            await browser.navigator.clipboard.writeText(url);
            copied = true;
        }
    } catch { /* Some mobile browsers deny clipboard access. */ }

    if (!copied) {
        input.focus();
        input.select();
        input.setSelectionRange(0, url.length);
        try { copied = browser.document.execCommand('copy') === true; } catch { /* Keep the link selected. */ }
    }
    button.textContent = copied ? '✓ Kopiert' : 'Link kopieren';
    message.textContent = copied ? 'Link kopiert.' : 'Der Link ist markiert. Halte das Feld gedrückt und wähle „Kopieren“.';
    return copied;
}

export async function shareLink(input, button, message, browser = globalThis) {
    if (!browser.navigator.share) return copyLink(input, button, message, browser);
    try {
        await browser.navigator.share({ title: 'Spielrunde', url: input.value });
        message.textContent = 'Link geteilt.';
    } catch (error) {
        if (error.name !== 'AbortError') await copyLink(input, button, message, browser);
    }
}

export const deviceStorageKey = 'spielrunde.participants.v1';
const validInvite = value => typeof value === 'string' && /^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/i.test(value);
const validIdentity = item => item && validInvite(item.invite) && typeof item.token === 'string' && /^[a-zA-Z0-9]{64}$/.test(item.token);

export function readParticipants(storage) {
    try {
        const items = JSON.parse(storage.getItem(deviceStorageKey) || '[]');
        return Array.isArray(items) ? items.filter(validIdentity).slice(0, 8) : [];
    } catch { return []; }
}

export function rememberParticipant(storage, identity) {
    if (!validIdentity(identity)) return false;
    try {
        const items = [identity, ...readParticipants(storage).filter(item => item.invite !== identity.invite)].slice(0, 8);
        storage.setItem(deviceStorageKey, JSON.stringify(items));
        return true;
    } catch { return false; }
}

export function forgetParticipant(storage, invite) {
    try { storage.setItem(deviceStorageKey, JSON.stringify(readParticipants(storage).filter(item => item.invite !== invite))); } catch { /* Storage may be disabled. */ }
}

export async function restoreParticipant(identity, csrf, fetcher = globalThis.fetch) {
    const response = await fetcher('/return', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ token: identity.token }),
    });
    if ([404, 422].includes(response.status)) return { expired: true };
    if (!response.ok) throw new Error('Die Teilnahme konnte gerade nicht wiederhergestellt werden.');
    const data = await response.json();
    // Never navigate to a URL supplied by local storage or an unexpected event.
    const url = new URL(data.url, globalThis.location?.origin || 'https://spielrunde.invalid');
    if (url.pathname !== `/play/${identity.invite}` || url.search || url.hash) return { expired: true };
    if (globalThis.location && url.origin !== globalThis.location.origin) return { expired: true };
    return { path: url.pathname };
}
