<?php
require_once 'config/database.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;

// Verify if user has permission to invite (must be room member)
$stmt = $conn->prepare("SELECT r.*, rm.admin_level 
                       FROM rooms r 
                       LEFT JOIN room_members rm ON r.id = rm.room_id AND rm.user_id = ? 
                       WHERE r.id = ?");
$stmt->bind_param("ii", $user_id, $room_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();

if (!$room || !isset($room['admin_level'])) {
    header('Location: index.php');
    exit;
}

// Handle invite submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    
    if ($username) {
        // Get user ID from username
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $invited_user = $stmt->get_result()->fetch_assoc();
        
        if ($invited_user) {
            // Check if user is already a member
            $stmt = $conn->prepare("SELECT 1 FROM room_members WHERE room_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $room_id, $invited_user['id']);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows === 0) {
                // Check if invite already exists
                $stmt = $conn->prepare("SELECT 1 FROM room_invites WHERE room_id = ? AND invited_user_id = ? AND status = 'pending'");
                $stmt->bind_param("ii", $room_id, $invited_user['id']);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows === 0) {
                    // Create invite
                    $stmt = $conn->prepare("INSERT INTO room_invites (room_id, invited_user_id, invited_by) VALUES (?, ?, ?)");
                    $stmt->bind_param("iii", $room_id, $invited_user['id'], $user_id);
                    
                    if ($stmt->execute()) {
                        $success = "Invitation sent to " . htmlspecialchars($username);
                    } else {
                        $error = "Failed to send invitation";
                    }
                } else {
                    $error = "User already has a pending invitation";
                }
            } else {
                $error = "User is already a member of this room";
            }
        } else {
            $error = "User not found";
        }
    }
}

// Get pending invites
$stmt = $conn->prepare("SELECT i.*, u.username 
                       FROM room_invites i 
                       JOIN users u ON i.invited_user_id = u.id 
                       WHERE i.room_id = ? AND i.status = 'pending'");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$pending_invites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invite Members - <?php echo htmlspecialchars($room['name']); ?></title>
    <script src="https://cdn.tailwindcss.com?v=3.4.1"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h1 class="text-2xl font-bold mb-6">Invite Members to <?php echo htmlspecialchars($room['name']); ?></h1>
                
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="mb-8">
                    <div class="mb-4">
                        <label for="username" class="block text-gray-700 text-sm font-bold mb-2">
                            Username to invite
                        </label>
                        <input type="text" id="username" name="username" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               placeholder="Enter username">
                    </div>
                    
                    <button type="submit" 
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Send Invitation
                    </button>
                    
                    <a href="room.php?id=<?php echo $room_id; ?>" 
                       class="ml-2 inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                        Back to Room
                    </a>
                </form>
                
                <?php if (!empty($pending_invites)): ?>
                    <div>
                        <h2 class="text-xl font-bold mb-4">Pending Invitations</h2>
                        <div class="space-y-2">
                            <?php foreach ($pending_invites as $invite): ?>
                                <div class="flex items-center justify-between bg-gray-50 p-3 rounded">
                                    <span><?php echo htmlspecialchars($invite['username']); ?></span>
                                    <span class="text-sm text-gray-500">
                                        Invited <?php echo date('M j, Y', strtotime($invite['created_at'])); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

                </form>
                
                <?php if (!empty($pending_invites)): ?>
                    <div>
                        <h2 class="text-xl font-bold mb-4">Pending Invitations</h2>
                        <div class="space-y-2">
                            <?php foreach ($pending_invites as $invite): ?>
                                <div class="flex items-center justify-between bg-gray-50 p-3 rounded">
                                    <span><?php echo htmlspecialchars($invite['username']); ?></span>
                                    <span class="text-sm text-gray-500">
                                        Invited <?php echo date('M j, Y', strtotime($invite['created_at'])); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

