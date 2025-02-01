<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$story_id = $data['story_id'] ?? null;

if (!$story_id) {
    echo json_encode(['success' => false, 'message' => 'Story ID is required']);
    exit;
}

// Check if already viewed
$stmt = $conn->prepare("SELECT id FROM story_views WHERE story_id = ? AND viewer_id = ?");
$stmt->bind_param("ii", $story_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Add view
    $stmt = $conn->prepare("INSERT INTO story_views (story_id, viewer_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $story_id, $user_id);
    $success = $stmt->execute();
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Story view recorded' : 'Failed to record view'
    ]);
} else {
    echo json_encode(['success' => true, 'message' => 'Story already viewed']);
}
