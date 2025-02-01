<?php
// Production Database Configuration for InfinityFree
define('DB_HOST', 'YOUR_INFINITYFREE_DB_HOST'); // Get this from InfinityFree MySQL settings
define('DB_USER', 'YOUR_INFINITYFREE_DB_USER'); // Get this from InfinityFree MySQL settings
define('DB_PASS', 'YOUR_INFINITYFREE_DB_PASS'); // Get this from InfinityFree MySQL settings
define('DB_NAME', 'YOUR_INFINITYFREE_DB_NAME'); // Get this from InfinityFree MySQL settings

// Define path constants for production
define('BASE_PATH', dirname(__DIR__)); // Root directory path
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('AVATARS_PATH', UPLOADS_PATH . '/avatars');
define('WALLPAPERS_PATH', UPLOADS_PATH . '/wallpapers');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('CONFIG_PATH', BASE_PATH . '/config');
define('AUTH_PATH', BASE_PATH . '/auth');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

// Set charset to utf8mb4
if (!$conn->set_charset("utf8mb4")) {
    error_log("Error loading character set utf8mb4: " . $conn->error);
}

// Set timezone
date_default_timezone_set('Asia/Karachi');

// Create required directories if they don't exist
$directories = [UPLOADS_PATH, AVATARS_PATH, WALLPAPERS_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

return $conn;
