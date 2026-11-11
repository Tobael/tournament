import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// Render the real Blade templates against an in-memory database; no user records are touched.
const fixtures = JSON.parse(execFileSync('php', ['tests/E2E/fixtures/commentary.php'], { encoding: 'utf8' }));

async function prepare(page, kind = 'life', lang = 'de', audioSupport = true, initialLag = 0) {
    page.on('pageerror', error => console.error('PAGE ERROR:', error.message));
    await page.route('https://**/*', route => route.abort());
    await page.addInitScript(({ audioSupport }) => {
        Math.random = () => 0;
        window.testSpeech = 0;
        window.testSamples = 0;
        window.testTones = 0;
        Object.defineProperty(Element.prototype, 'requestFullscreen', { value: undefined });
        Object.defineProperty(window, 'speechSynthesis', { get() { window.testSpeech++; throw new Error('Speech must never be used'); } });
        if (!audioSupport) { window.AudioContext = undefined; window.webkitAudioContext = undefined; return; }
        const originalTone = AudioContext.prototype.createOscillator;
        AudioContext.prototype.createOscillator = function () { window.testTones++; return originalTone.call(this); };
        const originalSample = AudioContext.prototype.createBufferSource;
        AudioContext.prototype.createBufferSource = function () { window.testSamples++; return originalSample.call(this); };
    }, { audioSupport });
    let state = { ...structuredClone(fixtures.state), serverTime: Date.now() - initialLag };
    const freshPage = fixtures[`${kind}-${lang}`].replaceAll(String(fixtures.state.serverTime), String(state.serverTime));
    let sockets = [];
    let delay = 0;
    let failNext = false;
    const writes = [];
    const channels = new Set();
    await page.routeWebSocket(/\/app\//, ws => {
        sockets.push(ws);
        ws.onMessage(message => {
            const event = JSON.parse(message);
            if (event.event === 'pusher:subscribe') {
                channels.add(event.data.channel);
                ws.send(JSON.stringify({ event: 'pusher_internal:subscription_succeeded', channel: event.data.channel, data: '{}' }));
            }
        });
        ws.send(JSON.stringify({ event: 'pusher:connection_established', data: JSON.stringify({ socket_id: '123.456', activity_timeout: 120 }) }));
    });
    await page.route('**/commentary-fixture', route => route.fulfill({ contentType: 'text/html', body: freshPage }));
    await page.route('**/life/*/state', route => route.fulfill({ json: state }));
    await page.route('**/life/*', async route => {
        if (route.request().method() !== 'POST') return route.continue();
        const command = route.request().postDataJSON();
        writes.push(command);
        if (delay) await new Promise(resolve => setTimeout(resolve, delay));
        if (failNext) { failNext = false; return route.fulfill({ status: 500, json: { message: 'Test failure' } }); }
        state = { ...state, revision: state.revision + 1, operation: command.action, operationId: command.operation_id, serverTime: Date.now() };
        const player = state.players.find(player => player.id === command.player);
        if (command.action === 'life') player.life += command.delta;
        if (command.action === 'set-life') player.life = command.value;
        await route.fulfill({ json: state });
    });
    let html = fixtures[`tv-${lang}`].match(/<div class="tv-board" data-tv-board>([\s\S]*?)<\/div>\s*<aside id="tv-music"/)[1];
    const data = () => ({ html, invite: new URL(state.returnUrl).pathname.split('/')[2], closed: false, serverTime: Date.now(), music: { revision: null, url: null } });
    await page.route('**/tv/state', route => route.fulfill({ json: data() }));
    await page.goto('/commentary-fixture');
    await expect.poll(() => channels.size).toBeGreaterThan(0);
    await expect(page.locator('[data-life-connection], [data-tv-connection]')).toBeAttached();
    // Allow subscription snapshots to establish their baseline before making live changes.
    await page.waitForTimeout(250);
    await page.evaluate(() => { Math.random = () => 0; });
    return {
        state: () => structuredClone(state), writes, latency(ms) { delay = ms; }, fail() { failNext = true; },
        emit(event, channel, payload) { for (const socket of sockets) socket.send(JSON.stringify({ event, channel, data: JSON.stringify(payload) })); },
        board(htmlValue) { html = htmlValue; },
    };
}

const samples = page => page.evaluate(() => window.testSamples);
const speechCount = page => page.evaluate(() => window.testSpeech);

test('damage shows a brief callout with grouped taps and a recorded clip', async ({ page }) => {
    const live = await prepare(page);
    await page.getByRole('button', { name: 'Ton aktivieren', exact: true }).click();
    const minus = page.locator('[data-life-delta="-1"]').first();
    for (let i = 0; i < 5; i++) await minus.click();
    await expect(page.locator('[data-callout-title]')).toHaveText('First Blood');
    await expect(page.locator('[data-commentary-text]')).toContainText('Leben');
    await expect(page.locator('[data-life-total]').first()).toHaveText('35');
    await expect.poll(() => samples(page)).toBe(1);
    await page.screenshot({ path: '/tmp/table-callout-life.png', fullPage: true });
    const state = live.state();
    live.emit('life.changed', `tournament.${new URL(state.returnUrl).pathname.split('/')[2]}`, { state });
    await expect(page.locator('[data-callout]')).toBeHidden({ timeout: 3500 });
    expect(await samples(page)).toBe(1);
    expect(await speechCount(page)).toBe(0);
});

test('mute persists with visual callouts; recorded samples can be previewed in English', async ({ page }) => {
    await prepare(page, 'life', 'en');
    await page.getByRole('button', { name: 'Enable sound', exact: true }).click();
    await page.getByRole('button', { name: 'Sound on', exact: true }).click();
    await page.locator('[data-life-delta="-1"]').first().click();
    await expect(page.locator('[data-commentary-text]')).toContainText('−1 life');
    expect(await samples(page)).toBe(0);
    await page.reload();
    await expect(page.getByRole('button', { name: 'Sound off', exact: true })).toBeVisible();
    await page.locator('.commentary-settings summary').click();
    await page.locator('[data-commentary-sample]').selectOption('bruh');
    await page.getByRole('button', { name: 'Test sound', exact: true }).click();
    await expect.poll(() => samples(page)).toBe(1);
    await expect(page.locator('[data-callout-title]')).toHaveText('Bruh');
    expect(await speechCount(page)).toBe(0);
    await expect(page.locator('[data-commentary-voice], [data-commentary-speech]')).toHaveCount(0);
});

test('TV keeps its result chime, shows a popup, and ignores duplicate invalidations and quiet refreshes', async ({ page }) => {
    const live = await prepare(page, 'tv');
    await page.getByRole('button', { name: 'Ton aktivieren', exact: true }).click();
    await expect.poll(() => page.evaluate(() => window.testTones)).toBe(2);
    const html = await page.locator('[data-tv-board]').evaluate(board => {
        const first = board.querySelector('[data-tv-standing]');
        first.dataset.points = '3'; first.dataset.games = '1'; first.dataset.place = '1';
        for (const other of [...board.querySelectorAll('[data-tv-standing]')].slice(1)) other.dataset.place = '2';
        first.querySelector('b').textContent = '3';
        return board.innerHTML;
    });
    live.board(html); live.emit('board.changed', 'tv-board', {});
    await expect.poll(() => page.evaluate(() => window.testTones)).toBe(4);
    await expect(page.locator('[data-callout-title]')).toHaveText('Tabelle aktualisiert');
    live.emit('board.changed', 'tv-board', {});
    await page.waitForTimeout(300);
    expect(await page.evaluate(() => window.testTones)).toBe(4);
    expect(await speechCount(page)).toBe(0);
    await page.screenshot({ path: '/tmp/table-callout-tv.png', fullPage: true });
    live.board(html.replace('data-points="3"', 'data-points="6"'));
    await page.evaluate(() => window.dispatchEvent(new Event('online')));
    await page.waitForTimeout(300);
    expect(await page.evaluate(() => window.testTones)).toBe(4);
});

for (const [width, height] of [[375, 812], [812, 375]]) {
    test(`popups do not block life controls at ${width} by ${height}, even without audio`, async ({ page }) => {
        await page.setViewportSize({ width, height });
        await prepare(page, 'life', 'de', false);
        const start = page.locator('[data-life-start-button]');
        if (await start.isVisible()) await start.click();
        const minus = page.locator('[data-life-delta="-1"]').first();
        await minus.click();
        await expect(page.locator('[data-callout-title]')).toHaveText('First Blood');
        const bounds = await page.locator('[data-callout]').boundingBox();
        expect(bounds.x).toBeGreaterThanOrEqual(0); expect(bounds.x + bounds.width).toBeLessThanOrEqual(width);
        await minus.click();
        await expect(page.locator('[data-life-total]').first()).toHaveText('38');
        await page.screenshot({ path: `/tmp/table-callout-${width}.png`, fullPage: true });
        await page.locator('.commentary-settings summary').click();
        await expect(page.locator('[data-commentary-popups]')).toBeChecked();
        const settings = await page.locator('.commentary-settings-panel').boundingBox();
        expect(settings.x).toBeGreaterThanOrEqual(0); expect(settings.x + settings.width).toBeLessThanOrEqual(width);
        expect(settings.y + settings.height).toBeLessThanOrEqual(height);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        expect(await speechCount(page)).toBe(0);
    });
}

test('reduced motion uses a static callout and visual effects can be disabled independently', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await prepare(page);
    await page.locator('[data-life-delta="-1"]').first().click();
    await expect(page.locator('[data-callout]')).toBeVisible();
    expect(await page.locator('.callout-card').evaluate(node => getComputedStyle(node).animationName)).toBe('none');
    await page.locator('.commentary-settings summary').click();
    await page.locator('[data-commentary-popups]').uncheck();
    await expect(page.locator('[data-callout]')).toBeHidden();
    const before = await samples(page);
    await page.getByRole('button', { name: 'Sound testen', exact: true }).click();
    await expect.poll(() => samples(page)).toBe(before + 1);
    await expect(page.locator('[data-callout]')).toBeHidden();
});

test('a failed sample download falls back to a short arcade effect and never breaks the popup', async ({ page }) => {
    await page.route('**/assets/callouts/**', route => route.abort());
    await prepare(page);
    await page.locator('.commentary-settings summary').click();
    await page.getByRole('button', { name: 'Sound testen', exact: true }).click();
    await expect(page.locator('[data-callout-title]')).toHaveText('First Blood');
    await expect.poll(() => page.evaluate(() => window.testTones)).toBe(2);
    expect(await samples(page)).toBe(0); expect(await speechCount(page)).toBe(0);
});


test('the life counter has no banner ad or reserved ad slot', async ({ page }) => {
    await prepare(page);
    await expect(page.locator('[data-trash-banner], [data-banner-next], .trash-banner')).toHaveCount(0);
    await expect(page.locator('[data-callout]')).toBeHidden();
});


test('default first-tap audio survives slow initial delivery and unchanged resyncs without opening the sound menu', async ({ page }) => {
    await prepare(page, 'life', 'de', true, 20000);
    await page.locator('[data-life-delta="-1"]').first().click();
    await page.evaluate(() => window.dispatchEvent(new Event('online')));
    await expect.poll(() => samples(page), { timeout: 2000 }).toBe(1);
    await expect(page.locator('[data-callout-title]')).toHaveText('First Blood');
    expect(await speechCount(page)).toBe(0);
});

test('life paints immediately under server latency, batches rapid taps, and settles to the server total', async ({ page }) => {
    const live = await prepare(page); live.latency(1000);
    const minus = page.locator('[data-life-delta="-1"]').first();
    for (let n = 0; n < 6; n++) await minus.click();
    await expect(page.locator('[data-life-total]').first()).toHaveText('34', { timeout: 200 });
    expect(live.state().players[0].life).toBe(40);
    await expect(page.locator('[data-life-counter]')).toHaveAttribute('data-pending', '0', { timeout: 5000 });
    expect(live.state().players[0].life).toBe(34);
    expect(live.writes).toHaveLength(2); expect(live.writes[1].delta).toBe(-5);
});

test('a rejected optimistic change rolls back, clears queued taps and shows an error', async ({ page }) => {
    const live = await prepare(page); live.latency(600); live.fail();
    const minus = page.locator('[data-life-delta="-1"]').first();
    await minus.click(); await minus.click();
    await expect(page.locator('[data-life-total]').first()).toHaveText('38', { timeout: 200 });
    await expect(page.locator('[data-life-error]')).toBeVisible();
    await expect(page.locator('[data-life-total]').first()).toHaveText('40');
    expect(live.writes).toHaveLength(1); expect(await samples(page)).toBe(0);
});

test('holding a button continues to paint while the first network request is still pending', async ({ page }) => {
    const live = await prepare(page); live.latency(1500);
    const button = page.locator('[data-life-delta="-1"]').first();
    const box = await button.boundingBox();
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await page.mouse.down(); await page.waitForTimeout(800); await page.mouse.up();
    const life = Number(await page.locator('[data-life-total]').first().textContent());
    expect(life).toBeLessThanOrEqual(36); expect(live.state().players[0].life).toBe(40);
    await expect(page.locator('[data-life-counter]')).toHaveAttribute('data-pending', '0', { timeout: 5000 });
    expect(live.state().players[0].life).toBe(life);
});


test('TV audio setup confirms audibly, then damage from a phone triggers a recorded sound once', async ({ page }) => {
    const live = await prepare(page, 'tv');
    await expect(page.locator('[data-tv-audio-prompt]')).toBeVisible();
    expect(await samples(page)).toBe(0);
    await page.getByRole('button', { name: 'TV-Ton aktivieren', exact: true }).click();
    await expect(page.locator('[data-tv-audio-prompt]')).toBeHidden();
    await expect.poll(() => page.evaluate(() => window.testTones)).toBe(2);
    const state = live.state();
    state.revision++; state.operation = 'life'; state.serverTime = Date.now(); state.players[0].life--;
    const channel = `tournament.${new URL(state.returnUrl).pathname.split('/')[2]}`;
    live.emit('life.changed', channel, { state });
    await expect(page.locator('[data-tv-life-total]').first()).toHaveText('39');
    await expect.poll(() => samples(page)).toBe(1);
    await expect(page.locator('[data-callout-title]')).toHaveText('First Blood');
    live.emit('life.changed', channel, { state });
    await page.waitForTimeout(350); expect(await samples(page)).toBe(1);
    expect(await speechCount(page)).toBe(0);
});

test('a remote Enter key on the TV page unlocks automatic damage audio without opening settings', async ({ page }) => {
    const live = await prepare(page, 'tv');
    await page.locator('body').evaluate(node => { node.tabIndex = -1; node.focus(); });
    await page.keyboard.press('Enter');
    await expect(page.locator('[data-tv-audio-prompt]')).toBeHidden();
    const state = live.state(); state.revision++; state.operation = 'damage'; state.serverTime = Date.now();
    state.players[0].life -= 7; state.players[0].damage = { [state.players[1].id]: 7 };
    live.emit('life.changed', `tournament.${new URL(state.returnUrl).pathname.split('/')[2]}`, { state });
    await expect.poll(() => samples(page)).toBe(1);
    await expect(page.locator('[data-tv-life-total]').first()).toHaveText('33');
});

test('the TV respects mute while continuing to display live life totals', async ({ page }) => {
    const live = await prepare(page, 'tv');
    await page.getByRole('button', { name: 'TV-Ton aktivieren', exact: true }).click();
    await page.getByRole('button', { name: 'Ton an', exact: true }).click();
    const state = live.state(); state.revision++; state.operation = 'life'; state.serverTime = Date.now(); state.players[0].life--;
    live.emit('life.changed', `tournament.${new URL(state.returnUrl).pathname.split('/')[2]}`, { state });
    await expect(page.locator('[data-tv-life-total]').first()).toHaveText('39');
    await page.waitForTimeout(350); expect(await samples(page)).toBe(0);
    await expect(page.locator('[data-tv-audio-prompt]')).toBeHidden();
});

test('TV celebrates a real standings gain with ascending audio, a row badge and a position callout', async ({ page }) => {
    const live = await prepare(page, 'tv');
    // Seed a previous placement quietly, as on initial load/reconnect.
    const initial = await page.locator('[data-tv-board]').evaluate(board => {
        const rows = [...board.querySelectorAll('[data-tv-standing]')];
        rows[0].dataset.place = '3'; rows[0].querySelector('.tv-place').textContent = '3';
        return board.innerHTML;
    });
    live.board(initial); await page.evaluate(() => window.dispatchEvent(new Event('online'))); await page.waitForTimeout(150);
    await page.getByRole('button', { name: 'TV-Ton aktivieren', exact: true }).click();
    await expect.poll(() => page.evaluate(() => window.testTones)).toBe(2);
    const improved = await page.locator('[data-tv-board]').evaluate(board => {
        const row = board.querySelector('[data-tv-standing]');
        row.dataset.place = '1'; row.dataset.points = '6'; row.dataset.games = '2';
        row.querySelector('.tv-place').textContent = '1'; row.querySelector('b').textContent = '6';
        return board.innerHTML;
    });
    live.board(improved); live.emit('board.changed', 'tv-board', {});
    await expect(page.locator('[data-callout-title]')).toHaveText('Neue Spitze!');
    await expect(page.locator('[data-commentary-text]')).toContainText('Platz 3 → 1');
    await expect(page.locator('[data-tv-standing]').first()).toHaveAttribute('data-rank-gain', '↑ 2');
    await expect.poll(() => page.evaluate(() => window.testTones)).toBe(6);
    await page.screenshot({ path: '/tmp/tv-rank-up.png', fullPage: true });
    live.emit('board.changed', 'tv-board', {}); await page.waitForTimeout(200);
    expect(await page.evaluate(() => window.testTones)).toBe(6);
});

test('TV reports a newly lost game with its own descending cue and named callout', async ({ page }) => {
    const live = await prepare(page, 'tv');
    await page.getByRole('button', { name: 'TV-Ton aktivieren', exact: true }).click();
    const html = await page.locator('[data-tv-board]').evaluate(board => {
        const match = board.querySelector('[data-tv-result]');
        const result = JSON.parse(match.dataset.tvResult); result.completed = true;
        result.players.forEach((player, index) => { player.score = index === 0 ? 0 : 3; });
        match.dataset.tvResult = JSON.stringify(result); match.dataset.tvValue = 'complete';
        return board.innerHTML;
    });
    live.board(html); live.emit('board.changed', 'tv-board', {});
    await expect(page.locator('[data-callout-title]')).toHaveText('Spiel verloren');
    await expect(page.locator('[data-commentary-text]')).toHaveText('Tobi');
    await expect.poll(() => page.evaluate(() => window.testTones)).toBe(5);
    live.emit('board.changed', 'tv-board', {}); await page.waitForTimeout(200);
    expect(await page.evaluate(() => window.testTones)).toBe(5);
});
