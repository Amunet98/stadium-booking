#!/usr/bin/env bash
#
# Apply schema.sql and seed.sql to the remote database in .env.aiven.
#
#   ./tests/bootstrap-remote.sh          # create only if absent
#   ./tests/bootstrap-remote.sh --force  # drop and recreate
#
# The deployed container does this for itself on first boot. This is for
# running it from here — to confirm the credentials work end to end before
# creating the Render service, or to reset the demo by hand.

set -uo pipefail
cd "$(dirname "$0")/.."

ENV_FILE="${ENV_FILE:-.env.aiven}"
[ -f "$ENV_FILE" ] || { echo "No $ENV_FILE — see .env.aiven.example" >&2; exit 1; }

set -a; . "./$ENV_FILE"; set +a
: "${DB_HOST:?}" "${DB_PORT:?}" "${DB_NAME:?}" "${DB_USER:?}" "${DB_PASSWORD:?}"

IMAGE=stadium-booking-bootstrap
docker build -q -t "$IMAGE" . >/dev/null 2>&1 || { echo "docker build failed" >&2; exit 1; }

MOUNT=()
CA_IN_CONTAINER=""
if [ -n "${DB_SSL_CA:-}" ] && [ -f "${DB_SSL_CA}" ]; then
    # ,z relabels for SELinux — without it the container cannot read the CA on
    # Fedora/RHEL and the failure surfaces as an SSL error, not a file error.
    MOUNT=(-v "$(realpath "$DB_SSL_CA")":/tmp/ca.pem:ro,z)
    CA_IN_CONTAINER=/tmp/ca.pem
fi

echo "Bootstrapping ${DB_USER}@${DB_HOST}:${DB_PORT}/${DB_NAME}"
docker run --rm "${MOUNT[@]}" \
    -e DB_HOST -e DB_PORT -e DB_NAME -e DB_USER -e DB_PASSWORD \
    -e DB_SSL_CA="$CA_IN_CONTAINER" \
    --entrypoint php "$IMAGE" /var/www/db/bootstrap.php "$@"
status=$?

docker rmi -f "$IMAGE" >/dev/null 2>&1
exit "$status"
