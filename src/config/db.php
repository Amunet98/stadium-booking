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
    //
    // If the path is set but unreadable, PDO raises "failed loading cafile
    // stream", which reads as a TLS fault when it is really a file permission
    // one. Fail with a message that says which.
    //
    // Deliberately fatal rather than falling back. Measured against Aiven:
    // dropping MYSQL_ATTR_SSL_CA does not downgrade to unverified TLS, it
    // downgrades to *no* TLS — `SHOW STATUS LIKE 'Ssl_cipher'` comes back
    // empty, and MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false does not change
    // that. A "graceful" fallback here would put the database password on the
    // public internet in cleartext on every connection. Better to be down.
    if ($ca = getenv('DB_SSL_CA')) {
        if (!is_readable($ca)) {
            throw new RuntimeException(
                "DB_SSL_CA={$ca} is not readable by " . (get_current_user() ?: 'this process')
                . '. Refusing to connect: without the CA this driver falls back to an'
                . ' unencrypted connection rather than unverified TLS.'
            );
        }
        $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
    }

    $pdo = new PDO($dsn, $user, $pass, $options);

    $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");

    return $pdo;
}
