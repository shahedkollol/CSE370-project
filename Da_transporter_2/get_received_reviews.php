<?php
require_once 'config.php';

// Check if user is logged in
if (!is_logged_in()) {
    json_response(false, "Please login first");
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Get user's NID
    $user_stmt = $pdo->prepare("SELECT NID FROM User WHERE User_ID = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        json_response(false, "User not found");
    }

    // Get reviews received
    $stmt = $pdo->prepare("
        SELECT 
            r.Review_ID,
            r.Rater_ID,
            r.Rated_ID,
            r.Trip_ID,
            r.Rating,
            r.Comment,
            r.Created,
            u.Name as Rater_Name
        FROM Review r
        LEFT JOIN User u ON r.Rater_ID = u.NID
        WHERE r.Rated_ID = ?
        ORDER BY r.Created DESC
    ");
    $stmt->execute([$user['NID']]);
    $reviews = $stmt->fetchAll();

    json_response(true, "Reviews retrieved successfully", $reviews);

} catch (Exception $e) {
    json_response(false, "Error retrieving reviews: " . $e->getMessage());
}
?>