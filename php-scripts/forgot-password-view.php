<?php

if (!defined('PS_PUBLIC_PAGE')) {
    define('PS_PUBLIC_PAGE', true);
}

require_once __DIR__ . '/auth-bootstrap.php';

$pageTitle = 'Forgot password?';
$eyebrow = 'ACCOUNT RECOVERY';
$description = 'Enter your email and we will send you a link to reset your password.';
require dirname(__DIR__) . '/partials/auth-start.php';
