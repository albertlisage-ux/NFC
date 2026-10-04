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
