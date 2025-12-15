<?php
/**
 * Logout functionality for Private Clinic Patient Record System
 * Terminates user session and redirects to login page
 */

require_once 'config.php';
require_once 'db.php';

// Start session
session_start();

// Log logout activity before destroying session
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    Database::logActivity(
        $_SESSION['user_id'], 
        $_SESSION['username'], 
        'LOGOUT', 
        "User logged out"
    );
}

// Destroy all session data
$_SESSION = array();

// Delete the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: index.php');
exit();