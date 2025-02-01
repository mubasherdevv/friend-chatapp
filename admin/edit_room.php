<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$room_id = $_GET['id'] ?? 0;

// Verify room exists and user is admin
$stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ? AND admin_id = ?");
$stmt->bind_param("ii", $room_id, $_SESSION['user_id']);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();

if (!$room) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $new_password = trim($_POST['new_password'] ?? '');
    
    // Handle avatar upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/room_avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $newFileName = uniqid() . '.' . $fileExtension;
        $uploadFile = $uploadDir . $newFileName;
        
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadFile)) {
            // Delete old avatar if it's not the default
            if ($room['avatar'] !== 'default_room.png') {
                @unlink($uploadDir . $room['avatar']);
            }
            $room['avatar'] = $newFileName;
        }
    }
    
    // Update room details
    if (!empty($new_password)) {
        // If new password is provided, hash it
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE rooms SET name = ?, description = ?, avatar = ?, password = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $description, $room['avatar'], $hashed_password, $room_id);
    } else {
        // If no new password, keep the existing password
        $stmt = $conn->prepare("UPDATE rooms SET name = ?, description = ?, avatar = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $description, $room['avatar'], $room_id);
    }
    
    if ($stmt->execute()) {
        header('Location: dashboard.php');
        exit;
    }
}

$is_password_protected = !empty($room['password']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Room - Chat Room</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-96">
            <h2 class="text-2xl font-bold mb-6 text-center">Edit Room</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Room Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($room['name'] ?? ''); ?>" 
                           required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><?php echo htmlspecialchars($room['description'] ?? ''); ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Current Avatar</label>
                    <img src="../uploads/room_avatars/<?php echo htmlspecialchars($room['avatar'] ?? 'default_room.png'); ?>" 
                         alt="Room Avatar" 
                         class="w-20 h-20 rounded-full object-cover mt-1">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">New Avatar</label>
                    <input type="file" name="avatar" accept="image/*" class="mt-1 block w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Room Password
                        <?php if ($is_password_protected): ?>
                            <span class="text-xs text-yellow-600">(Currently password protected)</span>
                        <?php endif; ?>
                    </label>
                    <input type="password" name="new_password" 
                           placeholder="<?php echo $is_password_protected ? 'Enter new password to change' : 'Leave empty for public room'; ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-sm text-gray-500">
                        <?php if ($is_password_protected): ?>
                            Leave empty to keep current password
                        <?php else: ?>
                            Set a password to make the room private
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex justify-end space-x-4 pt-4">
                    <a href="dashboard.php" 
                       class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
