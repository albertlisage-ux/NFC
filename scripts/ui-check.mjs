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
const probePath = args.get('probe') || null;

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

if (probePath) {
  const context = await browser.newContext({ viewport: { width: Number(args.get('width') || 390), height: 844 } });
  const page = await context.newPage();
  await page.goto(base + probePath, { waitUntil: 'domcontentloaded' });
  const result = await page.evaluate(() => {
    const width = () => document.documentElement.scrollWidth;
    const baseline = width();
    const candidates = [
      '.table-scroll', '.nfc-sample', '.data-table', '.faq', '.step-list',
      '.split-grid', '.explore-grid', '.layer-columns', '.example-list', '.tag-card-frame',
    ];
    const rows = [];
    candidates.forEach((selector) => {
      const nodes = [...document.querySelectorAll(selector)];
      if (!nodes.length) return;
      const previous = nodes.map((node) => node.style.display);
      nodes.forEach((node) => { node.style.display = 'none'; });
      const after = width();
      nodes.forEach((node, index) => { node.style.display = previous[index]; });
      rows.push({ selector, count: nodes.length, without: after, delta: baseline - after });
    });

    // Widest elements, ignoring whether they sit inside a scroll container.
    let widest = null;
    document.querySelectorAll('body *').forEach((element) => {
      const rect = element.getBoundingClientRect();
      if (!widest || rect.right > widest.right) {
        widest = {
          tag: element.tagName.toLowerCase(),
          cls: (element.className || '').toString().slice(0, 50),
          right: Math.round(rect.right),
          width: Math.round(rect.width),
        };
      }
    });

    return { baseline, rows, widest };
  });
  console.log(`baseline scrollWidth ${result.baseline} at ${Number(args.get('width') || 390)}px`);
  for (const row of result.rows) {
    console.log(`  hide ${row.selector} (${row.count}) -> ${row.without} (saves ${row.delta})`);
  }
  console.log('widest element:', JSON.stringify(result.widest));
  await context.close();
  await browser.close();
  process.exit(0);
}

if (diagnosePath) {
  const context = await browser.newContext({ viewport: { width: Number(args.get('width') || 390), height: 844 } });
  const page = await context.newPage();
  await page.goto(base + diagnosePath, { waitUntil: 'domcontentloaded' });
  const offenders = await page.evaluate(() => {
    const width = document.documentElement.clientWidth;
    const found = [];
    const inScroller = (element) => {
      let node = element.parentElement;
      while (node && node !== document.documentElement) {
        const overflowX = getComputedStyle(node).overflowX;
        if (overflowX === 'auto' || overflowX === 'scroll' || overflowX === 'hidden') {
          return true;
        }
        node = node.parentElement;
      }
      return false;
    };
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
          scroller: inScroller(element),
        });
      }
    });
    return {
      width,
      docScrollWidth: document.documentElement.scrollWidth,
      bodyScrollWidth: document.body.scrollWidth,
      unclipped: found.filter((item) => !item.scroller).slice(0, 15),
      found: found.slice(0, 25),
    };
  });
  console.log(`viewport ${offenders.width}px, doc scrollWidth=${offenders.docScrollWidth}, body scrollWidth=${offenders.bodyScrollWidth}`);
  console.log(`elements outside the viewport: ${offenders.found.length}, of those not inside a scroll container: ${offenders.unclipped.length}`);
  for (const item of (offenders.unclipped.length ? offenders.unclipped : offenders.found)) {
    console.log(`  <${item.tag} class="${item.cls}"> left=${item.left} right=${item.right} width=${item.width}${item.scroller ? ' (scroll container)' : ''}`);
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
  { path: '/start', name: 'start' },
  { path: '/write-a-tag', name: 'write-a-tag' },
  { path: '/demo', name: 'demo' },
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
      const navBottom = nav ? Math.round(nav.getBoundingClientRect().bottom) : 0;
      const bannerBottom = banner ? Math.round(banner.getBoundingClientRect().bottom) : 0;
      const referenceBottom = Math.max(
        bannerBottom,
        navBottom
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
        heroGeometry: `nav=${navBottom} banner=${bannerBottom} hero=${heroRect ? Math.round(heroRect.top) : '-'}`,
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
      record('home: hero follows the header directly', report.heroOffset >= 0 && report.heroOffset < 90,
        `${report.heroOffset}px (${report.heroGeometry})`);
    }
  }

  await context.close();
}

/* ------------------------------------------------- tab behaviour check -- */

{
  console.log('\ntab switching on /use-cases');
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  await page.goto(base + '/use-cases', { waitUntil: 'domcontentloaded' });

  const state = async () => page.evaluate(() => {
    const panels = [...document.querySelectorAll('.type-panel')];
    const visible = panels.filter((panel) => panel.getBoundingClientRect().height > 0);
    const selected = [...document.querySelectorAll('[role="tab"][aria-selected="true"]')];
    const nav = document.querySelector('.catalog-nav');
    const navRect = nav ? nav.getBoundingClientRect() : null;
    const panel = document.querySelector('.type-panel:target') || visible[0] || null;
    const panelRect = panel ? panel.getBoundingClientRect() : null;
    return {
      total: panels.length,
      visibleCount: visible.length,
      visibleId: visible[0] ? visible[0].id : null,
      selectedHref: selected[0] ? selected[0].getAttribute('href') : null,
      pageHeight: document.documentElement.scrollHeight,
      navTop: navRect ? Math.round(navRect.top) : null,
      navBottom: navRect ? Math.round(navRect.bottom) : null,
      navVisible: navRect ? navRect.bottom > 0 && navRect.top < window.innerHeight : false,
      viewportHeight: window.innerHeight,
      navWidth: navRect ? Math.round(navRect.width) : null,
      panelWidth: panelRect ? Math.round(panelRect.width) : null,
    };
  });

  const initial = await state();
  record('one panel is open on load', initial.visibleCount === 1, `${initial.visibleCount} of ${initial.total}`);
  record('the example column is much wider than the product list',
    initial.panelWidth !== null && initial.navWidth !== null && initial.panelWidth >= initial.navWidth * 3,
    `list ${initial.navWidth}px, example ${initial.panelWidth}px`);
  record('the first panel is the one open', initial.visibleId === 'type-menu_board', String(initial.visibleId));
  record('the first tab is marked selected', initial.selectedHref === '#type-menu_board', String(initial.selectedHref));

  // Click a tab from a different family than the one on screen.
  await page.click('[role="tab"][aria-controls="type-keychain"]');
  await page.waitForTimeout(200);
  const afterClick = await state();
  record('clicking a product opens only its panel', afterClick.visibleCount === 1 && afterClick.visibleId === 'type-keychain',
    `${afterClick.visibleCount} visible, ${afterClick.visibleId}`);
  record('clicking a product marks its tab selected', afterClick.selectedHref === '#type-keychain', String(afterClick.selectedHref));
  record('the page did not grow into a full list', afterClick.pageHeight <= initial.pageHeight + 10,
    `${initial.pageHeight} -> ${afterClick.pageHeight}`);
  record('the product list is still on screen after switching', afterClick.navVisible,
    `nav top ${afterClick.navTop}, bottom ${afterClick.navBottom} of ${afterClick.viewportHeight}`);

  // Switching again from further down the page must not require scrolling up.
  await page.evaluate(() => window.scrollBy(0, 600));
  await page.waitForTimeout(150);
  const scrolled = await state();
  record('the product list stays pinned while reading', scrolled.navVisible,
    `nav top ${scrolled.navTop} after scrolling`);
  await page.click('[role="tab"][aria-controls="type-lanyard"]');
  await page.waitForTimeout(200);
  const afterDeepSwitch = await state();
  record('a product can be chosen from a scrolled position',
    afterDeepSwitch.visibleId === 'type-lanyard' && afterDeepSwitch.navVisible,
    `${afterDeepSwitch.visibleId}, nav top ${afterDeepSwitch.navTop}`);

  // Deep link straight to a panel.
  await page.goto(base + '/use-cases#type-mini_tag', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(200);
  const deepLink = await state();
  record('deep link opens the linked product', deepLink.visibleCount === 1 && deepLink.visibleId === 'type-mini_tag',
    `${deepLink.visibleCount} visible, ${deepLink.visibleId}`);

  // Keyboard navigation.
  await page.goto(base + '/use-cases', { waitUntil: 'domcontentloaded' });
  await page.focus('[role="tab"][aria-controls="type-menu_board"]');
  await page.keyboard.press('ArrowRight');
  await page.waitForTimeout(200);
  const afterKey = await state();
  record('arrow key moves to the next product', afterKey.visibleId === 'type-poster', String(afterKey.visibleId));

  await context.close();
}

await browser.close();
console.log(`\n${failures === 0 ? `All ${checks} layout checks passed.` : `${failures} of ${checks} layout checks failed.`}`);
process.exit(failures === 0 ? 0 : 1);
