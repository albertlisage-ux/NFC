#!/usr/bin/env python3
"""
Draw the three pictures behind "How a tag works".

Each one is a small diagram of what that step actually does, drawn here rather
than photographed, so it cannot drift away from the feature:

    create  a form on a phone, and the tag it produces
    write   a chip in a tag, and the code being written onto it
    scan    a tag being tapped, and the message that comes back

The drawings are flat shapes on a transparent background, in the site accent
plus two greys. That is deliberate: one set of files then reads correctly on
both themes, because a grey at low alpha darkens a white page and lightens a
black one. No text is drawn, so the pictures work in either language.

Requirements

    python3 -m venv .venv && .venv/bin/pip install pillow
    .venv/bin/python scripts/make-step-illustrations.py

libwebp's `img2webp` has to be on PATH (brew install webp).

Shapes are drawn at twice the output size and scaled down, because Pillow does
not antialias its own shapes. Every file is written next to its still, and the
still is what a visitor with reduced motion gets.
"""

import argparse
import math
import os
import shutil
import subprocess
import sys
import tempfile

try:
    from PIL import Image, ImageDraw
except ImportError:  # pragma: no cover - a missing dependency is a hard stop
    sys.exit('Pillow is missing. See the header of this file for the two commands.')

OUT_SIZE = (640, 400)
FRAMES = 32
FRAME_MS = 150
SUPERSAMPLE = 2

ACCENT = (10, 132, 255)         # #0a84ff, 3.5:1 on white and 5:1 on black
GREY = (118, 118, 123)          # a mid grey: 4.3:1 on white and 4:1 on black
WHITE = (255, 255, 255)

# Anything that has to be read is drawn in a solid mid grey or in the accent,
# because only a mid tone clears 3:1 against both a white and a black page.
# Washes are used for surfaces, which are meant to be felt rather than read:
# a grey wash darkens a white page and lightens a black one, so the same file
# works on both themes. A single set of drawings is the whole point.
PANEL = 0.14
SCREEN = 0.07
MARK = 1.0

# The whole drawing is pushed in slowly and eased back out, so the loop closes.
# Flat art only ever moves at its edges, so without this the pictures read as
# static however much the small accents wiggle.
ZOOM = 1.10
DRIFT = (0.012, 0.008)

BACKGROUNDS = {'light': (250, 250, 250), 'dark': (22, 22, 23)}


def ink(colour, alpha):
    """An RGBA colour for ImageDraw, which blends when the draw mode is RGBA."""
    return colour + (max(0, min(255, int(round(255 * alpha)))),)


class Canvas:
    """A supersampled RGBA canvas with helpers that take output coordinates."""

    def __init__(self):
        self.scale = SUPERSAMPLE
        self.image = Image.new('RGBA', (OUT_SIZE[0] * self.scale, OUT_SIZE[1] * self.scale), (0, 0, 0, 0))
        self.draw = ImageDraw.Draw(self.image, 'RGBA')

    def box(self, values):
        return [round(value * self.scale) for value in values]

    def panel(self, values, radius, fill=None, outline=None, width=1.5):
        self.draw.rounded_rectangle(
            self.box(values), radius=radius * self.scale, fill=fill,
            outline=outline, width=max(1, round(width * self.scale)),
        )

    def bar(self, x, y, width, height, colour):
        if width <= 0:
            return
        self.draw.rounded_rectangle(
            self.box([x, y, x + width, y + height]),
            radius=height * self.scale / 2, fill=colour,
        )

    def disc(self, cx, cy, radius, fill=None, outline=None, width=1.5):
        self.draw.ellipse(
            self.box([cx - radius, cy - radius, cx + radius, cy + radius]),
            fill=fill, outline=outline, width=max(1, round(width * self.scale)),
        )

    def wave(self, cx, cy, radius, start, end, colour, width=2.0):
        self.draw.arc(
            self.box([cx - radius, cy - radius, cx + radius, cy + radius]),
            start, end, fill=colour, width=max(1, round(width * self.scale)),
        )

    def dashed(self, x1, y1, x2, y2, colour, phase, dash=10, gap=8, width=2.0):
        """A dashed line whose dashes travel, used for "data goes here"."""
        length = math.hypot(x2 - x1, y2 - y1)
        if length <= 0:
            return
        ux, uy = (x2 - x1) / length, (y2 - y1) / length
        step = dash + gap
        offset = -((phase % 1.0) * step)
        position = offset
        while position < length:
            start = max(0.0, position)
            end = min(length, position + dash)
            if end > start:
                self.draw.line(
                    self.box([x1 + ux * start, y1 + uy * start, x1 + ux * end, y1 + uy * end]),
                    fill=colour, width=max(1, round(width * self.scale)),
                )
            position += step

    def arrow(self, x, y, direction, size, colour):
        """A small triangle, pointing right (1) or left (-1)."""
        self.draw.polygon(
            self.box([
                x, y - size,
                x + direction * size * 1.4, y,
                x, y + size,
            ]),
            fill=colour,
        )

    def packet(self, x1, y1, x2, y2, phase, radius=8):
        """A dot travelling from one object to another, once per cycle.

        It fades in at the start and out at the end, so the loop closes even
        though the journey has a direction.
        """
        progress = phase % 1.0
        strength = min(1.0, min(progress, 1.0 - progress) / 0.18)
        if strength <= 0.02:
            return
        self.disc(
            x1 + (x2 - x1) * progress,
            y1 + (y2 - y1) * progress,
            radius,
            fill=ink(ACCENT, strength),
        )

    def render(self):
        """The supersampled drawing. Framing and scaling happen in build()."""
        return self.image


def qr_glyph(canvas, x, y, size, colour, shimmer=None):
    """A recognisable code mark: three finder squares and a field of modules.

    `shimmer` takes (row, column) and returns 0..1, which lets a wave travel
    across the modules without the mark ever going away.
    """
    cell = size / 11.0
    finder = cell * 3
    for fx, fy in ((0, 0), (1, 0), (0, 1)):
        left = x + fx * (size - finder)
        top = y + fy * (size - finder)
        canvas.draw.rectangle(canvas.box([left, top, left + finder, top + finder]), fill=colour)
        inner = cell
        canvas.draw.rectangle(
            canvas.box([left + inner, top + inner, left + finder - inner, top + finder - inner]),
            fill=(0, 0, 0, 0),
        )
    for row in range(4, 11):
        for column in range(0, 11):
            if row < 4 and (column < 4 or column > 6):
                continue
            if (row + column) % 3 == 0:
                continue
            strength = 1.0 if shimmer is None else max(0.0, min(1.0, shimmer(row, column)))
            if strength <= 0.02:
                continue
            tint = colour[:3] + (int(colour[3] * strength),)
            left = x + column * cell
            top = y + row * cell
            canvas.draw.rectangle(
                canvas.box([left + cell * 0.1, top + cell * 0.1, left + cell * 0.9, top + cell * 0.9]),
                fill=tint,
            )


def chip_glyph(canvas, cx, cy, size, body, pin):
    half = size / 2
    canvas.panel([cx - half, cy - half, cx + half, cy + half], size * 0.18, fill=body)
    for index in range(3):
        offset = -half + size * (index + 1) / 4
        for direction in (-1, 1):
            canvas.draw.line(
                canvas.box([cx + offset, cy - half, cx + offset, cy - half - size * 0.22]),
                fill=pin, width=max(1, round(1.8 * canvas.scale)),
            )
            canvas.draw.line(
                canvas.box([cx + offset, cy + half, cx + offset, cy + half + size * 0.22]),
                fill=pin, width=max(1, round(1.8 * canvas.scale)),
            )
            canvas.draw.line(
                canvas.box([cx - half, cy + offset, cx - half - size * 0.22, cy + offset]),
                fill=pin, width=max(1, round(1.8 * canvas.scale)),
            )
            canvas.draw.line(
                canvas.box([cx + half, cy + offset, cx + half + size * 0.22, cy + offset]),
                fill=pin, width=max(1, round(1.8 * canvas.scale)),
            )


def draw_create(t):
    """A form on a phone, and the tag it produces.

    Everything is present from the first frame; only a highlight travelling
    down the form moves. A build-up that started empty would snap back to
    empty once per loop, which reads as a glitch rather than as animation.
    """
    canvas = Canvas()
    canvas.panel([140, 50, 330, 400], 30, fill=ink(GREY, PANEL), outline=ink(GREY, MARK))
    canvas.panel([154, 66, 316, 384], 22, fill=ink(GREY, SCREEN))
    canvas.bar(215, 78, 40, 5, ink(GREY, MARK))

    # A tinted row travels down the three fields, one pass per loop.
    pass_ = t % 1.0
    strength = math.sin(math.pi * pass_) ** 2
    if strength > 0.02:
        top = 130 + pass_ * 156
        canvas.panel([168, top, 302, top + 32], 16, fill=ink(ACCENT, 0.22 * strength))

    # The fields light up in turn, which is the largest thing that moves here.
    for index in range(3):
        glow = 0.45 + 0.55 * (0.5 + 0.5 * math.sin(2 * math.pi * (t - index * 0.16)))
        canvas.bar(176, 140 + index * 52, 118, 13, ink(GREY, glow))

    canvas.panel([176, 296, 294, 330], 17, fill=ink(ACCENT, 1.0))

    # The tag it produces, floating gently, with a plus badge that breathes.
    float_y = 9 * math.sin(2 * math.pi * t)
    left = 424
    canvas.panel([left, 152 + float_y, left + 148, 300 + float_y], 26,
                 fill=ink(GREY, PANEL), outline=ink(GREY, MARK))
    canvas.disc(left + 74, 176 + float_y, 9, fill=(0, 0, 0, 0), outline=ink(GREY, MARK), width=2)
    qr_glyph(canvas, left + 34, 200 + float_y, 80, ink(GREY, MARK))
    pulse = 0.5 + 0.5 * math.sin(2 * math.pi * t)
    canvas.disc(left + 132, 282 + float_y, 18 + 2.6 * pulse, fill=ink(ACCENT, 1.0))
    plus = ink(WHITE, 1.0)
    canvas.draw.line(canvas.box([left + 124, 282 + float_y, left + 140, 282 + float_y]), fill=plus,
                     width=max(1, round(2.4 * canvas.scale)))
    canvas.draw.line(canvas.box([left + 132, 274 + float_y, left + 132, 290 + float_y]), fill=plus,
                     width=max(1, round(2.4 * canvas.scale)))

    canvas.dashed(348, 226, 412, 226, ink(ACCENT, 0.45), t * 2.0, dash=9, gap=7)
    canvas.packet(352, 226, 410, 226, t, radius=9)
    canvas.arrow(418, 226, 1, 7, ink(ACCENT, 1.0))
    return canvas.render()


def draw_write(t):
    """A chip in a tag, and the code being written onto it.

    The radio pulses outwards twice per loop and a wave crosses the modules of
    the code, so the mark stays whole while still reading as "data arriving".
    """
    canvas = Canvas()
    canvas.panel([128, 138, 306, 316], 30, fill=ink(GREY, PANEL), outline=ink(GREY, MARK))
    chip_glyph(canvas, 217, 227, 62, ink(GREY, 0.28), ink(GREY, MARK))

    for index in range(2):
        phase = (t * 2 + index * 0.5) % 1.0
        radius = 42 + phase * 66
        alpha = 0.95 * (1 - phase)
        canvas.wave(217, 227, radius, -42, 42, ink(ACCENT, alpha), width=2.8)

    canvas.dashed(330, 227, 470, 227, ink(ACCENT, 0.45), t * 2.0, dash=11, gap=9)
    canvas.packet(338, 227, 462, 227, t, radius=9)

    canvas.panel([470, 138, 648, 316], 30, fill=ink(GREY, PANEL), outline=ink(GREY, MARK))

    def shimmer(row, column):
        return 0.45 + 0.55 * (0.5 + 0.5 * math.sin(2 * math.pi * (t * 2) - (row + column) * 0.55))

    qr_glyph(canvas, 494, 162, 130, ink(GREY, MARK), shimmer)

    pulse = 0.5 + 0.5 * math.sin(2 * math.pi * t)
    canvas.disc(620, 288, 17 + 1.6 * pulse, fill=ink(ACCENT, 1.0))
    tick = ink(WHITE, 1.0)
    canvas.draw.line(canvas.box([611, 288, 617, 295]), fill=tick, width=max(1, round(2.6 * canvas.scale)))
    canvas.draw.line(canvas.box([617, 295, 630, 281]), fill=tick, width=max(1, round(2.6 * canvas.scale)))
    return canvas.render()


def draw_scan(t):
    """A tag being tapped, and the message that comes back."""
    canvas = Canvas()
    canvas.panel([104, 196, 244, 336], 30, fill=ink(GREY, PANEL), outline=ink(GREY, MARK))
    chip_glyph(canvas, 174, 266, 54, ink(GREY, 0.28), ink(GREY, MARK))

    for index in range(2):
        phase = (t * 2 + index * 0.5) % 1.0
        radius = 46 + phase * 70
        canvas.wave(174, 266, radius, -42, 42, ink(ACCENT, 0.95 * (1 - phase)), width=2.8)

    canvas.panel([420, 150, 604, 430], 34, fill=ink(GREY, PANEL), outline=ink(GREY, MARK))
    canvas.panel([436, 168, 588, 412], 24, fill=ink(GREY, SCREEN))
    canvas.bar(497, 180, 30, 5, ink(GREY, MARK))

    # The message sits above the phone and breathes, so the loop has no seam.
    bob = 8.0 * math.sin(2 * math.pi * t)
    left, width, height = 300, 250, 96
    top = 26 + bob
    canvas.panel([left, top, left + width, top + height], 24,
                 fill=ink(GREY, PANEL * 1.5), outline=ink(GREY, MARK))
    canvas.draw.polygon(
        canvas.box([left + 46, top + height - 2, left + 46, top + height + 24, left + 84, top + height - 2]),
        fill=ink(GREY, PANEL * 1.5),
    )
    canvas.bar(left + 28, top + 30, 180, 12, ink(GREY, MARK))
    shrink = 0.72 + 0.28 * (0.5 + 0.5 * math.sin(2 * math.pi * t))
    canvas.bar(left + 28, top + 56, 120 * shrink, 12, ink(ACCENT, 1.0))

    # The answer travelling back from the tag to the message.
    canvas.packet(252, 252, 340, top + 62, t, radius=9)
    return canvas.render()


STEPS = {'create': draw_create, 'write': draw_write, 'scan': draw_scan}


def quantise(image, colours):
    """Flatten the antialiased edges onto a small palette.

    The art is flat to begin with, so 96 colours costs a mean of 0.07 per
    channel and saves about 14% of the file. Anything crisper is wasted at the
    size the tiles are actually drawn.
    """
    if colours <= 0:
        return image
    flat = image.convert('RGB').quantize(colors=colours, method=Image.MEDIANCUT).convert('RGB')
    flat = flat.convert('RGBA')
    flat.putalpha(image.getchannel('A'))
    return flat


def build(name, folder, keep_frames=None, frames=FRAMES, duration=FRAME_MS, quality=78,
          lossless=True, colours=96):
    draw = STEPS[name]
    frame_dir = keep_frames or tempfile.mkdtemp(prefix='dat-step-')
    os.makedirs(frame_dir, exist_ok=True)

    # Everything is drawn first, then cropped to the area the whole animation
    # covers. Framing each frame on its own would make the drawing jitter.
    drawn = [draw(index / frames) for index in range(frames)]
    boxes = [image.getchannel('A').getbbox() for image in drawn]
    boxes = [box for box in boxes if box is not None]
    if not boxes:
        sys.exit('%s drew nothing' % name)
    left = min(box[0] for box in boxes)
    top = min(box[1] for box in boxes)
    right = max(box[2] for box in boxes)
    bottom = max(box[3] for box in boxes)

    margin = 0.07
    limit_width = OUT_SIZE[0] * SUPERSAMPLE * (1 - 2 * margin)
    limit_height = OUT_SIZE[1] * SUPERSAMPLE * (1 - 2 * margin)
    scale = min(limit_width / (right - left), limit_height / (bottom - top))
    size = (max(1, round((right - left) * scale)), max(1, round((bottom - top) * scale)))

    paths = []
    for index, image in enumerate(drawn):
        framed = image.crop((left, top, right, bottom)).resize(size, Image.LANCZOS)

        # Slow push in, eased so that frame 0 and the last frame match.
        phase = index / frames
        zoom = 1.0 + (ZOOM - 1.0) * (0.5 - 0.5 * math.cos(2 * math.pi * phase))
        window = (size[0] / zoom, size[1] / zoom)
        centre_x = size[0] / 2 + DRIFT[0] * size[0] * math.sin(2 * math.pi * phase)
        centre_y = size[1] / 2 + DRIFT[1] * size[1] * math.sin(2 * math.pi * phase)
        box = (
            centre_x - window[0] / 2,
            centre_y - window[1] / 2,
            centre_x + window[0] / 2,
            centre_y + window[1] / 2,
        )
        framed = framed.crop(box).resize(size, Image.LANCZOS)

        canvas = Image.new('RGBA', (OUT_SIZE[0] * SUPERSAMPLE, OUT_SIZE[1] * SUPERSAMPLE), (0, 0, 0, 0))
        canvas.paste(framed, ((canvas.width - size[0]) // 2, (canvas.height - size[1]) // 2))
        final = quantise(canvas.resize(OUT_SIZE, Image.LANCZOS), colours)
        path = os.path.join(frame_dir, 'frame-%03d.png' % index)
        final.save(path)
        paths.append(path)

    # The last frame is the finished state, which is the one worth showing
    # to anyone who asked for less motion.
    still = os.path.join(folder, name + '-still.webp')
    subprocess.run(['cwebp', '-quiet', '-lossless', '-exact', paths[-1], '-o', still], check=True)

    animation = os.path.join(folder, name + '.webp')
    command = ['img2webp', '-loop', '0', '-exact', '-m', '6', '-q', str(quality)]
    if lossless:
        command.append('-lossless')
    else:
        command.append('-lossy')
    for path in paths:
        command += ['-d', str(duration), path]
    command += ['-o', animation]
    subprocess.run(command, check=True)

    if keep_frames is None:
        shutil.rmtree(frame_dir, ignore_errors=True)

    print('%-7s %2d frames  animation %4d KB  still %3d KB' % (
        name, frames, os.path.getsize(animation) // 1024, os.path.getsize(still) // 1024))


def main():
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    folder = os.path.join(root, 'assets', 'img', 'steps')

    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--only', help='draw one picture, for example --only scan')
    parser.add_argument('--keep-frames', help='write the PNG frames here instead of a temp directory')
    parser.add_argument('--frames', type=int, default=FRAMES)
    parser.add_argument('--duration', type=int, default=FRAME_MS)
    parser.add_argument('--quality', type=int, default=78)
    parser.add_argument('--lossy', action='store_true',
                        help='smaller files, but flat art shows artefacts around every edge')
    parser.add_argument('--colours', type=int, default=96,
                        help='palette size per frame, 0 to keep every colour')
    args = parser.parse_args()

    for tool in ('img2webp', 'cwebp'):
        if shutil.which(tool) is None:
            sys.exit('%s is missing. Install it with: brew install webp' % tool)

    os.makedirs(folder, exist_ok=True)
    names = [args.only] if args.only else list(STEPS)
    for name in names:
        if name not in STEPS:
            sys.exit('Unknown picture: %s' % name)
        build(name, folder, args.keep_frames, args.frames, args.duration, args.quality,
              not args.lossy, args.colours)


if __name__ == '__main__':
    main()
