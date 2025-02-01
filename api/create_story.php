<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$story_type = $_POST['story_type'] ?? 'text';
$visibility = $_POST['visibility'] ?? 'public';
$background_color = $_POST['background_color'] ?? '#000000';

// Set story expiration time (24 hours from now)
$expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

// Debug received data
error_log("Story Type: " . $story_type);
error_log("Visibility: " . $visibility);
error_log("Background Color: " . $background_color);

$content = '';

// Handle text stories
if ($story_type === 'text') {
    $content = $_POST['text_content'] ?? '';
    
    if (empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Story text cannot be empty']);
        exit;
    }
} 
// Handle media stories (image/video)
else {
    if (!isset($_FILES['media_file']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = isset($_FILES['media_file']) ? 
            'Upload error: ' . $_FILES['media_file']['error'] : 
            'No file uploaded';
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit;
    }

    $allowed_types = [
        'image' => ['image/jpeg', 'image/png', 'image/gif'],
        'video' => ['video/mp4', 'video/webm']
    ];

    $file = $_FILES['media_file'];
    $file_type = $file['type'];
    
    error_log("File Type: " . $file_type);
    
    // Verify file type matches story type
    if ($story_type === 'image' && !in_array($file_type, $allowed_types['image'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid image file type. Allowed types: JPG, PNG, GIF']);
        exit;
    }
    if ($story_type === 'video' && !in_array($file_type, $allowed_types['video'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid video file type. Allowed types: MP4, WebM']);
        exit;
    }

    // Create upload directory if it doesn't exist
    $upload_dir = "../uploads/stories/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
        exit;
    }

    $content = $filename;
}

// Debug final data before insert
error_log("Content: " . $content);
error_log("User ID: " . $user_id);

// Create story
$stmt = $conn->prepare("
    INSERT INTO stories (user_id, story_type, content, background_color, visibility, expires_at)
    VALUES (?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("isssss", $user_id, $story_type, $content, $background_color, $visibility, $expires_at);

if ($stmt->execute()) {
    $story_id = $conn->insert_id;
    
    echo json_encode([
        'success' => true,
        'message' => 'Story created successfully',
        'story' => [
            'id' => $story_id,
            'type' => $story_type,
            'content' => $content,
            'background_color' => $background_color,
            'visibility' => $visibility,
            'expires_at' => $expires_at
        ]
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to create story: ' . $stmt->error
    ]);
}
