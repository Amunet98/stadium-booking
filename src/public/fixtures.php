<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/fixtures.php';

$pdo    = db();
$venues = fetch_venues($pdo);

// Filters arrive as GET so a filtered listing is a shareable URL and the back
// button restores it. Every value is validated against something known rather
// than trusted: `tier` against the seat vocabulary, `venue` against the rows
// that actually exist.
$venueIds     = array_map(static fn(array $v): int => (int) $v['sid'], $venues);
$venueFilter  = (int) ($_GET['venue'] ?? 0);
$venueFilter  = in_array($venueFilter, $venueIds, true) ? $venueFilter : 0;

$tierFilter   = (string) ($_GET['tier'] ?? '');
$tierFilter   = is_valid_tier($tierFilter) ? $tierFilter : '';

$availableOnly = isset($_GET['available']);

$fixtures = fetch_fixtures($pdo, [
    'venue'     => $venueFilter ?: null,
    'tier'      => $tierFilter ?: null,
    'available' => $availableOnly,
]);

$isFiltered = $venueFilter || $tierFilter !== '' || $availableOnly;

$pageTitle  = 'Fixtures';
$navCurrent = 'fixtures';
require __DIR__ . '/../views/header.php';
?>

<div class="page-hero">
    <h1 class="h3">Fixtures</h1>
    <p>Six fixtures across six grounds. Availability is counted per match, per tier.</p>
</div>

<form class="filter-bar" method="get" action="<?= url('fixtures.php') ?>" data-autosubmit>
    <div class="row g-2 align-items-end">
        <div class="col-6 col-lg-4">
            <label class="form-label" for="venue">Ground</label>
            <select class="form-select" id="venue" name="venue">
                <option value="">All grounds</option>
                <?php foreach ($venues as $v): ?>
                    <option value="<?= (int) $v['sid'] ?>" <?= $venueFilter === (int) $v['sid'] ? 'selected' : '' ?>>
                        <?= e($v['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-lg-3">
            <label class="form-label" for="tier">Seat tier</label>
            <select class="form-select" id="tier" name="tier">
                <option value="">Any tier</option>
                <?php foreach (SEAT_TIERS as $tier): ?>
                    <option value="<?= e($tier) ?>" <?= $tierFilter === $tier ? 'selected' : '' ?>>
                        <?= e(tier_label($tier)) ?> available
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-lg-3">
            <div class="form-check mt-lg-4 mb-lg-2">
                <input class="form-check-input" type="checkbox" id="available" name="available"
                       value="1" <?= $availableOnly ? 'checked' : '' ?>>
                <label class="form-check-label" for="available">Hide sold-out fixtures</label>
            </div>
        </div>

        <div class="col-lg-2 d-flex gap-2">
            <?php /* Hidden by app.js once the change handlers are attached, and
                     the only way to apply a filter without them. */ ?>
            <button class="btn btn-primary flex-grow-1" type="submit" data-autosubmit-fallback>Apply</button>
            <?php if ($isFiltered): ?>
                <a class="btn btn-ghost" href="<?= url('fixtures.php') ?>">Clear</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<p class="text-muted small mb-3" aria-live="polite">
    <?= count($fixtures) ?> <?= count($fixtures) === 1 ? 'fixture' : 'fixtures' ?>
    <?= $isFiltered ? 'match these filters' : 'scheduled' ?>
</p>

<?php if (!$fixtures): ?>
    <div class="empty-state">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
        </svg>
        <h2 class="h5 mb-2">Nothing matches those filters</h2>
        <p>
            <?= $isFiltered
                ? 'Try a different ground, or allow any seat tier.'
                : 'No fixtures are scheduled. An administrator can add one from the admin panel.' ?>
        </p>
        <?php if ($isFiltered): ?>
            <a class="btn btn-primary" href="<?= url('fixtures.php') ?>">Clear filters</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php /* The cards head at h3 so they nest correctly under a section
             heading on the landing page. This listing has no such heading, so
             without this the document jumps h1 -> h3 — and a screen-reader
             user navigating by heading gets no landmark for the results. */ ?>
    <h2 class="visually-hidden">Fixture list</h2>
    <div class="row g-4">
        <?php foreach ($fixtures as $m):
            $remaining = $m['remaining'];
            $capacity  = $m['capacity'];
        ?>
            <div class="col-md-6 col-xl-4">
                <?php require __DIR__ . '/../views/partials/fixture-card.php'; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../views/footer.php'; ?>
