#!/usr/bin/env bash
#
# Check that a managed MySQL is reachable and can actually run this app.
#
#   cp .env.aiven.example .env.aiven   # fill in from the Aiven console
#   ./tests/check-db.sh
#
# Reads credentials from .env.aiven (gitignored) so they never have to be typed
# into a terminal, pasted into a chat, or committed. Nothing here prints the
# password.
#
# Beyond "can I connect", this checks the two things that decide whether the
# provider is usable at all: MySQL 8.0.1+ for `SELECT ... FOR UPDATE OF`, and
# CHECK constraints that are actually enforced rather than parsed and ignored.

set -uo pipefail
cd "$(dirname "$0")/.."

ENV_FILE="${ENV_FILE:-.env.aiven}"
if [ ! -f "$ENV_FILE" ]; then
    echo "No $ENV_FILE. Copy .env.aiven.example and fill it in from the Aiven console." >&2
    exit 1
fi

set -a; . "./$ENV_FILE"; set +a

: "${DB_HOST:?DB_HOST not set in $ENV_FILE}"
: "${DB_PORT:?DB_PORT not set in $ENV_FILE}"
: "${DB_NAME:?DB_NAME not set in $ENV_FILE}"
: "${DB_USER:?DB_USER not set in $ENV_FILE}"
: "${DB_PASSWORD:?DB_PASSWORD not set in $ENV_FILE}"

PASS=0; FAIL=0
ok()  { printf '  \033[32mPASS\033[0m  %s\n' "$1"; PASS=$((PASS+1)); }
bad() { printf '  \033[31mFAIL\033[0m  %s\n' "$1"; FAIL=$((FAIL+1)); }

echo "Checking ${DB_USER}@${DB_HOST}:${DB_PORT}/${DB_NAME}"
echo

# Everything runs inside the app image, so this tests the exact PHP and PDO the
# deployment uses rather than whatever the host happens to have.
IMAGE=stadium-booking-dbcheck
docker build -q -t "$IMAGE" . >/dev/null 2>&1 || { echo "docker build failed" >&2; exit 1; }

MOUNT=()
if [ -n "${DB_SSL_CA:-}" ] && [ -f "${DB_SSL_CA}" ]; then
    MOUNT=(-v "$(realpath "$DB_SSL_CA")":/tmp/ca.pem:ro)
    CA_IN_CONTAINER=/tmp/ca.pem
else
    CA_IN_CONTAINER=""
    echo "  note: no CA file, connecting without certificate verification"
fi

run_php() {
    docker run --rm "${MOUNT[@]}" \
        -e DB_HOST -e DB_PORT -e DB_NAME -e DB_USER -e DB_PASSWORD \
        -e DB_SSL_CA="$CA_IN_CONTAINER" \
        --entrypoint php "$IMAGE" -r "$1" 2>&1
}

out=$(run_php '
require "/var/www/html/config/db.php";
try {
    $pdo = db();
    $v = $pdo->query("SELECT VERSION()")->fetchColumn();
    echo "VERSION:$v\n";
    $ssl = $pdo->query("SHOW STATUS LIKE \"Ssl_cipher\"")->fetch(PDO::FETCH_ASSOC);
    echo "CIPHER:" . ($ssl["Value"] ?: "none") . "\n";
} catch (Throwable $e) {
    echo "ERROR:" . $e->getMessage() . "\n";
}
')

if echo "$out" | grep -q "^ERROR:"; then
    bad "connect — $(echo "$out" | sed -n 's/^ERROR://p' | head -1)"
    echo; printf '\033[31m%d passed, %d failed\033[0m\n' "$PASS" "$FAIL"; exit 1
fi

version=$(echo "$out" | sed -n 's/^VERSION://p')
cipher=$(echo "$out" | sed -n 's/^CIPHER://p')
ok "connected (MySQL $version)"

[ "$cipher" != "none" ] && ok "TLS active ($cipher)" || bad "connection is NOT encrypted"

# 8.0.1+ is required for FOR UPDATE OF; below that the booking lock will not parse.
major=$(echo "$version" | cut -d. -f1)
minor=$(echo "$version" | cut -d. -f2)
patch=$(echo "$version" | cut -d. -f3 | tr -cd '0-9')
if [ "${major:-0}" -gt 8 ] || { [ "${major:-0}" -eq 8 ] && { [ "${minor:-0}" -gt 0 ] || [ "${patch:-0}" -ge 1 ]; }; }; then
    ok "version supports SELECT ... FOR UPDATE OF"
else
    bad "MySQL $version is too old for FOR UPDATE OF (need 8.0.1+)"
fi

# Prove it rather than trusting the version string — some compatible engines
# report 8.x and still reject the syntax.
syn=$(run_php '
require "/var/www/html/config/db.php";
$pdo = db();
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS _probe (id INT PRIMARY KEY, n INT, CHECK (n >= 0))");
    $pdo->exec("INSERT IGNORE INTO _probe VALUES (1, 5)");
    $pdo->beginTransaction();
    $pdo->query("SELECT p.id FROM _probe p WHERE p.id = 1 FOR UPDATE OF p")->fetchAll();
    $pdo->commit();
    echo "LOCK:ok\n";
} catch (Throwable $e) { echo "LOCK:" . $e->getMessage() . "\n"; }
try {
    $pdo->exec("INSERT INTO _probe VALUES (2, -1)");
    echo "CHECK:not-enforced\n";
} catch (Throwable $e) { echo "CHECK:enforced\n"; }
$pdo->exec("DROP TABLE IF EXISTS _probe");
')

echo "$syn" | grep -q "^LOCK:ok$" \
    && ok "FOR UPDATE OF actually works on this server" \
    || bad "FOR UPDATE OF rejected — $(echo "$syn" | sed -n 's/^LOCK://p')"

echo "$syn" | grep -q "^CHECK:enforced$" \
    && ok "CHECK constraints are enforced" \
    || bad "CHECK constraints are parsed but ignored"

docker rmi -f "$IMAGE" >/dev/null 2>&1

echo
if [ "$FAIL" -eq 0 ]; then
    printf '\033[32m%d passed. This database can run the app.\033[0m\n' "$PASS"
    echo "Next: ./tests/bootstrap-remote.sh to create the schema and seed."
else
    printf '\033[31m%d passed, %d failed.\033[0m\n' "$PASS" "$FAIL"
fi
exit "$FAIL"
