<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
start_session();
$viewer = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?>Stadium Booking</title>
    <?php /*
        Applied before the stylesheets so the page never paints in the wrong
        theme first. A saved choice wins; otherwise follow the OS setting.
    */ ?>
    <script>
        (function () {
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
    <link rel="stylesheet" href="<?= url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?= url('index.php') ?>">Stadium Booking</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#nav" aria-controls="nav" aria-expanded="false"
                    aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('index.php') ?>">Fixtures</a>
                    </li>
                    <?php if ($viewer && (int) $viewer['rid'] === ROLE_ADMIN): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= url('admin.php') ?>">Admin</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <button type="button" id="theme-toggle" class="btn btn-link nav-link theme-toggle"
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
                    </li>
                    <?php if ($viewer): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= url('myticket.php') ?>">My tickets</a>
                        </li>
                        <li class="nav-item">
                            <span class="navbar-text me-2"><?= e($viewer['name'] ?: $viewer['email']) ?></span>
                        </li>
                        <li class="nav-item">
                            <form method="post" action="<?= url('logout.php') ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-link nav-link">Log out</button>
                            </form>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="<?= url('login.php') ?>">Log in</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= url('signup.php') ?>">Sign up</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>
<main class="container py-4">
