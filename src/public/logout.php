<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

// POST only. The original exposed logout as a GET link, so any page could log a
// visitor out with an <img src="...logout.php">.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

csrf_verify();
log_out();
redirect('index.php');
