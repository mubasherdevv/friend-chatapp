<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Parse input data
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['room_id']) || !isset($data['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$room_id = $data['room_id'];
$user_id = $data['user_id'];

// Check if the current user is a site admin
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();
$is_site_admin = $current_user['is_admin'];

// Get room information
$stmt = $conn->prepare("SELECT admin_id FROM rooms WHERE id = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();

if (!$room) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Room not found']);
    exit;
}

// Check if user has permission to kick (either site admin or room admin)
if (!$is_site_admin && $room['admin_id'] != $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized to kick members']);
    exit;
}

// Don't allow kicking room admin unless you're a site admin
if ($room['admin_id'] == $user_id && !$is_site_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Cannot kick room admin']);
    exit;
}

// Remove the member from the room
$stmt = $conn->prepare("DELETE FROM room_members WHERE room_id = ? AND user_id = ?");
$stmt->bind_param("ii", $room_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to kick member']);
}
?>
