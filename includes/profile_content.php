<?php
// Check if this is an AJAX request expecting JSON
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!defined('INCLUDED_FROM_PROFILE') && !$isAjax) {
    exit('Direct access not permitted');
}

// If this is an AJAX request, we need to include the database connection
if ($isAjax) {
    session_start();
    require_once '../config/database.php';
    $current_user_id = $_SESSION['user_id'] ?? null;
    $user_id = $_SESSION['viewing_profile_id'] ?? $current_user_id;
    
    if (!$user_id) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'User ID not found']);
        exit;
    }
    
    // Get user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'User not found']);
        exit;
    }
}

// Initialize friendship status
$friendship_status = null;
$is_friend = false;

// Get friendship status if not viewing own profile
if ($current_user_id !== $user_id) {
    $stmt = $conn->prepare("
        SELECT status 
        FROM friends 
        WHERE (user_id = ? AND friend_id = ?) 
           OR (user_id = ? AND friend_id = ?)
    ");
    $stmt->bind_param("iiii", $current_user_id, $user_id, $user_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $friendship_status = $row['status'];
        $is_friend = ($friendship_status === 'accepted');
    }
}

// Function to check if user can view private information
function canViewPrivateInfo($visibility, $is_friend, $is_own_profile) {
    switch($visibility) {
        case 'public':
            return true;
        case 'friends':
            return $is_friend || $is_own_profile;
        case 'private':
            return $is_own_profile;
        default:
            return false;
    }
}
?>
<div class="text-center">
    <?php if (!empty($stories)): ?>
        <div class="story-ring inline-block cursor-pointer mx-auto" onclick="showStories()">
    <?php endif; ?>
        <div class="flex justify-center items-center">
            <img src="<?php echo !empty($user['avatar']) ? $user['avatar'] : 'uploads/avatars/default.png'; ?>?v=<?php echo time(); ?>" 
                alt="<?php echo htmlspecialchars($user['username']); ?>'s profile picture"
                class="profile-avatar rounded-full w-32 h-32 object-cover border-4 border-white dark:border-gray-800 shadow-lg mx-auto">
        </div>
    <?php if (!empty($stories)): ?>
        </div>
    <?php endif; ?>
    
    <h2 class="text-2xl font-bold mt-4 mb-2 dark:text-white"><?php echo htmlspecialchars($user['username']); ?></h2>
    
    <?php if (!empty($user['bio'])): ?>
        <p class="text-gray-600 dark:text-gray-400 mb-4"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
    <?php endif; ?>
    
    <p class="text-gray-600 dark:text-gray-400 mb-4">Member since <?php echo $user['formatted_join_date'] ?? 'Unknown'; ?></p>
    
    <?php if (!empty($user['email'])): ?>
        <p class="text-gray-600 dark:text-gray-400 mb-4">
            <i class="fas fa-envelope mr-2"></i>
            <?php echo htmlspecialchars($user['email']); ?>
        </p>
    <?php endif; ?>
    
    <?php if (!empty($user['date_of_birth'])): ?>
        <p class="text-gray-600 dark:text-gray-400 mb-4">
            <i class="fas fa-birthday-cake mr-2"></i>
            <?php 
            $dob = new DateTime($user['date_of_birth']);
            echo $dob->format('F j, Y'); 
            ?>
        </p>
    <?php endif; ?>
    
    <?php if (isset($current_status)): ?>
        <div class="mb-4">
            <span class="px-3 py-1 rounded-full text-sm <?php echo isset($user['is_online']) && $user['is_online'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                <?php echo isset($user['is_online']) && $user['is_online'] ? 'Online' : 'Offline'; ?>
            </span>
        </div>
    <?php endif; ?>

    <?php 
    // Get user's status updates with visibility rules
    $status_query = "
        SELECT * 
        FROM user_status 
        WHERE user_id = ? 
        AND (
            visibility = 'public'
            OR (visibility = 'friends' AND ? = true)
            OR (visibility = 'private' AND ? = ?)
        )
        AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY created_at DESC 
        LIMIT 5
    ";

    $stmt = $conn->prepare($status_query);
    $is_friend_int = $is_friend ? 1 : 0;
    $stmt->bind_param("iiii", $user_id, $is_friend_int, $current_user_id, $user_id);
    $stmt->execute();
    $statuses = $stmt->get_result();
    ?>

    <?php if (!$is_own_profile): ?>
        <div class="flex justify-center space-x-4 mb-6">
            <?php if (!$friendship_status): ?>
                <button onclick="addFriend(<?php echo $user_id; ?>)" 
                        class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
                    Add Friend
                </button>
            <?php elseif ($friendship_status === 'pending'): ?>
                <button disabled class="bg-gray-300 text-gray-600 px-4 py-2 rounded cursor-not-allowed">
                    Friend Request Pending
                </button>
            <?php endif; ?>
            <a href="chat.php?user=<?php echo $user_id; ?>" 
               class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition">
                Send Message
            </a>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-3 gap-6 mb-8 max-w-md mx-auto">
        <div class="bg-purple-50 dark:bg-purple-900 rounded-xl p-6 text-center">
            <div class="text-4xl font-bold text-purple-600 dark:text-purple-400 mb-2">
                <?php echo number_format($user['total_messages'] ?? 0); ?>
            </div>
            <div class="text-sm text-purple-600 dark:text-purple-400">Messages</div>
        </div>
        <div class="bg-blue-50 dark:bg-blue-900 rounded-xl p-6 text-center">
            <div class="text-4xl font-bold text-blue-600 dark:text-blue-400 mb-2">
                <?php echo number_format($user['joined_rooms_count'] ?? 0); ?>
            </div>
            <div class="text-sm text-blue-600 dark:text-blue-400">Rooms</div>
        </div>
        <div class="bg-pink-50 dark:bg-pink-900 rounded-xl p-6 text-center">
            <div class="text-4xl font-bold text-pink-600 dark:text-pink-400 mb-2">
                <?php echo count($user_rewards); ?>
            </div>
            <div class="text-sm text-pink-600 dark:text-pink-400">Awards</div>
        </div>
    </div>

    <!-- User's Status Updates -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Status Updates</h2>
        
        <?php if ($statuses->num_rows === 0): ?>
            <p class="text-gray-600 dark:text-gray-400 text-center py-4">No status updates to display.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php while ($status = $statuses->fetch_assoc()): ?>
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-4 last:border-0">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex items-center">
                                <span class="text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($status['status_text']); ?>
                                    <?php if ($status['emoji']): ?>
                                        <span class="ml-2 text-xl"><?php echo htmlspecialchars($status['emoji']); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="flex items-center text-sm text-gray-500">
                                <span class="mr-2"><?php echo date('M j, Y g:i A', strtotime($status['created_at'])); ?></span>
                                <?php 
                                $visibility_icon = [
                                    'public' => 'fa-globe',
                                    'friends' => 'fa-users',
                                    'private' => 'fa-lock'
                                ][$status['visibility']];
                                $visibility_color = [
                                    'public' => 'text-green-500',
                                    'friends' => 'text-blue-500',
                                    'private' => 'text-gray-500'
                                ][$status['visibility']];
                                ?>
                                <i class="fas <?php echo $visibility_icon; ?> <?php echo $visibility_color; ?>" 
                                   title="<?php echo ucfirst($status['visibility']); ?>"></i>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($stories)): ?>
        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-4 dark:text-white">Stories</h3>
            <div class="space-y-4">
                <?php foreach ($stories as $story): ?>
                    <div class="bg-white p-4 rounded-lg shadow dark:bg-gray-700">
                        <div class="font-medium text-gray-900 dark:text-white flex items-center">
                            <?php echo $story['story_type']; ?> Story
                            <span class="ml-2 px-2 py-1 text-xs rounded-full <?php 
                                echo match($story['visibility']) {
                                    'public' => 'bg-green-100 text-green-800',
                                    'friends' => 'bg-blue-100 text-blue-800',
                                    'private' => 'bg-gray-100 text-gray-800'
                                };
                            ?>">
                                <?php echo ucfirst($story['visibility']); ?>
                            </span>
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Posted <?php echo timeAgo($story['created_at']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($interest_groups)): ?>
        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-4 dark:text-white">Interest Groups</h3>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($interest_groups as $group): ?>
                    <span class="px-3 py-1 bg-gray-100 dark:bg-gray-700 rounded-full text-sm">
                        <?php echo htmlspecialchars($group['name']); ?>
                        <span class="text-gray-500 dark:text-gray-400">(<?php echo $group['member_count']; ?>)</span>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
