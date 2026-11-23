<?php

require_once __DIR__ . '/auth-bootstrap.php';

if (isset($_SESSION['user_id'])) {
    ps_redirect('index.php');
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$error = $_SESSION['signup_error'] ?? '';
$old = $_SESSION['signup_old'] ?? [];

unset(
    $_SESSION['signup_error'],
    $_SESSION['signup_old']
);

if (empty($_SESSION['signup_csrf'])) {
    $_SESSION['signup_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['signup_csrf'];
