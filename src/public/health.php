<?php
declare(strict_types=1);

/**
 * Liveness probe for the hosting platform.
 *
 * Deliberately answers 200 even when the database is unreachable. The obvious
 * alternative — pointing the health check at index.php — was what render.yaml
 * did, and it turned a dead database into a dead *site*: index.php calls db()
 * before it renders anything, so the check failed, the service never went
 * live, and requests hung with no status at all rather than returning an
 * error. A container that boots and says what is wrong is easier to diagnose
 * from outside than one the platform refuses to route to; docker-entrypoint.sh
 * already makes the same call about a failed bootstrap.
 *
 * So: 200 means "PHP is serving". Read the db line to learn whether the
 * application can actually do anything.
 *
 * The exception message is not echoed. This URL is public and unauthenticated,
 * and PDO's connection errors carry the host, port and user.
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$db = 'unavailable';
try {
    db()->query('SELECT 1');
    $db = 'ok';
} catch (Throwable $e) {
    // Into the container log, where it is safe to be specific.
    error_log('health: database unreachable: ' . $e->getMessage());
}

echo "ok\n";
echo "db: {$db}\n";
