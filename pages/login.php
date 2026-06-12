<?php
session_start();
require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $conn->prepare(
            "SELECT user_id, name, password_hash, role FROM users WHERE email = ?"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            header("Location: ../index.php");
            exit();
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Hotel Booking System</title>
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
        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #666;
        }
        .register-link a {
            color: #2E5FA3;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="card">
    <h2>Welcome Back</h2>
    <p class="subtitle">Hotel Booking System</p>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email"
               placeholder="Enter your email" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               placeholder="Enter your password" required>

        <button type="submit">Login</button>
    </form>

    <div class="register-link">
        No account yet? <a href="register.php">Register here</a>
    </div>
</div>
</body>
</html>