<?php
session_start();
require_once 'Backend/config.php';

// redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // If it's a GET request (page load), redirect to dashboard
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header("Location: admin_dashboard.html");
        exit();
    }
}

// Handle POST request 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = sanitize_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            json_response(false, 'Please enter both email and password');
        }

        $stmt = $pdo->prepare("SELECT * FROM Admin WHERE Gsuite = ? AND Password = ?");
        $stmt->execute([$email, $password]);
        $admin = $stmt->fetch();

        if (!$admin) {
            json_response(false, 'Invalid admin credentials');
        }

        // Set session variables
        $_SESSION['admin_email'] = $admin['Gsuite'];
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['is_admin'] = true;

        json_response(true, 'Admin login successful!', [
            'email' => $admin['Gsuite'],
            'name' => $admin['Name'] ?? 'Administrator',
            'redirect' => 'admin_dashboard.html'
        ]);

    } catch (PDOException $e) {
        error_log("Admin login error: " . $e->getMessage());
        json_response(false, 'Database error. Please try again.');
    } catch (Exception $e) {
        error_log("Admin login error: " . $e->getMessage());
        json_response(false, 'Login failed. Please try again.');
    }
} else {
}
?>