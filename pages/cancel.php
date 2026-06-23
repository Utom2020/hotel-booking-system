<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if ($booking_id === 0) {
    header("Location: my_bookings.php");
    exit();
}

// Fetch the booking — make sure it belongs to this user and is confirmed
$stmt = $conn->prepare("
    SELECT b.booking_id, b.room_id, b.check_in_date, b.check_out_date, b.total_price,
           r.room_number, r.room_type
    FROM bookings b
    JOIN rooms r ON b.room_id = r.room_id
    WHERE b.booking_id = ? AND b.user_id = ? AND b.status = 'confirmed'
");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) {
    header("Location: my_bookings.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        // Cancel the booking
        $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $booking_id, $user_id);
        $stmt->execute();
        $stmt->close();

        // Set the room back to available
        $stmt = $conn->prepare("UPDATE room_availability SET status = 'available' WHERE room_id = ?");
        $stmt->bind_param("i", $booking['room_id']);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $success = 'Your booking has been cancelled successfully.';
    } catch (Exception $e) {
        $conn->rollback();
        $error = 'Something went wrong. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Booking — Hotel Booking System</title>
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
        .icon { font-size: 60px; margin-bottom: 20px; }
        h2 {
            color: #1F3864;
            font-size: 22px;
            margin-bottom: 20px;
        }
        .booking-details {
            background: #f9fafb;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 28px;
            text-align: left;
        }
        .booking-details p {
            font-size: 14px;
            color: #444;
            margin-bottom: 8px;
        }
        .booking-details p span {
            font-weight: bold;
            color: #1F3864;
        }
        .warning {
            color: #dc2626;
            font-size: 14px;
            margin-bottom: 24px;
        }
        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 15px;
            font-weight: bold;
            margin: 6px;
            border: none;
            cursor: pointer;
        }
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        .btn-secondary {
            background: #e5e7eb;
            color: #333;
        }
        .alert-success {
            background: #dcfce7;
            color: #166534;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        footer {
            background: #1F3864;
            color: #ccc;
            text-align: center;
            padding: 14px;
            font-size: 13px;
            margin-top: auto;
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

        <?php if ($success): ?>
            <div class="icon">✅</div>
            <h2>Booking Cancelled</h2>
            <div class="alert-success"><?php echo $success; ?></div>
            <a href="my_bookings.php" class="btn btn-secondary">Back to My Bookings</a>
            <a href="../index.php" class="btn btn-secondary">Search Again</a>

        <?php elseif ($error): ?>
            <div class="icon">❌</div>
            <h2>Something Went Wrong</h2>
            <div class="alert-error"><?php echo $error; ?></div>
            <a href="my_bookings.php" class="btn btn-secondary">Back to My Bookings</a>

        <?php else: ?>
            <div class="icon">🗑️</div>
            <h2>Cancel Booking</h2>

            <div class="booking-details">
                <p>Room: <span><?php echo htmlspecialchars($booking['room_number']); ?></span></p>
                <p>Type: <span><?php echo htmlspecialchars($booking['room_type']); ?></span></p>
                <p>Check-in: <span><?php echo $booking['check_in_date']; ?></span></p>
                <p>Check-out: <span><?php echo $booking['check_out_date']; ?></span></p>
                <p>Total Paid: <span>£<?php echo number_format($booking['total_price'], 2); ?></span></p>
            </div>

            <p class="warning">Are you sure you want to cancel this booking? This action cannot be undone.</p>

            <form method="POST">
                <button type="submit" class="btn btn-danger">Yes, Cancel Booking</button>
                <a href="my_bookings.php" class="btn btn-secondary">No, Go Back</a>
            </form>
        <?php endif; ?>

    </div>
</div>

<footer>
    &copy; 2026 Hotel Booking System — University of Hertfordshire MSc Project
</footer>

</body>
</html>