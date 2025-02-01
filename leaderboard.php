<?php
require_once 'config/database.php';
session_start();

function getProfilePicture($avatar) {
    $defaultAvatar = 'images/default-avatar.png';
    
    if (empty($avatar)) {
        return $defaultAvatar;
    }
    
    $avatarPath = 'uploads/avatars/' . htmlspecialchars($avatar);
    if (file_exists($avatarPath)) {
        return $avatarPath;
    }
    
    return $defaultAvatar;
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get the time period from query parameter (default to daily)
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';
$valid_periods = ['daily', 'weekly', 'monthly'];
if (!in_array($period, $valid_periods)) {
    $period = 'daily';
}

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1

// Get total number of users for pagination
$count_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) as total_users
    FROM users u
    LEFT JOIN message_stats ms ON u.id = ms.user_id
");
$count_stmt->execute();
$total_users = $count_stmt->get_result()->fetch_assoc()['total_users'];
$total_pages = ceil($total_users / $items_per_page);
$page = min($page, $total_pages); // Ensure page doesn't exceed total pages

$offset = ($page - 1) * $items_per_page;

// Get leaderboard data with pagination
$count_column = $period . '_count';
$stmt = $conn->prepare("
    SELECT 
        u.id, 
        u.username, 
        u.avatar, 
        u.is_admin,
        COALESCE(ms.$count_column, 0) as message_count,
        CASE 
            WHEN ms.$count_column > 0 THEN RANK() OVER (ORDER BY ms.$count_column DESC)
            ELSE NULL 
        END as rank_num,
        ur.rank_name as rank_status,
        ur.total_messages,
        GROUP_CONCAT(
            DISTINCT CONCAT(ua.award_type, '|', ua.awarded_at)
            ORDER BY ua.awarded_at DESC
            SEPARATOR ','
        ) as awards_data
    FROM users u
    LEFT JOIN message_stats ms ON u.id = ms.user_id
    LEFT JOIN user_ranks ur ON u.id = ur.user_id
    LEFT JOIN (
        SELECT DISTINCT user_id, award_type, MAX(awarded_at) as awarded_at
        FROM user_awards
        GROUP BY user_id, award_type
    ) ua ON u.id = ua.user_id
    GROUP BY u.id
    ORDER BY 
        CASE WHEN ms.$count_column IS NULL THEN 1 ELSE 0 END,
        ms.$count_column DESC,
        u.username ASC
    LIMIT ? OFFSET ?
");
$stmt->bind_param('ii', $items_per_page, $offset);
$stmt->execute();
$leaderboard = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get current user's stats with additional information
$stmt = $conn->prepare("
    SELECT 
        u.id, u.username, u.avatar, u.is_admin, 
        ms.daily_count, ms.weekly_count, ms.monthly_count,
        RANK() OVER (ORDER BY ms.daily_count DESC) as daily_rank,
        RANK() OVER (ORDER BY ms.weekly_count DESC) as weekly_rank,
        RANK() OVER (ORDER BY ms.monthly_count DESC) as monthly_rank,
        (SELECT COUNT(*) FROM messages WHERE user_id = u.id) as total_messages,
        (SELECT COUNT(*) FROM messages WHERE user_id = u.id AND DATE(created_at) = CURDATE()) as today_messages,
        (SELECT COUNT(*) FROM messages WHERE user_id = u.id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as last_week_messages,
        (SELECT COUNT(*) FROM messages WHERE user_id = u.id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as last_month_messages
    FROM users u
    LEFT JOIN message_stats ms ON u.id = ms.user_id
    WHERE u.id = ?
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();

// Calculate progress percentages
$progress = [
    'daily' => [
        'current' => $current_user['daily_count'],
        'previous' => $current_user['today_messages'],
        'change' => $current_user['today_messages'] > 0 ? 
            (($current_user['daily_count'] - $current_user['today_messages']) / $current_user['today_messages'] * 100) : 0
    ],
    'weekly' => [
        'current' => $current_user['weekly_count'],
        'previous' => $current_user['last_week_messages'],
        'change' => $current_user['last_week_messages'] > 0 ? 
            (($current_user['weekly_count'] - $current_user['last_week_messages']) / $current_user['last_week_messages'] * 100) : 0
    ],
    'monthly' => [
        'current' => $current_user['monthly_count'],
        'previous' => $current_user['last_month_messages'],
        'change' => $current_user['last_month_messages'] > 0 ? 
            (($current_user['monthly_count'] - $current_user['last_month_messages']) / $current_user['last_month_messages'] * 100) : 0
    ]
];

// Get user's position relative to next rank
$stmt = $conn->prepare("
    SELECT MIN($count_column) as next_milestone
    FROM message_stats
    WHERE $count_column > ?
");
$current_count = $current_user[$period . '_count'];
$stmt->bind_param('i', $current_count);
$stmt->execute();
$next_milestone = $stmt->get_result()->fetch_assoc()['next_milestone'];
$messages_to_next_rank = $next_milestone ? ($next_milestone - $current_count) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Leaderboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #f39c12;
            --background-color: #f5f6fa;
            --card-background: #ffffff;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --gold: #f1c40f;
            --silver: #bdc3c7;
            --bronze: #cd7f32;
        }

        body {
            background-color: var(--background-color);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        .leaderboard-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 15px;
            background: var(--card-background);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .leaderboard-container {
                margin: 10px;
                padding: 10px;
                border-radius: 15px;
            }

            .leaderboard-header h1 {
                font-size: 1.8em !important;
            }

            .period-selector {
                flex-wrap: wrap;
            }

            .period-selector a {
                padding: 8px 16px !important;
                font-size: 0.9em;
            }
        }

        .leaderboard-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
            overflow-x: auto;
            display: block;
        }

        @media (max-width: 768px) {
            .leaderboard-table tbody tr {
                display: flex;
                flex-wrap: wrap;
                padding: 10px;
                margin-bottom: 10px;
                background: #f8f9fa;
                border-radius: 10px;
                position: relative;
            }

            .leaderboard-table td {
                padding: 5px !important;
            }

            .leaderboard-table td.rank {
                position: absolute;
                top: 5px;
                right: 5px;
                background: rgba(0,0,0,0.05);
                border-radius: 50%;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.9em;
            }

            .leaderboard-table td.user-cell {
                width: 100%;
                display: flex;
                align-items: center;
                padding-right: 40px !important;
            }

            .user-avatar {
                width: 40px !important;
                height: 40px !important;
            }

            .message-count {
                width: 100%;
                text-align: right !important;
                color: var(--primary-color);
                font-weight: bold;
                padding-top: 10px !important;
                border-top: 1px solid rgba(0,0,0,0.1);
                margin-top: 5px;
            }

            .awards-container {
                margin-top: 5px;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 5px;
            }

            .award-badge {
                font-size: 0.8em;
                padding: 4px 8px !important;
            }
        }

        /* Pagination Mobile Styles */
        @media (max-width: 768px) {
            .pagination {
                flex-wrap: wrap;
                gap: 10px;
            }

            .page-numbers {
                width: 100%;
                justify-content: center;
                order: 1;
            }

            .page-link {
                padding: 6px 12px;
                font-size: 0.9em;
            }

            .pagination a[href*="Previous"],
            .pagination a[href*="Next"] {
                width: calc(50% - 5px);
                justify-content: center;
                order: 2;
            }

            .page-ellipsis {
                display: none;
            }

            /* Show fewer page numbers on mobile */
            .page-numbers a:not(.active):not(:first-child):not(:last-child) {
                display: none;
            }
        }

        /* Current User Stats Mobile Styles */
        @media (max-width: 768px) {
            .current-user-stats {
                grid-template-columns: 1fr !important;
                gap: 15px;
                padding: 15px;
            }

            .user-profile {
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 10px;
            }

            .stat-card {
                padding: 10px !important;
            }

            .stat-value {
                font-size: 1.2em !important;
            }
        }

        /* Awards Summary Mobile Styles */
        @media (max-width: 768px) {
            .awards-summary {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                margin-top: 5px;
            }

            .award-count {
                font-size: 0.8em;
                padding: 2px 6px;
            }
        }

        :root {
            --primary-color: #4a90e2;
            --secondary-color: #f39c12;
            --background-color: #f5f6fa;
            --card-background: #ffffff;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --gold: #f1c40f;
            --silver: #bdc3c7;
            --bronze: #cd7f32;
        }

        body {
            background-color: var(--background-color);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .leaderboard-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 30px;
            background: var(--card-background);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .leaderboard-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .leaderboard-header h1 {
            font-size: 2.5em;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .period-selector {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 30px 0;
        }

        .period-selector a {
            padding: 12px 24px;
            border-radius: 30px;
            text-decoration: none;
            color: var(--text-primary);
            background: #eee;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .period-selector a:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .period-selector a.active {
            background: var(--primary-color);
            color: white;
        }

        .current-user-stats {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 50%, #ec4899 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 30px;
            position: relative;
            overflow: hidden;
        }

        .current-user-stats::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
            pointer-events: none;
        }

        .user-avatar-section {
            text-align: center;
        }

        .user-avatar-section img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255,255,255,0.3);
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }

        .user-avatar-section img:hover {
            transform: scale(1.05);
        }

        .user-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }

        .stat-title {
            font-size: 0.9em;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-change {
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stat-change.positive {
            color: #4ade80;
        }

        .stat-change.negative {
            color: #f87171;
        }

        .progress-bar {
            height: 6px;
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
            margin-top: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: rgba(255,255,255,0.8);
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .next-milestone {
            font-size: 0.9em;
            margin-top: 5px;
            opacity: 0.9;
        }

        .total-stats {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.2);
            display: flex;
            justify-content: space-between;
        }

        .total-stat {
            text-align: center;
        }

        .total-stat-value {
            font-size: 1.4em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .total-stat-label {
            font-size: 0.8em;
            opacity: 0.9;
        }

        .leaderboard-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .leaderboard-table tr {
            background: #f8f9fa;
            transition: transform 0.2s ease;
        }

        .leaderboard-table tr:hover {
            transform: translateX(5px);
            background: #f1f3f5;
        }

        .leaderboard-table th {
            padding: 15px;
            text-align: left;
            color: var(--text-secondary);
            font-weight: 600;
            border-bottom: 2px solid var(--primary-color);
        }

        .leaderboard-table td {
            padding: 15px;
            background: transparent;
        }

        .rank {
            font-weight: bold;
            font-size: 1.2em;
            color: var(--text-secondary);
            width: 50px;
        }

        .rank-1 .rank { color: var(--gold); }
        .rank-2 .rank { color: var(--silver); }
        .rank-3 .rank { color: var(--bronze); }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .user-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .admin-badge {
            background: #e74c3c;
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.8em;
            margin-left: 10px;
        }

        .message-count {
            font-weight: 600;
            color: var(--primary-color);
        }

        .trophy-icon {
            margin-right: 10px;
            font-size: 1.2em;
        }

        .rank-1 .trophy-icon { color: var(--gold); }
        .rank-2 .trophy-icon { color: var(--silver); }
        .rank-3 .trophy-icon { color: var(--bronze); }

        .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .rank-status {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .rank-status i {
            font-size: 0.9em;
        }
        
        .rank-status.Elite {
            background: linear-gradient(135deg, #8E2DE2, #4A00E0);
            color: white;
        }
        
        .rank-status.Gold {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: white;
        }
        
        .rank-status.Silver {
            background: linear-gradient(135deg, #C0C0C0, #808080);
            color: white;
        }
        
        .rank-status.Bronze {
            background: linear-gradient(135deg, #CD7F32, #8B4513);
            color: white;
        }
        
        .rank-status.Active {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
        }
        
        .rank-status.New {
            background: linear-gradient(135deg, #607d8b, #455a64);
            color: white;
        }

        .rank-progress {
            width: 100%;
            height: 6px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
            margin-top: 4px;
            overflow: hidden;
        }

        .rank-progress-bar {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .rank-info {
            font-size: 0.8em;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .no-rank {
            color: var(--text-secondary);
            font-style: italic;
        }

        .avatar-preview {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            z-index: 1000;
            background: white;
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }

        .avatar-preview.show {
            transform: translate(-50%, -50%) scale(1);
        }

        .avatar-preview img {
            max-width: 300px;
            max-height: 300px;
            border-radius: 10px;
            object-fit: cover;
        }

        .avatar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }

        .avatar-overlay.show {
            display: block;
        }

        .awards-container {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            max-width: 100%;
        }

        .award-badge {
            font-size: 0.75em;
            padding: 4px 10px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s ease;
            position: relative;
            cursor: help;
        }

        .award-badge i {
            font-size: 1.1em;
        }

        .award-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .award-badge.champion {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .award-badge.achievement {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-color-light));
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .award-badge.special {
            background: linear-gradient(135deg, #8E2DE2, #4A00E0);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .award-badge.time {
            background: linear-gradient(135deg, #00BCD4, #007A8C);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .award-details {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%) scale(0);
            padding: 8px 12px;
            background: rgba(0,0,0,0.9);
            color: white;
            border-radius: 6px;
            font-size: 0.9em;
            white-space: nowrap;
            z-index: 1000;
            transition: transform 0.2s ease;
            pointer-events: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .award-details .date {
            font-size: 0.8em;
            color: rgba(255,255,255,0.7);
            margin-top: 4px;
        }

        .award-badge:hover .award-details {
            transform: translateX(-50%) scale(1);
        }

        .awards-summary {
            font-size: 0.8em;
            color: var(--text-secondary);
            margin-top: 4px;
            display: flex;
            gap: 8px;
        }

        .awards-summary span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 30px 0;
            gap: 15px;
        }

        .page-numbers {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background: var(--card-background);
            color: var(--text-primary);
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }

        .page-link:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .page-link.active {
            background: var(--primary-color);
            color: white;
            font-weight: bold;
        }

        .page-ellipsis {
            color: var(--text-secondary);
            padding: 0 4px;
        }

        .pagination i {
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="leaderboard-container">
        <div class="leaderboard-header">
            <h1>Message Leaderboard</h1>
            <p>See who's leading the conversation!</p>
        </div>

        <?php if ($current_user): ?>
        <div class="current-user-stats">
            <div class="user-avatar-section">
                <img src="<?php echo getProfilePicture($current_user['avatar']); ?>" alt="Your Avatar" class="profile-avatar" data-avatar="true">
                <h2><?php echo htmlspecialchars($current_user['username']); ?></h2>
                <?php if ($current_user['is_admin']): ?>
                    <span class="admin-badge"><i class="fas fa-star"></i> Admin</span>
                <?php endif; ?>
            </div>
            
            <div class="user-stats-content">
                <div class="user-stats-grid">
                    <div class="stat-card">
                        <div class="stat-title">Daily Rank</div>
                        <div class="stat-value">#<?php echo $current_user['daily_rank']; ?></div>
                        <div class="stat-change <?php echo $progress['daily']['change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-<?php echo $progress['daily']['change'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo abs(round($progress['daily']['change'])); ?>% from yesterday
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, ($current_user['daily_count'] / max(1, $next_milestone)) * 100); ?>%"></div>
                        </div>
                        <?php if ($messages_to_next_rank > 0): ?>
                            <div class="next-milestone"><?php echo $messages_to_next_rank; ?> messages to next rank</div>
                        <?php endif; ?>
                    </div>

                    <div class="stat-card">
                        <div class="stat-title">Weekly Performance</div>
                        <div class="stat-value">#<?php echo $current_user['weekly_rank']; ?></div>
                        <div class="stat-change <?php echo $progress['weekly']['change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-<?php echo $progress['weekly']['change'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo abs(round($progress['weekly']['change'])); ?>% from last week
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, ($current_user['weekly_count'] / ($current_user['last_week_messages'] ?: 1)) * 100); ?>%"></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-title">Monthly Activity</div>
                        <div class="stat-value">#<?php echo $current_user['monthly_rank']; ?></div>
                        <div class="stat-change <?php echo $progress['monthly']['change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-<?php echo $progress['monthly']['change'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo abs(round($progress['monthly']['change'])); ?>% from last month
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, ($current_user['monthly_count'] / ($current_user['last_month_messages'] ?: 1)) * 100); ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="total-stats">
                    <div class="total-stat">
                        <div class="total-stat-value"><?php echo number_format((float)($current_user['total_messages'] ?? 0)); ?></div>
                        <div class="total-stat-label">Total Messages</div>
                    </div>
                    <div class="total-stat">
                        <div class="total-stat-value"><?php echo number_format((float)($current_user['daily_count'] ?? 0)); ?></div>
                        <div class="total-stat-label">Today's Messages</div>
                    </div>
                    <div class="total-stat">
                        <div class="total-stat-value"><?php echo number_format((float)($current_user['weekly_count'] ?? 0)); ?></div>
                        <div class="total-stat-label">This Week</div>
                    </div>
                    <div class="total-stat">
                        <div class="total-stat-value"><?php echo number_format((float)($current_user['monthly_count'] ?? 0)); ?></div>
                        <div class="total-stat-label">This Month</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="period-selector">
            <a href="?period=daily" <?php echo $period === 'daily' ? 'class="active"' : ''; ?>>
                <i class="fas fa-calendar-day"></i> Today
            </a>
            <a href="?period=weekly" <?php echo $period === 'weekly' ? 'class="active"' : ''; ?>>
                <i class="fas fa-calendar-week"></i> This Week
            </a>
            <a href="?period=monthly" <?php echo $period === 'monthly' ? 'class="active"' : ''; ?>>
                <i class="fas fa-calendar-alt"></i> This Month
            </a>
        </div>

        <?php if (empty($leaderboard)): ?>
            <div class="no-messages">
                <i class="fas fa-comment-slash"></i>
                <p>No messages sent during this period yet.</p>
            </div>
        <?php else: ?>
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>User</th>
                        <th>Messages</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaderboard as $index => $user): ?>
                        <tr class="rank-<?php echo $index + 1; ?>">
                            <td class="rank">
                                <?php if ($user['rank_num']): ?>
                                    <?php if ($index < 3): ?>
                                        <i class="fas fa-trophy trophy-icon"></i>
                                    <?php endif; ?>
                                    #<?php echo $user['rank_num']; ?>
                                <?php else: ?>
                                    <span class="no-rank">Unranked</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="user-cell">
                                    <img src="<?php echo getProfilePicture($user['avatar']); ?>" 
                                         alt="Avatar" 
                                         class="user-avatar"
                                         data-avatar="true">
                                    <div>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="admin-badge"><i class="fas fa-star"></i> Admin</span>
                                        <?php endif; ?>
                                        <span class="rank-status <?php echo $user['rank_status']; ?>">
                                            <?php
                                            $icon = match($user['rank_status']) {
                                                'Elite' => 'crown',
                                                'Gold' => 'medal',
                                                'Silver' => 'award',
                                                'Bronze' => 'shield-alt',
                                                'Active' => 'user-check',
                                                default => 'user'
                                            };
                                            ?>
                                            <i class="fas fa-<?php echo $icon; ?>"></i>
                                            <?php echo $user['rank_status']; ?>
                                        </span>
                                        
                                        <?php if (!empty($user['awards_data'])): ?>
                                        <div class="awards-container">
                                            <?php
                                            if (!empty($user['awards_data'])) {
                                                $awards = array_unique(explode(',', $user['awards_data']));
                                                $award_counts = [
                                                    'champion' => 0,
                                                    'achievement' => 0,
                                                    'special' => 0
                                                ];
                                                
                                                $processed_awards = [];
                                                foreach ($awards as $award_data) {
                                                    if (empty($award_data)) continue;
                                                    
                                                    list($award_type, $awarded_at) = explode('|', $award_data);
                                                    
                                                    // Skip if we've already processed this award type
                                                    if (in_array($award_type, $processed_awards)) continue;
                                                    $processed_awards[] = $award_type;
                                                    
                                                    // Get award details based on type
                                                    $details = match(true) {
                                                        str_contains($award_type, 'champion') => [
                                                            'icon' => 'trophy',
                                                            'class' => 'champion',
                                                            'group' => 'champion'
                                                        ],
                                                        str_contains($award_type, 'achievement') => [
                                                            'icon' => 'medal',
                                                            'class' => 'achievement',
                                                            'group' => 'achievement'
                                                        ],
                                                        default => [
                                                            'icon' => 'star',
                                                            'class' => 'special',
                                                            'group' => 'special'
                                                        ]
                                                    };
                                                    
                                                    $award_counts[$details['group']]++;
                                                    $award_class = $details['class'];
                                                    $award_icon = $details['icon'];
                                            ?>
                                                <span class="award-badge <?php echo $award_class; ?>">
                                                    <i class="fas fa-<?php echo $award_icon; ?>"></i>
                                                    <?php echo ucwords(str_replace('_', ' ', $award_type)); ?>
                                                    <div class="award-details">
                                                        <div class="award-date">
                                                            Awarded: <?php echo date('M d, Y', strtotime($awarded_at)); ?>
                                                        </div>
                                                    </div>
                                                </span>
                                            <?php
                                                }
                                            }
                                            ?>
                                        </div>
                                        <div class="awards-summary">
                                            <?php foreach ($award_counts as $type => $count): 
                                                if ($count > 0):
                                            ?>
                                                <span>
                                                    <i class="fas fa-<?php echo match($type) {
                                                        'champion' => 'trophy',
                                                        'achievement' => 'medal',
                                                        'special' => 'star'
                                                    }; ?>"></i>
                                                    <?php echo $count; ?> <?php echo ucfirst($type); ?>
                                                </span>
                                            <?php 
                                                endif;
                                            endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php
                                        // Calculate progress to next rank
                                        $current_messages = (int)$user['total_messages'];
                                        $next_rank_threshold = match($user['rank_status']) {
                                            'New' => 1,
                                            'Active' => 50,
                                            'Bronze' => 100,
                                            'Silver' => 500,
                                            'Gold' => 1000,
                                            default => null
                                        };
                                        
                                        if ($next_rank_threshold !== null) {
                                            $progress = min(100, ($current_messages / $next_rank_threshold) * 100);
                                            $remaining = $next_rank_threshold - $current_messages;
                                            ?>
                                            <div class="rank-progress">
                                                <div class="rank-progress-bar" 
                                                     style="width: <?php echo $progress; ?>%; 
                                                            background: linear-gradient(90deg, 
                                                                var(--primary-color), 
                                                                var(--primary-color-light));">
                                                </div>
                                            </div>
                                            <div class="rank-info">
                                                <?php echo $remaining; ?> messages to next rank
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                </div>
                            </td>
                            <td class="message-count">
                                <i class="fas fa-comment"></i>
                                <?php echo number_format((float)($user['message_count'] ?? 0)); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?period=<?php echo $period; ?>&page=<?php echo ($page - 1); ?>" class="page-link">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <div class="page-numbers">
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<a href="?period=' . $period . '&page=1" class="page-link">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="page-ellipsis">...</span>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $active_class = ($i === $page) ? ' active' : '';
                        echo '<a href="?period=' . $period . '&page=' . $i . '" class="page-link' . $active_class . '">' . $i . '</a>';
                    }
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="page-ellipsis">...</span>';
                        }
                        echo '<a href="?period=' . $period . '&page=' . $total_pages . '" class="page-link">' . $total_pages . '</a>';
                    }
                    ?>
                </div>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?period=<?php echo $period; ?>&page=<?php echo ($page + 1); ?>" class="page-link">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center;">
            <a href="index.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Chat
            </a>
        </div>
    </div>
    
    <!-- Add this at the bottom of the body -->
    <div class="avatar-overlay"></div>
    <div class="avatar-preview">
        <img src="" alt="Profile Picture Preview">
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const overlay = document.querySelector('.avatar-overlay');
        const preview = document.querySelector('.avatar-preview');
        const previewImg = preview.querySelector('img');
        
        document.querySelectorAll('[data-avatar="true"]').forEach(avatar => {
            avatar.addEventListener('click', function() {
                previewImg.src = this.src;
                overlay.classList.add('show');
                preview.classList.add('show');
            });
        });
        
        overlay.addEventListener('click', function() {
            overlay.classList.remove('show');
            preview.classList.remove('show');
        });
    });
    </script>
</body>
</html>
