<?php
// Path configurations for the application

// Base paths
define('BASE_URL', 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']));
define('BASE_PATH', dirname(__DIR__));

// Directory paths
define('UPLOADS_DIR', '/uploads');
define('AVATARS_DIR', UPLOADS_DIR . '/avatars');
define('WALLPAPERS_DIR', UPLOADS_DIR . '/wallpapers');
define('INCLUDES_DIR', '/includes');
define('CONFIG_DIR', '/config');
define('AUTH_DIR', '/auth');

// Full paths for file operations
define('UPLOADS_PATH', BASE_PATH . UPLOADS_DIR);
define('AVATARS_PATH', BASE_PATH . AVATARS_DIR);
define('WALLPAPERS_PATH', BASE_PATH . WALLPAPERS_DIR);
define('INCLUDES_PATH', BASE_PATH . INCLUDES_DIR);
define('CONFIG_PATH', BASE_PATH . CONFIG_DIR);
define('AUTH_PATH', BASE_PATH . AUTH_DIR);

// URL paths for browser access
define('UPLOADS_URL', BASE_URL . UPLOADS_DIR);
define('AVATARS_URL', BASE_URL . AVATARS_DIR);
define('WALLPAPERS_URL', BASE_URL . WALLPAPERS_DIR);

// Helper function to get correct URL for assets
function asset_url($path) {
    return BASE_URL . '/' . ltrim($path, '/');
}

// Helper function to get correct path for includes
function include_path($file) {
    return INCLUDES_PATH . '/' . ltrim($file, '/');
}

// Create required directories if they don't exist
$directories = [UPLOADS_PATH, AVATARS_PATH, WALLPAPERS_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}
