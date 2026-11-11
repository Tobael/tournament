import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/E2E',
    workers: 1,
    use: { baseURL: 'http://127.0.0.1:8095', launchOptions: { executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox'] } },
    webServer: { command: 'php artisan serve --host=127.0.0.1 --port=8095', url: 'http://127.0.0.1:8095', reuseExistingServer: !process.env.CI },
});
