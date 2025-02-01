<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$group_id = $data['group_id'] ?? 0;

if (!$group_id) {
    echo json_encode(['success' => false, 'message' => 'Group ID is required']);
    exit;
}

// Check if user is already a member
$stmt = $conn->prepare("
    SELECT 1 FROM user_interests 
    WHERE user_id = ? AND interest_group_id = ?
");
$stmt->bind_param("ii", $user_id, $group_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Already a member of this group']);
    exit;
}

// Join group
$stmt = $conn->prepare("
    INSERT INTO user_interests (user_id, interest_group_id)
    VALUES (?, ?)
");
$stmt->bind_param("ii", $user_id, $group_id);

if ($stmt->execute()) {
    // Get group details for activity feed
    $stmt = $conn->prepare("
        SELECT name FROM interest_groups WHERE id = ?
    ");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $group = $stmt->get_result()->fetch_assoc();
    
    // Log activity
    $activity_data = json_encode([
        'group_id' => $group_id,
        'group_name' => $group['name']
    ]);
    
    $stmt = $conn->prepare("
        INSERT INTO activity_feed (user_id, activity_type, activity_data, visibility)
        VALUES (?, 'group_join', ?, 'public')
    ");
    $stmt->bind_param("is", $user_id, $activity_data);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to join group']);
}
