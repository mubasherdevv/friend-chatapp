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

if (!isset($data['message_id'])) {
    echo json_encode(['success' => false, 'error' => 'Message ID is required']);
    exit;
}

$message_id = (int)$data['message_id'];
$user_id = (int)$_SESSION['user_id'];

try {
    // Verify message ownership and get room_id
    $stmt = $conn->prepare("SELECT id, room_id FROM messages WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $message_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Message not found or unauthorized']);
        exit;
    }

    $message = $result->fetch_assoc();
    $room_id = $message['room_id'];
    
    // Delete message
    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $message_id, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete message");
    }
    
    // Emit socket event for real-time deletion
    $socket_data = json_encode([
        'event' => 'message_deleted',
        'room_id' => $room_id,
        'message_id' => $message_id
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
        'message' => 'Message deleted successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to delete message'
    ]);
}
