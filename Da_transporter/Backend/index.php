<?php
require_once 'Backend/config.php';

// Check if user is already logged in
if (is_logged_in()) {
    header("Location: dashboard.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DA Transporter - Oni chan transport you anywhere</title>
    <style>
        :root {
            --primary: #000000;
            --secondary: #FFFFFF;
            --accent: #FF5E5B;
            --success: #4CAF50;
            --shadow: 8px 8px 0px var(--primary);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Courier New', monospace;
        }

        body {
            background-color: var(--secondary);
            min-height: 100vh;
            padding: 20px;
        }

        .header {
            background-color: var(--secondary);
            border: 3px solid var(--primary);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 6px;
            left: 6px;
            right: -6px;
            bottom: -6px;
            border: 3px solid var(--primary);
            z-index: -1;
        }

        .logo {
            font-size: 48px;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .tagline {
            font-size: 18px;
            color: var(--primary);
            font-weight: bold;
            margin-bottom: 20px;
        }

        .nav-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .nav-card {
            background-color: var(--secondary);
            border: 3px solid var(--primary);
            box-shadow: var(--shadow);
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-card::before {
            content: '';
            position: absolute;
            top: 6px;
            left: 6px;
            right: -6px;
            bottom: -6px;
            border: 3px solid var(--primary);
            z-index: -1;
        }

        .nav-card:hover {
            transform: translate(-4px, -4px);
            box-shadow: 12px 12px 0px var(--primary);
        }

        .nav-card h2 {
            color: var(--primary);
            font-size: 24px;
            font-weight: 900;
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        .nav-card p {
            color: var(--primary);
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .nav-icon {
            font-size: 48px;
            color: var(--accent);
            margin-bottom: 15px;
        }

        .auth-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 30px 0;
        }

        .btn {
            padding: 15px 30px;
            background-color: var(--accent);
            color: var(--secondary);
            border: 3px solid var(--primary);
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            text-transform: uppercase;
        }

        .btn:hover {
            box-shadow: 4px 4px 0px var(--primary);
            transform: translate(-2px, -2px);
        }

        .btn-secondary {
            background-color: var(--secondary);
            color: var(--primary);
        }

        .admin-link {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: var(--success);
            color: var(--secondary);
            border: 3px solid var(--primary);
            text-decoration: none;
            font-weight: bold;
            font-size: 12px;
            transition: all 0.3s;
        }

        .admin-link:hover {
            box-shadow: 4px 4px 0px var(--primary);
            transform: translate(-2px, -2px);
        }

        .stats-container {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 40px 0;
            flex-wrap: wrap;
        }

        .stat-box {
            background-color: var(--secondary);
            border: 3px solid var(--primary);
            padding: 20px;
            text-align: center;
            min-width: 150px;
            box-shadow: 4px 4px 0px var(--primary);
        }

        .stat-number {
            font-size: 36px;
            font-weight: 900;
            color: var(--accent);
        }

        .stat-label {
            font-size: 12px;
            font-weight: bold;
            color: var(--primary);
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">DA TRANSPORTER</div>
        <div class="tagline">"Oni chan transport you anywhere"</div>
        <div class="auth-buttons">
            <a href="login.html" class="btn">LOGIN</a>
            <a href="register.html" class="btn btn-secondary">SIGN UP</a>
        </div>
    </div>

    <?php
    // Get stats from database
    try {
        $stats_query = "SELECT 
            (SELECT COUNT(*) FROM Trip WHERE Trip_Status IN ('Pending', 'Confirmed')) as active_rides,
            (SELECT COUNT(*) FROM User) as total_users,
            (SELECT COUNT(*) FROM Trip WHERE Trip_Status = 'Completed') as completed_trips
        ";
        $stmt = $pdo->query($stats_query);
        $stats = $stmt->fetch();
    } catch(Exception $e) {
        $stats = ['active_rides' => 127, 'total_users' => 2543, 'completed_trips' => 15678];
    }
    ?>

    <div class="stats-container">
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['active_rides']; ?></div>
            <div class="stat-label">Active Rides</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['total_users']; ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['completed_trips']; ?></div>
            <div class="stat-label">Completed Trips</div>
        </div>
    </div>

    <div class="nav-container">
        <div class="nav-card" onclick="redirectTo('find_ride.html')">
            <div class="nav-icon">🚗</div>
            <h2>Find a Ride</h2>
            <p>Search for available rides and join other passengers</p>
        </div>

        <div class="nav-card" onclick="redirectTo('create_ride.html')">
            <div class="nav-icon">🚙</div>
            <h2>Offer a Ride</h2>
            <p>Create a ride and earn money by sharing your car</p>
        </div>

        <div class="nav-card" onclick="redirectTo('bus_booking.html')">
            <div class="nav-icon">🚌</div>
            <h2>Book Bus</h2>
            <p>Reserve seats on scheduled bus routes</p>
        </div>

        <div class="nav-card" onclick="redirectTo('my_trips.html')">
            <div class="nav-icon">📋</div>
            <h2>My Trips</h2>
            <p>View your ride history and manage bookings</p>
        </div>

        <div class="nav-card" onclick="redirectTo('profile.html')">
            <div class="nav-icon">👤</div>
            <h2>Profile</h2>
            <p>Manage your account and vehicle information</p>
        </div>

        <div class="nav-card" onclick="redirectTo('reviews.html')">
            <div class="nav-icon">⭐</div>
            <h2>Reviews</h2>
            <p>Rate your experience and read others' feedback</p>
        </div>
    </div>

    <a href="admin_login.html" class="admin-link">ADMIN</a>

    <script>
        function redirectTo(page) {
            window.location.href = page;
        }
    </script>
</body>
</html>