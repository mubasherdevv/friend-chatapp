<?php
// Prevent any output before headers
ob_start();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header first
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

try {
    require_once 'config/database.php';
    require_once 'includes/functions.php';
    require_once 'includes/avatar_helper.php';

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    $room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 1;
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

    if (!$room_id) {
        throw new Exception('Invalid room ID');
    }

    // Verify user has access to this room
    $stmt = $conn->prepare("
        SELECT 1 
        FROM room_members 
        WHERE room_id = ? AND user_id = ?
        LIMIT 1
    ");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $room_id, $_SESSION['user_id']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to verify room access');
    }
    
    if (!$stmt->get_result()->fetch_row()) {
        throw new Exception('Access denied to this room', 403);
    }

    // Get messages
    $stmt = $conn->prepare("
        SELECT m.*, u.username, u.avatar,
               DATE_FORMAT(m.created_at, '%h:%i %p') as formatted_time
        FROM messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.room_id = ? AND m.id > ?
        ORDER BY m.id ASC
        LIMIT 50
    ");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $room_id, $last_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to fetch messages: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $messages = [];
    
    while ($row = $result->fetch_assoc()) {
        $avatar_url = getAvatarUrl($row['avatar']);
        $messages[] = [
            'id' => (int)$row['id'],
            'content' => $row['content'],
            'user_id' => (int)$row['user_id'],
            'timestamp' => $row['formatted_time'],
            'avatar_url' => $avatar_url
        ];
    }
    
    // Clear any buffered output
    ob_clean();
    
    // Send success response
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);

} catch (Exception $e) {
    $code = $e->getCode();
    http_response_code($code >= 400 && $code < 600 ? $code : 500);
    
    // Clear any buffered output
    ob_clean();
    
    // Send error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}