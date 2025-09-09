<?php
require_once 'Backend/config.php';

// Destroy session
session_destroy();

// Clear all session data
$_SESSION = array();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// If it's an AJAX request, return JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    json_response(true, 'Logged out successfully');
}

// Otherwise redirect to home page
header("Location: Backend/index.php");
?>