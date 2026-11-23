<?php

if (!defined('PS_AUTH_API')) {
    define('PS_AUTH_API', true);
}
require_once __DIR__.'/auth-bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false,'message' => 'Not authenticated']);
    exit;
}
$id = (int)($_GET['device_id'] ?? 0);
$range = (string)($_GET['range'] ?? 'today');
$uid = (int)$_SESSION['user_id'];
session_write_close();
try {
    require_once __DIR__.'/reading-history.php';
    echo json_encode(ps_history(ps_db(), $uid, $id, $range));
} catch (DomainException $e) {
    http_response_code(404);
    echo json_encode(['success' => false,'message' => 'Device not found']);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false,'message' => 'Invalid range']);
} catch (Throwable $e) {
    error_log('History: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false,'message' => 'Unable to load readings']);
}
