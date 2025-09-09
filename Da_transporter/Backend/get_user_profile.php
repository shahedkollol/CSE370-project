<?php
require_once 'Backend/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    json_response(false, 'Please login first');
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Get user profile information
    $stmt = $pdo->prepare("
        SELECT 
            u.*,
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
        WHERE u.NID = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        json_response(false, 'User not found');
    }

    // Remove sensitive data
    unset($user['password']);

    // Get user stats
    $stats = [];

    if ($user['user_type'] === 'driver') {
        // Driver stats
        $driver_stats_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_trips,
                COUNT(CASE WHEN Trip_Status = 'Completed' THEN 1 END) as completed_trips,
                SUM(CASE WHEN Trip_Status = 'Completed' THEN Fare * Capacity_Used ELSE 0 END) as total_earnings,
                COUNT(CASE WHEN Trip_Status IN ('Pending', 'Confirmed') THEN 1 END) as active_trips
            FROM Trip 
            WHERE Creator_ID = ?
        ");
        $driver_stats_stmt->execute([$user_id]);
        $stats = $driver_stats_stmt->fetch();

        // Get vehicles
        $vehicle_stmt = $pdo->prepare("
            SELECT Vehicle_Num, Car_Model, Car_Type, Capacity, License_Num 
            FROM Private_Car 
            WHERE NID = ?
        ");
        $vehicle_stmt->execute([$user_id]);
        $user['vehicles'] = $vehicle_stmt->fetchAll();
    } else {
        // Student/Rider stats
        $rider_stats_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_trips,
                COUNT(CASE WHEN t.Trip_Status = 'Completed' THEN 1 END) as completed_trips,
                COUNT(CASE WHEN t.Trip_Status IN ('Pending', 'Confirmed') AND tj.Status = 'Accepted' THEN 1 END) as active_bookings
            FROM Trip_Join tj
            JOIN Trip t ON tj.Trip_ID = t.Trip_ID
            WHERE tj.NID = ?
        ");
        $rider_stats_stmt->execute([$user_id]);
        $stats = $rider_stats_stmt->fetch();
    }

    // Get user rating
    $rating_stmt = $pdo->prepare("
        SELECT AVG(Rating) as avg_rating, COUNT(*) as review_count
        FROM Review 
        WHERE Rated_ID = ?
    ");
    $rating_stmt->execute([$user_id]);
    $rating_data = $rating_stmt->fetch();

    $user['stats'] = $stats;
    $user['rating'] = [
        'average' => $rating_data['review_count'] > 0 ? round($rating_data['avg_rating'], 1) : 5.0,
        'count' => $rating_data['review_count']
    ];

    json_response(true, 'Profile loaded successfully', $user);

} catch (PDOException $e) {
    error_log("Get user profile error: " . $e->getMessage());
    json_response(false, 'Failed to load profile');
} catch (Exception $e) {
    error_log("Get user profile error: " . $e->getMessage());
    json_response(false, 'Failed to load profile');
}
?>