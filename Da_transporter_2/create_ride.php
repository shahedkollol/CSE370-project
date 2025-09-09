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
    json_response(false, 'Only drivers can create rides');
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Sanitize input data
    $start_point = sanitize_input($_POST['start_point']);
    $destination = sanitize_input($_POST['destination']);
    $trip_date = $_POST['trip_date'];
    $trip_time = $_POST['trip_time'];
    $request_type = $_POST['request_type'];
    $vehicle_num = sanitize_input($_POST['vehicle_num']);
    $fare = floatval($_POST['fare']);
    $available_seats = intval($_POST['available_seats']);
    $contact = sanitize_input($_POST['contact'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    $stop1 = sanitize_input($_POST['stop1'] ?? '');
    $stop2 = sanitize_input($_POST['stop2'] ?? '');

    // Validate required fields
    if (empty($start_point) || empty($destination) || empty($trip_date) || 
        empty($trip_time) || empty($request_type) || empty($vehicle_num) || 
        $fare <= 0 || $available_seats <= 0) {
        json_response(false, 'Please fill in all required fields');
    }

    // Validate trip date (must be today or future)
    $trip_datetime = new DateTime($trip_date . ' ' . $trip_time);
    $now = new DateTime();
    if ($trip_datetime <= $now) {
        json_response(false, 'Trip date and time must be in the future');
    }

    // Validate request type
    if (!in_array($request_type, ['Ride_Share', 'Private_Hire'])) {
        json_response(false, 'Invalid request type');
    }

    // Verify vehicle belongs to user
    $vehicle_stmt = $pdo->prepare("
        SELECT Vehicle_Num, Capacity, Car_Model, Car_Type 
        FROM Private_Car 
        WHERE Vehicle_Num = ? AND NID = ?
    ");
    $vehicle_stmt->execute([$vehicle_num, $user_id]);
    $vehicle = $vehicle_stmt->fetch();

    if (!$vehicle) {
        json_response(false, 'Vehicle not found or does not belong to you');
    }

    // Validate available seats against vehicle capacity
    if ($available_seats >= $vehicle['Capacity']) {
        json_response(false, 'Available seats cannot exceed vehicle capacity minus driver seat');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Check for existing route or create new one
    $route_id = null;
    
    // Try to find existing route
    $route_stmt = $pdo->prepare("
        SELECT Route_ID FROM Route 
        WHERE Start = ? AND End = ? 
        AND (Stop1 IS NULL OR Stop1 = ?) 
        AND (Stop2 IS NULL OR Stop2 = ?)
        LIMIT 1
    ");
    $route_stmt->execute([$start_point, $destination, $stop1 ?: null, $stop2 ?: null]);
    $existing_route = $route_stmt->fetch();

    if ($existing_route) {
        $route_id = $existing_route['Route_ID'];
    } else {
        // Create new route
        $new_route_stmt = $pdo->prepare("
            INSERT INTO Route (Start, End, Stop1, Stop2, Distance, Estimated_Duration) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        // Calculate estimated distance and duration (simplified)
        $estimated_distance = rand(5, 50); // Random between 5-50 km
        $estimated_duration = $estimated_distance * 2; // 2 minutes per km estimate
        
        $new_route_stmt->execute([
            $start_point, 
            $destination, 
            $stop1 ?: null, 
            $stop2 ?: null, 
            $estimated_distance, 
            $estimated_duration
        ]);
        $route_id = $pdo->lastInsertId();
    }

    // Create trip
    $trip_stmt = $pdo->prepare("
        INSERT INTO Trip (
            Creator_ID, Contact, Req_Type, Trip_Status, Start_Point, Destination,
            Fare, Capacity_Used, Time, Date, Route_ID, Vehicle_Num
        ) VALUES (?, ?, ?, 'Pending', ?, ?, ?, 0, ?, ?, ?, ?)
    ");
    
    $trip_stmt->execute([
        $user_id,
        $contact,
        $request_type,
        $start_point,
        $destination,
        $fare,
        $trip_time,
        $trip_date,
        $route_id,
        $vehicle_num
    ]);

    $trip_id = $pdo->lastInsertId();

    // Create notification for the driver
    $notification_stmt = $pdo->prepare("
        INSERT INTO Notifications (User_ID, Message, Status) 
        VALUES (?, ?, 'Unread')
    ");
    $trip_message = "Your ride from {$start_point} to {$destination} has been created successfully.";
    $notification_stmt->execute([$user_id, $trip_message]);

    // Commit transaction
    $pdo->commit();

    json_response(true, 'Ride created successfully!', [
        'trip_id' => $trip_id,
        'redirect' => 'my_trips.html'
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Create ride error: " . $e->getMessage());
    json_response(false, 'Failed to create ride. Please try again.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Create ride error: " . $e->getMessage());
    json_response(false, 'Failed to create ride. Please try again.');
}
?>