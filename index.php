<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: pages/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Booking - Search Rooms</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 0;
        }
        .navbar {
            background: #1a1a2e;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar h1 { margin: 0; font-size: 20px; }
        .navbar a {
            color: #f0c040;
            text-decoration: none;
            font-size: 14px;
        }
        .container {
            max-width: 600px;
            margin: 60px auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; color: #1a1a2e; }
        label { display: block; margin-top: 15px; font-weight: bold; color: #333; }
        input[type="date"] {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            margin-top: 25px;
            padding: 12px;
            background: #1a1a2e;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover { background: #f0c040; color: #1a1a2e; }
        .welcome {
            text-align: center;
            color: #555;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<div class="navbar">
    <h1>🏨 Hotel Booking System</h1>
    <a href="pages/logout.php">Logout</a>
</div>

<div class="container">
    <p class="welcome">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</p>
    <h2>Search Available Rooms</h2>

    <form action="pages/search.php" method="GET">
        <label for="checkin">Check-in Date</label>
        <input type="date" id="checkin" name="checkin" required>

        <label for="checkout">Check-out Date</label>
        <input type="date" id="checkout" name="checkout" required>

        <button type="submit">Search Rooms</button>
    </form>
</div>
<footer style="
        text-align: center;
        padding: 20px 20px;
        margin-top: 60px;
        font-size: 12px;
        color: #aaa;
        border-top: 1px solid #e8e8e8;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: #f0f2f7;
    ">
        &copy; 2026 Hotel Booking System &nbsp;|&nbsp; University of Hertfordshire MSc Project
    </footer>

</body>
</html>