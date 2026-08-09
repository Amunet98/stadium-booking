#!/usr/bin/env python3
"""
Pull UI screenshots out of the 2021 project report.

The report .docx is the only surviving record of the application running, so
the screenshots in it are the only "before" images available. The file is a zip;
images live at word/media/imageNN.png and are referenced from document.xml in
document order, which is how we map an image to the caption above it.

EXCLUDED deliberately: image28.png is the phpMyAdmin `users` table, showing real
names, working email addresses, phone numbers and MD5 password hashes belonging
to third parties. It is not committed to this repository in any form.

Usage:  python3 docs/extract-screenshots.py "/path/to/report.docx" docs/screenshots
"""
import html
import re
import sys
import zipfile
from pathlib import Path

# Images that must never be committed, and why.
BLOCKLIST = {
    "image28.png": "phpMyAdmin users table - real names, emails, phones, MD5 hashes",
}

# Source image -> output filename. Mapped by image name rather than by caption:
# captions in the report are long prose paragraphs and several are reused across
# multiple images, so they do not identify a screenshot uniquely.
#
# The report also contains ~40 screenshots of source code. Those are not
# extracted — the repository itself supersedes them.
WANTED = {
    # User-facing flow
    "image37.png": "ui-01-home",
    "image50.png": "ui-02-login",
    "image48.png": "ui-03-signup",
    "image57.png": "ui-04-signup-success",
    "image47.png": "ui-05-home-logged-in",
    "image52.png": "ui-06-match-cards",
    "image54.png": "ui-07-booking-form",
    "image55.png": "ui-08-my-tickets",
    # Admin panel
    "image61.png": "ui-09-admin-dashboard",
    "image56.png": "ui-10-admin-matches",
    "image59.png": "ui-11-admin-teams",
    "image60.png": "ui-12-admin-stadium",
    "image65.png": "ui-13-admin-bookings",
    # phpMyAdmin captures — the evidence db/schema.sql was reconstructed from.
    # `users` is deliberately absent; see BLOCKLIST.
    "image63.png": "db-01-bookings",
    "image67.png": "db-02-matches",
    "image30.png": "db-03-roles",
    "image23.png": "db-04-stadium",
    "image26.png": "db-05-teams",
}


def document_order(docx: Path):
    """Yield (caption, image_name) in the order they appear in the document."""
    with zipfile.ZipFile(docx) as z:
        rels = z.read("word/_rels/document.xml.rels").decode("utf8", "ignore")
        rel_map = dict(
            re.findall(r'Id="(rId\d+)"[^>]*Target="media/([^"]+)"', rels)
        )
        xml = z.read("word/document.xml").decode("utf8", "ignore")

    xml = re.sub(r"</w:p>", "\n", xml)
    xml = re.sub(
        r'<a:blip r:embed="(rId\d+)"',
        lambda m: f"\x00IMG:{rel_map.get(m.group(1), '?')}\x00",
        xml,
    )
    xml = re.sub(r"<[^>]+>", "", xml)
    text = html.unescape(xml)

    caption = None
    for line in text.splitlines():
        images = re.findall(r"\x00IMG:([^\x00]+)\x00", line)
        stripped = re.sub(r"\x00IMG:[^\x00]+\x00", "", line).strip()
        # Ignore leftover markup fragments such as a bare "/>" — a caption has
        # to contain actual words, or it overwrites the real one.
        if re.search(r"[A-Za-z]{3}", stripped):
            caption = stripped
        for img in images:
            yield caption, img


def main() -> int:
    if len(sys.argv) != 3:
        print(__doc__)
        return 2

    docx, outdir = Path(sys.argv[1]), Path(sys.argv[2])
    if not docx.is_file():
        print(f"error: no such file: {docx}", file=sys.stderr)
        return 1
    outdir.mkdir(parents=True, exist_ok=True)

    written, skipped = 0, 0
    with zipfile.ZipFile(docx) as z:
        for _caption, img in document_order(docx):
            if img in BLOCKLIST:
                print(f"  BLOCKED  {img}  ({BLOCKLIST[img]})")
                skipped += 1
                continue
            stem = WANTED.get(img)
            if stem is None:
                continue
            name = stem + Path(img).suffix
            (outdir / name).write_bytes(z.read(f"word/media/{img}"))
            print(f"  wrote    {name}  (from {img})")
            written += 1

    print(f"\n{written} written, {skipped} blocked -> {outdir}")
    print("Review every extracted image for incidental personal data "
          "(browser tabs, taskbars, table rows) before committing.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
