<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room No Longer Available — Hotel Booking System</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f7;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .navbar {
            background: #1F3864;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar h1 { font-size: 20px; }
        .navbar a {
            color: #f0c040;
            text-decoration: none;
            font-size: 14px;
            margin-left: 16px;
        }
        .container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            padding: 50px 40px;
            max-width: 520px;
            width: 100%;
            text-align: center;
        }
        .icon { font-size: 70px; margin-bottom: 20px; }
        h2 {
            color: #dc2626;
            font-size: 24px;
            margin-bottom: 14px;
        }
        .message {
            color: #555;
            font-size: 15px;
            line-height: 1.7;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 15px;
            font-weight: bold;
            margin: 6px;
        }
        .btn-primary {
            background: #1F3864;
            color: white;
        }
        .btn-secondary {
            background: #e5e7eb;
            color: #333;
        }
        footer {
            background: #1F3864;
            color: #ccc;
            text-align: center;
            padding: 14px;
            font-size: 13px;
        }
    </style>
</head>
<body>

<div class="navbar">
    <h1>🏨 Hotel Booking System</h1>
    <div>
        <a href="../index.php">Home</a>
        <a href="my_bookings.php">My Bookings</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <div class="icon">⚠️</div>
        <h2>Room No Longer Available</h2>
        <p class="message">
            Sorry, this room was just booked by another user while you were completing your reservation.
            
        </p>
        <a href="../index.php" class="btn btn-primary">Search Again</a>
        <a href="my_bookings.php" class="btn btn-secondary">My Bookings</a>
    </div>
</div>

<footer>
    &copy; 2026 Hotel Booking System — University of Hertfordshire MSc Project
</footer>

</body>
</html>