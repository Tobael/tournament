import { t } from '../i18n.js';

export const SOUND_CLIPS = Object.freeze({
    'first-blood': { title: 'First Blood', file: '/assets/callouts/first-blood.mp3' },
    'emotional-damage': { title: 'Emotional Damage', file: '/assets/callouts/emotional-damage.mp3' },
    'lotta-damage': { title: 'That’s a Lotta Damage', file: '/assets/callouts/lotta-damage.mp3' },
    bruh: { title: 'Bruh', file: '/assets/callouts/bruh.mp3' },
    fatality: { title: 'Fatality', file: '/assets/callouts/fatality.mp3' },
    wasted: { title: 'Wasted', file: '/assets/callouts/wasted.mp3' },
});

function pick(ids, random, previous) {
    const choices = ids.filter(id => id !== previous);
    return choices[Math.min(choices.length - 1, Math.floor(random() * choices.length))] || ids[0];
}

export function damageCallout(hit, random = Math.random, previous) {
    const pool = hit.lethal ? ['fatality', 'wasted']
        : hit.firstBlood ? ['first-blood']
            : hit.life <= 5 ? ['danger', 'emotional-damage']
                : hit.amount >= 10 ? ['lotta-damage', 'critical', 'emotional-damage']
                    : hit.amount >= 5 ? ['emotional-damage', 'bruh'] : ['bruh', 'emotional-damage'];
    const id = pick(pool, random, previous);
    const values = { name: hit.name, damage: hit.amount, life: hit.life };
    return {
        id, playerId: hit.playerId,
        title: SOUND_CLIPS[id]?.title || (id === 'danger' ? t('Gefahrenzone') : t('Kritischer Treffer')),
        detail: hit.commanderLethal ? t(':name · 21 Commander-Schaden', values)
            : hit.lethal ? t(':name · ausgeschieden', values)
                : t(':name · −:damage Leben · :life übrig', values),
        badge: `−${hit.amount}`,
        sound: SOUND_CLIPS[id] ? id : null,
        tone: hit.lethal ? 'knockout' : hit.amount >= 10 ? 'critical' : 'hit',
    };
}

export function scoreboardTone(change) {
    if (change.kind === 'finished') return 'victory';
    if (change.losses?.length && change.risers?.length) return 'turnaround';
    if (change.losses?.length) return 'defeat';
    if (change.risers?.length) return 'rank-up';
    return 'score';
}

export function scoreboardCallout(change) {
    const riser = change.risers?.[0];
    if (change.kind !== 'finished' && riser) return {
        id: 'rank-up', title: riser.to === 1 ? t('Neue Spitze!') : t('Aufgestiegen!'),
        detail: t(':name · Platz :from → :to', { name: riser.name, from: riser.from, to: riser.to }),
        badge: `↑${riser.from - riser.to}`, sound: null, tone: 'rank-up',
    };
    if (change.kind !== 'finished' && change.losses?.length) return {
        id: 'defeat', title: t('Spiel verloren'), detail: change.losses.map(player => player.name).join(' · '),
        badge: 'KO', sound: null, tone: 'defeat',
    };
    return {
        id: change.kind,
        title: change.kind === 'finished' ? t('Sieg!') : change.kind === 'round' ? t('Neue Runde') : t('Tabelle aktualisiert'),
        detail: change.kind === 'finished' ? (change.leader || t('Geteilter Turniersieg'))
            : change.kind === 'round' ? t('Runde :round · neu mischen, weiter geht’s.', { round: change.round })
                : change.leader ? t(':name führt. Zeit für ein Comeback?', { name: change.leader }) : t('Neuer Stand. Neue Ausreden.'),
        badge: change.kind === 'round' ? String(change.round) : '✦',
        sound: null, tone: change.kind === 'finished' ? 'victory' : 'score',
    };
}
