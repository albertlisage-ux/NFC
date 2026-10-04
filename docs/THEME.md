# Theme

The interface uses Apple's design language as served on developer.apple.com:
the same design tokens (their "sk" system), the same type scale, the same
12 column tile grid, and the same dark values when the device asks for a dark
appearance. One accent colour, no gradients, no decorative colour, no emoji.

## Where the values come from

Apple's public stylesheets were read directly (`global.dist.css` and
`dark-mode.css`) instead of approximating from screenshots, so the values below
are Apple's own.

| Apple token | Value (light) | Value (dark) |
|-------------|---------------|--------------|
| body text | `rgb(29,29,31)` | `#f5f5f7` |
| body background | `rgb(255,255,255)` | `#000` |
| secondary text | `rgb(110,110,115)` | `#86868b` |
| filled section | `#fafafa` (`bg-light`) | `rgba(255,255,255,.1)` |
| hairline | `#d2d2d7` | `#424245` |
| link | `rgb(0,102,204)` | `#2997ff` |
| interactive fill | `#0071e3` | `#0071e3` |
| focus ring | `#0071e3` | `#0071e3` |
| secondary fills | `#fff9f4`, `#fff2f4`, `#f5fff6` | `#290d00`, `#300`, `#002b03` |

The font is SF Pro on Apple devices. Apple serves its fonts from an
Apple-hosted service that is licensed for Apple's own pages, so this site uses
the system font instead and falls back through Apple's own fallback list
(`Helvetica Neue`, `Helvetica`, `Arial`). No web font request, no layout shift.

## Page format

Copied from `developer.apple.com/design/resources`:

- the content column is 980px wide, centred
- a section is a heading (40px), an optional 21px subtitle, then a grid
- the grid is 12 columns: a tile spans 4 (three per row), 6 (two per row) or 12
  (one per row), collapsing at 1068px and 734px exactly like Apple's
- a tile is a preview image, a 17px semibold title, one line of 14px grey text
  and a list of links. Links are a small blue glyph plus a semibold label
- bands alternate between white and `#fafafa`

## Palette

| Token | Value | Used for |
|-------|-------|----------|
| `--ink` | `#1d1d1f` | primary text, headings (16.4:1 on white) |
| `--ink-soft` | `#424245` | secondary text |
| `--muted` | `#6e6e73` | captions, hints (5.1:1 on white) |
| `--line` | `#d2d2d7` | borders, dividers |
| `--line-soft` | `#e8e8ed` | hairlines inside cards |
| `--canvas` | `#f5f5f7` | page background, alternating sections |
| `--surface` | `#ffffff` | cards, panels, forms |
| `--accent` | `#0071e3` | buttons, links, active states (4.7:1 with white) |
| `--accent-dark` | `#0066cc` | hover, accent text on tint |
| `--accent-tint` | `#f2f7ff` | accent surfaces |
| `--accent-line` | `#cfe1f7` | accent borders |

Status colours carry meaning and are checked for contrast on their own tint:

| State | Text | Tint |
|-------|------|------|
| Active / success | `#0b7a3f` | `#eef8f1` |
| Warning (lost) | `#8a5a00` | `#fff7e6` |
| Danger (delete) | `#c1121f` | `#fdf3f3` |
| Information | `#0b63b8` | `#f0f6fd` |
| Neutral | `#6e6e73` | `#f0f0f2` |

WhatsApp green (`#25d366` with dark text) is the one exception, because the
button has to be recognisable as WhatsApp.

## Type

```css
font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "SF Pro Display",
             "Helvetica Neue", Helvetica, Arial, sans-serif;
```

On Apple devices this renders SF Pro, the same font Apple's own pages use; the
rest of the stack is Apple's documented fallback list. No web font is loaded, so
there is no third-party request and no layout shift.

- Body: 17px / 1.47 (Apple's web body size)
- Headings: weight 600, line-height 1.1, letter-spacing -0.022em
- Monospace (URLs, NFC payloads): `ui-monospace, "SF Mono", Menlo, monospace`

## Shape and surface

| Element | Radius |
|---------|--------|
| Buttons, pills, badges | fully rounded (`980px`) |
| Inputs, selects, small controls | 12px |
| Cards, panels, forms, media | 18px |

Shadows are soft and neutral (`0 6px 24px rgba(0,0,0,.07)`), never black and
never coloured. The sticky header uses the translucent blurred bar Apple uses
on its own site, with a hairline border instead of a shadow.

## Motion

Colour and border transitions at 150ms, a `scale(.98)` press on buttons, and
everything disabled under `prefers-reduced-motion: reduce`.

## Where it lives

All tokens are in the `:root` block of `assets/css/portal.css`. Nothing else
hard-codes a colour, so a rebrand is that one block.

## Verified

The layout suite (`node scripts/ui-check.mjs`) measures the rendered contrast of
the primary button and of body text on every page, at desktop and mobile widths,
and fails below the WCAG AA threshold of 4.5:1.
