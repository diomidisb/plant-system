<?php

$error = ps_take_flash('error');
$success = ps_take_flash('success');
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
