import test from 'node:test';
import assert from 'node:assert/strict';
import { matchTime } from '../../resources/js/life-timer.js';

test('counter clock supports shared 90-minute deadlines and expiry without negative time', () => {
    assert.equal(matchTime(5400000, 0), '90:00');
    assert.equal(matchTime(60000, 1), '1:00');
    assert.equal(matchTime(60000, 59000), '0:01');
    assert.equal(matchTime(60000, 60000), 'Zeit abgelaufen');
    assert.equal(matchTime(60000, 61000), 'Zeit abgelaufen');
    assert.equal(matchTime(null, 0), null);
});
