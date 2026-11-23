<?php

if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)
    && parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/index.php') {
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if (strpbrk($query, "\r\n") !== false) {
        $query = '';
    }
    header('Location: /' . ($query !== '' ? '?' . $query : ''), true, 308);
    exit;
}

if (!defined('PS_PUBLIC_PAGE')) {
    define('PS_PUBLIC_PAGE', true);
}

require_once __DIR__ . '/auth-bootstrap.php';

$currentPage = basename($_SERVER['PHP_SELF']);
