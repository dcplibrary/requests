#!/usr/bin/env node
/**
 * Captures the screenshots used by the Requests help pages.
 *
 * Usage:
 *   1. Make sure your local Laravel app is running (the BASE_URL below).
 *   2. Make sure you are signed into /request/staff as an admin user in Chrome,
 *      then copy that browser's profile cookies to Puppeteer by running:
 *
 *         BASE_URL=http://localhost:8001 \
 *         REQUESTS_SESSION_COOKIE='laravel_session=...' \
 *         node scripts/capture-help-screenshots.js
 *
 *      To get the cookie: open DevTools → Application → Cookies → copy the
 *      value of `laravel_session` (and `XSRF-TOKEN` if your login flow needs
 *      it) for localhost:8001.
 *
 *   3. Output goes to public/img/help/*.jpg inside this package.
 *
 * Install (once):
 *   npm install --save-dev puppeteer
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8001';
const COOKIE_HEADER = process.env.REQUESTS_SESSION_COOKIE || '';
const OUT_DIR = path.resolve(__dirname, '..', 'public', 'img', 'help');

// Viewport matches the width the help HTML expects (1525 wide images look
// reasonable inside the max-width 860px content column at high-DPI).
const VIEWPORT = { width: 1525, height: 1000, deviceScaleFactor: 1 };

// Each entry:
//   name:   output filename (without .jpg)
//   url:    path (appended to BASE_URL)
//   wait:   optional selector or milliseconds to wait for before capturing
//   setup:  optional async (page) => {...} to click/scroll/fill something first
//   full:   true = full page, false = viewport only (default true)
const SHOTS = [
    // ---- Selector Guide ----
    { name: 'requests-list',           url: '/request/staff/requests?kind=sfp' },
    { name: 'ill-requests-list',       url: '/request/staff/requests?kind=ill' },
    { name: 'request-detail-top',      url: '/request/staff/requests/1' },
    { name: 'email-preview-modal',     url: '/request/staff/requests/1',
      setup: async (page) => {
          await page.waitForSelector('[x-data] button, button:has-text("Preview")', { timeout: 5000 }).catch(() => {});
          // Try to open the status email preview modal by clicking Preview if present
          const clicked = await page.evaluate(() => {
              const btn = Array.from(document.querySelectorAll('button, a')).find(el =>
                  /preview.*email/i.test(el.textContent || ''));
              if (btn) { btn.click(); return true; }
              return false;
          });
          if (clicked) { await new Promise(r => setTimeout(r, 800)); }
      }
    },
    { name: 'patrons-list',            url: '/request/staff/patrons' },
    { name: 'patron-detail',           url: '/request/staff/patrons/1' },
    { name: 'titles-list',             url: '/request/staff/titles' },
    { name: 'title-detail',            url: '/request/staff/titles/1' },

    // ---- Settings Guide ----
    { name: 'settings-general',        url: '/request/staff/settings' },
    { name: 'settings-forms',          url: '/request/staff/settings/form-fields' },
    { name: 'settings-notifications',  url: '/request/staff/settings/notifications' },
    { name: 'settings-email-templates',url: '/request/staff/settings/notifications?tab=emails' },
    { name: 'settings-catalog',        url: '/request/staff/catalog' },
    { name: 'settings-statuses',       url: '/request/staff/statuses' },
    { name: 'settings-users',          url: '/request/staff/users' },
    { name: 'settings-groups',         url: '/request/staff/groups' },
];

(async () => {
    if (!COOKIE_HEADER) {
        console.error('ERROR: REQUESTS_SESSION_COOKIE env var is required.');
        console.error('Get the cookie from DevTools → Application → Cookies for your logged-in staff session.');
        console.error('Example:');
        console.error('  REQUESTS_SESSION_COOKIE="laravel_session=eyJ..." node scripts/capture-help-screenshots.js');
        process.exit(1);
    }

    fs.mkdirSync(OUT_DIR, { recursive: true });

    const browser = await puppeteer.launch({
        headless: 'new',
        defaultViewport: VIEWPORT,
        args: ['--no-sandbox'],
    });

    try {
        const page = await browser.newPage();
        await page.setViewport(VIEWPORT);

        // Parse cookies from the header string ("name=value; name2=value2")
        const url = new URL(BASE_URL);
        const cookies = COOKIE_HEADER.split(';').map(s => s.trim()).filter(Boolean).map(pair => {
            const idx = pair.indexOf('=');
            const name = pair.slice(0, idx);
            const value = pair.slice(idx + 1);
            return { name, value, domain: url.hostname, path: '/' };
        });
        await page.setCookie(...cookies);

        for (const shot of SHOTS) {
            const target = `${BASE_URL}${shot.url}`;
            const outPath = path.join(OUT_DIR, `${shot.name}.jpg`);
            process.stdout.write(`→ ${shot.name.padEnd(26)} ${target} ... `);

            try {
                await page.goto(target, { waitUntil: 'networkidle0', timeout: 20000 });

                if (typeof shot.setup === 'function') {
                    await shot.setup(page);
                }

                if (typeof shot.wait === 'number') {
                    await new Promise(r => setTimeout(r, shot.wait));
                } else if (typeof shot.wait === 'string') {
                    await page.waitForSelector(shot.wait, { timeout: 5000 }).catch(() => {});
                }

                await page.screenshot({
                    path: outPath,
                    type: 'jpeg',
                    quality: 85,
                    fullPage: shot.full !== false,
                });

                console.log('ok');
            } catch (err) {
                console.log(`FAIL: ${err.message}`);
            }
        }
    } finally {
        await browser.close();
    }

    console.log('');
    console.log(`Wrote to: ${OUT_DIR}`);
    console.log('After capture, remember to run the asset-publish step for your host app');
    console.log('if it relies on the old /vendor/sfp/img/ path.');
})();
