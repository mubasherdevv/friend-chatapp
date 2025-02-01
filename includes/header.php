<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Required includes
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/avatar_helper.php';

// Prevent direct access
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Refresh user data from database
if (isset($_SESSION['user_id'])) {
    $refresh_stmt = $conn->prepare("SELECT avatar, username FROM users WHERE id = ?");
    $refresh_stmt->bind_param("i", $_SESSION['user_id']);
    $refresh_stmt->execute();
    $refresh_result = $refresh_stmt->get_result();
    if ($user_data = $refresh_result->fetch_assoc()) {
        $_SESSION['avatar'] = $user_data['avatar'];
        $_SESSION['username'] = $user_data['username'];
    }
}

// Set default theme if not set
if (!isset($_COOKIE['theme'])) {
    setcookie('theme', 'light', time() + 31536000, '/');
    $_COOKIE['theme'] = 'light';
}

$isDark = isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark';

// Get friend request count if user is logged in
$friend_request_count = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM friends 
        WHERE friend_id = ? AND status = 'pending'
    ");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $friend_request_count = $row['count'];
            }
        }
    }
}

// Handle theme toggle via AJAX
if (isset($_POST['toggle_theme'])) {
    $newTheme = ($_COOKIE['theme'] === 'dark') ? 'light' : 'dark';
    setcookie('theme', $newTheme, time() + 31536000, '/');
    $_COOKIE['theme'] = $newTheme;
    exit;
}

// Only output HTML if not an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest'):
?>

<!DOCTYPE html>
<html lang="en" class="<?php echo $isDark ? 'dark' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Chat Room</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        
        :root {
            --primary-gradient: linear-gradient(135deg, #00DC82 0%, #36E4DA 50%, #0047E1 100%);
        }

        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            position: relative;
        }

        .dark body {
            background: linear-gradient(135deg, #1a365d 0%, #2d3748 50%, #1a202c 100%);
        }
        
        .dark .bg-white {
            background-color: #2d2d2d;
        }
        
        .dark .text-gray-800 {
            color: #f3f4f6;
        }
        
        .dark .text-gray-600 {
            color: #e5e7eb;
        }
        
        .dark .border-gray-200 {
            border-color: #374151;
        }
        
        .logo-container:hover .logo-icon {
            transform: scale(1.05);
        }
        
        .logo-icon {
            transition: transform 0.3s ease;
        }
        
        .dropdown-menu .fas {
            width: 20px;
            text-align: center;
            margin-right: 8px;
            color: var(--primary-color, #4a90e2);
        }

        .dropdown-item {
            padding: 8px 16px;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background-color: rgba(74, 144, 226, 0.1);
            color: var(--primary-color, #4a90e2);
        }

        .dropdown-divider {
            margin: 8px 0;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggleBtn = document.getElementById('theme-toggle');
            const lightIcon = document.getElementById('theme-toggle-light-icon');
            const darkIcon = document.getElementById('theme-toggle-dark-icon');
            const html = document.documentElement;

            function setTheme(isDark) {
                if (isDark) {
                    html.classList.add('dark');
                    lightIcon.classList.add('hidden');
                    darkIcon.classList.remove('hidden');
                } else {
                    html.classList.remove('dark');
                    lightIcon.classList.remove('hidden');
                    darkIcon.classList.add('hidden');
                }
            }

            themeToggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const isDark = html.classList.contains('dark');
                const newTheme = isDark ? 'light' : 'dark';
                
                // Send AJAX request to update theme
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'toggle_theme=1'
                }).then(() => {
                    setTheme(!isDark);
                });
            });

            // Set initial theme based on PHP-set class
            setTheme(html.classList.contains('dark'));
        });
    </script>
</head>
<body class="min-h-screen bg-white dark:bg-gray-900 transition-colors duration-200">
    <div class="header-gradient shadow-lg bg-white/10 dark:bg-gray-800/10 transition-colors duration-200">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center h-20">
                <!-- Left side - Logo -->
                <div class="flex items-center space-x-2">
                    <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : ''; ?>index.php" 
                       class="flex items-center space-x-3">
                        <span class="text-2xl font-bold text-white">Chat Room</span>
                    </a>
                </div>

                <!-- Right side -->
                <div class="flex items-center space-x-4">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php
                        // Check if user is admin
                        $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
                        $stmt->bind_param("i", $_SESSION['user_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();
                        $is_admin = $user && $user['is_admin'];
                        ?>

                        <?php if ($is_admin): ?>
                        <!-- Admin Dashboard Link -->
                        <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '' : 'admin/'; ?>admin_dashboard.php" 
                           class="flex items-center space-x-2 text-gray-700 hover:text-blue-500 dark:text-gray-300 dark:hover:text-blue-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>Admin</span>
                        </a>
                        <?php endif; ?>

                        <!-- Friends Link -->
                        <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : ''; ?>friends.php" 
                           class="relative flex items-center text-gray-700 hover:text-blue-500 dark:text-gray-300 dark:hover:text-blue-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <?php if ($friend_request_count > 0): ?>
                                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                    <?php echo $friend_request_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <!-- User Profile Dropdown -->
                        <div class="relative" x-data="{ isOpen: false }">
                            <button @click="isOpen = !isOpen" 
                                    class="flex items-center space-x-3 bg-white/10 hover:bg-white/20 rounded-full pl-2 pr-4 py-1 transition-all duration-300">
                                <div class="w-8 h-8 rounded-full overflow-hidden border-2 border-white/30">
                                    <img src="<?php 
                                        $avatar_url = getAvatarUrl(isset($_SESSION['avatar']) ? $_SESSION['avatar'] : null);
                                        echo $avatar_url . '?v=' . time(); // Add cache-busting parameter
                                    ?>" 
                                        alt="<?php echo htmlspecialchars($_SESSION['username']); ?>'s Profile" 
                                        class="w-full h-full object-cover">
                                </div>
                                <span class="text-white font-medium"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            <!-- Dropdown Menu -->
                            <div x-show="isOpen" 
                                 @click.away="isOpen = false"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute right-0 mt-2 w-48 rounded-lg shadow-lg py-1 bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-[60]"
                                 style="display: none;">
                                <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : ''; ?>profile.php" 
                                   @click="isOpen = false"
                                   class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-user fa-fw"></i> Profile
                                </a>
                                <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : ''; ?>leaderboard.php" 
                                   @click="isOpen = false"
                                   class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-trophy fa-fw"></i> Leaderboard
                                </a>
                                <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : ''; ?>rewards.php" 
                                   @click="isOpen = false"
                                   class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-award fa-fw"></i> Rewards
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : ''; ?>auth/logout.php"
                                   @click="isOpen = false"
                                   class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-sign-out-alt fa-fw"></i> Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : ''; ?>../auth/login.php" class="bg-white/10 hover:bg-white/20 text-white rounded-lg px-4 py-2 transition-all duration-300">
                            Login
                        </a>
                        <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : ''; ?>../auth/register.php" class="bg-primary-600 hover:bg-primary-700 text-white rounded-lg px-4 py-2 transition-all duration-300">
                            Register
                        </a>
                    <?php endif; ?>

                    <!-- Theme Toggle -->
                    <button id="theme-toggle" type="button" class="bg-white/10 hover:bg-white/20 rounded-lg p-2.5 text-white">
                        <svg id="theme-toggle-dark-icon" class="<?php echo $isDark ? 'hidden' : ''; ?> w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                        </svg>
                        <svg id="theme-toggle-light-icon" class="<?php echo $isDark ? '' : 'hidden'; ?> w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>

<?php endif; ?>
