<?php
declare(strict_types=1);

/**
 * One booking attempt, by one of three strategies.
 *
 *   php race.php <mode> <uid> <mid> <tier>
 *
 * Modes:
 *   none   Insert with no capacity check at all — what the original did.
 *   naive  SELECT COUNT(*), compare, then INSERT. No transaction, no lock.
 *          This is the obvious fix, and it is still wrong.
 *   safe   create_booking() from src/config/booking.php.
 *
 * Run concurrently by tests/concurrency.sh. Exits 0 on a booking, 1 on a
 * refusal, 2 on an error, so the caller can count outcomes.
 */

require_once '/var/www/html/config/db.php';
require_once '/var/www/html/config/helpers.php';
require_once '/var/www/html/config/booking.php';

[$mode, $uid, $mid, $tier] = [
    $argv[1] ?? 'safe',
    (int) ($argv[2] ?? 0),
    (int) ($argv[3] ?? 0),
    $argv[4] ?? 'gold',
];

$pdo = db();

// Start all workers at the same instant. Without this they queue behind their
// own connection setup and the interesting interleavings never happen.
$startAt = (float) ($argv[5] ?? 0);
if ($startAt > 0) {
    $wait = $startAt - microtime(true);
    if ($wait > 0) {
        usleep((int) ($wait * 1_000_000));
    }
}

try {
    switch ($mode) {
        case 'none':
            // No check whatsoever.
            $price = $pdo->query(
                "SELECT price_{$tier} FROM matches WHERE mid = {$mid}"
            )->fetchColumn();
            $stmt = $pdo->prepare(
                'INSERT INTO bookings (uid, mid, seat_tier, paid_amount) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$uid, $mid, $tier, $price]);
            exit(0);

        case 'naive':
            // Check, then write — with a gap in between, and no lock holding
            // the answer still. Every worker reads the same count.
            $capCol = tier_capacity_column($tier);
            $row = $pdo->query(
                "SELECT s.{$capCol} AS capacity, m.price_{$tier} AS price
                   FROM matches m JOIN stadium s ON s.sid = m.venue
                  WHERE m.mid = {$mid}"
            )->fetch();

            $booked = (int) $pdo->query(
                "SELECT COUNT(*) FROM bookings WHERE mid = {$mid} AND seat_tier = '{$tier}'"
            )->fetchColumn();

            if ($booked >= (int) $row['capacity']) {
                exit(1);
            }

            // The gap. Real applications have one whether they mean to or not:
            // a template render, a price lookup, a network hop.
            usleep(random_int(1000, 8000));

            $stmt = $pdo->prepare(
                'INSERT INTO bookings (uid, mid, seat_tier, paid_amount) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$uid, $mid, $tier, $row['price']]);
            exit(0);

        case 'safe':
            $result = create_booking($pdo, $uid, $mid, $tier);
            exit($result->ok ? 0 : 1);

        default:
            fwrite(STDERR, "unknown mode: {$mode}\n");
            exit(2);
    }
} catch (Throwable $e) {
    // A UNIQUE violation here is the schema catching what the code missed.
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n");
    exit(2);
}
