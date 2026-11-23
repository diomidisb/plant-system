<?php

if (!defined('PS_PUBLIC_PAGE')) {
    define('PS_PUBLIC_PAGE', true);
}

require_once __DIR__ . '/auth-bootstrap.php';

ps_require_post('reset-password.php');
$password = ps_post('password');
$confirm = ps_post('password_confirm');

if (preg_match_all('/./us', $password) < 12 || strlen($password) > 72 || strpos($password, "\0") !== false) {
    ps_flash('error', 'Use at least 12 characters. If your password is very long, try a shorter passphrase.');
    ps_redirect('reset-password.php');
}

if ($password !== $confirm) {
    ps_flash('error', 'The passwords do not match.');
    ps_redirect('reset-password.php');
}

$context = $_SESSION['password_reset'] ?? [];
$token = ps_parse_token(is_string($context['token'] ?? null) ? $context['token'] : '');

if (!$token || (int) ($context['expires'] ?? 0) <= time()) {
    unset($_SESSION['password_reset']);
    ps_flash('error', 'This reset link is invalid or expired. Request a new one.');
    ps_redirect('forgot-password.php');
}

try {
    if (!ps_rate('reset-submit-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 20, 900)) {
        ps_flash('error', 'Too many attempts. Please try again later.');
        ps_redirect('reset-password.php');
    }
    $db = ps_db();
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $db->beginTransaction();
    $query = $db->prepare('SELECT * FROM auth_password_resets WHERE selector = ?');
    $query->execute([$token[0]]);
    $reset = $query->fetch();
    $valid = $reset && (int) $reset['expires_at'] > time()
        && hash_equals($reset['validator_hash'], hash('sha256', $token[1]));
    if (!$valid) {
        throw new DomainException('Invalid token');
    }
    $table = $reset['subject_type'] === 'user' ? 'users' : 'pending_signups';
    $query = $db->prepare("SELECT * FROM $table WHERE id = ? FOR UPDATE");
    $query->execute([$reset['subject_id']]);
    $user = $query->fetch();
    $query = $db->prepare('SELECT * FROM auth_password_resets WHERE selector = ? FOR UPDATE');
    $query->execute([$token[0]]);
    $locked = $query->fetch();
    if (!$locked || !$user || (int) $locked['expires_at'] <= time()
        || !hash_equals($locked['validator_hash'], hash('sha256', $token[1]))
        || !hash_equals($locked['password_fingerprint'], hash('sha256', $user['password_hash'] ?? ''))
        || ($table === 'users' && $user['auth_provider'] !== 'local')) {
        throw new DomainException('Invalid token');
    }
    if ($table === 'users') {
        $query = $db->prepare('UPDATE users SET password_hash = ?, auth_version = auth_version + 1 WHERE id = ?');
        $query->execute([$newHash, $user['id']]);
        $query = $db->prepare('DELETE FROM auth_remember_tokens WHERE user_id = ?');
        $query->execute([$user['id']]);
    } else {
        $query = $db->prepare('UPDATE pending_signups SET password_hash = ? WHERE id = ?');
        $query->execute([$newHash, $user['id']]);
    }
    $query = $db->prepare('DELETE FROM auth_password_resets WHERE subject_type = ? AND subject_id = ?');
    $query->execute([$reset['subject_type'], $user['id']]);
    $db->commit();
    ps_clear_remember_cookie();
    $_SESSION = [];
    session_regenerate_id(true);
    ps_flash('success', 'Your password was changed. Log in with your new password.');
    ps_redirect('login.php');
} catch (DomainException $error) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    unset($_SESSION['password_reset']);
    ps_flash('error', 'This reset link is invalid or expired. Request a new one.');
    ps_redirect('forgot-password.php');
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Plant password reset: ' . $error->getMessage());
    ps_flash('error', 'Unable to change your password right now. Please try again later.');
    ps_redirect('reset-password.php');
}
