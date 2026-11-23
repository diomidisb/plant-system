<?php

require_once __DIR__.'/i18n.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$supportOnly = isset($_SESSION['support_user_id']) && !isset($_SESSION['user_id']);
$isLoggedIn = isset($_SESSION['user_id']) || $supportOnly;
$isPending = isset($_SESSION['pending_id']);

$userName = $_SESSION['user_name'] ?? $_SESSION['support_user_name'] ?? '';
$pendingName = $_SESSION['pending_name'] ?? '';
