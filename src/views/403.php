<?php $pageTitle = 'Not allowed'; require __DIR__ . '/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="empty-state">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="11" width="18" height="11" rx="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <h1 class="h4 mb-2">403 &mdash; administrators only</h1>
            <p>
                You are signed in, but this account does not have admin rights.
                Nothing here is hidden by the navigation alone; the check runs
                before the page does.
            </p>
            <div class="d-flex flex-wrap gap-2 justify-content-center">
                <a class="btn btn-primary" href="<?= url('fixtures.php') ?>">Browse fixtures</a>
                <a class="btn btn-ghost" href="<?= url('index.php') ?>">Home</a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
