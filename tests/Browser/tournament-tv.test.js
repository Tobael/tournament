import test from 'node:test';
import assert from 'node:assert/strict';
import { youtubeEmbed, formatCountdown } from '../../resources/js/tournament-tv.js';

test('YouTube links produce canonical video and playlist embeds', () => {
    for (const link of [
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10',
        'https://youtu.be/dQw4w9WgXcQ',
        'https://m.youtube.com/shorts/dQw4w9WgXcQ',
        'https://www.youtube.com/live/dQw4w9WgXcQ',
        'https://music.youtube.com/watch?v=dQw4w9WgXcQ',
    ]) assert.equal(youtubeEmbed(link), 'https://www.youtube.com/embed/dQw4w9WgXcQ?playsinline=1');
    assert.equal(youtubeEmbed('https://www.youtube.com/playlist?list=PL1234567890_abcd'), 'https://www.youtube.com/embed/videoseries?list=PL1234567890_abcd&playsinline=1');
    assert.equal(youtubeEmbed('https://youtu.be/dQw4w9WgXcQ?list=PL1234567890_abcd'), 'https://www.youtube.com/embed/dQw4w9WgXcQ?list=PL1234567890_abcd&playsinline=1');
});

test('untrusted embeds, lookalike hosts, invalid IDs and non-web URLs are rejected', () => {
    for (const link of [
        'javascript:alert(1)', '<iframe src="https://youtube.com"></iframe>',
        'https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ',
        'https://youtube.com@evil.test/watch?v=dQw4w9WgXcQ',
        'https://evil.test@youtube.com/watch?v=dQw4w9WgXcQ',
        'https://youtube.com:8443/watch?v=dQw4w9WgXcQ',
        'https://youtube.com/watch?v=short', 'https://youtube.com/',
        'https://youtube.com/playlist?list=%22%3E%3Cscript%3E',
        'ftp://youtube.com/watch?v=dQw4w9WgXcQ',
    ]) assert.equal(youtubeEmbed(link), null, link);
});

test('shared deadlines count down across hours and never show negative time', () => {
    assert.equal(formatCountdown(5400000, 0), '90:00');
    assert.equal(formatCountdown(60000, 1), '1:00');
    assert.equal(formatCountdown(60000, 59000), '0:01');
    assert.equal(formatCountdown(60000, 60000), 'Zeit abgelaufen · Extrazüge');
    assert.equal(formatCountdown(60000, 99000), 'Zeit abgelaufen · Extrazüge');
});
