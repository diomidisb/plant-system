<?php

if (!defined('PS_PUBLIC_PAGE')) {
    define('PS_PUBLIC_PAGE', true);
}

define('PS_SKIP_REMEMBER', true);
require_once __DIR__ . '/auth-bootstrap.php';

$pageTitle = 'Log out';
$eyebrow = 'PLANT SYSTEM';
$description = 'Log out and remove Remember me from this browser?';
require dirname(__DIR__) . '/partials/auth-start.php';
