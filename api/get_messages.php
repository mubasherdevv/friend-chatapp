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

$room_id = $_GET['room_id'] ?? null;
$after_id = $_GET['after_id'] ?? 0;

if (!$room_id) {
    echo json_encode(['success' => false, 'error' => 'Room ID is required']);
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

// Get messages
$stmt = $conn->prepare("
    SELECT m.id, m.content as text, m.created_at, m.user_id,
           u.username, u.avatar
    FROM messages m
    JOIN users u ON m.user_id = u.id
    WHERE m.room_id = ? AND m.id > ?
    ORDER BY m.created_at ASC
    LIMIT 50
");

$stmt->bind_param("ii", $room_id, $after_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

// Update user's last_seen
$stmt = $conn->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();

echo json_encode([
    'success' => true,
    'messages' => $messages
]);
