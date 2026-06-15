<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$booking_id) {
    header("Location: ../index.php");
    exit();
}

// Get booking details with room info
$stmt = $conn->prepare("
    SELECT b.booking_id, b.check_in_date, b.check_out_date, 
           b.total_price, b.status, b.created_at,
           r.room_number, r.room_type, r.price_per_night, r.image,
           u.name as guest_name
    FROM bookings b
    JOIN rooms r ON b.room_id = r.room_id
    JOIN users u ON b.user_id = u.user_id
    WHERE b.booking_id = ? AND b.user_id = ?
");
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header("Location: ../index.php");
    exit();
}

$d1     = new DateTime($booking['check_in_date']);
$d2     = new DateTime($booking['check_out_date']);
$nights = $d2->diff($d1)->days;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed — Hotel Booking System</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f0f2f7; }
        .navbar {
            background: #1F3864;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar h1 { font-size: 20px; }
        .navbar a { color: #f0c040; text-decoration: none; font-size: 14px; margin-left: 16px; }
        .container {
            max-width: 560px;
            margin: 50px auto;
            padding: 0 16px;
        }
        .success-banner {
            background: #d1fae5;
            border: 1.5px solid #6ee7b7;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin-bottom: 24px;
        }
        .success-banner .icon { font-size: 50px; margin-bottom: 12px; }
        .success-banner h2 { color: #065f46; font-size: 22px; margin-bottom: 6px; }
        .success-banner p { color: #047857; font-size: 14px; }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .card-header {
            background: #1F3864;
            color: white;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h3 { font-size: 16px; }
        .booking-ref { font-size: 13px; opacity: 0.8; }
        .room-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        .no-img {
            width: 100%;
            height: 180px;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
        }
        .card-body { padding: 24px; }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 11px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        .detail-row:last-of-type { border-bottom: none; }
        .detail-label { color: #888; }
        .detail-value { font-weight: bold; color: #1F3864; }
        .total-row {
            background: #EEF3FB;
            border-radius: 8px;
            padding: 16px 20px;
            margin-top: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total-label { font-size: 15px; font-weight: bold; color: #444; }
        .total-amount { font-size: 24px; font-weight: bold; color: #2E5FA3; }
        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 8px;
        }
        .btn {
            display: block;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-primary { background: #1F3864; color: white; }
        .btn-primary:hover { background: #2E5FA3; }
        .btn-secondary { background: #f0f2f7; color: #444; }
        .btn-secondary:hover { background: #e0e4ef; }
        .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: bold;
            background: #d1fae5;
            color: #065f46;
        }
    </style>
</head>
<body>

<div class="navbar">
    <h1>🏨 Hotel Booking System</h1>
    <div>
        <a href="../index.php">New Search</a>
        <a href="my_bookings.php">My Bookings</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="success-banner">
        <div class="icon">🎉</div>
        <h2>Booking Confirmed!</h2>
        <p>Thank you, <?php echo htmlspecialchars($booking['guest_name']); ?>. Your room has been successfully reserved.</p>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Booking Details</h3>
            <span class="booking-ref">Ref #<?php echo str_pad($booking['booking_id'], 6, '0', STR_PAD_LEFT); ?></span>
        </div>

        <?php if (!empty($booking['image'])): ?>
            <img class="room-img"
                 src="../uploads/rooms/<?php echo htmlspecialchars($booking['image']); ?>"
                 alt="Room <?php echo htmlspecialchars($booking['room_number']); ?>">
        <?php else: ?>
            <div class="no-img">🛏️</div>
        <?php endif; ?>

        <div class="card-body">
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="status-badge">✅ Confirmed</span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Guest Name</span>
                <span class="detail-value"><?php echo htmlspecialchars($booking['guest_name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Room</span>
                <span class="detail-value">
                    Room <?php echo htmlspecialchars($booking['room_number']); ?>
                    — <?php echo htmlspecialchars($booking['room_type']); ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Check-in</span>
                <span class="detail-value"><?php echo htmlspecialchars($booking['check_in_date']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Check-out</span>
                <span class="detail-value"><?php echo htmlspecialchars($booking['check_out_date']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Duration</span>
                <span class="detail-value">
                    <?php echo $nights; ?> night<?php echo $nights !== 1 ? 's' : ''; ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Price per night</span>
                <span class="detail-value">
                    £<?php echo number_format($booking['price_per_night'], 2); ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Booked on</span>
                <span class="detail-value">
                    <?php echo date('d M Y, H:i', strtotime($booking['created_at'])); ?>
                </span>
            </div>

            <div class="total-row">
                <span class="total-label">Total Paid</span>
                <span class="total-amount">£<?php echo number_format($booking['total_price'], 2); ?></span>
            </div>

            <div class="actions">
                <a href="../index.php" class="btn btn-primary">Search More Rooms</a>
                <a href="my_bookings.php" class="btn btn-secondary">My Bookings</a>
            </div>
        </div>
    </div>

</div>

</body>
</html>