# Recovering the schema

The 2021 project shipped no `.sql` file. The application assumed a database
named `booking` already existed on the developer's XAMPP install, and that
database did not survive. There was no dump, no migration, and no `CREATE TABLE`
anywhere in the PHP.

So the schema had to be reconstructed. This note records how, because the method
is reusable and because anyone reading `db/schema.sql` deserves to know which
parts are recovered fact and which are inference.

## Sources, in order of reliability

**1. phpMyAdmin screenshots in the project report.** The report `.docx` embeds 69
images, six of which are phpMyAdmin `Browse` views — one per table, captured
2020-06-22. A `Browse` view gives column names in order and real values, which
between them pin down both the column list and the approximate types. This is the
strongest evidence available and is where the column names come from.

A `.docx` is a zip archive. The images are at `word/media/imageNN.png`, and
`word/document.xml` references them in document order, so an image can be tied
back to the caption above it:

```
python3 docs/extract-screenshots.py "path/to/report.docx" docs/screenshots
```

`docs/extract-screenshots.py` does exactly this and is committed alongside the
extracted images.

**2. The SQL embedded in the PHP.** Every query is an interpolated string, so the
queries themselves name columns and reveal join paths. `admin/inc/functions/teams.php`
contains `teams inner join stadium on teams.sid = stadium.sid`, which establishes
`teams.sid` as a foreign key to `stadium` — a relationship the database never
declared. Every foreign key in `db/schema.sql` was derived this way and
cross-checked against the screenshots.

**3. The report's prose.** Names the seat tiers (VIP / Platinum / Gold) and
confirms that `stadium.a`, `.b`, `.c` are their capacities.

## What was recovered

| Table | Columns as found |
|---|---|
| `roles` | `rid`, `name` — exactly two rows: (1, Admin), (2, User) |
| `users` | `uid`, `name`, `email`, `password`, `phone`, `country`, `rid` |
| `stadium` | `sid`, `sName`, `a`, `b`, `c`, `photo`, `description` |
| `teams` | `tid`, `tname`, `manager`, `sid`, `details`, `photos` |
| `matches` | `mid`, `title`, `description`, `venue`, `time`, `ptw`, `priceA`, `priceB`, `priceC`, `hometeam`, `awayteam` |
| `bookings` | `bid`, `uid`, `mid`, `seattype`, `paidAmount`, `timestamp` |

Six tables, and **not one declared foreign key, unique constraint, or check
constraint** — consistent with tables created by hand through the phpMyAdmin UI.

## What the data itself revealed

Two bugs were visible in the recovered rows before a single line of PHP was read.

**The `bookings.seattype` column held two vocabularies at once.** Some rows say
`a`, later rows say `priceB` / `priceC`. That is a write path whose format changed
mid-development while the read path was never updated — `booking.php` still counts
`WHERE seattype = 'a'`, so it silently stops matching anything the app now writes.
The screenshot is the artifact that makes this obvious; the code alone reads as if
it works.

**`matches` has no date column.** `time` is a `VARCHAR` holding strings like
`01:00 UKT`. Fixtures could not be ordered chronologically, which is why the
homepage lists them in insertion order.

Both are recorded in [SECURITY-FINDINGS.md](SECURITY-FINDINGS.md) (#7, #13).

## Deliberate departures in `db/schema.sql`

The recovered schema is the starting point, not the target. Changes are marked
`[NEW]` inline. The substantive ones:

- **Columns renamed to say what they hold.** `stadium.a` → `capacity_vip`,
  `matches.priceA` → `price_vip`, `bookings.seattype` → `seat_tier`. The originals
  were positional labels that the admin UI then mapped to the wrong tiers
  (finding #10) — a mistake that is much harder to make against a named column.
- **`match_date DATE` + `kickoff_time TIME`** replace the `VARCHAR` `time`.
- **Foreign keys on all six relationships**, previously enforced only by
  convention.
- **`CHECK (seat_tier IN ('vip','platinum','gold'))`** so the two-vocabulary bug
  is now rejected by the database rather than tolerated.
- **`UNIQUE (mid, uid, seat_tier)`** as a backstop against double-booking.
- **`UNIQUE` on `users.email`.** The original checked for duplicate emails in PHP
  only, which is racy — two concurrent signups could both pass the check and both
  insert.
- **`password VARCHAR(255)`**, widened from 32; the column was sized for MD5.

## A note on the abandoned refactor

The original `booking/` directory contained a `.git` with 58 loose objects, an
index, and no refs — `git add` had been run, but nothing was ever committed, so
there is no history to recover. The staged tree does show one thing the working
tree does not: an abandoned `admin/inc/functions/stadium/{create,index,show}.php`
layout, two of the three files empty. Someone started reorganising the admin
functions into per-resource directories and stopped. Recorded here only because
it is the sole trace of the project's own development history.

## The data that is not here

The recovered `users` table contained real personal data belonging to third
parties: full names, working personal email addresses, phone numbers, and
unsalted MD5 password hashes. MD5 without a salt is recoverable for common
passwords in seconds, and people reuse passwords across services.

None of it is reproduced in this repository. Specifically:

- `db/seed.sql` is written from scratch with invented people, teams and grounds.
- The phpMyAdmin screenshot of the `users` table is **not** extracted. It is in
  `BLOCKLIST` in `docs/extract-screenshots.py` so that re-running the script
  cannot reintroduce it.
- The remaining screenshots were reviewed individually for names appearing in
  table rows, browser tabs, and the taskbar.

This is why the schema above is documented as a table of column names rather than
illustrated with the original screenshot.
