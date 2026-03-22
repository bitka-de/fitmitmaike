<?php
session_start();

// Alle Session-Daten löschen
$_SESSION = [];

// Session-Cookie löschen (wichtig!)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Session komplett zerstören
session_destroy();

// Weiterleitung (z.B. zum Dashboard oder Login)
header("Location: index.php");
exit;