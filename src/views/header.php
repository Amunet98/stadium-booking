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
