<?php

declare(strict_types=1);
require_once __DIR__ . '/auth-bootstrap.php';

function pa_config(): ?array
{
    $path = '/etc/plant-system/admin.php';
    if (!is_readable($path)) {
        return null;
    }
    $config = require $path;
    return is_array($config) && isset($config['username'], $config['password_hash']) ? $config : null;
}

function pa_secure_connection(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}

function pa_active(): bool
{
    $config = pa_config();
    return $config !== null && pa_secure_connection()
        && isset($_SESSION['admin_username'], $_SESSION['admin_signature'], $_SESSION['admin_last_seen'])
        && hash_equals($config['username'], (string) $_SESSION['admin_username'])
        && hash_equals(hash('sha256', $config['password_hash']), (string) $_SESSION['admin_signature'])
        && time() - (int) $_SESSION['admin_last_seen'] < 1800;
}

function pa_require(): void
{
    if (!pa_active()) {
        unset($_SESSION['admin_username'], $_SESSION['admin_signature'], $_SESSION['admin_last_seen']);
        http_response_code(403);
        exit('Administrator access required. Use HTTPS or the SSH tunnel and sign in again.');
    }
    $_SESSION['admin_last_seen'] = time();
}

function pa_try_login(string $identifier, string $password): void
{
    $config = pa_config();
    if (!$config || !hash_equals($config['username'], $identifier)) {
        return;
    }
    if (!pa_secure_connection()) {
        ps_flash('error', 'Administrator login requires HTTPS or the SSH tunnel.');
        header('Location: /login.php', true, 303);
        exit;
    }
    $ipAllowed = ps_rate('admin-login-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 10, 900);
    $nameAllowed = ps_rate('admin-login-name', $identifier, 10, 900);
    if (!$ipAllowed || !$nameAllowed || strlen($password) > 4096 || !password_verify($password, $config['password_hash'])) {
        ps_flash('error', 'Incorrect login details or too many attempts. Try again later.');
        header('Location: /login.php', true, 303);
        exit;
    }
    ps_revoke_browser_token();
    session_regenerate_id(true);
    $_SESSION = [
        'admin_username' => $config['username'],
        'admin_signature' => hash('sha256', $config['password_hash']),
        'admin_last_seen' => time(),
        'auth_csrf' => bin2hex(random_bytes(32)),
    ];
    header('Location: /admin.php', true, 303);
    exit;
}
