/**
 * Optional browser checks. See README.md in this directory.
 *
 * Renders real pages in Chromium and reports anything a PHP test cannot see:
 * CSP violations, console errors, horizontal scrolling at phone width, charts
 * that never drew, form controls without a label, images without alt text.
 *
 * Exits non-zero if anything is found, so it can gate a release if you want it to.
 */
import { chromium } from 'playwright-core';
import { existsSync } from 'node:fs';

const BASE = process.env.SK_BASE || 'http://127.0.0.1:8080';
const EMAIL = process.env.SK_EMAIL || '';
const PASSWORD = process.env.SK_PASSWORD || '';
const AUTH = process.argv.includes('--auth');

const CANDIDATES = [
  process.env.CHROME_PATH,
  '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
  '/opt/pw-browsers/chromium/chrome-linux/chrome',
  '/usr/bin/chromium',
  '/usr/bin/google-chrome',
].filter(Boolean);
const executablePath = CANDIDATES.find(p => existsSync(p));
if (!executablePath) {
  console.error('No Chromium found. Set CHROME_PATH to a browser binary.');
  process.exit(2);
}

const PUBLIC_PAGES = ['/', '/templates', '/categories', '/login', '/register', '/page/terms'];
const AUTH_PAGES = [
  '/dashboard', '/invitations', '/analytics', '/profile', '/notifications',
  '/create', '/create/templates', '/builder/1', '/builder/1?step=4', '/builder/1?step=5',
  '/builder/1?step=7', '/builder/1/share', '/invitations/1/rsvp', '/invitations/1/analytics',
  '/admin', '/admin/templates', '/admin/templates/1/fields', '/admin/templates/1/components',
  '/admin/invitations',
  '/admin/media', '/admin/analytics', '/admin/settings/security',
  '/admin/system', '/admin/system/health', '/admin/system/update', '/admin/system/cron',
];
const VIEWPORTS = AUTH
  ? [{ name: 'phone 390', width: 390, height: 844 }]
  : [
      { name: 'phone 360', width: 360, height: 740 },
      { name: 'tablet 768', width: 768, height: 1024 },
      { name: 'laptop 1024', width: 1024, height: 768 },
      { name: 'desktop 1440', width: 1440, height: 900 },
    ];

/** What we ask of every rendered page. */
const inspect = () => ({
  overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
  widest: Array.from(document.querySelectorAll('body *'))
    .filter(el => el.getBoundingClientRect().right > document.documentElement.clientWidth + 1)
    .slice(0, 3)
    .map(el => el.tagName.toLowerCase() + '.' + String(el.className).split(' ')[0]),
  unlabelled: Array.from(document.querySelectorAll('input:not([type=hidden]), select, textarea'))
    .filter(el => !el.getAttribute('aria-label') && !el.getAttribute('aria-labelledby')
      && !el.closest('label') && !(el.id && document.querySelector(`label[for="${el.id}"]`)))
    .map(el => el.tagName.toLowerCase() + '#' + (el.id || el.name || '?')),
  iconOnlyWithoutLabel: Array.from(document.querySelectorAll('button, a'))
    .filter(el => el.textContent.trim() === '' && !el.getAttribute('aria-label') && el.querySelector('i,svg'))
    .length,
  imagesWithoutAlt: Array.from(document.images).filter(i => !i.hasAttribute('alt')).length,
  charts: document.querySelectorAll('canvas[data-sk-chart]').length,
  chartsDrawn: Array.from(document.querySelectorAll('canvas[data-sk-chart]'))
    .filter(c => typeof Chart !== 'undefined' && !!Chart.getChart(c)).length,
  scripted: typeof window.SK !== 'undefined',
});

const browser = await chromium.launch({ executablePath, args: ['--no-sandbox'] });
let findings = 0;
let renders = 0;

for (const viewport of VIEWPORTS) {
  const context = await browser.newContext({ viewport: { width: viewport.width, height: viewport.height } });
  const page = await context.newPage();
  const consoleErrors = [];
  page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
  page.on('pageerror', e => consoleErrors.push(String(e)));

  if (AUTH) {
    if (!EMAIL || !PASSWORD) {
      console.error('--auth needs SK_EMAIL and SK_PASSWORD.');
      process.exit(2);
    }
    await page.goto(BASE + '/login');
    await page.fill('#email', EMAIL);
    await page.fill('#password', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
  }

  for (const path of (AUTH ? AUTH_PAGES : PUBLIC_PAGES)) {
    consoleErrors.length = 0;
    const response = await page.goto(BASE + path, { waitUntil: 'networkidle' });
    const r = await page.evaluate(inspect);
    renders++;

    const problems = [];
    if (response.status() !== 200) problems.push(`status ${response.status()}`);
    if (r.overflow > 1) problems.push(`horizontal scroll ${r.overflow}px via ${r.widest.join(', ')}`);
    if (r.unlabelled.length) problems.push(`unlabelled: ${r.unlabelled.join(', ')}`);
    if (r.iconOnlyWithoutLabel) problems.push(`${r.iconOnlyWithoutLabel} icon-only control(s) without a label`);
    if (r.imagesWithoutAlt) problems.push(`${r.imagesWithoutAlt} image(s) without alt`);
    if (!r.scripted) problems.push('application JavaScript did not run (CSP?)');
    if (r.charts && r.chartsDrawn < r.charts) problems.push(`${r.charts - r.chartsDrawn} chart(s) did not draw`);
    if (consoleErrors.length) problems.push(`console: ${consoleErrors.slice(0, 2).join(' | ')}`);

    if (problems.length) {
      findings++;
      console.log(`✗ ${viewport.name} ${path}`);
      problems.forEach(p => console.log(`    ${p}`));
    } else {
      console.log(`✓ ${viewport.name} ${path}`);
    }
  }
  await context.close();
}

await browser.close();
console.log(`\n${renders} render(s), ${findings} with findings`);
process.exit(findings === 0 ? 0 : 1);
