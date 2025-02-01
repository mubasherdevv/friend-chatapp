<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAdmin() {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
    
    global $conn;
    
    // Check if database connection exists
    if (!isset($conn)) {
        require_once __DIR__ . '/../config/database.php';
    }
    
    // Verify admin status
    $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
    if (!$stmt) {
        die("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user || !$user['is_admin']) {
        header('Location: ../index.php');
        exit;
    }
}
?>
