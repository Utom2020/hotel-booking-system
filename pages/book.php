<?php
session_start();
require_once '../config/db.php';
require_once '../config/locking.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$room_id  = isset($_GET['room_id'])  ? (int)$_GET['room_id']  : 0;
$checkin  = isset($_GET['checkin'])  ? $_GET['checkin']        : '';
$checkout = isset($_GET['checkout']) ? $_GET['checkout']       : '';

if (!$room_id || !$checkin || !$checkout) {
    header("Location: ../index.php");
    exit();
}

// Get room details
$stmt = $conn->prepare(
    "SELECT * FROM rooms WHERE room_id = ? AND is_available = 1"
);
$stmt->bind_param("i", $room_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();  

if (!$room) {
    header("Location: ../index.php");
    exit();
}

// Get current version from room_availability for optimistic locking
$vstmt = $conn->prepare(
    "SELECT version FROM room_availability WHERE room_id = ?"
);
$vstmt->bind_param("i", $room_id);
$vstmt->execute();
$vresult = $vstmt->get_result()->fetch_assoc();
$current_version = $vresult ? $vresult['version'] : 0;
$vstmt->close();

$d1     = new DateTime($checkin);
$d2     = new DateTime($checkout);
$nights = $d2->diff($d1)->days;
$total  = $room['price_per_night'] * $nights;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];

    if (LOCKING_STRATEGY === 'pessimistic') {

        // ── PESSIMISTIC LOCKING ──────────────────────────────────
        $conn->begin_transaction();

        try {
            // Lock the room row to prevent other transactions
            $lock = $conn->prepare(
                "SELECT room_id FROM rooms 
                 WHERE room_id = ? AND is_available = 1 
                 FOR UPDATE"
            );
            $lock->bind_param("i", $room_id);
            $lock->execute();
            $locked = $lock->get_result()->fetch_assoc();

            if (!$locked) {
                $conn->rollback();
                header("Location: conflict.php");
                exit();
            }

            // Check no confirmed booking overlaps these dates
            $check = $conn->prepare(
                "SELECT booking_id FROM bookings 
                 WHERE room_id = ? 
                 AND status = 'confirmed'
                 AND check_in_date  < ? 
                 AND check_out_date > ?"
            );
            $check->bind_param("iss", $room_id, $checkout, $checkin);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $conn->rollback();
                header("Location: conflict.php");
                exit();
            }

            // Safe to book — insert the booking
            $insert = $conn->prepare(
                "INSERT INTO bookings 
                 (user_id, room_id, check_in_date, check_out_date, total_price, status) 
                 VALUES (?, ?, ?, ?, ?, 'confirmed')"
            );
            $insert->bind_param(
                "iissd",
                $user_id, $room_id, $checkin, $checkout, $total
            );
            $insert->execute();
            $booking_id = $conn->insert_id;

            // Update room_availability status
            $upd = $conn->prepare(
                "UPDATE room_availability SET status = 'booked' WHERE room_id = ?"
            );
            $upd->bind_param("i", $room_id);
            $upd->execute();

            $conn->commit();

            header("Location: confirm.php?booking_id=" . $booking_id);
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Something went wrong. Please try again.';
        }

    } else {

        // ── OPTIMISTIC LOCKING ───────────────────────────────────
        $posted_version = isset($_POST['version']) ? (int)$_POST['version'] : -1;

        $conn->begin_transaction();

        try {
            // Check no confirmed booking overlaps these dates
            $check = $conn->prepare(
                "SELECT booking_id FROM bookings 
                 WHERE room_id = ? 
                 AND status = 'confirmed'
                 AND check_in_date  < ? 
                 AND check_out_date > ?"
            );
            $check->bind_param("iss", $room_id, $checkout, $checkin);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $conn->rollback();
                header("Location: conflict.php");
                exit();
            }

            // Try to update room_availability only if version matches
            $upd = $conn->prepare(
                "UPDATE room_availability 
                 SET status = 'booked', version = version + 1
                 WHERE room_id = ? AND version = ?"
            );
            $upd->bind_param("ii", $room_id, $posted_version);
            $upd->execute();

            if ($upd->affected_rows === 0) {
                // Version mismatch — another user booked first
                $conn->rollback();
                header("Location: conflict.php");
                exit();
            }

            // Safe to book — insert the booking
            $insert = $conn->prepare(
                "INSERT INTO bookings 
                 (user_id, room_id, check_in_date, check_out_date, total_price, status) 
                 VALUES (?, ?, ?, ?, ?, 'confirmed')"
            );
            $insert->bind_param(
                "iissd",
                $user_id, $room_id, $checkin, $checkout, $total
            );
            $insert->execute();
            $booking_id = $conn->insert_id;

            $conn->commit();

            header("Location: confirm.php?booking_id=" . $booking_id);
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Booking — Hotel Booking System</title>
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
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card-header {
            background: #1F3864;
            color: white;
            padding: 20px 28px;
        }
        .card-header h2 { font-size: 20px; margin-bottom: 4px; }
        .card-header p { font-size: 13px; opacity: 0.8; }
        .card-body { padding: 28px; }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
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
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total-label { font-size: 15px; font-weight: bold; color: #444; }
        .total-amount { font-size: 24px; font-weight: bold; color: #2E5FA3; }
        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 13px;
        }
        .btn-confirm {
            width: 100%;
            padding: 14px;
            background: #1F3864;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-confirm:hover { background: #2E5FA3; }
        .btn-back {
            display: block;
            text-align: center;
            margin-top: 14px;
            color: #888;
            font-size: 13px;
            text-decoration: none;
        }
        .btn-back:hover { color: #1F3864; }
        .room-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .no-img {
            width: 100%;
            height: 200px;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
        }
        .strategy-badge {
            display: inline-block;
            background: #f0c040;
            color: #1F3864;
            font-size: 12px;
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 20px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>

<div class="navbar">
    <h1>🏨 Hotel Booking System</h1>
    <div>
        <a href="../index.php">Search</a>
        <a href="my_bookings.php">My Bookings</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="card">

        <?php if (!empty($room['image'])): ?>
            <img class="room-img" 
                 src="../uploads/rooms/<?php echo htmlspecialchars($room['image']); ?>" 
                 alt="Room <?php echo htmlspecialchars($room['room_number']); ?>">
        <?php else: ?>
            <div class="no-img">🛏️</div>
        <?php endif; ?>

        <div class="card-header">
            <h2>Booking Summary</h2>
            <p>Please review your booking details before confirming</p>
        </div>

        <div class="card-body">

            <div class="strategy-badge">
                Strategy: <?php echo ucfirst(LOCKING_STRATEGY); ?> Locking
            </div>

            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="detail-row">
                <span class="detail-label">Room</span>
                <span class="detail-value">
                    Room <?php echo htmlspecialchars($room['room_number']); ?> 
                    — <?php echo htmlspecialchars($room['room_type']); ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Check-in</span>
                <span class="detail-value"><?php echo htmlspecialchars($checkin); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Check-out</span>
                <span class="detail-value"><?php echo htmlspecialchars($checkout); ?></span>
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
                    £<?php echo number_format($room['price_per_night'], 2); ?>
                </span>
            </div>

            <div class="total-row">
                <span class="total-label">Total Amount</span>
                <span class="total-amount">£<?php echo number_format($total, 2); ?></span>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="room_id"  value="<?php echo $room_id; ?>">
                <input type="hidden" name="checkin"  value="<?php echo htmlspecialchars($checkin); ?>">
                <input type="hidden" name="checkout" value="<?php echo htmlspecialchars($checkout); ?>">
                <input type="hidden" name="version"  value="<?php echo $current_version; ?>">
                <button type="submit" class="btn-confirm">
                    ✅ Confirm Booking
                </button>
            </form>

            <a href="search.php?checkin=<?php echo urlencode($checkin); ?>&checkout=<?php echo urlencode($checkout); ?>" 
               class="btn-back">← Back to Search Results</a>

        </div>
    </div>
</div>

</body>
</html>