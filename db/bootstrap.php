<?php
declare(strict_types=1);

/**
 * Apply the schema and seed to a database that does not have them yet.
 *
 *   php db/bootstrap.php          # create only if absent
 *   php db/bootstrap.php --force  # drop and recreate (used by the reset job)
 *
 * docker-compose applies db/schema.sql and db/seed.sql by mounting them into
 * /docker-entrypoint-initdb.d/, which is a feature of the MySQL *container's*
 * entrypoint. A managed database has no such hook, so a fresh deploy would come
 * up with zero tables. This does the same job over an ordinary connection.
 *
 * Connection settings come from the same environment variables the application
 * reads, so a correctly configured deploy needs no extra configuration here.
 */

require_once __DIR__ . '/../src/config/db.php';

$force = in_array('--force', $argv, true);

/**
 * Split a .sql file into statements.
 *
 * Naive splitting on ';' breaks on semicolons inside string literals and
 * comments — both appear in seed.sql (descriptions, team details). This walks
 * the file tracking quote state instead.
 */
function sql_statements(string $sql): array
{
    $statements = [];
    $current    = '';
    $inSingle   = false;
    $inDouble   = false;
    $inLineComment = false;
    $length     = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $sql[$i + 1] ?? '';

        if ($inLineComment) {
            if ($char === "\n") {
                $inLineComment = false;
                $current .= $char;
            }
            continue;
        }

        if (!$inSingle && !$inDouble && $char === '-' && $next === '-') {
            $inLineComment = true;
            continue;
        }

        if ($char === "'" && !$inDouble) {
            // Doubled '' is an escaped quote, not a terminator.
            if ($inSingle && $next === "'") {
                $current .= $char . $next;
                $i++;
                continue;
            }
            $inSingle = !$inSingle;
        } elseif ($char === '"' && !$inSingle) {
            $inDouble = !$inDouble;
        } elseif ($char === '\\' && ($inSingle || $inDouble)) {
            $current .= $char . $next;
            $i++;
            continue;
        }

        if ($char === ';' && !$inSingle && !$inDouble) {
            $trimmed = trim($current);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $current = '';
            continue;
        }

        $current .= $char;
    }

    $trimmed = trim($current);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }
    return $statements;
}

function run_file(PDO $pdo, string $path): int
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("cannot read {$path}");
    }
    $count = 0;
    foreach (sql_statements($sql) as $statement) {
        $pdo->exec($statement);
        $count++;
    }
    return $count;
}

try {
    $pdo = db();

    $schemaName = getenv('DB_NAME') ?: 'booking';
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema = ? AND table_name = ?'
    );
    $stmt->execute([$schemaName, 'bookings']);
    $alreadySetUp = (int) $stmt->fetchColumn() > 0;

    if ($alreadySetUp && !$force) {
        fwrite(STDOUT, "bootstrap: schema already present, nothing to do\n");
        exit(0);
    }

    if ($alreadySetUp) {
        fwrite(STDOUT, "bootstrap: --force given, recreating from scratch\n");
    }

    // schema.sql leads with DROP TABLE IF EXISTS in dependency order, so it is
    // safe to re-run and doubles as the reset path.
    $n = run_file($pdo, __DIR__ . '/schema.sql');
    fwrite(STDOUT, "bootstrap: schema.sql applied ({$n} statements)\n");

    $n = run_file($pdo, __DIR__ . '/seed.sql');
    fwrite(STDOUT, "bootstrap: seed.sql applied ({$n} statements)\n");

    $tables = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"
    )->fetchColumn();
    fwrite(STDOUT, "bootstrap: done, {$tables} tables present\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'bootstrap failed: ' . $e->getMessage() . "\n");
    exit(1);
}
