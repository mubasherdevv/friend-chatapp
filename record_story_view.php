<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
$story_id = $data['story_id'] ?? null;
$viewer_id = $_SESSION['user_id'];

if (!$story_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Story ID is required']);
    exit;
}

try {
    // Check if story exists and hasn't expired
    $stmt = $conn->prepare("
        SELECT user_id 
        FROM stories 
        WHERE id = ? 
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->bind_param("i", $story_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $story = $result->fetch_assoc();

    if (!$story) {
        http_response_code(404);
        echo json_encode(['error' => 'Story not found or expired']);
        exit;
    }

    // Don't record view if user is viewing their own story
    if ($story['user_id'] === $viewer_id) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Own story view not recorded']);
        exit;
    }

    // Record the view (if not already viewed)
    $stmt = $conn->prepare("
        INSERT IGNORE INTO story_views (story_id, viewer_id)
        VALUES (?, ?)
    ");
    $stmt->bind_param("ii", $story_id, $viewer_id);
    $stmt->execute();

    // Get updated view count
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT viewer_id) as view_count
        FROM story_views
        WHERE story_id = ?
    ");
    $stmt->bind_param("i", $story_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $view_count = $result->fetch_assoc()['view_count'];

    echo json_encode([
        'success' => true,
        'view_count' => $view_count
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    error_log($e->getMessage());
}
