<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['message_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$message_id = $_GET['message_id'];

// Get reactions for the message
$stmt = $conn->prepare("
    SELECT r.reaction_type, COUNT(*) as count,
           GROUP_CONCAT(u.username) as users
    FROM reactions r
    JOIN users u ON r.user_id = u.id
    WHERE r.message_id = ?
    GROUP BY r.reaction_type
");
$stmt->bind_param("i", $message_id);
$stmt->execute();
$result = $stmt->get_result();

$reactions = [];
while ($row = $result->fetch_assoc()) {
    $reactions[] = [
        'type' => $row['reaction_type'],
        'count' => $row['count'],
        'users' => explode(',', $row['users']),
        'message_id' => $message_id
    ];
}

echo json_encode(['reactions' => $reactions]);
?>
