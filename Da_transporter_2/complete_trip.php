<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method');
}

// Check if user is logged in and is a driver
if (!is_logged_in()) {
    json_response(false, 'Please login first');
}

if ($_SESSION['user_type'] !== 'driver') {
    json_response(false, 'Only drivers can complete trips');
}

try {
    $user_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    $trip_id = intval($input['trip_id'] ?? 0);
    
    if ($trip_id <= 0) {
        json_response(false, 'Invalid trip ID');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Verify trip belongs to driver and can be completed
    $trip_stmt = $pdo->prepare("
        SELECT 
            Trip_ID,
            Creator_ID,
            Trip_Status,
            Start_Point,
            Destination,
            Date,
            Time,
            Fare,
            Capacity_Used
        FROM Trip 
        WHERE Trip_ID = ? AND Creator_ID = ?
    ");
    $trip_stmt->execute([$trip_id, $user_id]);
    $trip = $trip_stmt->fetch();

    if (!$trip) {
        $pdo->rollBack();
        json_response(false, 'Trip not found or unauthorized');
    }

    if ($trip['Trip_Status'] === 'Completed') {
        $pdo->rollBack();
        json_response(false, 'Trip is already completed');
    }

    if ($trip['Trip_Status'] === 'Cancelled') {
        $pdo->rollBack();
        json_response(false, 'Cannot complete cancelled trip');
    }

    // Update trip status to completed
    $complete_stmt = $pdo->prepare("
        UPDATE Trip 
        SET Trip_Status = 'Completed', Updated = NOW() 
        WHERE Trip_ID = ?
    ");
    $complete_stmt->execute([$trip_id]);

    // Update all accepted trip joins to completed
    $update_joins_stmt = $pdo->prepare("
        UPDATE Trip_Join 
        SET Status = 'Completed' 
        WHERE Trip_ID = ? AND Status = 'Accepted'
    ");
    $update_joins_stmt->execute([$trip_id]);

    // Get passengers who completed the trip
    $passengers_stmt = $pdo->prepare("
        SELECT tj.NID, u.Name 
        FROM Trip_Join tj
        JOIN User u ON tj.NID = u.NID
        WHERE tj.Trip_ID = ? AND tj.Status = 'Completed'
    ");
    $passengers_stmt->execute([$trip_id]);
    $passengers = $passengers_stmt->fetchAll();

    // Create notifications for passengers
    $notification_stmt = $pdo->prepare("
        INSERT INTO Notifications (User_ID, Message, Status) 
        VALUES (?, ?, 'Unread')
    ");

    foreach ($passengers as $passenger) {
        $passenger_message = "Your trip from {$trip['Start_Point']} to {$trip['Destination']} has been completed. Thank you for traveling with us!";
        $notification_stmt->execute([$passenger['NID'], $passenger_message]);
    }

    // Create notification for driver
    $driver_message = "Trip from {$trip['Start_Point']} to {$trip['Destination']} completed successfully. Earnings: ৳" . ($trip['Fare'] * $trip['Capacity_Used']);
    $notification_stmt->execute([$user_id, $driver_message]);

    // Commit transaction
    $pdo->commit();

    json_response(true, 'Trip completed successfully!', [
        'trip_id' => $trip_id,
        'earnings' => $trip['Fare'] * $trip['Capacity_Used'],
        'passengers' => count($passengers)
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Complete trip error: " . $e->getMessage());
    json_response(false, 'Failed to complete trip');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Complete trip error: " . $e->getMessage());
    json_response(false, 'Failed to complete trip');
}
?>