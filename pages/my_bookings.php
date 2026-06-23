<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT b.booking_id, b.check_in_date, b.check_out_date, b.total_price, b.status,
           r.room_number, r.room_type, r.price_per_night
    FROM bookings b
    JOIN rooms r ON b.room_id = r.room_id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings — Hotel Booking System</title>
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
            padding: 40px 30px;
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }
        h2 {
            color: #1F3864;
            margin-bottom: 24px;
            font-size: 22px;
        }
        .no-bookings {
            background: white;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            color: #555;
            font-size: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        thead {
            background: #1F3864;
            color: white;
        }
        thead th {
            padding: 14px 16px;
            text-align: left;
            font-size: 14px;
        }
        tbody tr {
            border-bottom: 1px solid #e5e7eb;
        }
        tbody tr:last-child {
            border-bottom: none;
        }
        tbody td {
            padding: 14px 16px;
            font-size: 14px;
            color: #333;
        }
        .status-confirmed {
            background: #dcfce7;
            color: #166534;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }
        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }
        .btn-cancel {
            background: #dc2626;
            color: white;
            padding: 6px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
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
    <h2>My Bookings</h2>

    <?php if (empty($bookings)): ?>
        <div class="no-bookings">
            <p>You have no bookings yet. <a href="../index.php">Search for a room</a> to get started.</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Booking ID</th>
                    <th>Room</th>
                    <th>Type</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Total Price</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                <tr>
                    <td>#<?php echo $booking['booking_id']; ?></td>
                    <td><?php echo htmlspecialchars($booking['room_number']); ?></td>
                    <td><?php echo htmlspecialchars($booking['room_type']); ?></td>
                    <td><?php echo $booking['check_in_date']; ?></td>
                    <td><?php echo $booking['check_out_date']; ?></td>
                    <td>£<?php echo number_format($booking['total_price'], 2); ?></td>
                    <td>
                        <span class="status-<?php echo $booking['status']; ?>">
                            <?php echo ucfirst($booking['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($booking['status'] === 'confirmed'): ?>
                            <a href="cancel.php?booking_id=<?php echo $booking['booking_id']; ?>" class="btn-cancel">Cancel</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<footer>
    &copy; 2026 Hotel Booking System — University of Hertfordshire MSc Project
</footer>

</body>
</html>