

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";



 Database: `chat_app`


 


 Table structure for table `activity_feed`


CREATE TABLE `activity_feed` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` enum('status_update','story_post','room_join','friend_add','group_join','achievement') NOT NULL,
  `activity_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`activity_data`)),
  `visibility` enum('public','friends','private') DEFAULT 'public',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Dumping data for table `activity_feed`


INSERT INTO `activity_feed` (`id`, `user_id`, `activity_type`, `activity_data`, `visibility`, `created_at`) VALUES
(13, 1, '', '{\"group_name\":\"Fitness & Health\"}', 'public', '2025-01-26 18:29:05');



 Table structure for table `admin_settings`


CREATE TABLE `admin_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Dumping data for table `admin_settings`


INSERT INTO `admin_settings` (`id`, `setting_name`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'FRIENDS - Chat Room', '2025-01-23 04:30:59', '2025-01-28 16:52:28'),
(2, 'max_room_members', '100', '2025-01-23 04:30:59', '2025-01-23 16:17:16'),
(3, 'allow_public_rooms', '1', '2025-01-23 04:30:59', '2025-01-23 04:30:59'),
(4, 'enable_file_sharing', '1', '2025-01-23 04:30:59', '2025-01-23 04:30:59'),
(5, 'max_file_size', '5242880', '2025-01-23 04:30:59', '2025-01-23 04:30:59'),
(6, 'allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx', '2025-01-23 04:30:59', '2025-01-23 04:30:59'),
(7, 'enable_user_registration', '1', '2025-01-23 04:30:59', '2025-01-23 04:30:59'),
(8, 'Welcome to our chat room!', 'welcome_message', '2025-01-23 05:07:40', '2025-01-23 05:07:40'),
(9, '5', 'max_user_rooms', '2025-01-23 05:07:40', '2025-01-23 05:07:40'),
(10, '1000', 'max_message_length', '2025-01-23 05:07:40', '2025-01-23 05:07:40'),
(11, '100', 'message_history_limit', '2025-01-23 05:07:40', '2025-01-23 05:07:40'),
(13, 'welcome_message', 'Welcome to our chat room!', '2025-01-23 16:17:16', '2025-01-23 16:17:16'),
(14, 'max_user_rooms', '500', '2025-01-23 16:17:17', '2025-01-23 16:17:17'),
(15, 'max_message_length', '1000', '2025-01-23 16:17:17', '2025-01-23 16:17:17'),
(16, 'message_history_limit', '100', '2025-01-23 16:17:17', '2025-01-23 16:17:17');

 


 Table structure for table `friends`


CREATE TABLE `friends` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `friend_id` int(11) NOT NULL,
  `status` enum('pending','accepted','blocked') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Dumping data for table `friends`


INSERT INTO `friends` (`id`, `user_id`, `friend_id`, `status`, `created_at`, `updated_at`) VALUES
(17, 3, 1, 'accepted', '2025-01-26 18:30:10', '2025-01-26 18:32:47'),
(18, 1, 2, 'accepted', '2025-01-25 09:53:54', '2025-01-25 09:54:11');

 


 Table structure for table `friendships`


CREATE TABLE `friendships` (
  `id` int(11) NOT NULL,
  `user_id1` int(11) NOT NULL,
  `user_id2` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

 


 Table structure for table `friend_suggestions`


CREATE TABLE `friend_suggestions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `suggested_user_id` int(11) NOT NULL,
  `score` float DEFAULT 0,
  `reason` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`reason`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

 


 Table structure for table `interest_groups`


CREATE TABLE `interest_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Dumping data for table `interest_groups`


INSERT INTO `interest_groups` (`id`, `name`, `description`, `created_by`, `created_at`) VALUES
(1, 'Gaming', 'Connect with fellow gamers and discuss your favorite games', 1, '2025-01-26 18:07:13'),
(2, 'Technology', 'Discuss the latest tech trends and innovations', 1, '2025-01-26 18:07:13'),
(3, 'Music', 'Share and discover new music with others', 1, '2025-01-26 18:07:13'),
(4, 'Movies & TV', 'Talk about your favorite shows and movies', 1, '2025-01-26 18:07:13'),
(5, 'Sports', 'Connect with sports enthusiasts', 1, '2025-01-26 18:07:13'),
(6, 'Art & Design', 'Share your creative work and get inspired', 1, '2025-01-26 18:07:13'),
(7, 'Books & Reading', 'Discuss literature and share book recommendations', 1, '2025-01-26 18:07:13'),
(8, 'Food & Cooking', 'Share recipes and cooking tips', 1, '2025-01-26 18:07:14'),
(9, 'Travel', 'Share travel experiences and get destination recommendations', 1, '2025-01-26 18:07:14'),
(10, 'Fitness & Health', 'Discuss fitness tips and health-related topics', 1, '2025-01-26 18:07:14'),
(11, 'Gaming', 'Connect with fellow gamers and discuss your favorite games', 1, '2025-01-26 18:13:54'),
(12, 'Technology', 'Discuss the latest tech trends and innovations', 1, '2025-01-26 18:13:54'),
(13, 'Music', 'Share and discover new music with others', 1, '2025-01-26 18:13:54'),
(14, 'Movies & TV', 'Talk about your favorite shows and movies', 1, '2025-01-26 18:13:54'),
(15, 'Sports', 'Connect with sports enthusiasts', 1, '2025-01-26 18:13:54'),
(16, 'Art & Design', 'Share your creative work and get inspired', 1, '2025-01-26 18:13:54'),
(17, 'Books & Reading', 'Discuss literature and share book recommendations', 1, '2025-01-26 18:13:54'),
(18, 'Food & Cooking', 'Share recipes and cooking tips', 1, '2025-01-26 18:13:54'),
(19, 'Travel', 'Share travel experiences and get destination recommendations', 1, '2025-01-26 18:13:54'),
(20, 'Fitness & Health', 'Discuss fitness tips and health-related topics', 1, '2025-01-26 18:13:54');

 


 Table structure for table `messages`


CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `type` varchar(50) DEFAULT 'text',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


 Dumping data for table `messages`


INSERT INTO `messages` (`id`, `room_id`, `user_id`, `content`, `type`, `created_at`) VALUES
(154, 5, 2, 'ss', 'text', '2025-01-28 17:18:30'),
(155, 5, 2, '65', 'text', '2025-01-28 17:22:42'),


 


 Table structure for table `message_stats`


CREATE TABLE `message_stats` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `daily_count` int(11) DEFAULT 0,
  `weekly_count` int(11) DEFAULT 0,
  `monthly_count` int(11) DEFAULT 0,
  `last_daily_reset` timestamp NULL DEFAULT NULL,
  `last_weekly_reset` timestamp NULL DEFAULT NULL,
  `last_monthly_reset` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Dumping data for table `message_stats`


INSERT INTO `message_stats` (`id`, `user_id`, `daily_count`, `weekly_count`, `monthly_count`, `last_daily_reset`, `last_weekly_reset`, `last_monthly_reset`) VALUES
(4, 1, 10, 11, 108, '2025-01-27 19:12:58', '2025-01-27 18:57:44', '2025-01-24 16:30:30'),
(29, 3, 15, 16, 84, '2025-01-27 19:03:58', '2025-01-27 18:53:53', '2025-01-24 17:49:15');


 Triggers `message_stats`

DELIMITER $$
CREATE TRIGGER `give_champion_awards` AFTER UPDATE ON `message_stats` FOR EACH ROW BEGIN
     Check for daily champion
    IF NEW.daily_count > 0 AND NEW.daily_count = (
        SELECT MAX(daily_count) FROM message_stats
    ) THEN
        INSERT IGNORE INTO user_awards (user_id, award_type) 
        VALUES (NEW.user_id, 'Daily Champion');
    END IF;

     Check for weekly champion
    IF NEW.weekly_count > 0 AND NEW.weekly_count = (
        SELECT MAX(weekly_count) FROM message_stats
    ) THEN
        INSERT IGNORE INTO user_awards (user_id, award_type) 
        VALUES (NEW.user_id, 'Weekly Champion');
    END IF;

     Check for monthly champion
    IF NEW.monthly_count > 0 AND NEW.monthly_count = (
        SELECT MAX(monthly_count) FROM message_stats
    ) THEN
        INSERT IGNORE INTO user_awards (user_id, award_type) 
        VALUES (NEW.user_id, 'Monthly Champion');
    END IF;
END
$$
DELIMITER ;

 


 Table structure for table `reports`


CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `status` enum('pending','resolved','dismissed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

 


 Table structure for table `rooms`


CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `privacy_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`privacy_settings`)),
  `chat_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`chat_settings`)),
  `notification_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_settings`)),
  `theme` varchar(50) DEFAULT 'default',
  `wallpaper` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `avatar` varchar(255) DEFAULT 'default_room.png',
  `description` text DEFAULT NULL,
  `background_color` varchar(7) DEFAULT '#FFFFFF',
  `text_color` varchar(7) DEFAULT '#000000',
  `max_members` int(11) DEFAULT 100,
  `password` varchar(255) DEFAULT NULL,
  `rules` text DEFAULT NULL,
  `is_locked` tinyint(1) DEFAULT 0,
  `language` varchar(10) DEFAULT 'en'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


 Dumping data for table `rooms`


INSERT INTO `rooms` (`id`, `name`, `admin_id`, `privacy_settings`, `chat_settings`, `notification_settings`, `theme`, `wallpaper`, `created_at`, `avatar`, `description`, `background_color`, `text_color`, `max_members`, `password`, `rules`, `is_locked`, `language`) VALUES
(4, '', 0, NULL, NULL, NULL, 'default', NULL, '2025-01-28 17:15:47', 'default_room.png', NULL, '#FFFFFF', '#000000', 100, NULL, NULL, 0, 'en'),
(5, 'admin', 1, '{\"is_private\":0,\"join_approval\":0,\"members_only_chat\":0}', '{\"file_sharing\":0,\"image_sharing\":0,\"link_preview\":0,\"max_message_length\":1000}', '{\"messages\":0,\"mentions\":0,\"files\":0}', 'dark', NULL, '2025-01-28 17:18:17', 'default_room.png', '', '#FFFFFF', '#000000', 100, '$2y$10$iOiN6b5t5Iebim7onlxW0.2uj9E3DtKScjKn80HfsSRUUGdNAePb6', '', 0, '0');

 


 Table structure for table `room_invites`


CREATE TABLE `room_invites` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `invited_user_id` int(11) NOT NULL,
  `invited_by` int(11) NOT NULL,
  `status` enum('pending','accepted','declined') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Dumping data for table `room_invites`


INSERT INTO `room_invites` (`id`, `room_id`, `invited_user_id`, `invited_by`, `status`, `created_at`) VALUES
(1, 3, 2, 1, 'pending', '2025-01-28 17:15:36');

 


 Table structure for table `room_members`


CREATE TABLE `room_members` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `admin_level` int(11) DEFAULT 0,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


 Dumping data for table `room_members`


INSERT INTO `room_members` (`id`, `room_id`, `user_id`, `admin_level`, `joined_at`) VALUES
(10, 4, 2, 0, '2025-01-28 17:15:47'),
(11, 4, 1, 0, '2025-01-28 17:15:47'),
(13, 5, 1, 0, '2025-01-28 17:18:17'),
(14, 5, 2, 0, '2025-01-28 17:18:27');

 


 Table structure for table `room_messages`


CREATE TABLE `room_messages` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `edited_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

 


 Table structure for table `stories`


CREATE TABLE `stories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `background_color` varchar(7) DEFAULT '#000000',
  `media_url` varchar(255) DEFAULT NULL,
  `story_type` enum('text','image','video') NOT NULL DEFAULT 'text',
  `visibility` enum('public','friends','private') NOT NULL DEFAULT 'public',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT (current_timestamp() + interval 24 hour)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Dumping data for table `stories`


INSERT INTO `stories` (`id`, `user_id`, `content`, `background_color`, `media_url`, `story_type`, `visibility`, `created_at`, `expires_at`) VALUES
(11, 1, 'FF', '#ff0000', NULL, 'text', 'public', '2025-01-26 20:23:56', '2025-01-27 16:23:56'),
(12, 1, 's', '#000000', NULL, 'text', 'public', '2025-01-26 20:28:40', '2025-01-27 16:28:40'),
(13, 1, 'Hi\r\n\r\n', '#bd0000', NULL, 'text', 'public', '2025-01-26 21:09:07', '2025-01-27 17:09:07'),
(14, 1, 'sssssss', '#000000', NULL, 'text', 'public', '2025-01-28 16:50:14', '2025-01-29 12:50:14');

 


 Table structure for table `story_views`


CREATE TABLE `story_views` (
  `id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `viewer_id` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Dumping data for table `story_views`


INSERT INTO `story_views` (`id`, `story_id`, `viewer_id`, `viewed_at`) VALUES
(15, 11, 3, '2025-01-26 20:24:02'),
(16, 12, 3, '2025-01-26 20:32:30'),
(24, 13, 3, '2025-01-26 21:09:25');

 


 Table structure for table `typing_status`


CREATE TABLE `typing_status` (
  `id` int(11) NOT NULL,
  `room_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_typing` tinyint(1) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

 


 Table structure for table `users`


CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'default.png',
  `last_seen` timestamp NULL DEFAULT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `is_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bio` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


 Dumping data for table `users`


INSERT INTO `users` (`id`, `username`, `password`, `email`, `avatar`, `last_seen`, `is_online`, `is_admin`, `created_at`, `bio`, `date_of_birth`) VALUES
(1, 'admin', '$2y$10$Caj7DFVkLT58TfWrsiQFHeW9OqKu512md5Wl3h68agaiOSwtQMHju', 'admin@example.com', 'uploads/avatars/1_1738087783.jpeg', '2025-01-28 19:40:50', 1, 1, '2025-01-28 16:32:13', '', '0000-00-00'),
(2, 'talhaa', '$2y$10$WzxP51RDty1ktkFFb3RmpeSl4IGdvS8OrciHj.urpVt2pGTkDNqtK', 'talha@gmail.com', 'uploads/avatars/2_1738087718.jpeg', '2025-01-28 19:38:30', 1, 0, '2025-01-28 16:32:52', '', '0000-00-00');

 


 Table structure for table `user_awards`


CREATE TABLE `user_awards` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `award_type` enum('First Message','Bronze Achieved','Silver Achieved','Gold Achieved','Elite Achieved','Daily Champion','Weekly Champion','Monthly Champion') NOT NULL,
  `awarded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Dumping data for table `user_awards`


INSERT INTO `user_awards` (`id`, `user_id`, `award_type`, `awarded_at`) VALUES
(222, 1, 'Daily Champion', '2025-01-25 10:43:46'),
(229, 1, 'Weekly Champion', '2025-01-26 17:34:30'),
(230, 1, 'Monthly Champion', '2025-01-26 17:34:30'),
(231, 3, 'Daily Champion', '2025-01-26 21:39:50'),
(243, 3, 'Weekly Champion', '2025-01-27 12:14:40'),
(295, 1, '', '2025-01-27 12:19:58'),
(378, 3, '', '2025-01-27 19:11:51');

 


 Table structure for table `user_interests`


CREATE TABLE `user_interests` (
  `user_id` int(11) NOT NULL,
  `interest_group_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Dumping data for table `user_interests`


INSERT INTO `user_interests` (`user_id`, `interest_group_id`, `joined_at`) VALUES
(1, 4, '2025-01-26 18:29:05'),
(1, 5, '2025-01-26 18:35:59'),
(1, 19, '2025-01-26 18:35:57'),
(1, 20, '2025-01-26 18:29:05'),
(3, 9, '2025-01-26 18:29:05'),
(3, 19, '2025-01-26 18:29:05');

 


 Table structure for table `user_ranks`


CREATE TABLE `user_ranks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rank_name` enum('Elite','Gold','Silver','Bronze','Active','New') NOT NULL DEFAULT 'New',
  `total_messages` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Triggers `user_ranks`

DELIMITER $$
CREATE TRIGGER `give_rank_awards` AFTER INSERT ON `user_ranks` FOR EACH ROW BEGIN
     Give rank achievement awards
    CASE NEW.rank_name
        WHEN 'Elite' THEN 
            INSERT IGNORE INTO user_awards (user_id, award_type) VALUES (NEW.user_id, 'Elite Achieved');
        WHEN 'Gold' THEN 
            INSERT IGNORE INTO user_awards (user_id, award_type) VALUES (NEW.user_id, 'Gold Achieved');
        WHEN 'Silver' THEN 
            INSERT IGNORE INTO user_awards (user_id, award_type) VALUES (NEW.user_id, 'Silver Achieved');
        WHEN 'Bronze' THEN 
            INSERT IGNORE INTO user_awards (user_id, award_type) VALUES (NEW.user_id, 'Bronze Achieved');
        WHEN 'Active' THEN 
            INSERT IGNORE INTO user_awards (user_id, award_type) VALUES (NEW.user_id, 'First Message');
    END CASE;
END
$$
DELIMITER ;

 


 Table structure for table `user_status`


CREATE TABLE `user_status` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status_text` varchar(255) DEFAULT NULL,
  `emoji` varchar(50) DEFAULT NULL,
  `visibility` enum('public','friends','private') DEFAULT 'public',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Dumping data for table `user_status`


INSERT INTO `user_status` (`id`, `user_id`, `status_text`, `emoji`, `visibility`, `created_at`, `expires_at`) VALUES
(9, 1, 'Working on a new project 💻', '😊', 'public', '2025-01-26 18:29:05', '2025-01-26 18:53:38');



 Indexes for dumped tables

 Indexes for table `activity_feed`

ALTER TABLE `activity_feed`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_feed_user` (`user_id`);


 Indexes for table `admin_settings`

ALTER TABLE `admin_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);


 Indexes for table `friends`

ALTER TABLE `friends`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_friendship` (`user_id`,`friend_id`),
  ADD KEY `friend_id` (`friend_id`);


 Indexes for table `friendships`

ALTER TABLE `friendships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_friendship` (`user_id1`,`user_id2`),
  ADD KEY `user_id2` (`user_id2`);


 Indexes for table `friend_suggestions`

ALTER TABLE `friend_suggestions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `suggested_user_id` (`suggested_user_id`),
  ADD KEY `idx_friend_suggestions_user` (`user_id`);


 Indexes for table `interest_groups`

ALTER TABLE `interest_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);


 Indexes for table `messages`

ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id_idx` (`room_id`),
  ADD KEY `user_id_idx` (`user_id`);


 Indexes for table `message_stats`

ALTER TABLE `message_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);


 Indexes for table `reports`

ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reporter_id` (`reporter_id`),
  ADD KEY `message_id` (`message_id`),
  ADD KEY `resolved_by` (`resolved_by`);


 Indexes for table `rooms`

ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name_idx` (`name`),
  ADD KEY `created_by_idx` (`admin_id`);


 Indexes for table `room_invites`

ALTER TABLE `room_invites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_invite` (`room_id`,`invited_user_id`),
  ADD KEY `invited_user_id` (`invited_user_id`),
  ADD KEY `invited_by` (`invited_by`);


 Indexes for table `room_members`

ALTER TABLE `room_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_user_unique` (`room_id`,`user_id`),
  ADD KEY `room_id_idx` (`room_id`),
  ADD KEY `user_id_idx` (`user_id`);


 Indexes for table `room_messages`

ALTER TABLE `room_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `user_id` (`user_id`);


 Indexes for table `stories`

ALTER TABLE `stories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);


 Indexes for table `story_views`

ALTER TABLE `story_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_view` (`story_id`,`viewer_id`),
  ADD KEY `viewer_id` (`viewer_id`);


 Indexes for table `typing_status`

ALTER TABLE `typing_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_typing_status_room` (`room_id`),
  ADD KEY `idx_typing_status_user` (`user_id`),
  ADD KEY `idx_typing_status_updated` (`last_updated`);


 Indexes for table `users`

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username_idx` (`username`),
  ADD KEY `email_idx` (`email`),
  ADD KEY `last_seen_idx` (`last_seen`),
  ADD KEY `is_online_idx` (`is_online`),
  ADD KEY `is_admin_idx` (`is_admin`);


 Indexes for table `user_awards`

ALTER TABLE `user_awards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_award_unique` (`user_id`,`award_type`);


 Indexes for table `user_interests`

ALTER TABLE `user_interests`
  ADD PRIMARY KEY (`user_id`,`interest_group_id`),
  ADD KEY `interest_group_id` (`interest_group_id`);


 Indexes for table `user_ranks`

ALTER TABLE `user_ranks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);


 Indexes for table `user_status`

ALTER TABLE `user_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_status_user` (`user_id`);


 AUTO_INCREMENT for dumped tables



 AUTO_INCREMENT for table `activity_feed`

ALTER TABLE `activity_feed`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;


 AUTO_INCREMENT for table `admin_settings`

ALTER TABLE `admin_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;


 AUTO_INCREMENT for table `friends`

ALTER TABLE `friends`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;


 AUTO_INCREMENT for table `friendships`

ALTER TABLE `friendships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;


 AUTO_INCREMENT for table `friend_suggestions`

ALTER TABLE `friend_suggestions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


 AUTO_INCREMENT for table `interest_groups`

ALTER TABLE `interest_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;


 AUTO_INCREMENT for table `messages`

ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=219;


 AUTO_INCREMENT for table `message_stats`

ALTER TABLE `message_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=202;


 AUTO_INCREMENT for table `reports`

ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


 AUTO_INCREMENT for table `rooms`

ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;


 AUTO_INCREMENT for table `room_invites`

ALTER TABLE `room_invites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;


 AUTO_INCREMENT for table `room_members`

ALTER TABLE `room_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;


 AUTO_INCREMENT for table `room_messages`

ALTER TABLE `room_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


 AUTO_INCREMENT for table `stories`

ALTER TABLE `stories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;


 AUTO_INCREMENT for table `story_views`

ALTER TABLE `story_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;


 AUTO_INCREMENT for table `typing_status`

ALTER TABLE `typing_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=379;


 AUTO_INCREMENT for table `users`

ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;


 AUTO_INCREMENT for table `user_awards`

ALTER TABLE `user_awards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=413;


 AUTO_INCREMENT for table `user_ranks`

ALTER TABLE `user_ranks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


 AUTO_INCREMENT for table `user_status`

ALTER TABLE `user_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
