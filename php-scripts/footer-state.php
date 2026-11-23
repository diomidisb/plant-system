<?php

if (!function_exists('ps_support_email')) {
    require_once __DIR__.'/auth-functions.php';
}
$footerSupportEmail = ps_support_email();
