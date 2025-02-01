<?php
require_once 'config/database.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Function to award a new achievement to a user
function awardAchievement($conn, $userId, $awardType) {
    $stmt = $conn->prepare("
        INSERT INTO user_awards (user_id, award_type)
        VALUES (?, ?)
    ");
    $stmt->bind_param('is', $userId, $awardType);
    return $stmt->execute();
}

// Check for new achievements
function checkAndAwardAchievements($conn, $userId) {
    // Get user's message count
    $stmt = $conn->prepare("SELECT COUNT(*) as message_count FROM messages WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $messageCount = $result->fetch_assoc()['message_count'];
    
    // Message count achievements
    $messageAchievements = [
        ['count' => 1, 'type' => 'first_message'],
        ['count' => 100, 'type' => 'chatty'],
        ['count' => 1000, 'type' => 'chat_master']
    ];
    
    foreach ($messageAchievements as $achievement) {
        if ($messageCount >= $achievement['count']) {
            // Check if user already has this achievement
            $checkStmt = $conn->prepare("SELECT id FROM user_awards WHERE user_id = ? AND award_type = ?");
            $checkStmt->bind_param('is', $userId, $achievement['type']);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows === 0) {
                // Award new achievement
                awardAchievement($conn, $userId, $achievement['type']);
            }
        }
    }
    
    // Add more achievement checks here in the future
    // For example: login streaks, reactions received, etc.
}

// Get user's awards
$stmt = $conn->prepare("
    SELECT DISTINCT
        ua.award_type,
        ua.awarded_at,
        u.username,
        u.avatar
    FROM user_awards ua
    JOIN users u ON ua.user_id = u.id
    WHERE ua.user_id = ?
    GROUP BY ua.award_type
    ORDER BY ua.awarded_at DESC
");

$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$user_awards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get global statistics with distinct awards
$stmt = $conn->prepare("
    SELECT 
        award_type,
        COUNT(DISTINCT user_id) as count,
        MAX(awarded_at) as last_awarded
    FROM user_awards
    GROUP BY award_type
");
$stmt->execute();
$award_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check for new achievements when viewing rewards page
checkAndAwardAchievements($conn, $_SESSION['user_id']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rewards & Achievements</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #6c63ff;
            --success-color: #2ecc71;
            --warning-color: #f1c40f;
            --danger-color: #e74c3c;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --background: #f8f9fa;
            --card-background: #ffffff;
            --border-radius: 16px;
            --box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--background);
            color: var(--text-primary);
            line-height: 1.6;
            margin: 0;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .page-header h1 {
            font-size: 3em;
            margin: 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .page-header p {
            font-size: 1.2em;
            margin: 1rem 0 0;
            opacity: 0.9;
        }

        .nav-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            background: white;
            color: var(--primary-color);
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: var(--transition);
            box-shadow: var(--box-shadow);
            margin: 1rem 0;
        }

        .nav-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
        }

        .rewards-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .rewards-sidebar {
            background: var(--card-background);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            position: sticky;
            top: 2rem;
            height: fit-content;
        }

        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .category-filter {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            margin-bottom: 2rem;
        }

        .filter-button {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem 1.2rem;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            text-align: left;
            font-size: 1rem;
        }

        .filter-button:hover,
        .filter-button.active {
            background: rgba(74, 144, 226, 0.1);
            color: var(--primary-color);
        }

        .reward-card {
            background: var(--card-background);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            gap: 1rem;
            position: relative;
            overflow: hidden;
        }

        .reward-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
        }

        .reward-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .reward-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .reward-icon::after {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 50%;
            background: inherit;
            filter: blur(8px);
            opacity: 0.4;
            z-index: -1;
        }

        .reward-card.achievement .reward-icon {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .reward-card.champion .reward-icon {
            background: linear-gradient(135deg, #f1c40f, #f39c12);
            color: white;
        }

        .reward-card.special .reward-icon {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
        }

        .reward-card.time .reward-icon {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }

        .reward-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .reward-description {
            color: var(--text-secondary);
            font-size: 1rem;
            flex-grow: 1;
        }

        .reward-stats {
            display: flex;
            justify-content: space-between;
            padding-top: 1rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .reward-stats span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .your-achievements {
            background: var(--card-background);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 3rem;
            box-shadow: var(--box-shadow);
        }

        .your-achievements h2 {
            margin-top: 0;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
            font-size: 1.8rem;
        }

        .achievement-list {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .achievement-badge {
            padding: 0.8rem 1.2rem;
            border-radius: 100px;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 0.9rem;
            color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }

        .achievement-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        @media (max-width: 1200px) {
            .rewards-container {
                grid-template-columns: 1fr;
            }

            .rewards-sidebar {
                position: static;
                margin-bottom: 2rem;
            }

            .category-filter {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
                margin-bottom: 2rem;
            }

            .page-header h1 {
                font-size: 2em;
            }

            .rewards-grid {
                grid-template-columns: 1fr;
            }

            .achievement-list {
                gap: 0.8rem;
            }

            .achievement-badge {
                padding: 0.6rem 1rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="leaderboard.php" class="nav-link">
            <i class="fas fa-arrow-left"></i> Back to Leaderboard
        </a>

        <div class="page-header">
            <h1>Rewards & Achievements</h1>
            <p>Unlock achievements and earn special rewards through active participation!</p>
        </div>
<!-- Show Arc -->
        <?php if (!empty($user_awards)): ?>
        <div class="your-achievements">
            <h2><i class="fas fa-trophy"></i> Your Achievements</h2>
            <div class="achievement-list">
                <?php 
                // Sort awards by awarded_at date in descending order
                usort($user_awards, function($a, $b) {
                    return strtotime($b['awarded_at']) - strtotime($a['awarded_at']);
                });
                
                foreach ($user_awards as $award): 
                ?>
                    <div class="achievement-badge" style="background: <?php
                        echo match(true) {
                            str_contains($award['award_type'], 'champion') => 'linear-gradient(135deg, #f1c40f, #f39c12)',
                            str_contains($award['award_type'], 'achievement') => 'linear-gradient(135deg, #3498db, #2980b9)',
                            in_array($award['award_type'], ['rising_star', 'chat_master', 'conversation_starter', 'popular_chatter']) => 'linear-gradient(135deg, #9b59b6, #8e44ad)',
                            default => 'linear-gradient(135deg, #2ecc71, #27ae60)'
                        };
                    ?>">
                        <i class="fas fa-<?php
                            echo match($award['award_type']) {
                                'daily_champion' => 'sun',
                                'weekly_champion' => 'calendar-week',
                                'monthly_champion' => 'calendar',
                                'first_message' => 'comment',
                                'bronze_achieved' => 'shield-alt',
                                'silver_achieved' => 'award',
                                'gold_achieved' => 'medal',
                                'elite_achieved' => 'crown',
                                'rising_star' => 'rocket',
                                'consistent_contributor' => 'calendar-check',
                                'chat_master' => 'comment-dots',
                                'early_bird' => 'sun',
                                'night_owl' => 'moon',
                                'weekend_warrior' => 'fighter-jet',
                                'conversation_starter' => 'comments',
                                'popular_chatter' => 'heart',
                                default => 'trophy'
                            };
                        ?>"></i>
                        <?php echo $award['award_type']; ?>
                        <span style="font-size: 0.8em; opacity: 0.9;">
                            <?php echo date('M j, Y', strtotime($award['awarded_at'])); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="rewards-container">
            <div class="rewards-sidebar">
                <div class="category-filter">
                    <button class="filter-button active" data-category="all">
                        <i class="fas fa-layer-group"></i>
                        All Rewards
                    </button>
                    <button class="filter-button" data-category="achievement">
                        <i class="fas fa-award"></i>
                        Achievements
                    </button>
                    <button class="filter-button" data-category="champion">
                        <i class="fas fa-trophy"></i>
                        Champions
                    </button>
                    <button class="filter-button" data-category="special">
                        <i class="fas fa-star"></i>
                        Special
                    </button>
                    <button class="filter-button" data-category="time">
                        <i class="fas fa-clock"></i>
                        Time-Based
                    </button>
                </div>
            </div>

            <div class="rewards-grid">
                <?php
                $rewards = [
                    [
                        'type' => 'achievement',
                        'title' => 'First Message',
                        'description' => 'Send your first message in any chat room',
                        'icon' => 'comment'
                    ],
                    [
                        'type' => 'achievement',
                        'title' => 'Bronze Achieved',
                        'description' => 'Reach 50+ total messages',
                        'icon' => 'shield-alt'
                    ],
                    [
                        'type' => 'achievement',
                        'title' => 'Silver Achieved',
                        'description' => 'Reach 100+ total messages',
                        'icon' => 'award'
                    ],
                    [
                        'type' => 'achievement',
                        'title' => 'Gold Achieved',
                        'description' => 'Reach 500+ total messages',
                        'icon' => 'medal'
                    ],
                    [
                        'type' => 'achievement',
                        'title' => 'Elite Achieved',
                        'description' => 'Reach 1000+ total messages',
                        'icon' => 'crown'
                    ],
                    [
                        'type' => 'champion',
                        'title' => 'Daily Champion',
                        'description' => 'Send the most messages in a single day',
                        'icon' => 'sun'
                    ],
                    [
                        'type' => 'champion',
                        'title' => 'Weekly Champion',
                        'description' => 'Send the most messages in a week',
                        'icon' => 'calendar-week'
                    ],
                    [
                        'type' => 'champion',
                        'title' => 'Monthly Champion',
                        'description' => 'Send the most messages in a month',
                        'icon' => 'calendar'
                    ],
                    [
                        'type' => 'special',
                        'title' => 'Rising Star',
                        'description' => 'Achieve rapid rank progression within a week',
                        'icon' => 'rocket'
                    ],
                    [
                        'type' => 'special',
                        'title' => 'Chat Master',
                        'description' => 'Reach 10,000+ total messages',
                        'icon' => 'comment-dots'
                    ],
                    [
                        'type' => 'time',
                        'title' => 'Early Bird',
                        'description' => 'Be the first to send a message between 5 AM and 9 AM',
                        'icon' => 'sun'
                    ],
                    [
                        'type' => 'time',
                        'title' => 'Night Owl',
                        'description' => 'Be most active between 10 PM and 4 AM',
                        'icon' => 'moon'
                    ],
                    [
                        'type' => 'time',
                        'title' => 'Weekend Warrior',
                        'description' => 'Be most active during weekends',
                        'icon' => 'fighter-jet'
                    ],
                    [
                        'type' => 'time',
                        'title' => 'Consistent Contributor',
                        'description' => 'Send messages every day for a week',
                        'icon' => 'calendar-check'
                    ]
                ];

                foreach ($rewards as $reward):
                    $stats = array_filter($award_stats, fn($stat) => $stat['award_type'] === strtolower($reward['title']));
                    $stat = !empty($stats) ? reset($stats) : ['count' => 0, 'last_awarded' => null];
                ?>
                    <div class="reward-card <?php echo $reward['type']; ?>">
                        <div class="reward-icon">
                            <i class="fas fa-<?php echo $reward['icon']; ?>"></i>
                        </div>
                        <div class="reward-title"><?php echo $reward['title']; ?></div>
                        <div class="reward-description"><?php echo $reward['description']; ?></div>
                        <div class="reward-stats">
                            <span>
                                <i class="fas fa-users"></i>
                                <?php echo number_format($stat['count']); ?> awarded
                            </span>
                            <?php if ($stat['last_awarded']): ?>
                            <span>
                                <i class="fas fa-clock"></i>
                                Last: <?php echo date('M j', strtotime($stat['last_awarded'])); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.filter-button');
            const rewardCards = document.querySelectorAll('.reward-card');

            filterButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const category = button.dataset.category;

                    // Update active button
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');

                    // Filter cards
                    rewardCards.forEach(card => {
                        if (category === 'all' || card.classList.contains(category)) {
                            card.style.display = 'flex';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>
