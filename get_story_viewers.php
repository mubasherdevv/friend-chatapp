<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['story_id'])) {
    echo json_encode(['error' => 'Story ID is required']);
    exit;
}

$story_id = $_GET['story_id'];
$user_id = $_SESSION['user_id'];

// First, verify that the user owns this story
$check_owner = $conn->prepare("SELECT user_id FROM stories WHERE id = ?");
$check_owner->bind_param("i", $story_id);
$check_owner->execute();
$story = $check_owner->get_result()->fetch_assoc();

if (!$story || $story['user_id'] !== $user_id) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get the viewers
$query = "
    SELECT 
        u.id,
        u.username,
        u.avatar,
        sv.viewed_at
    FROM story_views sv
    JOIN users u ON sv.viewer_id = u.id
    WHERE sv.story_id = ?
    ORDER BY sv.viewed_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $story_id);
$stmt->execute();
$result = $stmt->get_result();

$viewers = [];
while ($row = $result->fetch_assoc()) {
    $viewers[] = [
        'id' => $row['id'],
        'username' => $row['username'],
        'avatar' => $row['avatar'],
        'viewed_at' => $row['viewed_at']
    ];
}

echo json_encode([
    'success' => true,
    'viewers' => $viewers,
    'total' => count($viewers)
]);
