<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

start_session();
if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name     = trim((string) ($_POST['name'] ?? ''));
    $email    = trim((string) ($_POST['email'] ?? ''));
    $phone    = trim((string) ($_POST['phone'] ?? ''));
    $country  = trim((string) ($_POST['country'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '')                                       $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))         $errors[] = 'A valid email address is required.';
    if (strlen($password) < 8)                              $errors[] = 'Password must be at least 8 characters.';

    if (!$errors) {
        try {
            // The original SELECTed to check for a duplicate email and then
            // INSERTed — two statements with a gap between them, so two
            // concurrent signups could both pass the check. The UNIQUE index
            // decides it now, and the duplicate surfaces as a 23000 here.
            $stmt = db()->prepare(
                'INSERT INTO users (name, email, password, phone, country, rid)
                 VALUES (:name, :email, :password, :phone, :country, :rid)'
            );
            $stmt->execute([
                'name'     => $name,          // the original collected this and never stored it
                'email'    => $email,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'phone'    => $phone !== '' ? $phone : null,
                'country'  => $country !== '' ? $country : null,
                'rid'      => ROLE_USER,      // never taken from user input
            ]);

            $_SESSION['notice'] = 'Account created. You can log in now.';
            redirect('login.php');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'An account with that email already exists.';
            } else {
                throw $e;
            }
        }
    }
}

$pageTitle = 'Sign up';
require __DIR__ . '/../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h1 class="h4 card-title mb-3">Create an account</h1>

                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= url('signup.php') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="name">Name</label>
                        <input class="form-control" id="name" name="name"
                               value="<?= e($_POST['name'] ?? '') ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" type="email" id="email" name="email"
                               value="<?= e($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control" type="password" id="password" name="password"
                               minlength="8" required>
                        <div class="form-text">At least 8 characters.</div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <label class="form-label" for="phone">Phone <span class="text-muted">(optional)</span></label>
                            <input class="form-control" id="phone" name="phone"
                                   value="<?= e($_POST['phone'] ?? '') ?>">
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label" for="country">Country <span class="text-muted">(optional)</span></label>
                            <input class="form-control" id="country" name="country"
                                   value="<?= e($_POST['country'] ?? '') ?>">
                        </div>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Sign up</button>
                </form>

                <p class="mt-3 mb-0 small text-center">
                    Already registered? <a href="<?= url('login.php') ?>">Log in</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../views/footer.php'; ?>
