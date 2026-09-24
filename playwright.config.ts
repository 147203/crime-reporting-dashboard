import { defineConfig, devices } from '@playwright/test';
import { randomBytes } from 'node:crypto';

// One random password per run, shared with the Laravel process through the environment only; never written to disk.
process.env.E2E_PASSWORD ??= randomBytes(24).toString('base64url');

const port = 8123;
const env = {
    ...process.env,
    APP_ENV: 'local',
    DB_DATABASE: 'crime_dashboard_e2e',
    E2E_PASSWORD: process.env.E2E_PASSWORD,
    SESSION_SECURE_COOKIE: 'false',
    LOGIN_MAX_ATTEMPTS: '50',
} as Record<string, string>;

export default defineConfig({
    testDir: 'tests/e2e',
    outputDir: 'test-results',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1366, height: 900 } } },
        { name: 'mobile', use: { ...devices['Pixel 5'], viewport: { width: 360, height: 780 } } },
    ],
    webServer: {
        // Prepare the *_e2e schema, then serve; the web server starts before any global setup would run.
        command: `php artisan e2e:prepare && php artisan serve --host=127.0.0.1 --port=${port}`,
        url: `http://127.0.0.1:${port}/login`,
        reuseExistingServer: false,
        env,
        stdout: 'ignore',
        stderr: 'pipe',
        timeout: 180_000,
    },
});
