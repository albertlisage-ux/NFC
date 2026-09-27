/**
 * Visual QA helper: renders the portal in the locally installed Chrome and
 * writes screenshots for desktop and mobile widths.
 *
 *   php -S 127.0.0.1:8080 router.php &
 *   node scripts/screenshot.mjs --base=http://127.0.0.1:8080 --out=/tmp/nfc-shots
 *
 * Playwright is resolved from the local module, from PLAYWRIGHT_MODULE, or
 * from an npx cache entry. Chrome is used through the `chrome` channel, so no
 * browser download is required.
 */

import { createRequire } from 'node:module';
import { existsSync, mkdirSync, readdirSync } from 'node:fs';
import { homedir } from 'node:os';
import { join } from 'node:path';

const args = new Map(
  process.argv.slice(2).map((argument) => {
    const [key, ...rest] = argument.replace(/^--/, '').split('=');
    return [key, rest.join('=') || 'true'];
  })
);

const base = (args.get('base') || 'http://127.0.0.1:8080').replace(/\/$/, '');
const outDir = args.get('out') || '/tmp/nfc-shots';
const extraPaths = (args.get('paths') || '').split(',').map((value) => value.trim()).filter(Boolean);

function resolvePlaywright() {
  const candidates = [process.env.PLAYWRIGHT_MODULE];

  const npxRoot = join(homedir(), '.npm', '_npx');
  if (existsSync(npxRoot)) {
    for (const entry of readdirSync(npxRoot)) {
      candidates.push(join(npxRoot, entry, 'node_modules', 'playwright'));
    }
  }
  candidates.push(join(process.cwd(), 'node_modules', 'playwright'));

  for (const candidate of candidates) {
    if (candidate && existsSync(candidate)) {
      return candidate;
    }
  }
  return 'playwright';
}

const require = createRequire(import.meta.url);
const playwrightPath = resolvePlaywright();

let chromium;
try {
  ({ chromium } = require(playwrightPath));
} catch (error) {
  console.error('Playwright not found. Install it or set PLAYWRIGHT_MODULE.');
  console.error(String(error.message || error));
  process.exit(1);
}

mkdirSync(outDir, { recursive: true });

const targets = [
  { path: '/', name: 'home', full: false },
  { path: '/', name: 'home-full', full: true, skipMobile: true },
  { path: '/how-it-works', name: 'how-it-works', full: true },
  { path: '/use-cases', name: 'use-cases', full: true },
  { path: '/privacy', name: 'privacy', full: true },
  { path: '/account/login.php', name: 'login' },
  { path: '/account/register.php', name: 'register' },
  { path: '/docs/imprint.php', name: 'imprint' },
  ...extraPaths.map((path) => ({ path, name: path.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') })),
];

const viewports = [
  { label: 'desktop', width: 1440, height: 1000 },
  { label: 'mobile', width: 390, height: 844 },
];

const browser = await chromium.launch({ channel: 'chrome' });

for (const viewport of viewports) {
  const context = await browser.newContext({
    viewport: { width: viewport.width, height: viewport.height },
    deviceScaleFactor: 1,
  });
  const page = await context.newPage();

  for (const target of targets) {
    if (target.skipMobile && viewport.label === 'mobile') {
      continue;
    }
    const url = base + target.path;
    await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 }).catch(async () => {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    });
    const file = join(outDir, `${target.name}-${viewport.label}.png`);
    await page.screenshot({ path: file, fullPage: Boolean(target.full) });
    console.log(`saved ${file}`);
  }

  await context.close();
}

await browser.close();
