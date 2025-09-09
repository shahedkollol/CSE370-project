<?php
require_once 'config.php';

// Check if user is logged in
if (!is_logged_in()) {
    json_response(false, "Please login first");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, "Invalid request method");
}

try {
    $bus_id = sanitize_input($_POST['bus_id'] ?? '');
    $nid = sanitize_input($_POST['nid'] ?? '');

    if (empty($bus_id) || empty($nid)) {
        json_response(false, "Bus ID and NID are required");
    }

    // Verify bus exists and is available
    $bus_stmt = $pdo->prepare("SELECT * FROM Bus WHERE Bus_ID = ?");
    $bus_stmt->execute([$bus_id]);
    $bus = $bus_stmt->fetch();

    if (!$bus) {
        json_response(false, "Bus not found");
    }

    // Check if provided NID
    $user_stmt = $pdo->prepare("SELECT * FROM User WHERE NID = ?");
    $user_stmt->execute([$nid]);
    $user = $user_stmt->fetch();

    if (!$user) {
        json_response(false, "User with provided NID not found");
    }

    // Check if bus is already booked
    $existing_booking_stmt = $pdo->prepare("
        SELECT COUNT(*) as booking_count 
        FROM Bus_Booking 
        WHERE Bus_ID = ? AND Booking_Status IN ('Booked', 'Confirmed')
    ");
    $existing_booking_stmt->execute([$bus_id]);
    $existing_booking = $existing_booking_stmt->fetch();

    if ($existing_booking['booking_count'] > 0) {
        json_response(false, "This bus is already booked");
    }

    // Generate unique booking code
    $booking_code = generate_booking_code();

    // Create bus booking directly
    $booking_stmt = $pdo->prepare("
        INSERT INTO Bus_Booking (Book_Code, Bus_ID, NID, Book_Slot, Booking_Status, Payment_Status) 
        VALUES (?, ?, ?, 1, 'Booked', 'Pending')
    ");

    if ($booking_stmt->execute([$booking_code, $bus_id, $nid])) {
        json_response(true, "Bus booked successfully! Booking Code: " . $booking_code, [
            'booking_code' => $booking_code,
            'bus_id' => $bus_id,
            'nid' => $nid,
            'booking_status' => 'Booked',
            'payment_status' => 'Pending'
        ]);
    } else {
        json_response(false, "Failed to create booking");
    }

} catch (Exception $e) {
    json_response(false, "Error creating booking: " . $e->getMessage());
}
?>