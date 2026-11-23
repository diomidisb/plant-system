<?php

require_once __DIR__ . '/auth-bootstrap.php';


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn()
{
    return isset($_SESSION["user_id"]);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

function currentUserId()
{
    return $_SESSION["user_id"] ?? null;
}

function currentUserName()
{
    return $_SESSION["user_name"] ?? "";
}

function currentUserEmail()
{
    return $_SESSION["user_email"] ?? "";
}
