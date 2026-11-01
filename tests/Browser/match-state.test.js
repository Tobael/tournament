import test from 'node:test';
import assert from 'node:assert/strict';
import { acceptsMatchState, leaveInactiveMatch } from '../../resources/js/match-state.js';

const active = { matchId: 4, revision: 7, editable: true, completed: false, returnUrl: '/play/event#my-match' };

test('late responses cannot revive a completed match or a counter closed by a round or tournament change', () => {
    assert.equal(acceptsMatchState(active, { ...active, matchId: 5 }), false);
    assert.equal(acceptsMatchState(active, { ...active, revision: 6 }), false);
    const finished = { ...active, completed: true, editable: false, revision: 8 };
    assert.equal(acceptsMatchState(active, finished), true);
    assert.equal(acceptsMatchState(finished, { ...active, revision: 8 }), false);
    const inactive = { ...active, editable: false };
    assert.equal(acceptsMatchState(active, inactive), true);
    assert.equal(acceptsMatchState(inactive, active), false);
});

test('participants and spectators leave inactive counters once, hiding them before navigating', () => {
    for (const canControl of ['0', '1']) {
        for (const completed of [false, true]) {
            const root = { hidden: false, dataset: { canControl } };
            const visits = [];
            const navigate = url => { assert.equal(root.hidden, true); visits.push(url); };
            const inactive = { ...active, editable: false, completed };
            assert.equal(leaveInactiveMatch(root, inactive, navigate), true);
            leaveInactiveMatch(root, inactive, navigate);
            assert.deepEqual(visits, [active.returnUrl]);
        }
    }
});

test('an intermediate best-of-three game keeps the counter open', () => {
    const nextGame = { ...active, revision: 8, gameNumber: 2, operation: 'game-win' };
    assert.equal(acceptsMatchState(active, nextGame), true);
    const root = { hidden: false };
    assert.equal(leaveInactiveMatch(root, nextGame, () => assert.fail('Must stay in counter')), false);
    assert.equal(root.hidden, false);
});
