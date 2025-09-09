<?php
require_once 'config.php';

// Check if user is logged in
if (!is_logged_in()) {
    json_response(false, 'Please login first');
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Get available rides (excluding user's own rides)
    $stmt = $pdo->prepare("
        SELECT 
            t.Trip_ID,
            t.Creator_ID,
            u.Name as Creator_Name,
            u.Phone as Contact,
            t.Req_Type,
            t.Trip_Status,
            t.Start_Point,
            t.Destination,
            t.Fare,
            t.Capacity_Used,
            t.Date,
            t.Time,
            COALESCE(pc.Capacity, 4) as Total_Capacity,
            pc.Car_Model,
            pc.Car_Type,
            r.Stop1,
            r.Stop2,
            r.Distance
        FROM Trip t
        JOIN User u ON t.Creator_ID = u.NID
        LEFT JOIN Private_Car pc ON t.Vehicle_Num = pc.Vehicle_Num
        LEFT JOIN Route r ON t.Route_ID = r.Route_ID
        WHERE t.Trip_Status = 'Pending'
        AND t.Creator_ID != ?
        AND (COALESCE(pc.Capacity, 4) - 1) > t.Capacity_Used
        AND CONCAT(t.Date, ' ', t.Time) > NOW()
        ORDER BY t.Date ASC, t.Time ASC
    ");
    $stmt->execute([$user_id]);
    $rides = $stmt->fetchAll();

    json_response(true, 'Available rides loaded', $rides);

} catch (PDOException $e) {
    error_log("Get available rides error: " . $e->getMessage());
    json_response(false, 'Failed to load available rides');
} catch (Exception $e) {
    error_log("Get available rides error: " . $e->getMessage());
    json_response(false, 'Failed to load available rides');
}
?>