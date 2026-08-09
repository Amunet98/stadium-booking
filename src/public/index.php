<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/booking.php';

$pdo = db();

// ORDER BY match_date: the original had no date column at all, so fixtures came
// back in insertion order.
$matches = $pdo->query(
    'SELECT m.mid, m.title, m.description, m.match_date, m.kickoff_time, m.ptw,
            m.price_vip, m.price_platinum, m.price_gold,
            s.name AS venue_name,
            home.name AS home_team, away.name AS away_team
       FROM matches m
       JOIN stadium s    ON s.sid = m.venue
       JOIN teams  home  ON home.tid = m.hometeam
       JOIN teams  away  ON away.tid = m.awayteam
      ORDER BY m.match_date, m.kickoff_time'
)->fetchAll();

$pageTitle = 'Fixtures';
require __DIR__ . '/../views/header.php';
?>

<div class="page-hero mb-4">
    <h1 class="h3 mb-1">Upcoming fixtures</h1>
    <p class="text-muted mb-0">Book a seat for any match below.</p>
</div>

<?php if (!$matches): ?>
    <div class="alert alert-info">No fixtures scheduled.</div>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($matches as $m):
        $remaining = seats_remaining_all($pdo, (int) $m['mid']);
        $totalLeft = array_sum($remaining);
        // Per-match image, falling back to a placeholder. The original hardcoded
        // one team's image onto every card.
        $slug  = preg_replace('/[^a-z0-9]+/', '-', strtolower($m['home_team'] . '-vs-' . $m['away_team']));
        $image = file_exists(__DIR__ . "/assets/img/{$slug}.png")
            ? url("assets/img/{$slug}.png")
            : url('assets/img/placeholder.svg');
    ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <img src="<?= e($image) ?>" class="card-img-top match-thumb"
                     alt="<?= e($m['home_team'] . ' versus ' . $m['away_team']) ?>">
                <div class="card-body d-flex flex-column">
                    <h2 class="h5 card-title"><?= e($m['title']) ?></h2>
                    <p class="text-muted small mb-2">
                        <?= e(date('D j M Y', strtotime($m['match_date']))) ?>
                        &middot;
                        <?= e(date('H:i', strtotime($m['kickoff_time']))) ?>
                        &middot;
                        <?= e($m['venue_name']) ?>
                    </p>
                    <?php if ($m['ptw']): ?>
                        <p class="small mb-2"><strong>Players to watch:</strong> <?= e($m['ptw']) ?></p>
                    <?php endif; ?>
                    <ul class="list-unstyled small mb-3">
                        <?php foreach (SEAT_TIERS as $tier): ?>
                            <li class="d-flex justify-content-between">
                                <span><?= e(tier_label($tier)) ?> — &pound;<?= e(money($m['price_' . $tier])) ?></span>
                                <span class="<?= $remaining[$tier] === 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= $remaining[$tier] === 0 ? 'Sold out' : e((string) $remaining[$tier]) . ' left' ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a class="btn btn-primary mt-auto <?= $totalLeft === 0 ? 'disabled' : '' ?>"
                       href="<?= url('booking.php?match=' . (int) $m['mid']) ?>">
                        <?= $totalLeft === 0 ? 'Sold out' : 'Book a ticket' ?>
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../views/footer.php'; ?>
