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

# Render mounts secret files under /etc/secrets owned by root. Apache serves as
# www-data, which cannot necessarily read them — PDO reports that as
# "failed loading cafile stream", which reads like a TLS problem rather than a
# permission one. The entrypoint still runs as root, so copy the CA somewhere
# world-readable and point the app at the copy.
if [ -n "${DB_SSL_CA:-}" ]; then
    if [ -f "$DB_SSL_CA" ]; then
        CA_RUNTIME=/usr/local/share/db-ca.pem
        cp "$DB_SSL_CA" "$CA_RUNTIME"
        chmod 0644 "$CA_RUNTIME"
        export DB_SSL_CA="$CA_RUNTIME"
        echo "entrypoint: CA staged at $CA_RUNTIME ($(wc -c < "$CA_RUNTIME") bytes)"
    else
        echo "entrypoint: DB_SSL_CA=$DB_SSL_CA does not exist" >&2
        echo "entrypoint: contents of /etc/secrets:" >&2
        ls -la /etc/secrets 2>&1 | sed 's/^/entrypoint:   /' >&2 || true
        # DB_SSL_CA is deliberately left set so the app fails loudly. Unsetting
        # it would not give unverified TLS — measured against Aiven, this driver
        # drops to an entirely unencrypted connection, password included.
        echo "entrypoint: leaving DB_SSL_CA set so the app refuses to connect" >&2
        echo "entrypoint: add a Secret File named ca.pem to fix this" >&2
    fi
fi

if [ -f /var/www/db/bootstrap.php ]; then
    echo "entrypoint: running database bootstrap"
    php /var/www/db/bootstrap.php || \
        echo "entrypoint: bootstrap failed, starting anyway" >&2
else
    echo "entrypoint: no bootstrap script present, skipping"
fi

echo "entrypoint: starting apache on port ${PORT:-80}"
exec apache2-foreground
