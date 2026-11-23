<?php

require_once __DIR__ . '/auth-bootstrap.php';


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
if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "GET") {
    session_write_close();
}


// =====================================================
// DEVICE STATUS API
// =====================================================

if (
    isset($_GET["action"])
    &&
    $_GET["action"] === "device_status"
) {

    header("Content-Type: application/json; charset=utf-8");

    $deviceId =
        isset($_GET["device_id"])
            ? (int)$_GET["device_id"]
            : 0;


    if ($deviceId <= 0) {

        echo json_encode([
            "success" => false,
            "message" => "Invalid device."
        ]);

        exit;
    }


    $statusStmt = $pdo->prepare(
        "SELECT
            id,
            is_online,
            last_seen
         FROM devices
         WHERE id = ?
         AND user_id = ?
         LIMIT 1"
    );


    $statusStmt->execute([
        $deviceId,
        $userId
    ]);


    $deviceStatus =
        $statusStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$deviceStatus) {

        echo json_encode([
            "success" => false,
            "message" => "Device not found."
        ]);

        exit;
    }


    echo json_encode([
        "success" => true,
        "is_online" => (int)$deviceStatus["is_online"],
        "last_seen" => $deviceStatus["last_seen"]
    ]);

    exit;
}


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
