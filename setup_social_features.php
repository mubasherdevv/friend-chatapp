<?php
require_once 'config/database.php';

// Create tables for social features
$queries = [
    // User Status Updates
    "CREATE TABLE IF NOT EXISTS user_status (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        status_text VARCHAR(255),
        emoji VARCHAR(50),
        visibility ENUM('public', 'friends', 'private') DEFAULT 'public',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // Stories/Status Feature
    "CREATE TABLE IF NOT EXISTS stories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        content_type ENUM('text', 'image', 'video') NOT NULL,
        content TEXT NOT NULL,
        background_color VARCHAR(7) NULL,
        font_style VARCHAR(50) NULL,
        visibility ENUM('public', 'friends', 'private') DEFAULT 'public',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 24 HOUR),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // Story Views
    "CREATE TABLE IF NOT EXISTS story_views (
        id INT PRIMARY KEY AUTO_INCREMENT,
        story_id INT NOT NULL,
        viewer_id INT NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
        FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // Interest Groups
    "CREATE TABLE IF NOT EXISTS interest_groups (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )",

    // User Interests
    "CREATE TABLE IF NOT EXISTS user_interests (
        user_id INT NOT NULL,
        interest_group_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, interest_group_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (interest_group_id) REFERENCES interest_groups(id) ON DELETE CASCADE
    )",

    // Activity Feed
    "CREATE TABLE IF NOT EXISTS activity_feed (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        activity_type ENUM('status_update', 'story_post', 'room_join', 'friend_add', 'group_join', 'achievement') NOT NULL,
        activity_data JSON,
        visibility ENUM('public', 'friends', 'private') DEFAULT 'public',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // Friend Suggestions
    "CREATE TABLE IF NOT EXISTS friend_suggestions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        suggested_user_id INT NOT NULL,
        score FLOAT DEFAULT 0,
        reason JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (suggested_user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // Add indexes
    "CREATE INDEX IF NOT EXISTS idx_user_status_user ON user_status(user_id)",
    "CREATE INDEX IF NOT EXISTS idx_stories_user ON stories(user_id)",
    "CREATE INDEX IF NOT EXISTS idx_story_views_story ON story_views(story_id)",
    "CREATE INDEX IF NOT EXISTS idx_activity_feed_user ON activity_feed(user_id)",
    "CREATE INDEX IF NOT EXISTS idx_friend_suggestions_user ON friend_suggestions(user_id)"
];

// Execute each query
foreach ($queries as $query) {
    try {
        if ($conn->query($query)) {
            echo "Success: " . substr($query, 0, 50) . "...\n";
        } else {
            echo "Error: " . $conn->error . "\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// Create some default interest groups
$default_groups = [
    ['Gaming', 'Connect with fellow gamers and discuss your favorite games'],
    ['Technology', 'Discuss the latest tech trends and innovations'],
    ['Music', 'Share and discover new music with others'],
    ['Movies & TV', 'Talk about your favorite shows and movies'],
    ['Sports', 'Connect with sports enthusiasts'],
    ['Art & Design', 'Share your creative work and get inspired'],
    ['Books & Reading', 'Discuss literature and share book recommendations'],
    ['Food & Cooking', 'Share recipes and cooking tips'],
    ['Travel', 'Share travel experiences and get destination recommendations'],
    ['Fitness & Health', 'Discuss fitness tips and health-related topics']
];

// Insert default groups if they don't exist
$stmt = $conn->prepare("INSERT IGNORE INTO interest_groups (name, description, created_by) VALUES (?, ?, 1)");
foreach ($default_groups as $group) {
    try {
        $stmt->bind_param("ss", $group[0], $group[1]);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            echo "Created interest group: " . $group[0] . "\n";
        }
    } catch (Exception $e) {
        echo "Error creating group " . $group[0] . ": " . $e->getMessage() . "\n";
    }
}

echo "\nSocial features setup completed!\n";
