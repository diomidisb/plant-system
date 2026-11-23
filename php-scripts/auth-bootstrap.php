<?php

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('pcre.jit', '0');
require_once __DIR__ . '/auth-functions.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $options = ps_cookie_options(0);
    unset($options['expires']);
    $options['lifetime'] = 0;
    session_set_cookie_params($options);
    if (!session_start()) {
        http_response_code(503);
        exit('Unable to start a session. Please try again later.');
    }
}

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
// Only the public introduction pages are intended for search results.
$searchPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
if (!in_array($searchPage, ['index.php','About.php'], true)) {
    header('X-Robots-Tag: noindex, nofollow');
}

try {
    ps_check_session();
    if (!isset($_SESSION['user_id']) && !defined('PS_SKIP_REMEMBER')) {
        ps_restore_remember();
    }
    if (!empty($_SESSION['account_disabled']) && !defined('PS_PUBLIC_PAGE')) {
        if (defined('PS_AUTH_API')) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false,'message' => 'Your account has been disabled by the administration.','redirect' => '/account-disabled.php']);
            exit;
        }
        ps_redirect('account-disabled.php');
    }
} catch (Throwable $error) {
    error_log('Plant auth bootstrap: ' . $error->getMessage());
    http_response_code(503);
    exit('The service is temporarily unavailable. Please try again later.');
}
