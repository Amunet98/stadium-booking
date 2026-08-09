<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Booking logic.
 *
 * The original wrote a booking like this (bookingprocess.php):
 *
 *     $sql = "INSERT INTO bookings (uid, mid, seattype, paidAmount)
 *             VALUES ('$uid', '$mid', '$Seat', '$paidAmount')";
 *
 * No capacity check, no transaction, no constraint. The seat counter on the
 * booking page was display-only and, separately, broken: it counted bookings
 * across every match rather than the one being viewed, and matched on a seat
 * value the write path had stopped producing. Nothing anywhere prevented
 * selling the same seat tier past the capacity of the ground.
 */

final class BookingResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly string $code,
        public readonly string $message,
        public readonly ?int $bookingId = null,
    ) {}

    public static function success(int $bookingId): self
    {
        return new self(true, 'ok', 'Booking confirmed.', $bookingId);
    }

    public static function failure(string $code, string $message): self
    {
        return new self(false, $code, $message);
    }
}

/**
 * Seats left in a tier for one match.
 *
 * Scoped to the match — which the original's count was not.
 */
function seats_remaining(PDO $pdo, int $matchId, string $tier): int
{
    if (!is_valid_tier($tier)) {
        throw new InvalidArgumentException("Unknown seat tier: {$tier}");
    }
    $capacityColumn = tier_capacity_column($tier);

    $stmt = $pdo->prepare(
        "SELECT s.{$capacityColumn} - (
                    SELECT COUNT(*) FROM bookings b
                    WHERE b.mid = m.mid AND b.seat_tier = :tier
                ) AS remaining
           FROM matches m
           JOIN stadium s ON s.sid = m.venue
          WHERE m.mid = :mid"
    );
    $stmt->execute(['mid' => $matchId, 'tier' => $tier]);
    $row = $stmt->fetch();

    return $row === false ? 0 : max(0, (int) $row['remaining']);
}

/** Remaining seats for every tier of a match, keyed by tier. */
function seats_remaining_all(PDO $pdo, int $matchId): array
{
    $out = [];
    foreach (SEAT_TIERS as $tier) {
        $out[$tier] = seats_remaining($pdo, $matchId, $tier);
    }
    return $out;
}

/**
 * Create a booking, or explain why not.
 *
 * Correctness rests on three layers, deliberately:
 *
 *   1. SELECT ... FOR UPDATE on the match row, so two concurrent requests for
 *      the same fixture serialise instead of both reading the same free count
 *      and both inserting. `OF m` restricts the lock to `matches` — without it
 *      the joined `stadium` row is locked too, needlessly serialising bookings
 *      for unrelated matches sharing a ground.
 *   2. The count and the insert inside one transaction, so the count cannot go
 *      stale between check and write.
 *   3. UNIQUE (mid, uid, seat_tier) in the schema, as a backstop that holds
 *      even if some future code path forgets the transaction.
 *
 * Layer 1 alone would be enough for correctness here. Layer 3 exists because
 * the application is not the only thing that will ever write to this table.
 */
function create_booking(PDO $pdo, int $userId, int $matchId, string $tier): BookingResult
{
    if (!is_valid_tier($tier)) {
        return BookingResult::failure('bad_tier', 'That seat type does not exist.');
    }

    $capacityColumn = tier_capacity_column($tier);
    $priceColumn    = tier_price_column($tier);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT m.mid, m.{$priceColumn} AS price, s.{$capacityColumn} AS capacity
               FROM matches m
               JOIN stadium s ON s.sid = m.venue
              WHERE m.mid = :mid
              FOR UPDATE OF m"
        );
        $stmt->execute(['mid' => $matchId]);
        $match = $stmt->fetch();

        if ($match === false) {
            $pdo->rollBack();
            return BookingResult::failure('no_match', 'That match does not exist.');
        }

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM bookings WHERE mid = :mid AND seat_tier = :tier'
        );
        $countStmt->execute(['mid' => $matchId, 'tier' => $tier]);
        $booked = (int) $countStmt->fetchColumn();

        if ($booked >= (int) $match['capacity']) {
            $pdo->rollBack();
            return BookingResult::failure(
                'sold_out',
                sprintf('%s seats are sold out for this match.', tier_label($tier))
            );
        }

        $insert = $pdo->prepare(
            'INSERT INTO bookings (uid, mid, seat_tier, paid_amount)
             VALUES (:uid, :mid, :tier, :amount)'
        );
        $insert->execute([
            'uid'    => $userId,
            'mid'    => $matchId,
            'tier'   => $tier,
            'amount' => $match['price'],
        ]);

        $bookingId = (int) $pdo->lastInsertId();
        $pdo->commit();

        return BookingResult::success($bookingId);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // 23000 is the integrity-constraint class. Here it means the UNIQUE on
        // (mid, uid, seat_tier) fired: this user already holds this tier for
        // this match. That is a user-facing condition, not a server error.
        if ($e->getCode() === '23000') {
            return BookingResult::failure(
                'duplicate',
                'You already have a ' . tier_label($tier) . ' ticket for this match.'
            );
        }
        throw $e;
    }
}
