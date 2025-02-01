<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$friend_id = $data['user_id'] ?? 0;
$action = $data['action'] ?? ''; // 'accept' or 'reject'

if (!$friend_id || !in_array($action, ['accept', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Check if friend request exists
$stmt = $conn->prepare("
    SELECT * FROM friends 
    WHERE user_id = ? AND friend_id = ? AND status = 'pending'
");
$stmt->bind_param("ii", $friend_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No pending friend request found']);
    exit;
}

if ($action === 'accept') {
    // Accept friend request
    $stmt = $conn->prepare("
        UPDATE friends 
        SET status = 'accepted' 
        WHERE user_id = ? AND friend_id = ?
    ");
    $stmt->bind_param("ii", $friend_id, $user_id);
    
    if ($stmt->execute()) {
        // Get friend's username for activity feed
        $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->bind_param("i", $friend_id);
        $stmt->execute();
        $friend = $stmt->get_result()->fetch_assoc();
        
        // Log activity for both users
        $activity_data = json_encode([
            'friend_id' => $friend_id,
            'friend_name' => $friend['username']
        ]);
        
        $stmt = $conn->prepare("
            INSERT INTO activity_feed (user_id, activity_type, activity_data, visibility)
            VALUES (?, 'friend_add', ?, 'public')
        ");
        $stmt->bind_param("is", $user_id, $activity_data);
        $stmt->execute();
        
        // Update friend suggestions
        $conn->query("DELETE FROM friend_suggestions WHERE user_id = $user_id AND suggested_user_id = $friend_id");
        $conn->query("DELETE FROM friend_suggestions WHERE user_id = $friend_id AND suggested_user_id = $user_id");
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to accept friend request']);
    }
} else {
    // Reject friend request
    $stmt = $conn->prepare("
        DELETE FROM friends 
        WHERE user_id = ? AND friend_id = ?
    ");
    $stmt->bind_param("ii", $friend_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reject friend request']);
    }
}
