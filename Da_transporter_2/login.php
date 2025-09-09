<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method');
}

try {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate input
    if (empty($email) || empty($password)) {
        json_response(false, 'Please enter both email and password');
    }

    // Find user by email
    $stmt = $pdo->prepare("
        SELECT u.*, 
               CASE 
                   WHEN ru.NID IS NOT NULL THEN 'student'
                   WHEN rt.NID IS NOT NULL THEN 'driver'
                   ELSE 'user'
               END as user_type,
               ru.Std_ID,
               rt.Rider_ID
        FROM User u
        LEFT JOIN Rider_User ru ON u.NID = ru.NID
        LEFT JOIN Rider_Taker rt ON u.NID = rt.NID
        WHERE u.Email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        json_response(false, 'Invalid email or password');
    }

    // Verify password
    if (!password_verify($password, $user['password'])) {
        json_response(false, 'Invalid email or password');
    }

    // Set session data
    $_SESSION['user_id'] = $user['NID'];
    $_SESSION['user_name'] = $user['Name'];
    $_SESSION['user_email'] = $user['Email'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['user_phone'] = $user['Phone'];
    $_SESSION['logged_in'] = true;

    // Create login notification
    try {
        $notification_stmt = $pdo->prepare("
            INSERT INTO Notifications (User_ID, Message, Status) 
            VALUES (?, ?, 'Unread')
        ");
        $login_message = "Welcome back, {$user['Name']}! You logged in at " . date('Y-m-d H:i:s');
        $notification_stmt->execute([$user['NID'], $login_message]);
    } catch (Exception $e) {
        // Log error but don't fail login
        error_log("Failed to create login notification: " . $e->getMessage());
    }

    // Determine redirect based on user type
    $redirect = 'dashboard.html';
    
    json_response(true, 'Login successful!', [
        'user_id' => $user['NID'],
        'name' => $user['Name'],
        'email' => $user['Email'],
        'phone' => $user['Phone'],
        'user_type' => $user['user_type'],
        'redirect' => $redirect
    ]);

} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    json_response(false, 'Login failed. Please try again.');
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    json_response(false, 'Login failed. Please try again.');
}
?>