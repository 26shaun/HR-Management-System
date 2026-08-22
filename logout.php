<?php
require_once __DIR__ . '/config/db.php';

// Unset all session values and destroy
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Start fresh session for flash message
session_start();
$_SESSION['flash_success'] = "You have been logged out securely.";
header("Location: login.php");
exit;
