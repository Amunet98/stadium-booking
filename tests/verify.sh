#!/usr/bin/env bash
#
# End-to-end verification against a running stack.
#
#   docker compose up -d && ./tests/verify.sh
#
# Exercises the findings that matter through real HTTP: the access-control
# fix, the per-match seat count, capacity enforcement, and CSRF rejection.
# Everything here fails loudly; a green run means the claims in the README
# are true of the code as it currently stands.

set -uo pipefail

BASE="${BASE:-http://localhost:8080}"
JAR_DIR="$(mktemp -d)"
trap 'rm -rf "$JAR_DIR"' EXIT

PASS=0
FAIL=0

ok()   { printf '  \033[32mPASS\033[0m  %s\n' "$1"; PASS=$((PASS+1)); }
bad()  { printf '  \033[31mFAIL\033[0m  %s\n' "$1"; FAIL=$((FAIL+1)); }
head_() { printf '\n\033[1m%s\033[0m\n' "$1"; }

expect_eq() { # label expected actual
    if [ "$2" = "$3" ]; then ok "$1 ($3)"; else bad "$1 — expected $2, got $3"; fi
}

status()   { curl -s -o /dev/null -w '%{http_code}' "$@"; }
csrf_of()  { grep -oP 'name="_csrf" value="\K[^"]+' "$1" | head -1; }

# Query the database directly, so assertions rest on stored state rather than
# on what a page happens to render.
mysql_root() {
    docker compose exec -T db mysql -uroot -p"${MYSQL_ROOT_PASSWORD:-rootpw}" -N -B -e "$1"
}

# Log in, leaving the session in $JAR_DIR/$1.jar
login() { # name email password
    local jar="$JAR_DIR/$1.jar" page="$JAR_DIR/$1.html"
    curl -s -c "$jar" "$BASE/login.php" -o "$page"
    curl -s -b "$jar" -c "$jar" -X POST "$BASE/login.php" \
        -d "_csrf=$(csrf_of "$page")" -d "email=$2" -d "password=$3" -o /dev/null
}

# Seats remaining for a tier on a match, as the page reports them.
remaining() { # jar match tier
    curl -s -b "$JAR_DIR/$1.jar" "$BASE/booking.php?match=$2" \
      | tr '\n' ' ' \
      | grep -oP "id=\"tier-$3\".*?<span class=\"text-(success|danger)\">\s*\K[^<]+" \
      | head -1 | sed 's/ *$//'
}

book() { # jar match tier -> prints http status
    local jar="$JAR_DIR/$1.jar" page="$JAR_DIR/$1-book.html"
    curl -s -b "$jar" "$BASE/booking.php?match=$2" -o "$page"
    curl -s -b "$jar" -X POST "$BASE/booking.php" \
        -d "_csrf=$(csrf_of "$page")" -d "mid=$2" -d "seat_tier=$3" \
        -o "$JAR_DIR/$1-result.html" -w '%{http_code}'
}

# Reset bookings to the seeded state first. Without this the script passes only
# on a fresh volume: the capacity assertions below deliberately fill a tier, so
# a second run would start with it already sold out and fail for the wrong
# reason. A test that only passes once is not a test.
mysql_root "
USE booking;
DELETE FROM bookings;
ALTER TABLE bookings AUTO_INCREMENT = 1;
INSERT INTO bookings (uid, mid, seat_tier, paid_amount) VALUES
    (2, 1, 'vip', 295.00), (3, 1, 'gold', 55.00), (2, 3, 'platinum', 85.00);
" >/dev/null 2>&1

head_ "Access control (finding #1, #4)"
expect_eq "anonymous /admin.php redirects to login" 302 "$(status "$BASE/admin.php")"
expect_eq "anonymous /myticket.php redirects"       302 "$(status "$BASE/myticket.php")"
expect_eq "anonymous /booking.php redirects"        302 "$(status "$BASE/booking.php?match=1")"

head_ "Document root isolation (finding #6)"
for path in config/db.php admin/handlers.php inc/connect.php; do
    expect_eq "/$path is not served" 404 "$(status "$BASE/$path")"
done

head_ "Roles"
login user  alex@example.com  'Passw0rd!23'
login admin admin@example.com 'Admin!2345'
expect_eq "logged-in non-admin gets 403 on /admin.php" 403 "$(status -b "$JAR_DIR/user.jar" "$BASE/admin.php")"
expect_eq "admin reaches /admin.php"                   200 "$(status -b "$JAR_DIR/admin.jar" "$BASE/admin.php")"

head_ "CSRF (finding #5)"
expect_eq "POST without a token is rejected" 403 \
    "$(status -b "$JAR_DIR/user.jar" -X POST "$BASE/booking.php" -d 'mid=1' -d 'seat_tier=gold')"
expect_eq "POST with a wrong token is rejected" 403 \
    "$(status -b "$JAR_DIR/user.jar" -X POST "$BASE/booking.php" -d '_csrf=deadbeef' -d 'mid=1' -d 'seat_tier=gold')"
# A refused POST must not have written anything.
csrf_rows=$(curl -s -b "$JAR_DIR/user.jar" -X POST "$BASE/booking.php" \
    -d '_csrf=deadbeef' -d 'mid=1' -d 'seat_tier=gold' -o /dev/null -w '%{http_code}')
expect_eq "a refused POST is inert" 403 "$csrf_rows"

head_ "Seat counting is scoped to the match (finding #7)"
# Anfield releases 2 VIP seats; match 2 is played there, match 1 is not.
before_m2=$(remaining user 2 vip)
before_m1=$(remaining user 1 vip)
book user 2 vip >/dev/null
after_m2=$(remaining user 2 vip)
after_m1=$(remaining user 1 vip)

if [ "$before_m2" != "$after_m2" ]; then
    ok "booking match 2 decremented match 2 ($before_m2 -> $after_m2)"
else
    bad "booking match 2 left match 2 unchanged ($before_m2)"
fi
if [ "$before_m1" = "$after_m1" ]; then
    ok "booking match 2 left match 1 untouched ($after_m1)"
else
    bad "booking match 2 changed match 1 ($before_m1 -> $after_m1) — the cross-match bug is back"
fi

head_ "Capacity is enforced (finding #8)"
# One VIP seat is left at Anfield. A second user takes it; a third is
# refused. The UNIQUE constraint stops the same user buying the tier twice, so
# this needs distinct accounts.
login sam    sam@example.com    'Passw0rd!23'
login jordan jordan@example.com 'Passw0rd!23'

book sam 2 vip >/dev/null
sold_out_state=$(remaining user 2 vip)
expect_eq "tier reads sold out once capacity is reached" "Sold out" "$sold_out_state"

book jordan 2 vip >/dev/null
if grep -q "sold out for this match" "$JAR_DIR/jordan-result.html"; then
    ok "the over-capacity booking was refused with a clear message"
else
    bad "the over-capacity booking was not refused"
fi

# The database is the authority, not the page.
booked=$(mysql_root "SELECT COUNT(*) FROM booking.bookings WHERE mid=2 AND seat_tier='vip';" 2>/dev/null | tail -1)
expect_eq "exactly 2 VIP rows exist for match 2" 2 "$booked"

head_ "Duplicate booking (schema UNIQUE)"
book user 2 gold >/dev/null
book user 2 gold >/dev/null
if grep -q "already have a" "$JAR_DIR/user-result.html"; then
    ok "a repeated booking is rejected by the unique constraint"
else
    bad "a repeated booking was allowed"
fi

head_ "Seat tier vocabulary (finding #7)"
# The recovered data held both 'a' and 'priceA' in this column. Neither should
# now be insertable. MySQL warns about passwords on the command line, so filter
# that line out before looking for the error.
bad_tier=$(mysql_root \
    "INSERT INTO booking.bookings (uid,mid,seat_tier,paid_amount) VALUES (2,4,'priceA',10);" 2>&1 \
    | grep -v 'Using a password' | head -2)
if echo "$bad_tier" | grep -qi "check constraint"; then
    ok "the database rejects a legacy 'priceA' seat value"
else
    bad "a legacy seat value was accepted: ${bad_tier:-<no error>}"
fi

printf '\n\033[1m%d passed, %d failed\033[0m\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
