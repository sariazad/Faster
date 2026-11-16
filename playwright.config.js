import { defineConfig } from '@playwright/test';

// Browser tests for قدّها؟. CI starts `php artisan serve`; locally point E2E_BASE at any server.
export default defineConfig({
    testDir: 'tests/e2e',
    timeout: 240_000,
    expect: { timeout: 12_000 },
    fullyParallel: false,
    workers: 1,
    reporter: [['list']],
    outputDir: 'test-results/e2e',
    use: {
        baseURL: process.env.E2E_BASE || 'http://127.0.0.1:8000',
        channel: process.env.PW_CHANNEL || undefined,
        viewport: { width: 1600, height: 900 },
        locale: 'ar',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
});
