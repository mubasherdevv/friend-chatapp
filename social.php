<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get current user's friends
$friends_query = "
    SELECT friend_id 
    FROM friends 
    WHERE user_id = ? AND status = 'accepted'
    UNION
    SELECT user_id 
    FROM friends 
    WHERE friend_id = ? AND status = 'accepted'
";
$stmt = $conn->prepare($friends_query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$friend_ids = [];
while ($row = $result->fetch_assoc()) {
    $friend_ids[] = $row['friend_id'];
}
$friend_ids[] = $user_id; // Include user's own ID

// Convert friend IDs array to string for SQL IN clause
$friend_ids_str = implode(',', $friend_ids);
if (empty($friend_ids_str)) {
    $friend_ids_str = "0"; // Prevent SQL error if no friends
}

// Get user's current status
$stmt = $conn->prepare("
    SELECT us.*, u.username, u.avatar 
    FROM user_status us
    JOIN users u ON us.user_id = u.id 
    WHERE us.user_id = ? 
    AND (us.expires_at IS NULL OR us.expires_at > NOW())
    ORDER BY us.created_at DESC 
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_status = $stmt->get_result()->fetch_assoc();

// Get all visible statuses
$status_query = "
    WITH visible_statuses AS (
        SELECT DISTINCT s.id
        FROM user_status s
        LEFT JOIN friends f ON (
            (f.user_id = s.user_id AND f.friend_id = ? AND f.status = 'accepted')
            OR (f.friend_id = s.user_id AND f.user_id = ? AND f.status = 'accepted')
        )
        WHERE 
            s.visibility = 'public'
            OR s.user_id = ?
            OR (s.visibility = 'friends' AND f.status = 'accepted')
    )
    SELECT 
        s.*,
        u.username,
        u.avatar
    FROM user_status s
    JOIN visible_statuses vs ON s.id = vs.id
    JOIN users u ON s.user_id = u.id
    WHERE (s.expires_at IS NULL OR s.expires_at > NOW())
    ORDER BY s.created_at DESC
";

$stmt = $conn->prepare($status_query);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$all_statuses = $stmt->get_result();

// Get friends' stories
$friend_stories_query = "
    SELECT 
        u.id as user_id,
        u.username,
        u.avatar,
        GROUP_CONCAT(
            JSON_OBJECT(
                'id', s.id,
                'story_type', s.story_type,
                'content', COALESCE(s.content, ''),
                'background_color', COALESCE(s.background_color, '#000000'),
                'created_at', s.created_at,
                'username', COALESCE(u.username, ''),
                'avatar', COALESCE(u.avatar, 'default.png'),
                'visibility', s.visibility
            )
            ORDER BY s.created_at DESC
        ) as stories,
        SUM(CASE WHEN sv.id IS NULL THEN 1 ELSE 0 END) as unseen_stories
    FROM users u
    JOIN friends f ON (
        (f.user_id = u.id AND f.friend_id = ? AND f.status = 'accepted')
        OR (f.friend_id = u.id AND f.user_id = ? AND f.status = 'accepted')
    )
    JOIN stories s ON u.id = s.user_id AND s.expires_at > NOW()
    LEFT JOIN story_views sv ON s.id = sv.story_id AND sv.viewer_id = ?
    WHERE (s.visibility = 'public' OR (s.visibility = 'friends' AND f.status = 'accepted'))
    GROUP BY u.id, u.username, u.avatar
    HAVING COUNT(s.id) > 0
    ORDER BY unseen_stories DESC, MAX(s.created_at) DESC
";

$stmt = $conn->prepare($friend_stories_query);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$friend_stories = [];

while ($row = $result->fetch_assoc()) {
    $stories = explode(',', $row['stories'] ?? '');
    $row['stories'] = array_map(function($story) {
        $decoded = json_decode($story, true);
        if (is_array($decoded)) {
            $decoded['content'] = htmlspecialchars($decoded['content'] ?? '');
            $decoded['username'] = htmlspecialchars($decoded['username'] ?? '');
            $decoded['avatar'] = htmlspecialchars($decoded['avatar'] ?? 'default.png');
        }
        return $decoded;
    }, $stories);
    $friend_stories[] = $row;
}

// Get my stories
$my_stories_query = "
    SELECT 
        s.id,
        s.story_type,
        COALESCE(s.content, '') as content,
        COALESCE(s.background_color, '#000000') as background_color,
        s.visibility,
        s.created_at,
        s.expires_at,
        COALESCE(u.username, '') as username,
        COALESCE(u.avatar, 'default.png') as avatar,
        COUNT(sv.id) as view_count
    FROM stories s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN story_views sv ON s.id = sv.story_id
    WHERE s.user_id = ? AND s.expires_at > NOW()
    GROUP BY s.id, s.user_id, s.story_type, s.content, s.background_color, s.visibility, s.created_at, s.expires_at, u.username, u.avatar
    ORDER BY s.created_at DESC
";

$stmt = $conn->prepare($my_stories_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_stories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Sanitize my stories
foreach ($my_stories as &$story) {
    $story['content'] = htmlspecialchars($story['content'] ?? '');
    $story['username'] = htmlspecialchars($story['username'] ?? '');
    $story['avatar'] = htmlspecialchars($story['avatar'] ?? 'default.png');
}
unset($story);

// Get interest groups
$stmt = $conn->prepare("
    SELECT ig.*, COUNT(ui.user_id) as member_count 
    FROM interest_groups ig
    LEFT JOIN user_interests ui ON ig.id = ui.interest_group_id
    GROUP BY ig.id
    ORDER BY member_count DESC
    LIMIT 10
");
$stmt->execute();
$interest_groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get friend suggestions
$stmt = $conn->prepare("
    SELECT fs.*, u.username, u.avatar, fs.reason
    FROM friend_suggestions fs
    JOIN users u ON fs.suggested_user_id = u.id
    WHERE fs.user_id = ?
    ORDER BY fs.score DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$friend_suggestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get activity feed
$stmt = $conn->prepare("
    SELECT af.*, u.username, u.avatar
    FROM activity_feed af
    JOIN users u ON af.user_id = u.id
    JOIN friends f ON (f.user_id = ? AND f.friend_id = af.user_id AND f.status = 'accepted')
        OR (f.friend_id = ? AND f.user_id = af.user_id AND f.status = 'accepted')
    WHERE af.visibility IN ('public', 'friends')
    ORDER BY af.created_at DESC
    LIMIT 20
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$activity_feed = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Hub - Chat Room</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/emoji-mart@latest/css/emoji-mart.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/emoji-mart@latest/dist/emoji-mart.js"></script>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- Status Update Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Update Your Status</h2>
            <form id="statusForm" class="space-y-4">
                <div class="flex items-center space-x-4">
                    <div class="flex-1">
                        <input type="text" id="statusText" name="statusText" 
                               class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                               placeholder="What's on your mind?"
                               value="<?php echo htmlspecialchars($current_status['status_text'] ?? ''); ?>">
                    </div>
                    <button type="button" id="emojiPicker" class="text-2xl">😊</button>
                    <select name="visibility" id="statusVisibility" class="px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="public">Public</option>
                        <option value="friends">Friends Only</option>
                        <option value="private">Private</option>
                    </select>
                    <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                        Update
                    </button>
                </div>
            </form>
        </div>

        <!-- Debug Info (only visible to you) -->
        <?php if ($user_id == 1): // Assuming you're user ID 1 ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
            <h3 class="font-bold">Debug Info:</h3>
            <p>Your User ID: <?php echo $user_id; ?></p>
            <p>Number of Statuses Found: <?php echo $all_statuses->num_rows; ?></p>
            <p>Friends: <?php echo implode(', ', $friend_ids); ?></p>
        </div>
        <?php endif; ?>

        <!-- Status Feed -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Status Updates</h2>
            <div class="space-y-4">
                <?php if ($all_statuses->num_rows === 0): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 text-center">
                        <p class="text-gray-700 dark:text-gray-300">No statuses to display.</p>
                    </div>
                <?php else: ?>
                    <?php while ($status = $all_statuses->fetch_assoc()): ?>
                        <div class="flex items-start space-x-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <img src="uploads/avatars/<?php echo htmlspecialchars($status['avatar']); ?>" 
                                 class="w-10 h-10 rounded-full">
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($status['username']); ?>
                                        </span>
                                        <span class="ml-2 px-2 py-1 text-xs rounded-full <?php 
                                            echo match($status['visibility']) {
                                                'public' => 'bg-green-100 text-green-800',
                                                'friends' => 'bg-blue-100 text-blue-800',
                                                'private' => 'bg-gray-100 text-gray-800'
                                            };
                                        ?>">
                                            <?php echo ucfirst($status['visibility']); ?>
                                        </span>
                                    </div>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo timeAgo($status['created_at']); ?>
                                    </span>
                                </div>
                                <p class="mt-1 text-gray-600 dark:text-gray-300">
                                    <?php echo htmlspecialchars($status['status_text']); ?>
                                    <?php if ($status['emoji']): ?>
                                        <span class="ml-2 text-xl"><?php echo htmlspecialchars($status['emoji']); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Stories Section -->
            <div class="md:col-span-2 space-y-6">
                <!-- Story Creation -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Create Story</h2>
                    <form id="storyForm" class="space-y-4" method="POST" action="api/create_story.php" enctype="multipart/form-data">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Story Type
                                </label>
                                <select name="story_type" id="storyType" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="text">Text</option>
                                    <option value="image">Image</option>
                                    <option value="video">Video</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Visibility
                                </label>
                                <select name="visibility" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="public">Public</option>
                                    <option value="friends">Friends Only</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="textContent" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Story Text
                                </label>
                                <textarea name="text_content" rows="3" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Background Color
                                </label>
                                <input type="color" name="background_color" value="#000000" class="w-full">
                            </div>
                        </div>

                        <div id="mediaContent" class="hidden space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Upload Media
                                </label>
                                <input type="file" name="media_file" accept="image/*,video/*" class="w-full">
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                            Create Story
                        </button>
                    </form>
                </div>

                <!-- Stories Display -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mt-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Stories</h2>
                    
                    <!-- Story Avatars -->
                    <div class="flex space-x-4 overflow-x-auto pb-4">
                        <?php if (!empty($my_stories)): ?>
                        <!-- My Stories -->
                        <div class="flex-shrink-0 text-center">
                            <div class="relative">
                                <div class="w-16 h-16 rounded-full ring-2 ring-blue-500 p-1">
                                    <img src="uploads/avatars/<?php echo htmlspecialchars($_SESSION['avatar'] ?? 'default.png'); ?>" 
                                         class="w-full h-full rounded-full cursor-pointer story-avatar"
                                         data-stories='<?php echo json_encode($my_stories); ?>'
                                         onclick="showStories(this)">
                                </div>
                                <span class="absolute bottom-0 right-0 w-4 h-4 bg-blue-500 rounded-full border-2 border-white"></span>
                            </div>
                            <span class="text-sm text-gray-600 dark:text-gray-400 mt-1">Your Story</span>
                        </div>
                        <?php endif; ?>

                        <!-- Friends' Stories -->
                        <?php foreach ($friend_stories as $friend): ?>
                        <div class="flex-shrink-0 text-center">
                            <div class="relative">
                                <div class="w-16 h-16 rounded-full ring-2 ring-blue-500 p-1">
                                    <img src="uploads/avatars/<?php echo htmlspecialchars($friend['avatar'] ?? 'default.png'); ?>" 
                                         class="w-full h-full rounded-full cursor-pointer story-avatar"
                                         data-stories='<?php echo json_encode($friend['stories']); ?>'
                                         onclick="showStories(this)">
                                </div>
                                <?php if (!empty($friend['unseen_stories'])): ?>
                                <span class="absolute bottom-0 right-0 w-4 h-4 bg-blue-500 rounded-full border-2 border-white"></span>
                                <?php endif; ?>
                            </div>
                            <span class="text-sm text-gray-600 dark:text-gray-400 mt-1"><?php echo htmlspecialchars($friend['username']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Activity Feed -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Activity Feed</h2>
                    <div class="space-y-4">
                        <?php foreach ($activity_feed as $activity): ?>
                        <div class="flex items-start space-x-4">
                            <img src="uploads/avatars/<?php echo htmlspecialchars($activity['avatar']); ?>" 
                                 class="w-10 h-10 rounded-full">
                            <div class="flex-1">
                                <div class="text-sm">
                                    <span class="font-semibold text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($activity['username']); ?>
                                    </span>
                                    <?php
                                    $activityData = json_decode($activity['activity_data'], true);
                                    echo formatActivity($activity['activity_type'], $activityData);
                                    ?>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <?php echo timeAgo($activity['created_at']); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Friend Suggestions -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Suggested Friends</h2>
                    <div class="space-y-4">
                        <?php foreach ($friend_suggestions as $suggestion): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <img src="uploads/avatars/<?php echo htmlspecialchars($suggestion['avatar']); ?>" 
                                     class="w-10 h-10 rounded-full">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($suggestion['username']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?php
                                        $reason = json_decode($suggestion['reason'], true);
                                        echo formatSuggestionReason($reason);
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <button onclick="addFriend(<?php echo $suggestion['suggested_user_id']; ?>)"
                                    class="text-blue-500 hover:text-blue-600">
                                Add Friend
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Interest Groups -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Interest Groups</h2>
                    <div class="space-y-4">
                        <?php foreach ($interest_groups as $group): ?>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($group['name']); ?>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo $group['member_count']; ?> members
                                </div>
                            </div>
                            <button onclick="joinGroup(<?php echo $group['id']; ?>)"
                                    class="text-blue-500 hover:text-blue-600">
                                Join
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Story Viewer Modal -->
    <div id="storyModal" class="hidden fixed inset-0 bg-black bg-opacity-90 z-50">
        <div class="relative w-full h-full flex items-center justify-center">
            <button onclick="closeStoryModal()" class="absolute top-4 right-4 text-white text-xl">&times;</button>
            
            <div class="w-full max-w-2xl">
                <div id="storyContent" class="relative">
                    <!-- Story content will be inserted here -->
                </div>
                
                <div class="absolute bottom-4 left-4 right-4 text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <img id="storyUserAvatar" src="" alt="" class="w-10 h-10 rounded-full">
                            <span id="storyUsername" class="font-semibold"></span>
                        </div>
                        <div id="storyViewCount" class="flex items-center space-x-2 bg-gray-800 bg-opacity-50 px-3 py-1.5 rounded-full cursor-pointer hover:bg-opacity-70 transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            <span class="text-sm font-medium"></span>
                        </div>
                    </div>
                </div>
                
                <button onclick="prevStory()" class="absolute left-4 top-1/2 transform -translate-y-1/2 text-white text-4xl">&lt;</button>
                <button onclick="nextStory()" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-white text-4xl">&gt;</button>
            </div>
        </div>
    </div>

    <script>
    let currentStories = [];
    let currentStoryIndex = 0;
    let currentStoryId = null;
    let storyTimeout;

    function showStories(element) {
        const stories = JSON.parse(element.dataset.stories);
        if (stories && stories.length > 0) {
            currentStories = stories;
            currentStoryIndex = 0;
            showCurrentStory();
            document.getElementById('storyModal').classList.remove('hidden');
        }
    }

    function showCurrentStory() {
        if (!currentStories[currentStoryIndex]) return;
        
        const story = currentStories[currentStoryIndex];
        currentStoryId = story.id;
        
        // Update story content
        const content = document.getElementById('storyContent');
        if (story.story_type === 'image') {
            content.innerHTML = `<img src="${story.content}" class="max-h-[80vh] mx-auto">`;
        } else {
            content.innerHTML = `<div class="bg-gray-800 p-6 rounded-lg text-white text-center text-xl">${story.content}</div>`;
        }
        
        // Update user info
        document.getElementById('storyUsername').textContent = story.username;
        document.getElementById('storyUserAvatar').src = 'uploads/avatars/' + story.avatar;
        
        // Show/hide view count for story owner
        if (story.user_id === <?php echo $_SESSION['user_id']; ?>) {
            updateStoryViewCount();
            document.getElementById('storyViewCount').style.display = 'block';
        } else {
            document.getElementById('storyViewCount').style.display = 'none';
            // Record view
            fetch('record_story_view.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ story_id: story.id })
            });
        }
        
        // Clear previous timeout and set new one
        if (storyTimeout) clearTimeout(storyTimeout);
        storyTimeout = setTimeout(nextStory, 5000); // 5 seconds per story
    }
    
    function updateStoryViewCount() {
        if (!currentStoryId) return;
        
        fetch(`get_story_viewers.php?story_id=${currentStoryId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const viewCount = document.getElementById('storyViewCount');
                    viewCount.querySelector('span').textContent = `${data.total} view${data.total !== 1 ? 's' : ''}`;
                    viewCount.style.display = 'block';
                    viewCount.onclick = showStoryViewers;
                }
            });
    }
    
    function showStoryViewers() {
        if (!currentStoryId) return;
        
        fetch(`get_story_viewers.php?story_id=${currentStoryId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const viewersList = document.getElementById('storyViewersList');
                    viewersList.innerHTML = data.viewers.map(viewer => `
                        <div class="flex items-center space-x-3 py-2">
                            <img src="uploads/avatars/${viewer.avatar}" alt="" class="w-10 h-10 rounded-full">
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-white">${viewer.username}</div>
                                <div class="text-sm text-gray-500">${new Date(viewer.viewed_at).toLocaleString()}</div>
                            </div>
                        </div>
                    `).join('');
                    
                    document.getElementById('storyViewersModal').classList.remove('hidden');
                }
            });
    }
    
    function hideStoryViewers() {
        document.getElementById('storyViewersModal').classList.add('hidden');
    }
    
    function prevStory() {
        if (currentStoryIndex > 0) {
            currentStoryIndex--;
            showCurrentStory();
        }
    }

    function nextStory() {
        if (currentStoryIndex < currentStories.length - 1) {
            currentStoryIndex++;
            showCurrentStory();
        } else {
            closeStoryModal();
        }
    }

    function closeStoryModal() {
        document.getElementById('storyModal').classList.add('hidden');
        if (storyTimeout) clearTimeout(storyTimeout);
        currentStories = [];
        currentStoryIndex = 0;
        currentStoryId = null;
    }

    // Status update function
    document.getElementById('statusForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Disable form while submitting
        const submitButton = this.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'Updating...';
        
        fetch('api/update_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Server response:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            if (data.success) {
                location.reload(); // Reload to show updated status
            } else {
                throw new Error(data.message || 'Failed to update status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(error.message || 'Failed to update status');
        })
        .finally(() => {
            // Re-enable form
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        });
    });

    // Story Creation
    document.getElementById('storyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('api/create_story.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to create story');
            }
        });
    });

    // Friend Management
    function addFriend(userId) {
        fetch('api/add_friend.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ user_id: userId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to add friend');
            }
        });
    }

    // Group Management
    function joinGroup(groupId) {
        fetch('api/join_group.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ group_id: groupId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to join group');
            }
        });
    }

    // Emoji Picker
    document.getElementById('emojiPicker').addEventListener('click', function() {
        const picker = new EmojiMart.Picker({
            onSelect: emoji => {
                const statusInput = document.getElementById('statusText');
                statusInput.value += emoji.native;
            }
        });
        this.parentNode.insertBefore(picker, this.nextSibling);
    });

    // Story Type Switcher
    document.getElementById('storyType').addEventListener('change', function() {
        const textContent = document.getElementById('textContent');
        const mediaContent = document.getElementById('mediaContent');
        
        if (this.value === 'text') {
            textContent.classList.remove('hidden');
            mediaContent.classList.add('hidden');
        } else {
            textContent.classList.add('hidden');
            mediaContent.classList.remove('hidden');
        }
    });
    </script>
</body>
</html>
