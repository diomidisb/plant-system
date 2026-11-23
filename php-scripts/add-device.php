<?php

declare(strict_types=1);
require_once __DIR__ . '/require-login.php';
$pdo = ps_db();

$userId = (int) $_SESSION['user_id'];
$currentPage = 'add-device.php';
$error = '';
$success = (string) ($_SESSION['device_manager_success'] ?? '');
unset($_SESSION['device_manager_success']);
$deviceName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $deviceName = trim((string) ($_POST['device_name'] ?? ''));
    $deviceId = filter_var($_POST['device_id'] ?? null, FILTER_VALIDATE_INT);
    try {
        if (!hash_equals(ps_csrf(), (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Your session expired. Refresh the page and try again.');
        }
        if (!in_array($action, ['add', 'edit', 'delete'], true)) {
            throw new RuntimeException('Invalid request.');
        }
        if ($action !== 'delete' && ($deviceName === '' || mb_strlen($deviceName) > 100)) {
            throw new RuntimeException('Enter a device name with 1–100 characters.');
        }
        $pdo->beginTransaction();
        if ($action === 'add') {
            $key = trim((string) ($_POST['device_key'] ?? ''));
            if ($key === '' || strlen($key) > 250) {
                throw new RuntimeException('Enter a valid Device Key.');
            }
            $stmt = $pdo->prepare('SELECT id, user_id, device_uid FROM devices WHERE mqtt_key = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([$key]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$device) {
                throw new RuntimeException('Device Key not found. Connect the device to Wi-Fi first.');
            }
            if ($device['user_id'] !== null) {
                throw new RuntimeException((int) $device['user_id'] === $userId ? 'This device is already in your account.' : 'This device is already connected to another account.');
            }
            $topic = 'plantsystem/user/' . $userId . '/device/' . $device['device_uid'];
            $stmt = $pdo->prepare('UPDATE devices SET user_id = ?, device_name = ?, mqtt_topic = ? WHERE id = ? AND user_id IS NULL');
            $stmt->execute([$userId, $deviceName, $topic, $device['id']]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('The device could not be connected. Try again.');
            }
            $message = 'Device connected successfully.';
        } else {
            if (!$deviceId || $deviceId < 1) {
                throw new RuntimeException('Invalid device.');
            }
            $stmt = $pdo->prepare('SELECT id FROM devices WHERE id = ? AND user_id = ? FOR UPDATE');
            $stmt->execute([$deviceId, $userId]);
            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('Device not found in your account.');
            }
            if ($action === 'edit') {
                $stmt = $pdo->prepare('UPDATE devices SET device_name = ? WHERE id = ? AND user_id = ?');
                $stmt->execute([$deviceName, $deviceId, $userId]);
                $message = 'Device name updated.';
            } else {
                if (($_POST['confirm_delete'] ?? '') !== 'yes') {
                    throw new RuntimeException('Confirm removal from your account first.');
                }
                $stmt = $pdo->prepare("SELECT id FROM irrigation_commands WHERE device_id = ? AND status IN ('sent', 'started') LIMIT 1 FOR UPDATE");
                $stmt->execute([$deviceId]);
                if ($stmt->fetchColumn()) {
                    throw new RuntimeException('Stop watering and wait for the device to confirm completion before deleting it. Online devices can be deleted.');
                }
                // Keep identity, but remove the previous owner's measurements.
                $stmt = $pdo->prepare('DELETE FROM sensor_readings WHERE device_id = ?');
                $stmt->execute([$deviceId]);
                $stmt = $pdo->prepare('UPDATE irrigation_rules SET enabled = 0 WHERE device_id = ?');
                $stmt->execute([$deviceId]);
                $stmt = $pdo->prepare("UPDATE irrigation_commands SET status = 'cancelled' WHERE device_id = ? AND status IN ('pending','queued')");
                $stmt->execute([$deviceId]);
                $stmt = $pdo->prepare('UPDATE devices SET user_id = NULL, mqtt_topic = NULL, is_online = 0 WHERE id = ? AND user_id = ?');
                $stmt->execute([$deviceId, $userId]);
                $message = 'Device removed from your account. Device Key retained; readings deleted and old irrigation rules disabled.';
            }
        }
        $pdo->commit();
        $_SESSION['device_manager_success'] = $message;
        header('Location: add-device.php', true, 303);
        exit;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof RuntimeException && !($exception instanceof PDOException)) {
            $error = $exception->getMessage();
        } else {
            error_log('Device manager: ' . $exception->getMessage());
            $error = 'Unable to save your changes. Please try again.';
        }
    }
}

$devices = [];
try {
    $stmt = $pdo->prepare('SELECT id, device_name, device_uid, is_online FROM devices WHERE user_id = ? ORDER BY device_name, id');
    $stmt->execute([$userId]);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Device list: ' . $exception->getMessage());
    $error = 'Unable to load your devices. Please try again.';
}
