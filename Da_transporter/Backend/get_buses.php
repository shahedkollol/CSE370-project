<?php
require_once 'Backend/config.php';

header('Content-Type: application/json');

try {
    // Get all available buses
    $stmt = $pdo->prepare("SELECT * FROM Bus ORDER BY Created DESC");
    $stmt->execute();
    $buses = $stmt->fetchAll();

    if ($buses) {
        json_response(true, "Buses retrieved successfully", $buses);
    } else {
        json_response(true, "No buses found", []);
    }

} catch (Exception $e) {
    json_response(false, "Error retrieving buses: " . $e->getMessage());
}
?>