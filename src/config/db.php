<?php
declare(strict_types=1);

/**
 * Database connection.
 *
 * Replaces the original inc/connect.php and admin/inc/connect.php — two files
 * with identical contents that had already begun to drift apart, both holding
 * `mysqli_connect("localhost", "root", "", "booking")` with the credentials in
 * source control and inside the document root.
 *
 * Notable settings:
 *   ERRMODE_EXCEPTION   the original checked return values inconsistently and
 *                       echoed mysqli_error() into the page on failure
 *   EMULATE_PREPARES    false, so placeholders are sent to the server rather
 *                       than interpolated client-side
 *   ERR_MODE strict     so a truncated/invalid value is an error, not a warning
 *                       silently coercing the row
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'booking';
    $user = getenv('DB_USER') ?: 'booking';
    $pass = getenv('DB_PASSWORD') ?: '';

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");

    return $pdo;
}
