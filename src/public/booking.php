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
    'SELECT m.*, s.name AS venue_name,
            home.name AS home_team, away.name AS away_team
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
    echo '<div class="alert alert-warning">That match does not exist.</div>';
    echo '<a class="btn btn-primary" href="' . url('index.php') . '">Back to fixtures</a>';
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
$pageTitle = $match['title'];
require __DIR__ . '/../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h1 class="h5 mb-0"><?= e($match['title']) ?></h1>
            </div>
            <div class="card-body">

                <dl class="row small mb-4">
                    <dt class="col-sm-3">Kick-off</dt>
                    <dd class="col-sm-9">
                        <?= e(date('l j F Y', strtotime($match['match_date']))) ?>
                        at <?= e(date('H:i', strtotime($match['kickoff_time']))) ?>
                    </dd>
                    <dt class="col-sm-3">Venue</dt>
                    <dd class="col-sm-9"><?= e($match['venue_name']) ?></dd>
                    <dt class="col-sm-3">Teams</dt>
                    <dd class="col-sm-9"><?= e($match['home_team']) ?> vs <?= e($match['away_team']) ?></dd>
                    <?php if ($match['ptw']): ?>
                        <dt class="col-sm-3">Players to watch</dt>
                        <dd class="col-sm-9"><?= e($match['ptw']) ?></dd>
                    <?php endif; ?>
                </dl>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= url('booking.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="mid" value="<?= (int) $matchId ?>">

                    <fieldset class="mb-3">
                        <legend class="h6">Choose a seat</legend>
                        <?php foreach (SEAT_TIERS as $tier):
                            $left    = $remaining[$tier];
                            $soldOut = $left === 0;
                        ?>
                            <div class="form-check tier-option <?= $soldOut ? 'text-muted' : '' ?>">
                                <input class="form-check-input" type="radio" name="seat_tier"
                                       id="tier-<?= e($tier) ?>" value="<?= e($tier) ?>"
                                       <?= $soldOut ? 'disabled' : '' ?>
                                       <?= (($_POST['seat_tier'] ?? '') === $tier) ? 'checked' : '' ?> required>
                                <label class="form-check-label d-flex justify-content-between"
                                       for="tier-<?= e($tier) ?>">
                                    <span>
                                        <strong><?= e(tier_label($tier)) ?></strong>
                                        — &pound;<?= e(money($match['price_' . $tier])) ?>
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
                    </p>

                    <button class="btn btn-primary" type="submit"
                            <?= array_sum($remaining) === 0 ? 'disabled' : '' ?>>
                        Confirm booking
                    </button>
                    <a class="btn btn-link" href="<?= url('index.php') ?>">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../views/footer.php'; ?>
