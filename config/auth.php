<?php
// Site settings
$site_name = "Real-Time Chat Room";
$max_message_length = 1000;
$allow_file_sharing = true;
$max_file_size = 5 * 1024 * 1024; // 5MB

// Authentication functions
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        // Check if this is an API request
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Authentication required'
            ]);
            exit;
        }
        
        // Regular web request
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    global $conn;
    if (!isset($_SESSION['user_id']) || !isAdmin($conn, $_SESSION['user_id'])) {
        // Check if this is an API request
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Admin access required'
            ]);
            exit;
        }
        
        // Regular web request
        header('Location: index.php');
        exit;
    }
}

function isRoomAdmin($conn, $room_id, $user_id) {
    $stmt = $conn->prepare("SELECT admin_id FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    
    return $room && $room['admin_id'] == $user_id;
}

function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) return null;
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'avatar' => $_SESSION['avatar'] ?? 'default.png',
        'is_admin' => $_SESSION['is_admin'] ?? 0
    ];
}
