<?php
declare(strict_types=1);

/**
 * Shared helpers: output escaping, URL building, seat-tier vocabulary.
 */

/**
 * Escape for HTML output.
 *
 * The original had the inverse of this: a sanitize() function, copy-pasted into
 * four files, that ran trim + stripslashes + htmlspecialchars + real_escape_string
 * over input on the way *in*. That corrupts stored data (an apostrophe in a name
 * becomes &#039; in the database) while still leaving the SQL layer relying on
 * string interpolation. Escaping belongs at the point of output, and SQL safety
 * belongs to prepared statements.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Build an application URL.
 *
 * The original hardcoded "http://localhost/booking/..." in ten redirect targets,
 * so the app could only ever run from that one path on that one machine.
 */
function url(string $path = ''): string
{
    $base = rtrim(getenv('APP_BASE_URL') ?: '', '/');
    if ($base === '') {
        $scheme = request_is_https() ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = $scheme . '://' . $host;
    }
    return $base . '/' . ltrim($path, '/');
}

/**
 * Is the request arriving over HTTPS?
 *
 * `$_SERVER['HTTPS']` alone is only true when PHP itself terminated TLS. Every
 * hosting platform terminates TLS at a load balancer and forwards plain HTTP to
 * the container, so on a deployed site that check reads false on an https:// page
 * — which would make url() emit http:// links and drop the Secure flag off the
 * session cookie.
 *
 * X-Forwarded-Proto carries the real scheme, but it is client-supplied and
 * trivially spoofed, so it is honoured only when TRUST_PROXY confirms something
 * in front of us is actually setting it.
 */
function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (getenv('TRUST_PROXY') !== '1') {
        return false;
    }
    // Proxies may forward a comma-separated chain; the client's hop is first.
    $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    return strtolower(trim(explode(',', $proto)[0])) === 'https';
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/**
 * The seat-tier vocabulary.
 *
 * The original used three at once: the database held 'a'/'b'/'c' capacity
 * columns, the booking form posted 'priceA'/'priceB'/'priceC', and the
 * availability count queried for 'a'. Recovered data contains both 'a' and
 * 'priceB' rows, so the two never agreed (see docs/SECURITY-FINDINGS.md #7).
 *
 * One vocabulary now, enforced by a CHECK constraint in the schema.
 */
const SEAT_TIERS = ['vip', 'platinum', 'gold'];

function tier_label(string $tier): string
{
    // 'vip' is an initialism, so ucfirst() would render it "Vip".
    return $tier === 'vip' ? 'VIP' : ucfirst($tier);
}

function is_valid_tier(string $tier): bool
{
    return in_array($tier, SEAT_TIERS, true);
}

/** Column holding this tier's per-match price. */
function tier_price_column(string $tier): string
{
    if (!is_valid_tier($tier)) {
        throw new InvalidArgumentException("Unknown seat tier: {$tier}");
    }
    return 'price_' . $tier;
}

/** Column holding this tier's capacity at a ground. */
function tier_capacity_column(string $tier): string
{
    if (!is_valid_tier($tier)) {
        throw new InvalidArgumentException("Unknown seat tier: {$tier}");
    }
    return 'capacity_' . $tier;
}

function money(float|string|null $amount): string
{
    return number_format((float) $amount, 2);
}

/**
 * Resolve an asset path stored in teams.photo / stadium.photo to a URL.
 *
 * Those two columns existed in the recovered schema and were never populated;
 * the original hardcoded one team's image onto every fixture card instead. The
 * seed fills them now, but the admin panel creates teams and grounds without
 * artwork, so a missing or bogus value has to degrade rather than 404.
 *
 * The file_exists() check is what makes that work, and it is also why the
 * value is treated as untrusted: it reaches here from a database column an
 * admin can write, so basename() strips any traversal before it is used.
 */
function asset_image(?string $stored, string $fallback = 'assets/img/placeholder.svg'): string
{
    if ($stored === null || $stored === '') {
        return url($fallback);
    }

    $parts = array_map('basename', array_slice(explode('/', $stored), -2));
    $path  = 'assets/img/' . implode('/', $parts);

    return file_exists(__DIR__ . '/../public/' . $path) ? url($path) : url($fallback);
}

/**
 * How an availability count should be presented.
 *
 * Returns the pill modifier and the label. The label always states the
 * situation in words — "Sold out", "3 left" — because colour on its own is
 * not information to anyone who cannot distinguish these two greens.
 */
function seat_status(int $remaining, int $capacity): array
{
    if ($remaining === 0) {
        return ['none', 'Sold out'];
    }
    // Below a fifth of the allocation, or in single figures, counts as scarce.
    if ($capacity > 0 && ($remaining <= 9 || $remaining / $capacity <= 0.2)) {
        return ['low', $remaining . ' left'];
    }
    return ['ok', $remaining . ' left'];
}
