<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Access Denied</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: #f0f2f7; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
            .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 420px; text-align: center; }
            .icon { font-size: 60px; margin-bottom: 20px; }
            h2 { color: #dc2626; margin-bottom: 12px; }
            p { color: #666; font-size: 14px; margin-bottom: 24px; line-height: 1.6; }
            .btn { display: inline-block; padding: 11px 24px; border-radius: 8px; font-size: 14px; font-weight: bold; text-decoration: none; margin: 6px; }
            .btn-home { background: #1F3864; color: white; }
            .btn-back { background: #f0f2f7; color: #444; }
        </style>
    </head>
    <body>
    <div class="card">
        <div class="icon">🚫</div>
        <h2>Access Denied</h2>
        <p>You do not have permission to view this page. Only administrators can access the admin panel.</p>
        <a href="../index.php" class="btn btn-home">Go to Home</a>
        <a href="../pages/login.php" class="btn btn-back">Go to Login</a>
    </div>
    </body>
    </html>
    <?php
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_number = trim($_POST['room_number']);
    $room_type   = trim($_POST['room_type']);
    $price       = trim($_POST['price']);
    $description = trim($_POST['description']);
    $image_name  = '';

    if (empty($room_number) || empty($price)) {
        $error = 'Room number and price are required.';
    } else {

        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $allowed = ['jpg','jpeg','png','webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $error = 'Only JPG, PNG and WEBP images are allowed.';
            } elseif ($_FILES['image']['size'] > 10 * 1024 * 1024) {
                $error = 'Image must be under 10MB.';
            } else {
                $image_name = 'room_' . time() . '.' . $ext;
                $upload_path = '../uploads/rooms/' . $image_name;
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $error = 'Failed to upload image. Please try again.';
                    $image_name = '';
                }
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare(
                "INSERT INTO rooms (room_number, room_type, price_per_night, description, image) 
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("ssdss", $room_number, $room_type, $price, $description, $image_name);

            if ($stmt->execute()) {
                $message = 'Room added successfully!';
            } else {
                $error = 'Failed to add room. Please try again.';
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
    <title>Add Room — Admin</title>
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
        .navbar a { color: #f0c040; text-decoration: none; font-size: 14px; }
        .container {
            max-width: 520px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; color: #1F3864; margin-bottom: 24px; }
        label { display: block; font-size: 13px; font-weight: bold; color: #444; margin-bottom: 6px; }
        input, select, textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 18px;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #2E5FA3;
        }
        .image-preview {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 18px;
            display: none;
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
        }
        button:hover { background: #2E5FA3; }
        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 13px;
            text-align: center;
        }
        .error-msg {
            background: #fee2e2;
            color: #dc2626;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 13px;
            text-align: center;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
        }
        .back-link a { color: #2E5FA3; text-decoration: none; font-weight: bold; margin: 0 8px; }
    </style>
</head>
<body>

<div class="navbar">
    <h1>🏨 Admin Panel</h1>
    <a href="../index.php">Back to Home</a>
</div>

<div class="container">
    <h2>Add New Room</h2>

    <?php if ($message): ?>
        <div class="success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">

        <label>Room Number</label>
        <input type="text" name="room_number"
               placeholder="e.g. G101, Z202" required>

        <label>Room Type</label>
        <select name="room_type">
            <option value="Single">Single</option>
            <option value="Double">Double</option>
            <option value="Suite">Suite</option>
        </select>

        <label>Price Per Night (£)</label>
        <input type="number" name="price"
               placeholder="e.g. 75.00" step="0.01" min="1" required>

        <label>Description</label>
        <textarea name="description" rows="3"
                  placeholder="Brief description of the room"></textarea>

        <label>Room Image (optional — JPG, PNG or WEBP, max 10MB)</label>
        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"
               onchange="previewImage(this)">
        <img id="preview" class="image-preview" src="" alt="Image preview">

        <button type="submit">Add Room</button>

    </form>

    <div class="back-link">
        <a href="add_room.php">Add Another Room</a>
        &nbsp;|&nbsp;
        <a href="../index.php">Back to Home</a>
    </div>
</div>

<script>
function previewImage(input) {
    const preview = document.getElementById('preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

</body>
</html>