<?php
// Prevent any output before headers
ob_start();

// Set JSON content type immediately
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start session and include required files
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Create a log function
function logError($message, $context = []) {
    $logFile = __DIR__ . '/../logs/friends_api.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';
    $logMessage = "[$timestamp] $message $contextStr\n";
    error_log($logMessage, 3, $logFile);
}

// Function to ensure we're sending a JSON response
function sendResponse($success, $message = '', $error = '') {
    // Clear any output that might have been sent
    ob_clean();
    
    if (!$success) {
        if (empty($error)) {
            $error = 'An unknown error occurred';
        }
        logError($error);
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'error' => $error
    ]);
    exit;
}

// Check if user is logged in
try {
    requireLogin();
} catch (Exception $e) {
    sendResponse(false, '', 'Authentication required');
}

// Get user ID from session
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    sendResponse(false, '', 'User ID not found in session');
}

// Function to validate friendship
function validateFriendship($conn, $user_id, $friend_id) {
    // Log validation attempt
    logError("Validating friendship", [
        'user_id' => $user_id,
        'friend_id' => $friend_id
    ]);

    // Validate input
    if (!is_numeric($user_id) || !is_numeric($friend_id)) {
        return "Invalid user ID format";
    }

    // Check if trying to friend self
    if ($user_id == $friend_id) {
        return "You cannot add yourself as a friend";
    }

    // Check if users exist
    $stmt = $conn->prepare("SELECT id FROM users WHERE id IN (?, ?)");
    $stmt->bind_param("ii", $user_id, $friend_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 2) {
        logError("User validation failed", [
            'user_id' => $user_id,
            'friend_id' => $friend_id,
            'found_rows' => $result->num_rows
        ]);
        return "One or both users do not exist";
    }

    // Check if already friends or blocked
    $stmt = $conn->prepare("
        SELECT status 
        FROM friends 
        WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
    ");
    $stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        logError("Existing friendship found", [
            'user_id' => $user_id,
            'friend_id' => $friend_id,
            'status' => $row['status']
        ]);
        
        switch ($row['status']) {
            case 'blocked':
                return "This user is blocked";
            case 'accepted':
                return "Already friends with this user";
            case 'pending':
                return "Friend request already pending";
        }
    }

    return "";
}

// Log all POST data
logError("Received request", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'post_data' => $_POST,
    'user_id' => $user_id
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, '', 'Method not allowed');
}

$action = $_POST['action'] ?? '';

if (empty($action)) {
    sendResponse(false, '', 'Action is required');
}

try {
    $conn->begin_transaction();

    switch ($action) {
        case 'send_request_by_username':
            $username = trim($_POST['username'] ?? '');
            
            if (empty($username)) {
                sendResponse(false, '', 'Username cannot be empty');
            }
            
            if (strlen($username) < 3) {
                sendResponse(false, '', 'Username must be at least 3 characters long');
            }
            
            if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
                sendResponse(false, '', 'Username can only contain letters, numbers, and underscores');
            }
            
            logError("Looking up user by username", ['username' => $username]);
            
            // Get user ID from username
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare username lookup query: " . $conn->error);
            }
            
            $stmt->bind_param("s", $username);
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute username lookup query: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                sendResponse(false, '', "User '$username' not found");
            }
            
            $friend_id = $result->fetch_assoc()['id'];
            
            // Validate friendship
            if ($error = validateFriendship($conn, $user_id, $friend_id)) {
                sendResponse(false, '', $error);
            }
            
            // Send friend request
            $stmt = $conn->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')");
            if (!$stmt) {
                throw new Exception("Failed to prepare friend request query: " . $conn->error);
            }
            
            $stmt->bind_param("ii", $user_id, $friend_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute friend request query: " . $stmt->error);
            }
            
            $conn->commit();
            sendResponse(true, "Friend request sent to $username");
            break;
            
        case 'accept':
            $friend_id = filter_input(INPUT_POST, 'friend_id', FILTER_VALIDATE_INT);
            if ($friend_id === false || $friend_id === null) {
                sendResponse(false, '', 'Invalid friend ID');
            }
            
            $stmt = $conn->prepare("
                UPDATE friends 
                SET status = 'accepted' 
                WHERE user_id = ? AND friend_id = ? AND status = 'pending'
            ");
            
            if (!$stmt) {
                throw new Exception("Failed to prepare accept request query: " . $conn->error);
            }
            
            $stmt->bind_param("ii", $friend_id, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute accept request query: " . $stmt->error);
            }
            
            if ($stmt->affected_rows === 0) {
                sendResponse(false, '', 'Friend request not found or already accepted');
            }
            
            $conn->commit();
            sendResponse(true, 'Friend request accepted');
            break;
            
        case 'reject':
            $friend_id = filter_input(INPUT_POST, 'friend_id', FILTER_VALIDATE_INT);
            if ($friend_id === false || $friend_id === null) {
                sendResponse(false, '', 'Invalid friend ID');
            }
            
            $stmt = $conn->prepare("
                DELETE FROM friends 
                WHERE user_id = ? AND friend_id = ? AND status = 'pending'
            ");
            
            if (!$stmt) {
                throw new Exception("Failed to prepare reject request query: " . $conn->error);
            }
            
            $stmt->bind_param("ii", $friend_id, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute reject request query: " . $stmt->error);
            }
            
            $conn->commit();
            sendResponse(true, 'Friend request rejected');
            break;
            
        default:
            sendResponse(false, '', 'Invalid action');
    }
} catch (Exception $e) {
    $conn->rollback();
    logError("Exception caught", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    sendResponse(false, '', $e->getMessage());
}
