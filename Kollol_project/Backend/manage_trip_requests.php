<?php
require_once 'Backend/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method');
}

// Check if user is logged in and is a driver
if (!is_logged_in()) {
    json_response(false, 'Please login first');
}

if ($_SESSION['user_type'] !== 'driver') {
    json_response(false, 'Only drivers can manage trip requests');
}

try {
    $user_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    $action = $input['action'] ?? '';
    $join_id = intval($input['join_id'] ?? 0);
    
    if (!in_array($action, ['accept', 'reject']) || $join_id <= 0) {
        json_response(false, 'Invalid action or join ID');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Verify the join request belongs to driver's trip
    $stmt = $pdo->prepare("
        SELECT 
            tj.Join_ID,
            tj.NID as Passenger_ID,
            tj.Trip_ID,
            tj.Status,
            t.Creator_ID,
            t.Trip_Status,
            t.Capacity_Used,
            t.Start_Point,
            t.Destination,
            t.Date,
            COALESCE(pc.Capacity, 4) as Total_Capacity,
            u.Name as Passenger_Name
        FROM Trip_Join tj
        JOIN Trip t ON tj.Trip_ID = t.Trip_ID
        LEFT JOIN Private_Car pc ON t.Vehicle_Num = pc.Vehicle_Num
        LEFT JOIN User u ON tj.NID = u.NID
        WHERE tj.Join_ID = ? AND t.Creator_ID = ?
    ");
    $stmt->execute([$join_id, $user_id]);
    $join_request = $stmt->fetch();

    if (!$join_request) {
        $pdo->rollBack();
        json_response(false, 'Join request not found or unauthorized');
    }

    if ($join_request['Status'] !== 'Requested') {
        $pdo->rollBack();
        json_response(false, 'Request has already been processed');
    }

    if ($join_request['Trip_Status'] !== 'Pending') {
        $pdo->rollBack();
        json_response(false, 'Trip is no longer accepting passengers');
    }

    $new_status = ($action === 'accept') ? 'Accepted' : 'Rejected';

    // If accepting, check capacity
    if ($action === 'accept') {
        $available_seats = $join_request['Total_Capacity'] - $join_request['Capacity_Used'] - 1; // -1 for driver
        if ($available_seats <= 0) {
            $pdo->rollBack();
            json_response(false, 'No seats available');
        }
    }

    // Update join request status
    $update_stmt = $pdo->prepare("
        UPDATE Trip_Join 
        SET Status = ? 
        WHERE Join_ID = ?
    ");
    $update_stmt->execute([$new_status, $join_id]);

    // Create notifications
    $notification_stmt = $pdo->prepare("
        INSERT INTO Notifications (User_ID, Message, Status) 
        VALUES (?, ?, 'Unread')
    ");

    if ($action === 'accept') {
        // Notify passenger about acceptance
        $passenger_message = "Your request to join the trip from {$join_request['Start_Point']} to {$join_request['Destination']} on {$join_request['Date']} has been accepted!";
        $notification_stmt->execute([$join_request['Passenger_ID'], $passenger_message]);
        
        // Update trip status to confirmed if it was pending
        if ($join_request['Trip_Status'] === 'Pending') {
            $update_trip = $pdo->prepare("UPDATE Trip SET Trip_Status = 'Confirmed' WHERE Trip_ID = ?");
            $update_trip->execute([$join_request['Trip_ID']]);
        }
    } else {
        // Notify passenger about rejection
        $passenger_message = "Your request to join the trip from {$join_request['Start_Point']} to {$join_request['Destination']} on {$join_request['Date']} has been declined.";
        $notification_stmt->execute([$join_request['Passenger_ID'], $passenger_message]);
    }

    // Commit transaction
    $pdo->commit();

    $message = $action === 'accept' ? 'Passenger request accepted' : 'Passenger request rejected';
    json_response(true, $message, [
        'action' => $action,
        'join_id' => $join_id
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Manage trip request error: " . $e->getMessage());
    json_response(false, 'Failed to process request');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Manage trip request error: " . $e->getMessage());
    json_response(false, 'Failed to process request');
}
?>