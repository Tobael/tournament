import test from 'node:test';
import assert from 'node:assert/strict';
import { lifeLayout } from '../../resources/js/life-layout.js';

for (const [width, height] of [[360, 700], [700, 360], [1024, 768]]) {
    test(`two to five seats fill the table without overlaps at ${width}×${height}`, () => {
        for (const count of [2, 3, 4, 5]) {
            const seats = lifeLayout(count, width, height);
            assert.equal(seats.length, count);
            let area = 0;
            for (const seat of seats) {
                assert.ok(seat.x >= -1e-9 && seat.y >= -1e-9);
                assert.ok(seat.x + seat.width <= 1 + 1e-9 && seat.y + seat.height <= 1 + 1e-9);
                assert.equal(Math.abs(seat.angle % 90), 0);
                area += seat.width * seat.height;
            }
            assert.ok(Math.abs(area - 1) < 1e-9);
            for (let a = 0; a < seats.length; a++) for (let b = a + 1; b < seats.length; b++) {
                const xOverlap = Math.min(seats[a].x + seats[a].width, seats[b].x + seats[b].width) - Math.max(seats[a].x, seats[b].x);
                const yOverlap = Math.min(seats[a].y + seats[a].height, seats[b].y + seats[b].height) - Math.max(seats[a].y, seats[b].y);
                assert.ok(xOverlap < 1e-9 || yOverlap < 1e-9);
            }
        }
    });
}

test('opposite players face their screen edge in portrait and landscape', () => {
    assert.deepEqual(lifeLayout(2, 360, 700).map(s => s.angle), [180, 0]);
    assert.deepEqual(lifeLayout(2, 700, 360).map(s => s.angle), [90, -90]);
    assert.deepEqual(lifeLayout(4, 360, 700).map(s => s.angle), [90, -90, -90, 90]);
});
