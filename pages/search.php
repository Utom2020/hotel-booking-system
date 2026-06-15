<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$checkin  = isset($_GET['checkin'])  ? $_GET['checkin']  : '';
$checkout = isset($_GET['checkout']) ? $_GET['checkout'] : '';
$error    = '';
$rooms    = [];

if (empty($checkin) || empty($checkout)) {
    $error = 'Please select both check-in and check-out dates.';
} elseif ($checkin >= $checkout) {
    $error = 'Check-out date must be after check-in date.';
} else {
    $stmt = $conn->prepare("
        SELECT r.room_id, r.room_number, r.room_type, 
               r.price_per_night, r.description, r.image
        FROM rooms r
        WHERE r.is_available = 1
        AND r.room_id NOT IN (
            SELECT b.room_id FROM bookings b
            WHERE b.status = 'confirmed'
            AND b.check_in_date  < ?
            AND b.check_out_date > ?
        )
        ORDER BY r.price_per_night ASC
    ");
    $stmt->bind_param("ss", $checkout, $checkin);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
}

$nights = 0;
if ($checkin && $checkout) {
    $d1 = new DateTime($checkin);
    $d2 = new DateTime($checkout);
    $nights = $d2->diff($d1)->days;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results — Hotel Booking System</title>
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
        .container { max-width: 900px; margin: 40px auto; padding: 0 16px; }
        .search-info {
            background: white;
            padding: 16px 24px;
            border-radius: 10px;
            margin-bottom: 24px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .search-info p { color: #555; font-size: 14px; }
        .search-info strong { color: #1F3864; }
        .search-info a {
            background: #1F3864;
            color: white;
            padding: 8px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
        }
        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .no-rooms {
            text-align: center;
            background: white;
            padding: 60px 20px;
            border-radius: 12px;
            color: #888;
        }
        .no-rooms .icon { font-size: 50px; margin-bottom: 16px; }
        .no-rooms p { font-size: 15px; margin-bottom: 20px; }
        .no-rooms a {
            background: #1F3864;
            color: white;
            padding: 10px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }
        .results-heading {
            font-size: 16px;
            font-weight: bold;
            color: #1F3864;
            margin-bottom: 16px;
        }
        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .room-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        .room-card:hover { transform: translateY(-4px); }
        .room-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: #9ca3af;
        }
        .room-img img {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        .room-body { padding: 18px; }
        .room-number {
            font-size: 12px;
            color: #888;
            margin-bottom: 4px;
        }
        .room-type {
            font-size: 18px;
            font-weight: bold;
            color: #1F3864;
            margin-bottom: 8px;
        }
        .room-desc {
            font-size: 13px;
            color: #666;
            line-height: 1.5;
            margin-bottom: 14px;
        }
        .room-price {
            font-size: 20px;
            font-weight: bold;
            color: #2E5FA3;
            margin-bottom: 4px;
        }
        .room-total {
            font-size: 12px;
            color: #888;
            margin-bottom: 16px;
        }
        .book-btn {
            display: block;
            width: 100%;
            padding: 11px;
            background: #1F3864;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        .book-btn:hover { background: #2E5FA3; }
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

    <?php if (!empty($checkin) && !empty($checkout)): ?>
    <div class="search-info">
        <p>
            Showing rooms for 
            <strong><?php echo htmlspecialchars($checkin); ?></strong> 
            to 
            <strong><?php echo htmlspecialchars($checkout); ?></strong>
            &nbsp;•&nbsp;
            <strong><?php echo $nights; ?> night<?php echo $nights !== 1 ? 's' : ''; ?></strong>
        </p>
        <a href="../index.php">Change Dates</a>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php elseif (empty($rooms)): ?>
        <div class="no-rooms">
            <div class="icon">🏨</div>
            <p>No rooms available for the selected dates.</p>
            <a href="../index.php">Search Again</a>
        </div>
    <?php else: ?>
        <p class="results-heading">
            <?php echo count($rooms); ?> room<?php echo count($rooms) !== 1 ? 's' : ''; ?> available
        </p>
        <div class="rooms-grid">
            <?php foreach ($rooms as $room): ?>
            <div class="room-card">

                <div class="room-img">
                    <?php if (!empty($room['image'])): ?>
                        <img src="../uploads/rooms/<?php echo htmlspecialchars($room['image']); ?>" 
                             alt="Room <?php echo htmlspecialchars($room['room_number']); ?>">
                    <?php else: ?>
                        🛏️
                    <?php endif; ?>
                </div>

                <div class="room-body">
                    <div class="room-number">Room <?php echo htmlspecialchars($room['room_number']); ?></div>
                    <div class="room-type"><?php echo htmlspecialchars($room['room_type']); ?></div>
                    <div class="room-desc"><?php echo htmlspecialchars($room['description'] ?? 'No description available.'); ?></div>
                    <div class="room-price">£<?php echo number_format($room['price_per_night'], 2); ?> / night</div>
                    <div class="room-total">
                        Total for <?php echo $nights; ?> night<?php echo $nights !== 1 ? 's' : ''; ?>: 
                        <strong>£<?php echo number_format($room['price_per_night'] * $nights, 2); ?></strong>
                    </div>
                    <a href="book.php?room_id=<?php echo $room['room_id']; ?>&checkin=<?php echo urlencode($checkin); ?>&checkout=<?php echo urlencode($checkout); ?>" 
                       class="book-btn">Book Now</a>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

</body>
</html>