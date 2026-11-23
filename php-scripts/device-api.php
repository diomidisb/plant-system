<?php

/** Shared device HTTP protocol. Device Keys are never logged or returned. */
define('PS_AUTH_API', true);
require_once __DIR__.'/auth-functions.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function ps_device_reply(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
function ps_device_payload(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        ps_device_reply(405, ['success' => false,'message' => 'Method not allowed']);
    }
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) {
        ps_device_reply(413, ['success' => false,'message' => 'Request too large']);
    }
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if ($raw === false || strlen($raw) > 16384) {
        ps_device_reply(413, ['success' => false,'message' => 'Request too large']);
    }
    try {
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        ps_device_reply(400, ['success' => false,'message' => 'Invalid JSON']);
    }
    if (!is_array($data) || array_is_list($data)) {
        ps_device_reply(400, ['success' => false,'message' => 'JSON object required']);
    }
    return $data;
}
function ps_device_credentials(array $data): array
{
    $uid = $data['device_uid'] ?? null;
    $key = $data['device_key'] ?? $data['mqtt_key'] ?? null;
    if (!is_string($uid) || !is_string($key)) {
        ps_device_reply(400, ['success' => false,'message' => 'device_uid and device_key are required']);
    }
    $uid = trim($uid);
    $key = trim($key);
    if ($uid === '' || strlen($uid) > 100 || $key === '' || strlen($key) > 250) {
        ps_device_reply(400, ['success' => false,'message' => 'Invalid device credentials']);
    }
    return [$uid,$key];
}
function ps_device_auth(PDO $db, string $uid, string $key): array
{
    $q = $db->prepare('SELECT * FROM devices WHERE device_uid=? LIMIT 1');
    $q->execute([$uid]);
    $device = $q->fetch();
    if (!$device || !hash_equals((string)$device['mqtt_key'], $key)) {
        ps_device_reply(401, ['success' => false,'message' => 'Invalid device credentials']);
    }
    return $device;
}
function ps_sensor_value(array $data, string $key, float $min, float $max): ?float
{
    if (!isset($data[$key])) {
        return null;
    }
    $value = $data[$key];
    if (is_bool($value) || !is_numeric($value) || !is_finite((float)$value) || (float)$value < $min || (float)$value > $max) {
        ps_device_reply(422, ['success' => false,'message' => 'Invalid sensor value: '.$key]);
    }
    return (float)$value;
}
