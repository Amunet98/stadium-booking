<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/**
 * Per-session CSRF tokens.
 *
 * The original had no protection on any state-changing form. Combined with the
 * missing admin guard, a third-party page could silently POST to the admin
 * process endpoint on behalf of anyone who visited it.
 */
function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Verify the token on a POST, or refuse the request.
 *
 * 403, not 419: 419 is a Laravel convention rather than an HTTP status code,
 * and Apache rewrites status codes it does not recognise to 500 — which would
 * report a client-side token failure as a server fault.
 */
function csrf_verify(): void
{
    start_session();
    $sent = $_POST['_csrf'] ?? '';
    $held = $_SESSION['csrf_token'] ?? '';

    // hash_equals: constant-time, so a comparison cannot be timed to leak the
    // token a character at a time.
    if ($held === '' || !is_string($sent) || !hash_equals($held, $sent)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Invalid or missing request token. Please go back, reload the page and try again.');
    }
}
