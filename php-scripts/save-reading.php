<?php

require_once __DIR__.'/device-api.php';
$data = ps_device_payload();
[$uid,$key] = ps_device_credentials($data);
try {
    $db = ps_db();
    $temperature = ps_sensor_value($data, 'temperature', -50, 100);
    $humidity = ps_sensor_value($data, 'humidity', 0, 100);
    $soil = ps_sensor_value($data, 'soil_moisture', 0, 100);
    if (!array_key_exists('light_level', $data)) {
        $data['light_level'] = $data['light'] ?? null;
    }
    $light = ps_sensor_value($data, 'light_level', 0, 100);
    if ($temperature === null && $humidity === null && $soil === null && $light === null) {
        ps_device_reply(422, ['success' => false,'message' => 'At least one measurement is required']);
    }
    $db->beginTransaction();
    // Match account-transfer/deletion lock order: user then device.
    $device = ps_device_auth($db, $uid, $key);
    if ($device['user_id'] === null) {
        $db->rollBack();
        ps_device_reply(403, ['success' => false,'message' => 'Device is not claimed']);
    }
    $q = $db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
    $q->execute([$device['user_id']]);
    $q = $db->prepare('SELECT * FROM devices WHERE id=? FOR UPDATE');
    $q->execute([$device['id']]);
    $fresh = $q->fetch();
    if (!$fresh || $fresh['user_id'] !== $device['user_id'] || !hash_equals((string)$fresh['mqtt_key'], $key)) {
        $db->rollBack();
        ps_device_reply(409, ['success' => false,'message' => 'Device ownership changed. Refresh configuration.']);
    }
    $q = $db->prepare('INSERT INTO sensor_readings(device_id,temperature,humidity,soil_moisture,light_level) VALUES(?,?,?,?,?)');
    $q->execute([$fresh['id'],$temperature,$humidity,$soil,$light]);
    $q = $db->prepare('UPDATE devices SET last_seen=NOW(),is_online=1 WHERE id=?');
    $q->execute([$fresh['id']]);
    $db->commit();
    ps_device_reply(200, ['success' => true,'device_id' => (int)$fresh['id'],'user_id' => (int)$fresh['user_id'],'message' => 'Reading saved']);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Plant HTTP reading failed: '.$e->getMessage());
    ps_device_reply(500,['success' => false,'message' => 'Unable to save reading']);
}
