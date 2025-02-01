<?php
session_start();

// Basic configuration
error_reporting(0);
ini_set('display_errors', 0);

// Required includes
include 'config/database.php';
include 'includes/functions.php';
include 'includes/avatar_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<script>window.location.href = "login.php";</script>';
    exit();
}

// Get friend ID from URL
$friend_id = isset($_GET['friend_id']) ? (int)$_GET['friend_id'] : 0;

if (!$friend_id) {
    echo '<script>window.location.href = "friends.php";</script>';
    exit();
}

// Get friend's information
$stmt = $conn->prepare("SELECT id, username, avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $friend_id);
$stmt->execute();
$friend = $stmt->get_result()->fetch_assoc();

if (!$friend) {
    echo '<script>window.location.href = "friends.php";</script>';
    exit();
}

// Get or create chat room
$stmt = $conn->prepare("
    SELECT r.id 
    FROM rooms r
    INNER JOIN room_members rm1 ON r.id = rm1.room_id AND rm1.user_id = ?
    INNER JOIN room_members rm2 ON r.id = rm2.room_id AND rm2.user_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $_SESSION['user_id'], $friend_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();

if ($room) {
    $room_id = $room['id'];
} else {
    // Create new room
    $conn->query("INSERT INTO rooms (created_at) VALUES (NOW())");
    $room_id = $conn->insert_id;
    
    // Add both users to room_members
    $stmt = $conn->prepare("INSERT INTO room_members (room_id, user_id) VALUES (?, ?), (?, ?)");
    $stmt->bind_param("iiii", $room_id, $_SESSION['user_id'], $room_id, $friend_id);
    $stmt->execute();
}

// Handle message refresh requests
if (isset($_GET['refresh'])) {
    header('Content-Type: text/html; charset=utf-8');
    
    // Get last message ID from client
    $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    
    // Get new messages only
    $stmt = $conn->prepare("
        SELECT m.*, u.username, u.avatar,
        DATE_FORMAT(m.created_at, '%h:%i %p') as formatted_time
        FROM messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.room_id = ? AND m.id > ?
        ORDER BY m.created_at ASC
        LIMIT 50
    ");
    $stmt->bind_param("ii", $room_id, $lastId);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Return only new messages HTML
    foreach ($messages as $message): 
        $isSent = $message['user_id'] === $_SESSION['user_id'];
    ?>
        <div class="flex <?php echo $isSent ? 'justify-end' : 'justify-start'; ?> items-end space-x-2" data-message-id="<?php echo $message['id']; ?>">
            <?php if (!$isSent): ?>
                <img src="<?php echo getAvatarUrl($message['avatar']); ?>" 
                     alt="Avatar" 
                     class="w-8 h-8 rounded-full object-cover">
            <?php endif; ?>
            
            <div class="flex flex-col <?php echo $isSent ? 'items-end' : 'items-start'; ?> max-w-[70%] space-y-1">
                <div class="<?php echo $isSent 
                    ? 'bg-primary-500 text-white' 
                    : 'bg-white dark:bg-gray-800 text-gray-800 dark:text-white'; ?> 
                    rounded-xl p-3 shadow-sm">
                    <?php echo htmlspecialchars($message['content']); ?>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    <?php echo $message['formatted_time']; ?>
                </span>
            </div>
            
            <?php if ($isSent): ?>
                <img src="<?php echo getAvatarUrl($_SESSION['avatar']); ?>" 
                     alt="Your Avatar" 
                     class="w-8 h-8 rounded-full object-cover">
            <?php endif; ?>
        </div>
    <?php endforeach;
    exit();
}

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $content = trim($_POST['message']);
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if (!empty($content)) {
        $stmt = $conn->prepare("INSERT INTO messages (room_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
        if ($stmt->bind_param("iis", $room_id, $_SESSION['user_id'], $content)) {
            if ($stmt->execute()) {
                if ($isAjax) {
                    // For AJAX requests, return success response
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message_id' => $stmt->insert_id]);
                    exit();
                } else {
                    // For regular form submissions, redirect
                    header("Location: " . $_SERVER['REQUEST_URI'] . "#bottom");
                    exit();
                }
            }
        }
    }
    
    if ($isAjax) {
        // Return error for AJAX requests
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Failed to send message']);
        exit();
    }
}

// Get initial messages
$stmt = $conn->prepare("
    SELECT m.*, u.username, u.avatar,
    DATE_FORMAT(m.created_at, '%h:%i %p') as formatted_time
    FROM messages m
    JOIN users u ON m.user_id = u.id
    WHERE m.room_id = ?
    ORDER BY m.created_at DESC
    LIMIT 50
");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$messages = array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));

// Get the last message ID for refresh
$lastMessageId = !empty($messages) ? end($messages)['id'] : 0;

$page_title = "Chat with " . htmlspecialchars($friend['username']);
include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <div class="container mx-auto px-4 py-6">
        <div class="max-w-4xl mx-auto bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
            <!-- Chat Header -->
            <div class="p-4 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="friends.php" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </a>
                    <div class="relative">
                        <img src="<?php echo getAvatarUrl($friend['avatar']); ?>" 
                             alt="<?php echo htmlspecialchars($friend['username']); ?>'s Avatar" 
                             class="w-10 h-10 rounded-full object-cover">
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                            <?php echo htmlspecialchars($friend['username']); ?>
                        </h2>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <div id="messages" class="p-4 h-[calc(100vh-300px)] overflow-y-auto space-y-4 bg-gray-50 dark:bg-gray-900">
                <?php foreach ($messages as $message): 
                    $isSent = $message['user_id'] === $_SESSION['user_id'];
                ?>
                    <div class="flex <?php echo $isSent ? 'justify-end' : 'justify-start'; ?> items-end space-x-2" data-message-id="<?php echo $message['id']; ?>">
                        <?php if (!$isSent): ?>
                            <img src="<?php echo getAvatarUrl($message['avatar']); ?>" 
                                 alt="Avatar" 
                                 class="w-8 h-8 rounded-full object-cover">
                        <?php endif; ?>
                        
                        <div class="flex flex-col <?php echo $isSent ? 'items-end' : 'items-start'; ?> max-w-[70%] space-y-1">
                            <div class="<?php echo $isSent 
                                ? 'bg-primary-500 text-white' 
                                : 'bg-white dark:bg-gray-800 text-gray-800 dark:text-white'; ?> 
                                rounded-xl p-3 shadow-sm">
                                <?php echo htmlspecialchars($message['content']); ?>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                <?php echo $message['formatted_time']; ?>
                            </span>
                        </div>
                        
                        <?php if ($isSent): ?>
                            <img src="<?php echo getAvatarUrl($_SESSION['avatar']); ?>" 
                                 alt="Your Avatar" 
                                 class="w-8 h-8 rounded-full object-cover">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="bottom"></div>

            <!-- Message Input -->
            <div class="p-4 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                <form id="messageForm" class="flex items-end space-x-4" onsubmit="return sendMessage(event);">
                    <div class="flex-grow">
                        <textarea 
                            name="message" 
                            placeholder="Type your message..." 
                            class="w-full p-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:border-primary-500 dark:focus:border-primary-500 resize-none"
                            rows="1"
                            required
                            autofocus></textarea>
                    </div>
                    <button type="submit" 
                            class="p-3 bg-primary-500 text-white rounded-xl hover:bg-primary-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const messagesDiv = document.getElementById('messages');
    const messageForm = document.getElementById('messageForm');
    const messageInput = messageForm.querySelector('textarea[name="message"]');
    let lastMessageId = <?php echo $lastMessageId; ?>;
    const seenMessageIds = new Set(Array.from(document.querySelectorAll('[data-message-id]')).map(el => el.dataset.messageId));

    // Function to send message
    window.sendMessage = function(event) {
        event.preventDefault();
        const message = messageInput.value.trim();
        if (!message) return false;

        // Create form data
        const formData = new FormData();
        formData.append('message', message);

        // Clear input immediately
        messageInput.value = '';
        messageInput.style.height = 'auto';

        // Send message
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            // Force refresh messages
            checkNewMessages();
        })
        .catch(error => {
            console.error('Error:', error);
            messageInput.value = message; // Restore message on error
        });

        return false;
    };

    // Function to check for new messages
    function checkNewMessages() {
        fetch(window.location.href + '?refresh&last_id=' + lastMessageId, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(html => {
            if (html.trim()) {
                // Append new messages
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                const newMessages = Array.from(tempDiv.children);
                
                // Filter out messages we've already seen
                const uniqueMessages = newMessages.filter(msg => {
                    const messageId = msg.dataset.messageId;
                    if (!seenMessageIds.has(messageId)) {
                        seenMessageIds.add(messageId);
                        return true;
                    }
                    return false;
                });
                
                // Get the last message ID and append new messages
                if (uniqueMessages.length > 0) {
                    const lastMessage = uniqueMessages[uniqueMessages.length - 1];
                    lastMessageId = lastMessage.dataset.messageId;
                    
                    // Append messages to chat
                    uniqueMessages.forEach(msg => {
                        messagesDiv.appendChild(msg);
                    });
                    
                    // Always scroll to bottom for new messages
                    messagesDiv.scrollTop = messagesDiv.scrollHeight;
                }
            }
        })
        .catch(error => console.error('Error refreshing messages:', error));
    }

    // Auto-refresh messages every 2 seconds
    setInterval(checkNewMessages, 2000);

    // Auto-resize textarea
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    // Handle enter key to send message
    messageInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            messageForm.dispatchEvent(new Event('submit'));
        }
    });

    // Scroll to bottom on page load
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
});
</script>

<?php include 'includes/footer.php'; ?>
