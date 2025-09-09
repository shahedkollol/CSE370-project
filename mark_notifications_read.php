<?php
require_once 'Backend/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method');
}

// Check if user is logged in
if (!is_logged_in()) {
    json_response(false, 'Please login first');
}

try {
    $user_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    $notification_id = intval($input['notification_id'] ?? 0);
    
    if ($notification_id > 0) {
        // Mark specific notification as read
        $stmt = $pdo->prepare("
            UPDATE Notifications 
            SET Status = 'Read' 
            WHERE Notification_ID = ? AND User_ID = ?
        ");
        $stmt->execute([$notification_id, $user_id]);
        $message = 'Notification marked as read';
    } else {
        // Mark all notifications as read
        $stmt = $pdo->prepare("
            UPDATE Notifications 
            SET Status = 'Read' 
            WHERE User_ID = ? AND Status = 'Unread'
        ");
        $stmt->execute([$user_id]);
        $message = 'All notifications marked as read';
    }

    json_response(true, $message);

} catch (PDOException $e) {
    error_log("Mark notifications read error: " . $e->getMessage());
    json_response(false, 'Failed to update notifications');
} catch (Exception $e) {
    error_log("Mark notifications read error: " . $e->getMessage());
    json_response(false, 'Failed to update notifications');
}
?>