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

$pageTitle = 'Log in';
require __DIR__ . '/../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card">
            <div class="card-body">
                <h1 class="h4 card-title mb-3">Log in</h1>

                <?php if ($notice): ?>
                    <div class="alert alert-success"><?= e($notice) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= url('login.php') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" type="email" id="email" name="email"
                               value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control" type="password" id="password" name="password" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Log in</button>
                </form>

                <p class="mt-3 mb-0 small text-center">
                    No account? <a href="<?= url('signup.php') ?>">Sign up</a>
                </p>

                <?php if ((getenv('APP_ENV') ?: 'development') !== 'production'): ?>
                    <hr>
                    <p class="small text-muted mb-0">
                        <strong>Demo:</strong> admin@example.com / Admin!2345 &middot;
                        alex@example.com / Passw0rd!23
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../views/footer.php'; ?>
