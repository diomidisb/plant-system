<?php

require_once __DIR__ . '/auth-bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    ps_redirect('login.php');
}
