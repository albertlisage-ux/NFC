/**
 * Automated design and layout checks, measured in a real browser.
 *
 *   php -S 127.0.0.1:8080 router.php &
 *   node scripts/ui-check.mjs --base=http://127.0.0.1:8080
 *
 * Covers the checks that are easy to get wrong and hard to see: horizontal
 * overflow, wrapping navigation and buttons, hero height, heading structure,
 * contrast of the primary button and body text, and the presence of the
 * generated QR code.
 */

import { createRequire } from 'node:module';
import { existsSync, readdirSync } from 'node:fs';
import { homedir } from 'node:os';
import { join } from 'node:path';

const args = new Map(
  process.argv.slice(2).map((argument) => {
    const [key, ...rest] = argument.replace(/^--/, '').split('=');
    return [key, rest.join('=') || 'true'];
  })
);

const base = (args.get('base') || 'http://127.0.0.1:8080').replace(/\/$/, '');
const diagnosePath = args.get('diagnose') || null;

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

let failures = 0;
let checks = 0;
const record = (label, ok, detail = '') => {
  checks++;
  if (ok) {
    console.log(`  ok   ${label}`);
  } else {
    failures++;
    console.log(`  FAIL ${label}${detail ? ' -> ' + detail : ''}`);
  }
};

const browser = await chromium.launch({ channel: 'chrome' });

if (diagnosePath) {
  const context = await browser.newContext({ viewport: { width: Number(args.get('width') || 390), height: 844 } });
  const page = await context.newPage();
  await page.goto(base + diagnosePath, { waitUntil: 'domcontentloaded' });
  const offenders = await page.evaluate(() => {
    const width = document.documentElement.clientWidth;
    const found = [];
    document.querySelectorAll('body *').forEach((element) => {
      const rect = element.getBoundingClientRect();
      if (rect.width === 0 && rect.height === 0) return;
      if (rect.right > width + 1 || rect.left < -1) {
        found.push({
          tag: element.tagName.toLowerCase(),
          cls: (element.className || '').toString().slice(0, 60),
          left: Math.round(rect.left),
          right: Math.round(rect.right),
          width: Math.round(rect.width),
        });
      }
    });
    return { width, found: found.slice(0, 25) };
  });
  console.log(`viewport ${offenders.width}px, ${offenders.found.length} element(s) outside the viewport:`);
  for (const item of offenders.found) {
    console.log(`  <${item.tag} class="${item.cls}"> left=${item.left} right=${item.right} width=${item.width}`);
  }
  await context.close();
  await browser.close();
  process.exit(0);
}
const pages = [
  { path: '/', name: 'home' },
  { path: '/how-it-works', name: 'how-it-works' },
  { path: '/use-cases', name: 'use-cases' },
  { path: '/privacy', name: 'privacy' },
  { path: '/account/login.php', name: 'login' },
  { path: '/account/register.php', name: 'register' },
];

const viewports = [
  { label: 'desktop', width: 1440, height: 1000 },
  { label: 'mobile', width: 390, height: 844 },
];

for (const viewport of viewports) {
  console.log(`\n${viewport.label} ${viewport.width}x${viewport.height}`);
  const context = await browser.newContext({ viewport: { width: viewport.width, height: viewport.height } });
  const page = await context.newPage();

  for (const target of pages) {
    await page.goto(base + target.path, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForTimeout(150);

    const report = await page.evaluate(() => {
      const toRgb = (value) => {
        const match = value.match(/rgba?\(([^)]+)\)/);
        if (!match) return null;
        const parts = match[1].split(',').map((part) => parseFloat(part.trim()));
        return { r: parts[0], g: parts[1], b: parts[2], a: parts.length > 3 ? parts[3] : 1 };
      };
      const luminance = ({ r, g, b }) => {
        const channel = (value) => {
          const v = value / 255;
          return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        };
        return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
      };
      const effectiveBackground = (element) => {
        let node = element;
        while (node && node !== document.documentElement) {
          const colour = toRgb(getComputedStyle(node).backgroundColor);
          if (colour && colour.a > 0.5) return colour;
          node = node.parentElement;
        }
        return { r: 255, g: 255, b: 255, a: 1 };
      };
      const contrast = (element) => {
        const colour = toRgb(getComputedStyle(element).color);
        if (!colour) return null;
        const background = effectiveBackground(element);
        const l1 = luminance(colour);
        const l2 = luminance(background);
        const ratio = (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
        return Number(ratio.toFixed(2));
      };

      const doc = document.documentElement;
      const h1 = document.querySelector('h1');
      const h1Style = h1 ? getComputedStyle(h1) : null;
      const navLinks = [...document.querySelectorAll('.nav-links a')];
      const navLinkTops = navLinks.map((link) => Math.round(link.getBoundingClientRect().top));
      const visible = (element) => Boolean(element) && element.getBoundingClientRect().height > 0;
      const cta = [
        document.querySelector('.hero-actions .btn-primary'),
        ...document.querySelectorAll('.btn-primary'),
      ].find(visible) || null;
      const ctaRect = cta ? cta.getBoundingClientRect() : null;
      const ctaStyle = cta ? getComputedStyle(cta) : null;
      const qr = document.querySelector('.qr-frame svg, .qr-preview svg');
      const qrRect = qr ? qr.getBoundingClientRect() : null;
      const hero = document.querySelector('.hero');
      const heroRect = hero ? hero.getBoundingClientRect() : null;
      // A database notice can sit between the header and the hero.
      const banner = document.querySelector('.notice-warn');
      const nav = document.querySelector('.site-nav');
      const referenceBottom = Math.max(
        banner ? banner.getBoundingClientRect().bottom : 0,
        nav ? nav.getBoundingClientRect().bottom : 0
      );
      const navToggle = document.querySelector('.nav-toggle');
      const bodyText = document.body.innerText;

      return {
        scrollWidth: doc.scrollWidth,
        clientWidth: doc.clientWidth,
        h1Lines: h1 && h1Style ? Math.round(h1.getBoundingClientRect().height / parseFloat(h1Style.lineHeight)) : 0,
        navLinkTops,
        navToggleVisible: navToggle ? getComputedStyle(navToggle).display !== 'none' : false,
        ctaHeight: ctaRect ? Math.round(ctaRect.height) : 0,
        ctaContrast: cta ? contrast(cta) : null,
        ctaWidthFits: cta ? cta.scrollWidth <= Math.ceil(cta.clientWidth) + 1 : false,
        leadContrast: (() => {
          const lead = document.querySelector('.hero-lead') || document.querySelector('main p');
          return lead ? contrast(lead) : null;
        })(),
        h1Count: document.querySelectorAll('h1').length,
        hasEmDash: bodyText.includes('\u2014'),
        qrSize: qrRect ? Math.round(Math.min(qrRect.width, qrRect.height)) : 0,
        heroOffset: heroRect ? Math.round(heroRect.top - referenceBottom) : 0,
        lang: document.documentElement.lang,
        title: document.title,
        skipLink: Boolean(document.querySelector('.skip-link')),
      };
    });

    record(`${target.name}: no horizontal overflow`, report.scrollWidth <= report.clientWidth + 1, `${report.scrollWidth} > ${report.clientWidth}`);
    record(`${target.name}: exactly one h1`, report.h1Count === 1, `found ${report.h1Count}`);
    record(`${target.name}: h1 fits in two lines`, report.h1Lines <= 2, `${report.h1Lines} lines`);
    record(`${target.name}: no em dash in visible copy`, !report.hasEmDash);
    record(`${target.name}: documents a language`, /^[a-z]{2}$/.test(report.lang), report.lang);
    record(`${target.name}: has a skip link`, report.skipLink);
    const hasCta = report.ctaHeight > 0;
    record(`${target.name}: CTA label fits on one line`, !hasCta || report.ctaWidthFits, hasCta ? '' : 'no primary button on this page');
    record(`${target.name}: CTA height is single-line`, !hasCta || report.ctaHeight <= 60, `${report.ctaHeight}px`);
    record(`${target.name}: CTA contrast >= 4.5`, report.ctaContrast === null || report.ctaContrast >= 4.5, String(report.ctaContrast));
    record(`${target.name}: body text contrast >= 4.5`, report.leadContrast === null || report.leadContrast >= 4.5, String(report.leadContrast));

    if (viewport.label === 'desktop') {
      record(`${target.name}: desktop nav links share one line`, new Set(report.navLinkTops).size <= 1, JSON.stringify(report.navLinkTops));
      record(`${target.name}: desktop hides the mobile toggle`, !report.navToggleVisible);
    } else {
      record(`${target.name}: mobile shows the menu toggle`, report.navToggleVisible);
    }

    if (target.name === 'home') {
      record('home: hero QR code is rendered', report.qrSize >= 90, `${report.qrSize}px`);
      record('home: hero follows the header directly', report.heroOffset >= 0 && report.heroOffset < 90, `${report.heroOffset}px`);
    }
  }

  await context.close();
}

await browser.close();
console.log(`\n${failures === 0 ? `All ${checks} layout checks passed.` : `${failures} of ${checks} layout checks failed.`}`);
process.exit(failures === 0 ? 0 : 1);
