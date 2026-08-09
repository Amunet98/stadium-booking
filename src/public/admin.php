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

$pages = [
    'matches'  => 'Matches',
    'teams'    => 'Teams',
    'stadiums' => 'Stadiums',
    'bookings' => 'Bookings',
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

$pageTitle = 'Admin — ' . $pages[$page];
require __DIR__ . '/../views/header.php';
?>

<div class="row">
    <aside class="col-lg-3 mb-4">
        <div class="list-group">
            <?php foreach ($pages as $key => $label): ?>
                <a class="list-group-item list-group-item-action <?= $key === $page ? 'active' : '' ?>"
                   href="<?= url('admin.php?page=' . $key) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    </aside>

    <section class="col-lg-9">
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['ok'] ? 'success' : 'danger' ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <?php admin_render($page, $action); ?>
    </section>
</div>

<?php require __DIR__ . '/../views/footer.php'; ?>
