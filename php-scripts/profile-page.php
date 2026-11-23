<?php

require_once __DIR__ . '/auth.php';
requireLogin();

$pdo = ps_db();

$userId = currentUserId();

$stmt = $pdo->prepare(
    "SELECT
        id,
        name,
        surname,
        email,
        phone,
        nationality,
        created_at
     FROM users
     WHERE id = ?
     LIMIT 1"
);

$stmt->execute([$userId]);

$user = $stmt->fetch();

if (!$user) {
    header("Location: logout.php");
    exit;
}
