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
            m.mid, m.title, m.match_date, m.kickoff_time,
            s.name AS venue_name,
            home.name AS home_team, away.name AS away_team
       FROM bookings b
       JOIN matches m ON m.mid = b.mid
       JOIN stadium s ON s.sid = m.venue
       JOIN teams home ON home.tid = m.hometeam
       JOIN teams away ON away.tid = m.awayteam
      WHERE b.uid = ?
      ORDER BY m.match_date, m.kickoff_time'
);
$stmt->execute([$viewer['uid']]);
$tickets = $stmt->fetchAll();

$notice = $_SESSION['notice'] ?? null;
unset($_SESSION['notice']);

$totalPaid = array_sum(array_map(static fn(array $t): float => (float) $t['paid_amount'], $tickets));

$pageTitle  = 'My tickets';
$navCurrent = 'myticket';
require __DIR__ . '/../views/header.php';
?>

<div class="page-hero d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
        <h1 class="h3">My tickets</h1>
        <p>
            <?= count($tickets) ?> <?= count($tickets) === 1 ? 'ticket' : 'tickets' ?>
            <?php if ($tickets): ?>
                &middot; &pound;<?= e(money($totalPaid)) ?> recorded
            <?php endif; ?>
        </p>
    </div>
    <a class="btn btn-ghost" href="<?= url('fixtures.php') ?>">Book another</a>
</div>

<?php if ($notice): ?>
    <div class="alert alert-success" role="alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 6 9 17l-5-5"/>
        </svg>
        <span><?= e($notice) ?></span>
    </div>
<?php endif; ?>

<?php if (!$tickets): ?>
    <div class="empty-state">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 9V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 6v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-6z"/>
            <path d="M13 5v14" stroke-dasharray="2 3"/>
        </svg>
        <h2 class="h5 mb-2">No tickets yet</h2>
        <p>Once you book a seat it appears here, with the reference and what it cost.</p>
        <a class="btn btn-primary" href="<?= url('fixtures.php') ?>">Browse fixtures</a>
    </div>
<?php else: ?>
    <div class="d-grid gap-3">
        <?php foreach ($tickets as $t):
            $kickoff = new DateTimeImmutable($t['match_date'] . ' ' . $t['kickoff_time']);
        ?>
            <article class="ticket" data-reveal>
                <div>
                    <h2 class="h6">
                        <a class="text-decoration-none" style="color: inherit"
                           href="<?= url('booking.php?match=' . (int) $t['mid']) ?>"><?= e($t['title']) ?></a>
                    </h2>
                    <div class="fixture-meta mb-2">
                        <span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                            </svg>
                            <time datetime="<?= e($kickoff->format(DateTimeInterface::ATOM)) ?>">
                                <?= e($kickoff->format('D j M Y, H:i')) ?>
                            </time>
                        </span>
                        <span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>
                            </svg>
                            <?= e($t['venue_name']) ?>
                        </span>
                    </div>
                    <p class="small text-muted mb-0">
                        Booked <?= e(date('j M Y \a\t H:i', strtotime((string) $t['created_at']))) ?>
                    </p>
                </div>

                <div class="ticket-stub">
                    <div>
                        <span class="ticket-ref">#<?= (int) $t['bid'] ?></span>
                        <span class="ticket-tier d-block"><?= e(tier_label($t['seat_tier'])) ?></span>
                        <span class="ticket-paid d-block">&pound;<?= e(money($t['paid_amount'])) ?></span>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../views/footer.php'; ?>
