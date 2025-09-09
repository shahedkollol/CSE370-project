<?php
session_start(); // Add this line - it was missing!
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    json_response(false, 'Admin access required. Please login again.');
}

try {
    // System stats
    $stats = [];
    
    // Total users
    $user_stmt = $pdo->query("SELECT COUNT(*) as total_users FROM User");
    $stats['total_users'] = $user_stmt->fetchColumn();
    
    // Total drivers
    $driver_stmt = $pdo->query("SELECT COUNT(*) as total_drivers FROM Rider_Taker");
    $stats['total_drivers'] = $driver_stmt->fetchColumn();
    
    // Total students
    $student_stmt = $pdo->query("SELECT COUNT(*) as total_students FROM Rider_User");
    $stats['total_students'] = $student_stmt->fetchColumn();
    
    // Total trips
    $trip_stmt = $pdo->query("SELECT COUNT(*) as total_trips FROM Trip");
    $stats['total_trips'] = $trip_stmt->fetchColumn();
    
    // Active trips
    $active_trip_stmt = $pdo->query("
        SELECT COUNT(*) as active_trips 
        FROM Trip 
        WHERE Trip_Status IN ('Pending', 'Confirmed', 'In_Progress')
    ");
    $stats['active_trips'] = $active_trip_stmt->fetchColumn();
    
    // Completed trips
    $completed_trip_stmt = $pdo->query("
        SELECT COUNT(*) as completed_trips 
        FROM Trip 
        WHERE Trip_Status = 'Completed'
    ");
    $stats['completed_trips'] = $completed_trip_stmt->fetchColumn();
    
    // Total vehicles
    $vehicle_stmt = $pdo->query("SELECT COUNT(*) as total_vehicles FROM Private_Car");
    $stats['total_vehicles'] = $vehicle_stmt->fetchColumn();
    
    // Total earnings
    $earnings_stmt = $pdo->query("
        SELECT SUM(Fare * Capacity_Used) as total_earnings 
        FROM Trip 
        WHERE Trip_Status = 'Completed'
    ");
    $stats['total_earnings'] = $earnings_stmt->fetchColumn() ?? 0;
    
    // Recent registrations (last 7 days)
    $recent_users_stmt = $pdo->query("
        SELECT COUNT(*) as recent_users 
        FROM User 
        WHERE Created >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stats['recent_users'] = $recent_users_stmt->fetchColumn();
    
    // Get recent activities
    $activities_stmt = $pdo->query("
        SELECT 
            CONCAT(u.Name, ' created a new trip from ', t.Start_Point, ' to ', t.Destination) as message,
            t.Created as time,
            'trip_created' as type
        FROM Trip t
        JOIN User u ON t.Creator_ID = u.NID
        WHERE t.Created >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        
        UNION ALL
        
        SELECT 
            CONCAT(u.Name, ' joined the platform') as message,
            u.Created as time,
            'user_registered' as type
        FROM User u
        WHERE u.Created >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        
        ORDER BY time DESC
        LIMIT 10
    ");
    $activities = $activities_stmt->fetchAll();
    
    // Top drivers by trips
    $top_drivers_stmt = $pdo->query("
        SELECT 
            u.Name,
            u.Phone,
            COUNT(t.Trip_ID) as trip_count,
            AVG(COALESCE(r.Rating, 5)) as avg_rating
        FROM User u
        JOIN Rider_Taker rt ON u.NID = rt.NID
        LEFT JOIN Trip t ON u.NID = t.Creator_ID
        LEFT JOIN Review r ON u.NID = r.Rated_ID
        GROUP BY u.NID
        ORDER BY trip_count DESC
        LIMIT 5
    ");
    $top_drivers = $top_drivers_stmt->fetchAll();
    
    // Recent feedback/reviews
    $recent_reviews_stmt = $pdo->query("
        SELECT 
            ur.Name as rater_name,
            ud.Name as driver_name,
            r.Rating,
            r.Comment,
            r.Created
        FROM Review r
        JOIN User ur ON r.Rater_ID = ur.NID
        JOIN User ud ON r.Rated_ID = ud.NID
        ORDER BY r.Created DESC
        LIMIT 5
    ");
    $recent_reviews = $recent_reviews_stmt->fetchAll();

    json_response(true, 'Admin dashboard data loaded', [
        'stats' => $stats,
        'activities' => $activities,
        'top_drivers' => $top_drivers,
        'recent_reviews' => $recent_reviews
    ]);

} catch (PDOException $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    json_response(false, 'Failed to load admin dashboard data');
} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    json_response(false, 'Failed to load admin dashboard data');
}
?>