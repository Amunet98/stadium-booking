<?php $pageTitle = 'Not allowed'; require __DIR__ . '/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-6 text-center py-5">
        <h1 class="display-6">403</h1>
        <p class="lead">This area is for administrators.</p>
        <p class="text-muted">
            You are signed in, but your account does not have admin rights.
        </p>
        <a class="btn btn-primary" href="<?= url('index.php') ?>">Back to fixtures</a>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
