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

/**
 * The signed-in user, or null.
 *
 * Cached per request because is_logged_in(), is_admin() and require_admin()
 * all call it and a page can ask several times.
 *
 * The cache now remembers a *miss*. `static $user = null` could not tell "not
 * looked up yet" from "looked up, found nothing", so a session holding a uid
 * for a deleted user re-ran the query on every single call.
 *
 * It is also keyed by uid rather than being a bare flag, so it cannot outlive
 * a change of session identity inside one request — logging in as someone
 * else is the case that matters. (Logging *out* was already safe: log_out()
 * empties $_SESSION, and the check above returns null before the cache is
 * ever consulted.)
 */
function current_user(): ?array
{
    start_session();
    if (empty($_SESSION['uid'])) {
        return null;
    }

    static $cachedUid = null;
    static $user = null;

    $uid = $_SESSION['uid'];
    if ($cachedUid === $uid) {
        return $user;
    }

    $stmt = db()->prepare(
        'SELECT uid, name, email, rid FROM users WHERE uid = ?'
    );
    $stmt->execute([$uid]);
    $user = $stmt->fetch() ?: null;
    $cachedUid = $uid;

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

/**
 * Login throttling.
 *
 * docs/SECURITY-FINDINGS.md listed "no rate limiting on login" as accepted, and
 * it was the one accepted item worth closing: bcrypt makes each guess expensive
 * for the *server*, not for the attacker, and the login form was otherwise
 * happy to be asked forever.
 *
 * Counted two ways on purpose. Per email, so a single account cannot be ground
 * down from a botnet; per IP, so one host cannot sidestep that by spreading
 * guesses across many addresses. Either limit tripping is enough to refuse.
 *
 * Only failures are recorded, and a success clears that address's history, so
 * an ordinary user who mistypes twice and then gets in leaves nothing behind.
 * The window is a rolling one — there is no lockout to wait out and no state to
 * unstick, which matters for a public demo where the credentials are printed on
 * the page.
 */
const LOGIN_WINDOW_MINUTES = 15;
const LOGIN_MAX_PER_EMAIL  = 10;
// Deliberately much looser than the per-email limit. An IP is not a person:
// offices, universities and mobile carriers put thousands of people behind one
// address, so a tight per-IP cap mostly punishes the legitimate user who
// happens to share a NAT with someone guessing. The per-email limit is what
// actually protects an account; this one exists to make spraying across many
// accounts expensive, and 60 wrong passwords in 15 minutes from one address is
// still nothing a real person does.
const LOGIN_MAX_PER_IP     = 60;

/** Packed client address, or null when it cannot be parsed. */
function client_ip_packed(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    // Behind Render's proxy the peer is the load balancer, so the real client
    // is in X-Forwarded-For — honoured only when TRUST_PROXY says the hop is
    // trustworthy, the same rule request_is_https() applies to the scheme.
    if ((getenv('TRUST_PROXY') ?: '') !== '' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $first = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if ($first !== '') {
            $ip = $first;
        }
    }
    $packed = @inet_pton($ip);
    return $packed === false ? null : $packed;
}

/**
 * May this login attempt proceed?
 *
 * Fails *open* on a database error. A throttle that cannot read its own table
 * must not become an outage of the login page — the password check is still
 * the thing actually protecting the account.
 */
function login_throttle_check(string $email): bool
{
    try {
        $pdo = db();
        $since = 'DATE_SUB(NOW(), INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE)';

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts
              WHERE email = ? AND attempted_at > {$since}"
        );
        $stmt->execute([$email]);
        if ((int) $stmt->fetchColumn() >= LOGIN_MAX_PER_EMAIL) {
            return false;
        }

        $ip = client_ip_packed();
        if ($ip !== null) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM login_attempts
                  WHERE ip = ? AND attempted_at > {$since}"
            );
            $stmt->execute([$ip]);
            if ((int) $stmt->fetchColumn() >= LOGIN_MAX_PER_IP) {
                return false;
            }
        }
        return true;
    } catch (Throwable $e) {
        return true;
    }
}

function login_attempt_failed(string $email): void
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare('INSERT INTO login_attempts (ip, email) VALUES (?, ?)');
        $stmt->execute([client_ip_packed(), $email]);

        // Opportunistic prune. Nothing else ever deletes from this table, and
        // rows outside the window can never affect a decision again.
        if (random_int(1, 20) === 1) {
            $pdo->exec(
                'DELETE FROM login_attempts
                  WHERE attempted_at < DATE_SUB(NOW(), INTERVAL '
                . LOGIN_WINDOW_MINUTES . ' MINUTE)'
            );
        }
    } catch (Throwable $e) {
        // Recording is best-effort; never break the login page over it.
    }
}

/**
 * Clear the history a successful login has just disproved.
 *
 * The address is cleared as well as the email, and that is the part that keeps
 * the per-IP limit humane: someone behind a shared NAT who proves they hold a
 * real account redeems that address for everyone on it, instead of being
 * counted toward a cap they did not fill.
 */
function login_attempt_succeeded(string $email): void
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE email = ?');
        $stmt->execute([$email]);

        $ip = client_ip_packed();
        if ($ip !== null) {
            $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?');
            $stmt->execute([$ip]);
        }
    } catch (Throwable $e) {
    }
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
