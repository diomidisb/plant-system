<?php

if (!defined('PS_PUBLIC_PAGE')) {
    define('PS_PUBLIC_PAGE', true);
}

define('PS_SKIP_REMEMBER', true);
require_once __DIR__ . '/auth-bootstrap.php';

ps_require_post('logout.php');

try {
    ps_revoke_browser_token();
} catch (Throwable $error) {
    error_log('Plant logout: ' . $error->getMessage());
    ps_flash('error', 'Unable to complete logout. Please try again.');
    ps_redirect('logout.php');
}

$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => $params['path'],
    'domain' => $params['domain'],
    'secure' => $params['secure'],
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_destroy();
ps_redirect('index.php');
