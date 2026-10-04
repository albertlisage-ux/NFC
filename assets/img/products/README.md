# Product photos

Photos dropped in this folder are picked up automatically on the Products page
(`/use-cases`) and on the tag pages: the tile and the example panel show the
image, and when a file is missing the emoji mark is used instead, so the layout
never breaks.

Use exactly these file names, one per catalogue item:

| File | Product |
|------|---------|
| `menu-board.jpg` | Menu board (wood or acrylic) |
| `poster.jpg` | NFC poster |
| `wristband.jpg` | Wristband |
| `necklace.jpg` | Necklace / pendant |
| `lanyard.jpg` | Lanyard |
| `keychain.jpg` | Keychain |
| `mini-tag.jpg` | Mini tag |
| `pet.jpg` | Pet |
| `clothing.jpg` | Clothing |

Recommendations

- 1200 x 900 pixels, 4:3, JPEG, quality around 80 (roughly 150 to 400 KB)
- Plain background, the product filling most of the frame
- No text, no watermark, no visible brand names you do not have rights to
- Shoot the real product: for a shop that sells physical tags, real photos sell
  better than any rendering

The photos currently in this folder are CC0 or public domain stand-ins, listed
with their source in `docs/PHOTOS.md`. Replace them with your own shots when
you have them: overwrite the file, keep the name, and nothing else changes.
Strip the location data before publishing (`exiftool -all= photo.jpg`).

To add another product later, add a file here and give it the `image` name in
`includes/catalog.php`.
