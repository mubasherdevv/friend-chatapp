<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
$messageId = $data['messageId'] ?? null;
$roomId = $data['roomId'] ?? null;
$newMessage = $data['newMessage'] ?? null;

if (!$messageId || !$roomId || !$newMessage) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

// Check message length
$maxLength = getMaxMessageLength($conn);
if (strlen($newMessage) > $maxLength) {
    echo json_encode(['success' => false, 'error' => "Message exceeds maximum length of {$maxLength} characters"]);
    exit;
}

// Check if user is authorized to edit the message (message owner or admin)
$stmt = $conn->prepare("SELECT user_id FROM messages WHERE id = ? AND room_id = ?");
$stmt->bind_param("ii", $messageId, $roomId);
$stmt->execute();
$result = $stmt->get_result();
$message = $result->fetch_assoc();

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'Message not found']);
    exit;
}

$isAdmin = isAdmin($conn, $_SESSION['user_id']);
if ($message['user_id'] != $_SESSION['user_id'] && !$isAdmin) {
    echo json_encode(['success' => false, 'error' => 'Not authorized to edit this message']);
    exit;
}

// Update the message
$stmt = $conn->prepare("UPDATE messages SET message = ?, updated_at = NOW() WHERE id = ?");
$stmt->bind_param("si", $newMessage, $messageId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update message']);
}
