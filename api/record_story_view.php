<?php
session_start();
require_once '../config/database.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['story_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Story ID is required']);
    exit;
}

$story_id = (int)$data['story_id'];
$viewer_id = (int)$_SESSION['user_id'];

try {
    // First check if the story exists and is still valid
    $stmt = $conn->prepare("
        SELECT user_id, visibility 
        FROM stories 
        WHERE id = ? 
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->bind_param("i", $story_id);
    $stmt->execute();
    $story = $stmt->get_result()->fetch_assoc();

    if (!$story) {
        http_response_code(404);
        echo json_encode(['error' => 'Story not found or expired']);
        exit;
    }

    // Check if viewer has permission to view this story
    if ($story['visibility'] === 'private' && $story['user_id'] !== $viewer_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized to view this story']);
        exit;
    }

    if ($story['visibility'] === 'friends') {
        // Check if they are friends
        $stmt = $conn->prepare("
            SELECT status 
            FROM friends 
            WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
            AND status = 'accepted'
        ");
        $stmt->bind_param("iiii", $viewer_id, $story['user_id'], $story['user_id'], $viewer_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            http_response_code(403);
            echo json_encode(['error' => 'Not authorized to view this story']);
            exit;
        }
    }

    // Record the view if not already viewed
    $stmt = $conn->prepare("
        INSERT IGNORE INTO story_views (story_id, viewer_id)
        VALUES (?, ?)
    ");
    $stmt->bind_param("ii", $story_id, $viewer_id);
    $stmt->execute();

    // Get updated view count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as view_count 
        FROM story_views 
        WHERE story_id = ?
    ");
    $stmt->bind_param("i", $story_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        'success' => true,
        'view_count' => $result['view_count']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    error_log($e->getMessage());
}
