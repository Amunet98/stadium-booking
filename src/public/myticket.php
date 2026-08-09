<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

// The original read $_SESSION["user"] with no guard at all and interpolated it
// straight into the query, so a logged-out visit produced a SQL error that
// printed the query back to the page.
require_login();

$viewer = current_user();
$pdo    = db();

$stmt = $pdo->prepare(
    'SELECT b.bid, b.seat_tier, b.paid_amount, b.created_at,
            m.title, m.match_date, m.kickoff_time,
            s.name AS venue_name
       FROM bookings b
       JOIN matches m ON m.mid = b.mid
       JOIN stadium s ON s.sid = m.venue
      WHERE b.uid = ?
      ORDER BY m.match_date, m.kickoff_time'
);
$stmt->execute([$viewer['uid']]);
$tickets = $stmt->fetchAll();

$notice = $_SESSION['notice'] ?? null;
unset($_SESSION['notice']);

$pageTitle = 'My tickets';
require __DIR__ . '/../views/header.php';
?>

<h1 class="h4 mb-3">My tickets</h1>

<?php if ($notice): ?>
    <div class="alert alert-success"><?= e($notice) ?></div>
<?php endif; ?>

<?php if (!$tickets): ?>
    <div class="alert alert-info">
        You have no tickets yet. <a href="<?= url('index.php') ?>">Browse fixtures</a>.
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th scope="col">Ref</th>
                    <th scope="col">Match</th>
                    <th scope="col">Venue</th>
                    <th scope="col">Kick-off</th>
                    <th scope="col">Seat</th>
                    <th scope="col" class="text-end">Paid</th>
                    <th scope="col">Booked</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td class="text-muted">#<?= (int) $t['bid'] ?></td>
                        <td><?= e($t['title']) ?></td>
                        <td><?= e($t['venue_name']) ?></td>
                        <td>
                            <?= e(date('j M Y', strtotime($t['match_date']))) ?>
                            <?= e(date('H:i', strtotime($t['kickoff_time']))) ?>
                        </td>
                        <td><span class="badge bg-secondary"><?= e(tier_label($t['seat_tier'])) ?></span></td>
                        <td class="text-end">&pound;<?= e(money($t['paid_amount'])) ?></td>
                        <td class="text-muted small"><?= e(date('j M Y H:i', strtotime($t['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../views/footer.php'; ?>
