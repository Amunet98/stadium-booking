<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';

/**
 * Admin CRUD.
 *
 * Every function here assumes require_admin() has already run in admin.php.
 * All writes go through prepared statements; the original built INSERTs by
 * concatenation after passing values through a hand-rolled sanitize().
 */

/**
 * Row counts for the dashboard tiles.
 *
 * Four COUNT(*) queries rather than one UNION: each is an index-only count on
 * a small table, and keeping them separate means adding a tile does not mean
 * re-reading a hand-assembled result set.
 */
function admin_counts(PDO $pdo): array
{
    $counts = [];
    foreach (['matches', 'teams', 'stadium', 'bookings'] as $table) {
        // The table names are literals from this list, never user input — the
        // one place in the codebase where a table name is interpolated.
        $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }
    $counts['stadiums'] = $counts['stadium'];

    return $counts;
}

function admin_handle_post(string $page): array
{
    return match ($page) {
        'stadiums' => admin_create_stadium(),
        'teams'    => admin_create_team(),
        'matches'  => admin_create_match(),
        default    => ['ok' => false, 'message' => 'Nothing to do.'],
    };
}

function admin_render(string $page, string $action): void
{
    match ($page) {
        'matches'  => $action === 'add' ? admin_match_form()   : admin_list_matches(),
        'teams'    => $action === 'add' ? admin_team_form()    : admin_list_teams(),
        'stadiums' => $action === 'add' ? admin_stadium_form() : admin_list_stadiums(),
        'bookings' => admin_list_bookings(),
        default    => print('<p>Unknown page.</p>'),
    };
}

// ---------------------------------------------------------------------------
// Stadiums
// ---------------------------------------------------------------------------

function admin_create_stadium(): array
{
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        return ['ok' => false, 'message' => 'Stadium name is required.'];
    }

    try {
        // Each capacity is bound to the column that matches its label. The
        // original posted vip/gold/platinum and wrote them into (a, b, c) while
        // the listing rendered b as Platinum and c as Gold, so the two tiers
        // were transposed on every stadium ever added through this form.
        $stmt = db()->prepare(
            'INSERT INTO stadium (name, capacity_vip, capacity_platinum, capacity_gold, description)
             VALUES (:name, :vip, :platinum, :gold, :description)'
        );
        $stmt->execute([
            'name'        => $name,
            'vip'         => max(0, (int) ($_POST['capacity_vip'] ?? 0)),
            'platinum'    => max(0, (int) ($_POST['capacity_platinum'] ?? 0)),
            'gold'        => max(0, (int) ($_POST['capacity_gold'] ?? 0)),
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
        ]);
        return ['ok' => true, 'message' => 'Stadium added.'];
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return ['ok' => false, 'message' => 'A stadium with that name already exists.'];
        }
        throw $e;
    }
}

function admin_list_stadiums(): void
{
    $rows = db()->query(
        'SELECT sid, name, capacity_vip, capacity_platinum, capacity_gold, description
           FROM stadium ORDER BY name'
    )->fetchAll();
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Stadiums</h2>
        <a class="btn btn-primary btn-sm" href="<?= url('admin.php?page=stadiums&action=add') ?>">Add stadium</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col" class="text-end">VIP</th>
                    <th scope="col" class="text-end">Platinum</th>
                    <th scope="col" class="text-end">Gold</th>
                    <th scope="col">Description</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['name']) ?></td>
                    <td class="text-end"><?= (int) $r['capacity_vip'] ?></td>
                    <td class="text-end"><?= (int) $r['capacity_platinum'] ?></td>
                    <td class="text-end"><?= (int) $r['capacity_gold'] ?></td>
                    <td class="small text-muted"><?= e($r['description']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="5" class="py-4 text-center">
                        <p class="text-muted mb-2">No grounds yet.</p>
                            <a class="btn btn-primary btn-sm" href="<?= url('admin.php?page=stadiums&action=add') ?>">Add the first ground</a>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function admin_stadium_form(): void
{
    ?>
    <h2 class="h5 mb-3">Add stadium</h2>
    <form method="post" action="<?= url('admin.php?page=stadiums') ?>" class="mt-1">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="name">Name</label>
            <input class="form-control" id="name" name="name" required>
        </div>
        <div class="row">
            <?php foreach (SEAT_TIERS as $tier): ?>
                <div class="col-sm-4 mb-3">
                    <label class="form-label" for="cap-<?= e($tier) ?>">
                        <?= e(tier_label($tier)) ?> capacity
                    </label>
                    <input class="form-control" type="number" min="0" value="0"
                           id="cap-<?= e($tier) ?>" name="capacity_<?= e($tier) ?>" required>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mb-3">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
        </div>
        <div>
            <button class="btn btn-primary" type="submit">Add stadium</button>
            <a class="btn btn-ghost" href="<?= url('admin.php?page=stadiums') ?>">Cancel</a>
        </div>
    </form>
    <?php
}

// ---------------------------------------------------------------------------
// Teams
// ---------------------------------------------------------------------------

function admin_create_team(): array
{
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        return ['ok' => false, 'message' => 'Team name is required.'];
    }

    $stmt = db()->prepare(
        'INSERT INTO teams (name, manager, sid, details) VALUES (:name, :manager, :sid, :details)'
    );
    $stmt->execute([
        'name'    => $name,
        'manager' => trim((string) ($_POST['manager'] ?? '')) ?: null,
        'sid'     => ($_POST['sid'] ?? '') !== '' ? (int) $_POST['sid'] : null,
        'details' => trim((string) ($_POST['details'] ?? '')) ?: null,
    ]);
    return ['ok' => true, 'message' => 'Team added.'];
}

function admin_list_teams(): void
{
    // LEFT JOIN, not INNER: the original's inner join silently hid every team
    // whose stadium had been deleted or never set.
    $rows = db()->query(
        'SELECT t.tid, t.name, t.manager, t.details, s.name AS venue_name
           FROM teams t
           LEFT JOIN stadium s ON s.sid = t.sid
          ORDER BY t.name'
    )->fetchAll();
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Teams</h2>
        <a class="btn btn-primary btn-sm" href="<?= url('admin.php?page=teams&action=add') ?>">Add team</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th scope="col">Team</th>
                    <th scope="col">Manager</th>
                    <th scope="col">Home ground</th>
                    <th scope="col">Details</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['name']) ?></td>
                    <td><?= e($r['manager']) ?></td>
                    <td><?= $r['venue_name'] ? e($r['venue_name']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="small text-muted"><?= e($r['details']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="4" class="py-4 text-center">
                        <p class="text-muted mb-2">No clubs yet.</p>
                            <a class="btn btn-primary btn-sm" href="<?= url('admin.php?page=teams&action=add') ?>">Add the first club</a>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function admin_team_form(): void
{
    $stadiums = db()->query('SELECT sid, name FROM stadium ORDER BY name')->fetchAll();
    ?>
    <h2 class="h5 mb-3">Add team</h2>
    <form method="post" action="<?= url('admin.php?page=teams') ?>" class="mt-1">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="name">Team name</label>
            <input class="form-control" id="name" name="name" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="manager">Manager</label>
            <input class="form-control" id="manager" name="manager">
        </div>
        <div class="mb-3">
            <label class="form-label" for="sid">Home ground</label>
            <select class="form-select" id="sid" name="sid">
                <option value="">— none —</option>
                <?php foreach ($stadiums as $s): ?>
                    <option value="<?= (int) $s['sid'] ?>"><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="details">Details</label>
            <textarea class="form-control" id="details" name="details" rows="3"></textarea>
        </div>
        <div>
            <button class="btn btn-primary" type="submit">Add team</button>
            <a class="btn btn-ghost" href="<?= url('admin.php?page=teams') ?>">Cancel</a>
        </div>
    </form>
    <?php
}

// ---------------------------------------------------------------------------
// Matches
// ---------------------------------------------------------------------------

function admin_create_match(): array
{
    $title    = trim((string) ($_POST['title'] ?? ''));
    $home     = (int) ($_POST['hometeam'] ?? 0);
    $away     = (int) ($_POST['awayteam'] ?? 0);
    $venue    = (int) ($_POST['venue'] ?? 0);
    $date     = trim((string) ($_POST['match_date'] ?? ''));
    $kickoff  = trim((string) ($_POST['kickoff_time'] ?? ''));

    if ($title === '' || !$home || !$away || !$venue || $date === '' || $kickoff === '') {
        return ['ok' => false, 'message' => 'Title, teams, venue, date and kick-off time are all required.'];
    }
    if ($home === $away) {
        return ['ok' => false, 'message' => 'A team cannot play itself.'];
    }

    try {
        // Prices are captured here. The original's add-match form had no price
        // fields at all, so every match created through the admin UI had NULL
        // prices and every booking against it recorded a NULL amount paid.
        $stmt = db()->prepare(
            'INSERT INTO matches
                (title, description, venue, match_date, kickoff_time, ptw,
                 price_vip, price_platinum, price_gold, hometeam, awayteam)
             VALUES
                (:title, :description, :venue, :match_date, :kickoff_time, :ptw,
                 :price_vip, :price_platinum, :price_gold, :hometeam, :awayteam)'
        );
        $stmt->execute([
            'title'          => $title,
            'description'    => trim((string) ($_POST['description'] ?? '')) ?: null,
            'venue'          => $venue,
            'match_date'     => $date,
            'kickoff_time'   => $kickoff,
            'ptw'            => trim((string) ($_POST['ptw'] ?? '')) ?: null,
            'price_vip'      => max(0, (float) ($_POST['price_vip'] ?? 0)),
            'price_platinum' => max(0, (float) ($_POST['price_platinum'] ?? 0)),
            'price_gold'     => max(0, (float) ($_POST['price_gold'] ?? 0)),
            'hometeam'       => $home,
            'awayteam'       => $away,
        ]);
        return ['ok' => true, 'message' => 'Match added.'];
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return ['ok' => false, 'message' => 'That team or venue does not exist.'];
        }
        throw $e;
    }
}

function admin_list_matches(): void
{
    $rows = db()->query(
        'SELECT m.mid, m.title, m.match_date, m.kickoff_time, m.ptw,
                m.price_vip, m.price_platinum, m.price_gold,
                s.name AS venue_name, home.name AS home_team, away.name AS away_team,
                (SELECT COUNT(*) FROM bookings b WHERE b.mid = m.mid) AS booked
           FROM matches m
           JOIN stadium s   ON s.sid = m.venue
           JOIN teams  home ON home.tid = m.hometeam
           JOIN teams  away ON away.tid = m.awayteam
          ORDER BY m.match_date, m.kickoff_time'
    )->fetchAll();
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Matches</h2>
        <a class="btn btn-primary btn-sm" href="<?= url('admin.php?page=matches&action=add') ?>">Add match</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th scope="col">Match</th>
                    <th scope="col">Kick-off</th>
                    <th scope="col">Venue</th>
                    <th scope="col" class="text-end">VIP</th>
                    <th scope="col" class="text-end">Plat.</th>
                    <th scope="col" class="text-end">Gold</th>
                    <th scope="col" class="text-end">Booked</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <?= e($r['title']) ?><br>
                        <span class="small text-muted"><?= e($r['home_team']) ?> vs <?= e($r['away_team']) ?></span>
                    </td>
                    <td class="small">
                        <?= e(date('j M Y', strtotime($r['match_date']))) ?><br>
                        <?= e(date('H:i', strtotime($r['kickoff_time']))) ?>
                    </td>
                    <td class="small"><?= e($r['venue_name']) ?></td>
                    <td class="text-end">&pound;<?= e(money($r['price_vip'])) ?></td>
                    <td class="text-end">&pound;<?= e(money($r['price_platinum'])) ?></td>
                    <td class="text-end">&pound;<?= e(money($r['price_gold'])) ?></td>
                    <td class="text-end"><?= (int) $r['booked'] ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="7" class="py-4 text-center">
                        <p class="text-muted mb-2">No fixtures yet.</p>
                            <a class="btn btn-primary btn-sm" href="<?= url('admin.php?page=matches&action=add') ?>">Add the first fixture</a>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function admin_match_form(): void
{
    $pdo      = db();
    $stadiums = $pdo->query('SELECT sid, name FROM stadium ORDER BY name')->fetchAll();
    $teams    = $pdo->query('SELECT tid, name FROM teams ORDER BY name')->fetchAll();
    ?>
    <h2 class="h5 mb-3">Add match</h2>

    <?php if (!$stadiums || !$teams): ?>
        <div class="alert alert-warning">
            Add at least one stadium and two teams first.
        </div>
    <?php endif; ?>

    <form method="post" action="<?= url('admin.php?page=matches') ?>" class="mt-1">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="title">Title</label>
            <input class="form-control" id="title" name="title" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
        </div>
        <div class="row">
            <div class="col-sm-6 mb-3">
                <label class="form-label" for="hometeam">Home team</label>
                <select class="form-select" id="hometeam" name="hometeam" required>
                    <option value="">— select —</option>
                    <?php foreach ($teams as $t): ?>
                        <option value="<?= (int) $t['tid'] ?>"><?= e($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 mb-3">
                <label class="form-label" for="awayteam">Away team</label>
                <select class="form-select" id="awayteam" name="awayteam" required>
                    <option value="">— select —</option>
                    <?php foreach ($teams as $t): ?>
                        <option value="<?= (int) $t['tid'] ?>"><?= e($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-4 mb-3">
                <label class="form-label" for="venue">Venue</label>
                <select class="form-select" id="venue" name="venue" required>
                    <option value="">— select —</option>
                    <?php foreach ($stadiums as $s): ?>
                        <option value="<?= (int) $s['sid'] ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4 mb-3">
                <label class="form-label" for="match_date">Date</label>
                <input class="form-control" type="date" id="match_date" name="match_date" required>
            </div>
            <div class="col-sm-4 mb-3">
                <label class="form-label" for="kickoff_time">Kick-off</label>
                <input class="form-control" type="time" id="kickoff_time" name="kickoff_time" required>
            </div>
        </div>
        <div class="row">
            <?php foreach (SEAT_TIERS as $tier): ?>
                <div class="col-sm-4 mb-3">
                    <label class="form-label" for="price-<?= e($tier) ?>">
                        <?= e(tier_label($tier)) ?> price
                    </label>
                    <input class="form-control" type="number" min="0" step="0.01" value="0.00"
                           id="price-<?= e($tier) ?>" name="price_<?= e($tier) ?>" required>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mb-3">
            <label class="form-label" for="ptw">Players to watch</label>
            <input class="form-control" id="ptw" name="ptw">
        </div>
        <div>
            <button class="btn btn-primary" type="submit">Add match</button>
            <a class="btn btn-ghost" href="<?= url('admin.php?page=matches') ?>">Cancel</a>
        </div>
    </form>
    <?php
}

// ---------------------------------------------------------------------------
// Bookings
// ---------------------------------------------------------------------------

/**
 * Bookings listing.
 *
 * Read-only by design. The original had an "ADD BOOKING" button wired to an
 * empty addBookings() function, so it rendered a blank page. An admin creating
 * a booking on someone else's behalf needs a customer to attribute it to and a
 * payment story to go with it; neither exists here, so the button is gone
 * rather than stubbed.
 */
function admin_list_bookings(): void
{
    $rows = db()->query(
        'SELECT b.bid, b.seat_tier, b.paid_amount, b.created_at,
                u.name AS user_name, u.email,
                m.title, m.match_date
           FROM bookings b
           JOIN users   u ON u.uid = b.uid
           JOIN matches m ON m.mid = b.mid
          ORDER BY b.created_at DESC'
    )->fetchAll();

    $revenue = array_sum(array_column($rows, 'paid_amount'));
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Bookings</h2>
        <span class="text-muted small">
            <?= count($rows) ?> booking<?= count($rows) === 1 ? '' : 's' ?>
            &middot; &pound;<?= e(money($revenue)) ?> recorded
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th scope="col">Ref</th>
                    <th scope="col">Customer</th>
                    <th scope="col">Match</th>
                    <th scope="col">Seat</th>
                    <th scope="col" class="text-end">Paid</th>
                    <th scope="col">Booked</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="text-muted">#<?= (int) $r['bid'] ?></td>
                    <td>
                        <?= e($r['user_name'] ?: '—') ?><br>
                        <span class="small text-muted"><?= e($r['email']) ?></span>
                    </td>
                    <td class="small">
                        <?= e($r['title']) ?><br>
                        <span class="text-muted"><?= e(date('j M Y', strtotime($r['match_date']))) ?></span>
                    </td>
                    <td><span class="badge bg-secondary"><?= e(tier_label($r['seat_tier'])) ?></span></td>
                    <td class="text-end">&pound;<?= e(money($r['paid_amount'])) ?></td>
                    <td class="small text-muted"><?= e(date('j M Y H:i', strtotime($r['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="6" class="py-4 text-center">
                        <p class="text-muted mb-2">No bookings yet.</p>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
