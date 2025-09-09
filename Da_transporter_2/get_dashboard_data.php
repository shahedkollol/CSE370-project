<?php
require_once 'config.php';

// Check if user is logged in
if (!is_logged_in()) {
    json_response(false, 'Please login first');
}

try {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'];
    
    // Initialize stats array
    $stats = [
        'total_trips' => 0,
        'active_bookings' => 0,
        'total_earnings' => 0,
        'rating' => '5.0',
        'available_rides' => 0,
        'your_rides' => 0,
        'bus_routes' => 0,
        'unread_notifications' => 0,
        'recent_activities' => []
    ];

    // Get total trips for user
    if ($user_type === 'driver') {
        // For drivers: count trips they created
        $trip_stmt = $pdo->prepare("
            SELECT COUNT(*) as total_trips,
                   SUM(CASE WHEN Trip_Status = 'Completed' THEN Fare * Capacity_Used ELSE 0 END) as total_earnings,
                   COUNT(CASE WHEN Trip_Status IN ('Pending', 'Confirmed', 'In_Progress') THEN 1 END) as your_rides
            FROM Trip 
            WHERE Creator_ID = ?
        ");
        $trip_stmt->execute([$user_id]);
        $trip_data = $trip_stmt->fetch();
        
        $stats['total_trips'] = $trip_data['total_trips'] ?? 0;
        $stats['total_earnings'] = $trip_data['total_earnings'] ?? 0;
        $stats['your_rides'] = $trip_data['your_rides'] ?? 0;
    } else {
        // For students: count trips they joined
        $trip_stmt = $pdo->prepare("
            SELECT COUNT(*) as total_trips
            FROM Trip_Join tj
            JOIN Trip t ON tj.Trip_ID = t.Trip_ID
            WHERE tj.NID = ?
        ");
        $trip_stmt->execute([$user_id]);
        $trip_data = $trip_stmt->fetch();
        
        $stats['total_trips'] = $trip_data['total_trips'] ?? 0;
    }

    // Get active bookings (for all users)
    $active_stmt = $pdo->prepare("
        SELECT COUNT(*) as active_bookings
        FROM Trip_Join tj
        JOIN Trip t ON tj.Trip_ID = t.Trip_ID
        WHERE tj.NID = ? AND t.Trip_Status IN ('Pending', 'Confirmed', 'In_Progress')
    ");
    $active_stmt->execute([$user_id]);
    $active_data = $active_stmt->fetch();
    $stats['active_bookings'] = $active_data['active_bookings'] ?? 0;

    // Get available rides count
    $available_stmt = $pdo->query("
        SELECT COUNT(*) as available_rides
        FROM Active_Trips 
        WHERE Trip_Status = 'Pending' AND Capacity_Used < Total_Capacity - 1
    ");
    $available_data = $available_stmt->fetch();
    $stats['available_rides'] = $available_data['available_rides'] ?? 0;

    // Get bus routes count
    $bus_stmt = $pdo->query("SELECT COUNT(*) as bus_routes FROM Bus");
    $bus_data = $bus_stmt->fetch();
    $stats['bus_routes'] = $bus_data['bus_routes'] ?? 8; // Default value

    // Get unread notifications
    $notification_stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_notifications
        FROM Notifications 
        WHERE User_ID = ? AND Status = 'Unread'
    ");
    $notification_stmt->execute([$user_id]);
    $notification_data = $notification_stmt->fetch();
    $stats['unread_notifications'] = $notification_data['unread_notifications'] ?? 0;

    // Get user rating (from reviews)
    $rating_stmt = $pdo->prepare("
        SELECT AVG(Rating) as avg_rating, COUNT(*) as review_count
        FROM Review 
        WHERE Rated_ID = ?
    ");
    $rating_stmt->execute([$user_id]);
    $rating_data = $rating_stmt->fetch();
    
    if ($rating_data['review_count'] > 0) {
        $stats['rating'] = number_format($rating_data['avg_rating'], 1);
    }

    // Get recent activities
    $activities = [];
    
    if ($user_type === 'driver') {
        // Driver activities
        $activity_stmt = $pdo->prepare("
            SELECT 
                CONCAT('New booking request for trip to ', t.Destination) as message,
                tj.Joined_At as time
            FROM Trip_Join tj
            JOIN Trip t ON tj.Trip_ID = t.Trip_ID
            WHERE t.Creator_ID = ? AND tj.Status = 'Requested'
            ORDER BY tj.Joined_At DESC
            LIMIT 3
            
            UNION ALL
            
            SELECT 
                CONCAT('Trip to ', t.Destination, ' completed') as message,
                t.Updated as time
            FROM Trip t
            WHERE t.Creator_ID = ? AND t.Trip_Status = 'Completed'
            ORDER BY t.Updated DESC
            LIMIT 2
        ");
        $activity_stmt->execute([$user_id, $user_id]);
    } else {
        // Student activities
        $activity_stmt = $pdo->prepare("
            SELECT 
                CASE 
                    WHEN tj.Status = 'Accepted' THEN CONCAT('Your booking for trip to ', t.Destination, ' was accepted')
                    WHEN tj.Status = 'Rejected' THEN CONCAT('Your booking for trip to ', t.Destination, ' was rejected')
                    WHEN t.Trip_Status = 'Completed' THEN CONCAT('Trip to ', t.Destination, ' completed')
                    ELSE CONCAT('Booking request sent for trip to ', t.Destination)
                END as message,
                GREATEST(tj.Joined_At, t.Updated) as time
            FROM Trip_Join tj
            JOIN Trip t ON tj.Trip_ID = t.Trip_ID
            WHERE tj.NID = ?
            ORDER BY time DESC
            LIMIT 5
        ");
        $activity_stmt->execute([$user_id]);
    }
    
    while ($activity = $activity_stmt->fetch()) {
        $activities[] = [
            'message' => $activity['message'],
            'time' => date('M j, g:i A', strtotime($activity['time']))
        ];
    }
    
    // If no activities, add default ones
    if (empty($activities)) {
        $activities = [
            ['message' => 'Welcome to DA Transporter!', 'time' => 'Just now'],
            ['message' => 'Complete your profile to get started', 'time' => '5 min ago']
        ];
    }
    
    $stats['recent_activities'] = $activities;

    json_response(true, 'Dashboard data loaded', $stats);

} catch (PDOException $e) {
    error_log("Dashboard data error: " . $e->getMessage());
    json_response(false, 'Failed to load dashboard data');
} catch (Exception $e) {
    error_log("Dashboard data error: " . $e->getMessage());
    json_response(false, 'Failed to load dashboard data');
}
?>