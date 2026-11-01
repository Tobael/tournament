export function acceptsMatchState(current, next) {
    if (next.matchId !== current.matchId || next.revision < current.revision) return false;
    // An HTTP response can arrive after a newer WebSocket completion or closure.
    if ((current.completed && !next.completed) || (!current.editable && next.editable)) return false;
    return true;
}

export function leaveInactiveMatch(root, state, navigate = url => window.location.replace(url)) {
    if (state.editable && !state.completed) return false;
    if (!root.hidden) {
        root.hidden = true;
        navigate(state.returnUrl);
    }
    return true;
}
