<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['room_id'])) {
    echo json_encode(['error' => 'Room ID is required']);
    exit;
}

$room_id = $_GET['room_id'];

// Get all members with their status
$stmt = $conn->prepare("
    SELECT u.id, u.username, u.avatar, u.is_admin, u.online_status, u.last_seen,
           r.admin_id = u.id as is_room_admin
    FROM room_members rm
    JOIN users u ON rm.user_id = u.id
    JOIN rooms r ON rm.room_id = r.id
    WHERE rm.room_id = ?
    ORDER BY 
        u.is_admin DESC,
        u.online_status DESC,
        u.username ASC
");

$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

$admins = [];
$online = [];
$offline = [];

while ($member = $result->fetch_assoc()) {
    $member['avatar'] = $member['avatar'] ?? 'default.png';
    
    if ($member['is_admin'] || $member['is_room_admin']) {
        $admins[] = $member;
    } elseif ($member['online_status']) {
        $online[] = $member;
    } else {
        $offline[] = $member;
    }
}

echo json_encode([
    'admins' => $admins,
    'online' => $online,
    'offline' => $offline
]);
