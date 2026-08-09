<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

const ROLE_ADMIN = 1;
const ROLE_USER  = 2;

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        // request_is_https() rather than $_SERVER['HTTPS'] directly: behind a
        // TLS-terminating proxy the latter is unset, which would ship the
        // session cookie without the Secure flag on an https:// site.
        'secure'   => request_is_https(),
    ]);
    session_start();
}

function current_user(): ?array
{
    start_session();
    if (empty($_SESSION['uid'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare(
            'SELECT uid, name, email, rid FROM users WHERE uid = ?'
        );
        $stmt->execute([$_SESSION['uid']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && (int) $user['rid'] === ROLE_ADMIN;
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

/**
 * Gate for every admin entry point.
 *
 * The original had no equivalent. admin/index.php rendered the sidebar and
 * dashboard unconditionally, so /booking/admin was reachable by anyone —
 * unauthenticated included — with full create access to stadiums, teams and
 * matches, and read access to every booking. The role column existed and was
 * populated; it was simply never consulted after login.
 *
 * Note the distinction between the two failure modes: an anonymous visitor is
 * sent to log in, but a logged-in non-admin gets 403. Redirecting the latter to
 * a login page they have already passed is a dead end.
 */
function require_admin(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
    if (!is_admin()) {
        http_response_code(403);
        require __DIR__ . '/../views/403.php';
        exit;
    }
}

/**
 * Verify a password, transparently upgrading legacy hashes.
 *
 * The original stored MD5(password) with no salt — recoverable for any common
 * password in seconds, and identical for identical passwords across accounts.
 *
 * Rather than invalidate existing accounts, a stored 32-character hex hash is
 * treated as legacy: verified against MD5 once, then immediately rehashed with
 * bcrypt so the next login uses the modern path. After every active user has
 * logged in once, the legacy branch can be deleted.
 */
function verify_and_upgrade_password(array $user, string $password): bool
{
    $stored = $user['password'];

    if (preg_match('/^[a-f0-9]{32}$/i', $stored)) {
        if (!hash_equals(strtolower($stored), md5($password))) {
            return false;
        }
        $stmt = db()->prepare('UPDATE users SET password = ? WHERE uid = ?');
        $stmt->execute([password_hash($password, PASSWORD_BCRYPT), $user['uid']]);
        return true;
    }

    if (!password_verify($password, $stored)) {
        return false;
    }

    if (password_needs_rehash($stored, PASSWORD_BCRYPT)) {
        $stmt = db()->prepare('UPDATE users SET password = ? WHERE uid = ?');
        $stmt->execute([password_hash($password, PASSWORD_BCRYPT), $user['uid']]);
    }
    return true;
}

function log_in_user(array $user): void
{
    start_session();
    // Prevents session fixation: an attacker who plants a known session id
    // before login cannot ride the authenticated session afterwards.
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['uid'];
}

function log_out(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
