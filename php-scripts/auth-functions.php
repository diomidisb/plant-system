<?php

require_once __DIR__.'/i18n.php';
require_once __DIR__.'/mail.php';

function ps_config(): array
{
    static $config;
    return $config ??= require dirname(__DIR__) . '/auth_config.php';
}

function ps_url(string $path): string
{
    return rtrim(ps_config()['base_url'], '/') . '/' . ltrim($path, '/');
}

function ps_redirect(string $path): void
{
    header('Location: ' . ps_url($path), true, 303);
    exit;
}

function ps_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ps_db(): PDO
{
    static $connection;
    if (!$connection) {
        require dirname(__DIR__) . '/db.php';
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new RuntimeException('db.php must create a PDO variable named $pdo.');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $connection = $pdo;
    }
    // Legacy pages expect db.php's PDO in global scope even when auth loaded it first.
    $GLOBALS['pdo'] = $connection;
    return $connection;
}

function ps_cookie_options(int $expires): array
{
    $config = ps_config();
    $path = parse_url($config['base_url'], PHP_URL_PATH) ?: '/';
    $secure = $config['cookie_secure']
        || parse_url($config['base_url'], PHP_URL_SCHEME) === 'https'
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return [
        'expires' => $expires,
        'path' => rtrim($path, '/') . '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function ps_flash(string $key, $value): void
{
    $_SESSION['auth_flash'][$key] = $value;
}

function ps_take_flash(string $key, $default = '')
{
    $value = $_SESSION['auth_flash'][$key] ?? $default;
    unset($_SESSION['auth_flash'][$key]);
    return $value;
}

function ps_post(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
}

function ps_csrf(): string
{
    if (empty($_SESSION['auth_csrf'])) {
        $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['auth_csrf'];
}

function ps_require_post(string $page): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ps_redirect($page);
    }
    if (!hash_equals(ps_csrf(), ps_post('csrf'))) {
        ps_flash('error', 'Your session expired. Please try again.');
        ps_redirect($page);
    }
}

function ps_rate(string $scope, string $identity, int $limit, int $seconds): bool
{
    $db = ps_db();
    $key = hash('sha256', $scope . ':' . $identity);
    $now = time();
    $query = $db->prepare('INSERT INTO auth_rate_limits (bucket, attempts, expires_at)
        VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE
        attempts = IF(expires_at <= ?, 1, LEAST(attempts + 1, 1000000)),
        expires_at = IF(expires_at <= ?, ?, expires_at)');
    $query->execute([$key, $now + $seconds, $now, $now, $now + $seconds]);
    $query = $db->prepare('SELECT attempts FROM auth_rate_limits WHERE bucket = ?');
    $query->execute([$key]);
    return (int) $query->fetchColumn() <= $limit;
}

function ps_parse_token(string $value): ?array
{
    if (!preg_match('/\A([a-f0-9]{24}):([a-f0-9]{64})\z/D', $value, $matches)) {
        return null;
    }
    return [$matches[1], $matches[2]];
}

function ps_clear_remember_cookie(): void
{
    setcookie('plant_remember', '', ps_cookie_options(time() - 3600));
    unset($_COOKIE['plant_remember']);
}

function ps_revoke_browser_token(): void
{
    $raw = $_COOKIE['plant_remember'] ?? '';
    $token = ps_parse_token(is_string($raw) ? $raw : '');
    if ($token) {
        $hash = hash('sha256', $token[1]);
        $query = ps_db()->prepare('DELETE FROM auth_remember_tokens
            WHERE selector = ? AND (validator_hash = ? OR previous_hash = ?)');
        $query->execute([$token[0], $hash, $hash]);
    }
    ps_clear_remember_cookie();
}

function ps_establish_session(array $user): void
{
    ps_record_login_ip((int)$user['id']);
    if (isset($user['is_active']) && !(int)$user['is_active']) {
        ps_mark_disabled($user);
        ps_redirect('account-disabled.php');
    }
    unset($_SESSION['account_disabled'],$_SESSION['support_user_id'],$_SESSION['support_auth_version'],$_SESSION['support_user_name']);
    session_regenerate_id(true);
    unset($_SESSION['pending_id'], $_SESSION['pending_name'], $_SESSION['pending_email']);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['auth_version'] = (int) $user['auth_version'];
    $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
}

function ps_issue_remember(int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + (int) ps_config()['remember_days'] * 86400;
    $query = ps_db()->prepare('INSERT INTO auth_remember_tokens
        (selector, user_id, validator_hash, expires_at) VALUES (?, ?, ?, ?)');
    $query->execute([$selector, $userId, hash('sha256', $validator), $expires]);
    setcookie('plant_remember', $selector . ':' . $validator, ps_cookie_options($expires));
}

function ps_restore_remember(): void
{
    $raw = $_COOKIE['plant_remember'] ?? '';
    $token = ps_parse_token(is_string($raw) ? $raw : '');
    if (!$token || isset($_SESSION['pending_id'])) {
        return;
    }
    $db = ps_db();
    $db->beginTransaction();
    try {
        $query = $db->prepare('SELECT * FROM auth_remember_tokens WHERE selector = ?');
        $query->execute([$token[0]]);
        $record = $query->fetch();
        if (!$record) {
            $db->rollBack();
            return;
        }
        $query = $db->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE');
        $query->execute([$record['user_id']]);
        $user = $query->fetch();
        $query = $db->prepare('SELECT * FROM auth_remember_tokens WHERE selector = ? FOR UPDATE');
        $query->execute([$token[0]]);
        $record = $query->fetch();
        if (!$user || !$record || $user['auth_provider'] !== 'local'
            || (int) $user['email_verified'] !== 1 || (int) $record['expires_at'] <= time()) {
            $db->rollBack();
            return;
        }
        $hash = hash('sha256', $token[1]);
        $current = hash_equals($record['validator_hash'], $hash);
        $previous = $record['previous_hash'] !== null
            && (int) $record['previous_until'] > time()
            && hash_equals($record['previous_hash'], $hash);
        if (!$current && !$previous) {
            $db->rollBack();
            return;
        }
        // Grant limited support access only after the remember-token validator matched.
        if (!(int)$user['is_active']) {
            $db->rollBack();
            ps_record_login_ip((int)$user['id']);
            ps_mark_disabled($user);
            return;
        }
        if ($current) {
            $validator = bin2hex(random_bytes(32));
            $query = $db->prepare('UPDATE auth_remember_tokens SET previous_hash = validator_hash,
                previous_until = ?, validator_hash = ? WHERE selector = ?');
            $query->execute([time() + 15, hash('sha256', $validator), $token[0]]);
        }
        $db->commit();
        ps_establish_session($user);
        if ($current) {
            setcookie(
                'plant_remember',
                $token[0] . ':' . $validator,
                ps_cookie_options((int) $record['expires_at'])
            );
        }
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function ps_check_session(): void
{
    if (!isset($_SESSION['user_id'])) {
        ps_support_user();
        return;
    }
    $query = ps_db()->prepare('SELECT id, name, auth_version, is_active FROM users WHERE id = ?');
    $query->execute([$_SESSION['user_id']]);
    $user = $query->fetch();
    if ($user && !(int)$user['is_active']) {
        ps_mark_disabled($user);
        return;
    }
    if ($user && !isset($_SESSION['auth_version']) && (int) $user['auth_version'] === 0) {
        $_SESSION['auth_version'] = 0;
    }
    if (!$user || !isset($_SESSION['auth_version'])
        || (int) $_SESSION['auth_version'] !== (int) $user['auth_version']) {
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['auth_version']);
        session_regenerate_id(true);
    }
}

function ps_mail_reset(string $email, string $link): void
{
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    require dirname(__DIR__) . '/email_config.php';
    if (empty($mail_username) || empty($mail_password)) {
        throw new RuntimeException('SMTP credentials are missing.');
    }
    $config = ps_config();
    $mail = ps_mailer($email);
    $mail->isHTML(true);
    $mail->Subject = 'Plant System - Επαναφορά κωδικού';
    $minutes = (int) $config['reset_minutes'];
    $mail->Body = '<h2>Επαναφορά κωδικού / Password reset</h2>'
        . '<p>Για να αλλάξεις τον κωδικό σου, πάτησε τον παρακάτω σύνδεσμο.</p>'
        . '<p><a href="' . ps_e($link) . '">Αλλαγή κωδικού / Reset password</a></p>'
        . '<p>Ο σύνδεσμος ισχύει για ' . $minutes . ' λεπτά και χρησιμοποιείται μία φορά.</p>'
        . '<p>Αν δεν ζήτησες αλλαγή κωδικού, αγνόησε αυτό το email.</p>';
    $mail->AltBody = "Reset your password: $link\nExpires in $minutes minutes. "
        . 'If you did not request this, ignore this email.';
    $mail->send();
}

function ps_mark_disabled(array $user): void
{
    $_SESSION['support_user_id'] = (int)$user['id'];
    $_SESSION['support_auth_version'] = (int)$user['auth_version'];
    $_SESSION['support_user_name'] = (string)($user['name'] ?? 'Account');
    $_SESSION['account_disabled'] = true;
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['auth_version']);
    ps_clear_remember_cookie();
    session_regenerate_id(true);
}
function ps_support_email(): string
{
    require dirname(__DIR__) . '/email_config.php';
    return filter_var($mail_username ?? '', FILTER_VALIDATE_EMAIL) ? $mail_username : '';
}
function ps_admin_reset_mail(array $user): void
{
    if ($user['auth_provider'] !== 'local' || empty($user['password_hash'])) {
        throw new RuntimeException('This account uses Google sign-in and has no local password to reset.');
    }
    $db = ps_db();
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $query = $db->prepare('INSERT INTO auth_password_resets (selector,subject_type,subject_id,validator_hash,password_fingerprint,expires_at) VALUES (?,\'user\',?,?,?,?)');
    $query->execute([$selector,$user['id'],hash('sha256', $validator),hash('sha256', $user['password_hash']),time() + (int)ps_config()['reset_minutes'] * 60]);
    try {
        ps_mail_reset($user['email'], ps_url('reset-password.php?token='.$selector.':'.$validator));
    } catch (Throwable $error) {
        $query = $db->prepare('DELETE FROM auth_password_resets WHERE selector=?');
        $query->execute([$selector]);
        throw new RuntimeException('The email could not be sent. Check SMTP settings and try again.');
    }
}

// A disabled account has a separate, restricted identity. Never use it for device APIs.
function ps_support_user(): ?array
{
    $id = (int)($_SESSION['user_id'] ?? $_SESSION['support_user_id'] ?? 0);
    if (!$id) {
        return null;
    }
    $q = ps_db()->prepare('SELECT id,name,surname,email,phone,is_active,auth_version FROM users WHERE id=?');
    $q->execute([$id]);
    $user = $q->fetch(PDO::FETCH_ASSOC);
    $version = isset($_SESSION['user_id']) ? ($_SESSION['auth_version'] ?? -1) : ($_SESSION['support_auth_version'] ?? -1);
    if (!$user || (int)$version !== (int)$user['auth_version']) {
        unset($_SESSION['support_user_id'],$_SESSION['support_auth_version'],$_SESSION['support_user_name']);
        return null;
    }
    return $user;
}

// Called on successful credential/remember-token authentication, never on ordinary page views.
function ps_record_login_ip(int $userId): void
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return;
    }
    $packed = inet_pton($ip);
    if ($packed === false) {
        return;
    }
    $db = ps_db();
    $own = !$db->inTransaction();
    try {
        if ($own) {
            $db->beginTransaction();
        }
        $q = $db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
        $q->execute([$userId]);
        if (!$q->fetchColumn()) {
            if ($own) {
                $db->rollBack();
            }return;
        }
        $q = $db->prepare('SELECT ip_address FROM user_login_ips WHERE user_id=? ORDER BY id DESC LIMIT 1');
        $q->execute([$userId]);
        $last = $q->fetchColumn();
        if ($last === false || !hash_equals($last, $packed)) {
            $q = $db->prepare('INSERT INTO user_login_ips(user_id,ip_address) VALUES(?,?)');
            $q->execute([$userId,$packed]);
        }
        if ($own) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($own && $db->inTransaction()) {
            $db->rollBack();
        }throw $e;
    }
}
