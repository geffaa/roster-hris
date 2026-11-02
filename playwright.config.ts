import { defineConfig, devices } from '@playwright/test';

/*
 * End-to-end tests run against their own database (roster_e2e) and server (port 8030),
 * so they never touch the demo data. `npm run e2e` reseeds that database first.
 */
const env = { ...process.env, DB_DATABASE: 'roster_e2e', APP_URL: 'http://127.0.0.1:8030' } as Record<string, string>;

export default defineConfig({
    testDir: 'tests/e2e',
    workers: 1,
    timeout: 60_000,
    use: { baseURL: 'http://127.0.0.1:8030', channel: 'chrome', trace: 'retain-on-failure' },
    globalSetup: './tests/e2e/setup.ts',
    webServer: { command: 'php artisan serve --port=8030 --no-reload', url: 'http://127.0.0.1:8030/up', env, reuseExistingServer: false, timeout: 60_000 },
    projects: [
        { name: 'phone', use: { ...devices['Pixel 7'], channel: 'chrome' }, testMatch: /employee\.spec/ },
        { name: 'desktop', use: { viewport: { width: 1440, height: 900 } }, testMatch: /payroll\.spec/ },
    ],
});
