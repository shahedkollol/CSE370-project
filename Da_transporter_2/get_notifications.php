<?php
require_once 'config.php';

// Check if user is logged in
if (!is_logged_in()) {
    json_response(false, 'Please login first');
}

try {
    // Get user's NID from session
    $nid = $_SESSION['user_id'];

    // Fetch notifications for user with NID as foreign key
    $stmt = $pdo->prepare("
        SELECT 
            Notification_ID,
            Message,
            Status,
            Created_At
        FROM Notifications 
        WHERE NID = ?
        ORDER BY Created_At DESC
        LIMIT 50
    ");
    $stmt->execute([$nid]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unread count
    $unread_stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM Notifications 
        WHERE NID = ? AND Status = 'Unread'
    ");
    $unread_stmt->execute([$nid]);
    $unread_data = $unread_stmt->fetch(PDO::FETCH_ASSOC);

    json_response(true, 'Notifications loaded', [
        'notifications' => $notifications,
        'unread_count' => $unread_data['unread_count']
    ]);

} catch (PDOException $e) {
    error_log("Get notifications error: " . $e->getMessage());
    json_response(false, 'Failed to load notifications');
} catch (Exception $e) {
    error_log("Get notifications error: " . $e->getMessage());
    json_response(false, 'Failed to load notifications');
}
?>