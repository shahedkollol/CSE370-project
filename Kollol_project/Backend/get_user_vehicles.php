<?php
require_once 'Backend/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    json_response(false, 'Please login first');
}

try {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'];
    
    // Only drivers can have vehicles
    if ($user_type !== 'driver') {
        json_response(false, 'Only drivers can have vehicles');
    }

    // Get user's vehicles
    $stmt = $pdo->prepare("
        SELECT 
            pc.Vehicle_Num,
            pc.Car_Model,
            pc.Capacity,
            pc.License_Num,
            pc.Car_Type,
            pc.Owner_ID,
            pc.Created
        FROM Private_Car pc
        WHERE pc.NID = ?
        ORDER BY pc.Created ASC
    ");
    $stmt->execute([$user_id]);
    $vehicles = $stmt->fetchAll();

    json_response(true, 'Vehicles loaded successfully', $vehicles);

} catch (PDOException $e) {
    error_log("Get vehicles error: " . $e->getMessage());
    json_response(false, 'Failed to load vehicles');
} catch (Exception $e) {
    error_log("Get vehicles error: " . $e->getMessage());
    json_response(false, 'Failed to load vehicles');
}
?>