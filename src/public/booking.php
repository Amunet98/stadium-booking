<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/booking.php';

require_login();

$pdo     = db();
$viewer  = current_user();
$matchId = (int) ($_GET['match'] ?? $_POST['mid'] ?? 0);
$error   = null;

$stmt = $pdo->prepare(
    'SELECT m.*, s.name AS venue_name, s.photo AS venue_photo, s.description AS venue_description,
            s.capacity_vip, s.capacity_platinum, s.capacity_gold,
            home.name AS home_team, home.photo AS home_photo, home.manager AS home_manager,
            away.name AS away_team, away.photo AS away_photo, away.manager AS away_manager
       FROM matches m
       JOIN stadium s   ON s.sid = m.venue
       JOIN teams  home ON home.tid = m.hometeam
       JOIN teams  away ON away.tid = m.awayteam
      WHERE m.mid = ?'
);
$stmt->execute([$matchId]);
$match = $stmt->fetch();

if (!$match) {
    http_response_code(404);
    $pageTitle = 'Match not found';
    require __DIR__ . '/../views/header.php';
    ?>
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="empty-state">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>
                </svg>
                <h1 class="h4 mb-2">That fixture does not exist</h1>
                <p>It may have been removed from the schedule since you last looked.</p>
                <a class="btn btn-primary" href="<?= url('fixtures.php') ?>">Back to fixtures</a>
            </div>
        </div>
    </div>
    <?php
    require __DIR__ . '/../views/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $tier   = (string) ($_POST['seat_tier'] ?? '');
    $result = create_booking($pdo, (int) $viewer['uid'], $matchId, $tier);

    if ($result->ok) {
        $_SESSION['notice'] = $result->message;
        redirect('myticket.php');
    }
    $error = $result->message;
}

$remaining = seats_remaining_all($pdo, $matchId);
$kickoff   = new DateTimeImmutable($match['match_date'] . ' ' . $match['kickoff_time']);

$pageTitle  = $match['title'];
$navCurrent = 'fixtures';
require __DIR__ . '/../views/header.php';
?>

<nav aria-label="Breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="<?= url('index.php') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= url('fixtures.php') ?>">Fixtures</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($match['title']) ?></li>
    </ol>
</nav>

<div class="row g-4">
    <!-- ------------------------------------------------------- the fixture -->
    <div class="col-lg-5">
        <div class="card overflow-hidden">
            <?php
            $m = $match;
            $m['home_photo'] = $match['home_photo'];
            require __DIR__ . '/../views/partials/fixture-art.php';
            ?>
            <div class="card-body">
                <h1 class="h5 mb-3"><?= e($match['title']) ?></h1>

                <dl class="row small mb-0">
                    <dt class="col-4 text-muted fw-normal">Kick-off</dt>
                    <dd class="col-8">
                        <time datetime="<?= e($kickoff->format(DateTimeInterface::ATOM)) ?>">
                            <?= e($kickoff->format('l j F Y')) ?><br><?= e($kickoff->format('H:i')) ?>
                        </time>
                    </dd>

                    <dt class="col-4 text-muted fw-normal">Ground</dt>
                    <dd class="col-8"><?= e($match['venue_name']) ?></dd>

                    <dt class="col-4 text-muted fw-normal">Home</dt>
                    <dd class="col-8">
                        <?= e($match['home_team']) ?>
                        <?php if ($match['home_manager']): ?>
                            <span class="text-muted d-block"><?= e($match['home_manager']) ?></span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-4 text-muted fw-normal">Away</dt>
                    <dd class="col-8">
                        <?= e($match['away_team']) ?>
                        <?php if ($match['away_manager']): ?>
                            <span class="text-muted d-block"><?= e($match['away_manager']) ?></span>
                        <?php endif; ?>
                    </dd>

                    <?php if ($match['ptw']): ?>
                        <dt class="col-4 text-muted fw-normal">Watch for</dt>
                        <dd class="col-8 mb-0"><?= e($match['ptw']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>

    <!-- ---------------------------------------------------------- the form -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h2 class="h6 mb-0">Choose a seat</h2>
            </div>
            <div class="card-body">

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>
                        </svg>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= url('booking.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="mid" value="<?= (int) $matchId ?>">

                    <fieldset class="mb-4">
                        <legend class="visually-hidden">Seat tier</legend>
                        <?php /*
                            The availability span below keeps Bootstrap's
                            .text-success / .text-danger class names, and the
                            wording "N remaining" / "Sold out", on purpose:
                            tests/verify.sh scrapes exactly this to read what
                            the page reports before and after a booking. It is
                            styled as a pill in style.css rather than renamed —
                            a test that reads the real page is worth more than
                            a tidier class attribute.
                        */ ?>
                        <?php foreach (SEAT_TIERS as $tier):
                            $left     = $remaining[$tier];
                            $soldOut  = $left === 0;
                            $capacity = (int) $match['capacity_' . $tier];
                        ?>
                            <div class="form-check tier-option <?= $soldOut ? 'text-muted' : '' ?>">
                                <input class="form-check-input" type="radio" name="seat_tier"
                                       id="tier-<?= e($tier) ?>" value="<?= e($tier) ?>"
                                       <?= $soldOut ? 'disabled' : '' ?>
                                       <?= (($_POST['seat_tier'] ?? '') === $tier) ? 'checked' : '' ?> required>
                                <label class="form-check-label d-flex justify-content-between align-items-center gap-3"
                                       for="tier-<?= e($tier) ?>">
                                    <span>
                                        <span class="tier-name"><?= e(tier_label($tier)) ?></span>
                                        <span class="tier-price d-block">&pound;<?= e(money($match['price_' . $tier])) ?></span>
                                        <span class="small text-muted d-block">
                                            <?= number_format($capacity) ?> released for this fixture
                                        </span>
                                    </span>
                                    <span class="<?= $soldOut ? 'text-danger' : 'text-success' ?>">
                                        <?= $soldOut ? 'Sold out' : e((string) $left) . ' remaining' ?>
                                    </span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>

                    <p class="small text-muted">
                        Booking as <strong><?= e($viewer['name'] ?: $viewer['email']) ?></strong>.
                        One ticket per tier, per fixture, per account.
                        No payment is taken — this demo records what a ticket cost, nothing more.
                    </p>

                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" type="submit"
                                <?= array_sum($remaining) === 0 ? 'disabled' : '' ?>>
                            Confirm booking
                        </button>
                        <a class="btn btn-ghost" href="<?= url('fixtures.php') ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../views/footer.php'; ?>
