<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    if (!isset($_FILES['avatar'])) {
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['avatar'];
    $user_id = $_SESSION['user_id'];

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }

    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File size too large. Maximum size is 5MB');
    }

    // Check file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Invalid file type. Only JPEG, PNG and GIF are allowed');
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/avatars';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $user_id . '_' . uniqid() . '.' . $extension;
    $target_path = $upload_dir . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        throw new Exception('Failed to save file');
    }

    // Update user's avatar in database
    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->bind_param("si", $filename, $user_id);
    
    if (!$stmt->execute()) {
        // If database update fails, delete the uploaded file
        unlink($target_path);
        throw new Exception('Failed to update avatar in database');
    }

    // Delete old avatar if it exists and is not the default
    $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $old_avatar = $result->fetch_assoc()['avatar'];
    
    if ($old_avatar && $old_avatar !== 'default.png' && $old_avatar !== $filename) {
        $old_avatar_path = $upload_dir . '/' . $old_avatar;
        if (file_exists($old_avatar_path)) {
            unlink($old_avatar_path);
        }
    }

    echo json_encode([
        'success' => true,
        'avatar' => $filename
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
