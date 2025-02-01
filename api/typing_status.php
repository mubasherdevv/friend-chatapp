<?php
session_start();

// Prevent any output before headers
ob_start();

// Include files after starting output buffering
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Set JSON content type
header('Content-Type: application/json');

// Disable error reporting for production
error_reporting(0);
ini_set('display_errors', 0);

// Default response
$response = ['success' => false, 'error' => 'Unknown error'];

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }

    // Get request parameters
    $room_id = $_POST['room_id'] ?? $_GET['room_id'] ?? 0;
    $action = $_GET['action'] ?? '';

    if (!$room_id) {
        throw new Exception('Room ID is required');
    }

    // Verify user is member of the room
    $stmt = $conn->prepare("
        SELECT 1 FROM room_members 
        WHERE room_id = ? AND user_id = ?
    ");

    if (!$stmt) {
        throw new Exception('Database error');
    }

    $stmt->bind_param("ii", $room_id, $_SESSION['user_id']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to verify room membership');
    }

    if (!$stmt->get_result()->fetch_assoc()) {
        throw new Exception('Not authorized');
    }

    switch ($action) {
        case 'set':
            $is_typing = isset($_POST['is_typing']) ? (int)$_POST['is_typing'] : 0;
            
            $stmt = $conn->prepare("
                INSERT INTO typing_status (room_id, user_id, is_typing, last_updated) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    is_typing = VALUES(is_typing),
                    last_updated = NOW()
            ");

            if (!$stmt) {
                throw new Exception('Database error');
            }

            $stmt->bind_param("iii", $room_id, $_SESSION['user_id'], $is_typing);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update typing status');
            }

            $response = ['success' => true];
            break;

        case 'get':
            $stmt = $conn->prepare("
                SELECT u.username 
                FROM typing_status ts
                JOIN users u ON ts.user_id = u.id
                WHERE ts.room_id = ? 
                AND ts.user_id != ? 
                AND ts.is_typing = 1
                AND ts.last_updated >= NOW() - INTERVAL 5 SECOND
            ");

            if (!$stmt) {
                throw new Exception('Database error');
            }

            $stmt->bind_param("ii", $room_id, $_SESSION['user_id']);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to get typing status');
            }

            $result = $stmt->get_result();
            $typing_users = [];
            
            while ($row = $result->fetch_assoc()) {
                $typing_users[] = $row['username'];
            }

            $response = [
                'success' => true,
                'typing_users' => $typing_users
            ];
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    // Log error for debugging
    error_log("Typing status error: " . $e->getMessage());
    
    // Set error response
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

// Clean output buffer
ob_clean();

// Send JSON response
echo json_encode($response);
exit;
?>
