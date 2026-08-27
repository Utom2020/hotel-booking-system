<?php
session_start();

require_once '../config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'An account with this email already exists.';
        } else {
            // Hash the password
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            error_log("Password hash: " . $password_hash);

            // Insert new user
            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)"
            );
            $stmt->bind_param("sss", $name, $email, $password_hash);

            if ($stmt->execute()) {
                header("Location: login.php");
                exit();
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Hotel Booking System</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f7;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 420px;
        }
        h2 {
            text-align: center;
            color: #1F3864;
            margin-bottom: 8px;
            font-size: 24px;
        }
        p.subtitle {
            text-align: center;
            color: #888;
            margin-bottom: 28px;
            font-size: 14px;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            color: #444;
            margin-bottom: 6px;
        }
        input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 18px;
            transition: border 0.2s;
        }
        input:focus {
            outline: none;
            border-color: #2E5FA3;
        }
        button {
            width: 100%;
            padding: 13px;
            background: #1F3864;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover { background: #2E5FA3; }
        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 13px;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #666;
        }
        .login-link a {
            color: #2E5FA3;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="card">
    <h2>Create Account</h2>
    <p class="subtitle">Hotel Booking System</p>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name"
               placeholder="Enter your full name" required>

        <label for="email">Email Address</label>
        <input type="email" id="email" name="email"
               placeholder="Enter your email" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               placeholder="Minimum 6 characters" required>

        <button type="submit">Create Account</button>
    </form>

    <div class="login-link">
        Already have an account? <a href="login.php">Login here</a>
    </div>
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
