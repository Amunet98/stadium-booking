<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/helpers.php';

/*
 * Single admin entry point.
 *
 * The original had admin/index.php include sidebar.php and dashboard.php with
 * no check of any kind, so /booking/admin was open to the world. The role
 * column existed and was populated — it was just never read after login.
 *
 * require_admin() is the first thing that runs here, before any routing, so
 * there is no path through this file that reaches a handler unauthenticated.
 */
require_admin();

require_once __DIR__ . '/../admin/handlers.php';

$page   = (string) ($_GET['page'] ?? 'matches');
$action = (string) ($_GET['action'] ?? 'list');

// Icons live beside the labels so the sidebar reads at a glance; every one is
// paired with its text, never on its own.
$pages = [
    'matches'  => ['label' => 'Matches',  'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
    'teams'    => ['label' => 'Teams',    'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/>'],
    'stadiums' => ['label' => 'Grounds',  'icon' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>'],
    'bookings' => ['label' => 'Bookings', 'icon' => '<path d="M3 9V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 6v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-6z"/><path d="M13 5v14" stroke-dasharray="2 3"/>'],
];

if (!isset($pages[$page])) {
    $page = 'matches';
}

// POSTs are handled before any output so a handler can redirect.
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $flash = admin_handle_post($page);
}

$counts = admin_counts(db());

$pageTitle  = 'Admin — ' . $pages[$page]['label'];
$navCurrent = 'admin';
require __DIR__ . '/../views/header.php';
?>

<div class="page-hero">
    <h1 class="h3">Admin</h1>
    <p>
        Full create access to fixtures, clubs and grounds, plus every customer booking.
        This is the interface that had no access control at all in 2021.
    </p>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Fixtures', $counts['matches'],  'scheduled'],
        ['Clubs',    $counts['teams'],    'registered'],
        ['Grounds',  $counts['stadiums'], 'available'],
        ['Bookings', $counts['bookings'], 'sold to date'],
    ] as [$label, $value, $sub]): ?>
        <div class="col-6 col-lg-3">
            <div class="stat-tile" data-reveal>
                <span class="stat-value"><?= number_format((int) $value) ?></span>
                <span class="stat-label"><?= e($label) ?> &middot; <?= e($sub) ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <aside class="col-lg-3">
        <nav class="list-group admin-nav" aria-label="Admin sections">
            <?php foreach ($pages as $key => $meta): ?>
                <a class="list-group-item list-group-item-action <?= $key === $page ? 'active' : '' ?>"
                   href="<?= url('admin.php?page=' . $key) ?>"
                   <?= $key === $page ? 'aria-current="page"' : '' ?>>
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <?= $meta['icon'] ?>
                    </svg>
                    <?= e($meta['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <section class="col-lg-9">
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['ok'] ? 'success' : 'danger' ?>" role="alert">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <?= $flash['ok'] ? '<path d="M20 6 9 17l-5-5"/>' : '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>' ?>
                </svg>
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <?php admin_render($page, $action); ?>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../views/footer.php'; ?>
