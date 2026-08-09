#!/bin/sh
# Container entrypoint.
#
# Runs the database bootstrap before Apache starts, so a fresh deploy comes up
# with its schema and seed data without a manual step. bootstrap.php checks for
# an existing `bookings` table and does nothing if it finds one, so this is
# harmless on every restart after the first.
#
# A bootstrap failure is logged but does not stop Apache: a site that boots and
# reports a database error is easier to diagnose from the outside than a
# container that exits and takes its logs with it.

set -e

if [ -f /var/www/db/bootstrap.php ]; then
    echo "entrypoint: running database bootstrap"
    php /var/www/db/bootstrap.php || \
        echo "entrypoint: bootstrap failed, starting anyway" >&2
else
    echo "entrypoint: no bootstrap script present, skipping"
fi

echo "entrypoint: starting apache on port ${PORT:-80}"
exec apache2-foreground
