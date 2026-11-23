<?php

require_once __DIR__.'/device-api.php';
$data = ps_device_payload();
[$uid,$key] = ps_device_credentials($data);
try {
    $db = ps_db();
    $device = ps_device_auth($db, $uid, $key);
    $q = $db->prepare('UPDATE devices SET last_seen=NOW() WHERE id=?');
    $q->execute([$device['id']]);
    if ($device['user_id'] === null || empty($device['mqtt_topic'])) {
        ps_device_reply(200, ['success' => true,'claimed' => false,'message' => 'Device waiting for account connection']);
    }
    ps_device_reply(200, ['success' => true,'claimed' => true,'device' => ['id' => (int)$device['id'],'name' => $device['device_name'],'uid' => $device['device_uid']],'mqtt' => ['topic' => $device['mqtt_topic']]]);
} catch (Throwable $e) {
    error_log('Plant device config failed: '.$e->getMessage());
    ps_device_reply(500, ['success' => false,'message' => 'Unable to load device configuration']);
}
