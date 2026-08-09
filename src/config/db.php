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

    $port = getenv('DB_PORT') ?: '3306';
    $dsn  = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Managed MySQL requires TLS, and providers issue non-standard ports.
    // DB_SSL_CA points at a CA bundle on disk; with it set the server
    // certificate is verified, which is the part that makes encryption
    // worth having.
    if ($ca = getenv('DB_SSL_CA')) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
    }

    $pdo = new PDO($dsn, $user, $pass, $options);

    $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");

    return $pdo;
}
