import { chromium } from 'playwright-core';

const browser = await chromium.launch({ executablePath: '/usr/bin/chromium', args: ['--no-sandbox'] });
const page = await browser.newPage();
page.on('websocket', (ws) => {
    console.log('[ws] open', ws.url());
    ws.on('framesent', (f) => console.log('[ws] >>', String(f.payload).slice(0, 120)));
    ws.on('framereceived', (f) => console.log('[ws] <<', String(f.payload).slice(0, 160)));
    ws.on('close', () => console.log('[ws] close'));
    ws.on('socketerror', (e) => console.log('[ws] error', e));
});
await page.goto(process.env.PANEL_URL + '/auth/login', { waitUntil: 'networkidle' });
await page.fill('input[name="user"]', 'admin@nebulodactyl.dev');
await page.fill('input[name="password"]', 'admin');
await Promise.all([page.waitForURL(process.env.PANEL_URL + '/'), page.click('button[type="submit"]')]);
await page.goto(`${process.env.PANEL_URL}/server/${process.env.SERVER_ID}`, { waitUntil: 'networkidle' });
await page.waitForTimeout(10000);
const text = await page.evaluate(() => document.body.innerText);
console.log('body has Done:', text.includes('Done ('), '| offline:', text.includes('Offline'));
await browser.close();
