<?php

if (!defined('PS_PUBLIC_PAGE')) {
    define('PS_PUBLIC_PAGE', true);
}

require_once __DIR__ . '/auth-bootstrap.php';

if (isset($_GET['token'])) {
    $raw = is_string($_GET['token']) ? $_GET['token'] : '';
    $parsed = ps_parse_token($raw);
    unset($_SESSION['password_reset']);
    if ($parsed) {
        $_SESSION['password_reset'] = [
            'token' => $raw,
            'expires' => time() + (int) ps_config()['reset_minutes'] * 60,
        ];
    }
    ps_redirect('reset-password.php');
}

$context = $_SESSION['password_reset'] ?? [];
if (empty($context['token']) || (int) ($context['expires'] ?? 0) <= time()) {
    unset($_SESSION['password_reset']);
    ps_flash('error', 'Open the link from your email or request a new reset link.');
    ps_redirect('forgot-password.php');
}

$pageTitle = 'Set a new password';
$eyebrow = 'ACCOUNT RECOVERY';
$description = 'Choose a new password. You will log in again after saving it.';
require dirname(__DIR__) . '/partials/auth-start.php';
