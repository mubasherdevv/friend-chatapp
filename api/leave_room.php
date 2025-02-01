<?php
session_start();
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function for debugging
function debug_log($message, $data = null) {
    $log = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $log .= " - Data: " . print_r($data, true);
    }
    error_log($log);
}

header('Content-Type: application/json');

debug_log("Leave room API called");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    debug_log("User not authenticated", $_SESSION);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    debug_log("Processing request for user: " . $_SESSION['user_id']);
    
    // Verify database connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection error: " . ($conn->connect_error ?? 'No connection'));
    }
    
    // Get POST data
    $input = file_get_contents('php://input');
    debug_log("Received input", $input);
    
    if (!$input) {
        throw new Exception('No input data received');
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data: ' . json_last_error_msg());
    }

    debug_log("Decoded JSON data", $data);

    $room_id = isset($data['room_id']) ? (int)$data['room_id'] : 0;
    debug_log("Room ID: " . $room_id);

    if (!$room_id) {
        throw new Exception('Invalid room ID');
    }

    // Check if user is a member of the room
    $check_member_sql = "SELECT * FROM room_members WHERE room_id = ? AND user_id = ?";
    debug_log("Checking membership with query: " . $check_member_sql);
    
    $stmt = $conn->prepare($check_member_sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare member check query: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $room_id, $_SESSION['user_id']);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute member check query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('You are not a member of this room');
    }

    // Check if user is not the room admin
    $check_admin_sql = "SELECT admin_id FROM rooms WHERE id = ?";
    debug_log("Checking admin status with query: " . $check_admin_sql);
    
    $stmt = $conn->prepare($check_admin_sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare admin check query: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $room_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute admin check query: ' . $stmt->error);
    }
    
    $room = $stmt->get_result()->fetch_assoc();
    if (!$room) {
        throw new Exception('Room not found');
    }

    if ($room['admin_id'] == $_SESSION['user_id']) {
        throw new Exception('Room admin cannot leave the room');
    }

    // Remove user from room_members
    $delete_sql = "DELETE FROM room_members WHERE room_id = ? AND user_id = ?";
    debug_log("Deleting member with query: " . $delete_sql);
    
    $stmt = $conn->prepare($delete_sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare delete query: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $room_id, $_SESSION['user_id']);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute delete query: ' . $stmt->error);
    }

    if ($stmt->affected_rows > 0) {
        debug_log("Successfully removed user from room");
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('No rows were deleted');
    }

} catch (Exception $e) {
    debug_log("Error occurred: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug_info' => [
            'user_id' => $_SESSION['user_id'] ?? null,
            'room_id' => $room_id ?? null,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
