# Findings

Twenty defects found in the 2021 codebase, and what was done about each.

Line references point into [`legacy/`](../legacy), which is the original code
preserved unmodified. Severity is my own judgement in the context of a public
deployment; this was coursework that only ever ran on localhost.

| # | Finding | Severity | Status |
|---|---------|----------|--------|
| 1 | Admin panel has no access control at all | Critical | Fixed |
| 2 | Passwords stored as unsalted MD5 | High | Fixed |
| 3 | Queries built by string interpolation throughout | High | Fixed |
| 4 | `myticket.php` reads session state with no login check | High | Fixed |
| 5 | No CSRF protection on any form | High | Fixed |
| 6 | Database credentials hardcoded and web-reachable | High | Fixed |
| 7 | Seat availability wrong two independent ways | High | Fixed |
| 8 | Nothing prevents overselling a match | High | Fixed |
| 9 | Add-match form omits prices entirely | Medium | Fixed |
| 10 | Platinum and gold capacities transposed on insert | Medium | Fixed |
| 11 | "Add booking" button wired to an empty function | Low | Removed |
| 12 | Windows-only path makes the app fatal elsewhere | Medium | Fixed |
| 13 | No match date column; time held as free text | Medium | Fixed |
| 14 | Output emitted before redirect on login | Low | Fixed |
| 15 | Signup collects a name and discards it | Low | Fixed |
| 16 | `http://localhost/booking/` hardcoded in ~10 places | Medium | Fixed |
| 17 | Malformed HTML on every page | Low | Fixed |
| 18 | Every fixture shows the same hardcoded image | Low | Fixed |
| 19 | Errors printed to the response body | Medium | Fixed |
| 20 | Booking form collects a name and discards it | Low | Fixed |

---

## 1. The admin panel had no access control — Critical

`legacy/admin/index.php` includes the sidebar and dashboard unconditionally:

```php
<div class="col-sm-3 p-0"><?php include 'sidebar.php' ?></div>
<div class="col-sm-9 p-0"><?php include 'dashboard.php' ?></div>
```

There is no session check, no role check, nothing. Requesting
`/booking/admin` — logged out, from any browser — returned the full dashboard
with create access to stadiums, teams and matches, and read access to every
booking and the customer behind it.

What makes this worth dwelling on is that the mechanism to prevent it was
already built. `users.rid` existed and was populated, `roles` existed with Admin
and User rows, and `loginProcess.php` even read the column — to decide where to
redirect:

```php
if ($row['rid'] == 1) { header('Location: .../admin'); }
else                  { header('Location: .../'); }
```

The role decided which URL you were *sent* to, and nothing decided which URLs
you were *allowed* to visit. That is the whole bug: authorisation implemented as
navigation.

**Fixed** by `require_admin()` in `src/config/auth.php`, called as the first
statement in `src/public/admin.php` before any routing, so no path through the
file reaches a handler unauthenticated. Anonymous visitors are redirected to log
in; authenticated non-admins get 403, because bouncing them to a login page they
have already passed is a dead end.

Verified by `tests/verify.sh` — anonymous 302, non-admin 403, admin 200.

## 2. Unsalted MD5 passwords — High

```php
$password = MD5($password);
```

`legacy/account/loginProcess.php:24` and `signupProcess.php:27`. Unsalted MD5 is
recoverable for any common password essentially instantly, and identical
passwords produce identical hashes, so one leak exposes every reused password
across the table at once.

**Fixed** with `password_hash()` / `password_verify()` (bcrypt). Existing MD5
rows are not invalidated: `verify_and_upgrade_password()` recognises a 32-char
hex hash, verifies it once against MD5, and immediately rehashes with bcrypt, so
accounts upgrade themselves on next login. Once every active user has logged in
once, the legacy branch can be deleted.

## 3. String-interpolated queries — High

Every query in the original was built by concatenation:

```php
$sql = "SELECT * FROM `users` WHERE email='$username' AND `password` = '$password'";
```

A `sanitize()` helper — copy-pasted verbatim into four files — ran `trim`,
`stripslashes`, `htmlspecialchars` and `mysqli_real_escape_string` over every
input. That combination blocks the obvious payloads, so this is not a trivially
exploitable injection. It is still the wrong control, for two reasons:

- It escapes on the way **in**, which corrupts stored data. An apostrophe in a
  surname is written to the database as `&#039;`.
- It is applied by hand at each call site, so it holds only as long as nobody
  forgets. `myticket.php` forgot — see #4.

**Fixed**: every query is a prepared statement with bound parameters,
`ATTR_EMULATE_PREPARES` off so placeholders reach the server. All four copies of
`sanitize()` are gone, and escaping happens at output via `e()`.

## 4. `myticket.php` had no login check — High

```php
$uid = $_SESSION["user"] ;
$sql = "select * from bookings where uid = $uid";
```

No guard, and the one interpolation in the codebase that skips `sanitize()`
entirely. Logged out, `$_SESSION["user"]` is undefined, the query becomes
`where uid = ` and MySQL's error is printed to the page along with the SQL.

**Fixed**: `require_login()` first, then a prepared statement.

## 5. No CSRF protection — High

No token on any form. Combined with #1, a third-party page could POST to the
admin endpoint and create records on behalf of anyone who visited it — and
because logout was a GET link, an `<img src=".../logout.php">` logged visitors
out.

**Fixed**: per-session tokens in `src/config/csrf.php`, verified on every POST
with `hash_equals()`. Logout is POST-only.

The rejection status is 403, not the 419 often used for this: 419 is a Laravel
convention rather than an HTTP status code, and Apache rewrites unrecognised
codes to 500 — which would report a client-side token failure as a server fault.
That was caught by `tests/verify.sh` expecting 419 and receiving 500.

## 6. Credentials hardcoded and web-reachable — High

```php
$conn = mysqli_connect("localhost","root","","booking");
```

In source control, in two files (`inc/connect.php` and `admin/inc/connect.php`)
that had already begun to drift apart, as the database superuser with an empty
password. Both sat inside the document root, so a misconfiguration that stopped
PHP executing would have served them as plain text.

**Fixed**: one `src/config/db.php` reading from the environment, outside the
document root. Apache serves `src/public/` only; `tests/verify.sh` asserts that
`/config/db.php` and `/inc/connect.php` both 404.

## 7. Seat availability was wrong two independent ways — High

```php
$bookingsqlA = "select bid from `bookings` where `seattype` = 'a'";
$bookedSeatsA = countSeats($bookingsqlA);
...
echo ($totalA - $bookedSeatsA)
```

`legacy/booking.php:52-59`. Two separate bugs in four lines:

**No `WHERE mid`.** The count spans the entire bookings table, so every booking
ever made — for any match, at any ground — was subtracted from the availability
of the match being viewed. Sell out one fixture and every other fixture in the
system loses seats too.

**The seat values do not match.** The query looks for `seattype = 'a'`, while
`bookingprocess.php` inserts `'priceA'`. The recovered data shows both formats
in the same table, so the write format changed at some point and the read side
was never updated. After that change the count matched nothing and always
returned zero, meaning the page displayed full availability regardless of
bookings.

The second bug hid the first. With the count stuck at zero, the cross-match
contamination never showed up in testing.

**Fixed**: one vocabulary (`vip` / `platinum` / `gold`), a `CHECK` constraint so
the database rejects anything else, and `seats_remaining()` scoped to the match.
`tests/verify.sh` asserts that booking match 2 leaves match 1's count untouched.

## 8. Nothing prevented overselling — High

The insert had no capacity check, no transaction and no constraint behind it:

```php
$sql = "INSERT INTO bookings (`uid`,`mid`,`seattype`,`paidAmount`)
        VALUES ( '$uid', '$mid', '$Seat', '$paidAmount')";
```

The remaining-seats display was decorative. Nothing consulted it before writing.

**Fixed** in `create_booking()` — `SELECT ... FOR UPDATE OF m` on the match row,
count and insert inside one transaction, with `UNIQUE (mid, uid, seat_tier)` as
a backstop.

`tests/concurrency.sh` measures this. 24 workers start simultaneously and
compete for 4 seats:

```
  no capacity check   (the original)             sold  24 / 4   oversold by 20
  check, then insert  (the obvious fix)          sold  24 / 4   oversold by 20
  SELECT ... FOR UPDATE (src/config/booking.php) sold   4 / 4   exactly 4
```

The middle row is the interesting one. Adding a `SELECT COUNT(*)` before the
insert — the fix most people reach for — performs no better than no check at
all, because every worker reads the same count before any of them writes. The
lock is what does the work, not the check.

The two failing strategies are run on every invocation deliberately: a
concurrency test that has never been observed to fail is not evidence that the
code is correct.

## 9. The add-match form had no price fields — Medium

`legacy/admin/inc/functions/matches.php` renders inputs for title, description,
stadium, time, players-to-watch, home team and away team. No prices. The
corresponding INSERT in `process.php` does not mention `priceA`/`B`/`C` either.

So every match created through the admin UI had NULL prices, and every booking
against such a match recorded `paidAmount = NULL`. Only the seeded rows had
prices, which is why the bug survived: the demo data was inserted by hand
through phpMyAdmin.

**Fixed**: the form captures a price per tier, `NOT NULL DEFAULT 0.00` in the
schema.

## 10. Platinum and gold were transposed — Medium

The form posts, in order, `vipseat`, `goldseat`, `platinumseat`. The insert
writes them positionally:

```php
INSERT INTO stadium (`sName`, `a`, `b`, `c`) VALUES ('$sname', '$vipseat', '$goldseat', '$platinumseat')
```

so `b` receives gold and `c` receives platinum. But the listing renders `b` as
Platinum and `c` as Gold. Every stadium added through the form had its two lower
tiers swapped, and the booking page then priced seats against the wrong
capacity.

This is what columns named `a`, `b`, `c` cost. Nothing at the call site reads
wrongly — you have to hold the column order in your head to see it.

**Fixed**: columns renamed `capacity_vip` / `capacity_platinum` /
`capacity_gold`, and every capacity is bound to a named placeholder.

## 11. "Add booking" was an empty function — Low

```php
function addBookings(){

}
```

The admin bookings page rendered an "ADD BOOKING" button linking to
`?page=bookings&action=add`, which dispatched to this. The result was a blank
page.

**Removed** rather than implemented. An admin booking on a customer's behalf
needs a customer to attribute it to and a payment story to go with it; neither
exists here, and a button that does nothing is worse than no button.

## 12. A Windows path made the app fatal elsewhere — Medium

```php
require_once 'inc\connect.php';
```

`legacy/bookingprocess.php:3`. On Windows the backslash resolves. On Linux and
macOS `inc\connect.php` is a filename containing a backslash, the require fails,
and booking dies with a fatal error. Every other file in the project uses a
forward slash.

**Fixed** by the rewrite. Worth listing because it is the single reason the
original cannot be demonstrated on the machine this restoration was done on.

## 13. No match date — Medium

`matches.time` was a `VARCHAR` holding strings like `01:00 UKT`. There was no
date column at all, so fixtures could not be sorted or filtered chronologically,
and the homepage listed them in insertion order.

**Fixed**: `match_date DATE` and `kickoff_time TIME`, with an index on
`match_date` and `ORDER BY match_date, kickoff_time`.

## 14. Output before redirect on login — Low

```php
echo $_SESSION['user'];
```

`legacy/account/loginProcess.php:6`, above the `header('Location: ...')` calls —
debug output left in. On a first login `$_SESSION['user']` is undefined, so it
emits a notice, and any output before `header()` risks the redirect failing
outright depending on output buffering.

**Fixed**: removed.

## 15. Signup collected a name and discarded it — Low

`signupForm.php` has a `name` input. `signupProcess.php` reads `email`, `phone`,
`country` and `password`, and inserts those four. `name` is never read. The
recovered `users` table shows `NULL` in the name column for later signups —
the bug is visible in the surviving data.

**Fixed**: the name is validated and stored.

## 16. Hardcoded localhost URLs — Medium

`http://localhost/booking/...` appears in roughly ten redirect targets and nav
links. The application could only ever run from that one path on that one
machine.

**Fixed**: a `url()` helper deriving the base from the request, overridable via
`APP_BASE_URL`.

**Amended (2026-08-13).** "Overridable via `APP_BASE_URL`" undersold the risk:
with it *unset* — which is how the service was actually deployed, the variable
being only a commented-out line in `.env.example` — the base came from
`$_SERVER['HTTP_HOST']`, which the client supplies. Every `redirect()` therefore
echoed whatever host was asked for:

```
$ curl -sD - -H 'Host: evil.example' http://localhost:8080/booking.php
Location: http://evil.example/login.php
```

A working open redirect on every redirect in the app. Impact was limited — there
is no password-reset flow to poison — but it is exactly the primitive a
phishing link wants.

Three changes, in order of how much they matter:

1. `redirect()` emits a **path**, not an absolute URL. A relative `Location` is
   explicitly allowed (RFC 7231 §7.1.2) and is resolved against the current
   request, so it cannot leave the origin no matter what any header says. This
   is the fix that does not depend on anyone remembering configuration.
2. `url()` prefers Render's injected `RENDER_EXTERNAL_URL` before falling back
   to the Host header, so a deploy is correct by default.
3. `APP_BASE_URL` is now actually set in `render.yaml`.

`tests/verify.sh` covers it.

## 17. Malformed HTML on every page — Low

`legacy/footer.php` ends:

```html
</footer>

</head>
<body>
```

A closing `</head>` and an opening `<body>` at the *end* of the document, with
`</body>` and `</html>` never emitted. Browsers recover, which is why it went
unnoticed.

`myticket.php` has the matching problem from the other direction: it includes no
header or footer at all, so it renders as unstyled text on a white page. The
report's own screenshot shows this.

**Fixed**: shared `header.php` / `footer.php` that produce a well-formed
document, used by every page including `myticket.php`.

## 18. One image for every fixture — Low

```php
<img src="assets/img/arsenalvsmancity.png" class="card-img-top" alt="...">
```

Inside the loop over all matches, so every card showed the same two teams
regardless of who was playing. Three other match images were present in
`assets/img/` and unused.

**Fixed**: per-match lookup by team-name slug with an SVG placeholder fallback.

## 19. Errors printed to the response — Medium

```php
echo "Error: " . $sql . "<br>" . mysqli_error($conn);
```

Repeated at nearly every call site. On failure the page returned the full SQL
statement and MySQL's error text — table names, column names, and query
structure handed to whoever triggered it.

**Fixed**: `display_errors=Off` with `log_errors` to the container log, and PDO
in exception mode so failures are handled rather than echoed.

## 20. The booking form collected a name and discarded it — Low

```php
$Name = sanitize( $_POST['name'] );
```

`legacy/bookingprocess.php` reads it, and the INSERT that follows lists
`uid`, `mid`, `seattype`, `paidAmount` — no name. The booking page asked every
customer to type their name into a field that went nowhere. The same mistake as
#15, in a second place.

**Fixed**: the field is gone. The booking is attributed to the logged-in
session, which is the only trustworthy source for it anyway.

---

## Not addressed

- **No payments.** `paid_amount` records what a ticket cost at the time of
  booking. Nothing takes money. Adding a gateway is out of scope for a
  restoration, and claiming otherwise on a CV would not survive one question.
- **No cancellation or refunds.** Bookings are create-and-read only.
- ~~**No rate limiting on login.**~~ **Addressed 2026-08-13.** It was the one
  item on this list worth closing rather than accepting: bcrypt makes a guess
  expensive for the server, not for the attacker, so the form was happy to be
  asked forever. Failed attempts are now recorded in `login_attempts` and
  counted two ways — 10 per email and 60 per IP in a rolling 15 minutes. The
  per-IP figure is deliberately loose, because an IP is not a person and a
  tight cap mostly punishes whoever shares a NAT with the attacker; the
  per-email limit is what protects an account. A success clears both, so
  proving you hold an account redeems your address. There is no lockout to sit
  out, which matters for a public demo whose credentials are printed on the
  login page. Covered by `tests/verify.sh`.
- **No email delivery.** Confirmations are shown on screen and not sent.
