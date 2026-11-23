<?php

declare(strict_types=1);
define('PS_PUBLIC_PAGE', true);
require_once __DIR__.'/auth-bootstrap.php';
require_once __DIR__.'/account-delete-core.php';
header('X-Frame-Options: DENY');
$identity = ps_support_user();
if (!$identity) {
    ps_redirect('login.php');
}
$db = ps_db();
$q = $db->prepare('SELECT * FROM users WHERE id=?');
$q->execute([$identity['id']]);
$account = $q->fetch();
$error = '';
$success = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!hash_equals(ps_csrf(), ps_post('csrf'))) {
            throw new RuntimeException('Your session expired. Refresh the page and try again.');
        }
        $action = ps_post('action');
        if ($action === 'send_code' && $account['auth_provider'] === 'google') {
            if (!ps_rate('delete-email', (string)$account['id'], 3, 3600)) {
                throw new RuntimeException('Too many emails. Try again in one hour.');
            }
            unset($_SESSION['delete_proof']);
            $code = (string)random_int(100000, 999999);
            try {
                ps_mail_delete_code($account['email'], $code);
            } catch (Throwable $e) {
                error_log('Account deletion email: '.$e->getMessage());
                throw new RuntimeException('The confirmation email could not be sent. Please try again later.');
            }
            $_SESSION['delete_proof'] = ['hash' => hash('sha256', $code),'expires' => time() + 600,'id' => (int)$account['id'],'version' => (int)$account['auth_version'],'email' => $account['email']];
            $success = 'A confirmation code was sent to your account email. It expires in 10 minutes.';
        } elseif ($action === 'delete_account') {
            if (ps_post('confirm') !== 'yes' || !hash_equals($account['email'], trim(ps_post('confirm_email')))) {
                throw new RuntimeException('Enter your account email and check the confirmation box.');
            }
            if (!ps_rate('delete-proof', (string)$account['id'], 5, 900)) {
                throw new RuntimeException('Too many attempts. Try again in 15 minutes.');
            }
            $verified = false;
            if ($account['auth_provider'] === 'google') {
                $proof = $_SESSION['delete_proof'] ?? [];
                $verified = ($proof['expires'] ?? 0) > time() && ($proof['id'] ?? 0) === (int)$account['id'] && ($proof['version'] ?? -1) === (int)$account['auth_version'] && ($proof['email'] ?? '') === $account['email'] && hash_equals($proof['hash'] ?? '', hash('sha256', ps_post('code')));
                if (!$verified) {
                    throw new RuntimeException('The confirmation code is incorrect or expired. Request a new code.');
                }
            }
            ps_delete_own_account($db, (int)$account['id'], (int)$identity['auth_version'], $account['email'], ps_post('password'), $verified);
            ps_clear_remember_cookie();
            $_SESSION = [];
            $params = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600,'path' => $params['path'],'domain' => $params['domain'],'secure' => $params['secure'],'httponly' => true,'samesite' => 'Lax']);
            session_destroy();
            ps_redirect('account-deleted.php');
        } else {
            throw new RuntimeException('Invalid request.');
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Account self deletion: '.$e->getMessage());
        $error = 'Your account could not be deleted. Please try again later.';
    }
}
