<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'auth_middleware.php';

// Ensure user is admin
requireAdmin();

// Prevent any output before JSON response
ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

// Don't allow actions on own account
if ($userId === $_SESSION['user_id']) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot perform this action on your own account']);
    exit;
}

switch ($action) {
    case 'get_user':
        $stmt = $conn->prepare("
            SELECT id, username, email, avatar, is_admin, created_at, is_online, last_active
            FROM users 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user) {
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
        }
        break;

    case 'update_user':
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $newPassword = trim($_POST['new_password'] ?? '');
        
        if (empty($username) || empty($email)) {
            http_response_code(400);
            echo json_encode(['error' => 'Username and email are required']);
            exit;
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update basic info
            $stmt = $conn->prepare("
                UPDATE users 
                SET username = ?, email = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssi", $username, $email, $userId);
            $stmt->execute();
            
            // Update password if provided
            if (!empty($newPassword)) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashedPassword, $userId);
                $stmt->execute();
            }
            
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update user']);
        }
        break;

    case 'delete_user':
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Check if this is the last admin
            $stmt = $conn->prepare("SELECT COUNT(*) as admin_count FROM users WHERE is_admin = 1");
            $stmt->execute();
            $adminCount = $stmt->get_result()->fetch_assoc()['admin_count'];
            
            $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $isAdmin = $stmt->get_result()->fetch_assoc()['is_admin'];
            
            if ($adminCount <= 1 && $isAdmin) {
                throw new Exception("Cannot delete the last admin user");
            }
            
            // First, get all rooms where user is admin
            $stmt = $conn->prepare("SELECT id FROM rooms WHERE admin_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $adminRooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            foreach ($adminRooms as $room) {
                // Delete all messages in the room
                $stmt = $conn->prepare("DELETE FROM messages WHERE room_id = ?");
                $stmt->bind_param("i", $room['id']);
                $stmt->execute();
                
                // Delete all room members
                $stmt = $conn->prepare("DELETE FROM room_members WHERE room_id = ?");
                $stmt->bind_param("i", $room['id']);
                $stmt->execute();
            }
            
            // Delete rooms where user is admin
            $stmt = $conn->prepare("DELETE FROM rooms WHERE admin_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            // Delete user's messages in other rooms
            $stmt = $conn->prepare("DELETE FROM messages WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            // Remove user from other rooms
            $stmt = $conn->prepare("DELETE FROM room_members WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            // Delete user's reports (where they are the reporter)
            $stmt = $conn->prepare("DELETE FROM reports WHERE reporter_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            // Delete typing status if table exists
            $result = $conn->query("SHOW TABLES LIKE 'typing_status'");
            if ($result->num_rows > 0) {
                $stmt = $conn->prepare("DELETE FROM typing_status WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
            }
            
            // Delete reactions if table exists
            $result = $conn->query("SHOW TABLES LIKE 'reactions'");
            if ($result->num_rows > 0) {
                $stmt = $conn->prepare("DELETE FROM reactions WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
            }
            
            // Delete direct messages if table exists
            $result = $conn->query("SHOW TABLES LIKE 'direct_messages'");
            if ($result->num_rows > 0) {
                $stmt = $conn->prepare("DELETE FROM direct_messages WHERE sender_id = ? OR receiver_id = ?");
                $stmt->bind_param("ii", $userId, $userId);
                $stmt->execute();
            }
            
            // Delete friend relationships
            $stmt = $conn->prepare("DELETE FROM friends WHERE user_id = ? OR friend_id = ?");
            $stmt->bind_param("ii", $userId, $userId);
            $stmt->execute();
            
            // Finally, delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error deleting user: " . $e->getMessage());
            http_response_code(500);
            ob_clean(); // Clean any previous output
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
