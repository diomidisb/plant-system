<?php

if (!defined('PS_PUBLIC_PAGE')) {
    define('PS_PUBLIC_PAGE', true);
}

require_once __DIR__ . '/auth-bootstrap.php';

ps_require_post('forgot-password.php');
$email = trim(ps_post('email'));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    ps_flash('error', 'Please enter a valid email address.');
    ps_redirect('forgot-password.php');
}

$message = 'If this email belongs to a password-based account, you will receive a reset link. Check your spam folder too.';

try {
    $ipAllowed = ps_rate('reset-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 10, 3600);
    $emailAllowed = ps_rate('reset-email', strtolower($email), 3, 3600);
    if ($ipAllowed && $emailAllowed) {
        $db = ps_db();
        $query = $db->prepare("SELECT * FROM users WHERE email = ? AND auth_provider = 'local' LIMIT 1");
        $query->execute([$email]);
        $user = $query->fetch();
        $type = 'user';
        if (!$user) {
            $query = $db->prepare('SELECT * FROM pending_signups WHERE email = ? LIMIT 1');
            $query->execute([$email]);
            $user = $query->fetch();
            $type = 'pending';
        }
        if ($user && !empty($user['password_hash'])) {
            $selector = bin2hex(random_bytes(12));
            $validator = bin2hex(random_bytes(32));
            $query = $db->prepare('INSERT INTO auth_password_resets
                (selector, subject_type, subject_id, validator_hash, password_fingerprint, expires_at)
                VALUES (?, ?, ?, ?, ?, ?)');
            $query->execute([
                $selector, $type, $user['id'], hash('sha256', $validator),
                hash('sha256', $user['password_hash']), time() + ps_config()['reset_minutes'] * 60,
            ]);
            try {
                ps_mail_reset($user['email'], ps_url('reset-password.php?token=' . $selector . ':' . $validator));
            } catch (Throwable $error) {
                $query = $db->prepare('DELETE FROM auth_password_resets WHERE selector = ?');
                $query->execute([$selector]);
                throw $error;
            }
        }
    }
} catch (Throwable $error) {
    error_log('Plant password reset request: ' . $error->getMessage());
}

ps_flash('success', $message);
ps_redirect('forgot-password.php');
