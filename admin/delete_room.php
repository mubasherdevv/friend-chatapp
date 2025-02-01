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

if (isset($data['room_id'])) {
    $room_id = $data['room_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete room messages
        $stmt = $conn->prepare("DELETE FROM messages WHERE room_id = ?");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        
        // Delete room members
        $stmt = $conn->prepare("DELETE FROM room_members WHERE room_id = ?");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        
        // Get room avatar before deleting
        $stmt = $conn->prepare("SELECT avatar FROM rooms WHERE id = ?");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $room = $result->fetch_assoc();
        
        // Delete room
        $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        
        // Delete room avatar if it's not the default
        if ($room && $room['avatar'] !== 'default_room.png') {
            $avatar_path = "../uploads/room_avatars/" . $room['avatar'];
            if (file_exists($avatar_path)) {
                unlink($avatar_path);
            }
        }
        
        // Commit transaction
        $conn->commit();
        $response['success'] = true;
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $response['error'] = $e->getMessage();
    }
}

header('Content-Type: application/json');
echo json_encode($response);
