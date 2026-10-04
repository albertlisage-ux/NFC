/**
 * Regenerates the preview images used on the home page.
 *
 * Apple's resource page shows a preview image per tile. The equivalent here is
 * a real rendering of that product's own tag page, captured from the running
 * application, plus the owner dashboard. Nothing is drawn by hand.
 *
 *   php -S 127.0.0.1:8080 router.php &
 *   node scripts/make-previews.mjs --base=http://127.0.0.1:8080
 *
 * Output: assets/img/previews/*.jpg, overwritten on every run.
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
const outDir = args.get('out') || join(process.cwd(), 'assets', 'img', 'previews');
const email = args.get('email') || 'demo@example.com';
const password = args.get('password') || 'DemoTag2026!';

function resolvePlaywright() {
  const candidates = [process.env.PLAYWRIGHT_MODULE];
  const npxRoot = join(homedir(), '.npm', '_npx');
  if (existsSync(npxRoot)) {
    for (const entry of readdirSync(npxRoot)) {
      candidates.push(join(npxRoot, entry, 'node_modules', 'playwright'));
    }
  }
  for (const candidate of candidates) {
    if (candidate && existsSync(candidate)) return candidate;
  }
  return 'playwright';
}

const require = createRequire(import.meta.url);
const { chromium } = require(resolvePlaywright());

mkdirSync(outDir, { recursive: true });

// One preview per catalogue product, plus the owner side of the flow.
const shots = [
  { path: '/t/MENUTAG2', name: 'menu_board' },
  { path: '/t/PSTTAG24', name: 'poster' },
  { path: '/t/WRSTAG24', name: 'wristband' },
  { path: '/t/NCKTAG24', name: 'necklace' },
  { path: '/t/LNYTAG24', name: 'lanyard' },
  { path: '/t/KEYTAG24', name: 'keychain' },
  { path: '/t/TNYTAG24', name: 'mini_tag' },
  { path: '/t/DEMTAG24', name: 'pet' },
  { path: '/t/JAKTAG24', name: 'clothing' },
  { path: '/dashboard/index.php', name: 'dashboard', login: true, width: 1120, height: 720 },
  // The tag page of the first asset, whichever id this installation assigned.
  { path: '@first-asset-tags', name: 'tags', login: true, width: 1120, height: 720 },
  { path: '/demo', name: 'demo', width: 1120, height: 720 },
];

const phone = { width: 430, height: 880 };
const browser = await chromium.launch({ channel: 'chrome' });
const context = await browser.newContext({ viewport: phone, deviceScaleFactor: 2 });
const page = await context.newPage();

// Sign in once: the session cookie is reused for the owner screenshots.
await page.goto(base + '/account/login.php', { waitUntil: 'domcontentloaded' });
await page.fill('#identifier', email);
await page.fill('#password', password);
await Promise.all([
  page.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}),
  page.click('button[type="submit"]'),
]);

for (const shot of shots) {
  if (shot.width) {
    await page.setViewportSize({ width: shot.width, height: shot.height });
  } else {
    await page.setViewportSize(phone);
  }

  let target = base + shot.path;
  if (shot.path === '@first-asset-tags') {
    await page.goto(base + '/dashboard/index.php', { waitUntil: 'domcontentloaded' });
    const link = await page.getAttribute('a[href*="dashboard/tags.php?id="]', 'href');
    if (!link) {
      console.error('No asset found for the tag page preview, skipping.');
      continue;
    }
    target = link.startsWith('http') ? link : base + link;
  }

  await page.goto(target, { waitUntil: 'domcontentloaded' });
  // Let the fonts and the QR images settle before the capture.
  await page.waitForTimeout(400);

  const file = join(outDir, shot.name + '.jpg');
  await page.screenshot({ path: file, type: 'jpeg', quality: 82 });
  console.log('saved ' + file);
}

await browser.close();
