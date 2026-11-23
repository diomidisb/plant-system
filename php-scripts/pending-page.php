<?php

define('PS_PUBLIC_PAGE', true);
require_once __DIR__.'/auth-bootstrap.php';
$db = ps_db();
$error = '';
$success = '';
$pendingId = (int)($_SESSION['pending_id'] ?? 0);
if (!$pendingId || empty($_SESSION['pending_email'])) {
    ps_redirect('login.php');
}
$q = $db->prepare('SELECT * FROM pending_signups WHERE id=? AND email=?');
$q->execute([$pendingId,$_SESSION['pending_email']]);
$pending = $q->fetch();
if (!$pending) {
    unset($_SESSION['pending_id'],$_SESSION['pending_name'],$_SESSION['pending_email']);
    ps_redirect('login.php');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!hash_equals(ps_csrf(), ps_post('csrf'))) {
            throw new RuntimeException('Your session expired. Refresh the page and try again.');
        }
        if (!ps_rate('verification-resend', (string)$pendingId, 3, 3600)) {
            throw new RuntimeException('Too many emails. Try again in one hour.');
        }
        $code = (string)random_int(100000, 999999);
        ps_mail_verification($pending['email'], $pending['name'], $code);
        $q = $db->prepare('UPDATE pending_signups SET verification_token=?,expires_at=? WHERE id=?');
        $q->execute([$code,date('Y-m-d H:i:s', time() + 600),$pendingId]);
        $success = 'A new verification code has been sent to your email.';
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Plant verification resend failed: '.$e->getMessage());
        $error = 'The verification email could not be sent. Please try again later.';
    }
}
