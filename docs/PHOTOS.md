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
| `pet.jpg` | Golden retriever portrait | [WordPress Photo Directory](https://wordpress.org/photos/photo/18068c2884/) | CC0 |
| `clothing.jpg` | Care label sewn into a garment | [Wikimedia Commons, by Elkagye](https://commons.wikimedia.org/wiki/File:Label_with_care_symbols.JPG) | Public domain |

## Scenes

The three pictures beside the steps on the home page (`assets/img/scenes/`),
1600 x 1000, 16:10.

| File | Step | Source | Licence |
|------|------|--------|---------|
| `create.jpg` | Create the tag | [WordPress Photo Directory](https://wordpress.org/photos/photo/28468b5f3b/) | CC0 |
| `write.jpg` | Write the chip | [Wikimedia Commons, by Adrian Tync](https://commons.wikimedia.org/wiki/File:RFID_in_book.jpg) | CC0 |
| `scan.jpg` | Scan and read | [WordPress Photo Directory](https://wordpress.org/photos/photo/339642c1e5/) | CC0 |

### The moving versions

Beside each still sits a looping animation built from it, `create.webp`,
`write.webp` and `scan.webp`. They are not a separate set of pictures: the
photograph is pushed in slowly, one soft band of light crosses it, and a
single accent says what the step does, a ring pulse for setting a tag up, a
progress bar for writing the chip, a travelling band for reading a code.

- 720 x 450, 32 frames, 150 ms a frame, so one loop is 4.8 seconds
- roughly 280 to 350 KB each, and around 1 MB for all three
- seamless: the zoom eases in and back out, and the sweep and accent both
  start and end invisible

Rebuild them after replacing a still:

```sh
python3 -m venv .venv
.venv/bin/pip install pillow
.venv/bin/python scripts/make-scene-animations.py
```

`img2webp` from libwebp has to be on PATH (`brew install webp`).

The page offers the animation through `<picture>`, and anyone whose system
asks for reduced motion gets the still instead. An animated WebP cannot be
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
- Scenes: overwrite the file in `assets/img/scenes/` with the same name.

No code change is needed. A missing file falls back to the page preview and
then to the icon, so a gap never breaks the layout.

Before publishing a photo of your own, take out the location data:

```sh
exiftool -all= -overwrite_original photo.jpg
```
