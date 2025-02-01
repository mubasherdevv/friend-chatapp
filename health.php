<?php
// Set content type to JSON
header('Content-Type: application/json');

try {
    // Check if database connection is working
    require_once 'config/database.php';
    
    // Try to connect to database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    // Close the connection
    $conn->close();
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'status' => 'healthy',
        'timestamp' => time()
    ]);
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'status' => 'unhealthy',
        'error' => $e->getMessage()
    ]);
}
