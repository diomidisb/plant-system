<?php

if (!defined('PS_AUTH_API')) {
    define('PS_AUTH_API', true);
}
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

    $action =
        isset($_GET["action"])
            ? (string)$_GET["action"]
            : "";

    $plainTextActions = [
        "water_now",
        "stop_watering",
        "clear_irrigation_commands"
    ];

    http_response_code(401);
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

    if (
        in_array(
            $action,
            $plainTextActions,
            true
        )
    ) {

        header("Content-Type: text/plain; charset=utf-8");

        echo "ERROR|Session expired. Please log in again.";

    } else {

        header("Content-Type: application/json; charset=utf-8");

        echo json_encode([
            "success" => false,
            "message" => "Session expired. Please log in again."
        ]);
    }

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
if ($_SERVER["REQUEST_METHOD"] === "GET") {
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
// WATER NOW API - PLAIN TEXT RESPONSE
// =====================================================

if (
    isset($_GET["action"])
    &&
    $_GET["action"] === "water_now"
) {

    header("Content-Type: text/plain; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

    function waterReply(
        bool $success,
        string $message,
        int $statusCode = 200
    ): never {

        http_response_code($statusCode);

        $message =
            str_replace(
                ["\r", "\n", "|"],
                [" ", " ", "/"],
                $message
            );

        echo ($success ? "OK|" : "ERROR|") . $message;
        exit;
    }


    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        waterReply(false, "POST request required.", 405);
    }


    try {

        $deviceId =
            isset($_POST["device_id"])
                ? (int)$_POST["device_id"]
                : 0;

        $duration =
            isset($_POST["duration"])
                ? (int)$_POST["duration"]
                : 0;

        $csrfToken =
            isset($_POST["csrf_token"])
                ? (string)$_POST["csrf_token"]
                : "";


        if (
            empty($_SESSION["csrf_token"])
            ||
            !hash_equals(
                $_SESSION["csrf_token"],
                $csrfToken
            )
        ) {
            waterReply(false, "Invalid security token.", 403);
        }


        if (
            $deviceId <= 0
            ||
            $duration < 1
            ||
            $duration > 600
        ) {
            waterReply(
                false,
                "Duration must be between 1 and 600 seconds.",
                422
            );
        }


        $deviceStmt =
            $pdo->prepare(
                "SELECT id, device_uid, is_online
                 FROM devices
                 WHERE id = ?
                 AND user_id = ?
                 LIMIT 1"
            );

        $deviceStmt->execute([
            $deviceId,
            $userId
        ]);

        $device =
            $deviceStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$device) {
            waterReply(false, "Device not found.", 404);
        }


        if ((int)$device["is_online"] !== 1) {
            waterReply(false, "Device is offline.", 409);
        }


        $activeStmt =
            $pdo->prepare(
                "SELECT id
                 FROM irrigation_commands
                 WHERE device_id = ?
                 AND command_type = 'water'
                 AND status IN (
                    'pending',
                    'sent',
                    'accepted',
                    'started'
                 )
                 LIMIT 1"
            );

        $activeStmt->execute([
            $deviceId
        ]);


        if ($activeStmt->fetchColumn()) {
            waterReply(
                false,
                "This device already has an active watering command.",
                409
            );
        }


        $commandId =
            "water_"
            .
            bin2hex(
                random_bytes(12)
            );


        $insertStmt =
            $pdo->prepare(
                "INSERT INTO irrigation_commands (
                    device_id,
                    command_id,
                    command_type,
                    duration_seconds,
                    status
                 )
                 VALUES (
                    ?,
                    ?,
                    'water',
                    ?,
                    'pending'
                 )"
            );

        $insertStmt->execute([
            $deviceId,
            $commandId,
            $duration
        ]);


        waterReply(
            true,
            $commandId,
            200
        );

    } catch (Throwable $e) {

        waterReply(
            false,
            "Water Now error: " . $e->getMessage(),
            500
        );
    }
}


// =====================================================
// STOP WATERING API - PLAIN TEXT RESPONSE
// =====================================================

if (
    isset($_GET["action"])
    &&
    $_GET["action"] === "stop_watering"
) {

    header("Content-Type: text/plain; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

    function stopWaterReply(
        bool $success,
        string $message,
        int $statusCode = 200
    ): never {

        http_response_code($statusCode);

        $message =
            str_replace(
                ["\r", "\n", "|"],
                [" ", " ", "/"],
                $message
            );

        echo ($success ? "OK|" : "ERROR|") . $message;
        exit;
    }


    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        stopWaterReply(false, "POST request required.", 405);
    }


    try {

        $deviceId =
            isset($_POST["device_id"])
                ? (int)$_POST["device_id"]
                : 0;

        $csrfToken =
            isset($_POST["csrf_token"])
                ? (string)$_POST["csrf_token"]
                : "";


        if (
            empty($_SESSION["csrf_token"])
            ||
            !hash_equals(
                $_SESSION["csrf_token"],
                $csrfToken
            )
        ) {
            stopWaterReply(false, "Invalid security token.", 403);
        }


        if ($deviceId <= 0) {
            stopWaterReply(false, "Invalid device.", 422);
        }


        $deviceStmt =
            $pdo->prepare(
                "SELECT id, device_uid, is_online
                 FROM devices
                 WHERE id = ?
                 AND user_id = ?
                 LIMIT 1"
            );

        $deviceStmt->execute([
            $deviceId,
            $userId
        ]);

        $device =
            $deviceStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$device) {
            stopWaterReply(false, "Device not found.", 404);
        }


        if ((int)$device["is_online"] !== 1) {
            stopWaterReply(false, "Device is offline.", 409);
        }


        $pdo->beginTransaction();

        // Cancel water commands which have not yet been sent to the ESP.
        $cancelPending =
            $pdo->prepare(
                "UPDATE irrigation_commands
                 SET
                    status = 'stopped',
                    completed_at = NOW(),
                    error_message = 'Cancelled by user before sending'
                 WHERE device_id = ?
                 AND command_type = 'water'
                 AND status = 'pending'"
            );

        $cancelPending->execute([
            $deviceId
        ]);


        // Avoid stacking multiple stop commands.
        $existingStop =
            $pdo->prepare(
                "SELECT command_id
                 FROM irrigation_commands
                 WHERE device_id = ?
                 AND command_type = 'stop'
                 AND status IN ('pending', 'sent')
                 ORDER BY id DESC
                 LIMIT 1"
            );

        $existingStop->execute([
            $deviceId
        ]);

        $existingStopId =
            $existingStop->fetchColumn();


        if ($existingStopId) {

            $pdo->commit();

            stopWaterReply(
                true,
                "Stop command already queued: " . $existingStopId,
                200
            );
        }


        $commandId =
            "stop_"
            .
            bin2hex(
                random_bytes(12)
            );


        $insertStop =
            $pdo->prepare(
                "INSERT INTO irrigation_commands (
                    device_id,
                    command_id,
                    command_type,
                    duration_seconds,
                    status
                 )
                 VALUES (
                    ?,
                    ?,
                    'stop',
                    NULL,
                    'pending'
                 )"
            );

        $insertStop->execute([
            $deviceId,
            $commandId
        ]);


        $pdo->commit();

        stopWaterReply(
            true,
            "Stop command queued: " . $commandId,
            200
        );

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        stopWaterReply(
            false,
            "Stop Watering error: " . $e->getMessage(),
            500
        );
    }
}


// =====================================================
// CLEAR STUCK IRRIGATION COMMANDS API
// =====================================================

if (
    isset($_GET["action"])
    &&
    $_GET["action"] === "clear_irrigation_commands"
) {

    header("Content-Type: text/plain; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

    function clearCommandsReply(
        bool $success,
        string $message,
        int $statusCode = 200
    ): never {

        http_response_code($statusCode);

        $message = str_replace(
            ["\r", "\n", "|"],
            [" ", " ", "/"],
            $message
        );

        echo ($success ? "OK|" : "ERROR|") . $message;
        exit;
    }


    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        clearCommandsReply(false, "POST request required.", 405);
    }


    try {

        $deviceId =
            isset($_POST["device_id"])
                ? (int)$_POST["device_id"]
                : 0;

        $csrfToken =
            isset($_POST["csrf_token"])
                ? (string)$_POST["csrf_token"]
                : "";


        if (
            empty($_SESSION["csrf_token"])
            ||
            !hash_equals(
                $_SESSION["csrf_token"],
                $csrfToken
            )
        ) {
            clearCommandsReply(false, "Invalid security token.", 403);
        }


        if ($deviceId <= 0) {
            clearCommandsReply(false, "Invalid device.", 422);
        }


        $deviceStmt = $pdo->prepare(
            "SELECT id
             FROM devices
             WHERE id = ?
             AND user_id = ?
             LIMIT 1"
        );

        $deviceStmt->execute([
            $deviceId,
            $userId
        ]);


        if (!$deviceStmt->fetchColumn()) {
            clearCommandsReply(false, "Device not found.", 404);
        }


        /*
         * Do not physically delete command history.
         * Remove only commands that are still considered active by the dashboard.
         */
        $clearStmt = $pdo->prepare(
            "UPDATE irrigation_commands
             SET
                status = 'stopped',
                completed_at = NOW(),
                error_message = 'Cleared manually from dashboard'
             WHERE device_id = ?
             AND status IN (
                'pending',
                'sent',
                'accepted',
                'started'
             )"
        );

        $clearStmt->execute([
            $deviceId
        ]);

        $cleared = $clearStmt->rowCount();

        clearCommandsReply(
            true,
            $cleared === 1
                ? "1 stuck command cleared."
                : $cleared . " stuck commands cleared.",
            200
        );

    } catch (Throwable $e) {

        clearCommandsReply(
            false,
            "Clear commands error: " . $e->getMessage(),
            500
        );
    }
}


// =====================================================
// LATEST SENSOR READING API
// =====================================================

if (
    isset($_GET["action"])
    &&
    $_GET["action"] === "latest_reading"
) {

    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

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


    try {

        $deviceStmt = $pdo->prepare(
            "SELECT id
             FROM devices
             WHERE id = ?
             AND user_id = ?
             LIMIT 1"
        );

        $deviceStmt->execute([
            $deviceId,
            $userId
        ]);


        if (!$deviceStmt->fetchColumn()) {

            http_response_code(404);

            echo json_encode([
                "success" => false,
                "message" => "Device not found."
            ]);

            exit;
        }


        $latestStmt = $pdo->prepare(
            "SELECT
                temperature,
                humidity,
                soil_moisture,
                light_level,
                recorded_at
             FROM sensor_readings
             WHERE device_id = ?
             ORDER BY recorded_at DESC
             LIMIT 1"
        );

        $latestStmt->execute([
            $deviceId
        ]);

        $reading = $latestStmt->fetch(
            PDO::FETCH_ASSOC
        );


        echo json_encode([
            "success" => true,
            "reading" => $reading ?: null
        ]);

    } catch (Throwable $e) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" =>
                "Latest reading database error: "
                .
                $e->getMessage()
        ]);
    }

    exit;
}


// =====================================================
// SENSOR READINGS API
// =====================================================


if (
    isset($_GET["action"])
    &&
    $_GET["action"] === "readings"
) {

    header("Content-Type: application/json; charset=utf-8");

    $deviceId =
        isset($_GET["device_id"])
            ? (int)$_GET["device_id"]
            : 0;


    $range =
        isset($_GET["range"])
            ? (string)$_GET["range"]
            : "today";


    $allowedRanges = [
        "today",
        "yesterday",
        "7days",
        "30days"
    ];


    if (
        $deviceId <= 0
        ||
        !in_array(
            $range,
            $allowedRanges,
            true
        )
    ) {

        http_response_code(422);

        echo json_encode([
            "success" => false,
            "message" => "Invalid readings request."
        ]);

        exit;
    }


    try {
        require_once __DIR__ . '/reading-history.php';
        echo json_encode(ps_history($pdo, $userId, $deviceId, $range));
    } catch (DomainException $error) {
        http_response_code(404);
        echo json_encode(['success' => false,'message' => 'Device not found.']);
    } catch (Throwable $error) {
        error_log('History API: '.$error->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false,'message' => 'Unable to load readings. Please try again.']);
    }

    exit;
}


// =====================================================
// IRRIGATION RULE API
// =====================================================

if (
    isset($_GET["action"])
    &&
    $_GET["action"] === "irrigation_rule"
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


    try {

        $ruleStmt =
            $pdo->prepare(
                "SELECT r.*
                 FROM irrigation_rules r
                 INNER JOIN devices d
                    ON d.id = r.device_id
                 WHERE r.device_id = ?
                 AND d.user_id = ?
                 LIMIT 1"
            );


        $ruleStmt->execute([
            $deviceId,
            $userId
        ]);


        $row =
            $ruleStmt->fetch(
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

    } catch (Throwable $e) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" =>
                "Irrigation rule database error: "
                .
                $e->getMessage()
        ]);
    }

    exit;
}

// =====================================================
// SAVE IRRIGATION RULE API
// =====================================================

if (
    isset($_GET["action"])
    &&
    $_GET["action"] === "save_irrigation_rule"
) {

    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");


    if ($_SERVER["REQUEST_METHOD"] !== "POST") {

        http_response_code(405);

        echo json_encode([
            "success" => false,
            "message" => "POST request required."
        ]);

        exit;
    }


    try {

        $rawInput =
            file_get_contents("php://input");

        $payload =
            json_decode(
                $rawInput,
                true
            );


        if (!is_array($payload)) {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "Invalid JSON request."
            ]);

            exit;
        }


        $requestCsrf =
            isset($payload["csrf_token"])
                ? (string)$payload["csrf_token"]
                : "";


        if (
            empty($_SESSION["csrf_token"])
            ||
            !hash_equals(
                $_SESSION["csrf_token"],
                $requestCsrf
            )
        ) {

            http_response_code(403);

            echo json_encode([
                "success" => false,
                "message" => "Invalid security token."
            ]);

            exit;
        }


        $deviceId =
            isset($payload["device_id"])
                ? (int)$payload["device_id"]
                : 0;

        $enabled =
            !empty($payload["enabled"])
                ? 1
                : 0;

        $logic =
            isset($payload["logic"])
                ? strtoupper(
                    trim(
                        (string)$payload["logic"]
                    )
                )
                : "AND";

        $durationSeconds =
            isset($payload["duration_seconds"])
                ? (int)$payload["duration_seconds"]
                : 0;

        // 0 is valid and means "no cooldown".
        $cooldownMinutes =
            array_key_exists(
                "cooldown_minutes",
                $payload
            )
                ? (int)$payload["cooldown_minutes"]
                : 30;


        if ($deviceId <= 0) {

            http_response_code(422);

            echo json_encode([
                "success" => false,
                "message" => "Invalid device."
            ]);

            exit;
        }


        if (
            !in_array(
                $logic,
                ["AND", "OR"],
                true
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "success" => false,
                "message" => "Invalid condition logic."
            ]);

            exit;
        }


        if (
            $durationSeconds < 1
            ||
            $durationSeconds > 600
        ) {

            http_response_code(422);

            echo json_encode([
                "success" => false,
                "message" => "Watering duration must be between 1 and 600 seconds."
            ]);

            exit;
        }


        if (
            $cooldownMinutes < 0
            ||
            $cooldownMinutes > 1440
        ) {

            http_response_code(422);

            echo json_encode([
                "success" => false,
                "message" => "Cooldown must be between 0 and 1440 minutes."
            ]);

            exit;
        }


        $deviceStmt =
            $pdo->prepare(
                "SELECT id
                 FROM devices
                 WHERE id = ?
                 AND user_id = ?
                 LIMIT 1"
            );

        $deviceStmt->execute([
            $deviceId,
            $userId
        ]);


        if (!$deviceStmt->fetchColumn()) {

            http_response_code(404);

            echo json_encode([
                "success" => false,
                "message" => "Device not found."
            ]);

            exit;
        }


        $allowedOperators = [
            "<",
            ">",
            "="
        ];


        $readCondition =
            function (
                string $name,
                float $min,
                float $max
            ) use (
                $payload,
                $allowedOperators
            ): array {

                $condition =
                    isset($payload[$name])
                    &&
                    is_array($payload[$name])
                        ? $payload[$name]
                        : [];

                $conditionEnabled =
                    !empty($condition["enabled"])
                        ? 1
                        : 0;

                $operator =
                    isset($condition["operator"])
                        ? (string)$condition["operator"]
                        : "<";

                $value =
                    isset($condition["value"])
                        ? (float)$condition["value"]
                        : 0.0;


                if (
                    !in_array(
                        $operator,
                        $allowedOperators,
                        true
                    )
                ) {
                    throw new InvalidArgumentException(
                        "Invalid operator for " . $name . "."
                    );
                }


                if (
                    $value < $min
                    ||
                    $value > $max
                ) {
                    throw new InvalidArgumentException(
                        "Invalid value for " . $name . "."
                    );
                }


                return [
                    "enabled" => $conditionEnabled,
                    "operator" => $operator,
                    "value" => $value
                ];
            };


        try {

            $soil =
                $readCondition(
                    "soil",
                    0,
                    100
                );

            $light =
                $readCondition(
                    "light",
                    0,
                    100
                );

            $temperature =
                $readCondition(
                    "temperature",
                    -50,
                    100
                );

            $humidity =
                $readCondition(
                    "humidity",
                    0,
                    100
                );

        } catch (InvalidArgumentException $validationError) {

            http_response_code(422);

            echo json_encode([
                "success" => false,
                "message" =>
                    $validationError->getMessage()
            ]);

            exit;
        }


        $activeConditionCount =
            $soil["enabled"]
            +
            $light["enabled"]
            +
            $temperature["enabled"]
            +
            $humidity["enabled"];


        if (
            $enabled === 1
            &&
            $activeConditionCount === 0
        ) {

            http_response_code(422);

            echo json_encode([
                "success" => false,
                "message" => "Enable at least one sensor condition."
            ]);

            exit;
        }


        $existingRuleStmt =
            $pdo->prepare(
                "SELECT id
                 FROM irrigation_rules
                 WHERE device_id = ?
                 LIMIT 1"
            );

        $existingRuleStmt->execute([
            $deviceId
        ]);

        $existingRuleId =
            $existingRuleStmt->fetchColumn();


        if ($existingRuleId) {

            $saveStmt =
                $pdo->prepare(
                    "UPDATE irrigation_rules
                     SET
                        enabled = ?,
                        logic_mode = ?,
                        duration_seconds = ?,
                        cooldown_minutes = ?,
                        soil_enabled = ?,
                        soil_operator = ?,
                        soil_value = ?,
                        light_enabled = ?,
                        light_operator = ?,
                        light_value = ?,
                        temperature_enabled = ?,
                        temperature_operator = ?,
                        temperature_value = ?,
                        humidity_enabled = ?,
                        humidity_operator = ?,
                        humidity_value = ?
                     WHERE id = ?
                     AND device_id = ?"
                );

            $saveStmt->execute([
                $enabled,
                $logic,
                $durationSeconds,
                $cooldownMinutes,

                $soil["enabled"],
                $soil["operator"],
                $soil["value"],

                $light["enabled"],
                $light["operator"],
                $light["value"],

                $temperature["enabled"],
                $temperature["operator"],
                $temperature["value"],

                $humidity["enabled"],
                $humidity["operator"],
                $humidity["value"],

                (int)$existingRuleId,
                $deviceId
            ]);

        } else {

            $saveStmt =
                $pdo->prepare(
                    "INSERT INTO irrigation_rules (
                        device_id,
                        enabled,
                        logic_mode,
                        duration_seconds,
                        cooldown_minutes,
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
                        humidity_value
                     )
                     VALUES (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                     )"
                );

            $saveStmt->execute([
                $deviceId,
                $enabled,
                $logic,
                $durationSeconds,
                $cooldownMinutes,

                $soil["enabled"],
                $soil["operator"],
                $soil["value"],

                $light["enabled"],
                $light["operator"],
                $light["value"],

                $temperature["enabled"],
                $temperature["operator"],
                $temperature["value"],

                $humidity["enabled"],
                $humidity["operator"],
                $humidity["value"]
            ]);
        }


        echo json_encode([
            "success" => true,
            "message" => "Automatic watering rule saved."
        ]);

    } catch (Throwable $e) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" =>
                "Save irrigation rule error: "
                .
                $e->getMessage()
        ]);
    }

    exit;
}


// =====================================================
// UNKNOWN ACTION
// =====================================================

http_response_code(400);

header("Content-Type: application/json; charset=utf-8");

echo json_encode([
    "success" => false,
    "message" => "Unknown dashboard action."
]);

exit;
