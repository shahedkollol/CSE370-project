<?php
require_once 'Backend/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    json_response(false, "Please login first");
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Get user's NID
    $user_stmt = $pdo->prepare("SELECT NID FROM User WHERE User_ID = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        json_response(false, "User not found");
    }

    // Get user's bus bookings with bus information
    $stmt = $pdo->prepare("
        SELECT 
            bb.*,
            b.Bus_Type,
            b.Capacity,
            b.License_Num,
            b.Schedule
        FROM Bus_Booking bb
        LEFT JOIN Bus b ON bb.Bus_ID = b.Bus_ID
        WHERE bb.NID = ?
        ORDER BY bb.Booked_At DESC
    ");
    $stmt->execute([$user['NID']]);
    $bookings = $stmt->fetchAll();

    // Format the response data
    $formatted_bookings = [];
    foreach ($bookings as $booking) {
        $formatted_bookings[] = [
            'Book_Code' => $booking['Book_Code'],
            'Bus_ID' => $booking['Bus_ID'],
            'NID' => $booking['NID'],
            'Book_Slot' => $booking['Book_Slot'],
            'Booking_Status' => $booking['Booking_Status'],
            'Payment_Status' => $booking['Payment_Status'],
            'Booked_At' => $booking['Booked_At'],
            'Updated_At' => $booking['Updated_At'] ?? null,
            // Bus information
            'Bus_Type' => $booking['Bus_Type'],
            'Capacity' => $booking['Capacity'],
            'License_Num' => $booking['License_Num'],
            'Schedule' => $booking['Schedule']
        ];
    }

    json_response(true, "Bookings retrieved successfully", $formatted_bookings);

} catch (Exception $e) {
    json_response(false, "Error retrieving bookings: " . $e->getMessage());
}
?>