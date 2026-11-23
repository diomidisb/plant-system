<?php

if (!defined('PS_PUBLIC_PAGE')) {
    define('PS_PUBLIC_PAGE', true);
}

require_once __DIR__ . '/admin-auth.php';

if (pa_active()) {
    header('Location: /admin.php', true, 303);
    exit;
}

if (isset($_SESSION['user_id'])) {
    ps_redirect('index.php');
}

$pageTitle = 'Log in';
$eyebrow = 'WELCOME BACK';
$description = 'Enter your details to continue.';
$email = ps_take_flash('email');
require dirname(__DIR__) . '/partials/auth-start.php';
