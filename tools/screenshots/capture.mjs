// Captures panel screenshots with a headless Chromium.
//
// Environment:
//   PANEL_URL      base URL of a running panel (default http://127.0.0.1:8899)
//   OUT_DIR        where PNGs are written (default ../../showcase)
//   SERVER_ID      uuidShort of a seeded server for the console shot
//   PANEL_USER     login username or email (default admin@nebulodactyl.dev)
//   PANEL_PASSWORD login password (default admin)
//   CHROMIUM_PATH  path to a chromium/chrome binary (autodetected otherwise)

import { execSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const BASE = process.env.PANEL_URL || 'http://127.0.0.1:8899';
const OUT = process.env.OUT_DIR || path.join(ROOT, 'showcase');
const SERVER_ID = process.env.SERVER_ID || '';
const USER = process.env.PANEL_USER || 'admin@nebulodactyl.dev';
const PASS = process.env.PANEL_PASSWORD || 'admin';

function findChromium() {
    if (process.env.CHROMIUM_PATH) return process.env.CHROMIUM_PATH;
    for (const bin of ['chromium', 'chromium-browser', 'google-chrome', 'google-chrome-stable']) {
        try {
            return execSync(`command -v ${bin}`, { encoding: 'utf8' }).trim();
        } catch {
            // try the next candidate
        }
    }
    throw new Error(
        'No Chromium found. Set CHROMIUM_PATH or install one with: pnpm exec playwright-core install chromium',
    );
}

const browser = await chromium.launch({
    executablePath: findChromium(),
    args: ['--no-sandbox', '--disable-gpu', '--hide-scrollbars'],
});

const page = await browser.newPage({
    viewport: { width: 1600, height: 1000 },
    deviceScaleFactor: 2,
});

mkdirSync(OUT, { recursive: true });

async function shot(name) {
    await page.screenshot({ path: path.join(OUT, name) });
    console.log(`wrote ${name}`);
}

// Give the SPA a moment to finish its first paint after load.
const settle = () => page.waitForTimeout(1500);

// 1. Login screen
await page.goto(`${BASE}/auth/login`, { waitUntil: 'networkidle' });
await settle();
await shot('panel-login.png');

// 2. Sign in, then the server list dashboard. Each row polls the daemon
// REST endpoint for stats, so give them a moment to land.
await page.fill('input[name="user"]', USER);
await page.fill('input[name="password"]', PASS);
await Promise.all([page.waitForURL(`${BASE}/`, { timeout: 15000 }), page.click('button[type="submit"]')]);
await page.waitForLoadState('networkidle');
await settle();
// Rows poll the daemon REST endpoint on mount; wait until at least one
// row shows real memory usage instead of the zeroed placeholder.
await page
    .waitForFunction(() => document.body.innerText.includes('GiB'), { timeout: 10000 })
    .catch(() => {});
await page.waitForTimeout(500);
await shot('panel-dark.png');

// 3. Server console, if a demo server was seeded
if (SERVER_ID) {
    await page.goto(`${BASE}/server/${SERVER_ID}`, { waitUntil: 'networkidle' });
    await settle();
    await shot('panel-console.png');

    // 4. Networking view with the seeded allocations
    await page.goto(`${BASE}/server/${SERVER_ID}/network`, { waitUntil: 'networkidle' });
    await settle();
    await shot('panel-network.png');

    // 5. Schedules view with the seeded restart schedule
    await page.goto(`${BASE}/server/${SERVER_ID}/schedules`, { waitUntil: 'networkidle' });
    await settle();
    await shot('panel-schedules.png');
}

await browser.close();
