<?php
session_start();

function isSecure(): bool
{
    return isset($_SESSION['loggedIn']) && $_SESSION['loggedIn'] === true;
}

function requireLogin(): void
{
    if (!isSecure()) {
        header("Location: login.php");
        exit;
    }
}