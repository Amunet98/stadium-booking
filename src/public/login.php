<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

start_session();
if (is_logged_in()) {
    redirect('index.php');
}

$error  = null;
$notice = $_SESSION['notice'] ?? null;
unset($_SESSION['notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT uid, name, email, password, rid FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // One message for "no such account" and "wrong password" alike: distinct
    // messages let an attacker enumerate which addresses are registered. The
    // original printed "no user found" for the former.
    if ($user && verify_and_upgrade_password($user, $password)) {
        log_in_user($user);
        redirect((int) $user['rid'] === ROLE_ADMIN ? 'admin.php' : 'index.php');
    }
    $error = 'Those credentials do not match an account.';
}

$isDemo = (getenv('APP_ENV') ?: 'development') !== 'production';

$pageTitle  = 'Log in';
$navCurrent = 'login';
require __DIR__ . '/../views/header.php';
?>

<div class="row justify-content-center py-lg-4">
    <div class="col-md-7 col-lg-5">
        <div class="card">
            <div class="card-body p-4">
                <h1 class="h4 mb-1">Welcome back</h1>
                <p class="text-muted small mb-4">Log in to book a seat and see your tickets.</p>

                <?php if ($notice): ?>
                    <div class="alert alert-success" role="status">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 6 9 17l-5-5"/>
                        </svg>
                        <span><?= e($notice) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>
                        </svg>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= url('login.php') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" type="email" id="email" name="email"
                               value="<?= e($_POST['email'] ?? '') ?>"
                               autocomplete="username" inputmode="email" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password">Password</label>
                        <div class="app-password">
                            <input class="form-control" type="password" id="password" name="password"
                                   autocomplete="current-password" required>
                            <button type="button" aria-label="Show password" aria-pressed="false">
                                <svg class="icon-show" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round" aria-hidden="true">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="icon-hide" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round" aria-hidden="true">
                                    <path d="M10.6 6.2A9.9 9.9 0 0 1 12 6c6.5 0 10 7 10 7a17 17 0 0 1-2.6 3.5M6.6 6.6A17 17 0 0 0 2 13s3.5 7 10 7a9.9 9.9 0 0 0 4.4-1"/>
                                    <path d="m2 2 20 20"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 btn-lg" type="submit">Log in</button>
                </form>

                <p class="mt-3 mb-0 small text-center text-muted">
                    No account? <a href="<?= url('signup.php') ?>">Sign up</a>
                </p>

                <?php /* Outside production only. On the public demo the credentials
                         are in the README instead — printing them on the login form
                         of a live site is an invitation, not a convenience. */ ?>
                <?php if ($isDemo): ?>
                    <div class="mt-4 pt-3 border-top">
                        <p class="small text-muted mb-2"><strong>Demo accounts</strong></p>
                        <div class="d-grid gap-2">
                            <?php foreach ([
                                ['Admin', 'admin@example.com', 'Admin!2345'],
                                ['User',  'alex@example.com',  'Passw0rd!23'],
                            ] as [$role, $demoEmail, $demoPassword]): ?>
                                <?php /* A real form post rather than a JS autofill:
                                         it works with scripting off and needs no
                                         credentials in the page's JavaScript. */ ?>
                                <form method="post" action="<?= url('login.php') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="email" value="<?= e($demoEmail) ?>">
                                    <input type="hidden" name="password" value="<?= e($demoPassword) ?>">
                                    <button class="btn btn-ghost btn-sm w-100 d-flex justify-content-between" type="submit">
                                        <span>Log in as <?= e($role) ?></span>
                                        <span class="text-muted"><?= e($demoEmail) ?></span>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../views/footer.php'; ?>
