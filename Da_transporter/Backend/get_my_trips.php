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
    
    //User_Trip_History view
    $trips_query = "SELECT 
                        uth.*,
                        u_creator.Name as Creator_Name,
                        u_creator.Phone as Creator_Phone,
                        pc.Car_Model,
                        pc.Car_Type,
                        CASE 
                            WHEN pc.Vehicle_Num IS NOT NULL THEN CONCAT(pc.Car_Model, ' (', pc.Car_Type, ')')
                            ELSE 'Bus Service'
                        END as Vehicle_Type,
                        bb.Book_Code,
                        bb.Booking_Status,
                        bb.Payment_Status
                    FROM User_Trip_History uth
                    LEFT JOIN User u_creator ON uth.Trip_ID IN (
                        SELECT Trip_ID FROM Trip WHERE Creator_ID = u_creator.NID
                    )
                    LEFT JOIN Trip t ON uth.Trip_ID = t.Trip_ID
                    LEFT JOIN Private_Car pc ON t.Vehicle_Num = pc.Vehicle_Num
                    LEFT JOIN Bus_Booking bb ON t.Trip_ID = bb.Trip_ID AND bb.NID = uth.NID
                    WHERE uth.NID = ?
                    ORDER BY uth.Date DESC, uth.Time DESC";
    
    $stmt = $pdo->prepare($trips_query);
    $stmt->execute([$user_id]);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get additional trip details for each trip
    foreach ($trips as &$trip) {
        // Get creator info if not already available
        if (empty($trip['Creator_Name'])) {
            $creator_query = "SELECT u.Name, u.Phone FROM Trip t JOIN User u ON t.Creator_ID = u.NID WHERE t.Trip_ID = ?";
            $creator_stmt = $pdo->prepare($creator_query);
            $creator_stmt->execute([$trip['Trip_ID']]);
            $creator = $creator_stmt->fetch(PDO::FETCH_ASSOC);
            if ($creator) {
                $trip['Creator_Name'] = $creator['Name'];
                $trip['Creator_Phone'] = $creator['Phone'];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $trips
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>