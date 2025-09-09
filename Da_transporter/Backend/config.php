<?php
$servername = "localhost";
$username = "root";  
$password = "";     
$dbname = "da_transporter";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generate_user_id($type = 'user') {
    return strtoupper($type) . '_' . date('Y') . '_' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function generate_trip_code() {
    return 'TRIP_' . date('Ymd') . '_' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
}

function generate_booking_code() {
    return 'BOOK_' . date('Ymd') . '_' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}


function redirect_if_not_logged_in($redirect_to = 'login.html') {
    if (!is_logged_in()) {
        header("Location: $redirect_to");
        exit();
    }
}

function json_response($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}
?>