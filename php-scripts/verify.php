<?php

if (!defined('PS_PUBLIC_PAGE')) {
    define('PS_PUBLIC_PAGE', true);
}

require_once __DIR__ . '/auth-bootstrap.php';
$pdo = ps_db();

$error = '';
$success = '';

$email = trim(
    (string) (
        $_GET['email']
        ?? $_POST['email']
        ?? ''
    )
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf'] ?? '');
    $email = trim((string) ($_POST['email'] ?? ''));
    $code = trim((string) ($_POST['code'] ?? ''));

    if (
        $csrf === ''
        || !hash_equals(ps_csrf(), $csrf)
    ) {
        $error = 'Invalid request. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (!preg_match('/^[0-9]{6}$/', $code)) {
        $error = 'Please enter the 6-digit verification code.';
    } elseif (!ps_rate('verify-ip', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 30, 900) || !ps_rate('verify-email', strtolower($email), 10, 900)) {
        $error = 'Too many attempts. Please try again in 15 minutes.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT
                    id,
                    name,
                    surname,
                    email,
                    phone,
                    nationality,
                    password_hash,
                    verification_token,
                    expires_at
                 FROM pending_signups
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->execute([$email]);

            $pending = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pending) {
                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM users
                     WHERE email = ?
                     LIMIT 1"
                );

                $stmt->execute([$email]);

                if ($stmt->fetchColumn()) {
                    $success = 'Your email is already verified.';
                } else {
                    $error = 'No pending account was found.';
                }
            } elseif (
                !hash_equals(
                    (string) $pending['verification_token'],
                    $code
                )
            ) {
                $error = 'Incorrect verification code.';
            } elseif (
                strtotime((string) $pending['expires_at']) < time()
            ) {
                $error = 'This verification code has expired.';
            } else {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "INSERT INTO users (
                        name,
                        surname,
                        email,
                        phone,
                        nationality,
                        password_hash,
                        auth_provider,
                        email_verified
                     )
                     VALUES (?, ?, ?, ?, ?, ?, 'local', 1)"
                );

                $stmt->execute([
                    $pending['name'],
                    $pending['surname'],
                    $pending['email'],
                    $pending['phone'],
                    $pending['nationality'],
                    $pending['password_hash']
                ]);

                $stmt = $pdo->prepare(
                    "DELETE FROM pending_signups
                     WHERE id = ?"
                );

                $stmt->execute([
                    $pending['id']
                ]);

                $pdo->commit();

                $success = 'Email verified successfully!';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Email verification error: '
                . $e->getMessage()
            );

            $error = 'Email verification failed. Please try again.';
        }
    }
}
