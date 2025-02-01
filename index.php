<?php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session with strict settings
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true
]);


// Load database configuration

require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// If user not found in database, log them out
if (!$user) {
    session_destroy();
    header('Location: auth/login.php');
    exit;
}

// Check admin status from session
$is_admin = isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : false;

// Initialize error message variable
$error_message = '';
$success_message = '';

// Debug output
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("Is admin check: " . ($is_admin ? 'true' : 'false'));

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST request received");
    error_log("POST data: " . print_r($_POST, true));
    
    if (isset($_POST['action'])) {
        error_log("Action: " . $_POST['action']);
        
        if ($_POST['action'] === 'create_room') {
            try {
                error_log("Starting room creation process...");
                $name = trim($_POST['room_name']);
                $description = trim($_POST['description'] ?? '');
                $password = trim($_POST['room_password'] ?? '');
                
                // Validate input
                if (empty($name)) {
                    throw new Exception("Room name is required");
                }
                
                error_log("Room name: " . $name);
                error_log("Description: " . $description);
                
                // Hash password if provided
                $hashed_password = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;
                
                // Default room avatar
                $avatar = 'default_room.png';
                
                // Create room
                $stmt = $conn->prepare("
                    INSERT INTO rooms (name, description, admin_id, avatar, password) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("ssiss", $name, $description, $_SESSION['user_id'], $avatar, $hashed_password);
                
                if ($stmt->execute()) {
                    $room_id = $conn->insert_id;
                    
                    // Add creator as a member
                    $stmt = $conn->prepare("INSERT INTO room_members (room_id, user_id) VALUES (?, ?)");
                    $stmt->bind_param("ii", $room_id, $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        // Redirect to the room after successful creation
                        header("Location: room.php?id=" . $room_id . "&created=1");
                        exit;
                    } else {
                        throw new Exception("Failed to join the room");
                    }
                } else {
                    throw new Exception("Failed to create room");
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = $e->getMessage();
                header("Location: index.php");
                exit;
            }
        } elseif ($_POST['action'] === 'join_room') {
            try {
                $room_id = (int)$_POST['room_id'];
                $password = trim($_POST['room_password'] ?? '');

                // Verify room exists
                $stmt = $conn->prepare("SELECT password FROM rooms WHERE id = ?");
                $stmt->bind_param("i", $room_id);
                $stmt->execute();
                $room = $stmt->get_result()->fetch_assoc();

                if (!$room) {
                    throw new Exception("Room not found");
                }

                // Check if password is required and verify it
                if (!empty($room['password']) && !password_verify($password, $room['password'])) {
                    throw new Exception("Incorrect room password");
                }

                // Check if user is already a member
                $stmt = $conn->prepare("SELECT 1 FROM room_members WHERE room_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $room_id, $_SESSION['user_id']);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception("You are already a member of this room");
                }

                // Add user to room
                $stmt = $conn->prepare("INSERT INTO room_members (room_id, user_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $room_id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    header("Location: room.php?id=" . $room_id . "&joined=1");
                    exit;
                } else {
                    throw new Exception("Failed to join room");
                }
            } catch (Exception $e) {
                $error_message = $e->getMessage();
                error_log("Error joining room: " . $error_message);
            }
        }
    }
}

// Get error message from session if exists
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get success message if room was created or joined
if (isset($_GET['created'])) {
    $success_message = "Room created successfully!";
} elseif (isset($_GET['joined'])) {
    $success_message = "Joined room successfully!";
}

$page_title = "Home ";
include 'includes/header.php';
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #00DC82 0%, #36E4DA 50%, #0047E1 100%);
    }

    body {
        background: var(--primary-gradient);
        min-height: 100vh;
        position: relative;
    }

    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px),
                    linear-gradient(0deg, rgba(255,255,255,0.1) 1px, transparent 1px);
        background-size: 30px 30px;
        mask-image: var(--primary-gradient);
        -webkit-mask-image: var(--primary-gradient);
        pointer-events: none;
        z-index: 0;
    }

    .main-content {
        position: relative;
        z-index: 1;
        padding: 2rem 0;
    }

    .welcome-text {
        text-align: center;
        color: white;
        margin-bottom: 2rem;
    }

    .welcome-text h1 {
        font-size: 2.5rem;
        font-weight: bold;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-bottom: 0.5rem;
    }

    .welcome-text p {
        font-size: 1.1rem;
        opacity: 0.9;
    }

    .room-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        padding: 1rem;
    }

    .room-card {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 1rem;
        padding: 1.5rem;
        transition: all 0.3s ease;
    }

    .room-card:hover {
        transform: translateY(-5px);
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.3);
    }

    .room-card h2 {
        color: white;
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .room-card p {
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.9rem;
        margin-bottom: 1rem;
    }

    .room-stats {
        display: flex;
        justify-content: space-between;
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.875rem;
    }

    .create-room-btn {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 1rem;
        border-radius: 50%;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .create-room-btn:hover {
        transform: translateY(-2px);
        background: rgba(255, 255, 255, 0.3);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
    }

    #joinRoomModal {
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }

    #modalContent {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .modal-input {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
        transition: all 0.3s ease;
    }

    .modal-input:focus {
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.3);
    }

    .modal-input::placeholder {
        color: rgba(255, 255, 255, 0.5);
    }

    /* Dark mode adjustments */
    .dark body {
        background: linear-gradient(135deg, #1a365d 0%, #2d3748 50%, #1a202c 100%);
    }

    .dark .room-card {
        background: rgba(0, 0, 0, 0.2);
    }

    .dark .room-card:hover {
        background: rgba(0, 0, 0, 0.3);
    }
</style>

<div class="main-content text-gray-900 dark:text-white">
    <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>
    <div class="welcome-text">
        <h1 class="text-3xl font-bold mb-2">Welcome to Chat Rooms</h1>
        <p class="text-gray-700 dark:text-gray-300">Join a room or create your own to start chatting</p>
    </div>

    <div class="room-grid">
        <!-- Create Room Section -->
        <div class="room-card bg-white/10 dark:bg-gray-800/10 backdrop-blur-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Create New Room</h2>
            <form action="index.php" method="POST" class="room-form">
                <input type="hidden" name="action" value="create_room">
                <input type="text" name="room_name" placeholder="Room Name" required 
                       class="w-full p-2 mb-3 bg-white/10 dark:bg-gray-700/10 border border-white/20 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <textarea name="description" placeholder="Description (Optional)"
                          class="w-full p-2 mb-3 bg-white/10 dark:bg-gray-700/10 border border-white/20 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                <input type="password" name="room_password" placeholder="Password (Optional)"
                       class="w-full p-2 mb-3 bg-white/10 dark:bg-gray-700/10 border border-white/20 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit" class="btn btn-primary w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors">Create Room</button>
            </form>
        </div>

        <!-- My Rooms Section -->
        <div class="room-card bg-white/10 dark:bg-gray-800/10 backdrop-blur-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">My Rooms</h2>
            <div class="room-list">
                <?php
                // Get rooms where user is a member
                $stmt = $conn->prepare("
                    SELECT r.*, COUNT(rm.user_id) as member_count 
                    FROM rooms r 
                    JOIN room_members rm ON r.id = rm.room_id 
                    WHERE rm.user_id = ? 
                    GROUP BY r.id
                ");
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    while ($room = $result->fetch_assoc()) {
                        ?>
                        <div class="room-item flex justify-between items-center p-3 mb-2 bg-white/5 dark:bg-gray-700/5 rounded-lg hover:bg-white/10 dark:hover:bg-gray-700/10 transition-colors">
                            <div class="room-info flex items-center">
                                <img src="<?php echo $room['avatar'] ?? 'assets/images/default-room.png'; ?>" alt="Room" class="room-avatar w-10 h-10 rounded-full mr-3">
                                <div>
                                    <div class="font-medium"><?php echo htmlspecialchars($room['name']); ?></div>
                                    <small class="text-gray-600 dark:text-gray-400"><?php echo $room['member_count']; ?> member<?php echo $room['member_count'] != 1 ? 's' : ''; ?></small>
                                </div>
                            </div>
                            <a href="room.php?id=<?php echo $room['id']; ?>" class="btn btn-primary bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors">Enter</a>
                        </div>
                        <?php
                    }
                } else {
                    echo '<div class="empty-state text-gray-600 dark:text-gray-400 text-center py-4">You haven\'t joined any rooms yet</div>';
                }
                ?>
            </div>
        </div>

        <!-- Available Rooms Section -->
        <div class="room-card bg-white/10 dark:bg-gray-800/10 backdrop-blur-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Available Rooms</h2>
            <div class="room-list">
                <?php
                // Get rooms where user is not a member
                $stmt = $conn->prepare("
                    SELECT r.*, COUNT(rm.user_id) as member_count 
                    FROM rooms r 
                    LEFT JOIN room_members rm ON r.id = rm.room_id 
                    WHERE r.id NOT IN (
                        SELECT room_id 
                        FROM room_members 
                        WHERE user_id = ?
                    )
                    GROUP BY r.id
                ");
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    while ($room = $result->fetch_assoc()) {
                        ?>
                        <div class="room-item flex justify-between items-center p-3 mb-2 bg-white/5 dark:bg-gray-700/5 rounded-lg hover:bg-white/10 dark:hover:bg-gray-700/10 transition-colors">
                            <div class="room-info flex items-center">
                                <img src="<?php echo $room['avatar'] ?? 'assets/images/default-room.png'; ?>" alt="Room" class="room-avatar w-10 h-10 rounded-full mr-3">
                                <div>
                                    <div class="font-medium"><?php echo htmlspecialchars($room['name']); ?></div>
                                    <small class="text-gray-600 dark:text-gray-400"><?php echo $room['member_count']; ?> member<?php echo $room['member_count'] != 1 ? 's' : ''; ?></small>
                                </div>
                            </div>
                            <button onclick="openJoinModal(<?php echo $room['id']; ?>, <?php echo !empty($room['password']) ? 'true' : 'false'; ?>)" class="btn btn-primary bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors">Join</button>
                        </div>
                        <?php
                    }
                } else {
                    echo '<div class="empty-state text-gray-600 dark:text-gray-400 text-center py-4">No available rooms to join</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Join Room Modal -->
<div id="joinRoomModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-md flex items-center justify-center z-50">
    <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-8 max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0" id="modalContent">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-white" id="modalTitle">Join Room</h3>
            <button type="button" onclick="closeJoinModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 transition-colors duration-200">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form id="joinRoomForm" action="index.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="join_room">
            <input type="hidden" name="room_id" id="join_room_id">
            <div id="passwordField" class="hidden">
                <label class="block text-sm font-medium text-gray-300 mb-1" for="room_password">Room Password</label>
                <input type="password" name="room_password" id="room_password" class="w-full px-4 py-2 rounded-lg bg-white/10 border border-gray-600 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeJoinModal()" class="px-4 py-2 rounded-lg bg-gray-600 text-white hover:bg-gray-700 transition-colors duration-200">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-colors duration-200">Join Room</button>
            </div>
        </form>
    </div>
</div>

<script>
function openJoinModal(roomId, hasPassword) {
    document.getElementById('join_room_id').value = roomId;
    const passwordField = document.getElementById('passwordField');
    if (hasPassword) {
        passwordField.classList.remove('hidden');
    } else {
        passwordField.classList.add('hidden');
    }
    document.getElementById('joinRoomModal').classList.remove('hidden');
    setTimeout(() => {
        document.getElementById('modalContent').classList.remove('scale-95', 'opacity-0');
        document.getElementById('modalContent').classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeJoinModal() {
    const modal = document.getElementById('joinRoomModal');
    const modalContent = document.getElementById('modalContent');
    modalContent.classList.remove('scale-100', 'opacity-100');
    modalContent.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
        document.getElementById('joinRoomForm').reset();
    }, 300);
}
</script>

<?php include 'includes/footer.php'; ?>
