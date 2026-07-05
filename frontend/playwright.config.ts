import * as fs from 'fs';
import * as path from 'path';

import {defineConfig, devices} from '@playwright/test';

// Load .env.test from repo root when present so credentials can be overridden per-env.
const envTestPath = path.resolve(__dirname, '../.env.test');
if (fs.existsSync(envTestPath)) {
    const lines = fs.readFileSync(envTestPath, 'utf-8').split('\n');
    for (const line of lines) {
        const trimmed = line.trim();
        if (!trimmed || trimmed.startsWith('#')) continue;
        const eqIdx = trimmed.indexOf('=');
        if (eqIdx === -1) continue;
        const key = trimmed.slice(0, eqIdx).trim();
        const value = trimmed.slice(eqIdx + 1).trim();
        if (!(key in process.env)) {
            process.env[key] = value;
        }
    }
}

// Trailing slash matters: page.goto('login') resolves relative to the base path
// only when the URL ends with one (URL() constructor semantics).
const baseURL = process.env['E2E_BASE_URL'] ?? 'https://localhost:7281/app/';
const skipWebServer = process.env['E2E_SKIP_WEBSERVER'] === '1';

export default defineConfig({
    testDir: './e2e',
    fullyParallel: false,
    forbidOnly: !!process.env['CI'],
    retries: process.env['CI'] ? 1 : 0,
    workers: 1,
    reporter: process.env['CI'] ? 'github' : 'list',

    use: {
        baseURL,
        ignoreHTTPSErrors: true,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'on-first-retry',
    },

    webServer: skipWebServer ? undefined : {
        command: 'docker compose up -d --build --wait',
        cwd: path.resolve(__dirname, '..'),
        url: baseURL,
        reuseExistingServer: true,
        ignoreHTTPSErrors: true,
        timeout: 300_000,
    },

    projects: [
        {
            name: 'setup',
            testMatch: /setup\/.*\.setup\.ts/,
        },
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                storageState: 'e2e/.auth/user.json',
            },
            dependencies: ['setup'],
        },
    ],
});
