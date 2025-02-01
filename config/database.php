<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim(trim($value), '"');
        }
    }
}

// Database configuration - Check for Railway's MySQL environment variables first
define('DB_HOST', getenv('MYSQLHOST') ?: ($_ENV['DB_HOST'] ?? 'localhost'));
define('DB_USER', getenv('MYSQLUSER') ?: ($_ENV['DB_USER'] ?? 'root'));
define('DB_PASSWORD', getenv('MYSQLPASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? ''));
define('DB_NAME', getenv('MYSQLDATABASE') ?: ($_ENV['DB_NAME'] ?? 'chat_app'));
define('DB_PORT', getenv('MYSQLPORT') ?: ($_ENV['DB_PORT'] ?? '3306'));

try {
    // Create connection with error reporting
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
    
    // Set charset to utf8mb4
    $conn->set_charset('utf8mb4');
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
} catch (Exception $e) {
    // Log the error and display a user-friendly message
    error_log("Database connection error: " . $e->getMessage());
    die("Could not connect to the database. Please try again later.");
}
?>