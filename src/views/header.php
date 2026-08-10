<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
start_session();
$viewer = current_user();

/*
 * Which nav item is current. Pages set $navCurrent; anything that does not
 * falls back to the script name, so a new page gets a sensible default rather
 * than an unlit navigation.
 */
$navCurrent = $navCurrent ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php');

/** Mark the active item for both CSS and assistive technology. */
$navAttrs = static fn(string $key): string =>
    $key === $navCurrent ? ' aria-current="page"' : '';
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?>Stadium Booking</title>
    <meta name="description" content="Book a seat for a Premier League fixture. A 2021 coursework booking system, rebuilt in 2026 with the twenty defects it shipped with documented and fixed.">
    <meta name="color-scheme" content="light dark">
    <?php /*
        Applied before the stylesheets so the page never paints in the wrong
        theme first. A saved choice wins; otherwise follow the OS setting.

        The no-js class comes off in the same breath: the reveal animations
        start elements at opacity 0, so if scripting is off they must never be
        hidden in the first place. Doing it here rather than in app.js means
        the class is gone before the stylesheet is parsed, so there is no
        flash of hidden content either.
    */ ?>
    <script>
        (function () {
            document.documentElement.classList.remove('no-js');
            try {
                var saved = localStorage.getItem('theme');
                var dark  = saved
                    ? saved === 'dark'
                    : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
            } catch (e) {
                document.documentElement.setAttribute('data-bs-theme', 'light');
            }
        })();
    </script>
    <?php /* The fonts are render-blocking for headings; preload beats waiting
             for the stylesheet to be parsed before the request even starts. */ ?>
    <link rel="preload" href="<?= url('assets/fonts/inter.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= url('assets/fonts/archivo.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <?php /* Without this the browser requests /favicon.ico, gets a 404, and
             logs a console error on every page load. */ ?>
    <link rel="icon" href="<?= url('favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<a class="app-skip" href="#main">Skip to content</a>

<header>
    <nav class="navbar navbar-expand-lg app-nav sticky-top" id="app-nav">
        <div class="container">
            <a class="app-brand" href="<?= url('index.php') ?>">
                <?php /* Ticket stub with a floodlight mast. 2px strokes, to
                         match the icon weight used across the other demos. */ ?>
                <svg class="app-brand-mark" width="30" height="30" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 9V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 6v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-6z"/>
                    <path d="M13 5v3M13 11v2M13 16v3"/>
                </svg>
                <span>
                    Stadium Booking
                    <span class="app-brand-sub">Matchday tickets</span>
                </span>
            </a>

            <div class="d-flex align-items-center gap-1 order-lg-3">
                <button type="button" id="theme-toggle" class="app-icon-btn theme-toggle"
                        aria-label="Switch between light and dark theme" title="Switch theme">
                    <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="4"></circle>
                        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path>
                    </svg>
                    <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"></path>
                    </svg>
                </button>

                <?php if ($viewer): ?>
                    <div class="dropdown d-none d-lg-block">
                        <button class="app-user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="app-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($viewer['name'] ?: $viewer['email'], 0, 1))) ?></span>
                            <span class="d-none d-xl-inline"><?= e($viewer['name'] ?: $viewer['email']) ?></span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                            <span class="visually-hidden">Account menu</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="dropdown-header text-truncate"><?= e($viewer['email']) ?></li>
                            <li><a class="dropdown-item" href="<?= url('myticket.php') ?>">My tickets</a></li>
                            <?php if ((int) $viewer['rid'] === ROLE_ADMIN): ?>
                                <li><a class="dropdown-item" href="<?= url('admin.php') ?>">Admin</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <?php /* A POST with a CSRF token, not a link: a GET
                                         log-out can be triggered by any image tag. */ ?>
                                <form method="post" action="<?= url('logout.php') ?>" class="dropdown-item-form">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="dropdown-item dropdown-item-danger">Log out</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a class="btn btn-ghost btn-sm d-none d-lg-inline-flex" href="<?= url('login.php') ?>">Log in</a>
                    <a class="btn btn-primary btn-sm d-none d-lg-inline-flex" href="<?= url('signup.php') ?>">Sign up</a>
                <?php endif; ?>

                <button class="app-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas"
                        data-bs-target="#app-menu" aria-controls="app-menu" aria-label="Open menu">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>

            <?php /* Desktop navigation. On phones the same links live in the
                     offcanvas below, where they get 48px rows. */ ?>
            <ul class="navbar-nav d-none d-lg-flex flex-row me-auto ms-4 order-lg-2">
                <li class="nav-item"><a class="nav-link" href="<?= url('index.php') ?>"<?= $navAttrs('index') ?>>Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= url('fixtures.php') ?>"<?= $navAttrs('fixtures') ?>>Fixtures</a></li>
                <?php if ($viewer): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url('myticket.php') ?>"<?= $navAttrs('myticket') ?>>My tickets</a></li>
                <?php endif; ?>
                <?php if ($viewer && (int) $viewer['rid'] === ROLE_ADMIN): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url('admin.php') ?>"<?= $navAttrs('admin') ?>>Admin</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="app-menu" aria-labelledby="app-menu-title">
        <div class="offcanvas-header">
            <h2 class="offcanvas-title h6 mb-0" id="app-menu-title">Menu</h2>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="navbar-nav mb-3">
                <li class="nav-item"><a class="nav-link" href="<?= url('index.php') ?>"<?= $navAttrs('index') ?>>Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= url('fixtures.php') ?>"<?= $navAttrs('fixtures') ?>>Fixtures</a></li>
                <?php if ($viewer): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url('myticket.php') ?>"<?= $navAttrs('myticket') ?>>My tickets</a></li>
                <?php endif; ?>
                <?php if ($viewer && (int) $viewer['rid'] === ROLE_ADMIN): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url('admin.php') ?>"<?= $navAttrs('admin') ?>>Admin</a></li>
                <?php endif; ?>
            </ul>

            <?php if ($viewer): ?>
                <div class="d-flex align-items-center gap-2 mb-3 pt-3 border-top">
                    <span class="app-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($viewer['name'] ?: $viewer['email'], 0, 1))) ?></span>
                    <span class="small text-truncate"><?= e($viewer['name'] ?: $viewer['email']) ?></span>
                </div>
                <form method="post" action="<?= url('logout.php') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost w-100">Log out</button>
                </form>
            <?php else: ?>
                <div class="d-grid gap-2 pt-3 border-top">
                    <a class="btn btn-primary" href="<?= url('signup.php') ?>">Sign up</a>
                    <a class="btn btn-ghost" href="<?= url('login.php') ?>">Log in</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<main id="main" tabindex="-1"<?= empty($fullWidth) ? ' class="container py-4"' : '' ?>>
