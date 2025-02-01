<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/auth.php';

// Check if user is logged in
requireLogin();

$page_title = "Friends";
include 'includes/header.php';

// Get friend requests
$stmt = $conn->prepare("
    SELECT f.id, u.id as user_id, u.username, u.avatar, u.last_seen,
           CASE 
               WHEN u.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'Online'
               WHEN u.last_seen IS NOT NULL THEN CONCAT('Last seen ', DATE_FORMAT(u.last_seen, '%b %d, %Y at %h:%i %p'))
               ELSE 'Never'
           END as last_seen_formatted
    FROM friends f
    JOIN users u ON f.user_id = u.id
    WHERE f.friend_id = ? AND f.status = 'pending'
    ORDER BY f.created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$friend_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get friends list
$stmt = $conn->prepare("
    SELECT 
        u.id, u.username, u.avatar, u.last_seen,
        CASE 
            WHEN u.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'Online'
            WHEN u.last_seen IS NOT NULL THEN CONCAT('Last seen ', DATE_FORMAT(u.last_seen, '%b %d, %Y at %h:%i %p'))
            ELSE 'Never'
        END as last_seen_formatted,
        f.status
    FROM friends f
    JOIN users u ON (f.friend_id = u.id AND f.user_id = ?) OR (f.user_id = u.id AND f.friend_id = ?)
    WHERE f.status IN ('accepted', 'blocked')
    ORDER BY 
        f.status ASC,
        CASE WHEN u.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END DESC,
        u.last_seen DESC
");
$stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$friends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get sent requests
$stmt = $conn->prepare("
    SELECT f.id, u.id as user_id, u.username, u.avatar, f.created_at
    FROM friends f
    JOIN users u ON f.friend_id = u.id
    WHERE f.user_id = ? AND f.status = 'pending'
    ORDER BY f.created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$sent_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="min-h-screen bg-gray-100 dark:bg-gray-900 py-8">
    <div class="container mx-auto px-4">
        <div class="flex flex-col space-y-6">
            <!-- Top Section: Add Friend and Friend Requests -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Add Friend Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 transform transition-all hover:scale-[1.02]">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Add Friend</h2>
                        <svg class="w-6 h-6 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                    <form id="addFriendForm" class="space-y-4">
                        <div class="relative">
                            <input type="text" 
                                   name="username" 
                                   id="username" 
                                   placeholder="Enter username" 
                                   class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-200 dark:bg-gray-700 dark:text-white dark:border-gray-600"
                                   pattern="[A-Za-z0-9_]{3,}"
                                   title="Username must be at least 3 characters long and can only contain letters, numbers, and underscores"
                                   required>
                            <div class="text-red-500 text-sm mt-2 hidden" id="usernameError"></div>
                        </div>
                        <button type="submit" 
                                class="w-full bg-primary-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-600 transform transition-all hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                            Send Friend Request
                        </button>
                    </form>
                </div>

                <!-- Friend Requests Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Friend Requests</h2>
                        <span class="px-3 py-1 bg-primary-100 text-primary-600 rounded-full text-sm font-medium">
                            <?php echo count($friend_requests); ?> pending
                        </span>
                    </div>
                    <?php if (empty($friend_requests)): ?>
                        <div class="flex flex-col items-center justify-center py-8">
                            <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <p class="mt-4 text-gray-500 dark:text-gray-400">No pending friend requests</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($friend_requests as $request): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                    <div class="flex items-center space-x-4">
                                        <div class="relative">
                                            <img src="uploads/avatars/<?php echo htmlspecialchars($request['avatar'] ?? 'default.png'); ?>" 
                                                 alt="Avatar" 
                                                 class="w-12 h-12 rounded-full object-cover ring-2 ring-white dark:ring-gray-800">
                                            <?php if (strpos($request['last_seen_formatted'], 'Online') !== false): ?>
                                                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full ring-2 ring-white dark:ring-gray-800"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <a href="profile.php?id=<?php echo $request['user_id']; ?>" 
                                               class="font-semibold text-gray-800 dark:text-white hover:text-primary-500 transition-colors">
                                                <?php echo htmlspecialchars($request['username']); ?>
                                            </a>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo $request['last_seen_formatted']; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button data-friend-action="accept" data-friend-id="<?php echo $request['user_id']; ?>"
                                                class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                                            Accept
                                        </button>
                                        <button data-friend-action="reject" data-friend-id="<?php echo $request['user_id']; ?>"
                                                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
                                            Reject
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($sent_requests)): ?>
                        <div class="mt-8">
                            <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">Sent Requests</h3>
                            <div class="space-y-4">
                                <?php foreach ($sent_requests as $request): ?>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                        <div class="flex items-center space-x-4">
                                            <img src="uploads/avatars/<?php echo htmlspecialchars($request['avatar'] ?? 'default.png'); ?>" 
                                                 alt="Avatar" 
                                                 class="w-12 h-12 rounded-full object-cover ring-2 ring-white dark:ring-gray-800">
                                            <div>
                                                <a href="profile.php?id=<?php echo $request['user_id']; ?>" 
                                                   class="font-semibold text-gray-800 dark:text-white hover:text-primary-500 transition-colors">
                                                    <?php echo htmlspecialchars($request['username']); ?>
                                                </a>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    Sent <?php echo time_elapsed_string($request['created_at']); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <button data-friend-action="cancel_request" data-friend-id="<?php echo $request['user_id']; ?>"
                                                class="text-gray-500 hover:text-red-500 dark:text-gray-400 dark:hover:text-red-400 transition-colors">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Friends List Section -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Friends</h2>
                    <span class="px-3 py-1 bg-primary-100 text-primary-600 rounded-full text-sm font-medium">
                        <?php echo count($friends); ?> total
                    </span>
                </div>
                <?php if (empty($friends)): ?>
                    <div class="flex flex-col items-center justify-center py-12">
                        <svg class="w-20 h-20 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <p class="mt-4 text-gray-500 dark:text-gray-400">No friends yet</p>
                        <p class="text-sm text-gray-400 dark:text-gray-500">Start by sending friend requests!</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($friends as $friend): ?>
                            <div class="flex flex-col p-6 bg-gray-50 dark:bg-gray-700 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-600 transition-all transform hover:scale-[1.02]">
                                <div class="flex items-center space-x-4">
                                    <div class="relative">
                                        <img src="uploads/avatars/<?php echo htmlspecialchars($friend['avatar'] ?? 'default.png'); ?>" 
                                             alt="Avatar" 
                                             class="w-16 h-16 rounded-full object-cover ring-4 ring-white dark:ring-gray-800">
                                        <?php if (strpos($friend['last_seen_formatted'], 'Online') !== false): ?>
                                            <span class="absolute bottom-0 right-0 w-4 h-4 bg-green-500 rounded-full ring-2 ring-white dark:ring-gray-800"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <a href="profile.php?id=<?php echo $friend['id']; ?>" 
                                           class="font-semibold text-gray-800 dark:text-white hover:text-primary-500 transition-colors">
                                            <?php echo htmlspecialchars($friend['username']); ?>
                                        </a>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo $friend['last_seen_formatted']; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-4 flex space-x-2">
                                    <?php if ($friend['status'] === 'blocked'): ?>
                                        <button data-friend-action="unblock" data-friend-id="<?php echo $friend['id']; ?>"
                                                class="flex-1 px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded-lg transition-colors">
                                            Unblock
                                        </button>
                                    <?php else: ?>
                                        <form action="chat.php" method="GET" style="flex: 1;">
                                            <input type="hidden" name="friend_id" value="<?php echo htmlspecialchars($friend['id']); ?>">
                                            <button type="submit" 
                                                    class="w-full px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white rounded-lg text-center transition-colors">
                                                <i class="fas fa-comment-alt mr-2"></i>Chat
                                            </button>
                                        </form>
                                        <button data-friend-action="block" data-friend-id="<?php echo $friend['id']; ?>"
                                                class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded-lg transition-colors">
                                            Block
                                        </button>
                                        <button data-friend-action="unfriend" data-friend-id="<?php echo $friend['id']; ?>"
                                                class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors">
                                            Unfriend
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Show notification function
    function showNotification(message, type = 'success') {
        // Remove any existing notifications
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => notification.remove());
        
        // Create new notification
        const notification = document.createElement('div');
        notification.className = `notification fixed bottom-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
            type === 'error' ? 'bg-red-500' : 'bg-green-500'
        } text-white`;
        notification.textContent = message;
        
        // Add to document
        document.body.appendChild(notification);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    // Handle add friend form submission
    document.getElementById('addFriendForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        console.log('Form submission started');
        
        const submitButton = this.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        const usernameInput = this.querySelector('input[name="username"]');
        const usernameError = document.getElementById('usernameError');
        
        // Clear previous error
        usernameError.textContent = '';
        usernameError.classList.add('hidden');
        
        // Validate username
        const username = usernameInput.value.trim();
        console.log('Username:', username);
        
        if (!username) {
            usernameError.textContent = 'Username cannot be empty';
            usernameError.classList.remove('hidden');
            usernameInput.focus();
            return;
        }
        
        if (username.length < 3) {
            usernameError.textContent = 'Username must be at least 3 characters long';
            usernameError.classList.remove('hidden');
            usernameInput.focus();
            return;
        }
        
        if (!/^[A-Za-z0-9_]+$/.test(username)) {
            usernameError.textContent = 'Username can only contain letters, numbers, and underscores';
            usernameError.classList.remove('hidden');
            usernameInput.focus();
            return;
        }
        
        try {
            // Disable form while submitting
            submitButton.disabled = true;
            submitButton.textContent = 'Sending...';
            usernameInput.disabled = true;
            
            // Create form data with action
            const formData = new FormData();
            formData.append('action', 'send_request_by_username');
            formData.append('username', username);
            
            console.log('Sending request with data:', {
                action: 'send_request_by_username',
                username: username
            });
            
            const response = await fetch('api/friends.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(formData)
            });
            
            console.log('Response status:', response.status);
            const data = await response.json();
            console.log('Response data:', data);
            
            if (data.success) {
                showNotification(data.message || 'Friend request sent successfully');
                // Clear input
                usernameInput.value = '';
            } else {
                showNotification(data.error || 'Failed to send friend request', 'error');
                console.error('Server error:', data.error);
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('An error occurred while sending the friend request. Please try again later.', 'error');
        } finally {
            // Re-enable form
            submitButton.disabled = false;
            submitButton.textContent = originalText;
            usernameInput.disabled = false;
        }
    });

    // Handle friend request actions (accept/reject)
    document.querySelectorAll('[data-friend-action]').forEach(button => {
        button.addEventListener('click', async function(e) {
            e.preventDefault();
            
            const action = this.dataset.friendAction;
            const friendId = this.dataset.friendId;
            const originalText = this.textContent;
            
            try {
                // Disable button while processing
                this.disabled = true;
                this.textContent = 'Processing...';
                
                const response = await fetch('api/friends.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: action,
                        friend_id: friendId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message || 'Friend request processed successfully');
                    // Remove the request from the list
                    const requestDiv = this.closest('.friend-request');
                    if (requestDiv) {
                        requestDiv.remove();
                    }
                    // Reload page to update friends list
                    window.location.reload();
                } else {
                    showNotification(data.error || 'Failed to process friend request', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred while processing the request. Please try again later.', 'error');
            } finally {
                // Re-enable button
                this.disabled = false;
                this.textContent = originalText;
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
