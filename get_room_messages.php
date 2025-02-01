<?php
// Start session and include required files
require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear any output buffer
ob_clean();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

// Validate room access
$stmt = $conn->prepare("SELECT COUNT(*) as is_member FROM room_members WHERE room_id = ? AND user_id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit;
}

$stmt->bind_param("ii", $room_id, $user_id);
if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit;
}

$result = $stmt->get_result()->fetch_assoc();

if (!$result || !$result['is_member']) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

try {
    // Get new messages
    $stmt = $conn->prepare("
        SELECT m.*, u.username, u.avatar,
               DATE_FORMAT(m.created_at, '%H:%i') as formatted_time
        FROM messages m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.room_id = ? AND m.id > ? 
        ORDER BY m.created_at ASC
    ");

    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    $stmt->bind_param("ii", $room_id, $last_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }

    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Format messages
    $formatted_messages = array_map(function($message) use ($user_id) {
        return [
            'id' => intval($message['id']),
            'content' => $message['content'],
            'user_id' => intval($message['user_id']),
            'username' => $message['username'],
            'avatar' => $message['avatar'] ? 'uploads/avatars/' . $message['avatar'] : 'images/default-avatar.png',
            'timestamp' => $message['formatted_time'],
            'is_own' => intval($message['user_id']) === $user_id
        ];
    }, $messages);

    echo json_encode([
        'status' => 'success',
        'messages' => $formatted_messages
    ]);
    exit;

} catch (Exception $e) {
    error_log("Error in get_room_messages.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch messages: ' . $e->getMessage()
    ]);
    exit;
}
