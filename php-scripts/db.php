<?php

require_once __DIR__.'/private-config.php';
try {
    $database = ps_private_config('database');
    $pdo = new PDO('mysql:host='.$database['host'].';dbname='.$database['dbname'].';charset=utf8mb4', $database['username'], $database['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES => false]);
} catch (Throwable $e) {
    error_log('Plant database connection failed: '.$e->getMessage());
    if (PHP_SAPI === 'cli') {
        throw $e;
    }
    http_response_code(503);
    if (defined('PS_AUTH_API')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false,'message' => 'The service is temporarily unavailable.']);
    } else {
        echo 'The service is temporarily unavailable. Please try again later.';
    }
    exit;
}
