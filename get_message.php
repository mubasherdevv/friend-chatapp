<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Message ID is required']);
    exit;
}

$message_id = (int)$_GET['id'];
$user_id = (int)$_SESSION['user_id'];

try {
    // Get message
    $stmt = $conn->prepare("
        SELECT m.*, u.username 
        FROM messages m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.id = ? AND m.user_id = ?
    ");
    
    $stmt->bind_param("ii", $message_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $message = $result->fetch_assoc();
    
    if (!$message) {
        echo json_encode(['success' => false, 'error' => 'Message not found or unauthorized']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to get message'
    ]);
}
