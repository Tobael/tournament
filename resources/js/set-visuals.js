// One request per set per navigation session, including Livewire remounts.
const artworkRequests = new Map();
const initialized = new WeakSet();

export function setupSetVisuals(root = document) {
    for (const figure of root.querySelectorAll('[data-set-visual]')) {
        if (initialized.has(figure)) continue;
        initialized.add(figure);
        const symbol = figure.querySelector('[data-set-symbol]');
        const showSymbol = () => {
            symbol.hidden = false;
            figure.querySelector('[data-set-symbol-fallback]').hidden = true;
        };
        symbol.addEventListener('load', showSymbol, { once: true });
        if (symbol.complete && symbol.naturalWidth > 0) showSymbol();
        const url = figure.dataset.artworkUrl;
        if (!url) continue;
        if (!artworkRequests.has(url)) {
            artworkRequests.set(url, fetch(url, { headers: { Accept: 'application/json' } })
                .then(response => response.ok ? response.json() : null).catch(() => null));
        }
        void artworkRequests.get(url).then(artwork => {
            if (!figure.isConnected || !artwork?.image) return;
            const image = figure.querySelector('[data-set-art-image]');
            image.addEventListener('load', () => {
                figure.querySelector('[data-set-art]').hidden = false;
                const caption = figure.querySelector('[data-set-caption]');
                if (caption) caption.hidden = false;
            }, { once: true });
            figure.querySelector('[data-set-credit]').textContent = `Illustration: ${artwork.artist} · ${artwork.card}`;
            const source = figure.querySelector('[data-set-source]');
            source.href = artwork.source;
            image.src = artwork.image;
        });
    }
}
