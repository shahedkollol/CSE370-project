<?php
require_once 'Backend/config.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    json_response(false, "Please login to create booking");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, "Invalid request method");
}

try {
    $bus_id = sanitize_input($_POST['bus_id']);
    $route_id = sanitize_input($_POST['route_id'] ?? '');
    $pickup_point = sanitize_input($_POST['pickup_point']);
    $destination = sanitize_input($_POST['destination']);
    $travel_date = sanitize_input($_POST['travel_date']);
    $user_id = $_SESSION['user_id'];
    
    if (!$bus_id || !$pickup_point || !$destination || !$travel_date) {
        json_response(false, "Bus, pickup point, destination and travel date are required");
    }
    
    // Validate date is not in the past
    if (strtotime($travel_date) < strtotime(date('Y-m-d'))) {
        json_response(false, "Cannot book for past dates");
    }
    
    // Get bus information
    $stmt = $pdo->prepare("SELECT * FROM Bus WHERE Bus_ID = ?");
    $stmt->execute([$bus_id]);
    $bus = $stmt->fetch();
    
    if (!$bus) {
        json_response(false, "Invalid bus");
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Get user contact for trip
        $userStmt = $pdo->prepare("SELECT Phone FROM User WHERE NID = ?");
        $userStmt->execute([$user_id]);
        $user = $userStmt->fetch();
        
        if (!$user) {
            throw new Exception("User not found");
        }
        
        // 
        $baseFare = 500; 
        switch($bus['Bus_Type']) {
            case 'AC':
                $fare = $baseFare * 1.5;
                break;
            case 'Deluxe':
                $fare = $baseFare * 1.3;
                break;
            default:
                $fare = $baseFare;
        }
        
        // Create trip for bus booking
        $stmt = $pdo->prepare("
            INSERT INTO Trip (Creator_ID, Contact, Req_Type, Trip_Status, Start_Point, Destination, 
                            Fare, Capacity_Used, Date, Route_ID, Bus_ID, Created) 
            VALUES (?, ?, 'Bus_Booking', 'Confirmed', ?, ?, ?, 1, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $user['Phone'],
            $pickup_point,
            $destination,
            $fare,
            $travel_date,
            $route_id ?: null,
            $bus_id
        ]);
        
        $trip_id = $pdo->lastInsertId();
        
        // Generate booking code
        $book_code = generate_booking_code();
        
        // Create bus booking record
        $stmt = $pdo->prepare("
            INSERT INTO Bus_Booking (Book_Code, Trip_ID, NID, Book_Slot, Booking_Status, Payment_Status, Booked_At) 
            VALUES (?, ?, ?, 1, 'Booked', 'Pending', NOW())
        ");
        $stmt->execute([$book_code, $trip_id, $user_id]);
        
        // Commit transaction
        $pdo->commit();
        
        json_response(true, "Bus booking created successfully", [
            'book_code' => $book_code,
            'trip_id' => $trip_id,
            'fare' => $fare,
            'bus_id' => $bus_id,
            'pickup_point' => $pickup_point,
            'destination' => $destination,
            'travel_date' => $travel_date
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
} catch(Exception $e) {
    json_response(false, "Error creating booking: " . $e->getMessage());
}
?>