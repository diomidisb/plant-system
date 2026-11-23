<?php

if (!defined('PS_AUTH_API')) {
    define('PS_AUTH_API', true);
}
require_once __DIR__ . '/auth-bootstrap.php';


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");

$pdo = ps_db();


if (!isset($_SESSION["user_id"])) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "You are not logged in."
    ]);

    exit;
}


$userId =
    (int)$_SESSION["user_id"];


$deviceId =
    isset($_GET["device_id"])
        ? (int)$_GET["device_id"]
        : 0;


if ($deviceId <= 0) {

    http_response_code(422);

    echo json_encode([
        "success" => false,
        "message" => "Invalid device."
    ]);

    exit;
}


$stmt =
    $pdo->prepare(
        "SELECT r.*
         FROM irrigation_rules r
         INNER JOIN devices d
            ON d.id = r.device_id
         WHERE r.device_id = ?
         AND d.user_id = ?
         LIMIT 1"
    );


$stmt->execute([
    $deviceId,
    $userId
]);


$row =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$row) {

    echo json_encode([
        "success" => true,
        "rule" => null
    ]);

    exit;
}


echo json_encode([
    "success" => true,

    "rule" => [
        "enabled" =>
            (bool)$row["enabled"],

        "logic" =>
            $row["logic_mode"],

        "duration_seconds" =>
            (int)$row["duration_seconds"],

        "cooldown_minutes" =>
            (int)$row["cooldown_minutes"],

        "soil" => [
            "enabled" =>
                (bool)$row["soil_enabled"],
            "operator" =>
                $row["soil_operator"],
            "value" =>
                (float)$row["soil_value"]
        ],

        "light" => [
            "enabled" =>
                (bool)$row["light_enabled"],
            "operator" =>
                $row["light_operator"],
            "value" =>
                (float)$row["light_value"]
        ],

        "temperature" => [
            "enabled" =>
                (bool)$row["temperature_enabled"],
            "operator" =>
                $row["temperature_operator"],
            "value" =>
                (float)$row["temperature_value"]
        ],

        "humidity" => [
            "enabled" =>
                (bool)$row["humidity_enabled"],
            "operator" =>
                $row["humidity_operator"],
            "value" =>
                (float)$row["humidity_value"]
        ]
    ]
]);
