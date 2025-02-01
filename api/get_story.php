<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$story_id = $_GET['id'] ?? 0;

if (!$story_id) {
    echo json_encode(['success' => false, 'message' => 'Story ID is required']);
    exit;
}

// Get story details
$stmt = $conn->prepare("
    SELECT s.*, u.username, u.avatar
    FROM stories s
    JOIN users u ON s.user_id = u.id
    WHERE s.id = ? AND s.expires_at > NOW()
    AND (
        s.visibility = 'public'
        OR (s.visibility = 'friends' AND EXISTS (
            SELECT 1 FROM friends f 
            WHERE (f.user_id1 = ? AND f.user_id2 = s.user_id)
            OR (f.user_id2 = ? AND f.user_id1 = s.user_id)
        ))
        OR s.user_id = ?
    )
");
$stmt->bind_param("iiii", $story_id, $user_id, $user_id, $user_id);
$stmt->execute();
$story = $stmt->get_result()->fetch_assoc();

if (!$story) {
    echo json_encode(['success' => false, 'message' => 'Story not found or expired']);
    exit;
}

// Record view if not already viewed
$stmt = $conn->prepare("
    INSERT IGNORE INTO story_views (story_id, viewer_id)
    VALUES (?, ?)
");
$stmt->bind_param("ii", $story_id, $user_id);
$stmt->execute();

// Format story content
if ($story['content_type'] !== 'text') {
    $story['content'] = 'uploads/stories/' . $story['content'];
}

echo json_encode(['success' => true, 'story' => $story]);
