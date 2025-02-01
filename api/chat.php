<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_profile':
        $user_id = intval($_GET['user_id'] ?? 0);
        if (!$user_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid user ID']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, username, avatar, is_admin FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        echo json_encode([
            'id' => $user['id'],
            'username' => $user['username'],
            'avatar' => $user['avatar'],
            'is_admin' => (bool)$user['is_admin']
        ]);
        break;

    case 'send_message':
        $data = json_decode(file_get_contents('php://input'), true);
        $room_id = intval($data['room_id'] ?? 0);
        $message = trim($data['message'] ?? '');

        if (!$room_id || !$message) {
            http_response_code(400);
            echo json_encode(['error' => 'Room ID and message are required']);
            exit;
        }

        // Check if user is member of room
        $stmt = $conn->prepare("SELECT 1 FROM room_members WHERE room_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $room_id, $_SESSION['user_id']);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            http_response_code(403);
            echo json_encode(['error' => 'Not a member of this room']);
            exit;
        }

        // Insert message
        $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $room_id, $_SESSION['user_id'], $message);
        
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to send message']);
            exit;
        }

        echo json_encode(['success' => true, 'message_id' => $stmt->insert_id]);
        break;

    case 'get_messages':
        $room_id = intval($_GET['room_id'] ?? 0);
        $last_id = intval($_GET['last_id'] ?? 0);

        if (!$room_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Room ID is required']);
            exit;
        }

        // Check if user is member of room
        $stmt = $conn->prepare("SELECT 1 FROM room_members WHERE room_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $room_id, $_SESSION['user_id']);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            http_response_code(403);
            echo json_encode(['error' => 'Not a member of this room']);
            exit;
        }

        // Get messages
        $sql = "SELECT m.*, u.username, u.avatar 
                FROM messages m 
                JOIN users u ON m.user_id = u.id 
                WHERE m.room_id = ?";
        if ($last_id) {
            $sql .= " AND m.id > ?";
        }
        $sql .= " ORDER BY m.created_at DESC LIMIT 50";

        $stmt = $conn->prepare($sql);
        if ($last_id) {
            $stmt->bind_param("ii", $room_id, $last_id);
        } else {
            $stmt->bind_param("i", $room_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = [
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'username' => $row['username'],
                'avatar' => $row['avatar'],
                'message' => $row['message'],
                'created_at' => $row['created_at']
            ];
        }

        echo json_encode(['messages' => $messages]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
?>
