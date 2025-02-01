<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get pending friend requests
$stmt = $conn->prepare("
    SELECT f.*, u.username, u.avatar, u.bio
    FROM friends f
    JOIN users u ON f.user_id = u.id
    WHERE f.friend_id = ? AND f.status = 'pending'
    ORDER BY f.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$friend_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get sent friend requests
$stmt = $conn->prepare("
    SELECT f.*, u.username, u.avatar, u.bio
    FROM friends f
    JOIN users u ON f.friend_id = u.id
    WHERE f.user_id = ? AND f.status = 'pending'
    ORDER BY f.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sent_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friend Requests - Chat Room</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Pending Friend Requests -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                    Friend Requests (<?php echo count($friend_requests); ?>)
                </h2>
                <div class="space-y-4">
                    <?php if (empty($friend_requests)): ?>
                    <p class="text-gray-500 dark:text-gray-400">No pending friend requests</p>
                    <?php else: ?>
                        <?php foreach ($friend_requests as $request): ?>
                        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-4">
                            <div class="flex items-center space-x-4">
                                <img src="uploads/avatars/<?php echo htmlspecialchars($request['avatar'] ?: 'default.png'); ?>" 
                                     class="w-12 h-12 rounded-full">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($request['username']); ?>
                                    </div>
                                    <?php if ($request['bio']): ?>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($request['bio']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Sent <?php echo timeAgo($request['created_at']); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <button onclick="respondToRequest(<?php echo $request['user_id']; ?>, 'accept')"
                                        class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                                    Accept
                                </button>
                                <button onclick="respondToRequest(<?php echo $request['user_id']; ?>, 'reject')"
                                        class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
                                    Reject
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sent Friend Requests -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                    Sent Requests (<?php echo count($sent_requests); ?>)
                </h2>
                <div class="space-y-4">
                    <?php if (empty($sent_requests)): ?>
                    <p class="text-gray-500 dark:text-gray-400">No sent friend requests</p>
                    <?php else: ?>
                        <?php foreach ($sent_requests as $request): ?>
                        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-4">
                            <div class="flex items-center space-x-4">
                                <img src="uploads/avatars/<?php echo htmlspecialchars($request['avatar'] ?: 'default.png'); ?>" 
                                     class="w-12 h-12 rounded-full">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($request['username']); ?>
                                    </div>
                                    <?php if ($request['bio']): ?>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($request['bio']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Sent <?php echo timeAgo($request['created_at']); ?>
                                    </div>
                                </div>
                            </div>
                            <button onclick="cancelRequest(<?php echo $request['friend_id']; ?>)"
                                    class="text-red-500 hover:text-red-600">
                                Cancel
                            </button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    function respondToRequest(userId, action) {
        fetch('api/respond_friend_request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: userId,
                action: action
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to process friend request');
            }
        });
    }

    function cancelRequest(friendId) {
        if (confirm('Are you sure you want to cancel this friend request?')) {
            fetch('api/add_friend.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: friendId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to cancel friend request');
                }
            });
        }
    }
    </script>
</body>
</html>
