#!/usr/bin/env python3
"""
Build the looping animations behind "How a tag works".

Each tile shows a real photograph, and this script turns that photograph into
a short, seamless loop: a slow push in, one soft light sweep, and a single
motion accent that says what the step does.

    create.jpg  ->  create.webp   a ring pulse, the tag being registered
    write.jpg   ->  write.webp    a progress bar, data going onto the chip
    scan.jpg    ->  scan.webp     a band sweeping down, the code being read

The loop is seamless because the zoom eases in and back out over one cycle,
and the sweep and the accent both start and end invisible.

Requirements

    python3 -m venv .venv && .venv/bin/pip install pillow
    .venv/bin/python scripts/make-scene-animations.py

libwebp's `img2webp` has to be on PATH (brew install webp).

The stills stay in the repository as the reduced-motion fallback and as the
poster frame, so a browser that does not want animation shows exactly what it
showed before.
"""

import argparse
import math
import os
import shutil
import subprocess
import sys
import tempfile

try:
    from PIL import Image, ImageDraw, ImageFilter
except ImportError:  # pragma: no cover - a missing dependency is a hard stop
    sys.exit('Pillow is missing. See the header of this file for the two commands.')

ACCENT = (0, 113, 227)          # the site accent, #0071e3
OUT_SIZE = (720, 450)           # 16:10, comfortably above the size the tile is drawn at
FRAMES = 32
FRAME_MS = 150                  # 4.8 seconds per loop
ZOOM_MAX = 1.07
# Drift as a share of the frame, so a different output size keeps the same feel.
DRIFT_FRACTION = (0.0175, 0.016)
SWEEP_OPACITY = 0.10
DEFAULT_QUALITY = 52
# Frames are delivered at about seven per second, which is fine for slow,
# soft motion and much too slow for a hard edge. Every accent is therefore
# blurred into a gradient instead of a line.
SOFTNESS = 3


def ken_burns(source, index):
    """Crop a zoomed window out of the photo, easing in and back out."""
    width, height = OUT_SIZE
    t = index / FRAMES
    zoom = 1.0 + (ZOOM_MAX - 1.0) * (0.5 - 0.5 * math.cos(2.0 * math.pi * t))
    source_width, source_height = source.size
    crop_width = source_width / zoom
    crop_height = source_height / zoom
    # A gentle drift keeps the loop from looking like a plain zoom.
    drift_x = DRIFT_FRACTION[0] * source_width / 1.6
    drift_y = DRIFT_FRACTION[1] * source_height
    centre_x = source_width / 2.0 + drift_x * math.sin(2.0 * math.pi * t)
    centre_y = source_height / 2.0 + drift_y * math.sin(2.0 * math.pi * t)
    left = min(max(0.0, centre_x - crop_width / 2.0), source_width - crop_width)
    top = min(max(0.0, centre_y - crop_height / 2.0), source_height - crop_height)
    box = (left, top, left + crop_width, top + crop_height)
    return source.crop(box).resize((width, height), Image.LANCZOS)


def sweep_mask(t):
    """A soft diagonal band of light travelling across the frame once."""
    width, height = OUT_SIZE
    ramp_width = 512
    ramp = Image.new('L', (ramp_width, 1))
    for x in range(ramp_width):
        u = x / (ramp_width - 1)
        ramp.putpixel((x, 0), int(255 * (1.0 - abs(2.0 * u - 1.0))))
    band_width = int(width * 1.6)
    band = ramp.resize((band_width, height), Image.BILINEAR)
    mask = Image.new('L', OUT_SIZE, 0)
    # Travels from fully off one side to fully off the other, so the loop closes.
    offset = int(-band_width + (width + band_width) * t)
    mask.paste(band, (offset, 0))
    mask = mask.rotate(-16, resample=Image.BILINEAR, expand=False)
    return mask.filter(ImageFilter.GaussianBlur(SOFTNESS * 2))


def accent_create(t):
    """An expanding ring, the tag being registered."""
    width, height = OUT_SIZE
    mask = Image.new('L', OUT_SIZE, 0)
    draw = ImageDraw.Draw(mask)
    centre_x, centre_y = width * 0.5, height * 0.55
    for step in range(2):
        phase = (t + step * 0.5) % 1.0
        radius = (0.06 + 0.44 * phase) * width
        alpha = int(150 * (1.0 - phase) ** 1.6)
        if alpha <= 0:
            continue
        draw.ellipse(
            [centre_x - radius, centre_y - radius, centre_x + radius, centre_y + radius],
            outline=alpha,
            width=max(3, int(width * 0.006)),
        )
    return mask.filter(ImageFilter.GaussianBlur(SOFTNESS))


def accent_write(t):
    """A progress bar, the link going onto the chip."""
    width, height = OUT_SIZE
    mask = Image.new('L', OUT_SIZE, 0)
    draw = ImageDraw.Draw(mask)
    pad = int(width * 0.08)
    bar_height = max(4, int(height * 0.010))
    y = height - int(height * 0.11)
    draw.rounded_rectangle([pad, y, width - pad, y + bar_height], radius=bar_height // 2, fill=50)
    progress = min(1.0, t * 1.06)
    # Fades away at the end so the next loop starts on an empty bar.
    fade = 1.0 if t < 0.78 else max(0.0, (1.0 - t) / 0.22)
    if progress > 0.01 and fade > 0:
        draw.rounded_rectangle(
            [pad, y, pad + (width - 2 * pad) * progress, y + bar_height],
            radius=bar_height // 2,
            fill=int(215 * fade),
        )
    return mask.filter(ImageFilter.GaussianBlur(SOFTNESS))


def accent_scan(t):
    """A band with a bright leading edge, the code being read."""
    width, height = OUT_SIZE
    mask = Image.new('L', OUT_SIZE, 0)
    band_height = int(height * 0.30)
    ramp = Image.new('L', (1, band_height))
    for y in range(band_height):
        u = y / (band_height - 1)
        # Brightest right at the leading edge, fading smoothly behind it.
        ramp.putpixel((0, y), int(96 * (u ** 3)))
    band = ramp.resize((width, band_height))
    top = int(-band_height + (height + band_height) * t)
    mask.paste(band, (0, top))
    return mask.filter(ImageFilter.GaussianBlur(SOFTNESS))


ACCENTS = {
    'create': accent_create,
    'write': accent_write,
    'scan': accent_scan,
}


def build(name, source_path, output_path, keep_frames=None, quality=DEFAULT_QUALITY, min_size=True):
    accent = ACCENTS[name]
    source = Image.open(source_path).convert('RGB')
    frame_dir = keep_frames or tempfile.mkdtemp(prefix='dat-scene-')
    os.makedirs(frame_dir, exist_ok=True)
    frame_paths = []

    for index in range(FRAMES):
        t = index / FRAMES
        frame = ken_burns(source, index)
        frame = Image.composite(
            Image.new('RGB', OUT_SIZE, (255, 255, 255)),
            frame,
            sweep_mask(t).point(lambda value: int(value * SWEEP_OPACITY)),
        )
        frame = Image.composite(Image.new('RGB', OUT_SIZE, ACCENT), frame, accent(t))
        path = os.path.join(frame_dir, 'frame-%03d.png' % index)
        frame.save(path)
        frame_paths.append(path)

    command = ['img2webp', '-loop', '0', '-lossy', '-q', str(quality), '-m', '6']
    if min_size:
        command.append('-min_size')
    for path in frame_paths:
        command += ['-d', str(FRAME_MS), path]
    command += ['-o', output_path]
    subprocess.run(command, check=True)

    if keep_frames is None:
        shutil.rmtree(frame_dir, ignore_errors=True)

    size_kb = os.path.getsize(output_path) // 1024
    print('%-8s %2d frames, %d KB -> %s' % (name, FRAMES, size_kb, output_path))


def main():
    global FRAMES, FRAME_MS, OUT_SIZE

    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    scenes = os.path.join(root, 'assets', 'img', 'scenes')

    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--only', help='build one animation, for example --only scan')
    parser.add_argument('--keep-frames', help='write the PNG frames here instead of a temp directory')
    parser.add_argument('--frames', type=int, help='frames per loop (default %d)' % FRAMES)
    parser.add_argument('--duration', type=int, help='milliseconds per frame (default %d)' % FRAME_MS)
    parser.add_argument('--width', type=int, help='output width (default %d)' % OUT_SIZE[0])
    parser.add_argument('--quality', type=int, help='webp quality (default %d)' % DEFAULT_QUALITY)
    parser.add_argument('--no-min-size', action='store_true', help='skip the encoder size optimisation')
    args = parser.parse_args()

    if shutil.which('img2webp') is None:
        sys.exit('img2webp is missing. Install it with: brew install webp')

    if args.frames:
        FRAMES = args.frames
    if args.duration:
        FRAME_MS = args.duration
    if args.width:
        width = args.width
        OUT_SIZE = (width, round(width * 10 / 16))

    quality = args.quality or DEFAULT_QUALITY
    names = [args.only] if args.only else list(ACCENTS)
    for name in names:
        if name not in ACCENTS:
            sys.exit('Unknown animation: %s' % name)
        source = os.path.join(scenes, name + '.jpg')
        if not os.path.isfile(source):
            sys.exit('Missing still: %s' % source)
        build(
            name,
            source,
            os.path.join(scenes, name + '.webp'),
            args.keep_frames,
            quality=quality,
            min_size=not args.no_min_size,
        )


if __name__ == '__main__':
    main()
