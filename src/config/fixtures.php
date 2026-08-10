<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/booking.php';

/**
 * Reading fixtures.
 *
 * The landing page and the listing page ask for the same rows with different
 * filters, so the join lives here once. Both need the venue and both clubs'
 * artwork columns, which is the part most likely to be forgotten if this were
 * copied into each page.
 */

/**
 * Fixtures, ordered by kick-off, with the venue and both teams resolved.
 *
 * $filters accepts:
 *   venue      int     a stadium sid
 *   tier       string  a seat tier the fixture must still have seats in
 *   available  bool    exclude fixtures with nothing left at all
 *   limit      int     cap the number returned
 *
 * `tier` and `available` are applied in PHP rather than SQL: availability
 * comes from seats_remaining_many(), which already answers the question for
 * every fixture in two queries. Pushing them into the WHERE clause would mean
 * a correlated subquery per tier per row to say the same thing.
 */
function fetch_fixtures(PDO $pdo, array $filters = []): array
{
    $sql = 'SELECT m.mid, m.title, m.description, m.match_date, m.kickoff_time, m.ptw,
                   m.price_vip, m.price_platinum, m.price_gold,
                   s.sid AS venue_id, s.name AS venue_name, s.photo AS venue_photo,
                   s.capacity_vip, s.capacity_platinum, s.capacity_gold,
                   home.name AS home_team, home.photo AS home_photo,
                   away.name AS away_team, away.photo AS away_photo
              FROM matches m
              JOIN stadium s   ON s.sid = m.venue
              JOIN teams  home ON home.tid = m.hometeam
              JOIN teams  away ON away.tid = m.awayteam';

    $params = [];
    if (!empty($filters['venue'])) {
        $sql .= ' WHERE s.sid = :venue';
        $params['venue'] = (int) $filters['venue'];
    }

    // ORDER BY match_date: the original had no date column at all, so fixtures
    // came back in insertion order.
    $sql .= ' ORDER BY m.match_date, m.kickoff_time';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $remaining = seats_remaining_many($pdo);
    $out = [];

    foreach ($rows as $row) {
        $mid  = (int) $row['mid'];
        $left = $remaining[$mid] ?? array_fill_keys(SEAT_TIERS, 0);

        if (!empty($filters['tier']) && is_valid_tier((string) $filters['tier'])
            && $left[$filters['tier']] === 0) {
            continue;
        }
        if (!empty($filters['available']) && array_sum($left) === 0) {
            continue;
        }

        $row['remaining'] = $left;
        $row['capacity']  = [
            'vip'      => (int) $row['capacity_vip'],
            'platinum' => (int) $row['capacity_platinum'],
            'gold'     => (int) $row['capacity_gold'],
        ];
        $out[] = $row;

        if (!empty($filters['limit']) && count($out) >= (int) $filters['limit']) {
            break;
        }
    }

    return $out;
}

/** Every ground, for the filter select and the venues strip. */
function fetch_venues(PDO $pdo): array
{
    return $pdo->query(
        'SELECT s.sid, s.name, s.photo, s.description,
                s.capacity_vip + s.capacity_platinum + s.capacity_gold AS seats,
                COUNT(m.mid) AS fixtures
           FROM stadium s
           LEFT JOIN matches m ON m.venue = s.sid
          GROUP BY s.sid, s.name, s.photo, s.description,
                   s.capacity_vip, s.capacity_platinum, s.capacity_gold
          ORDER BY s.name'
    )->fetchAll();
}
