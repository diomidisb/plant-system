<?php

require_once __DIR__.'/device-api.php';
$data = ps_device_payload();
[$uid,$key] = ps_device_credentials($data);
try {
    $db = ps_db();
    if (!ps_rate('device-register-ip', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 120, 900)) {
        ps_device_reply(429, ['success' => false,'message' => 'Too many registration requests']);
    }
    $q = $db->prepare('SELECT 1 FROM permanently_deleted_devices WHERE device_uid=?');
    $q->execute([$uid]);
    if ($q->fetchColumn()) {
        ps_device_reply(403, ['success' => false,'message' => 'Device permanently deleted by administrator']);
    }
    $q = $db->prepare('SELECT * FROM devices WHERE device_uid=?');
    $q->execute([$uid]);
    $device = $q->fetch();
    if ($device) {
        if (!hash_equals((string)$device['mqtt_key'], $key)) {
            ps_device_reply(401, ['success' => false,'message' => 'Invalid device credentials']);
        }
        $q = $db->prepare('UPDATE devices SET last_seen=NOW() WHERE id=?');
        $q->execute([$device['id']]);
        ps_device_reply(200, ['success' => true,'registered' => true,'claimed' => $device['user_id'] !== null]);
    }
    $q = $db->prepare("INSERT INTO devices(user_id,device_name,device_uid,mqtt_key,mqtt_topic,last_seen) VALUES(NULL,'Unclaimed Device',?,?,NULL,NOW())");
    $q->execute([$uid,$key]);
    ps_device_reply(200, ['success' => true,'registered' => true,'claimed' => false]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        ps_device_reply(409, ['success' => false,'message' => 'Device UID or key already registered']);
    }
    error_log('Plant device registration failed: '.$e->getMessage());
    ps_device_reply(500, ['success' => false,'message' => 'Unable to register device']);
} catch (Throwable $e) {
    error_log('Plant device registration failed: '.$e->getMessage());
    ps_device_reply(500, ['success' => false,'message' => 'Unable to register device']);
}
