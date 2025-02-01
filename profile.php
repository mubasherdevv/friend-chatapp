<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// Get user ID from URL or use logged-in user's ID
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)$_SESSION['user_id'];
$is_own_profile = $user_id === (int)$_SESSION['user_id'];
$current_user_id = (int)$_SESSION['user_id'];

// Debug information
echo "<!-- Debug Info:
User ID: " . $user_id . "
Current User ID: " . $current_user_id . "
Is Own Profile: " . ($is_own_profile ? 'Yes' : 'No') . "
-->";

try {
    // First, check if the users table exists and has the required columns
    $check_table = "SHOW TABLES LIKE 'users'";
    $table_exists = $conn->query($check_table);
    
    if ($table_exists->num_rows == 0) {
        throw new Exception("The users table does not exist. Please run the database setup script.");
    }
    
    // Get user data with counts
    $query = "
        SELECT 
            users.id,
            users.username,
            users.email,
            COALESCE(users.bio, '') as bio,
            COALESCE(users.avatar, 'default.png') as avatar,
            users.date_of_birth,
            users.created_at,
            users.last_seen as last_active,
            COALESCE(users.is_online, 0) as is_online,
            DATE_FORMAT(users.created_at, '%M %d, %Y') as formatted_join_date,
            (SELECT COUNT(*) FROM friends WHERE (user_id = users.id OR friend_id = users.id) AND status = 'accepted') as friend_count,
            (SELECT COUNT(*) FROM stories WHERE user_id = users.id AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as story_count,
            (SELECT COUNT(*) FROM messages WHERE user_id = users.id) as total_messages,
            (SELECT COUNT(*) FROM room_members WHERE user_id = users.id) as joined_rooms_count
        FROM users
        WHERE users.id = ?";

    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            die("User not found");
        }
        
        // Ensure numeric values are integers
        $user['friend_count'] = (int)($user['friend_count'] ?? 0);
        $user['story_count'] = (int)($user['story_count'] ?? 0);
        $user['total_messages'] = (int)($user['total_messages'] ?? 0);
        $user['joined_rooms_count'] = (int)($user['joined_rooms_count'] ?? 0);
        
        // Ensure string values are properly set
        $user['username'] = $user['username'] ?? 'Unknown User';
        $user['email'] = $user['email'] ?? 'No email provided';
        $user['bio'] = $user['bio'] ?? '';
        
        // Fetch user's rewards
        $rewards_query = "
            SELECT 
                ua.award_type,
                ua.awarded_at
            FROM user_awards ua
            WHERE ua.user_id = ?
            ORDER BY ua.awarded_at DESC";
            
        $rewards_stmt = $conn->prepare($rewards_query);
        $rewards_stmt->bind_param("i", $user_id);
        $rewards_stmt->execute();
        $rewards_result = $rewards_stmt->get_result();
        $user_rewards = $rewards_result->fetch_all(MYSQLI_ASSOC);
        
        // Update last seen and online status for own profile
        if ($is_own_profile) {
            $update = $conn->prepare("UPDATE users SET last_seen = NOW(), is_online = 1 WHERE id = ?");
            $update->bind_param("i", $user_id);
            $update->execute();
        }
        
        // Get social features data
        // Check friendship status
        $stmt = $conn->prepare("
            SELECT status FROM friends 
            WHERE (user_id = ? AND friend_id = ?) 
            OR (user_id = ? AND friend_id = ?)
        ");
        $stmt->bind_param("iiii", $current_user_id, $user_id, $user_id, $current_user_id);
        $stmt->execute();
        $friendship = $stmt->get_result()->fetch_assoc();

        // Debug friendship status
        echo "<!-- Friendship Status: " . ($friendship ? $friendship['status'] : 'No friendship record') . " -->";

        // Get user's current status
        $stmt = $conn->prepare("
            SELECT * FROM user_status 
            WHERE user_id = ? 
            AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $current_status = $stmt->get_result()->fetch_assoc();

        // Debug current status
        echo "<!-- Current Status: " . ($current_status ? json_encode($current_status) : 'No status') . " -->";

        // Get user's active stories with view counts and viewer information
        $stmt = $conn->prepare("
            SELECT 
                s.*, 
                COUNT(DISTINCT sv.viewer_id) as view_count,
                GROUP_CONCAT(DISTINCT u.username ORDER BY sv.viewed_at DESC SEPARATOR '|') as viewers
            FROM stories s 
            LEFT JOIN story_views sv ON s.id = sv.story_id
            LEFT JOIN users u ON sv.viewer_id = u.id
            WHERE s.user_id = ? 
            AND (s.expires_at IS NULL OR s.expires_at > NOW())
            GROUP BY s.id
            ORDER BY s.created_at DESC
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Debug stories
        echo "<!-- Stories Count: " . count($stories) . " -->";

        // Get user's interest groups
        $stmt = $conn->prepare("
            SELECT ig.*, COUNT(ui2.user_id) as member_count
            FROM interest_groups ig
            JOIN user_interests ui ON ig.id = ui.interest_group_id
            LEFT JOIN user_interests ui2 ON ig.id = ui2.interest_group_id
            WHERE ui.user_id = ?
            GROUP BY ig.id
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $interest_groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Debug interest groups
        echo "<!-- Interest Groups Count: " . count($interest_groups) . " -->";

        // Get recent activity
        $stmt = $conn->prepare("
            SELECT af.*, u.username, u.avatar
            FROM activity_feed af
            JOIN users u ON af.user_id = u.id
            WHERE af.user_id = ?
            AND af.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY af.created_at DESC
            LIMIT 10
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Debug activities
        echo "<!-- Activities Count: " . count($activities) . " -->";
        if (!empty($activities)) {
            echo "<!-- Sample Activity: " . json_encode($activities[0]) . " -->";
        }
    }
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    die("An error occurred: " . $e->getMessage());
}

// If this is an AJAX request, return only the profile content
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    define('INCLUDED_FROM_PROFILE', true);
    include 'includes/profile_content.php';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_own_profile ? 'My Profile' : htmlspecialchars($user['username']) . "'s Profile"; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .story-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .story-content {
            position: relative;
            max-width: 90%;
            max-height: 90vh;
            width: auto;
            height: auto;
            background-color: #000;
            border-radius: 8px;
            overflow: hidden;
        }

        .story-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            font-size: 20px;
            border-radius: 50%;
            transition: background-color 0.3s;
        }

        .story-nav:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .story-prev {
            left: 10px;
        }

        .story-next {
            right: 10px;
        }

        .story-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s;
        }

        .story-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .story-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
            padding: 20px;
            color: white;
        }

        .story-ring {
            position: relative;
        }

        .story-ring::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            border-radius: 50%;
            background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
            z-index: -1;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            padding: 1rem;
            overflow-y: auto;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background-color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            width: 100%;
            max-width: 28rem;
            position: relative;
        }

        .dark .modal-content {
            background-color: #1f2937;
            color: white;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-6 dark:bg-gray-800">
                <?php if ($is_own_profile): ?>
                <!-- Edit Profile Button -->
                <button onclick="showEditProfileModal()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition-colors">
                    Edit Profile
                </button>
                <?php endif; ?>
                <?php 
                define('INCLUDED_FROM_PROFILE', true);
                include 'includes/profile_content.php'; 
                ?>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <h2 class="text-2xl font-bold mb-4 dark:text-white">Edit Profile</h2>
            <form id="editProfileForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                    <input type="text" id="editUsername" value="<?php echo htmlspecialchars($user['username']); ?>" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:border-gray-600">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                    <input type="email" id="editEmail" value="<?php echo htmlspecialchars($user['email']); ?>" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:border-gray-600">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bio</label>
                    <textarea id="editBio" rows="4" 
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:border-gray-600"><?php echo htmlspecialchars($user['bio']); ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date of Birth</label>
                    <input type="date" id="editDateOfBirth" value="<?php echo $user['date_of_birth']; ?>" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:border-gray-600">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Profile Picture</label>
                    <input type="file" id="editAvatar" accept="image/*" 
                           class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:text-gray-400 dark:file:bg-gray-700 dark:file:text-gray-300">
                </div>
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="hideEditProfileModal()" 
                            class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition-colors">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Story Modal -->
    <div id="storyModal" class="story-modal">
        <div class="story-content">
            <div id="storyContent"></div>
            <div class="story-info p-4 text-white">
                <div id="storyViewCount" class="text-sm"></div>
                <div id="storyViewers" class="text-sm mt-1"></div>
            </div>
            <button onclick="prevStory()" class="story-nav story-prev">&lt;</button>
            <button onclick="nextStory()" class="story-nav story-next">&gt;</button>
            <button onclick="closeStory()" class="story-close">&times;</button>
        </div>
    </div>

    <script>
        let currentStoryIndex = 0;
        let storyTimeout;
        let stories = <?php echo json_encode($stories); ?>;

        function showStories() {
            if (stories.length === 0) return;
            
            // Reset to first story
            currentStoryIndex = 0;
            
            // Show the modal
            const modal = document.getElementById('storyModal');
            modal.style.display = 'flex';
            
            // Show the first story
            showCurrentStory();
        }

        function viewStory(storyId) {
            // Record story view
            fetch('record_story_view.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    story_id: storyId
                })
            });
        }

        function showCurrentStory() {
            clearTimeout(storyTimeout);
            
            const story = stories[currentStoryIndex];
            if (!story) return;
            
            const content = document.getElementById('storyContent');
            const viewCount = document.getElementById('storyViewCount');
            const viewers = document.getElementById('storyViewers');
            
            content.innerHTML = '';
            
            if (story.content_type === 'text') {
                content.innerHTML = `
                    <div class="text-content p-4" style="background-color: ${story.background_color || '#000000'}">
                        <p class="text-white text-lg" style="font-family: ${story.font_style || 'Arial'}">${story.content}</p>
                        <a href="view_story.php?id=${story.id}" class="text-blue-400 text-sm mt-2 block" target="_blank">View story</a>
                    </div>`;
            } else if (story.content_type === 'image') {
                content.innerHTML = `
                    <div>
                        <img src="${story.content}" class="max-w-full h-auto" alt="Story content">
                        <a href="view_story.php?id=${story.id}" class="text-blue-400 text-sm mt-2 block" target="_blank">View story</a>
                    </div>`;
            }
            
            // Display view count and viewers
            if (story.view_count) {
                viewCount.textContent = `${story.view_count} view${story.view_count !== '1' ? 's' : ''}`;
                if (story.viewers) {
                    const viewerList = story.viewers.split('|');
                    viewers.textContent = `Seen by: ${viewerList.slice(0, 3).join(', ')}${viewerList.length > 3 ? ` and ${viewerList.length - 3} more` : ''}`;
                }
            } else {
                viewCount.textContent = '0 views';
                viewers.textContent = '';
            }
            
            // Record view if not own story
            if (story.user_id !== <?php echo $current_user_id; ?>) {
                viewStory(story.id);
            }
            
            storyTimeout = setTimeout(() => {
                nextStory();
            }, 5000);
        }

        function nextStory() {
            if (currentStoryIndex < stories.length - 1) {
                currentStoryIndex++;
                showCurrentStory();
            } else {
                closeStory();
            }
        }

        function prevStory() {
            if (currentStoryIndex > 0) {
                currentStoryIndex--;
                showCurrentStory();
            }
        }

        function closeStory() {
            clearTimeout(storyTimeout);
            const modal = document.getElementById('storyModal');
            modal.style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('storyModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStory();
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (document.getElementById('storyModal').style.display === 'block') {
                if (e.key === 'ArrowLeft') prevStory();
                else if (e.key === 'ArrowRight') nextStory();
                else if (e.key === 'Escape') closeStory();
            }
        });

        // Add friend functionality
        function addFriend(userId) {
            fetch('api/add_friend.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to send friend request');
                }
            });
        }

        function viewStory(storyId) {
            window.location.href = 'view_story.php?id=' + storyId;
        }

        // Modal Functions
        function showEditProfileModal() {
            document.getElementById('editProfileModal').classList.add('show');
        }

        function hideEditProfileModal() {
            document.getElementById('editProfileModal').classList.remove('show');
        }

        // Handle Edit Profile Form Submission
        document.getElementById('editProfileForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.textContent = 'Updating...';
            submitButton.disabled = true;
            
            try {
                const formData = new FormData();
                
                // Add form fields
                formData.append('username', document.getElementById('editUsername').value);
                formData.append('email', document.getElementById('editEmail').value);
                formData.append('bio', document.getElementById('editBio').value);
                formData.append('date_of_birth', document.getElementById('editDateOfBirth').value);
                
                // Add avatar if selected
                const avatarInput = document.getElementById('editAvatar');
                if (avatarInput.files.length > 0) {
                    formData.append('avatar', avatarInput.files[0]);
                }
                
                const response = await fetch('update_profile.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success message
                    alert('Profile updated successfully!');
                    // Force browser to reload the image by adding a timestamp
                    const avatar = document.querySelector('.profile-avatar');
                    if (avatar) {
                        const currentSrc = avatar.src.split('?')[0];
                        avatar.src = currentSrc + '?v=' + new Date().getTime();
                    }
                    // Reload the page to show other updated information
                    window.location.reload();
                } else {
                    throw new Error(result.error || 'Failed to update profile');
                }
            } catch (error) {
                alert(error.message);
            } finally {
                // Reset button state
                submitButton.textContent = originalText;
                submitButton.disabled = false;
            }
        });

        // Close modal when clicking outside
        document.getElementById('editProfileModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideEditProfileModal();
            }
        });

        // Close modal when pressing ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('editProfileModal').classList.contains('show')) {
                hideEditProfileModal();
            }
        });
    </script>
</body>
</html>
