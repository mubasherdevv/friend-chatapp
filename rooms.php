<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle room creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_room') {
        $room_name = trim($_POST['room_name']);
        if (!empty($room_name)) {
            // Create new room
            $stmt = $conn->prepare("INSERT INTO rooms (name) VALUES (?)");
            $stmt->bind_param("s", $room_name);
            
            if ($stmt->execute()) {
                $room_id = $conn->insert_id;
                // Add creator as room member
                $stmt = $conn->prepare("INSERT INTO room_members (room_id, user_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $room_id, $user_id);
                $stmt->execute();
                
                header("Location: room.php?id=" . $room_id);
                exit;
            }
        }
    } elseif ($_POST['action'] === 'join_room') {
        $room_id = intval($_POST['room_id']);
        // Check if room exists
        $stmt = $conn->prepare("SELECT id FROM rooms WHERE id = ?");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            // Add user to room
            $stmt = $conn->prepare("INSERT IGNORE INTO room_members (room_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $room_id, $user_id);
            $stmt->execute();
            
            header("Location: room.php?id=" . $room_id);
            exit;
        }
    }
}

// Get user's rooms
$stmt = $conn->prepare("
    SELECT r.*, 
           COUNT(DISTINCT rm.user_id) as member_count,
           (SELECT created_at FROM messages WHERE room_id = r.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
           (SELECT content FROM messages WHERE room_id = r.id ORDER BY created_at DESC LIMIT 1) as last_message
    FROM rooms r
    JOIN room_members rm ON r.id = rm.room_id
    WHERE r.id IN (SELECT room_id FROM room_members WHERE user_id = ?)
    GROUP BY r.id
    ORDER BY COALESCE(last_message_time, '1970-01-01') DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row gap-6">
        <!-- Left side: Room list -->
        <div class="md:w-2/3">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-2xl font-semibold mb-6">Your Chat Rooms</h2>
                
                <div class="space-y-4">
                    <?php if (empty($rooms)): ?>
                        <p class="text-gray-500">You haven't joined any chat rooms yet.</p>
                    <?php else: ?>
                        <?php foreach ($rooms as $room): ?>
                            <a href="room.php?id=<?php echo $room['id']; ?>" 
                               class="block bg-gray-50 rounded-lg p-4 hover:bg-gray-100 transition">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-semibold text-lg"><?php echo htmlspecialchars($room['name']); ?></h3>
                                        <p class="text-sm text-gray-600 mt-1">
                                            <?php echo $room['member_count']; ?> members
                                        </p>
                                        <?php if ($room['last_message']): ?>
                                            <p class="text-sm text-gray-500 mt-2">
                                                <?php echo htmlspecialchars(substr($room['last_message'], 0, 50)) . (strlen($room['last_message']) > 50 ? '...' : ''); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($room['last_message_time']): ?>
                                        <span class="text-xs text-gray-500">
                                            <?php echo time_elapsed_string($room['last_message_time']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right side: Create/Join room -->
        <div class="md:w-1/3 space-y-6">
            <!-- Create Room -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h3 class="text-xl font-semibold mb-4">Create New Room</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="create_room">
                    <div>
                        <label for="room_name" class="block text-sm font-medium text-gray-700 mb-1">Room Name</label>
                        <input type="text" id="room_name" name="room_name" required
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <button type="submit" 
                            class="w-full bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition">
                        Create Room
                    </button>
                </form>
            </div>
            
            <!-- Join Room -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h3 class="text-xl font-semibold mb-4">Join Room</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="join_room">
                    <div>
                        <label for="room_id" class="block text-sm font-medium text-gray-700 mb-1">Room ID</label>
                        <input type="number" id="room_id" name="room_id" required
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <button type="submit" 
                            class="w-full bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition">
                        Join Room
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="user-actions">
    <a href="profile.php" class="btn">Profile</a>
    <a href="leaderboard.php" class="btn">Leaderboard</a>
    <a href="logout.php" class="btn">Logout</a>
</div>

<?php include 'includes/footer.php'; ?>
