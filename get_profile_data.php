<?php
require_once 'Backend/onfig.php';

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Check if user is logged in
    if (!is_logged_in()) {
        throw new Exception('User not logged in');
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Get user profile data
    $profile_query = "SELECT u.Name, u.Phone, u.Emergency_Contact, u.Area, u.PS, u.Gender, u.Email, 
                            ru.Std_ID,
                            CASE 
                                WHEN ru.NID IS NOT NULL THEN 'student'
                                WHEN rt.NID IS NOT NULL THEN 'driver'
                                ELSE 'user'
                            END as user_type
                     FROM User u
                     LEFT JOIN Rider_User ru ON u.NID = ru.NID
                     LEFT JOIN Rider_Taker rt ON u.NID = rt.NID
                     WHERE u.NID = ?";
    
    $stmt = $pdo->prepare($profile_query);
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profile) {
        throw new Exception('User profile not found');
    }
    
    // Get user statistics
    $stats_query = "SELECT 
                        COUNT(DISTINCT t1.Trip_ID) as total_trips,
                        COUNT(DISTINCT CASE WHEN t1.Trip_Status = 'Completed' THEN t1.Trip_ID END) as completed_trips,
                        COALESCE(SUM(CASE WHEN t1.Trip_Status = 'Completed' AND t1.Creator_ID = ? THEN t1.Fare * t1.Capacity_Used END), 0) as total_earnings,
                        COALESCE(AVG(r.Rating), 5.0) as rating
                    FROM (
                        SELECT Trip_ID, Trip_Status, Creator_ID, Fare, Capacity_Used FROM Trip WHERE Creator_ID = ?
                        UNION
                        SELECT t.Trip_ID, t.Trip_Status, t.Creator_ID, t.Fare, t.Capacity_Used 
                        FROM Trip t 
                        JOIN Trip_Join tj ON t.Trip_ID = tj.Trip_ID 
                        WHERE tj.NID = ?
                    ) t1
                    LEFT JOIN Review r ON r.Rated_ID = ?";
    
    $stmt = $pdo->prepare($stats_query);
    $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format the rating to one decimal place
    $stats['rating'] = number_format($stats['rating'], 1);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'profile' => $profile,
            'stats' => $stats
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>