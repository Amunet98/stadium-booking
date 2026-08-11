# Online Stadium Booking

[![Live Demo](https://img.shields.io/badge/Live%20Demo-onrender.com-facc15)](https://stadium-booking-75sm.onrender.com)
[![PHP](https://img.shields.io/badge/PHP-8.2-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479a1?logo=mysql&logoColor=white)](https://www.mysql.com)
[![Docker](https://img.shields.io/badge/Docker-compose-2496ed?logo=docker&logoColor=white)](https://docs.docker.com/compose/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952b3?logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![Findings](https://img.shields.io/badge/findings-20%20documented-c0392b)](docs/SECURITY-FINDINGS.md)

A stadium ticket-booking system I wrote in my third year of engineering, found
five years later, and rebuilt — because reading it back showed the admin panel
had no access control and the booking flow would happily oversell a stadium.

<p align="center">
  <img src="docs/screenshots/after-01-home.jpg" width="49%" alt="Home page, light theme">
  <img src="docs/screenshots/after-02-home-dark.jpg" width="49%" alt="Home page, dark theme">
</p>

<p align="center">
  <img src="docs/screenshots/after-03-fixtures.jpg" width="49%" alt="Fixtures listing, light theme">
  <img src="docs/screenshots/after-04-fixtures-dark.jpg" width="49%" alt="Fixtures listing, dark theme">
</p>

The 2021 code is preserved unmodified in [`legacy/`](legacy), so the whole thing
reads as a diff. What changed and why is in
[docs/SECURITY-FINDINGS.md](docs/SECURITY-FINDINGS.md) — twenty defects, each
with the original code, the failure it caused, and the fix.

---

## Try it

**[stadium-booking-75sm.onrender.com](https://stadium-booking-75sm.onrender.com)** —
free tier, so the first request after a quiet spell takes ~50 seconds to wake.

| Role  | Email               | Password      |
|-------|---------------------|---------------|
| Admin | `admin@example.com` | `Admin!2345`  |
| User  | `alex@example.com`  | `Passw0rd!23` |

The admin login is real and unrestricted, so the demo data gets rearranged from
time to time. It resets to seed every night.

The clubs, grounds and managers are real; every account is invented. The seat
counts are not the real capacity of the ground — `capacity_vip` and friends
hold the allocation *released to this demo*, and they are deliberately tiny
(Anfield offers two VIP seats) so that the capacity limit can be reached by
hand in under a minute instead of by scripting sixty thousand bookings. The
real figure for each ground is in its description.

## Run it locally

Docker is the only requirement.

```bash
git clone <this repo> && cd stadium-booking
cp .env.example .env
docker compose up -d
```

Then open <http://localhost:8080>, with the same logins as above. The schema and
demo data apply themselves on first boot.

Verify the claims below rather than taking them on trust:

```bash
./tests/verify.sh        # 18 assertions over HTTP: auth, CSRF, capacity, constraints
./tests/concurrency.sh   # 24 workers race for 4 seats
```

<details>
<summary><b>Deploying it</b></summary>

`render.yaml` is committed, so Render picks the whole thing up from the repo.
The container initialises its own database on first boot — `docker-entrypoint.sh`
runs `db/bootstrap.php`, which applies `schema.sql` and `seed.sql` if the tables
are missing and does nothing if they are already there. No manual migration step.

**1. A MySQL 8 database.** It has to be *genuine* MySQL 8, not a
MySQL-compatible service: the booking transaction locks with
`SELECT ... FOR UPDATE OF m`, which is 8.0.1+ syntax that TiDB- and
Vitess-backed offerings do not reliably support — and that lock is the point of
the project. Render has no MySQL at all, so use something like Aiven's free
MySQL plan and keep it outside the Render workspace. Expect to replace it: free
managed databases do not last, and this one's first host stopped resolving
eleven hours after it was set up.

**2. Create the Render service** from this repo. `render.yaml` sets
`TRUST_PROXY=1` and `APP_ENV=production` already; fill in `DB_HOST`, `DB_PORT`,
`DB_NAME`, `DB_USER`, `DB_PASSWORD` in the dashboard, and upload the provider's
CA certificate as a Secret File named `ca.pem`.

`TRUST_PROXY` matters more than it looks: Render terminates TLS at its load
balancer and forwards plain HTTP, so without it `$_SERVER['HTTPS']` is empty and
the app emits `http://` links on an `https://` page and drops the `Secure` flag
off the session cookie. It is off by default because that header is trivially
forged when nothing trustworthy sets it.

**3. Reset the data on a schedule.** The admin panel is full CRUD and the
credentials above are public, so a public deploy will get rearranged sooner or
later. `.github/workflows/demo-reset.yml` restores the seed nightly once its
secrets are set, and stays inert until then.

`.env.example` documents every variable. To run a bootstrap or reset by hand:

```bash
php db/bootstrap.php          # create if absent
php db/bootstrap.php --force  # drop and recreate
```

**When the demo goes dark,** suspect the database first — it is the part with no
paid guarantee behind it. `curl https://<demo>/health.php` answers `200` whenever
the container is serving and prints `db: ok` or `db: unavailable`, which
separates a sleeping web service from a missing database. The reset workflow
checks the same thing before it tries to seed and fails with a sentence saying
so. If the database really is gone, recreating it means re-pointing the
connection details in three places, all of which must agree: the `DEMO_DB_*`
GitHub secrets, the `DB_*` variables and the `ca.pem` Secret File on the Render
service, and your local `.env.aiven` plus `ca.pem`.
</details>

---

## The two bugs worth reading about

### The admin panel was open to everyone

`legacy/admin/index.php` included the dashboard unconditionally. No session
check, no role check. Requesting `/booking/admin` logged out returned the full
admin interface with create access to fixtures and read access to every customer
booking.

The mechanism to stop it was already there: `users.rid` was populated, a `roles`
table existed, and the login code read the column — to decide where to *redirect*
you:

```php
if ($row['rid'] == 1) { header('Location: .../admin'); }
else                  { header('Location: .../'); }
```

The role picked which URL you were sent to. Nothing picked which URLs you were
allowed to visit. That is the entire bug, and it is the one I find most worth
keeping in mind: authorisation implemented as navigation looks completely fine
when you click through it as the developer.

Now `require_admin()` runs as the first statement of the admin entry point,
before any routing. Anonymous requests get redirected to log in; authenticated
non-admins get 403.

### Seat availability was wrong, and the way it was wrong hid itself

```php
$bookingsqlA = "select bid from `bookings` where `seattype` = 'a'";
$bookedSeatsA = countSeats($bookingsqlA);
...
echo ($totalA - $bookedSeatsA)
```

Two bugs in four lines. There is no `WHERE mid`, so every booking in the table
was subtracted from every match — sell out one fixture and all the others lose
seats. And the query matches `seattype = 'a'` while the insert wrote `'priceA'`,
so after that format changed the count matched nothing and always returned zero.

The second bug masked the first. With the count pinned at zero, the cross-match
contamination never showed up in testing. Both formats are still visible in the
recovered data, which is how I found it.

Worse, none of it mattered: the display was decorative. Nothing consulted it
before writing, and there was no constraint behind it either.

**Fixing the count is not enough.** The obvious repair — `SELECT COUNT(*)`,
compare, then insert — still oversells, because concurrent requests all read the
same count before any of them writes. `tests/concurrency.sh` measures all three
approaches with 24 workers competing for 4 seats:

```
  no capacity check   (the original)             sold  24 / 4   oversold by 20
  check, then insert  (the obvious fix)          sold  24 / 4   oversold by 20
  SELECT ... FOR UPDATE (src/config/booking.php) sold   4 / 4   exactly 4
```

The lock does the work, not the check. The two failing strategies run on every
invocation on purpose — a concurrency test that has never been seen to fail is
not evidence of anything.

```php
// src/config/booking.php
$pdo->beginTransaction();
    // FOR UPDATE OF m locks the match row only; without `OF m` the joined
    // stadium row locks too, serialising unrelated matches at the same ground.
    SELECT m.mid, m.price_x, s.capacity_x
      FROM matches m JOIN stadium s ON s.sid = m.venue
     WHERE m.mid = :mid FOR UPDATE OF m

    SELECT COUNT(*) FROM bookings WHERE mid = :mid AND seat_tier = :tier
    // reject if count >= capacity
    INSERT INTO bookings ...
$pdo->commit();
```

with `UNIQUE (mid, uid, seat_tier)` in the schema as a backstop, because the
application will not always be the only thing writing to this table.

---

## Recovering the schema

The original shipped no `.sql` file, and the XAMPP database it assumed did not
survive. The only record of the schema was a set of phpMyAdmin screenshots
embedded in the project report.

A `.docx` is a zip archive, so the images are extractable and can be tied back to
their captions through `document.xml`. `docs/extract-screenshots.py` does this;
the reconstruction is written up in
[docs/schema-recovery.md](docs/schema-recovery.md).

Two bugs were visible in the recovered *data* before I read any PHP: the
`seattype` column holding two incompatible vocabularies, and `matches` having no
date column at all — `time` was a `VARCHAR` holding strings like `01:00 UKT`.

```mermaid
erDiagram
    roles    ||--o{ users    : "has"
    users    ||--o{ bookings : "makes"
    stadium  ||--o{ teams    : "home ground for"
    stadium  ||--o{ matches  : "hosts"
    teams    ||--o{ matches  : "plays in"
    matches  ||--o{ bookings : "sells"

    roles {
        int rid PK
        varchar name
    }
    users {
        int uid PK
        varchar name
        varchar email UK
        varchar password "bcrypt"
        int rid FK
    }
    stadium {
        int sid PK
        varchar name UK
        int capacity_vip
        int capacity_platinum
        int capacity_gold
    }
    teams {
        int tid PK
        varchar name
        varchar manager
        int sid FK
    }
    matches {
        int mid PK
        varchar title
        int venue FK
        date match_date
        time kickoff_time
        decimal price_vip
        decimal price_platinum
        decimal price_gold
        int hometeam FK
        int awayteam FK
    }
    bookings {
        int bid PK
        int uid FK
        int mid FK
        varchar seat_tier "CHECK vip|platinum|gold"
        decimal paid_amount
        timestamp created_at
    }
```

The recovered schema had **no foreign keys, no unique constraints and no check
constraints** — consistent with tables created by hand through the phpMyAdmin
UI. `db/schema.sql` adds them, marking every departure from the original with
`[NEW]`.

One is worth mentioning because MySQL forced the choice. `chk_matches_distinct_teams`
(a team cannot play itself) is rejected if the same column also carries a foreign
key with a referential action — `ERROR 3823`. Since `tid` is a surrogate key that
is never updated, `ON UPDATE CASCADE` bought nothing there, so the FKs became
`ON UPDATE RESTRICT` and the check survived.

---

## What changed

| | 2021 | Now |
|---|---|---|
| Admin access | none | `require_admin()`, 302 anon / 403 non-admin |
| Passwords | unsalted MD5 | bcrypt, with transparent upgrade of legacy hashes |
| Queries | string interpolation + hand-rolled `sanitize()` | prepared statements, escaping on output |
| CSRF | none | per-session tokens on every POST |
| Booking | bare INSERT | transaction + row lock + UNIQUE |
| Seat counts | table-wide, matched nothing | scoped to the match |
| Credentials | `root`/empty, in the document root | environment, outside the docroot |
| Errors | SQL echoed to the page | logged, not displayed |
| Fixtures | no date column | `match_date` + `kickoff_time`, indexed |
| Theming | none | light/dark, follows the OS, remembers your choice |
| Interface | one page listing fixtures | landing page, filterable listing, ticket stubs |
| Fixture art | one PNG on every card | per-club badges and per-ground artwork |
| Keyboard | no skip link, no focus rings on buttons | skip link, AA-checked focus ring everywhere |
| Runs on | Windows + XAMPP only | anywhere with Docker |

### The interface

Light and dark, driven by Bootstrap 5.3's native `data-bs-theme`. It follows
`prefers-color-scheme` until you touch the toggle, after which your choice is
remembered.

The theme is applied by a small inline script in `<head>`, before any stylesheet
loads — otherwise the page paints in the default theme and then corrects itself,
which is a visible flash on every navigation. Colours are declared once per theme
as tokens in `style.css`, so a third theme would be one block rather than a hunt
through hex values. Every foreground/background pair in both themes was measured
against WCAG AA rather than eyeballed; the tightest is secondary text on the page
at 5.82:1.

Two things that only show up if you go looking for them, both fixed:

- Bootstrap ships `.btn:focus-visible { outline: 0 }`, which out-specifies a
  bare `:focus-visible` rule. Every button on the site had **no keyboard focus
  ring at all** while the stylesheet appeared to define one. It is invisible to
  anyone using a mouse; it was found by tabbing to a button and reading its
  computed `outline-width`.
- Scroll reveals use an `IntersectionObserver` with a negative bottom
  `rootMargin`. An element that only ever comes to rest inside that shrunken
  strip — the last section above the footer — never reports as intersecting and
  stays at `opacity: 0` with no way for the reader to recover it. There is an
  explicit failsafe at the bottom of the document.

Motion is transform and opacity only, and `prefers-reduced-motion` is honoured
in both the stylesheet and the script — the script stops observing entirely, so
content renders visible rather than stuck mid-animation.

The club badges and ground artwork are original, generated by
[`docs/generate-artwork.py`](docs/generate-artwork.py). Club crests are
registered trademarks and photographs of grounds are somebody's copyright;
club *colours* are not, so each badge is a shield carrying a club's colours,
its kit motif and its three-letter code. Nothing is traced from a crest.

<p align="center">
  <img src="docs/screenshots/after-05-booking.jpg" width="49%" alt="Booking page with per-tier availability">
  <img src="docs/screenshots/after-06-tickets.jpg" width="49%" alt="My tickets, as ticket stubs">
</p>

<p align="center">
  <img src="docs/screenshots/after-07-admin-bookings.jpg" width="49%" alt="Admin bookings listing, light theme">
  <img src="docs/screenshots/after-08-admin-dark.jpg" width="49%" alt="Admin bookings listing, dark theme">
</p>

For comparison, the 2021 interface is preserved in
[`docs/screenshots/`](docs/screenshots) as `ui-*` — including `ui-08-my-tickets.png`,
which shows the unstyled page that resulted from `myticket.php` including neither
header nor footer.

---

## Layout

```
├── src/
│   ├── config/       db, auth, csrf, booking logic, fixtures, helpers
│   ├── public/       the only web-served directory (+ assets: css, js, fonts, img)
│   ├── admin/        admin handlers, reached via src/public/admin.php
│   └── views/        shared layout, and partials/ for the fixture card
├── db/               schema.sql, seed.sql
├── legacy/           the 2021 code, unmodified
├── tests/            verify.sh, concurrency.sh, race.php
└── docs/             findings, schema recovery, artwork generator, screenshots
```

Fonts and Bootstrap are self-hosted under `src/public/assets/`, not linked from
a CDN. The container has no guaranteed outbound network, and a
`fonts.googleapis.com` link makes every visitor's browser announce this page to
a third party before it can render text.

Still procedural PHP, deliberately. Rewriting it in a framework would have
produced a different project and thrown away the only interesting thing about
this one: a measurable before and after.

## Not included

No payment gateway — `paid_amount` records what a ticket cost, nothing takes
money. No cancellations, no email delivery, no rate limiting on login. These are
listed in the findings document rather than quietly implied.

## Credits

Original coursework, 2020–21: **Bimesh Poudel** and **Aadarsh Gurung**,
Department of Computer Science & Engineering, Dr. Ambedkar Institute of
Technology, Bengaluru. Supervised by Prof. Veena Potdar.

2026 restoration and hardening: **Bimesh Poudel**.

The original coursework was built on a publicly available PHP booking template.
The restoration described here is my own work.

---

More of my work: **[bimeshpoudel.com.np](https://www.bimeshpoudel.com.np)**
