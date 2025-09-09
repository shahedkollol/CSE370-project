<?php
require_once 'Backend/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    if (!is_logged_in()) {
        throw new Exception('User not logged in');
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Get user's NID
    $user_stmt = $pdo->prepare("SELECT NID FROM User WHERE User_ID = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    $rater_nid = $user['NID'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input data');
    }
    
    $required_fields = ['trip_id', 'rated_id', 'rating'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is required');
        }
    }
    
    $trip_id = $input['trip_id'];
    $rated_id = $input['rated_id'];
    $rating = $input['rating'];
    $comment = $input['comment'] ?? '';
    
    // Validate rating
    if (!is_numeric($rating) || $rating < 1 || $rating > 5) {
        throw new Exception('Rating must be between 1 and 5');
    }
    
    // Check if rated user exists
    $rated_user_stmt = $pdo->prepare("SELECT NID FROM User WHERE NID = ?");
    $rated_user_stmt->execute([$rated_id]);
    if (!$rated_user_stmt->fetch()) {
        throw new Exception('User to review not found');
    }
    
    // Check if user is trying to review themselves
    if ($rater_nid === $rated_id) {
        throw new Exception('You cannot review yourself');
    }
    
    // Check if user has already reviewed this person for this trip
    $existing_review_query = "SELECT Review_ID FROM Review WHERE Rater_ID = ? AND Rated_ID = ? AND Trip_ID = ?";
    $stmt = $pdo->prepare($existing_review_query);
    $stmt->execute([$rater_nid, $rated_id, $trip_id]);
    
    if ($stmt->fetch()) {
        throw new Exception('You have already reviewed this person for this trip');
    }
    
    // Insert review
    $insert_query = "INSERT INTO Review (Rater_ID, Rated_ID, Trip_ID, Rating, Comment) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($insert_query);
    $result = $stmt->execute([$rater_nid, $rated_id, $trip_id, $rating, $comment]);
    
    if (!$result) {
        throw new Exception('Failed to submit review');
    }
    
    // Create notification for the rated user
    $notification_message = "You received a {$rating}-star review from a user";
    $notification_query = "INSERT INTO Notifications (User_ID, Message) VALUES (?, ?)";
    $stmt = $pdo->prepare($notification_query);
    $stmt->execute([$rated_id, $notification_message]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Review submitted successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>