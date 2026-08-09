#!/usr/bin/env bash
#
# Does the booking path actually hold under concurrent load?
#
#   docker compose up -d && ./tests/concurrency.sh
#
# Sets up a fixture with a known capacity, then fires many simultaneous booking
# attempts at it by three strategies and counts what ended up in the table:
#
#   none   no capacity check          — what the original did
#   naive  check, then insert         — the obvious fix, still wrong
#   safe   SELECT ... FOR UPDATE      — src/config/booking.php
#
# Only `safe` is expected to hold. `none` and `naive` are run to show the test
# has the power to detect a failure: a concurrency test that has never been seen
# to fail is not evidence of anything.

set -uo pipefail

WORKERS="${WORKERS:-24}"
CAPACITY="${CAPACITY:-4}"
TIER=gold

cd "$(dirname "$0")/.." || exit 1

mysql_root() {
    docker compose exec -T db mysql -uroot -p"${MYSQL_ROOT_PASSWORD:-rootpw}" -N -B -e "$1" 2>/dev/null
}

printf '\033[1mConcurrent booking: %s workers competing for %s seats\033[0m\n\n' "$WORKERS" "$CAPACITY"

# --- fixture -------------------------------------------------------------
# A ground with exactly $CAPACITY seats in the tier under test, one match at
# it, and $WORKERS distinct users. Distinct users matter: UNIQUE (mid, uid,
# seat_tier) would otherwise reject the duplicates before capacity was ever
# the deciding factor, and the test would pass for the wrong reason.
mysql_root "
USE booking;
DELETE FROM bookings WHERE mid IN (SELECT mid FROM matches WHERE title = 'RACE FIXTURE');
DELETE FROM matches  WHERE title = 'RACE FIXTURE';
DELETE FROM users    WHERE email LIKE 'race-%@example.com';
DELETE FROM teams    WHERE name IN ('Race A', 'Race B');
DELETE FROM stadium  WHERE name = 'Race Ground';

INSERT INTO stadium (name, capacity_vip, capacity_platinum, capacity_gold)
     VALUES ('Race Ground', 999, 999, ${CAPACITY});
SET @sid = LAST_INSERT_ID();
INSERT INTO teams (name, sid) VALUES ('Race A', @sid);
SET @home = LAST_INSERT_ID();
INSERT INTO teams (name, sid) VALUES ('Race B', @sid);
SET @away = LAST_INSERT_ID();
INSERT INTO matches (title, venue, match_date, kickoff_time,
                     price_vip, price_platinum, price_gold, hometeam, awayteam)
     VALUES ('RACE FIXTURE', @sid, '2026-12-01', '15:00:00', 10, 10, 10, @home, @away);
" >/dev/null

MID=$(mysql_root "SELECT mid FROM booking.matches WHERE title='RACE FIXTURE';" | tail -1)

for i in $(seq 1 "$WORKERS"); do
    mysql_root "INSERT INTO booking.users (name, email, password, rid)
                VALUES ('Race ${i}', 'race-${i}@example.com', 'x', 2);" >/dev/null
done
mapfile -t UIDS < <(mysql_root "SELECT uid FROM booking.users WHERE email LIKE 'race-%@example.com' ORDER BY uid;")

if [ "${#UIDS[@]}" -ne "$WORKERS" ]; then
    echo "fixture setup failed: got ${#UIDS[@]} users, wanted $WORKERS" >&2
    exit 1
fi

# --- run one strategy ----------------------------------------------------
run_mode() { # mode
    local mode="$1"
    mysql_root "DELETE FROM booking.bookings WHERE mid = ${MID};" >/dev/null

    # Every worker waits for the same wall-clock instant before touching the
    # database, so they collide instead of arriving in a queue.
    local start
    start=$(docker compose exec -T web php -r 'echo microtime(true) + 2.0;')

    local cmd=""
    for uid in "${UIDS[@]}"; do
        cmd+="php /var/www/tests/race.php ${mode} ${uid} ${MID} ${TIER} ${start} & "
    done
    cmd+="wait"

    docker compose exec -T web bash -c "$cmd" >/dev/null 2>&1

    mysql_root "SELECT COUNT(*) FROM booking.bookings WHERE mid=${MID} AND seat_tier='${TIER}';" | tail -1
}

fail=0
for mode in none naive safe; do
    sold=$(run_mode "$mode")
    over=$(( sold - CAPACITY ))

    case "$mode" in
        none)
            label="no capacity check   (the original)"
            if [ "$sold" -gt "$CAPACITY" ]; then
                verdict="\033[31moversold by ${over}\033[0m"
            else
                verdict="\033[33mdid not oversell — test lacks power\033[0m"; fail=1
            fi
            ;;
        naive)
            label="check, then insert  (the obvious fix)"
            if [ "$sold" -gt "$CAPACITY" ]; then
                verdict="\033[31moversold by ${over}\033[0m"
            else
                verdict="\033[33mheld this run — race not triggered\033[0m"
            fi
            ;;
        safe)
            label="SELECT ... FOR UPDATE (src/config/booking.php)"
            if [ "$sold" -eq "$CAPACITY" ]; then
                verdict="\033[32mexactly ${CAPACITY}\033[0m"
            else
                verdict="\033[31mWRONG: ${sold}\033[0m"; fail=1
            fi
            ;;
    esac
    printf '  %-46s sold %3d / %d  %b\n' "$label" "$sold" "$CAPACITY" "$verdict"
done

# --- tidy up -------------------------------------------------------------
mysql_root "
USE booking;
DELETE FROM bookings WHERE mid = ${MID};
DELETE FROM matches  WHERE mid = ${MID};
DELETE FROM users    WHERE email LIKE 'race-%@example.com';
DELETE FROM teams    WHERE name IN ('Race A', 'Race B');
DELETE FROM stadium  WHERE name = 'Race Ground';
" >/dev/null

if [ "$fail" -eq 0 ]; then
    printf '\n\033[32mThe transactional path held; the alternatives did not.\033[0m\n'
else
    printf '\n\033[31mConcurrency check failed.\033[0m\n'
fi
exit "$fail"
