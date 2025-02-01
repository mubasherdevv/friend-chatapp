<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug logging
error_log("Script started");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST Data: " . print_r($_POST, true));
error_log("GET Data: " . print_r($_GET, true));

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get room ID from GET parameter
$room_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$room_id) {
    $_SESSION['error'] = "Room ID is required. Please select a room first.";
    header('Location: index.php');
    exit;
}

// Get room data
$stmt = $conn->prepare("SELECT r.*, rm.admin_level, (r.admin_id = ?) as is_creator 
                       FROM rooms r 
                       LEFT JOIN room_members rm ON r.id = rm.room_id AND rm.user_id = ? 
                       WHERE r.id = ?");
if (!$stmt) {
    $_SESSION['error'] = "Database error occurred. Please try again.";
    header('Location: room.php?id=' . $room_id);
    exit;
}

$stmt->bind_param("iii", $_SESSION['user_id'], $_SESSION['user_id'], $room_id);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();

if (!$room) {
    $_SESSION['error'] = "Room not found or you don't have access.";
    header('Location: index.php');
    exit;
}

// Check if user has permission to edit settings
if (!$room['admin_level'] && !$room['is_creator']) {
    $_SESSION['error'] = "You don't have permission to edit room settings.";
    header('Location: room.php?id=' . $room_id);
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Processing POST request");
    
    // Update room settings
    $theme = $_POST['theme'] ?? 'default';
    $rules = $_POST['rules'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Privacy settings
    $privacy_settings = [
        'is_private' => isset($_POST['is_private']) ? 1 : 0,
        'join_approval' => isset($_POST['join_approval']) ? 1 : 0,
        'members_only_chat' => isset($_POST['members_only_chat']) ? 1 : 0
    ];
    
    // Chat settings
    $chat_settings = [
        'file_sharing' => isset($_POST['file_sharing']) ? 1 : 0,
        'image_sharing' => isset($_POST['image_sharing']) ? 1 : 0,
        'link_preview' => isset($_POST['link_preview']) ? 1 : 0,
        'max_message_length' => (int)($_POST['max_message_length'] ?? 1000)
    ];
    
    // Notification settings
    $notification_settings = [
        'messages' => isset($_POST['notify_messages']) ? 1 : 0,
        'mentions' => isset($_POST['notify_mentions']) ? 1 : 0,
        'files' => isset($_POST['notify_files']) ? 1 : 0
    ];
    
    // Convert to JSON
    $privacy_json = json_encode($privacy_settings);
    $chat_json = json_encode($chat_settings);
    $notif_json = json_encode($notification_settings);
    
    // Update room settings
    $update_sql = "UPDATE rooms SET 
                   theme = ?, 
                   rules = ?, 
                   description = ?, 
                   notification_settings = ?,
                   privacy_settings = ?,
                   chat_settings = ?,
                   max_members = ?,
                   language = ?
                   WHERE id = ?";
                   
    $max_members = (int)($_POST['max_members'] ?? 100);
    $language = $_POST['language'] ?? 'en';
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssssssiis", 
        $theme, 
        $rules, 
        $description, 
        $notif_json,
        $privacy_json,
        $chat_json,
        $max_members,
        $language,
        $room_id
    );
    
    if ($update_stmt->execute()) {
        // Handle wallpaper upload if present
        if (isset($_FILES['wallpaper']) && $_FILES['wallpaper']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['wallpaper']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $wallpaper = 'room_' . $room_id . '_' . time() . '.' . $ext;
                $upload_dir = 'uploads/wallpapers';
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                if (move_uploaded_file($_FILES['wallpaper']['tmp_name'], $upload_dir . '/' . $wallpaper)) {
                    $sql = "UPDATE rooms SET wallpaper = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $wallpaper, $room_id);
                    $stmt->execute();
                }
            }
        }
        
        $_SESSION['message'] = "Settings saved successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error saving settings: " . $conn->error;
        $_SESSION['message_type'] = "error";
    }
    
    // Redirect back
    header("Location: room_settings.php?id=" . $room_id);
    exit();
}

// Decode existing notification settings
$room['notification_settings'] = json_decode($room['notification_settings'] ?? '{}', true) ?: [];
$room['privacy_settings'] = json_decode($room['privacy_settings'] ?? '{}', true) ?: [];
$room['chat_settings'] = json_decode($room['chat_settings'] ?? '{}', true) ?: [];

// Check and add columns individually if they don't exist
$columns = [
    'theme' => "ALTER TABLE rooms ADD COLUMN theme VARCHAR(50) DEFAULT 'default'",
    'wallpaper' => "ALTER TABLE rooms ADD COLUMN wallpaper VARCHAR(255) DEFAULT NULL",
    'notification_settings' => "ALTER TABLE rooms ADD COLUMN notification_settings JSON DEFAULT NULL",
    'rules' => "ALTER TABLE rooms ADD COLUMN rules TEXT DEFAULT NULL",
    'description' => "ALTER TABLE rooms ADD COLUMN description TEXT DEFAULT NULL",
    'is_locked' => "ALTER TABLE rooms ADD COLUMN is_locked TINYINT(1) DEFAULT 0",
    'privacy_settings' => "ALTER TABLE rooms ADD COLUMN privacy_settings JSON DEFAULT NULL",
    'chat_settings' => "ALTER TABLE rooms ADD COLUMN chat_settings JSON DEFAULT NULL",
    'max_members' => "ALTER TABLE rooms ADD COLUMN max_members INT DEFAULT 100",
    'language' => "ALTER TABLE rooms ADD COLUMN language VARCHAR(10) DEFAULT 'en'"
];

foreach ($columns as $column => $sql) {
    $result = $conn->query("SHOW COLUMNS FROM rooms LIKE '$column'");
    if ($result->num_rows === 0) {
        $conn->query($sql);
    }
}

// Check and add admin_level column to room_members if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM room_members LIKE 'admin_level'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE room_members ADD COLUMN admin_level INT DEFAULT 0");
    // Set admin_level = 2 for room creators (highest level)
    $conn->query("UPDATE room_members SET admin_level = 2 WHERE user_id IN (SELECT admin_id FROM rooms WHERE id = room_members.room_id)");
}

// Set default theme for existing rooms if needed
$conn->query("UPDATE rooms SET theme = 'default' WHERE theme IS NULL");

// Handle admin management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'manage_admin') {
        $target_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $admin_action = isset($_POST['admin_action']) ? $_POST['admin_action'] : '';
        
        if ($target_user_id && $admin_action && $room['is_creator']) {
            if ($admin_action === 'promote') {
                $stmt = $conn->prepare("UPDATE room_members SET admin_level = 2 WHERE room_id = ? AND user_id = ?");
            } else if ($admin_action === 'demote') {
                $stmt = $conn->prepare("UPDATE room_members SET admin_level = 0 WHERE room_id = ? AND user_id = ?");
            }
            if (isset($stmt)) {
                $stmt->bind_param("ii", $room_id, $target_user_id);
                $stmt->execute();
                header('Location: room_settings.php?id=' . $room_id);
                exit;
            }
        }
    } else if ($_POST['action'] === 'toggle_lock' && $room['is_creator']) {
        $stmt = $conn->prepare("UPDATE rooms SET is_locked = NOT is_locked WHERE id = ?");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        header('Location: room_settings.php?id=' . $room_id);
        exit;
    }
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-2xl font-bold mb-6">Room Settings</h1>
        
        <form method="POST" class="space-y-6" enctype="multipart/form-data">
            <!-- Theme Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Theme</label>
                <select name="theme" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    <option value="default" <?php echo ($room['theme'] ?? 'default') === 'default' ? 'selected' : ''; ?>>Default</option>
                    <option value="dark" <?php echo ($room['theme'] ?? 'default') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                    <option value="light" <?php echo ($room['theme'] ?? 'default') === 'light' ? 'selected' : ''; ?>>Light</option>
                    <option value="blue" <?php echo ($room['theme'] ?? 'default') === 'blue' ? 'selected' : ''; ?>>Blue</option>
                    <option value="green" <?php echo ($room['theme'] ?? 'default') === 'green' ? 'selected' : ''; ?>>Green</option>
                </select>
            </div>

            <!-- Privacy Settings -->
            <div class="border rounded-lg p-4">
                <h3 class="text-lg font-medium mb-4">Privacy Settings</h3>
                <div class="space-y-3">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_private" value="1" class="rounded text-blue-500"
                               <?php echo (!empty($room['privacy_settings']['is_private'])) ? 'checked' : ''; ?>>
                        <span class="ml-2">Private Room (Only invited members can join)</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="join_approval" value="1" class="rounded text-blue-500"
                               <?php echo (!empty($room['privacy_settings']['join_approval'])) ? 'checked' : ''; ?>>
                        <span class="ml-2">Require Admin Approval to Join</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="members_only_chat" value="1" class="rounded text-blue-500"
                               <?php echo (!empty($room['privacy_settings']['members_only_chat'])) ? 'checked' : ''; ?>>
                        <span class="ml-2">Members Only Chat</span>
                    </label>
                </div>
            </div>

            <!-- Chat Settings -->
            <div class="border rounded-lg p-4">
                <h3 class="text-lg font-medium mb-4">Chat Settings</h3>
                <div class="space-y-4">
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="file_sharing" value="1" class="rounded text-blue-500"
                                   <?php echo (!empty($room['chat_settings']['file_sharing'])) ? 'checked' : ''; ?>>
                            <span class="ml-2">Allow File Sharing</span>
                        </label>
                    </div>
                    
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="image_sharing" value="1" class="rounded text-blue-500"
                                   <?php echo (!empty($room['chat_settings']['image_sharing'])) ? 'checked' : ''; ?>>
                            <span class="ml-2">Allow Image Sharing</span>
                        </label>
                    </div>
                    
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="link_preview" value="1" class="rounded text-blue-500"
                                   <?php echo (!empty($room['chat_settings']['link_preview'])) ? 'checked' : ''; ?>>
                            <span class="ml-2">Enable Link Previews</span>
                        </label>
                    </div>
                    
                    <div>
                        <label class="block text-sm text-gray-600">Maximum Message Length</label>
                        <input type="number" name="max_message_length" min="100" max="5000" 
                               value="<?php echo $room['chat_settings']['max_message_length'] ?? 1000; ?>"
                               class="mt-1 w-32 rounded-md border-gray-300">
                    </div>
                </div>
            </div>

            <!-- Room Capacity -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Maximum Members</label>
                <input type="number" name="max_members" min="2" max="1000" 
                       value="<?php echo $room['max_members'] ?? 100; ?>"
                       class="w-32 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
            </div>

            <!-- Language Setting -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Primary Language</label>
                <select name="language" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    <option value="en" <?php echo ($room['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                    <option value="es" <?php echo ($room['language'] ?? 'en') === 'es' ? 'selected' : ''; ?>>Spanish</option>
                    <option value="fr" <?php echo ($room['language'] ?? 'en') === 'fr' ? 'selected' : ''; ?>>French</option>
                    <option value="de" <?php echo ($room['language'] ?? 'en') === 'de' ? 'selected' : ''; ?>>German</option>
                    <option value="it" <?php echo ($room['language'] ?? 'en') === 'it' ? 'selected' : ''; ?>>Italian</option>
                    <option value="pt" <?php echo ($room['language'] ?? 'en') === 'pt' ? 'selected' : ''; ?>>Portuguese</option>
                    <option value="ru" <?php echo ($room['language'] ?? 'en') === 'ru' ? 'selected' : ''; ?>>Russian</option>
                    <option value="zh" <?php echo ($room['language'] ?? 'en') === 'zh' ? 'selected' : ''; ?>>Chinese</option>
                    <option value="ja" <?php echo ($room['language'] ?? 'en') === 'ja' ? 'selected' : ''; ?>>Japanese</option>
                    <option value="ko" <?php echo ($room['language'] ?? 'en') === 'ko' ? 'selected' : ''; ?>>Korean</option>
                </select>
            </div>

            <!-- Notification Settings -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Notification Settings</label>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" name="notify_messages" value="1" class="rounded text-blue-500" 
                               <?php echo (!empty($room['notification_settings']['messages'])) ? 'checked' : ''; ?>>
                        <span class="ml-2">New Messages</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="notify_mentions" value="1" class="rounded text-blue-500"
                               <?php echo (!empty($room['notification_settings']['mentions'])) ? 'checked' : ''; ?>>
                        <span class="ml-2">Mentions</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="notify_files" value="1" class="rounded text-blue-500"
                               <?php echo (!empty($room['notification_settings']['files'])) ? 'checked' : ''; ?>>
                        <span class="ml-2">File Uploads</span>
                    </label>
                </div>
            </div>

            <!-- Room Rules -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Room Rules</label>
                <textarea name="rules" rows="4" 
                          class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                          placeholder="Enter room rules..."><?php echo htmlspecialchars($room['rules'] ?? ''); ?></textarea>
            </div>

            <!-- Room Description -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Room Description</label>
                <textarea name="description" rows="3" 
                          class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                          placeholder="Enter room description..."><?php echo htmlspecialchars($room['description'] ?? ''); ?></textarea>
            </div>

            <!-- Wallpaper Upload -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Wallpaper</label>
                <input type="file" name="wallpaper" accept=".jpg,.jpeg,.png,.gif" 
                       class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                <?php if (!empty($room['wallpaper'])): ?>
                    <div class="mt-2">
                        <p class="text-sm text-gray-600">Current wallpaper: <?php echo htmlspecialchars($room['wallpaper']); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end mt-6">
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    Save Changes
                </button>
            </div>
        </form>
        
        <?php if ($room['is_creator']): ?>
        <div class="mt-4">
            <button type="button" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors"
                    onclick="confirmDeleteRoom(<?php echo $room_id; ?>)">
                Delete Room
            </button>
            <button type="button" class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors"
                    onclick="toggleRoomLock(<?php echo $room_id; ?>)">
                <?php echo $room['is_locked'] ? 'Unlock Room' : 'Lock Room'; ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Display messages if they exist
if (isset($_SESSION['message'])): ?>
    <div class="fixed top-4 right-4 p-4 rounded-lg <?php echo $_SESSION['message_type'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
        <?php 
        echo htmlspecialchars($_SESSION['message']); 
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        ?>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('roomSettingsForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Prevent double submission
            if (this.submitted) {
                e.preventDefault();
                return;
            }
            this.submitted = true;
            this.querySelector('button[type="submit"]').disabled = true;
        });
    }
});

function confirmDeleteRoom(roomId) {
    if (confirm('Are you sure you want to delete this room? This action cannot be undone.')) {
        window.location.href = 'admin/delete_room.php?id=' + roomId;
    }
}

function toggleRoomLock(roomId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href;
    form.innerHTML = `
        <input type="hidden" name="action" value="toggle_lock">
        <input type="hidden" name="room_id" value="${roomId}">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php include 'includes/footer.php'; ?>