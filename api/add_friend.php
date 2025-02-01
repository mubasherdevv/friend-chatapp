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

if (!$friend_id) {
    echo json_encode(['success' => false, 'message' => 'Friend ID is required']);
    exit;
}

// Check if already friends or pending request exists
$check_query = "
    SELECT * FROM friends 
    WHERE (user_id = ? AND friend_id = ?) 
    OR (user_id = ? AND friend_id = ?)
";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $existing = $result->fetch_assoc();
    if ($existing['status'] === 'accepted') {
        echo json_encode(['success' => false, 'message' => 'Already friends']);
        exit;
    } elseif ($existing['status'] === 'pending') {
        echo json_encode(['success' => false, 'message' => 'Friend request already sent']);
        exit;
    }
}

// Get friend's username for activity feed
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $friend_id);
$stmt->execute();
$friend = $stmt->get_result()->fetch_assoc();

// Send friend request
$stmt = $conn->prepare("
    INSERT INTO friends (user_id, friend_id, status)
    VALUES (?, ?, 'pending')
");
$stmt->bind_param("ii", $user_id, $friend_id);

if ($stmt->execute()) {
    // Log activity
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
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send friend request']);
}
