<?php
ob_start();
session_start();
require_once 'config/database.php';

// Clear any output and set JSON header
ob_clean();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit;
}

try {
    // Get user data
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.username,
            u.avatar,
            CASE 
                WHEN u.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'Online'
                WHEN u.last_seen IS NOT NULL THEN CONCAT('Last seen ', DATE_FORMAT(u.last_seen, '%b %d, %Y at %h:%i %p'))
                ELSE 'Never'
            END as last_seen,
            COUNT(m.id) as total_messages
        FROM users u 
        LEFT JOIN messages m ON u.id = m.user_id
        WHERE u.id = ?
        GROUP BY u.id
    ");

    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception('Query error: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        throw new Exception('User not found');
    }

    echo json_encode([
        'status' => 'success',
        'user' => $user
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
exit;
