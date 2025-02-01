<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$story_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$story_id) {
    die('Story ID is required');
}

try {
    // Get story details with user information
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            u.username,
            u.avatar,
            COUNT(DISTINCT sv.viewer_id) as view_count,
            GROUP_CONCAT(DISTINCT vu.username ORDER BY sv.viewed_at DESC SEPARATOR '|') as viewers
        FROM stories s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN story_views sv ON s.id = sv.story_id
        LEFT JOIN users vu ON sv.viewer_id = vu.id
        WHERE s.id = ?
        AND (s.expires_at IS NULL OR s.expires_at > NOW())
        GROUP BY s.id
    ");
    
    $stmt->bind_param("i", $story_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $story = $result->fetch_assoc();

    if (!$story) {
        die('Story not found or has expired');
    }

    // Record view if not the story owner
    if ($story['user_id'] !== $current_user_id) {
        $stmt = $conn->prepare("
            INSERT IGNORE INTO story_views (story_id, viewer_id)
            VALUES (?, ?)
        ");
        $stmt->bind_param("ii", $story_id, $current_user_id);
        $stmt->execute();
    }

} catch (Exception $e) {
    die("An error occurred: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Story - <?php echo htmlspecialchars($story['username']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .story-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .story-content {
            position: relative;
            max-width: 90%;
            max-height: 90vh;
            background-color: #000;
            border-radius: 8px;
            overflow: hidden;
        }

        .story-header {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            padding: 1rem;
            background: linear-gradient(rgba(0,0,0,0.7), transparent);
            color: white;
            z-index: 10;
            display: flex;
            align-items: center;
        }

        .story-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1rem;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            color: white;
        }

        .close-button {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s;
        }

        .close-button:hover {
            background: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body class="bg-black">
    <div class="story-container">
        <div class="story-content">
            <div class="story-header">
                <img src="uploads/avatars/<?php echo htmlspecialchars($story['avatar']); ?>" 
                     alt="<?php echo htmlspecialchars($story['username']); ?>" 
                     class="w-10 h-10 rounded-full object-cover mr-3">
                <div>
                    <div class="font-semibold"><?php echo htmlspecialchars($story['username']); ?></div>
                    <div class="text-sm opacity-75">
                        <?php echo date('F j, g:i a', strtotime($story['created_at'])); ?>
                    </div>
                </div>
                <a href="profile.php" class="close-button">×</a>
            </div>

            <div class="story-content-inner">
                <?php if ($story['content_type'] === 'text'): ?>
                    <div class="text-content p-8" style="background-color: <?php echo $story['background_color'] ?? '#000000'; ?>">
                        <p class="text-white text-xl" style="font-family: <?php echo $story['font_style'] ?? 'Arial'; ?>">
                            <?php echo nl2br(htmlspecialchars($story['content'])); ?>
                        </p>
                    </div>
                <?php elseif ($story['content_type'] === 'image'): ?>
                    <img src="<?php echo htmlspecialchars($story['content']); ?>" 
                         alt="Story content" 
                         class="max-w-full h-auto">
                <?php endif; ?>
            </div>

            <div class="story-footer">
                <div class="view-count text-sm">
                    <?php echo $story['view_count']; ?> view<?php echo $story['view_count'] !== '1' ? 's' : ''; ?>
                </div>
                <?php if (!empty($story['viewers'])): ?>
                    <div class="viewers text-sm mt-1">
                        <?php
                        $viewers = explode('|', $story['viewers']);
                        echo 'Seen by: ' . implode(', ', array_slice($viewers, 0, 3));
                        if (count($viewers) > 3) {
                            echo ' and ' . (count($viewers) - 3) . ' more';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Close story and return to profile when clicking outside
        document.querySelector('.story-container').addEventListener('click', function(e) {
            if (e.target === this) {
                window.location.href = 'profile.php';
            }
        });

        // Close story when pressing ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.location.href = 'profile.php';
            }
        });
    </script>
</body>
</html>
