<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $room_id = $data['room_id'] ?? null;
    $content = $data['content'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    if (!$room_id || !$content) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    // Check if user is a member of this room
    $stmt = $conn->prepare("SELECT 1 FROM room_members WHERE room_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $room_id, $user_id);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit;
    }
    
    if ($stmt->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not a member of this room']);
        exit;
    }
    
    // Insert the message
    $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $room_id, $user_id, $content);
    
    if ($stmt->execute()) {
        // Get the inserted message with user details
        $message_id = $stmt->insert_id;
        $stmt = $conn->prepare("
            SELECT m.*, u.username, u.avatar, u.is_admin 
            FROM messages m 
            JOIN users u ON m.user_id = u.id 
            WHERE m.id = ?
        ");
        $stmt->bind_param("i", $message_id);
        
        if ($stmt->execute()) {
            $message = $stmt->get_result()->fetch_assoc();
            $response = [
                'success' => true,
                'message' => [
                    'id' => $message['id'],
                    'room_id' => $message['room_id'],
                    'user_id' => $message['user_id'],
                    'content' => $message['content'],
                    'created_at' => $message['created_at'],
                    'username' => $message['username'],
                    'avatar' => $message['avatar'],
                    'is_admin' => $message['is_admin']
                ]
            ];
            echo json_encode($response);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch message details']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send message']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
