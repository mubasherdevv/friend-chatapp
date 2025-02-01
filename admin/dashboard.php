<?php
session_start();
require_once '../config/database.php';
require_once 'auth_middleware.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../auth/login.php');
    exit;
}

// Get all rooms for admin users
$stmt = $conn->prepare("
    SELECT r.*, 
           u.username as admin_username,
           CASE WHEN r.password IS NOT NULL THEN 1 ELSE 0 END as is_protected,
           (SELECT COUNT(*) FROM room_members WHERE room_id = r.id) as member_count
    FROM rooms r 
    JOIN users u ON r.admin_id = u.id
    ORDER BY r.created_at DESC
");
$stmt->execute();
$rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get room members for each room
foreach ($rooms as &$room) {
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.avatar, u.is_online,
               CASE 
                   WHEN u.last_seen IS NULL THEN NULL
                   ELSE TIMESTAMPDIFF(MINUTE, u.last_seen, NOW())
               END as minutes_since_active
        FROM room_members rm
        JOIN users u ON rm.user_id = u.id
        WHERE rm.room_id = ?
        ORDER BY u.is_online DESC, u.last_seen DESC
    ");
    $stmt->bind_param("i", $room['id']);
    $stmt->execute();
    $room['members'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Room Management</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Manage all chat rooms and their members</p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="admin_dashboard.php" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 px-3 py-2 rounded-md">Analytics Dashboard</a>
                <a href="../index.php" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 px-3 py-2 rounded-md">Back to Chat</a>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($rooms as $room): ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <?php echo htmlspecialchars($room['name']); ?>
                            <?php if ($room['is_protected']): ?>
                                <span class="ml-2 text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Protected</span>
                            <?php endif; ?>
                        </h3>
                        <div class="flex items-center">
                            <button onclick="editRoom(<?php echo $room['id']; ?>)" class="text-blue-600 hover:text-blue-800 mr-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button onclick="deleteRoom(<?php echo $room['id']; ?>)" class="text-red-600 hover:text-red-800">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        <p>Admin: <?php echo htmlspecialchars($room['admin_username']); ?></p>
                        <p>Members: <?php echo $room['member_count']; ?></p>
                        <p>Created: <?php echo date('M j, Y', strtotime($room['created_at'])); ?></p>
                    </div>
                    <div class="border-t dark:border-gray-700 pt-4">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Recent Members</h4>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach (array_slice($room['members'], 0, 5) as $member): ?>
                            <div class="flex items-center bg-gray-50 dark:bg-gray-700 rounded-full px-3 py-1">
                                <div class="relative">
                                    <img src="../uploads/avatars/<?php echo htmlspecialchars($member['avatar']); ?>" 
                                         alt="" 
                                         class="w-6 h-6 rounded-full">
                                    <span class="absolute bottom-0 right-0 block h-2 w-2 rounded-full ring-2 ring-white <?php echo $member['is_online'] ? 'bg-green-400' : 'bg-gray-300'; ?>"></span>
                                </div>
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($member['username']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    function editRoom(roomId) {
        // Implement room editing functionality
        window.location.href = `edit_room.php?id=${roomId}`;
    }

    function deleteRoom(roomId) {
        if (confirm('Are you sure you want to delete this room? This action cannot be undone.')) {
            fetch('delete_room.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    room_id: roomId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to delete room');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the room');
            });
        }
    }
    </script>
</body>
</html>
