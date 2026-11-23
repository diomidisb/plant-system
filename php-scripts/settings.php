<?php

require_once __DIR__ . '/require-login.php';
$pdo = ps_db();

$userId = (int) $_SESSION['user_id'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf'] ?? '');

    if (
        $csrf === ''
        || !hash_equals(ps_csrf(), $csrf)
    ) {
        $error = 'Invalid request. Please try again.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $surname = trim((string) ($_POST['surname'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $nationality = trim((string) ($_POST['nationality'] ?? ''));

        if (
            $name === ''
            || $surname === ''
            || $phone === ''
            || $nationality === ''
        ) {
            $error = 'Please fill in all fields.';
        } elseif (
            strlen($name) > 100
            || strlen($surname) > 100
            || strlen($phone) > 30
            || strlen($nationality) > 100
        ) {
            $error = 'One or more fields are too long.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE users
                     SET
                        name = ?,
                        surname = ?,
                        phone = ?,
                        nationality = ?
                     WHERE id = ?"
                );

                $stmt->execute([
                    $name,
                    $surname,
                    $phone,
                    $nationality,
                    $userId
                ]);

                $_SESSION['user_name'] = $name;

                $success = 'Settings updated successfully.';
            } catch (Throwable $e) {
                error_log(
                    'Settings update error: '
                    . $e->getMessage()
                );

                $error = 'Settings could not be updated. Please try again.';
            }
        }
    }
}

try {
    $stmt = $pdo->prepare(
        "SELECT
            name,
            surname,
            email,
            phone,
            nationality
         FROM users
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->execute([$userId]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        ps_redirect('login.php');
    }
} catch (Throwable $e) {
    error_log(
        'Settings load error: '
        . $e->getMessage()
    );

    http_response_code(500);
    exit('The account could not be loaded.');
}
