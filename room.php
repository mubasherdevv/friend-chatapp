<?php
// Start session and include required files
require_once 'config/database.php';
require_once 'includes/functions.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$room_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get room data including settings
$stmt = $conn->prepare("SELECT r.*, rm.admin_level 
                       FROM rooms r 
                       LEFT JOIN room_members rm ON r.id = rm.room_id AND rm.user_id = ? 
                       WHERE r.id = ?");
$stmt->bind_param("ii", $user_id, $room_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();

if (!$room) {
    header('Location: index.php');
    exit;
}

// Load existing messages
$stmt = $conn->prepare("SELECT m.*, u.username, u.avatar, DATE_FORMAT(m.created_at, '%H:%i') as formatted_time 
                       FROM messages m 
                       JOIN users u ON m.user_id = u.id 
                       WHERE m.room_id = ? 
                       ORDER BY m.created_at ASC");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Add is_own flag to messages
foreach ($messages as &$message) {
    $message['is_own'] = $message['user_id'] == $user_id;
}

// Decode JSON settings
$privacy_settings = json_decode($room['privacy_settings'] ?? '{}', true) ?: [];
$chat_settings = json_decode($room['chat_settings'] ?? '{}', true) ?: [];
$notification_settings = json_decode($room['notification_settings'] ?? '{}', true) ?: [];

// Check privacy settings
$is_member = isset($room['admin_level']);
$is_private = !empty($privacy_settings['is_private']);
$members_only_chat = !empty($privacy_settings['members_only_chat']);

if ($is_private && !$is_member) {
    header('Location: index.php?error=private_room');
    exit;
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear any previous output and set JSON header
    ob_clean();
    header('Content-Type: application/json');

    // Check and fix primary key setup
    $result = $conn->query("SHOW COLUMNS FROM messages WHERE Field = 'id'");
    if ($result->num_rows > 0) {
        $column = $result->fetch_assoc();
        if (strpos(strtoupper($column['Extra']), 'AUTO_INCREMENT') === false) {
            // Drop the primary key if it exists
            $conn->query("ALTER TABLE messages DROP PRIMARY KEY");
            // Modify the id column to be auto-increment
            $conn->query("ALTER TABLE messages MODIFY id INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
        }
    } else {
        // If id column doesn't exist, create it
        $conn->query("ALTER TABLE messages ADD id INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
    }

    // Check if type column exists in messages table
    $result = $conn->query("SHOW COLUMNS FROM messages LIKE 'type'");
    if ($result->num_rows === 0) {
        // Add type column if it doesn't exist
        $conn->query("ALTER TABLE messages ADD COLUMN type VARCHAR(50) DEFAULT 'text'");
    }

    try {
        // Create uploads directory if it doesn't exist
        $upload_dir = __DIR__ . '/uploads';
        $media_dir = $upload_dir . '/media';
        if (!file_exists($media_dir)) {
            mkdir($media_dir, 0777, true);
        }

        // Handle different message types
        $message_type = $_POST['type'] ?? 'text';
        $content = '';

        switch ($message_type) {
            case 'image':
                if (!isset($_FILES['image'])) {
                    throw new Exception('No image file uploaded');
                }

                $file = $_FILES['image'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Image upload failed: ' . $file['error']);
                }

                // Validate image file
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($file['type'], $allowed_types)) {
                    throw new Exception('Invalid image type. Allowed types: JPG, PNG, GIF');
                }

                // Check file size (5MB limit)
                if ($file['size'] > 5 * 1024 * 1024) {
                    throw new Exception('Image file too large. Maximum size: 5MB');
                }

                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid('img_') . '.' . $extension;
                $filepath = $media_dir . '/' . $filename;

                if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                    throw new Exception('Failed to save image file');
                }

                $content = 'uploads/media/' . $filename;
                break;

            case 'voice':
                if (!isset($_FILES['voice'])) {
                    throw new Exception('No voice file uploaded');
                }

                $file = $_FILES['voice'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Voice upload failed: ' . $file['error']);
                }

                // Generate unique filename
                $filename = uniqid('voice_') . '.wav';
                $filepath = $media_dir . '/' . $filename;

                if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                    throw new Exception('Failed to save voice file');
                }

                $content = 'uploads/media/' . $filename;
                break;

            default: // text message
                if (!isset($_POST['message'])) {
                    throw new Exception('No message content provided');
                }
                $content = trim($_POST['message']);
                if (empty($content)) {
                    throw new Exception('Message cannot be empty');
                }
                break;
        }

        // Insert message into database
        $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id, content, type, created_at) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $stmt->bind_param("iiss", $room_id, $user_id, $content, $message_type);
        if (!$stmt->execute()) {
            throw new Exception("Failed to send message: " . $stmt->error);
        }

        $message_id = $stmt->insert_id;

        // Get the created message with timestamp
        $stmt = $conn->prepare("SELECT m.*, u.username, u.avatar, DATE_FORMAT(m.created_at, '%H:%i') as formatted_time 
                              FROM messages m 
                              JOIN users u ON m.user_id = u.id 
                              WHERE m.id = ?");
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $stmt->bind_param("i", $message_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to fetch message: " . $stmt->error);
        }

        $message = $stmt->get_result()->fetch_assoc();

        echo json_encode([
            'status' => 'success',
            'message' => [
                'id' => $message_id,
                'content' => $content,
                'type' => $message_type,
                'timestamp' => $message['formatted_time'],
                'username' => $message['username'],
                'avatar' => $message['avatar'],
                'is_own' => true
            ]
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// Check and add admin_level column to room_members if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM room_members LIKE 'admin_level'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE room_members ADD COLUMN admin_level INT DEFAULT 0");
    // Set admin_level = 2 for room creators (highest level)
    $conn->query("UPDATE room_members SET admin_level = 2 WHERE user_id IN (SELECT admin_id FROM rooms WHERE id = room_members.room_id)");
}

update_user_activity($conn, $user_id);

// Get room members
$stmt = $conn->prepare("SELECT u.id, u.username, u.avatar, u.is_admin,
    CASE 
        WHEN u.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 
        ELSE 0 
    END as is_online, 
    u.last_seen as last_active,
    CASE 
        WHEN r.admin_id = u.id THEN 2  -- Room Creator (Super Admin)
        WHEN u.is_admin = 1 THEN 1     -- Regular Admin
        ELSE 0                         -- Regular User
    END as admin_level
    FROM room_members rm 
    JOIN users u ON rm.user_id = u.id 
    JOIN rooms r ON rm.room_id = r.id
    WHERE rm.room_id = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent messages
$stmt = $conn->prepare("SELECT m.*, 'text' as type, u.username, u.avatar, 
    DATE_FORMAT(m.created_at, '%H:%i') as formatted_time 
    FROM messages m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.room_id = ? 
    ORDER BY m.created_at DESC 
    LIMIT 50");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$recent_messages = array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));

include 'includes/header.php';
?>

<style>
    :root {
        --safe-area-inset-bottom: env(safe-area-inset-bottom, 0px);
    }

    /* Mobile viewport height fix */
    @supports (-webkit-touch-callout: none) {
        .min-h-screen {
            min-height: -webkit-fill-available;
        }
    }

    /* Ensure content is visible on mobile */
    @media (max-width: 768px) {
        #messages-container {
            height: calc(100vh - 16rem - var(--safe-area-inset-bottom));
        }
        
        .input-container {
            padding-bottom: calc(1rem + var(--safe-area-inset-bottom));
        }
    }

    /* Custom scrollbar for messages */
    #messages-container {
        scrollbar-width: thin;
        scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
    }

    #messages-container::-webkit-scrollbar {
        width: 6px;
    }

    #messages-container::-webkit-scrollbar-track {
        background: transparent;
    }

    #messages-container::-webkit-scrollbar-thumb {
        background-color: rgba(156, 163, 175, 0.5);
        border-radius: 3px;
    }

    /* Message bubbles */
    .message {
        max-width: 85%;
        word-break: break-word;
    }

    @media (max-width: 640px) {
        .message {
            max-width: 90%;
        }
    }

    /* Theme styles */
    .theme-default {
        --bg-gradient: linear-gradient(120deg, #f0f2f5, #e5e7eb);
        --message-sent: linear-gradient(135deg, #4299e1, #3182ce);
        --message-received: rgba(255, 255, 255, 0.9);
    }
    
    .theme-dark {
        --bg-gradient: linear-gradient(120deg, #1a202c, #2d3748);
        --message-sent: linear-gradient(135deg, #4299e1, #2b6cb0);
        --message-received: rgba(45, 55, 72, 0.9);
    }
    
    .theme-light {
        --bg-gradient: linear-gradient(120deg, #ffffff, #f7fafc);
        --message-sent: linear-gradient(135deg, #4299e1, #3182ce);
        --message-received: rgba(255, 255, 255, 0.95);
    }
    
    .theme-blue {
        --bg-gradient: linear-gradient(120deg, #ebf8ff, #bee3f8);
        --message-sent: linear-gradient(135deg, #2b6cb0, #2c5282);
        --message-received: rgba(255, 255, 255, 0.9);
    }
    
    .theme-green {
        --bg-gradient: linear-gradient(120deg, #f0fff4, #c6f6d5);
        --message-sent: linear-gradient(135deg, #38a169, #2f855a);
        --message-received: rgba(255, 255, 255, 0.9);
    }

    .messages-bg {
        background: var(--bg-gradient);
        position: relative;
        overflow-y: auto;
        overflow-x: hidden;
        height: calc(100vh - 13rem);
    }

    .message-bubble.sent {
        background: var(--message-sent);
        border: none !important;
    }

    .message-bubble.received {
        background: var(--message-received);
        border: 1px solid rgba(226, 232, 240, 0.8) !important;
    }

    .custom-wallpaper {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 0.15;
        z-index: 0;
    }
    
    /* Mobile Specific Styles */
    @media (max-width: 768px) {
        .container {
            padding: 0 !important;
        }
        
        .h-[calc(100vh-8rem)] {
            height: 100vh !important;
        }
        
        #membersSidebar {
            width: 85% !important;
            max-width: 320px;
        }
        
        .message-bubble {
            max-width: 85% !important;
        }
        
        #messages-container {
            padding: 1rem !important;
        }
        
        .message-container {
            margin-bottom: 0.75rem !important;
        }
        
        /* Hide some elements on mobile */
        .hidden-mobile {
            display: none !important;
        }
        
        /* Adjust header for mobile */
        .room-header {
            padding: 0.75rem 1rem !important;
        }
        
        /* Make input area stick to bottom */
        .input-container {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 0.75rem 1rem !important;
            border-top: 1px solid #e5e7eb;
        }
        
        /* Improve touch targets */
        button {
            min-height: 44px;
            min-width: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        input[type="text"] {
            font-size: 16px !important; /* Prevent zoom on iOS */
            padding: 0.75rem !important;
        }
        
        /* Improve sidebar overlay */
        #mobileOverlay {
            backdrop-filter: blur(4px);
        }
        
        /* Smooth sidebar animation */
        #membersSidebar {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Adjust message spacing */
        .space-y-4 > * + * {
            margin-top: 0.75rem !important;
        }
    }
</style>

<style>
    :root {
        --safe-area-inset-bottom: env(safe-area-inset-bottom, 0px);
    }
</style>
<link rel="stylesheet" href="/customization/css/styles.css">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Room</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<div class="container mx-auto px-4 py-4">
    <div class="flex flex-col lg:flex-row gap-4 min-h-[calc(100vh-8rem)] max-h-[calc(100vh-8rem)]">
        
        <!-- Chat Area -->
        <div class="flex-grow bg-white rounded-lg shadow-lg flex flex-col relative z-0 overflow-hidden">
            <div class="room-header p-4 border-b flex justify-between items-center bg-white sticky top-0 z-10">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-semibold truncate"><?php echo htmlspecialchars($room['name'] ?? 'Chat Room'); ?></h2>
                    <span class="text-sm text-gray-500"><?php echo count($members); ?> members</span>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($is_member): ?>
                        <button onclick="location.href='room_settings.php?id=<?php echo $room_id; ?>'" 
                                class="text-gray-600 hover:text-gray-800">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </button>
                        
                        <?php if ($is_private): ?>
                            <button onclick="location.href='invite_member.php?room_id=<?php echo $room_id; ?>'" 
                                    class="text-gray-600 hover:text-gray-800" title="Invite Members">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 0112 0v1H3v-1z"></path>
                                </svg>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <button onclick="toggleMembersSidebar()" class="text-gray-600 hover:text-gray-800">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Room Rules Banner (if exists) -->
            <?php if (!empty($room['rules'])): ?>
            <div class="bg-blue-50 p-4 border-b">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div class="flex-1">
                        <h3 class="font-semibold text-blue-800 mb-1">Room Rules</h3>
                        <p class="text-sm text-blue-600"><?php echo nl2br(htmlspecialchars($room['rules'])); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Messages Container with theme and wallpaper support -->
            <div id="messages-container" class="messages-bg p-4 space-y-4 theme-<?php echo htmlspecialchars($room['theme'] ?? 'default'); ?> flex-grow overflow-y-auto">
                <?php if (!empty($room['wallpaper'])): ?>
                <img src="uploads/wallpapers/<?php echo htmlspecialchars($room['wallpaper']); ?>" 
                     alt="Room wallpaper" class="custom-wallpaper">
                <?php endif; ?>
                
                <!-- Messages will be loaded here -->
                <div id="messages-container">
                    <div class="space-y-4">
                        <?php foreach ($messages as $message): ?>
                            <div class="message <?php echo $message['is_own'] ? 'flex justify-end' : 'flex'; ?>"
                                 data-message-id="<?php echo $message['id']; ?>">
                                <div class="message-bubble <?php echo $message['is_own'] ? 'bg-blue-500 text-white' : 'bg-gray-100'; ?> rounded-lg p-3 max-w-[70%]" data-message-id="<?php echo $message['id']; ?>">
                                    <?php if (!$message['is_own']): ?>
                                        <div class="flex items-center gap-2 mb-1">
                                            <?php if (!empty($message['avatar'])): ?>
                                                <img src="<?php echo htmlspecialchars($message['avatar']); ?>" 
                                                     alt="User avatar" 
                                                     class="w-6 h-6 rounded-full">
                                            <?php endif; ?>
                                            <div class="text-sm font-semibold"><?php echo htmlspecialchars($message['username']); ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($message['type'] === 'voice'): ?>
                                        <audio src="<?php echo htmlspecialchars($message['content']); ?>" controls class="max-w-[240px]"></audio>
                                    <?php else: ?>
                                        <div class="message-text break-words"><?php echo htmlspecialchars($message['content']); ?></div>
                                    <?php endif; ?>

                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-xs opacity-75"><?php echo $message['formatted_time']; ?></span>
                                        <div class="flex items-center gap-2">
                                            <?php if ($message['is_own']): ?>
                                                <div class="message-actions relative">
                                                    <button onclick="toggleMessageActions(<?php echo $message['id']; ?>)" 
                                                            class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div id="message-actions-<?php echo $message['id']; ?>" 
                                                         class="hidden absolute right-0 bottom-6 bg-white dark:bg-gray-800 rounded shadow-lg py-1 min-w-[120px] z-[1000]">
                                                        <button onclick="editMessage(<?php echo $message['id']; ?>, '<?php echo htmlspecialchars(addslashes($message['content'])); ?>')" 
                                                                class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                                            <i class="fas fa-edit mr-2"></i> Edit
                                                        </button>
                                                        <button onclick="deleteMessage(<?php echo $message['id']; ?>)" 
                                                                class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                                                            <i class="fas fa-trash-alt mr-2"></i> Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <div class="read-receipts text-xs ml-2 flex items-center gap-1"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Typing indicator -->
                <div id="typing-indicator" class="text-sm text-gray-500 italic hidden"></div>
            </div>
            
            <!-- Message Input Area -->
            <div class="p-4 border-t bg-white sticky bottom-0">
                <form id="message-form" class="flex gap-3 items-center" onsubmit="event.preventDefault(); sendMessage();">
                    <!-- Voice Recording Button -->
                    <button type="button" id="voiceButton"
                            class="p-2 text-gray-500 hover:text-blue-500 transition-colors"
                            onclick="toggleVoiceRecording()">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 0112 0v1H3v-1z"></path>
                        </svg>
                    </button>

                    <!-- Image Upload Button -->
                    <label class="p-2 text-gray-500 hover:text-blue-500 transition-colors cursor-pointer">
                        <input type="file" id="imageInput" accept="image/*" class="hidden" onchange="handleImageUpload(event)">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </label>

                    <!-- Recording Status -->
                    <div id="recordingStatus" class="hidden items-center gap-2 text-red-500">
                        <span class="animate-pulse">●</span>
                        <span>Recording...</span>
                    </div>

                    <input type="text" id="messageInput" 
                           class="flex-grow rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" 
                           placeholder="Type your message...">
                    <button type="submit" 
                            class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors flex items-center gap-2 font-medium">
                        <span>Send</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Members Sidebar -->
        <div id="membersSidebar" class="lg:w-80 bg-white rounded-lg shadow-lg h-full overflow-hidden flex flex-col fixed lg:relative right-0 top-0 lg:top-auto w-[85vw] sm:w-[300px] lg:w-auto lg:translate-x-0 translate-x-full transition-transform duration-300 ease-in-out z-50">
            <!-- Close button for mobile -->
            <button onclick="toggleMembersSidebar()" 
                    class="lg:hidden absolute top-4 right-4 text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            <!-- Online Users Section -->
            <div class="border-b">
                <div class="p-4 bg-gray-50 border-b">
                    <h3 class="font-semibold flex items-center gap-2 text-gray-700">
                        <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                        Online Users
                    </h3>
                </div>
                <div class="p-4 space-y-3">
                    <?php foreach ($members as $member): 
                        if ($member['is_online'] == 1):
                    ?>
                        <div class="flex items-center gap-2">
                            <a href="profile.php?id=<?php echo $member['id']; ?>" 
                               class="block hover:opacity-90 transition-opacity">
                                <img src="<?php echo $member['avatar'] ? 'uploads/avatars/' . $member['avatar'] : 'images/default-avatar.png'; ?>" 
                                     class="w-8 h-8 rounded-full" 
                                     alt="<?php echo htmlspecialchars($member['username']); ?>'s Avatar">
                            </a>
                            <div>
                                <div class="font-medium">
                                    <a href="profile.php?id=<?php echo $member['id']; ?>" 
                                       class="hover:text-blue-600 transition-colors">
                                        <?php echo htmlspecialchars($member['username']); ?>
                                    </a>
                                </div>
                                <div class="text-xs text-green-500">Online</div>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>

            <!-- Offline Users Section -->
            <div class="border-b">
                <div class="p-4 bg-gray-50 border-b">
                    <h3 class="font-semibold flex items-center gap-2 text-gray-700">
                        <span class="w-3 h-3 bg-gray-300 rounded-full"></span>
                        Offline Users
                    </h3>
                </div>
                <div class="p-4 space-y-3">
                    <?php foreach ($members as $member): 
                        if ($member['is_online'] == 0):
                    ?>
                        <div class="flex items-center gap-2">
                            <a href="profile.php?id=<?php echo $member['id']; ?>" 
                               class="block hover:opacity-90 transition-opacity">
                                <img src="<?php echo $member['avatar'] ? 'uploads/avatars/' . $member['avatar'] : 'images/default-avatar.png'; ?>" 
                                     class="w-8 h-8 rounded-full" 
                                     alt="<?php echo htmlspecialchars($member['username']); ?>'s Avatar">
                            </a>
                            <div>
                                <div class="font-medium">
                                    <a href="profile.php?id=<?php echo $member['id']; ?>" 
                                       class="hover:text-blue-600 transition-colors">
                                        <?php echo htmlspecialchars($member['username']); ?>
                                    </a>
                                </div>
                                <div class="text-xs text-gray-500">
                                    Last seen <?php echo time_elapsed_string($member['last_active']); ?>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>

            <!-- Admins Section -->
            <div>
                <div class="p-4 bg-gray-50">
                    <h3 class="font-semibold flex items-center gap-2 text-gray-700">
                        <span class="w-3 h-3 bg-blue-500 rounded-full"></span>
                        Admins
                    </h3>
                </div>
                <div class="p-4 space-y-3">
                    <?php 
                    // First show room creator (super admin)
                    foreach ($members as $member): 
                        if ($member['admin_level'] == 2):
                    ?>
                        <div class="flex items-center gap-2">
                            <a href="profile.php?id=<?php echo $member['id']; ?>" 
                               class="block hover:opacity-90 transition-opacity">
                                <img src="<?php echo $member['avatar'] ? 'uploads/avatars/' . $member['avatar'] : 'images/default-avatar.png'; ?>" 
                                     class="w-8 h-8 rounded-full" 
                                     alt="<?php echo htmlspecialchars($member['username']); ?>'s Avatar">
                            </a>
                            <div>
                                <div class="font-medium flex items-center gap-1">
                                    <?php echo htmlspecialchars($member['username']); ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                        Room Creator
                                    </span>
                                    <svg class="w-4 h-4 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                    </svg>
                                </div>
                                <div class="text-xs <?php echo $member['is_online'] ? 'text-green-500' : 'text-gray-500'; ?>">
                                    <?php echo $member['is_online'] ? 'Online' : 'Last seen ' . time_elapsed_string($member['last_active']); ?>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 

                    // Then show regular admins
                    foreach ($members as $member): 
                        if ($member['admin_level'] == 1):
                    ?>
                        <div class="flex items-center gap-2">
                            <a href="profile.php?id=<?php echo $member['id']; ?>" 
                               class="block hover:opacity-90 transition-opacity">
                                <img src="<?php echo $member['avatar'] ? 'uploads/avatars/' . $member['avatar'] : 'images/default-avatar.png'; ?>" 
                                     class="w-8 h-8 rounded-full" 
                                     alt="<?php echo htmlspecialchars($member['username']); ?>'s Avatar">
                            </a>
                            <div>
                                <div class="font-medium flex items-center gap-1">
                                    <?php echo htmlspecialchars($member['username']); ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        Admin
                                    </span>
                                    <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                    </svg>
                                </div>
                                <div class="text-xs <?php echo $member['is_online'] ? 'text-green-500' : 'text-gray-500'; ?>">
                                    <?php echo $member['is_online'] ? 'Online' : 'Last seen ' . time_elapsed_string($member['last_active']); ?>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Mobile overlay -->
        <div id="mobileOverlay" 
             class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden"
             onclick="toggleMembersSidebar()">
        </div>
    </div>
</div>

<!-- Profile Modal -->
<div id="profileModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-[60]">
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-md w-full mx-4 relative dark:bg-gray-800">
        <button onclick="closeProfileModal()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
        <div id="profileContent" class="text-center">
            <!-- Profile content will be loaded here -->
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/socket.io/4.7.2/socket.io.js"></script>

<script>
    // Initialize Socket.IO connection first
    const socket = io('http://localhost:3000', {
        query: {
            userId: '<?php echo $user_id; ?>',
            roomId: '<?php echo $room_id; ?>'
        }
    });

    // Initialize chat settings from PHP
    const chatSettings = <?php echo json_encode($chat_settings ?? []); ?>;
    const privacySettings = <?php echo json_encode($privacy_settings ?? []); ?>;
    const notificationSettings = <?php echo json_encode($notification_settings ?? []); ?>;
    
    // Voice recording variables
    let mediaRecorder = null;
    let recordedBlob = null;
    let audioChunks = [];
    let isRecording = false;

    // Listen for new messages
    socket.on('new_message', function(data) {
        if (data.userId != <?php echo $user_id; ?>) {
            appendMessage({
                id: data.messageId,
                content: data.content,
                type: data.type,
                timestamp: data.timestamp,
                is_own: false,
                username: data.username,
                avatar: data.avatar
            });
            
            // Scroll to bottom if user is near bottom
            const messagesContainer = document.getElementById('messages-container');
            if (messagesContainer.scrollHeight - messagesContainer.scrollTop <= messagesContainer.clientHeight + 100) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }
    });

    // Message sending function
    async function sendMessage() {
        const messageInput = document.getElementById('messageInput');
        const message = messageInput.value.trim();
        const form = document.getElementById('message-form');
        const formData = new FormData(form);

        // Check if there's any content to send
        if (!message && !recordedBlob) {
            return;
        }

        // Check message length restrictions if configured
        if (message) {
            const maxLength = chatSettings.max_message_length || 1000; // Default to 1000 if not set
            if (message.length > maxLength) {
                alert(`Message too long. Maximum length is ${maxLength} characters.`);
                return;
            }
        }

        try {
            if (recordedBlob) {
                formData.append('type', 'voice');
                formData.append('voice', recordedBlob, 'voice.wav');
            } else {
                formData.append('type', 'text');
                formData.append('message', message);
            }

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            if (result.status === 'success') {
                // Emit socket event for real-time update
                socket.emit('send_message', {
                    roomId: '<?php echo $room_id; ?>',
                    userId: '<?php echo $user_id; ?>',
                    username: '<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>',
                    avatar: '<?php echo htmlspecialchars($_SESSION['avatar'] ?? ''); ?>',
                    messageId: result.message.id,
                    content: result.message.content,
                    type: result.message.type,
                    timestamp: result.message.timestamp
                });

                messageInput.value = '';
                if (recordedBlob) {
                    resetVoiceRecording();
                }

                appendMessage({
                    id: result.message.id,
                    content: result.message.content,
                    type: result.message.type,
                    timestamp: result.message.timestamp,
                    is_own: true,
                    username: '<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>',
                    avatar: '<?php echo htmlspecialchars($_SESSION['avatar'] ?? ''); ?>'
                });

                const messagesContainer = document.getElementById('messages-container');
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            } else {
                throw new Error(result.message || 'Failed to send message');
            }
        } catch (error) {
            console.error('Error sending message:', error);
            alert('Failed to send message. Please try again.');
        }
    }

    // Reset voice recording state
    function resetVoiceRecording() {
        recordedBlob = null;
        audioChunks = [];
        isRecording = false;
        const voiceButton = document.getElementById('voiceButton');
        if (voiceButton) {
            voiceButton.classList.remove('recording');
        }
    }

    // Initialize voice recording functionality
    async function initVoiceRecording() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            
            mediaRecorder.ondataavailable = (event) => {
                audioChunks.push(event.data);
            };
            
            mediaRecorder.onstop = () => {
                recordedBlob = new Blob(audioChunks, { type: 'audio/wav' });
                sendMessage();
            };
            
            return true;
        } catch (error) {
            console.error('Error initializing voice recording:', error);
            alert('Could not initialize voice recording. Please check your microphone permissions.');
            return false;
        }
    }

    // Handle voice recording button click
    document.getElementById('voiceButton').addEventListener('click', async function() {
        if (!mediaRecorder) {
            const initialized = await initVoiceRecording();
            if (!initialized) return;
        }
        
        if (!isRecording) {
            // Start recording
            audioChunks = [];
            mediaRecorder.start();
            isRecording = true;
            this.classList.add('recording');
        } else {
            // Stop recording
            mediaRecorder.stop();
            isRecording = false;
            this.classList.remove('recording');
        }
    });

    // Utility function to escape HTML special characters
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Function to format timestamps
    function formatTime(timestamp) {
        // If timestamp is already in HH:mm format, return as is
        if (/^\d{2}:\d{2}$/.test(timestamp)) {
            return timestamp;
        }
        
        // Otherwise, create a date object and format it
        const date = new Date(timestamp);
        return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
    }

    // Modified appendMessage function to handle different message types
    function appendMessage(message) {
        const messagesContainer = document.querySelector('#messages-container .space-y-4');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${message.is_own ? 'flex justify-end' : 'flex'}`;
        messageDiv.setAttribute('data-message-id', message.id);
        
        const avatarHtml = message.avatar && !message.is_own ? `
            <img src="${escapeHtml(message.avatar)}" 
                 alt="User avatar" 
                 class="w-6 h-6 rounded-full">
        ` : '';

        const content = `
            <div class="message-bubble ${message.is_own ? 'bg-blue-500 text-white' : 'bg-gray-100'} rounded-lg p-3 max-w-[70%]" data-message-id="${message.id}">
                ${!message.is_own ? `
                    <div class="flex items-center gap-2 mb-1">
                        ${avatarHtml}
                        <div class="text-sm font-semibold">${escapeHtml(message.username)}</div>
                    </div>
                ` : ''}
                
                ${message.type === 'voice' ? `
                    <audio src="${message.content}" controls class="max-w-[240px]"></audio>
                ` : `
                    <div class="message-text break-words">${escapeHtml(message.content)}</div>
                `}
                
                <div class="flex items-center justify-between mt-1">
                    <span class="text-xs opacity-75">${message.timestamp}</span>
                    <div class="flex items-center gap-2">
                        ${message.is_own ? `
                            <div class="message-actions relative">
                                <button onclick="toggleMessageActions(${message.id})" class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div id="message-actions-${message.id}" class="hidden absolute right-0 bottom-6 bg-white dark:bg-gray-800 rounded shadow-lg py-1 min-w-[120px] z-[1000]">
                                    <button onclick="editMessage(${message.id}, '${escapeHtml(message.content.replace(/'/g, "\\'"))}')" 
                                            class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-edit mr-2"></i> Edit
                                    </button>
                                    <button onclick="deleteMessage(${message.id})" 
                                            class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-trash-alt mr-2"></i> Delete
                                    </button>
                                </div>
                            </div>
                        ` : ''}
                        <div class="read-receipts text-xs ml-2 flex items-center gap-1"></div>
                    </div>
                </div>
            </div>
        `;
        
        messageDiv.innerHTML = content;
        messagesContainer.appendChild(messageDiv);
        
        // Mark message as read if it's not our own
        if (!message.is_own) {
            markMessageAsRead(message.id);
        }
        
        // Observe the new message for read receipts
        observeNewMessage(messageDiv);
        
        // Hide all message action menus when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.message-actions')) {
                const allMenus = document.querySelectorAll('.message-actions > div');
                allMenus.forEach(menu => menu.classList.add('hidden'));
            }
        });
        
        // Scroll to bottom
        const container = document.getElementById('messages-container');
        container.scrollTop = container.scrollHeight;
    }

    // Handle read receipts
    function markMessageAsRead(messageId) {
        socket.emit('message_read', {
            messageId: messageId,
            username: '<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>'
        });
    }

    // Listen for read receipts
    socket.on('message_read_by_user', function(data) {
        const messageElement = document.querySelector(`[data-message-id="${data.messageId}"]`);
        if (messageElement) {
            const readReceiptsContainer = messageElement.querySelector('.read-receipts');
            if (readReceiptsContainer) {
                // Check if this user hasn't already been marked
                const existingReadBy = readReceiptsContainer.querySelector(`[data-user-id="${data.userId}"]`);
                if (!existingReadBy) {
                    const readByElement = document.createElement('span');
                    readByElement.className = 'read-by ml-1';
                    readByElement.setAttribute('data-user-id', data.userId);
                    readByElement.innerHTML = `<span class="text-xs">✓</span> ${escapeHtml(data.username)}`;
                    readReceiptsContainer.appendChild(readByElement);
                }
            }
        }
    });

    // Add intersection observer to track message visibility
    const messageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const messageId = entry.target.getAttribute('data-message-id');
                if (messageId) {
                    markMessageAsRead(messageId);
                }
            }
        });
    }, { threshold: 0.5 });

    // Observe new messages as they're added
    const observeNewMessage = (messageElement) => {
        if (messageElement.getAttribute('data-message-id')) {
            messageObserver.observe(messageElement);
        }
    };

    // Observe existing messages
    document.querySelectorAll('.message').forEach(message => {
        observeNewMessage(message);
    });

    // Typing indicator variables
    let typingTimeout = null;
    const TYPING_TIMER_LENGTH = 3000;

    // Handle typing indicator
    const messageInput = document.getElementById('messageInput');
    messageInput.addEventListener('input', function() {
        if (!typingTimeout) {
            socket.emit('typing_start', {
                username: '<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>'
            });
        }
        
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            socket.emit('typing_end');
            typingTimeout = null;
        }, TYPING_TIMER_LENGTH);
    });

    // Listen for typing events
    socket.on('user_typing', function(data) {
        const typingIndicator = document.getElementById('typing-indicator');
        if (data.isTyping) {
            typingIndicator.textContent = `${data.username} is typing...`;
            typingIndicator.classList.remove('hidden');
        } else {
            if (typingIndicator.textContent.includes(data.username)) {
                typingIndicator.classList.add('hidden');
            }
        }
    });

    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const sidebar = document.getElementById('membersSidebar');
            const overlay = document.getElementById('mobileOverlay');
            if (!sidebar.classList.contains('translate-x-full')) {
                sidebar.classList.add('translate-x-full');
                overlay.classList.add('hidden');
                document.body.style.overflow = ''; // Restore scrolling
            }
        }
    });

    // Event listeners
    document.getElementById('messageInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Initialize when document is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize message container scroll
        const messagesContainer = document.getElementById('messages-container');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        // Request microphone permission when page loads
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            initVoiceRecording().catch(error => {
                console.error('Error initializing voice recording:', error);
            });
        }
    });

    // Message actions functions
    function toggleMessageActions(messageId) {
        const actionsMenu = document.getElementById(`message-actions-${messageId}`);
        const allMenus = document.querySelectorAll('.message-actions > div');
        
        // Hide all other menus
        allMenus.forEach(menu => {
            if (menu.id !== `message-actions-${messageId}`) {
                menu.classList.add('hidden');
            }
        });
        
        // Toggle current menu
        actionsMenu.classList.toggle('hidden');
    }

    function editMessage(messageId, content) {
        const messageElement = document.querySelector(`[data-message-id="${messageId}"] .message-text`);
        if (!messageElement) return;
        
        // Create edit form
        const currentContent = messageElement.textContent.trim();
        const editForm = document.createElement('form');
        editForm.className = 'edit-form mt-2';
        editForm.innerHTML = `
            <input type="text" class="form-control" value="${currentContent}" />
            <div class="mt-2">
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                <button type="button" class="btn btn-secondary btn-sm ml-2" onclick="cancelEdit(this)">Cancel</button>
            </div>
        `;
        
        editForm.onsubmit = async function(e) {
            e.preventDefault();
            const newContent = this.querySelector('input').value.trim();
            
            if (newContent === currentContent) {
                cancelEdit(this.querySelector('button[type="button"]'));
                return;
            }
            
            try {
                // Update UI immediately for faster feedback
                messageElement.textContent = newContent;
                cancelEdit(this.querySelector('button[type="button"]'));
                
                // Send to server
                const response = await fetch('edit_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        message_id: messageId,
                        content: newContent
                    })
                });
                
                const result = await response.json();
                if (!result.success) {
                    // Revert if failed
                    messageElement.textContent = currentContent;
                    throw new Error(result.error || 'Failed to edit message');
                }
            } catch (error) {
                console.error('Error editing message:', error);
                alert('Failed to edit message. Please try again.');
            }
        };
        
        messageElement.parentNode.appendChild(editForm);
        const input = editForm.querySelector('input');
        input.focus();
        input.select();
    }

    function cancelEdit(button) {
        const formElement = button.parentNode.parentNode;
        if (!formElement) return;
        
        // Get original message content
        fetch(`get_message.php?id=${formElement.querySelector('input').getAttribute('data-message-id')}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'message-text break-words';
                    messageDiv.textContent = data.message.content;
                    formElement.replaceWith(messageDiv);
                }
            });
    }

    function deleteMessage(messageId) {
    if (!confirm('Are you sure you want to delete this message?')) return;
    
    const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
    if (!messageElement) return;
    
    messageElement.style.opacity = '0.5';
    
    fetch('delete_message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            message_id: messageId
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            messageElement.remove();
            socket.emit('message_deleted', {
                room_id: '<?php echo $room_id; ?>',
                message_id: messageId
            });
        } else {
            messageElement.style.opacity = '1';
            throw new Error(result.error || 'Failed to delete message');
        }
    })
    .catch(error => {
        console.error('Error deleting message:', error);
        messageElement.style.opacity = '1';
        alert('Failed to delete message. Please try again.');
    });
}

    // Socket.IO event handlers for real-time updates
    socket.on('message_deleted', function(data) {
        console.log('Message deleted event received:', data);
        const messageElement = document.querySelector(`[data-message-id="${data.message_id}"]`);
        if (messageElement) {
            messageElement.remove();
        }
    });

    socket.on('message_edited', function(data) {
        console.log('Message edited event received:', data);
        const messageElement = document.querySelector(`[data-message-id="${data.message_id}"] .message-text`);
        if (messageElement) {
            messageElement.textContent = data.content;
            // Remove edit form if it exists
            const editForm = messageElement.parentNode.querySelector('.edit-form');
            if (editForm) {
                editForm.remove();
            }
        }
    });

    // Join room for Socket.IO
    socket.emit('join_room', {
        room_id: '<?php echo $room_id; ?>'
    });
</script>

<!-- Socket.IO Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/socket.io/4.7.2/socket.io.js"></script>
<script>
    // Wait for DOM and Socket.IO to be ready
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Socket.IO connection first
        const socket = io('http://localhost:3000', {
    query: {
        userId: '<?php echo $user_id; ?>',
        roomId: '<?php echo $room_id; ?>'
    },
    transports: ['websocket']
});

socket.on('connect', () => {
    console.log('Connected to Socket.IO server');
    socket.emit('join_room', { room_id: '<?php echo $room_id; ?>' });
});

socket.on('connect_error', (error) => {
    console.error('Socket.IO connection error:', error);
});

        // Initialize chat settings from PHP
        const chatSettings = <?php echo json_encode($chat_settings ?? []); ?>;
        const privacySettings = <?php echo json_encode($privacy_settings ?? []); ?>;
        const notificationSettings = <?php echo json_encode($notification_settings ?? []); ?>;
        
        // Voice recording variables
        let mediaRecorder = null;
        let recordedBlob = null;
        let audioChunks = [];
        let isRecording = false;

        // Listen for new messages
        socket.on('new_message', function(data) {
            if (data.userId != <?php echo $user_id; ?>) {
                appendMessage({
                    id: data.messageId,
                    content: data.content,
                    type: data.type,
                    timestamp: data.timestamp,
                    is_own: false,
                    username: data.username,
                    avatar: data.avatar
                });
            }
        });

        // Make sendMessage available globally
        window.sendMessage = async function() {
            const messageInput = document.getElementById('message-input');
            const message = messageInput.value.trim();
            const form = document.getElementById('message-form');
            const formData = new FormData(form);

            if (!message && !recordedBlob) return;

            try {
                if (recordedBlob) {
                    formData.append('type', 'voice');
                    formData.append('voice', recordedBlob, 'voice.wav');
                } else {
                    formData.append('type', 'text');
                    formData.append('message', message);
                }

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (result.status === 'success') {
                    socket.emit('send_message', {
                        roomId: '<?php echo $room_id; ?>',
                        userId: '<?php echo $user_id; ?>',
                        username: '<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>',
                        avatar: '<?php echo htmlspecialchars($_SESSION['avatar'] ?? ''); ?>',
                        messageId: result.message.id,
                        content: result.message.content,
                        type: result.message.type,
                        timestamp: result.message.timestamp
                    });

                    messageInput.value = '';
                    if (recordedBlob) {
                        resetVoiceRecording();
                    }
                    
                    appendMessage(result.message);
                    
                    const messagesContainer = document.getElementById('messages-container');
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                } else {
                    throw new Error(result.message || 'Failed to send message');
                }
            } catch (error) {
                console.error('Error sending message:', error);
                alert('Failed to send message. Please try again.');
            }
        };
    });


<?php include 'includes/footer.php'; ?>

<style>
    /* Message Actions Styles */
    .message-actions {
        position: relative;
        display: inline-block;
    }

    .message-actions button {
        padding: 2px 6px;
        border-radius: 4px;
        transition: background-color 0.2s;
    }

    .message-actions > div {
        position: absolute;
        right: 0;
        bottom: 100%;
        margin-bottom: 4px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        min-width: 120px;
        z-index: 1000;
    }

    .dark .message-actions > div {
        background: #1f2937;
    }

    .message-actions button:hover {
        background-color: rgba(0, 0, 0, 0.05);
    }

    .dark .message-actions button:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }
</style>

<script>
    // Add the toggleMembersSidebar function
    function toggleMembersSidebar() {
        const sidebar = document.getElementById('membersSidebar');
        const overlay = document.getElementById('mobileOverlay');
        
        if (sidebar.classList.contains('translate-x-full')) {
            // Open sidebar
            sidebar.classList.remove('translate-x-full');
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
        } else {
            // Close sidebar
            sidebar.classList.add('translate-x-full');
            overlay.classList.add('hidden');
            overlay.classList.remove('flex');
        }
    }

    // Close sidebar when clicking overlay
    document.getElementById('mobileOverlay').addEventListener('click', toggleMembersSidebar);
</script>
