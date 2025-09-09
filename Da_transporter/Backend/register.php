<?php
require_once 'Backend/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method');
}

try {
    // Sanitize input data
    $name = sanitize_input($_POST['name']);
    $nid = sanitize_input($_POST['nid']);
    $phone = sanitize_input($_POST['phone']);
    $emergency_contact = sanitize_input($_POST['emergency_contact'] ?? '');
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $area = sanitize_input($_POST['area'] ?? '');
    $ps = sanitize_input($_POST['ps'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $user_type = $_POST['user_type'] ?? 'student'; // student or driver

    // Validate required fields
    if (empty($name) || empty($nid) || empty($phone) || empty($email) || empty($password)) {
        json_response(false, 'Please fill in all required fields');
    }

    // Validate NID format (basic validation)
    if (!preg_match('/^\d{10,17}$/', $nid)) {
        json_response(false, 'Invalid NID format');
    }

    // Validate phone format
    if (!preg_match('/^(\+88)?01[3-9]\d{8}$/', $phone)) {
        json_response(false, 'Invalid phone number format');
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(false, 'Invalid email format');
    }

    // Check password strength
    if (strlen($password) < 6) {
        json_response(false, 'Password must be at least 6 characters long');
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Begin transaction
    $pdo->beginTransaction();

    // Check if NID, phone, or email already exists
    $check_stmt = $pdo->prepare("SELECT NID, Phone, Email FROM User WHERE NID = ? OR Phone = ? OR Email = ?");
    $check_stmt->execute([$nid, $phone, $email]);
    $existing_user = $check_stmt->fetch();

    if ($existing_user) {
        $pdo->rollBack();
        if ($existing_user['NID'] === $nid) {
            json_response(false, 'NID already registered');
        } elseif ($existing_user['Phone'] === $phone) {
            json_response(false, 'Phone number already registered');
        } else {
            json_response(false, 'Email already registered');
        }
    }

    // Insert into User table
    $user_stmt = $pdo->prepare("
        INSERT INTO User (NID, Name, Phone, Emergency_Contact, Area, PS, Gender, Email, password) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $user_stmt->execute([$nid, $name, $phone, $emergency_contact, $area, $ps, $gender, $email, $hashed_password]);

    // Insert into appropriate user type table
    if ($user_type === 'student') {
        $student_id = $_POST['student_id'] ?? null;
        $rider_stmt = $pdo->prepare("INSERT INTO Rider_User (NID, Std_ID) VALUES (?, ?)");
        $rider_stmt->execute([$nid, $student_id]);
    } elseif ($user_type === 'driver') {
        // Validate driver-specific fields
        $rider_id = sanitize_input($_POST['rider_id'] ?? '');
        $vehicle_num = sanitize_input($_POST['vehicle_num'] ?? '');
        $car_model = sanitize_input($_POST['car_model'] ?? '');
        $license_num = sanitize_input($_POST['license_num'] ?? '');
        $capacity = intval($_POST['capacity'] ?? 0);
        $car_type = $_POST['car_type'] ?? '';

        if (empty($rider_id) || empty($vehicle_num) || empty($license_num) || $capacity < 1) {
            $pdo->rollBack();
            json_response(false, 'Please fill in all driver information fields');
        }

        // Check if Rider_ID, Vehicle_Num, or License_Num already exists
        $check_driver_stmt = $pdo->prepare("
            SELECT rt.Rider_ID, pc.Vehicle_Num, pc.License_Num 
            FROM Rider_Taker rt 
            LEFT JOIN Private_Car pc ON rt.NID = pc.NID 
            WHERE rt.Rider_ID = ? OR pc.Vehicle_Num = ? OR pc.License_Num = ?
        ");
        $check_driver_stmt->execute([$rider_id, $vehicle_num, $license_num]);
        $existing_driver = $check_driver_stmt->fetch();

        if ($existing_driver) {
            $pdo->rollBack();
            if ($existing_driver['Rider_ID'] === $rider_id) {
                json_response(false, 'Rider ID already exists');
            } elseif ($existing_driver['Vehicle_Num'] === $vehicle_num) {
                json_response(false, 'Vehicle number already registered');
            } else {
                json_response(false, 'License number already registered');
            }
        }

        // Insert into Rider_Taker table
        $taker_stmt = $pdo->prepare("INSERT INTO Rider_Taker (NID, Rider_ID) VALUES (?, ?)");
        $taker_stmt->execute([$nid, $rider_id]);

        // Insert into Private_Car table
        $car_stmt = $pdo->prepare("
            INSERT INTO Private_Car (Vehicle_Num, Car_Model, Owner_ID, Capacity, License_Num, Car_Type, NID) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $car_stmt->execute([$vehicle_num, $car_model, $rider_id, $capacity, $license_num, $car_type, $nid]);
    }

    // Create welcome notification
    $notification_stmt = $pdo->prepare("
        INSERT INTO Notifications (User_ID, Message, Status) 
        VALUES (?, ?, 'Unread')
    ");
    $welcome_message = "Welcome to DA Transporter, {$name}! Your account has been created successfully.";
    $notification_stmt->execute([$nid, $welcome_message]);

    // Commit transaction
    $pdo->commit();

    json_response(true, 'Registration successful! You can now login.', [
        'user_type' => $user_type,
        'redirect' => 'login.html'
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Handle specific database errors
    if ($e->getCode() == 23000) {
        json_response(false, 'Registration failed: Duplicate entry');
    }
    
    error_log("Registration error: " . $e->getMessage());
    json_response(false, 'Registration failed. Please try again.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Registration error: " . $e->getMessage());
    json_response(false, 'Registration failed. Please try again.');
}
?>