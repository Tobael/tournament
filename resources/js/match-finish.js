export function survivingPlayer(state) {
    if (!state.editable || state.players.length < 2) return null;
    const alive = state.players.filter(player => player.life > 0 && (state.format !== 'commander' || !Object.values(player.damage || {}).some(value => value >= 21)));
    return alive.length === 1 ? alive[0] : null;
}

export function createWinnerNotice() {
    let dismissed;
    return {
        candidate(state) {
            const winner = survivingPlayer(state);
            if (!winner) { dismissed = null; return null; }
            return `${state.gameNumber || 1}:${winner.id}` === dismissed ? null : winner;
        },
        dismiss(id, game = 1) { dismissed = `${game}:${id}`; },
        reopen() { dismissed = null; },
    };
}

export function setupMatchFinish(root, { getState, isBusy, send }) {
    if (root.dataset.canControl !== '1') return () => {};
    const dialog = root.querySelector('[data-life-finish]');
    const form = dialog.querySelector('form');
    const submit = dialog.querySelector('[data-life-finish-confirm]');
    const reopen = root.querySelector('[data-life-finish-reopen]');
    const cancel = dialog.querySelector('[data-life-finish-cancel]');
    const message = dialog.querySelector('[data-life-finish-error]');
    const notice = createWinnerNotice();
    let winnerId;
    let saving = false;
    let timer;
    function check() {
        const state = getState();
        const survivor = survivingPlayer(state);
        reopen.hidden = !survivor;
        if (dialog.open && (!survivor || survivor.id !== winnerId)) dialog.close();
        const winner = notice.candidate(state);
        if (saving || isBusy() || !winner || dialog.open || root.querySelector('dialog[open]')) return;
        winnerId = winner.id;
        dialog.querySelector('[data-life-winner-name]').textContent = winner.name;
        const description = dialog.querySelector('[data-life-win-description]');
        if (state.bestOf === 3) {
            const next = state.players.map(player => (player.wins || 0) + (player.id === winnerId ? 1 : 0));
            description.textContent = next.some(wins => wins >= 2)
                ? `Matchgewinn mit ${next.join('–')}. Danach zurück zum Turnier.`
                : `Spielstand danach: ${next.join('–')}. Das nächste Spiel startet mit 20 Leben.`;
        } else description.textContent = 'Der Sieg wird für diesen Tisch gespeichert.';
        message.textContent = '';
        submit.disabled = false;
        dialog.showModal();
    }
    const schedule = () => { clearTimeout(timer); timer = setTimeout(check, 0); };
    function dismiss() { notice.dismiss(winnerId, getState().gameNumber || 1); dialog.close(); }
    const escape = event => { event.preventDefault(); dismiss(); };
    const retry = () => { notice.reopen(); schedule(); };
    async function finish(event) {
        event.preventDefault();
        if (saving || !form.reportValidity()) return;
        const state = getState();
        if (survivingPlayer(state)?.id !== winnerId) { dialog.close(); schedule(); return; }
        saving = true;
        submit.disabled = true;
        const saved = await send({ action: 'finish', winner: winnerId, revision: state.revision });
        saving = false;
        if (saved) {
            dialog.close(); notice.reopen(); schedule();
            return;
        }
        message.textContent = 'Sieg nicht bestätigt. Stand prüfen und erneut versuchen.';
        submit.disabled = false;
        schedule();
    }
    root.addEventListener('life:changed', schedule);
    root.addEventListener('life:idle', schedule);
    root.addEventListener('close', schedule, true);
    cancel.addEventListener('click', dismiss);
    dialog.addEventListener('cancel', escape);
    reopen.addEventListener('click', retry);
    form.addEventListener('submit', finish);
    schedule();
    return () => {
        clearTimeout(timer);
        root.removeEventListener('life:changed', schedule);
        root.removeEventListener('life:idle', schedule);
        root.removeEventListener('close', schedule, true);
        cancel.removeEventListener('click', dismiss);
        dialog.removeEventListener('cancel', escape);
        reopen.removeEventListener('click', retry);
        form.removeEventListener('submit', finish);
        if (dialog.open) dialog.close();
    };
}
