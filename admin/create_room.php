<?php
session_start();
require_once '../config/database.php';
require_once 'auth_middleware.php';
requireAdmin();

function generateInvitationCode() {
    return substr(str_shuffle(str_repeat('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', 3)), 0, 8);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $admin_id = $_SESSION['user_id'];
    $invitation_code = generateInvitationCode();
    
    // Handle file upload
    $avatar = 'default_room.png';
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/room_avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $newFileName = uniqid() . '.' . $fileExtension;
        $uploadFile = $uploadDir . $newFileName;
        
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadFile)) {
            $avatar = $newFileName;
        }
    }
    
    // Get room limit from settings
    $stmt = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'max_user_rooms'");
    $stmt->execute();
    $result = $stmt->get_result();
    $max_rooms = ($result->num_rows > 0) ? (int)$result->fetch_assoc()['setting_value'] : 5;
    
    // Check if user has reached room limit
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM rooms WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $current_rooms = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($current_rooms >= $max_rooms) {
        $error = "You have reached the maximum number of rooms you can create ($max_rooms).";
    } else {
        $stmt = $conn->prepare("INSERT INTO rooms (name, description, avatar, invitation_code, admin_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $name, $description, $avatar, $invitation_code, $admin_id);
        
        if ($stmt->execute()) {
            $room_id = $stmt->insert_id;
            // Add admin as room member
            $stmt = $conn->prepare("INSERT INTO room_members (room_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $room_id, $admin_id);
            $stmt->execute();
            header('Location: admin_dashboard.php');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Room - Chat Room</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-96">
            <h2 class="text-2xl font-bold mb-6 text-center">Create New Room</h2>
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Room Name</label>
                    <input type="text" name="name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Room Avatar</label>
                    <input type="file" name="avatar" accept="image/*" class="mt-1 block w-full">
                </div>
                <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">Create Room</button>
            </form>
            <a href="admin_dashboard.php" class="mt-4 block text-center text-sm text-indigo-600 hover:text-indigo-500">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
