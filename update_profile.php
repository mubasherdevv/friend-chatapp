<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function for debugging
function logError($message) {
    $logFile = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    error_log($logMessage, 3, $logFile);
}

// Log all POST data
logError("POST data received: " . print_r($_POST, true));
if (isset($_FILES['avatar'])) {
    logError("File upload data: " . print_r($_FILES['avatar'], true));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    logError("No user session found");
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
logError("Processing update for user ID: " . $user_id);

try {
    // Verify database connection
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Get form data
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $bio = trim($_POST['bio'] ?? null); // Allow null for bio
    $date_of_birth = trim($_POST['date_of_birth'] ?? null); // Allow null for date_of_birth

    // Log received data
    logError("Received data - Username: $username, Email: $email, Bio length: " . 
            (is_null($bio) ? 'null' : strlen($bio)) . ", DOB: " . 
            (is_null($date_of_birth) ? 'null' : $date_of_birth));

    // Validate required fields
    if (empty($username) || empty($email)) {
        logError("Missing required fields - Username: " . (empty($username) ? 'missing' : 'present') . 
                ", Email: " . (empty($email) ? 'missing' : 'present'));
        echo json_encode(['success' => false, 'error' => 'Username and email are required']);
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        logError("Invalid email format: $email");
        echo json_encode(['success' => false, 'error' => 'Invalid email format']);
        exit;
    }

    // Handle avatar upload if provided
    $avatar_path = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['avatar']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            logError("Invalid file type: $file_type");
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG and GIF are allowed']);
            exit;
        }

        $max_size = 5 * 1024 * 1024; // 5MB
        if ($_FILES['avatar']['size'] > $max_size) {
            logError("File too large: " . $_FILES['avatar']['size'] . " bytes");
            echo json_encode(['success' => false, 'error' => 'File is too large. Maximum size is 5MB']);
            exit;
        }

        $upload_dir = __DIR__ . '/uploads/avatars/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                throw new Exception("Failed to create upload directory: $upload_dir");
            }
        }

        if (!is_writable($upload_dir)) {
            throw new Exception("Upload directory is not writable: $upload_dir");
        }

        $file_extension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $avatar_filename = $user_id . '_' . time() . '.' . $file_extension;
        $avatar_full_path = $upload_dir . $avatar_filename;

        if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $avatar_full_path)) {
            logError("Failed to move uploaded file from {$_FILES['avatar']['tmp_name']} to {$avatar_full_path}");
            throw new Exception("Failed to upload avatar");
        }

        $avatar_path = 'uploads/avatars/' . $avatar_filename;
        logError("Avatar uploaded successfully: $avatar_path");

        // Delete old avatar if it exists and is not the default
        $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $old_avatar = $result->fetch_assoc()['avatar'];
        
        if ($old_avatar && $old_avatar !== 'default.png' && file_exists(__DIR__ . '/' . $old_avatar)) {
            unlink(__DIR__ . '/' . $old_avatar);
        }
    }

    // Build the update query dynamically
    $query = "UPDATE users SET username = ?, email = ?";
    $types = "ss";
    $params = [$username, $email];

    if (!is_null($bio)) {
        $query .= ", bio = ?";
        $types .= "s";
        $params[] = $bio;
    }

    if (!is_null($date_of_birth)) {
        $query .= ", date_of_birth = ?";
        $types .= "s";
        $params[] = $date_of_birth;
    }

    if ($avatar_path) {
        $query .= ", avatar = ?";
        $types .= "s";
        $params[] = $avatar_path;
    }

    $query .= " WHERE id = ?";
    $types .= "i";
    $params[] = $user_id;

    logError("Executing query: $query");
    logError("Param types: $types");
    logError("Params: " . print_r($params, true));

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare update query: " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute update: " . $stmt->error);
    }

    // Check if the update was successful
    if ($stmt->affected_rows === 0) {
        logError("No rows were updated for user ID: $user_id - This might be normal if no changes were made");
    } else {
        logError("Successfully updated profile for user ID: $user_id");
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);

} catch (Exception $e) {
    logError("Profile update error: " . $e->getMessage());
    logError("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while updating profile',
        'debug_message' => $e->getMessage()
    ]);
}
