<?php

require_once __DIR__.'/private-config.php';
$emailConfig = ps_private_config('email');
$mail_username = $emailConfig['username'];
$mail_password = $emailConfig['password'];
unset($emailConfig);
