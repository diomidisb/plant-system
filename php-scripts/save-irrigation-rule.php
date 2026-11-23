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


function failResponse(
    int $status,
    string $message
): never {

    http_response_code($status);

    echo json_encode([
        "success" => false,
        "message" => $message
    ]);

    exit;
}


if (!isset($_SESSION["user_id"])) {
    failResponse(401, "You are not logged in.");
}


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    failResponse(405, "POST request required.");
}


$data =
    json_decode(
        file_get_contents("php://input"),
        true
    );


if (!is_array($data)) {
    failResponse(400, "Invalid JSON.");
}


$csrfToken =
    $data["csrf_token"] ?? "";


if (
    empty($_SESSION["csrf_token"])
    ||
    !is_string($csrfToken)
    ||
    !hash_equals(
        $_SESSION["csrf_token"],
        $csrfToken
    )
) {
    failResponse(403, "Invalid security token.");
}


$userId =
    (int)$_SESSION["user_id"];


$deviceId =
    (int)($data["device_id"] ?? 0);


if ($deviceId <= 0) {
    failResponse(422, "Invalid device.");
}


$pdo->beginTransaction();
$userLock = $pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
$userLock->execute([$userId]);

$deviceStmt =
    $pdo->prepare(
        "SELECT id
         FROM devices
         WHERE id = ?
         AND user_id = ?
         LIMIT 1 FOR UPDATE"
    );


$deviceStmt->execute([
    $deviceId,
    $userId
]);


if (!$deviceStmt->fetchColumn()) {
    failResponse(404, "Device not found.");
}


$allowedOperators = [
    "<",
    ">",
    "="
];


function readCondition(
    array $data,
    string $name,
    array $allowedOperators,
    float $min,
    float $max
): array {

    $condition =
        $data[$name] ?? [];


    if (!is_array($condition)) {
        failResponse(422, 'Invalid sensor condition.');
    }
    if (isset($condition['value']) && (!is_numeric($condition['value']) || is_bool($condition['value']) || !is_finite((float)$condition['value']))) {
        failResponse(422, 'Invalid sensor threshold.');
    }

    $enabled =
        !empty(
            $condition["enabled"]
        );


    $operator =
        (string)(
            $condition["operator"]
            ??
            "<"
        );


    $value =
        isset($condition["value"])
            ? (float)$condition["value"]
            : 0;


    if (
        !in_array(
            $operator,
            $allowedOperators,
            true
        )
    ) {

        failResponse(
            422,
            "Invalid operator for "
            .
            $name
            .
            "."
        );
    }


    if (
        $value < $min
        ||
        $value > $max
    ) {

        failResponse(
            422,
            "Invalid threshold for "
            .
            $name
            .
            "."
        );
    }


    return [
        "enabled" => $enabled,
        "operator" => $operator,
        "value" => $value
    ];
}


$soil =
    readCondition(
        $data,
        "soil",
        $allowedOperators,
        0,
        100
    );


$light =
    readCondition(
        $data,
        "light",
        $allowedOperators,
        0,
        100
    );


$temperature =
    readCondition(
        $data,
        "temperature",
        $allowedOperators,
        -50,
        100
    );


$humidity =
    readCondition(
        $data,
        "humidity",
        $allowedOperators,
        0,
        100
    );


$enabled =
    !empty(
        $data["enabled"]
    );


$activeConditions =
    (int)$soil["enabled"]
    +
    (int)$light["enabled"]
    +
    (int)$temperature["enabled"]
    +
    (int)$humidity["enabled"];


if (
    $enabled
    &&
    $activeConditions === 0
) {

    failResponse(
        422,
        "Enable at least one sensor condition."
    );
}


$logic =
    strtoupper(
        (string)(
            $data["logic"]
            ??
            "AND"
        )
    );


if (
    $logic !== "AND"
    &&
    $logic !== "OR"
) {

    failResponse(
        422,
        "Logic must be AND or OR."
    );
}


$duration =
    (int)(
        $data["duration_seconds"]
        ??
        30
    );


if (
    $duration < 1
    ||
    $duration > 600
) {

    failResponse(
        422,
        "Watering duration must be between 1 and 600 seconds."
    );
}


$cooldown =
    (int)(
        $data["cooldown_minutes"]
        ??
        30
    );


if (
    $cooldown < 0
    ||
    $cooldown > 10080
) {

    failResponse(
        422,
        "Cooldown must be between 0 minutes and 7 days."
    );
}


$sql =
    "INSERT INTO irrigation_rules (
        device_id,
        enabled,
        logic_mode,

        soil_enabled,
        soil_operator,
        soil_value,

        light_enabled,
        light_operator,
        light_value,

        temperature_enabled,
        temperature_operator,
        temperature_value,

        humidity_enabled,
        humidity_operator,
        humidity_value,

        duration_seconds,
        cooldown_minutes
     )
     VALUES (
        ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?,
        ?, ?
     )
     ON DUPLICATE KEY UPDATE
        enabled = VALUES(enabled),
        logic_mode = VALUES(logic_mode),

        soil_enabled = VALUES(soil_enabled),
        soil_operator = VALUES(soil_operator),
        soil_value = VALUES(soil_value),

        light_enabled = VALUES(light_enabled),
        light_operator = VALUES(light_operator),
        light_value = VALUES(light_value),

        temperature_enabled = VALUES(temperature_enabled),
        temperature_operator = VALUES(temperature_operator),
        temperature_value = VALUES(temperature_value),

        humidity_enabled = VALUES(humidity_enabled),
        humidity_operator = VALUES(humidity_operator),
        humidity_value = VALUES(humidity_value),

        duration_seconds = VALUES(duration_seconds),
        cooldown_minutes = VALUES(cooldown_minutes),

        updated_at = CURRENT_TIMESTAMP";


$stmt =
    $pdo->prepare(
        $sql
    );


$stmt->execute([
    $deviceId,
    $enabled ? 1 : 0,
    $logic,

    $soil["enabled"] ? 1 : 0,
    $soil["operator"],
    $soil["value"],

    $light["enabled"] ? 1 : 0,
    $light["operator"],
    $light["value"],

    $temperature["enabled"] ? 1 : 0,
    $temperature["operator"],
    $temperature["value"],

    $humidity["enabled"] ? 1 : 0,
    $humidity["operator"],
    $humidity["value"],

    $duration,
    $cooldown
]);


$pdo->commit();
echo json_encode([
    "success" => true
]);
