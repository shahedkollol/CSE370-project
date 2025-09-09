<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method');
}

// Check if user is logged in
if (!is_logged_in()) {
    json_response(false, 'Please login first');
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $trip_id = intval($input['trip_id'] ?? 0);

    if ($trip_id <= 0) {
        json_response(false, 'Invalid trip ID');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Check if trip exists and is available
    $trip_stmt = $pdo->prepare("
        SELECT 
            t.Trip_ID,
            t.Creator_ID,
            t.Trip_Status,
            t.Capacity_Used,
            t.Start_Point,
            t.Destination,
            t.Date,
            t.Time,
            COALESCE(pc.Capacity, 4) as Total_Capacity,
            u.Name as Creator_Name
        FROM Trip t
        LEFT JOIN Private_Car pc ON t.Vehicle_Num = pc.Vehicle_Num
        LEFT JOIN User u ON t.Creator_ID = u.NID
        WHERE t.Trip_ID = ?
    ");
    $trip_stmt->execute([$trip_id]);
    $trip = $trip_stmt->fetch();

    if (!$trip) {
        $pdo->rollBack();
        json_response(false, 'Trip not found');
    }

    // Check if trip is still available
    if ($trip['Trip_Status'] !== 'Pending') {
        $pdo->rollBack();
        json_response(false, 'Trip is no longer available');
    }

    // Check if user is trying to join their own trip
    if ($trip['Creator_ID'] === $user_id) {
        $pdo->rollBack();
        json_response(false, 'You cannot join your own trip');
    }

    // Check capacity
    $available_seats = $trip['Total_Capacity'] - $trip['Capacity_Used'] - 1; // -1 for driver
    if ($available_seats <= 0) {
        $pdo->rollBack();
        json_response(false, 'No seats available');
    }

    // Check if user already joined this trip
    $existing_stmt = $pdo->prepare("
        SELECT Join_ID, Status FROM Trip_Join 
        WHERE NID = ? AND Trip_ID = ?
    ");
    $existing_stmt->execute([$user_id, $trip_id]);
    $existing_join = $existing_stmt->fetch();

    if ($existing_join) {
        $pdo->rollBack();
        $status = $existing_join['Status'];
        if ($status === 'Requested') {
            json_response(false, 'You have already requested to join this trip');
        } elseif ($status === 'Accepted') {
            json_response(false, 'You have already joined this trip');
        } else {
            json_response(false, 'Your previous request was rejected');
        }
    }

    // Check if trip is in the past
    $trip_datetime = new DateTime($trip['Date'] . ' ' . $trip['Time']);
    $now = new DateTime();
    if ($trip_datetime <= $now) {
        $pdo->rollBack();
        json_response(false, 'Cannot join past trips');
    }

    // Insert join request
    $join_stmt = $pdo->prepare("
        INSERT INTO Trip_Join (NID, Trip_ID, Status) 
        VALUES (?, ?, 'Requested')
    ");
    $join_stmt->execute([$user_id, $trip_id]);

    // Create notification for driver
    $driver_notification = $pdo->prepare("
        INSERT INTO Notifications (User_ID, Message, Status) 
        VALUES (?, ?, 'Unread')
    ");
    $user_name = $_SESSION['user_name'];
    $driver_message = "{$user_name} has requested to join your trip from {$trip['Start_Point']} to {$trip['Destination']} on {$trip['Date']}.";
    $driver_notification->execute([$trip['Creator_ID'], $driver_message]);

    // Create notification for user
    $user_notification = $pdo->prepare("
        INSERT INTO Notifications (User_ID, Message, Status) 
        VALUES (?, ?, 'Unread')
    ");
    $user_message = "Your request to join the trip from {$trip['Start_Point']} to {$trip['Destination']} has been sent to {$trip['Creator_Name']}.";
    $user_notification->execute([$user_id, $user_message]);

    // Commit transaction
    $pdo->commit();

    json_response(true, 'Join request sent successfully! The driver will be notified.', [
        'trip_id' => $trip_id
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Join ride error: " . $e->getMessage());
    json_response(false, 'Failed to join ride. Please try again.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Join ride error: " . $e->getMessage());
    json_response(false, 'Failed to join ride. Please try again.');
}
?>