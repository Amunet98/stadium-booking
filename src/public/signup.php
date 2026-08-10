<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

start_session();
if (is_logged_in()) {
    redirect('index.php');
}

// Keyed by field so each message can be printed beside the input it concerns
// rather than in one list at the top, which leaves the user hunting.
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name     = trim((string) ($_POST['name'] ?? ''));
    $email    = trim((string) ($_POST['email'] ?? ''));
    $phone    = trim((string) ($_POST['phone'] ?? ''));
    $country  = trim((string) ($_POST['country'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '')                               $errors['name']     = 'Enter the name to print on your tickets.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email']    = 'That does not look like an email address.';
    if (strlen($password) < 8)                      $errors['password'] = 'Use at least 8 characters.';

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
                $errors['email'] = 'An account with that email already exists.';
            } else {
                throw $e;
            }
        }
    }
}

/** Print the error for a field, if it has one. */
$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<p class="app-field-error" id="' . $field . '-error" role="alert">' . e($errors[$field]) . '</p>'
        : '';
};

/** Wire an invalid input to its message for screen readers. */
$fieldAttrs = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? ' aria-invalid="true" aria-describedby="' . $field . '-error"'
        : '';
};

$pageTitle  = 'Sign up';
$navCurrent = 'signup';
require __DIR__ . '/../views/header.php';
?>

<div class="row justify-content-center py-lg-4">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body p-4">
                <h1 class="h4 mb-1">Create an account</h1>
                <p class="text-muted small mb-4">
                    Needed to hold a seat against your name. Nothing is charged and no
                    payment details are collected — this demo takes no money.
                </p>

                <?php if ($errors): ?>
                    <div class="alert alert-danger" role="alert">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>
                        </svg>
                        <span>
                            <?= count($errors) === 1 ? 'One field needs' : count($errors) . ' fields need' ?>
                            attention — see the messages below.
                        </span>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= url('signup.php') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="name">Name</label>
                        <input class="form-control" id="name" name="name" autocomplete="name"
                               value="<?= e($_POST['name'] ?? '') ?>"<?= $fieldAttrs('name') ?> required autofocus>
                        <?= $fieldError('name') ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" type="email" id="email" name="email"
                               autocomplete="email" inputmode="email"
                               value="<?= e($_POST['email'] ?? '') ?>"<?= $fieldAttrs('email') ?> required>
                        <?= $fieldError('email') ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <div class="app-password">
                            <input class="form-control" type="password" id="password" name="password"
                                   autocomplete="new-password" minlength="8"<?= $fieldAttrs('password') ?> required>
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
                        <?= $fieldError('password') ?>
                        <div class="form-text">At least 8 characters. Stored as a bcrypt hash.</div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <label class="form-label" for="phone">Phone <span class="text-muted fw-normal">(optional)</span></label>
                            <input class="form-control" id="phone" name="phone" type="tel"
                                   autocomplete="tel" inputmode="tel" value="<?= e($_POST['phone'] ?? '') ?>">
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label" for="country">Country <span class="text-muted fw-normal">(optional)</span></label>
                            <input class="form-control" id="country" name="country"
                                   autocomplete="country-name" value="<?= e($_POST['country'] ?? '') ?>">
                        </div>
                    </div>

                    <button class="btn btn-primary w-100 btn-lg" type="submit">Create account</button>
                </form>

                <p class="mt-3 mb-0 small text-center text-muted">
                    Already registered? <a href="<?= url('login.php') ?>">Log in</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../views/footer.php'; ?>
