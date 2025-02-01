<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['message_id']) || !isset($data['content'])) {
    echo json_encode(['success' => false, 'error' => 'Message ID and content are required']);
    exit;
}

$message_id = (int)$data['message_id'];
$content = trim($data['content']);
$user_id = (int)$_SESSION['user_id'];

if (empty($content)) {
    echo json_encode(['success' => false, 'error' => 'Message content cannot be empty']);
    exit;
}

try {
    // Verify message ownership
    $stmt = $conn->prepare("SELECT id FROM messages WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $message_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Message not found or unauthorized']);
        exit;
    }
    
    // Update message
    $stmt = $conn->prepare("UPDATE messages SET content = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $content, $message_id, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update message");
    }
    
    // Get room_id for the message
    $stmt = $conn->prepare("SELECT room_id FROM messages WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $message = $result->fetch_assoc();
    $room_id = $message['room_id'];

    // Emit socket event for real-time update
    $socket_data = json_encode([
        'event' => 'message_edited',
        'room_id' => $room_id,
        'message_id' => $message_id,
        'content' => $content
    ]);

    // Send to Socket.IO server
    $ch = curl_init('http://localhost:3000/emit');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $socket_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
    
    echo json_encode([
        'success' => true,
        'message' => 'Message updated successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update message'
    ]);
}
