<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'auth_middleware.php';

// Ensure user is admin
requireAdmin();

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
$stmt->execute();
$total_users = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM rooms");
$stmt->execute();
$total_rooms = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages");
$stmt->execute();
$total_messages = $stmt->get_result()->fetch_assoc()['count'];

// Get daily active users for the past week
$stmt = $conn->prepare("
    SELECT DATE(last_seen) as date, COUNT(DISTINCT id) as active_users
    FROM users
    WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(last_seen)
    ORDER BY date ASC
");
$stmt->execute();
$daily_active_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get hourly message count for today
$stmt = $conn->prepare("
    SELECT HOUR(created_at) as hour, COUNT(*) as message_count
    FROM messages
    WHERE DATE(created_at) = CURDATE()
    GROUP BY HOUR(created_at)
    ORDER BY hour ASC
");
$stmt->execute();
$hourly_messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get top 5 most active rooms
$stmt = $conn->prepare("
    SELECT 
        r.name,
        COALESCE(r.avatar, 'default_room.png') as avatar,
        COUNT(m.id) as message_count,
        COUNT(DISTINCT m.user_id) as active_users,
        r.description,
        r.background_color,
        r.text_color,
        r.max_members
    FROM rooms r
    LEFT JOIN messages m ON r.id = m.room_id
    WHERE m.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY r.id
    ORDER BY message_count DESC
    LIMIT 5
");
$stmt->execute();
$active_rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get reported content
$stmt = $conn->prepare("
    SELECT r.id, r.reason, r.status, r.created_at,
           u.username as reporter_name,
           ru.username as reported_user_name,
           m.content as reported_content
    FROM reports r
    JOIN users u ON r.reporter_id = u.id
    JOIN messages m ON r.message_id = m.id
    JOIN users ru ON m.user_id = ru.id
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute();
$pending_reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get users list with pagination
$users_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $users_per_page;

$stmt = $conn->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM rooms WHERE admin_id = u.id) as rooms_count,
           (SELECT COUNT(*) FROM messages WHERE user_id = u.id) as messages_count,
           CASE 
               WHEN u.last_seen IS NULL THEN 0
               ELSE TIMESTAMPDIFF(MINUTE, u.last_seen, NOW())
           END as minutes_since_active
    FROM users u
    ORDER BY u.is_online DESC, COALESCE(u.last_seen, '1970-01-01') DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $users_per_page, $offset);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total pages for users
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
$stmt->execute();
$total_users_count = $stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_users_count / $users_per_page);

// Get recent rooms
$stmt = $conn->prepare("
    SELECT r.*, 
           u.username as admin_username,
           (SELECT COUNT(*) FROM room_members WHERE room_id = r.id) as members_count,
           (SELECT COUNT(*) FROM messages WHERE room_id = r.id) as messages_count
    FROM rooms r
    JOIN users u ON r.admin_id = u.id
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get admin settings
$stmt = $conn->prepare("SELECT * FROM admin_settings");
$stmt->execute();
$settings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$site_name = getSiteName($conn);
?>

<!DOCTYPE html>
<html lang="en" class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo htmlspecialchars($site_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Transition styles for theme switching */
        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        
        /* Dark mode styles */
        .dark body {
            background-color: #1a1a1a;
            color: #ffffff;
        }
        
        .dark .bg-white {
            background-color: #2d2d2d;
        }
        
        .dark .text-gray-800 {
            color: #f3f4f6;
        }
        
        .dark .text-gray-600 {
            color: #d1d5db;
        }

        .dark .border-gray-200 {
            border-color: #374151;
        }

        .dark .shadow-md {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }
    </style>
    <?php include '../includes/theme_toggle.php'; ?>
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <?php include 'includes/user_edit_modal.php'; ?>
    
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white shadow-lg dark:bg-gray-800">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <h1 class="text-xl font-bold text-gray-800 dark:text-gray-200">Admin Dashboard</h1>
                        </div>
                        <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                            <a href="#analytics" class="text-gray-900 dark:text-gray-200 inline-flex items-center px-1 pt-1 border-b-2 border-indigo-500 dark:border-indigo-400 text-sm font-medium">
                                Analytics
                            </a>
                            <a href="#users" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 inline-flex items-center px-1 pt-1 border-b-2 border-transparent hover:border-gray-300 dark:hover:border-gray-700 text-sm font-medium">
                                Users
                            </a>
                            <a href="#rooms" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 inline-flex items-center px-1 pt-1 border-b-2 border-transparent hover:border-gray-300 dark:hover:border-gray-700 text-sm font-medium">
                                Rooms
                            </a>
                            <!-- <a href="#moderation" class="text-gray-500 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 border-transparent hover:border-gray-300 text-sm font-medium">
                                Moderation
                            </a> -->
                        </div>
                    </div>
                    <div class="flex items-center">
                    <a href="dashboard.php" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 px-3 py-2 rounded-md">Room Settings</a>
                        <a href="settings.php" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 px-3 py-2 rounded-md">Settings</a>
                        <a href="../index.php" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 px-3 py-2 rounded-md">Back to Chat</a>
                    </div>
                </div>
            </div>
            
        </nav>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Analytics Section -->
            <div id="analytics" class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-200 mb-4">Analytics</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                    <!-- Stats Cards -->
                    <div class="bg-white dark:bg-gray-700 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-indigo-500 dark:bg-indigo-600 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Users</dt>
                                        <dd class="text-lg font-bold text-gray-900 dark:text-gray-200"><?php echo number_format($total_users); ?></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-700 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-green-500 dark:bg-green-600 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Active Rooms</dt>
                                        <dd class="text-lg font-bold text-gray-900 dark:text-gray-200"><?php echo number_format($total_rooms); ?></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-700 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-yellow-500 dark:bg-yellow-600 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Messages</dt>
                                        <dd class="text-lg font-bold text-gray-900 dark:text-gray-200"><?php echo number_format($total_messages); ?></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white dark:bg-gray-700 p-4 rounded-lg shadow">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">Daily Active Users</h3>
                        <canvas id="dailyActiveUsersChart"></canvas>
                    </div>
                    <div class="bg-white dark:bg-gray-700 p-4 rounded-lg shadow">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">Message Activity Today</h3>
                        <canvas id="messageActivityChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Users Section -->
            <div id="users" class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-200 mb-4">Users</h2>
                <div class="bg-white dark:bg-gray-700 shadow rounded-lg">
                    <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-600 sm:px-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200">Users</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                            <thead class="bg-gray-50 dark:bg-gray-600">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Active</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rooms</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Messages</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-700 divide-y divide-gray-200 dark:divide-gray-600">
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-3 w-3 rounded-full <?php echo $user['is_online'] ? 'bg-green-500' : 'bg-gray-300'; ?>"></div>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                                                <?php 
                                                if ($user['is_online']) {
                                                    echo 'Online';
                                                } else {
                                                    echo $user['minutes_since_active'] > 0 ? $user['minutes_since_active'] . ' mins ago' : 'Never';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <img class="h-8 w-8 rounded-full" src="../uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" alt="">
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                                    <?php echo htmlspecialchars($user['username']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($user['email']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo formatTimestamp($user['last_seen']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo $user['rooms_count']; ?> rooms
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo $user['messages_count']; ?> messages
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                                    class="text-indigo-600 dark:text-indigo-500 hover:text-indigo-900 dark:hover:text-indigo-400 mr-3">
                                                Edit
                                            </button>
                                            <button onclick="toggleAdmin(<?php echo $user['id']; ?>, <?php echo $user['is_admin']; ?>)" 
                                                    class="text-blue-600 dark:text-blue-500 hover:text-blue-900 dark:hover:text-blue-400 mr-3">
                                                <?php echo $user['is_admin'] ? 'Remove Admin' : 'Make Admin'; ?>
                                            </button>
                                            <button onclick="deleteUser(<?php echo $user['id']; ?>)" 
                                                    class="text-red-600 dark:text-red-500 hover:text-red-900 dark:hover:text-red-400">
                                                Delete
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-600 sm:px-6">
                            <div class="flex items-center justify-between">
                                <div class="flex-1 flex justify-between sm:hidden">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                            Previous
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                            Next
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm text-gray-700 dark:text-gray-300">
                                            Showing page <span class="font-medium"><?php echo $page; ?></span> of <span class="font-medium"><?php echo $total_pages; ?></span>
                                        </p>
                                    </div>
                                    <div>
                                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <a href="?page=<?php echo $i; ?>" 
                                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 <?php echo $i === $page ? 'bg-gray-100 dark:bg-gray-800' : ''; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            <?php endfor; ?>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rooms Section -->
            <div id="rooms" class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-200 mb-4">Rooms</h2>
                <div class="bg-white dark:bg-gray-700 shadow rounded-lg">
                    <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-600 sm:px-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200">Recent Rooms</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                            <thead class="bg-gray-50 dark:bg-gray-600">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Room</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Admin</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Stats</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-700 divide-y divide-gray-200 dark:divide-gray-600">
                                <?php foreach ($recent_rooms as $room): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <img class="h-8 w-8 rounded-full" src="../uploads/room_avatars/<?php echo htmlspecialchars($room['avatar']); ?>" alt="">
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                                        <?php echo htmlspecialchars($room['name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        Created <?php echo formatTimestamp($room['created_at']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($room['admin_username']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <div><?php echo $room['members_count']; ?> members</div>
                                            <div><?php echo $room['messages_count']; ?> messages</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="../room.php?id=<?php echo $room['id']; ?>" class="text-indigo-600 dark:text-indigo-500 hover:text-indigo-900 dark:hover:text-indigo-400">View</a>
                                            <button onclick="deleteRoom(<?php echo $room['id']; ?>)" class="ml-4 text-red-600 dark:text-red-500 hover:text-red-900 dark:hover:text-red-400">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Content Moderation
            <div id="moderation" class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Content Moderation</h2>
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                        <h3 class="text-lg font-medium text-gray-900">Reported Messages</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reporter</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reported User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($pending_reports as $report): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($report['reporter_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($report['reported_user_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="max-w-xs truncate">
                                            <?php echo htmlspecialchars($report['reported_content']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($report['reason']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="handleReport(<?php echo $report['id']; ?>, 'delete')" class="text-red-600 hover:text-red-900 mr-2">Delete</button>
                                        <button onclick="handleReport(<?php echo $report['id']; ?>, 'ignore')" class="text-gray-600 hover:text-gray-900">Ignore</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div> -->

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
    // Initialize charts
    document.addEventListener('DOMContentLoaded', function() {
        // Daily Active Users Chart
        const dauCtx = document.getElementById('dailyActiveUsersChart').getContext('2d');
        new Chart(dauCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($daily_active_users, 'date')); ?>,
                datasets: [{
                    label: 'Active Users',
                    data: <?php echo json_encode(array_column($daily_active_users, 'active_users')); ?>,
                    borderColor: 'rgb(79, 70, 229)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Message Activity Chart
        const macCtx = document.getElementById('messageActivityChart').getContext('2d');
        new Chart(macCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($hourly_messages, 'hour')); ?>,
                datasets: [{
                    label: 'Messages',
                    data: <?php echo json_encode(array_column($hourly_messages, 'message_count')); ?>,
                    backgroundColor: 'rgba(79, 70, 229, 0.2)',
                    borderColor: 'rgb(79, 70, 229)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    });

    // Handle report actions
    function handleReport(reportId, action) {
        if (confirm('Are you sure you want to ' + action + ' this report?')) {
            fetch('handle_report.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    report_id: reportId,
                    action: action
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing the report.');
            });
        }
    }

    function toggleAdmin(userId, currentStatus) {
        if (confirm(`Are you sure you want to ${currentStatus ? 'remove' : 'grant'} admin privileges?`)) {
            fetch('toggle_admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId
                })
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      location.reload();
                  } else {
                      alert('Failed to update admin status');
                  }
              });
        }
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
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      location.reload();
                  } else {
                      alert('Failed to delete room');
                  }
              });
        }
    }

    function editUser(user) {
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUsername').value = user.username;
        document.getElementById('editEmail').value = user.email;
        document.getElementById('editPassword').value = '';
        document.getElementById('userModal').classList.remove('hidden');
    }

    function closeUserModal() {
        document.getElementById('userModal').classList.add('hidden');
    }

    function deleteUser(userId) {
        if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            return;
        }

        fetch('user_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_user&user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || 'Failed to delete user');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the user');
        });
    }

    document.getElementById('editUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('user_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || 'Failed to update user');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the user');
        });
    });

    // Close modal when clicking outside
    document.getElementById('userModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeUserModal();
        }
    });
    </script>
</body>
</html>
