<?php
require_once 'config/database.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle invite response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_id'], $_POST['action'])) {
    $invite_id = intval($_POST['invite_id']);
    $action = $_POST['action'];
    
    if ($action === 'accept' || $action === 'decline') {
        // Get invite details
        $stmt = $conn->prepare("SELECT * FROM room_invites WHERE id = ? AND invited_user_id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $invite_id, $user_id);
        $stmt->execute();
        $invite = $stmt->get_result()->fetch_assoc();
        
        if ($invite) {
            if ($action === 'accept') {
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Add user to room
                    $stmt = $conn->prepare("INSERT INTO room_members (room_id, user_id, joined_at) VALUES (?, ?, NOW())");
                    $stmt->bind_param("ii", $invite['room_id'], $user_id);
                    $stmt->execute();
                    
                    // Update invite status
                    $status = 'accepted';
                    $stmt = $conn->prepare("UPDATE room_invites SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $status, $invite_id);
                    $stmt->execute();
                    
                    $conn->commit();
                    $success = "You have joined the room!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Failed to join room";
                }
            } else {
                // Decline invite
                $status = 'declined';
                $stmt = $conn->prepare("UPDATE room_invites SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $status, $invite_id);
                if ($stmt->execute()) {
                    $success = "Invitation declined";
                } else {
                    $error = "Failed to decline invitation";
                }
            }
        }
    }
}

// Get pending invites
$stmt = $conn->prepare("SELECT i.*, r.name as room_name, u.username as invited_by_username 
                       FROM room_invites i 
                       JOIN rooms r ON i.room_id = r.id 
                       JOIN users u ON i.invited_by = u.id 
                       WHERE i.invited_user_id = ? AND i.status = 'pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_invites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Invitations</title>
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
                <h1 class="text-2xl font-bold mb-6">Room Invitations</h1>
                
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
                
                <?php if (empty($pending_invites)): ?>
                    <p class="text-gray-500">No pending invitations</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($pending_invites as $invite): ?>
                            <div class="border rounded-lg p-4">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="font-bold"><?php echo htmlspecialchars($invite['room_name']); ?></h3>
                                        <p class="text-sm text-gray-500">
                                            Invited by <?php echo htmlspecialchars($invite['invited_by_username']); ?>
                                            on <?php echo date('M j, Y', strtotime($invite['created_at'])); ?>
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="invite_id" value="<?php echo $invite['id']; ?>">
                                            <input type="hidden" name="action" value="accept">
                                            <button type="submit" 
                                                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                                Accept
                                            </button>
                                        </form>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="invite_id" value="<?php echo $invite['id']; ?>">
                                            <input type="hidden" name="action" value="decline">
                                            <button type="submit" 
                                                    class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                                Decline
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="mt-6">
                    <a href="index.php" class="text-blue-500 hover:text-blue-800 font-bold">
                        Back to Rooms
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
