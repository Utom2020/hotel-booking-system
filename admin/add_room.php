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

// Handle Add Room
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $room_number = trim($_POST['room_number']);
    $room_type   = trim($_POST['room_type']);
    $price       = trim($_POST['price']);
    $description = trim($_POST['description']);
    $image_name  = '';

    if (empty($room_number) || empty($price)) {
        $error = 'Room number and price are required.';
    } elseif (!preg_match('/^[A-Za-z0-9]+$/', $room_number)) {
        $error = 'Room number can only contain letters and numbers. No symbols allowed.';
    } else {
        $check = $conn->prepare("SELECT room_id FROM rooms WHERE room_number = ?");
        $check->bind_param("s", $room_number);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $error = 'Room number already exists. Please use a different room number.';
        } else {
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
                        $error = 'Failed to upload image.';
                        $image_name = '';
                    }
                }
            }
            if (empty($error)) {
                $stmt = $conn->prepare("INSERT INTO rooms (room_number, room_type, price_per_night, description, image) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdss", $room_number, $room_type, $price, $description, $image_name);
                if ($stmt->execute()) {
                    $message = 'Room added successfully!';
                } else {
                    $error = 'Failed to add room. Please try again.';
                }
            }
        }
    }
}

// Handle Update Room
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $room_id     = intval($_POST['room_id']);
    $room_number = trim($_POST['room_number']);
    $room_type   = trim($_POST['room_type']);
    $price       = trim($_POST['price']);
    $description = trim($_POST['description']);

    $existing = $conn->query("SELECT image FROM rooms WHERE room_id = $room_id")->fetch_assoc();
    $image_name = $existing['image'];

    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['image']['size'] <= 10 * 1024 * 1024) {
            $image_name = 'room_' . time() . '.' . $ext;
            $upload_path = '../uploads/rooms/' . $image_name;
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_path);
        }
    }

    $stmt = $conn->prepare("UPDATE rooms SET room_number=?, room_type=?, price_per_night=?, description=?, image=? WHERE room_id=?");
    $stmt->bind_param("ssdssi", $room_number, $room_type, $price, $description, $image_name, $room_id);
    if ($stmt->execute()) {
        $message = 'Room updated successfully!';
    } else {
        $error = 'Failed to update room.';
    }
}

        // Handle Delete Room
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM rooms WHERE room_id = $delete_id");
    $message = 'Room deleted successfully!';
}// Fetch all rooms
$rooms = $conn->query("SELECT * FROM rooms ORDER BY room_id ASC");

// Fetch room to edit
$edit_room = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $result = $conn->query("SELECT * FROM rooms WHERE room_id = $edit_id");
    $edit_room = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rooms — Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f0f2f7; }
        .navbar { background: #1F3864; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .navbar h1 { font-size: 20px; }
        .navbar a { color: #f0c040; text-decoration: none; font-size: 14px; }
        .container { max-width: 600px; margin: 40px auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        h3 { color: #1F3864; margin-bottom: 16px; border-bottom: 2px solid #f0f2f7; padding-bottom: 8px; }
        label { display: block; font-size: 13px; font-weight: bold; color: #444; margin-bottom: 6px; }
        input, select, textarea { width: 100%; padding: 11px 14px; border: 1.5px solid #ddd; border-radius: 8px; font-size: 14px; margin-bottom: 18px; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #2E5FA3; }
        button { width: 100%; padding: 13px; background: #1F3864; color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: bold; cursor: pointer; margin-bottom: 10px; }
        button:hover { background: #2E5FA3; }
        .btn-edit { display: inline-block; padding: 6px 14px; background: #2E5FA3; color: white; border-radius: 6px; font-size: 12px; text-decoration: none; }
        .btn-delete { display: inline-block; padding: 6px 14px; background: #dc2626; color: white; border-radius: 6px; font-size: 12px; text-decoration: none; margin-left: 4px; }
        .success { background: #d1fae5; color: #065f46; padding: 10px 14px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; text-align: center; }
        .error-msg { background: #fee2e2; color: #dc2626; padding: 10px 14px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; text-align: center; }
        .room-table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 13px; }
        .room-table th { background: #1F3864; color: white; padding: 10px; text-align: left; }
        .room-table td { padding: 10px; border-bottom: 1px solid #eee; }
        .room-table tr:hover td { background: #f0f2f7; }
        .divider { border: none; border-top: 2px solid #f0f2f7; margin: 36px 0; }
        .back-link { text-align: center; margin-top: 20px; font-size: 13px; }
        .back-link a { color: #2E5FA3; text-decoration: none; font-weight: bold; margin: 0 8px; }
        .image-preview { width: 100%; max-height: 200px; object-fit: cover; border-radius: 8px; margin-bottom: 18px; display: none; }
        .hint { font-size: 12px; color: #888; margin-top: -14px; margin-bottom: 14px; }
    </style>
</head>
<body>

<div class="navbar">
    <h1>🏨 Admin Panel</h1>
    <a href="../index.php">Back to Home</a>
</div>

<div class="container">

    <?php if ($message): ?>
        <div class="success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- ADD ROOM SECTION -->
    <h3>Add New Room</h3>
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add">
        <label>Room Number</label>
        <input type="text" name="room_number" placeholder="e.g. G101, Z202" pattern="[A-Za-z0-9]+" title="Letters and numbers only — no symbols" required>
        <p class="hint">Letters and numbers only — no symbols or spaces</p>
        <label>Room Type</label>
        <select name="room_type">
            <option value="Single">Single</option>
            <option value="Double">Double</option>
            <option value="Suite">Suite</option>
        </select>
        <label>Price Per Night (£)</label>
        <input type="number" name="price" placeholder="e.g. 75.00" step="0.01" min="1" required>
        <label>Description</label>
        <textarea name="description" rows="3" placeholder="Brief description of the room"></textarea>
        <label>Room Image (optional — JPG, PNG or WEBP, max 10MB)</label>
        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" onchange="previewImage(this, 'preview-add')">
        <img id="preview-add" class="image-preview" src="" alt="Image preview">
        <button type="submit">Add Room</button>
    </form>

    <hr class="divider">

    <!-- EDIT ROOM SECTION -->
    <h3>Edit Existing Room</h3>

    <?php if ($edit_room): ?>
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="room_id" value="<?php echo $edit_room['room_id']; ?>">
        <label>Room Number</label>
        <input type="text" name="room_number" value="<?php echo htmlspecialchars($edit_room['room_number']); ?>" pattern="[A-Za-z0-9]+" title="Letters and numbers only — no symbols" required>
        <p class="hint">Letters and numbers only — no symbols or spaces</p>
        <label>Room Type</label>
        <select name="room_type">
            <option value="Single" <?php echo $edit_room['room_type'] === 'Single' ? 'selected' : ''; ?>>Single</option>
            <option value="Double" <?php echo $edit_room['room_type'] === 'Double' ? 'selected' : ''; ?>>Double</option>
            <option value="Suite" <?php echo $edit_room['room_type'] === 'Suite' ? 'selected' : ''; ?>>Suite</option>
        </select>
        <label>Price Per Night (£)</label>
        <input type="number" name="price" value="<?php echo $edit_room['price_per_night']; ?>" step="0.01" min="1" required>
        <label>Description</label>
        <textarea name="description" rows="3"><?php echo htmlspecialchars($edit_room['description']); ?></textarea>
        <label>Room Image (optional — leave blank to keep current)</label>
        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" onchange="previewImage(this, 'preview-edit')">
        <img id="preview-edit" class="image-preview" src="" alt="Image preview">
        <button type="submit">Update Room</button>
    </form>
    <?php else: ?>
    <p style="color:#888; font-size:13px; margin-bottom:16px;">Click Edit next to a room below to load it here.</p>
    <?php endif; ?>

    <!-- ROOMS LIST -->
    <table class="room-table">
        <tr>
            <th>Room</th>
            <th>Type</th>
            <th>Price</th>
            <th>Action</th>
        </tr>
        <?php while ($room = $rooms->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($room['room_number']); ?></td>
            <td><?php echo htmlspecialchars($room['room_type']); ?></td>
            <td>£<?php echo number_format($room['price_per_night'], 2); ?></td>
            <td>
                <a href="?edit_id=<?php echo $room['room_id']; ?>" class="btn-edit">Edit</a>
                <a href="?delete_id=<?php echo $room['room_id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this room?')">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

    <div class="back-link">
        <a href="../index.php">Back to Home</a>
    </div>
</div>

<script>
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
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