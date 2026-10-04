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
const overlapPath = args.get('overlaps') || null;
const overflowPath = args.get('overflow') || null;

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

if (overlapPath) {
  const context = await browser.newContext({
    viewport: { width: Number(args.get('width') || 1440), height: 1000 },
    colorScheme: args.get('dark') === 'true' ? 'dark' : 'light',
  });
  const page = await context.newPage();
  await page.goto(base + overlapPath, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(250);

  const found = await page.evaluate(() => {
    const textTags = ['H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'P', 'LI', 'DT', 'DD', 'FIGCAPTION', 'BUTTON', 'TD', 'TH'];
    const nodes = [...document.querySelectorAll(textTags.join(','))].filter((el) => {
      const text = (el.textContent || '').trim();
      if (text.length < 2) return false;
      const rect = el.getBoundingClientRect();
      if (rect.width < 8 || rect.height < 8) return false;
      if (rect.bottom < 0 || rect.top > window.innerHeight * 6) return false;
      const style = getComputedStyle(el);
      return style.visibility !== 'hidden' && style.display !== 'none' && parseFloat(style.opacity) > 0.05;
    });

    const overlaps = [];
    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const a = nodes[i];
        const b = nodes[j];
        if (a.contains(b) || b.contains(a)) continue;
        const ra = a.getBoundingClientRect();
        const rb = b.getBoundingClientRect();
        const x = Math.min(ra.right, rb.right) - Math.max(ra.left, rb.left);
        const y = Math.min(ra.bottom, rb.bottom) - Math.max(ra.top, rb.top);
        if (x > 6 && y > 6) {
          overlaps.push({
            a: a.tagName + '.' + (a.className || '').toString().split(' ').slice(0, 2).join('.'),
            b: b.tagName + '.' + (b.className || '').toString().split(' ').slice(0, 2).join('.'),
            overlap: Math.round(x) + 'x' + Math.round(y),
            aText: (a.textContent || '').trim().slice(0, 40),
            bText: (b.textContent || '').trim().slice(0, 40),
          });
        }
      }
    }
    return overlaps.slice(0, 20);
  });

  console.log(`${found.length} overlapping text element(s) on ${overlapPath}:`);
  for (const item of found) {
    console.log(`  ${item.overlap}  ${item.a} "${item.aText}"  ><  ${item.b} "${item.bText}"`);
  }
  await context.close();
  await browser.close();
  process.exit(0);
}

if (overflowPath) {
  const context = await browser.newContext({
    viewport: { width: Number(args.get('width') || 1440), height: 1000 },
    colorScheme: args.get('dark') === 'true' ? 'dark' : 'light',
  });
  const page = await context.newPage();
  await page.goto(base + overflowPath, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(250);

  const found = await page.evaluate(() => {
    const out = [];
    document.querySelectorAll('body *').forEach((el) => {
      const parent = el.parentElement;
      if (!parent || parent === document.body) return;
      const style = getComputedStyle(el);
      if (style.display === 'none' || style.visibility === 'hidden') return;
      // Content of a closed <details> is not rendered: not a layout bug.
      const closed = el.closest('details:not([open])');
      if (closed && !el.closest('summary')) return;
      const parentStyle = getComputedStyle(parent);
      if (['hidden', 'auto', 'scroll'].includes(parentStyle.overflow)
        || ['hidden', 'auto', 'scroll'].includes(parentStyle.overflowX)
        || ['hidden', 'auto', 'scroll'].includes(parentStyle.overflowY)) {
        return;
      }
      const r = el.getBoundingClientRect();
      const p = parent.getBoundingClientRect();
      if (r.width < 4 || r.height < 4 || p.width < 4) return;
      const over = {
        right: Math.round(r.right - p.right),
        bottom: Math.round(r.bottom - p.bottom),
        left: Math.round(p.left - r.left),
      };
      const worst = Math.max(over.right, over.bottom, over.left);
      if (worst > 4) {
        out.push({
          el: el.tagName + '.' + (el.className || '').toString().split(' ').slice(0, 2).join('.'),
          parent: parent.tagName + '.' + (parent.className || '').toString().split(' ').slice(0, 2).join('.'),
          over,
          size: Math.round(r.width) + 'x' + Math.round(r.height),
          parentSize: Math.round(p.width) + 'x' + Math.round(p.height),
        });
      }
    });
    return out.slice(0, 20);
  });

  console.log(`${found.length} element(s) overflow their parent on ${overflowPath}:`);
  for (const item of found) {
    console.log(`  ${item.el} (${item.size}) in ${item.parent} (${item.parentSize}): right+${item.over.right} bottom+${item.over.bottom} left+${item.over.left}`);
  }
  await context.close();
  await browser.close();
  process.exit(0);
}

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

    // Photographs sit below the fold and are lazy-loaded, so ask for them all
    // and wait until each one has either arrived or failed.
    await page.evaluate(() => {
      document.querySelectorAll('img[loading="lazy"]').forEach((img) => {
        img.loading = 'eager';
      });
    });
    await page.waitForFunction(
      () => [...document.images].every((img) => img.complete),
      null,
      { timeout: 10000 },
    ).catch(() => {});

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
      const qr = document.querySelector('.qr-row-code svg, .tile-media svg, .qr-frame svg, .qr-preview svg');
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

      // Text or boxes that stick out of their container read as "overlapping"
      // to a visitor, so every page is checked for it.
      const overflowing = [];
      const spilling = [];
      document.querySelectorAll('body *').forEach((el) => {
        const parent = el.parentElement;
        if (!parent || parent === document.body) return;
        const style = getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden') return;
        if (el.closest('details:not([open])') && !el.closest('summary')) return;
        const parentStyle = getComputedStyle(parent);
        if (['hidden', 'auto', 'scroll'].includes(parentStyle.overflow)
          || ['hidden', 'auto', 'scroll'].includes(parentStyle.overflowX)
          || ['hidden', 'auto', 'scroll'].includes(parentStyle.overflowY)) return;
        const r = el.getBoundingClientRect();
        const p = parent.getBoundingClientRect();
        if (r.width < 4 || r.height < 4 || p.width < 4) return;
        const worst = Math.max(r.right - p.right, r.bottom - p.bottom, p.left - r.left);
        if (worst > 4) {
          overflowing.push(
            el.tagName.toLowerCase() + '.' + (el.className || '').toString().split(' ')[0]
            + ' in ' + parent.tagName.toLowerCase() + '.' + (parent.className || '').toString().split(' ')[0]
            + ' by ' + Math.round(worst) + 'px'
          );
        }
      });

      // Text that is wider than its own box runs over whatever sits next to
      // it, which reads as overlapping text.
      document.querySelectorAll('p, h1, h2, h3, h4, h5, h6, li, dt, dd, a, span, strong, small, code')
        .forEach((el) => {
          if (el.children.length > 0) return;
          const text = (el.textContent || '').trim();
          if (text.length < 4) return;
          const style = getComputedStyle(el);
          if (style.display === 'none' || style.visibility === 'hidden') return;
          if (['hidden', 'auto', 'scroll'].includes(style.overflowX)) return;
          if (el.closest('details:not([open])')) return;
          if (el.clientWidth > 0 && el.scrollWidth > el.clientWidth + 2) {
            spilling.push(
              el.tagName.toLowerCase() + '.' + (el.className || '').toString().split(' ')[0]
              + ' spills ' + (el.scrollWidth - el.clientWidth) + 'px: "' + text.slice(0, 30) + '…"'
            );
          }
        });

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
        overflowing: overflowing.slice(0, 3),
        spilling: spilling.slice(0, 3),
        brokenImages: [...document.images]
          .filter((img) => img.complete && img.naturalWidth === 0)
          .map((img) => img.getAttribute('src') || '(no src)')
          .slice(0, 4),
        imagesWithoutAlt: [...document.images]
          .filter((img) => !img.hasAttribute('alt'))
          .map((img) => img.getAttribute('src') || '(no src)')
          .slice(0, 4),
        // The three step tiles are <picture> elements: the animation for most
        // visitors, the still for anyone who asked for less motion.
        stepSources: [...document.querySelectorAll('.tile-media picture img')]
          .map((img) => (img.currentSrc || '').split('?')[0].split('/').pop()),
      };
    });

    record(`${target.name}: no horizontal overflow`, report.scrollWidth <= report.clientWidth + 1, `${report.scrollWidth} > ${report.clientWidth}`);
    record(`${target.name}: exactly one h1`, report.h1Count === 1, `found ${report.h1Count}`);
    record(`${target.name}: h1 fits in two lines`, report.h1Lines <= 2, `${report.h1Lines} lines`);
    record(`${target.name}: no em dash in visible copy`, !report.hasEmDash);
    record(`${target.name}: documents a language`, /^[a-z]{2}$/.test(report.lang), report.lang);
    record(`${target.name}: has a skip link`, report.skipLink);
    record(`${target.name}: nothing overflows its container`, report.overflowing.length === 0,
      report.overflowing.join('; '));
    record(`${target.name}: no text spills out of its box`, report.spilling.length === 0,
      report.spilling.join('; '));
    record(`${target.name}: every image loads`, report.brokenImages.length === 0,
      report.brokenImages.join('; '));
    record(`${target.name}: every image has alt text`, report.imagesWithoutAlt.length === 0,
      report.imagesWithoutAlt.join('; '));
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
      record('home: the three step tiles serve the animation',
        report.stepSources.length === 3 && report.stepSources.every((name) => name.endsWith('.webp')),
        report.stepSources.join(', ') || 'no picture tiles found');
      record('home: hero follows the header directly', report.heroOffset >= 0 && report.heroOffset < 90,
        `${report.heroOffset}px (${report.heroGeometry})`);

      // The guest Wi-Fi tile draws the code itself. A cropped or shrunken code
      // still looks perfectly fine in a screenshot and silently stops working,
      // so the rendered card is decoded here, in a real browser.
      const codeCard = await page.$('.tile-media-code .code-card');
      if (codeCard !== null) {
        const shot = await codeCard.screenshot();
        const decoded = await page.evaluate(async (base64) => {
          if (typeof BarcodeDetector === 'undefined') return null;
          const image = new Image();
          image.src = 'data:image/png;base64,' + base64;
          await image.decode();
          const detector = new BarcodeDetector({ formats: ['qr_code'] });
          return (await detector.detect(image)).map((code) => code.rawValue);
        }, shot.toString('base64'));
        record('home: the guest Wi-Fi code still scans',
          decoded === null || decoded.some((value) => value.startsWith('WIFI:')),
          decoded === null
            ? 'BarcodeDetector unavailable in this browser, check skipped'
            : decoded.join(' | ') || 'not decoded');
      } else {
        record('home: the guest Wi-Fi code still scans', false, 'no .tile-media-code .code-card found');
      }

      // The extra tiles are a 16:9 band, and the drawn code must not stretch
      // its tile out of shape the way an unsized SVG would.
      const codeTile = await page.evaluate(() => {
        const box = document.querySelector('.tile-media-code');
        if (!box) return null;
        const rect = box.getBoundingClientRect();
        return { width: Math.round(rect.width), height: Math.round(rect.height) };
      });
      const codeRatio = codeTile ? codeTile.width / codeTile.height : 0;
      record('home: the Wi-Fi tile keeps the 16:9 shape',
        codeTile !== null && Math.abs(codeRatio - 16 / 9) < 0.12,
        codeTile ? `${codeTile.width}x${codeTile.height} ratio ${codeRatio.toFixed(2)}` : 'tile missing');
    }
  }

  await context.close();
}

/* ------------------------------------------------------- contrast/mode -- */

{
  console.log('\ndark appearance');
  const context = await browser.newContext({
    viewport: { width: 1440, height: 1000 },
    colorScheme: 'dark',
  });
  const page = await context.newPage();
  const darkPages = ['/', '/use-cases', '/demo', '/privacy', '/start'];

  for (const path of darkPages) {
    await page.goto(base + path, { waitUntil: 'domcontentloaded' });
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
        return { r: 0, g: 0, b: 0, a: 1 };
      };
      const contrast = (element) => {
        if (!element) return null;
        const colour = toRgb(getComputedStyle(element).color);
        if (!colour) return null;
        const background = effectiveBackground(element);
        const l1 = luminance(colour);
        const l2 = luminance(background);
        return Number(((Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05)).toFixed(2));
      };

      const visible = (element) => Boolean(element) && element.getBoundingClientRect().height > 0;
      const cta = [
        document.querySelector('.hero-actions .btn-primary'),
        ...document.querySelectorAll('.btn-primary'),
      ].find(visible) || null;
      const body = document.querySelector('main p') || null;
      const pageBackground = toRgb(getComputedStyle(document.body).backgroundColor);

      return {
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
        backgroundLuminance: pageBackground ? Number(luminance(pageBackground).toFixed(3)) : null,
        ctaContrast: contrast(cta),
        bodyContrast: contrast(body),
      };
    });

    record(`${path}: dark appearance is applied`, report.backgroundLuminance !== null && report.backgroundLuminance < 0.1,
      `background luminance ${report.backgroundLuminance}`);
    record(`${path}: no horizontal overflow in dark mode`, report.scrollWidth <= report.clientWidth + 1,
      `${report.scrollWidth} > ${report.clientWidth}`);
    record(`${path}: CTA contrast in dark mode >= 4.5`, report.ctaContrast === null || report.ctaContrast >= 4.5,
      String(report.ctaContrast));
    record(`${path}: body contrast in dark mode >= 4.5`, report.bodyContrast === null || report.bodyContrast >= 4.5,
      String(report.bodyContrast));
  }

  await context.close();
}

/* --------------------------------------------- reduced motion fallback -- */

{
  console.log('\nreduced motion on /');
  const context = await browser.newContext({
    viewport: { width: 1440, height: 1000 },
    reducedMotion: 'reduce',
  });
  const page = await context.newPage();
  await page.goto(base + '/', { waitUntil: 'domcontentloaded' });
  // Ask for the lazy images so the browser commits to a source.
  await page.evaluate(() => {
    document.querySelectorAll('img[loading="lazy"]').forEach((img) => {
      img.loading = 'eager';
    });
  });
  await page.waitForFunction(
    () => [...document.images].every((img) => img.complete),
    null,
    { timeout: 10000 },
  ).catch(() => {});

  const sources = await page.evaluate(() => [...document.querySelectorAll('.tile-media picture img')]
    .map((img) => (img.currentSrc || '').split('?')[0].split('/').pop()));

  // An animated WebP cannot be paused from CSS, so the still has to be chosen
  // in markup. This guards that decision.
  record('home: reduced motion gets the still frame, not the loop',
    sources.length === 3 && sources.every((name) => name.endsWith('-still.webp')),
    sources.join(', ') || 'no picture tiles found');

  await context.close();
}

/* ------------------------------------------------- tab behaviour check -- */

/* ------------------------------------------------- product code checks -- */

{
  console.log('\nQR codes on /use-cases');
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  await page.goto(base + '/use-cases', { waitUntil: 'domcontentloaded' });

  const panels = await page.evaluate(() => [...document.querySelectorAll('.type-panel')].map((panel) => panel.id));
  const failures = [];
  let checked = 0;
  let supported = true;

  for (const id of panels) {
    // The panels swap on :target, so the one being checked has to be the
    // visible one before it can be screenshotted.
    await page.evaluate((target) => {
      window.location.hash = '#' + target;
    }, id);
    await page.waitForTimeout(120);

    const panel = await page.$('#' + id);
    const mark = panel ? await panel.$('.example-code-mark') : null;
    if (mark === null) {
      failures.push(`${id}: no code shown`);
      continue;
    }

    const expected = await panel.$eval('.example-code-body .example-url code', (el) => el.textContent.trim());
    const shot = await mark.screenshot();
    const decoded = await page.evaluate(async (base64) => {
      if (typeof BarcodeDetector === 'undefined') return null;
      const image = new Image();
      image.src = 'data:image/png;base64,' + base64;
      await image.decode();
      const detector = new BarcodeDetector({ formats: ['qr_code'] });
      return (await detector.detect(image)).map((code) => code.rawValue);
    }, shot.toString('base64'));

    if (decoded === null) {
      supported = false;
      break;
    }
    if (!decoded.includes(expected)) {
      failures.push(`${id}: ${decoded[0] || 'did not decode'}`);
      continue;
    }
    checked += 1;
  }

  record('use-cases: every product shows a code that decodes to its own address',
    !supported || (failures.length === 0 && checked === panels.length),
    supported
      ? (failures.join('; ') || `${checked} of ${panels.length} checked`)
      : 'BarcodeDetector unavailable in this browser, check skipped');

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
