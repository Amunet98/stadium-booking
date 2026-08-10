<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/fixtures.php';

$pdo = db();

$fixtures = fetch_fixtures($pdo);
$venues   = fetch_venues($pdo);

// The spotlight is the soonest fixture that has not kicked off. Fixtures are
// already ordered by kick-off, so this is the first one still in the future —
// and if the demo data has aged past every date, the first one overall, which
// is better than an empty hero.
$now  = new DateTimeImmutable('now');
$next = null;
foreach ($fixtures as $fixture) {
    $kickoff = new DateTimeImmutable($fixture['match_date'] . ' ' . $fixture['kickoff_time']);
    if ($kickoff > $now) {
        $next = $fixture;
        break;
    }
}
$next ??= $fixtures[0] ?? null;

// Featured: the three soonest that still have seats.
$featured = array_slice(array_values(array_filter(
    $fixtures,
    static fn(array $f): bool => array_sum($f['remaining']) > 0
)), 0, 3);

$seatsAvailable = array_sum(array_map(
    static fn(array $f): int => array_sum($f['remaining']),
    $fixtures
));

$pageTitle  = 'Matchday tickets';
$navCurrent = 'index';
$fullWidth  = true;
require __DIR__ . '/../views/header.php';
?>

<!-- ------------------------------------------------------------------ hero -->
<section class="hero">
    <div class="container hero-inner">
        <div class="row">
            <div class="col-lg-9">
                <p class="app-eyebrow mb-3">Premier League &middot; 2026&ndash;27</p>
                <h1>Every seat accounted for, even when everyone books at once.</h1>
                <p class="hero-lead">
                    Pick a fixture, choose a tier, and the seat is held under a database row
                    lock before the page comes back. This is a 2021 university project,
                    rebuilt in 2026 — the original would sell the same seat to as many
                    people as asked for it.
                </p>
                <div class="hero-actions">
                    <a class="btn btn-primary btn-lg" href="<?= url('fixtures.php') ?>">
                        Browse <?= count($fixtures) ?> fixtures
                    </a>
                    <a class="btn btn-ghost btn-lg"
                       href="https://github.com/Amunet98/stadium-booking/blob/main/docs/SECURITY-FINDINGS.md"
                       rel="noopener">
                        Read what was broken
                    </a>
                </div>

                <div class="hero-proof">
                    <div>
                        <span class="stat-value"><?= number_format($seatsAvailable) ?></span>
                        <span class="stat-label">seats available right now</span>
                    </div>
                    <div>
                        <span class="stat-value"><?= count($venues) ?></span>
                        <span class="stat-label">grounds</span>
                    </div>
                    <div>
                        <span class="stat-value">24 &rarr; 4</span>
                        <span class="stat-label">workers racing for 4 seats, 4 sold</span>
                    </div>
                    <div>
                        <span class="stat-value">20</span>
                        <span class="stat-label">defects found and fixed</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($next):
    $m         = $next;
    $kickoff   = new DateTimeImmutable($m['match_date'] . ' ' . $m['kickoff_time']);
    $artClass  = 'spotlight-art';
    $remaining = $m['remaining'];
?>
<!-- ------------------------------------------------------------- spotlight -->
<section class="app-section">
    <div class="container">
        <div class="app-section-head" data-reveal>
            <p class="app-eyebrow">Next up</p>
            <h2 class="h3">The nearest kick-off</h2>
        </div>

        <div class="spotlight" data-reveal>
            <?php require __DIR__ . '/../views/partials/fixture-art.php'; ?>

            <div class="spotlight-body">
                <h3 class="h4 mb-2"><?= e($m['title']) ?></h3>
                <p class="text-muted mb-0">
                    <?= e($m['venue_name']) ?> &middot;
                    <time datetime="<?= e($kickoff->format(DateTimeInterface::ATOM)) ?>">
                        <?= e($kickoff->format('l j F Y')) ?> at <?= e($kickoff->format('H:i')) ?>
                    </time>
                </p>

                <?php /* The countdown is progressive enhancement over the <time>
                         above: without scripting the date is still stated in full,
                         and these cells simply read zero. */ ?>
                <div class="countdown" data-countdown="<?= e($kickoff->format(DateTimeInterface::ATOM)) ?>"
                     role="group" aria-label="Time until kick-off">
                    <div><b data-cd="days">0</b><span>Days</span></div>
                    <div><b data-cd="hours">00</b><span>Hours</span></div>
                    <div><b data-cd="minutes">00</b><span>Mins</span></div>
                    <div><b data-cd="seconds">00</b><span>Secs</span></div>
                </div>

                <ul class="fixture-tiers mb-4">
                    <?php foreach (SEAT_TIERS as $tier):
                        [$state, $label] = seat_status($remaining[$tier], $m['capacity'][$tier]);
                    ?>
                        <li>
                            <span>
                                <span class="fixture-tier-name"><?= e(tier_label($tier)) ?></span>
                                <span class="fixture-tier-price">&pound;<?= e(money($m['price_' . $tier])) ?></span>
                            </span>
                            <span class="seat-pill seat-pill-<?= e($state) ?>"><?= e($label) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($m['ptw']): ?>
                    <p class="small text-muted mb-3">
                        <strong>Players to watch:</strong> <?= e($m['ptw']) ?>
                    </p>
                <?php endif; ?>

                <a class="btn btn-primary" href="<?= url('booking.php?match=' . (int) $m['mid']) ?>">
                    Book this fixture
                </a>
            </div>
        </div>
    </div>
</section>
<?php unset($artClass, $remaining, $m); endif; ?>

<!-- ---------------------------------------------------------- how it works -->
<section class="app-section app-section-alt">
    <div class="container">
        <div class="app-section-head" data-reveal>
            <p class="app-eyebrow">How it works</p>
            <h2 class="h3">Three steps, and one of them is the interesting one</h2>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="step" data-reveal>
                    <span class="step-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                            <path d="M16 2v4M8 2v4M3 10h18"/>
                        </svg>
                    </span>
                    <span class="step-index">Step 01</span>
                    <h3>Pick a fixture</h3>
                    <p>
                        Six fixtures across six grounds, ordered by kick-off. Each card shows
                        what is actually left in each tier, counted for that match alone.
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step" data-reveal>
                    <span class="step-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 9V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 6v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-6z"/>
                            <path d="M13 5v14" stroke-dasharray="2 3"/>
                        </svg>
                    </span>
                    <span class="step-index">Step 02</span>
                    <h3>Choose a tier</h3>
                    <p>
                        VIP, Platinum or Gold. Sold-out tiers are disabled rather than hidden,
                        so the page tells you what happened instead of quietly changing.
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step" data-reveal>
                    <span class="step-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="11" rx="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </span>
                    <span class="step-index">Step 03</span>
                    <h3>The seat is locked, not checked</h3>
                    <p>
                        Counting free seats and then inserting still oversells: every
                        concurrent request reads the same count first. The match row is
                        locked for the duration instead, so the requests queue.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ------------------------------------------------------ featured fixtures -->
<?php if ($featured): ?>
<section class="app-section">
    <div class="container">
        <div class="app-section-head d-flex flex-wrap justify-content-between align-items-end gap-3" data-reveal>
            <div>
                <p class="app-eyebrow">On sale</p>
                <h2 class="h3">Fixtures with seats left</h2>
            </div>
            <a class="btn btn-ghost" href="<?= url('fixtures.php') ?>">See all fixtures</a>
        </div>

        <div class="row g-4">
            <?php foreach ($featured as $m):
                $remaining = $m['remaining'];
                $capacity  = $m['capacity'];
            ?>
                <div class="col-md-6 col-lg-4">
                    <?php require __DIR__ . '/../views/partials/fixture-card.php'; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ---------------------------------------------------------------- venues -->
<section class="app-section app-section-alt">
    <div class="container">
        <div class="app-section-head" data-reveal>
            <p class="app-eyebrow">Grounds</p>
            <h2 class="h3">Where the fixtures are played</h2>
            <p>
                Six real grounds. The seat allocation released to this demo is deliberately
                small — small enough to sell out a tier by hand and watch the limit hold.
            </p>
        </div>

        <div class="row g-3">
            <?php foreach ($venues as $v): ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <a class="venue-card h-100" data-reveal
                       href="<?= url('fixtures.php?venue=' . (int) $v['sid']) ?>">
                        <img src="<?= e(asset_image($v['photo'])) ?>" alt=""
                             width="400" height="225" loading="lazy" decoding="async">
                        <div class="venue-card-body">
                            <h3><?= e($v['name']) ?></h3>
                            <p>
                                <?= (int) $v['fixtures'] ?> <?= (int) $v['fixtures'] === 1 ? 'fixture' : 'fixtures' ?>
                                &middot; <?= number_format((int) $v['seats']) ?> seats
                            </p>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ------------------------------------------------------------------- cta -->
<section class="app-section-tight">
    <div class="container">
        <div class="cta-band text-center" data-reveal>
            <h2 class="h3 mb-2">Book a seat, or go and read the diff</h2>
            <p class="text-muted mb-4 mx-auto" style="max-width: 54ch;">
                The 2021 code is preserved unmodified alongside the rebuild, so the whole
                project reads as a before and after. Twenty findings, each with the original
                code, the failure it caused, and the fix.
            </p>
            <div class="d-flex flex-wrap gap-2 justify-content-center">
                <a class="btn btn-primary btn-lg" href="<?= url('fixtures.php') ?>">Browse fixtures</a>
                <a class="btn btn-ghost btn-lg" href="https://github.com/Amunet98/stadium-booking" rel="noopener">
                    View the source
                </a>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../views/footer.php'; ?>
