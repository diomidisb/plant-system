<?php

declare(strict_types=1);
$language = (string)($_GET['lang'] ?? '');
if (!in_array($language, ['el','en'], true)) {
    http_response_code(400);
    exit('Invalid language.');
}
$return = (string)($_GET['return'] ?? '/');
// Only paths on this hostname, never protocol-relative URLs or response splitting.
if (!preg_match('~\A/[A-Za-z0-9/_\.\-]*\z~D', $return) || str_starts_with($return, '//')) {
    $return = '/';
}
setcookie('plant_language', $language, ['expires' => time() + 31536000,'path' => '/','secure' => true,'httponly' => true,'samesite' => 'Lax']);
header('Cache-Control: no-store');
header('Location: '.$return, true, 303);
exit;
