<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'auth_middleware.php';

// Ensure user is admin
requireAdmin();

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
$response = ['success' => false];

if (isset($data['user_id'])) {
    $user_id = $data['user_id'];
    
    // Prevent self-demotion
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("UPDATE users SET is_admin = NOT is_admin WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
