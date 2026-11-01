import test from 'node:test';
import assert from 'node:assert/strict';
import { survivingPlayer, createWinnerNotice } from '../../resources/js/match-finish.js';

const state = lives => ({ editable: true, players: lives.map((life, index) => ({ id: index + 1, name: `Seat ${index + 1}`, life })) });

test('a winner is suggested only when exactly one player has positive life', () => {
    assert.equal(survivingPlayer(state([0, 9, -3, 0])).id, 2);
    for (const lives of [[0, 0], [20, 20], [20], [], [-1, 0]]) assert.equal(survivingPlayer(state(lives)), null);
    assert.equal(survivingPlayer({ ...state([20, 0]), editable: false }), null);
});

test('declining does not repeatedly prompt on more taps, but revival or a different survivor resets the prompt', () => {
    const notice = createWinnerNotice();
    assert.equal(notice.candidate(state([4, 0])).id, 1);
    notice.dismiss(1);
    assert.equal(notice.candidate(state([3, -1])), null);
    assert.equal(notice.candidate(state([0, 3])).id, 2);
    assert.equal(notice.candidate(state([3, 2])), null);
    assert.equal(notice.candidate(state([3, 0])).id, 1);
    notice.dismiss(1);
    notice.reopen();
    assert.equal(notice.candidate(state([3, 0])).id, 1);
});

test('a new best-of-three game prompts again even if the same player survives', () => {
    const notice = createWinnerNotice();
    notice.dismiss(1, 1);
    assert.equal(notice.candidate({ ...state([4, 0]), gameNumber: 1 }), null);
    assert.equal(notice.candidate({ ...state([4, 0]), gameNumber: 2 }).id, 1);
});

test('commander damage elimination counts as out even while life remains positive', () => {
    const game = { ...state([10, 15, 30]), format: 'commander' };
    game.players[1].damage = { 1: 21 };
    game.players[2].damage = { 1: 22 };
    assert.equal(survivingPlayer(game).id, 1);
});
