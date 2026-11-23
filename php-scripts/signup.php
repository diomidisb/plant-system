<?php

if (!defined('PS_PUBLIC_PAGE')) {
    define('PS_PUBLIC_PAGE', true);
}

require_once __DIR__ . '/auth-bootstrap.php';
$pdo = ps_db();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/email_config.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

if (isset($_SESSION['user_id'])) {
    ps_redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ps_redirect('signup.php');
}

function signupFail(string $message, array $old = []): never
{
    $_SESSION['signup_error'] = $message;
    $_SESSION['signup_old'] = $old;

    ps_redirect('signup.php');
}

$csrf = (string) ($_POST['csrf'] ?? '');
$sessionCsrf = (string) ($_SESSION['signup_csrf'] ?? '');

if (
    $sessionCsrf === ''
    || $csrf === ''
    || !hash_equals($sessionCsrf, $csrf)
) {
    signupFail('Invalid request. Please try again.');
}

$name = trim((string) ($_POST['name'] ?? ''));
$surname = trim((string) ($_POST['surname'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$phone = trim((string) ($_POST['phone'] ?? ''));
$nationality = trim((string) ($_POST['nationality'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');
$termsAccepted = isset($_POST['terms']);

$old = [
    'name' => $name,
    'surname' => $surname,
    'email' => $email,
    'phone' => $phone,
    'nationality' => $nationality
];

$allowedNationalities = [
    'Greek',
    'Albanian',
    'Italian',
    'German',
    'French',
    'British',
    'American',
    'Other'
];

if (
    $name === ''
    || $surname === ''
    || $email === ''
    || $phone === ''
    || $nationality === ''
    || $password === ''
    || $confirmPassword === ''
) {
    signupFail('Please fill in all fields.', $old);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    signupFail('Please enter a valid email address.', $old);
}

if (!in_array($nationality, $allowedNationalities, true)) {
    signupFail('Please select a valid nationality.', $old);
}

$passwordLength = strlen($password);

if ($passwordLength < 12) {
    signupFail('Password must contain at least 12 characters.', $old);
}

if ($passwordLength > 72) {
    signupFail('Password must not exceed 72 bytes.', $old);
}

if ($password !== $confirmPassword) {
    signupFail('Passwords do not match.', $old);
}

if (!$termsAccepted) {
    signupFail('You must accept the Terms & Conditions.', $old);
}

try {
    $stmt = $pdo->prepare(
        "SELECT id
         FROM users
         WHERE email = ?
         LIMIT 1"
    );

    $stmt->execute([$email]);

    if ($stmt->fetchColumn()) {
        signupFail('An account with this email already exists.', $old);
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM pending_signups
         WHERE email = ?
         LIMIT 1"
    );

    $stmt->execute([$email]);

    $pendingId = $stmt->fetchColumn();

    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    if ($passwordHash === false) {
        throw new RuntimeException('Password hashing failed.');
    }

    $verificationCode = (string) random_int(
        100000,
        999999
    );

    $expiresAt = date(
        'Y-m-d H:i:s',
        time() + 600
    );

    if ($pendingId) {
        $stmt = $pdo->prepare(
            "UPDATE pending_signups
             SET
                name = ?,
                surname = ?,
                phone = ?,
                nationality = ?,
                password_hash = ?,
                verification_token = ?,
                expires_at = ?
             WHERE id = ?"
        );

        $stmt->execute([
            $name,
            $surname,
            $phone,
            $nationality,
            $passwordHash,
            $verificationCode,
            $expiresAt,
            $pendingId
        ]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO pending_signups (
                name,
                surname,
                email,
                phone,
                nationality,
                password_hash,
                verification_token,
                expires_at
             )
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $name,
            $surname,
            $email,
            $phone,
            $nationality,
            $passwordHash,
            $verificationCode,
            $expiresAt
        ]);
    }

    if (!ps_rate('signup-email', strtolower($email), 3, 3600) || !ps_rate('signup-ip', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 12, 3600)) {
        throw new RuntimeException('Too many signup emails. Please try again later.');
    }
    $mail = ps_mailer($email, $name.' '.$surname);
    $safeName = ps_e($name);
    $safeCode = ps_e($verificationCode);
    $mail->isHTML(true);
    $mail->Subject = 'Plant System - Email Verification';

    $mail->Body = "
        <div style=\"font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:30px;\">
            <h1 style=\"color:#2f6b3f;\">Plant System</h1>
            <p>Hello {$safeName},</p>
            <p>Thank you for creating your Plant System account.</p>
            <p>Your verification code is:</p>
            <div style=\"font-size:32px;font-weight:bold;letter-spacing:8px;padding:20px;background:#f1f7f2;text-align:center;color:#2f6b3f;border-radius:10px;\">
                {$safeCode}
            </div>
            <p>Enter this code on the verification page.</p>
            <p>This code expires in <strong>10 minutes</strong>.</p>
            <p>If you did not create this account, you can ignore this email.</p>
            <p>Plant System</p>
        </div>
    ";

    $mail->AltBody =
        'Your Plant System verification code is: '
        . $verificationCode;

    $mail->send();

    unset(
        $_SESSION['signup_error'],
        $_SESSION['signup_old']
    );

    $_SESSION['signup_csrf'] = bin2hex(
        random_bytes(32)
    );

    ps_redirect(
        'verify.php?email=' . rawurlencode($email)
    );

} catch (Exception $e) {
    error_log(
        'Signup email error: ' . $e->getMessage()
    );

    signupFail(
        'The verification email could not be sent. Please try again.',
        $old
    );

} catch (Throwable $e) {
    error_log(
        'Signup error: ' . $e->getMessage()
    );

    signupFail(
        'The account could not be created. Please try again.',
        $old
    );
}
