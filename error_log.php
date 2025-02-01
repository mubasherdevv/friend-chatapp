<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'debug.log');

// Test basic PHP functionality
echo "PHP is working\n";

// Test database connection
try {
    require_once 'config/database.php';
    echo "Database connection successful\n";
} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    echo "Database Error: " . $e->getMessage() . "\n";
}

// Print server information
echo "\nServer Information:\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Current Script: " . $_SERVER['SCRIPT_FILENAME'] . "\n";

// Check required PHP extensions
$required_extensions = ['mysqli', 'pdo', 'pdo_mysql', 'json'];
echo "\nChecking PHP Extensions:\n";
foreach ($required_extensions as $ext) {
    echo $ext . ": " . (extension_loaded($ext) ? "Loaded" : "Not Loaded") . "\n";
}

// Check file permissions
$files_to_check = [
    'config/database.php',
    '.env',
    'debug.log'
];

echo "\nChecking File Permissions:\n";
foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo $file . ": Exists, Permissions: " . decoct(fileperms($file) & 0777) . "\n";
    } else {
        echo $file . ": Does not exist\n";
    }
}
?>
