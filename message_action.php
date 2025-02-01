<?php
// Prevent any output before headers
ob_start();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

try {
    require_once 'config/database.php';
    require_once 'includes/functions.php';

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    // Get action and message ID
    $action = $_POST['action'] ?? '';
    $message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
    
    if (!in_array($action, ['edit', 'delete']) || !$message_id) {
        throw new Exception('Invalid request');
    }

    // Verify message belongs to user
    $stmt = $conn->prepare("
        SELECT content 
        FROM messages 
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    
    if (!$stmt) {
        throw new Exception('Database error');
    }
    
    $stmt->bind_param("ii", $message_id, $_SESSION['user_id']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to verify message ownership');
    }
    
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        throw new Exception('Message not found or access denied', 403);
    }

    if ($action === 'edit') {
        $new_content = trim($_POST['content'] ?? '');
        if (empty($new_content)) {
            throw new Exception('Message content cannot be empty');
        }

        $stmt = $conn->prepare("
            UPDATE messages 
            SET content = ?, edited = 1, edited_at = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception('Database error');
        }
        
        $stmt->bind_param("sii", $new_content, $message_id, $_SESSION['user_id']);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update message');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Message updated successfully',
            'content' => $new_content,
            'edited' => true
        ]);
    } else { // delete action
        $stmt = $conn->prepare("
            DELETE FROM messages 
            WHERE id = ? AND user_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception('Database error');
        }
        
        $stmt->bind_param("ii", $message_id, $_SESSION['user_id']);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete message');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Message deleted successfully'
        ]);
    }

} catch (Exception $e) {
    $code = $e->getCode();
    http_response_code($code >= 400 && $code < 600 ? $code : 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
