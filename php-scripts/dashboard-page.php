<?php

require_once __DIR__ . '/auth-bootstrap.php';

// BUILD: AUTO-FIX-1787919282

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = ps_db();


// =====================================================
// LOGIN CHECK
// =====================================================

if (!isset($_SESSION["user_id"])) {

    header("Location: login.php");
    exit;
}


$userId =
    (int)$_SESSION["user_id"];

$userName =
    $_SESSION["user_name"] ?? "User";


// =====================================================
// CSRF TOKEN
// =====================================================

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// =====================================================
// USER DEVICES
// =====================================================

$stmt = $pdo->prepare(
    "SELECT
        id,
        device_name,
        device_uid,
        last_seen,
        is_online
     FROM devices
     WHERE user_id = ?
     ORDER BY created_at ASC"
);

$stmt->execute([
    $userId
]);

$devices =
    $stmt->fetchAll(PDO::FETCH_ASSOC);
