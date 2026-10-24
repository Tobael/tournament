const initialized = new WeakSet();
const catalogs = new Map();
const euros = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' });

export function formatPrice(value) {
    return typeof value === 'number' && Number.isFinite(value) ? euros.format(value) : 'Kein EUR-Preis';
}

export function cardElement(card, { finish = 'normal', lookup = false } = {}, documentApi = document) {
    const article = documentApi.createElement('article');
    article.className = 'valued-card';
    const link = documentApi.createElement('a');
    link.href = card.source;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    const image = documentApi.createElement('img');
    image.src = card.image;
    image.alt = card.name;
    image.loading = 'lazy';
    image.decoding = 'async';
    link.append(image);
    const text = documentApi.createElement('div');
    const name = documentApi.createElement('strong');
    name.textContent = card.name;
    const number = documentApi.createElement('p');
    number.className = 'muted';
    number.textContent = `${card.set.toUpperCase()} · #${card.number}${card.set === 'spg' ? ' · Special Guests' : ''}`;
    const price = documentApi.createElement('p');
    price.className = 'card-value';
    price.textContent = lookup ? `Normal: ${formatPrice(card.eur)}` : formatPrice(finish === 'foil' ? card.eur_foil : card.eur);
    text.append(name, number, price);
    if (lookup) {
        const foil = documentApi.createElement('p');
        foil.className = 'card-value foil-value';
        foil.textContent = `Foil: ${formatPrice(card.eur_foil)}`;
        text.append(foil);
    }
    article.append(link, text);
    return article;
}

export async function lookupCard(url, { subset, number }, fetchApi = fetch) {
    const query = new URLSearchParams({ subset, number: number.trim().replace(/^#/, '').split('/')[0].trim() });
    const response = await fetchApi(`${url}?${query}`, { headers: { Accept: 'application/json' } });
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || 'Karte gerade nicht abrufbar. Bitte erneut versuchen.');
    return result;
}

export function setupSetCardTools(root = document) {
    for (const section of root.querySelectorAll('[data-set-card-tools]')) {
        if (initialized.has(section)) continue;
        initialized.add(section);
        const form = section.querySelector('[data-card-search]');
        const status = section.querySelector('[data-card-search-status]');
        const result = section.querySelector('[data-card-search-result]');
        let requestId = 0;
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const currentId = ++requestId;
            const values = new FormData(form);
            status.textContent = 'Karte wird gesucht …';
            result.hidden = true;
            try {
                const data = await lookupCard(section.dataset.lookupUrl, { subset: values.get('subset'), number: values.get('number') });
                if (!section.isConnected || currentId !== requestId) return;
                result.replaceChildren(cardElement(data.card, { lookup: true }));
                result.hidden = false;
                status.textContent = 'Gefunden · '+new Date(data.updated_at).toLocaleString('de-DE');
            } catch (error) {
                if (section.isConnected && currentId === requestId) status.textContent = error.message;
            }
        });
        const url = section.dataset.topUrl;
        if (!catalogs.has(url)) catalogs.set(url, fetch(url, { headers: { Accept: 'application/json' } }).then(response => {
            if (!response.ok) throw new Error('Kartenwerte gerade nicht erreichbar.');
            return response.json();
        }).catch(() => null));
        void catalogs.get(url).then(data => {
            if (!section.isConnected) return;
            const message = section.querySelector('[data-pull-status]');
            if (!data || data.unavailable) {
                message.textContent = 'Top Pulls gerade nicht erreichbar. Die Kartennummernsuche kannst du trotzdem nutzen.';
                return;
            }
            const select = section.querySelector('[data-card-subset]');
            for (const set of data.sets) {
                if ([...select.options].some(option => option.value === set.code)) continue;
                const option = document.createElement('option');
                option.value = set.code;
                option.textContent = `${set.name} (${set.code.toUpperCase()})`;
                select.append(option);
            }
            section.querySelector('[data-pull-scope]').textContent = 'Berücksichtigt: '+data.sets.map(set => set.name).join(' · ');
            section.querySelector('[data-price-updated]').textContent = 'Stand: '+new Date(data.updated_at).toLocaleString('de-DE');
            const finish = section.querySelector('[data-pull-finish]');
            const grid = section.querySelector('[data-pull-grid]');
            const render = () => {
                const cards = data[finish.value] || [];
                grid.replaceChildren(...cards.map(card => cardElement(card, { finish: finish.value })));
                message.textContent = cards.length ? '' : 'Für diese Ausführung sind noch keine EUR-Preise verfügbar.';
            };
            finish.addEventListener('change', render);
            render();
        });
    }
}
