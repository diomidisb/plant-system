<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

function workerLog(string $message): void
{
    fwrite(STDERR, '[' . date('c') . '] ' . $message . PHP_EOL);
}

function notifySystemd(string $state): void
{
    $address = getenv('NOTIFY_SOCKET');
    if (!$address) {
        return;
    }
    if (!extension_loaded('sockets')) {
        throw new RuntimeException('PHP sockets extension is required for the watchdog.');
    }
    if ($address[0] === '@') {
        $address = "\0" . substr($address, 1);
    }
    $socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
    if ($socket === false) {
        throw new RuntimeException('Cannot create watchdog socket.');
    }
    socket_sendto($socket, $state, strlen($state), 0, $address);
    socket_close($socket);
}

function openDatabase(): PDO
{
    require __DIR__ . '/db.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('db.php must provide PDO.');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $pdo;
}

$host = getenv('MQTT_HOST') ?: 'ra812163.ala.us-east-1.emqxsl.com';
$port = (int) (getenv('MQTT_PORT') ?: 8883);
$username = getenv('MQTT_USERNAME');
$password = getenv('MQTT_PASSWORD');
if (!$username || !$password) {
    throw new RuntimeException('Set MQTT_USERNAME and MQTT_PASSWORD in the service environment file.');
}

// Prevent a second local worker even when started manually.
$lock = fopen(getenv('WORKER_LOCK') ?: '/run/plant-mqtt/worker.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    throw new RuntimeException('Another unified worker is already running, or lock file is not writable.');
}

$running = true;
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    foreach ([SIGTERM, SIGINT] as $signal) {
        pcntl_signal($signal, function () use (&$running): void {
            $running = false;
        });
    }
}

$settings = (new ConnectionSettings())
    ->setUsername($username)
    ->setPassword($password)
    ->setUseTls(true)
    ->setTlsVerifyPeer(true)
    ->setTlsVerifyPeerName(true)
    ->setTlsSelfSignedAllowed(false)
    ->setKeepAliveInterval(30)
    ->setConnectTimeout(10)
    ->setSocketTimeout(5);

notifySystemd("READY=1\nSTATUS=Connecting to MQTT");

function logLine(string $message): void
{
    if (getenv('WORKER_DEBUG') === '1' || preg_match('/ERROR|LOST|RESPONSE|SENT|CONNECTED/i', $message)) {
        workerLog($message);
    }
}

function compareValue(
    float $actual,
    string $operator,
    float $threshold
): bool {

    return match ($operator) {

        "<" =>
            $actual < $threshold,

        ">" =>
            $actual > $threshold,

        "=" =>
            abs(
                $actual
                -
                $threshold
            ) < 0.01,

        default =>
            false
    };
}


function commandStatusIsFinal(
    string $status
): bool {

    return in_array(
        $status,
        [
            "completed",
            "stopped",
            "failed",
            "rejected"
        ],
        true
    );
}


// =====================================================
// CREATE AUTOMATIC COMMANDS FROM RULES
// =====================================================

function evaluateAutomaticRules(
    PDO $pdo
): void {

    static $ruleCursor = 0;
    $sql =
        "SELECT
            r.*,
            d.user_id,
            d.device_uid,
            d.mqtt_topic,
            d.is_online,
            CASE
                WHEN r.last_triggered_at IS NULL THEN 0
                ELSE GREATEST(
                    0,
                    TIMESTAMPDIFF(
                        SECOND,
                        NOW(),
                        DATE_ADD(
                            r.last_triggered_at,
                            INTERVAL r.cooldown_minutes MINUTE
                        )
                    )
                )
            END AS cooldown_remaining_seconds
         FROM irrigation_rules r
         INNER JOIN devices d
            ON d.id = r.device_id
         WHERE r.enabled = 1
           AND d.is_online = 1
           AND d.user_id IS NOT NULL
           AND d.mqtt_topic IS NOT NULL
           AND r.id > " . (int) $ruleCursor . "
         ORDER BY r.id ASC
         LIMIT 50";


    $rules =
        $pdo
        ->query($sql)
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


    if (!$rules) {
        $ruleCursor = 0;
        return;
    }
    $ruleCursor = (int) $rules[array_key_last($rules)]['id'];

    static $lastRuleCountLog = 0;

    if (
        time() - $lastRuleCountLog
        >=
        15
    ) {

        logLine(
            "AUTO enabled rules: "
            .
            count($rules)
        );

        $lastRuleCountLog =
            time();
    }


    foreach (
        $rules as $rule
    ) {

        $deviceId =
            (int)$rule["device_id"];


        logLine(
            "AUTO rule "
            .
            $rule["device_uid"]
            .
            ": cooldown="
            .
            (int)$rule["cooldown_minutes"]
            .
            " min"
        );


        // ---------------------------------------------
        // DEVICE MUST BE ONLINE
        // ---------------------------------------------

        if (
            (int)$rule["is_online"]
            !==
            1
        ) {

            logLine(
                "AUTO skip "
                .
                $rule["device_uid"]
                .
                ": device offline"
            );

            continue;
        }


        if (
            empty($rule["mqtt_topic"])
        ) {

            logLine(
                "AUTO skip "
                .
                $rule["device_uid"]
                .
                ": mqtt_topic is empty"
            );

            continue;
        }


        // ---------------------------------------------
        // COOLDOWN
        // ---------------------------------------------
        // Important: calculated by MySQL, not PHP.
        // This avoids timezone differences between
        // PHP date()/time() and MySQL NOW().

        $cooldownRemaining =
            isset($rule["cooldown_remaining_seconds"])
                ? (int)$rule["cooldown_remaining_seconds"]
                : 0;


        if (
            $cooldownRemaining
            >
            0
        ) {

            logLine(
                "AUTO skip "
                .
                $rule["device_uid"]
                .
                ": cooldown active, remaining="
                .
                $cooldownRemaining
                .
                " sec"
            );

            continue;
        }


        // ---------------------------------------------
        // CLEAN UP STALE ACTIVE COMMANDS
        // ---------------------------------------------
        //
        // A lost ESP response must not block automatic
        // irrigation forever.
        //
        // pending/sent/accepted:
        //   fail after 90 seconds
        //
        // started:
        //   fail after expected duration + 120 seconds
        //
        $cleanupStmt =
            $pdo->prepare(
                "UPDATE irrigation_commands
                 SET
                    status = 'failed',
                    completed_at = NOW(),
                    error_message = 'Command timeout / missing ESP final response'
                 WHERE device_id = ?
                 AND (
                    (
                        status IN ('pending', 'sent', 'accepted')
                        AND TIMESTAMPDIFF(
                            SECOND,
                            COALESCE(sent_at, created_at),
                            NOW()
                        ) > 90
                    )
                    OR
                    (
                        status = 'started'
                        AND TIMESTAMPDIFF(
                            SECOND,
                            COALESCE(started_at, sent_at, created_at),
                            NOW()
                        ) > (
                            COALESCE(duration_seconds, 0)
                            + 120
                        )
                    )
                 )"
            );


        $cleanupStmt->execute([
            $deviceId
        ]);


        if (
            $cleanupStmt->rowCount() > 0
        ) {

            logLine(
                "AUTO cleanup "
                .
                $rule["device_uid"]
                .
                ": cleared "
                .
                $cleanupStmt->rowCount()
                .
                " stale command(s)"
            );
        }


        // ---------------------------------------------
        // DO NOT CREATE ANOTHER ACTIVE COMMAND
        // ---------------------------------------------

        $activeStmt =
            $pdo->prepare(
                "SELECT
                    command_id,
                    command_type,
                    status,
                    duration_seconds,
                    created_at,
                    sent_at,
                    started_at,
                    TIMESTAMPDIFF(
                        SECOND,
                        COALESCE(started_at, sent_at, created_at),
                        NOW()
                    ) AS age_seconds
                 FROM irrigation_commands
                 WHERE device_id = ?
                 AND status IN (
                    'pending',
                    'sent',
                    'accepted',
                    'started'
                 )
                 ORDER BY id DESC
                 LIMIT 1"
            );


        $activeStmt->execute([
            $deviceId
        ]);


        $activeCommand =
            $activeStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            $activeCommand
        ) {

            logLine(
                "AUTO skip "
                .
                $rule["device_uid"]
                .
                ": active command "
                .
                $activeCommand["command_id"]
                .
                " type="
                .
                $activeCommand["command_type"]
                .
                " status="
                .
                $activeCommand["status"]
                .
                " age="
                .
                (int)$activeCommand["age_seconds"]
                .
                "s"
            );

            continue;
        }


        // ---------------------------------------------
        // LATEST SENSOR VALUES
        // ---------------------------------------------

        $readingStmt =
            $pdo->prepare(
                "SELECT
                    temperature,
                    humidity,
                    soil_moisture,
                    light_level,
                    recorded_at,
                    TIMESTAMPDIFF(
                        SECOND,
                        recorded_at,
                        NOW()
                    ) AS age_seconds
                 FROM sensor_readings
                 WHERE device_id = ?
                 ORDER BY recorded_at DESC
                 LIMIT 1"
            );


        $readingStmt->execute([
            $deviceId
        ]);


        $reading =
            $readingStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$reading) {

            continue;
        }


        // Do not automate based on old sensor data.
        // Age is calculated by MySQL itself to avoid PHP/MySQL
        // timezone differences.
        $readingAge =
            isset($reading["age_seconds"])
                ? (int)$reading["age_seconds"]
                : 999999;


        if (
            $readingAge < 0
            ||
            $readingAge > 60
        ) {

            logLine(
                "AUTO skip "
                .
                $rule["device_uid"]
                .
                ": latest reading age="
                .
                $readingAge
                .
                " sec"
            );

            continue;
        }


        // ---------------------------------------------
        // BUILD ENABLED CONDITIONS + DEBUG OUTPUT
        // ---------------------------------------------

        $results = [];
        $conditionDebug = [];


        if (
            (int)$rule["soil_enabled"]
            ===
            1
        ) {

            $actual =
                (float)$reading["soil_moisture"];

            $target =
                (float)$rule["soil_value"];

            $result =
                compareValue(
                    $actual,
                    $rule["soil_operator"],
                    $target
                );

            $results[] =
                $result;

            $conditionDebug[] =
                "SOIL "
                .
                $actual
                .
                " "
                .
                $rule["soil_operator"]
                .
                " "
                .
                $target
                .
                " => "
                .
                ($result ? "TRUE" : "FALSE");
        }


        if (
            (int)$rule["light_enabled"]
            ===
            1
        ) {

            $actual =
                (float)$reading["light_level"];

            $target =
                (float)$rule["light_value"];

            $result =
                compareValue(
                    $actual,
                    $rule["light_operator"],
                    $target
                );

            $results[] =
                $result;

            $conditionDebug[] =
                "LIGHT "
                .
                $actual
                .
                " "
                .
                $rule["light_operator"]
                .
                " "
                .
                $target
                .
                " => "
                .
                ($result ? "TRUE" : "FALSE");
        }


        if (
            (int)$rule["temperature_enabled"]
            ===
            1
        ) {

            $actual =
                (float)$reading["temperature"];

            $target =
                (float)$rule["temperature_value"];

            $result =
                compareValue(
                    $actual,
                    $rule["temperature_operator"],
                    $target
                );

            $results[] =
                $result;

            $conditionDebug[] =
                "TEMP "
                .
                $actual
                .
                " "
                .
                $rule["temperature_operator"]
                .
                " "
                .
                $target
                .
                " => "
                .
                ($result ? "TRUE" : "FALSE");
        }


        if (
            (int)$rule["humidity_enabled"]
            ===
            1
        ) {

            $actual =
                (float)$reading["humidity"];

            $target =
                (float)$rule["humidity_value"];

            $result =
                compareValue(
                    $actual,
                    $rule["humidity_operator"],
                    $target
                );

            $results[] =
                $result;

            $conditionDebug[] =
                "HUMIDITY "
                .
                $actual
                .
                " "
                .
                $rule["humidity_operator"]
                .
                " "
                .
                $target
                .
                " => "
                .
                ($result ? "TRUE" : "FALSE");
        }


        if (
            count($conditionDebug)
            >
            0
        ) {

            logLine(
                "AUTO check "
                .
                $rule["device_uid"]
                .
                ": "
                .
                implode(
                    " | ",
                    $conditionDebug
                )
                .
                " | logic="
                .
                strtoupper(
                    (string)$rule["logic_mode"]
                )
                .
                " | age="
                .
                $readingAge
                .
                "s"
            );
        }


        // Invalid rule: no enabled conditions.
        if (
            count($results)
            ===
            0
        ) {

            logLine(
                "AUTO skip "
                .
                $rule["device_uid"]
                .
                ": rule has no enabled sensor conditions"
            );

            continue;
        }


        // ---------------------------------------------
        // AND / OR
        // ---------------------------------------------

        $logic =
            strtoupper(
                (string)$rule["logic_mode"]
            );


        if (
            $logic
            ===
            "OR"
        ) {

            $matched =
                in_array(
                    true,
                    $results,
                    true
                );

        } else {

            $matched =
                !in_array(
                    false,
                    $results,
                    true
                );
        }


        if (!$matched) {

            continue;
        }


        // ---------------------------------------------
        // CREATE PENDING AUTO WATER COMMAND
        // ---------------------------------------------

        $commandId =
            "auto_"
            .
            bin2hex(
                random_bytes(12)
            );


        try {

            $pdo->beginTransaction();


            // Re-check active commands inside transaction.
            $lockStmt =
                $pdo->prepare(
                    "SELECT id
                     FROM irrigation_commands
                     WHERE device_id = ?
                     AND status IN (
                        'pending',
                        'sent',
                        'accepted',
                        'started'
                     )
                     LIMIT 1
                     FOR UPDATE"
                );


            $lockStmt->execute([
                $deviceId
            ]);


            if (
                $lockStmt->fetchColumn()
            ) {

                $pdo->rollBack();

                continue;
            }


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
                (int)$rule["duration_seconds"]
            ]);


            $updateRuleStmt =
                $pdo->prepare(
                    "UPDATE irrigation_rules
                     SET last_triggered_at = NOW()
                     WHERE device_id = ?"
                );


            $updateRuleStmt->execute([
                $deviceId
            ]);


            $pdo->commit();


            logLine(
                "AUTO rule matched for device "
                .
                $rule["device_uid"]
                .
                " -> created "
                .
                $commandId
            );

        } catch (Throwable $e) {
            if ($e instanceof PDOException) {
                throw $e;
            }

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            logLine(
                "AUTO rule DB error: "
                .
                $e->getMessage()
            );
        }
    }
}


// =====================================================
// PUBLISH PENDING COMMANDS
// =====================================================

function publishPendingCommands(
    PDO $pdo,
    MqttClient $mqtt
): void {

    $sql =
        "SELECT
            c.id,
            c.command_id,
            c.command_type,
            c.duration_seconds,
            d.device_uid,
            d.mqtt_topic,
            d.is_online
         FROM irrigation_commands c
         INNER JOIN devices d
            ON d.id = c.device_id
         WHERE c.status = 'pending'
         ORDER BY c.id ASC
         LIMIT 20";


    $commands =
        $pdo
        ->query($sql)
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


    foreach (
        $commands as $command
    ) {

        if (
            (int)$command["is_online"]
            !==
            1
        ) {

            continue;
        }


        $baseTopic =
            rtrim(
                (string)$command["mqtt_topic"],
                "/"
            );


        if (
            $baseTopic
            ===
            ""
        ) {

            continue;
        }


        $topic =
            $baseTopic
            .
            "/command";


        $payload = [
            "command_id" =>
                $command["command_id"],

            "command" =>
                $command["command_type"]
        ];


        if (
            $command["command_type"]
            ===
            "water"
        ) {

            $payload["duration"] =
                (int)$command["duration_seconds"];
        }


        $json =
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
            );


        try {

            // QoS 1: broker acknowledges delivery.
            // ESP code is idempotent by command_id.
            $mqtt->publish(
                $topic,
                $json,
                MqttClient::QOS_AT_LEAST_ONCE,
                false
            );


            $stmt =
                $pdo->prepare(
                    "UPDATE irrigation_commands
                     SET
                        status = 'sent',
                        sent_at = NOW()
                     WHERE id = ?
                     AND status = 'pending'"
                );


            $stmt->execute([
                (int)$command["id"]
            ]);


            logLine(
                "SENT "
                .
                $command["command_id"]
                .
                " -> "
                .
                $topic
                .
                " "
                .
                $json
            );

        } catch (Throwable $e) {
            if ($e instanceof PDOException) {
                throw $e;
            }

            logLine(
                "Publish failed for "
                .
                $command["command_id"]
                .
                ": "
                .
                $e->getMessage()
            );
        }
    }
}


// =====================================================
// PROCESS ESP RESPONSE
// =====================================================

function processCommandResponse(
    PDO $pdo,
    string $topic,
    string $message
): void {

    $data =
        json_decode(
            $message,
            true
        );


    if (
        !is_array($data)
    ) {

        logLine(
            "Invalid response JSON: "
            .
            $message
        );

        return;
    }


    $commandId =
        (string)(
            $data["command_id"]
            ??
            ""
        );


    $status =
        strtolower(
            (string)(
                $data["status"]
                ??
                ""
            )
        );


    $messageText =
        isset($data["message"])
            ? (string)$data["message"]
            : null;


    if (
        $commandId === ""
        ||
        $status === ""
    ) {

        return;
    }


    $allowedStatuses = [
        "accepted",
        "started",
        "completed",
        "stopped",
        "busy",
        "rejected",
        "failed"
    ];


    if (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {

        return;
    }


    // busy means this command did not start.
    // Mark failed so it does not remain active forever.
    $databaseStatus =
        $status === "busy"
            ? "failed"
            : $status;


    if (
        $databaseStatus
        ===
        "accepted"
    ) {

        $stmt =
            $pdo->prepare(
                "UPDATE irrigation_commands
                 SET status = 'accepted'
                 WHERE command_id = ?"
            );


        $stmt->execute([
            $commandId
        ]);

    } elseif (
        $databaseStatus
        ===
        "started"
    ) {

        $stmt =
            $pdo->prepare(
                "UPDATE irrigation_commands
                 SET
                    status = 'started',
                    started_at = COALESCE(
                        started_at,
                        NOW()
                    )
                 WHERE command_id = ?"
            );


        $stmt->execute([
            $commandId
        ]);

    } elseif (
        commandStatusIsFinal(
            $databaseStatus
        )
    ) {

        $stmt =
            $pdo->prepare(
                "UPDATE irrigation_commands
                 SET
                    status = ?,
                    completed_at = NOW(),
                    error_message = ?
                 WHERE command_id = ?"
            );


        $errorMessage =
            in_array(
                $databaseStatus,
                [
                    "failed",
                    "rejected"
                ],
                true
            )
                ? (
                    $messageText
                    ??
                    $status
                )
                : null;


        $stmt->execute([
            $databaseStatus,
            $errorMessage,
            $commandId
        ]);
    }


    logLine(
        "RESPONSE "
        .
        $commandId
        .
        " = "
        .
        $status
        .
        (
            $messageText
            ? " (" . $messageText . ")"
            : ""
        )
    );
}



$delay = 1;
$readingsTopic = 'plantsystem/user/+/device/+/readings';
$statusTopic = 'plantsystem/user/+/device/+/status';
while ($running) {
    $mqtt = null;
    try {
        $pdo = openDatabase();
        $mqtt = new MqttClient($host, $port, getenv('MQTT_CLIENT_ID') ?: 'plantsystem-unified-primary');
        $mqtt->connect($settings, true);
        $mqtt->subscribe(
            $readingsTopic,
            function (
                string $topic,
                string $message,
                bool $retained = false
            ) use ($pdo) {
                if ($retained) {
                    return;
                }
                if (strlen($message) > 8192) {
                    return;
                }















                // =================================================
                // TOPIC VALIDATION
                // =================================================

                // Expected:
                //
                // plantsystem
                // /user
                // /13
                // /device
                // /ESP32-XXXX
                // /readings

                $parts =
                    explode(
                        "/",
                        $topic
                    );


                if (
                    count($parts) !== 6
                ) {



                    return;
                }


                if (
                    $parts[0] !== "plantsystem"
                    ||
                    $parts[1] !== "user"
                    ||
                    $parts[3] !== "device"
                    ||
                    $parts[5] !== "readings"
                ) {



                    return;
                }


                $userId =
                    (int)$parts[2];


                $topicDeviceUid =
                    $parts[4];


                // =================================================
                // JSON
                // =================================================

                $data =
                    json_decode(
                        $message,
                        true
                    );


                if (
                    !is_array($data)
                ) {



                    return;
                }


                // =================================================
                // DEVICE UID
                // =================================================

                if (
                    empty(
                        $data["device_uid"]
                    )
                ) {



                    return;
                }


                $payloadDeviceUid =
                    trim(
                        $data["device_uid"]
                    );


                // Topic UID must equal payload UID
                if (
                    $payloadDeviceUid
                    !==
                    $topicDeviceUid
                ) {



                    return;
                }


                // =================================================
                // FIND DEVICE
                // =================================================

                $stmt =
                    $pdo->prepare(
                        "
                SELECT
                    id,
                    user_id,
                    device_uid

                FROM devices

                WHERE
                    device_uid = ?

                LIMIT 1
                "
                    );


                $stmt->execute([
                    $payloadDeviceUid
                ]);


                $device =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (
                    !$device
                ) {



                    return;
                }


                // =================================================
                // DEVICE MUST BE CLAIMED
                // =================================================

                if (
                    $device["user_id"] === null
                ) {



                    return;
                }


                // =================================================
                // USER SECURITY CHECK
                // =================================================

                if (
                    (int)$device["user_id"]
                    !==
                    $userId
                ) {



                    return;
                }


                $deviceId =
                    (int)$device["id"];


                // =================================================
                // SENSOR VALUES
                // =================================================

                $temperature =
                    isset(
                        $data["temperature"]
                    )
                        ? (float)$data["temperature"]
                        : null;


                $humidity =
                    isset(
                        $data["humidity"]
                    )
                        ? (float)$data["humidity"]
                        : null;


                $soilMoisture =
                    isset(
                        $data["soil_moisture"]
                    )
                        ? (float)$data["soil_moisture"]
                        : null;


                $lightLevel =
                    isset(
                        $data["light"]
                    )
                        ? (float)$data["light"]
                        : null;


                // =================================================
                // INSERT SENSOR READING
                // =================================================

                try {

                    $stmt =
                        $pdo->prepare(
                            "
                    INSERT INTO sensor_readings
                    (
                        device_id,
                        temperature,
                        humidity,
                        soil_moisture,
                        light_level
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                    "
                        );


                    $stmt->execute([

                        $deviceId,

                        $temperature,

                        $humidity,

                        $soilMoisture,

                        $lightLevel

                    ]);


                    // =============================================
                    // UPDATE LAST SEEN
                    // =============================================

                    $stmt =
                        $pdo->prepare(
                            "
                    UPDATE devices

                    SET
                        last_seen = NOW()

                    WHERE
                        id = ?
                    "
                        );


                    $stmt->execute([
                        $deviceId
                    ]);































                } catch (Throwable $e) {
                    workerLog("Message processing failed: " . $e->getMessage());
                    throw $e;






                }



            },
            1
        );


        // =====================================================
        // ONLINE / OFFLINE STATUS SUBSCRIPTION
        // =====================================================

        $mqtt->subscribe(
            $statusTopic,
            function (
                string $topic,
                string $message,
                bool $retained = false
            ) use ($pdo) {
                if (strlen($message) > 8192) {
                    return;
                }

















                // =================================================
                // EXPECTED TOPIC:
                //
                // plantsystem/user/13/device/ESP32-XXXX/status
                // =================================================

                $parts =
                    explode(
                        "/",
                        $topic
                    );


                if (
                    count($parts) !== 6
                ) {



                    return;
                }


                if (
                    $parts[0] !== "plantsystem"
                    ||
                    $parts[1] !== "user"
                    ||
                    $parts[3] !== "device"
                    ||
                    $parts[5] !== "status"
                ) {



                    return;
                }


                // =================================================
                // USER + DEVICE
                // =================================================

                $userId =
                    (int)$parts[2];


                $deviceUid =
                    $parts[4];


                $status =
                    strtolower(
                        trim(
                            $message
                        )
                    );


                // =================================================
                // ONLINE
                // =================================================

                if (
                    $status === "online"
                ) {

                    try {

                        $stmt =
                            $pdo->prepare(
                                "
                        UPDATE devices

                        SET
                            is_online = 1,
                            last_seen = NOW()

                        WHERE
                            device_uid = ?
                        AND
                            user_id = ?
                        "
                            );


                        $stmt->execute([
                            $deviceUid,
                            $userId
                        ]);


                        if (
                            $stmt->rowCount() > 0
                        ) {















                        } else {






                        }


                    } catch (Throwable $e) {
                        workerLog("Message processing failed: " . $e->getMessage());
                        throw $e;






                    }
                }


                // =================================================
                // OFFLINE
                // =================================================

                elseif (
                    $status === "offline"
                ) {

                    try {

                        $stmt =
                            $pdo->prepare(
                                "
                        UPDATE devices

                        SET
                            is_online = 0

                        WHERE
                            device_uid = ?
                        AND
                            user_id = ?
                        "
                            );


                        $stmt->execute([
                            $deviceUid,
                            $userId
                        ]);

















                    } catch (Throwable $e) {
                        workerLog("Message processing failed: " . $e->getMessage());
                        throw $e;






                    }
                }


                // =================================================
                // UNKNOWN STATUS
                // =================================================

                else {






                }



            },
            1
        );



        $mqtt->subscribe('plantsystem/user/+/device/+/response', function (
            string $topic,
            string $message,
            bool $retained = false
        ) use ($pdo): void {
            if (!$retained && strlen($message) <= 8192) {
                processCommandResponse($pdo, $topic, $message);
            }
        }, MqttClient::QOS_AT_LEAST_ONCE);

        workerLog('MQTT connected; readings, status and responses subscribed.');
        notifySystemd('STATUS=Connected');
        $loopStartedAt = microtime(true);
        $lastRules = $lastPending = $lastHealth = 0.0;
        while ($running) {
            $mqtt->loopOnce($loopStartedAt, false, 50000);
            $now = microtime(true);
            if ($now - $lastRules >= 0.25) {
                evaluateAutomaticRules($pdo);
                $lastRules = microtime(true);
            }
            if ($now - $lastPending >= 0.5) {
                publishPendingCommands($pdo, $mqtt);
                $lastPending = microtime(true);
            }
            if ($now - $lastHealth >= 10) {
                $pdo->query('SELECT 1');
                notifySystemd("WATCHDOG=1\nSTATUS=Connected; database healthy");
                $lastHealth = $now;
            }
            if ($now - $loopStartedAt > 60) {
                $delay = 1;
            }
            usleep(20000);
        }
    } catch (Throwable $error) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        workerLog('Worker recovery: ' . $error->getMessage());
        notifySystemd('STATUS=Reconnecting');
    } finally {
        if ($mqtt instanceof MqttClient) {
            try {
                $mqtt->disconnect();
            } catch (Throwable $ignored) {
            }
        }
        unset($pdo);
    }
    if ($running) {
        $wait = min(30, $delay) + random_int(0, 2);
        for ($i = 0; $i < $wait && $running; $i++) {
            notifySystemd('WATCHDOG=1');
            sleep(1);
        }
        $delay = min(30, $delay * 2);
    }
}
notifySystemd('STOPPING=1');
workerLog('Worker stopped gracefully.');
