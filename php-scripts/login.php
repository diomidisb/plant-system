<?php

if (!defined('PS_PUBLIC_PAGE')) {
    define('PS_PUBLIC_PAGE', true);
}

require_once __DIR__ . '/admin-auth.php';

ps_require_post('login.php');

if (isset($_SESSION['user_id'])) {
    ps_redirect('index.php');
}

$email = trim(ps_post('email'));
$password = ps_post('password');
ps_flash('email', $email);

try {
    pa_try_login($email, $password);
} catch (Throwable $exception) {
    error_log('Admin login: ' . $exception->getMessage());
    ps_flash('error', 'Unable to log in right now.');
    header('Location: /login.php', true, 303);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255 || $password === '') {
    ps_flash('error', 'Please enter a valid email address and your password.');
    ps_redirect('login.php');
}

try {
    $ipAllowed = ps_rate('login-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 40, 900);
    $emailAllowed = ps_rate('login-email', strtolower($email), 10, 900);
    if (!$ipAllowed || !$emailAllowed) {
        ps_flash('error', 'Too many attempts. Please try again in 15 minutes.');
        ps_redirect('login.php');
    }
    $db = ps_db();
    $query = $db->prepare("SELECT * FROM users WHERE email = ? AND auth_provider = 'local' LIMIT 1");
    $query->execute([$email]);
    $user = $query->fetch();
    $pending = false;
    if (!$user) {
        $query = $db->prepare('SELECT * FROM pending_signups WHERE email = ? LIMIT 1');
        $query->execute([$email]);
        $user = $query->fetch();
        $pending = true;
    }
    $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $valid = strlen($password) <= 4096 && password_verify($password, $hash);
    if (!$user || !$valid) {
        ps_flash('error', 'Incorrect email or password.');
        ps_redirect('login.php');
    }
    if (!$pending && (int) $user['email_verified'] !== 1) {
        ps_redirect('verify.php?email=' . rawurlencode($email));
    }
    ps_revoke_browser_token();
    if ($pending) {
        session_regenerate_id(true);
        $_SESSION['pending_id'] = (int) $user['id'];
        $_SESSION['pending_name'] = $user['name'];
        $_SESSION['pending_email'] = $user['email'];
        $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
        ps_redirect('pending.php');
    }
    $db->beginTransaction();
    $query = $db->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE');
    $query->execute([$user['id']]);
    $fresh = $query->fetch();
    if (!$fresh || $fresh['auth_provider'] !== 'local' || (int) $fresh['email_verified'] !== 1
        || !hash_equals($user['password_hash'], $fresh['password_hash'])) {
        $db->rollBack();
        ps_flash('error', 'Your account changed. Please log in again.');
        ps_redirect('login.php');
    }
    if (!(int)$fresh['is_active']) {
        $db->rollBack();
        ps_record_login_ip((int)$fresh['id']);
        ps_mark_disabled($fresh);
        ps_redirect('account-disabled.php');
    }
    if (ps_post('remember') === '1') {
        ps_issue_remember((int) $fresh['id']);
    }
    $db->commit();
    ps_establish_session($fresh);
    unset($_SESSION['auth_flash']);
    ps_redirect('index.php');
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Plant login: ' . $error->getMessage());
    ps_flash('error', 'Unable to log in right now. Please try again later.');
    ps_redirect('login.php');
}
