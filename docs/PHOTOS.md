# Photographs

Every picture on the home page and the products page is a real photograph,
not a rendering. All of them are CC0 or public domain, so they can be sold
with, printed on and modified without a royalty or an attribution notice.
The credits below are kept for provenance, not because a licence demands it.

## Products

Drop-in files for the products page and the home page tiles. Each one is
1200 x 900, 4:3, JPEG, metadata (EXIF, GPS, camera) removed.

| File | Subject | Source | Licence |
|------|---------|--------|---------|
| `menu-board.jpg` | Café with two chalkboard menus at the entrance | [WordPress Photo Directory](https://wordpress.org/photos/photo/18767ab162/) | CC0 |
| `poster.jpg` | Framed poster on a dark wall | [WordPress Photo Directory](https://wordpress.org/photos/photo/153679783a/) | CC0 |
| `wristband.jpg` | Fabric wristband | [Wikimedia Commons, by Spiileer](https://commons.wikimedia.org/wiki/File:Wristband_Yellow_Ducks_WTR_2025.jpg) | CC0 |
| `necklace.jpg` | Hand holding two pendants on chains | [WordPress Photo Directory](https://wordpress.org/photos/photo/81869c69ae/) | CC0 |
| `lanyard.jpg` | Attendee card and lanyard on a table | [WordPress Photo Directory](https://wordpress.org/photos/photo/1926710db3/) | CC0 |
| `keychain.jpg` | Keychain with keys on a white table | [WordPress Photo Directory](https://wordpress.org/photos/photo/91565c6210/) | CC0 |
| `mini-tag.jpg` | RFID chip laminated into a small label | [Wikimedia Commons, by Pedalito](https://commons.wikimedia.org/wiki/File:RFID_tag_in_a_label_1.png) | CC0 |
| `pet.jpg` | The shop's own pet-tag product photo | Supplied by the shop (`~/Desktop/71ZH3YFAWkL._AC_UF894,1000_QL80_.jpg`) | Own work |
| `clothing.jpg` | Care label sewn into a garment | [Wikimedia Commons, by Elkagye](https://commons.wikimedia.org/wiki/File:Label_with_care_symbols.JPG) | Public domain |

## Step illustrations

The three pictures beside "How a tag works" are drawn, not photographed
(`assets/img/steps/`). Each one is a small diagram of what that step does, so
it cannot drift away from the feature the way a stock photo can:

| File | Step | Drawing |
|------|------|---------|
| `create.webp` | Create the tag | a form on a phone, and the tag it produces |
| `write.webp` | Write the chip | a chip in a tag, and the code written onto it |
| `scan.webp` | Scan, and get a message | a tag being tapped, and the message coming back |

They are flat shapes on a transparent background, in the site accent plus a
single mid grey. That is deliberate: a mid grey clears 4:1 against both a white
and a black page, so one set of files serves both themes, and a grey wash
darkens a light page while lightening a dark one. Nothing is drawn as text, so
the pictures work in either language.

- 640 x 400, 32 frames, 150 ms a frame, so one loop is 4.8 seconds
- 320 to 525 KB each, about 1.3 MB for all three
- seamless: the push-in eases back out, and every accent is periodic, so the
  last frame matches the first

Each step is also written out next to its animation as `<name>-still.webp`,
which is the finished frame. A build-up that started empty would snap back to
empty once per loop and read as a glitch, so nothing here assembles or
disappears: the composition is always complete and only the motion is
periodic.

Rebuild after changing a drawing:

```sh
python3 -m venv .venv
.venv/bin/pip install pillow
.venv/bin/python scripts/make-step-illustrations.py
```

`img2webp` from libwebp has to be on PATH (`brew install webp`).

The page offers the animation through `<picture>`, and anyone whose system asks
for reduced motion gets the still frame instead. An animated WebP cannot be
paused from CSS, so that choice has to be made in markup; `scripts/ui-check.mjs`
asserts both halves of it.

## Extras

The four tiles on the "Also built in" band (`assets/img/extras/`), 1200 x 675,
16:9.

| File | Tile | Source | Licence |
|------|------|--------|---------|
| `messaging.jpg` | WhatsApp, if the owner wants it | [Rawpixel via Openverse](https://www.rawpixel.com/image/5923126/photo-image-background-phone-public-domain) | CC0 |
| `tags.jpg` | Replace a tag, keep the link | [Wikimedia Commons](https://commons.wikimedia.org/wiki/File:RFID_and_magneto-acoustic_tags.JPG) | Public domain |
| `privacy.jpg` | What a finder sees, and what stays private | [WordPress Photo Directory](https://wordpress.org/photos/photo/6726a05b1d/) | CC0 |

The first tile has no photograph: the guest Wi-Fi code is drawn on the page as
an SVG so it stays square and whole. As a picture of a code in a 16:9 box it
was being cropped by `object-fit: cover` and had stopped scanning, which is a
failure that looks like nothing at all in a screenshot. `ui-check.mjs` now
decodes the rendered tile in a real browser to catch it coming back.

## Replacing them

These are stand-ins for a shop that has not photographed its own stock yet.
A picture of the actual product always sells better than a stock photo, and
it removes the risk that a visitor recognises something that is not yours.

- Products: overwrite the file in `assets/img/products/` with the same name.
- Extras: overwrite the file in `assets/img/extras/` with the same name.

The step illustrations are drawn rather than photographed, so they are changed
by editing `scripts/make-step-illustrations.py` and rerunning it.

No code change is needed. A missing file falls back to the page preview and
then to the icon, so a gap never breaks the layout.

Before publishing a photo of your own, take out the location data:

```sh
exiftool -all= -overwrite_original photo.jpg
```
