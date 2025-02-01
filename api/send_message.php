<?php
// Prevent any output before headers
ob_start();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Clear any previous output
ob_clean();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get POST data
$room_id = $_POST['room_id'] ?? null;
$message = $_POST['message'] ?? null;

// If no POST data, try JSON
if (!$room_id || !$message) {
    $data = json_decode(file_get_contents('php://input'), true);
    $room_id = $data['room_id'] ?? null;
    $message = $data['message'] ?? null;
}

if (!$room_id || !$message) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Check if user is member of the room
$stmt = $conn->prepare("SELECT 1 FROM room_members WHERE room_id = ? AND user_id = ?");
$stmt->bind_param("ii", $room_id, $_SESSION['user_id']);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Not a member of this room']);
    exit;
}

// Insert message
$stmt = $conn->prepare("INSERT INTO messages (room_id, user_id, content) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $room_id, $_SESSION['user_id'], $message);

if ($stmt->execute()) {
    // Update user's last_seen
    $stmt = $conn->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    
    $message_id = $conn->insert_id;
    echo json_encode([
        'success' => true,
        'message_id' => $message_id,
        'message' => 'Message sent successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to send message: ' . $stmt->error
    ]);
}
