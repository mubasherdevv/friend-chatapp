<?php

if (!function_exists('getAdminSetting')) {

function getAdminSetting($conn, $key, $default = null) {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = ?");
        if (!$stmt) {
            error_log("Error preparing admin settings query: " . $conn->error);
            return $default;
        }
        
        $stmt->bind_param("s", $key);
        if (!$stmt->execute()) {
            error_log("Error executing admin settings query: " . $stmt->error);
            return $default;
        }
        
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc()['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Error getting admin setting: " . $e->getMessage());
    }
    
    return $default;
}

function isAdmin($conn, $user_id) {
    try {
        $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
        if (!$stmt) {
            error_log("Error preparing admin check query: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            error_log("Error executing admin check query: " . $stmt->error);
            return false;
        }
        
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            return (bool)$result->fetch_assoc()['is_admin'];
        }
    } catch (Exception $e) {
        error_log("Error checking admin status: " . $e->getMessage());
    }
    
    return false;
}

function enforceRoomLimit($conn, $user_id) {
    $max_rooms = (int)getAdminSetting($conn, 'max_user_rooms', 5);
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM rooms WHERE admin_id = ?");
        if (!$stmt) {
            error_log("Error preparing room count query: " . $conn->error);
            return true;
        }
        
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            error_log("Error executing room count query: " . $stmt->error);
            return true;
        }
        
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc()['count'] < $max_rooms;
        }
    } catch (Exception $e) {
        error_log("Error enforcing room limit: " . $e->getMessage());
    }
    
    return true;
}

function enforceRoomMemberLimit($conn, $room_id) {
    $max_members = (int)getAdminSetting($conn, 'max_room_members', 100);
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM room_members WHERE room_id = ?");
        if (!$stmt) {
            error_log("Error preparing member count query: " . $conn->error);
            return true;
        }
        
        $stmt->bind_param("i", $room_id);
        if (!$stmt->execute()) {
            error_log("Error executing member count query: " . $stmt->error);
            return true;
        }
        
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc()['count'] < $max_members;
        }
    } catch (Exception $e) {
        error_log("Error enforcing member limit: " . $e->getMessage());
    }
    
    return true;
}

function getSiteName($conn) {
    return getAdminSetting($conn, 'site_name', 'Chat Application');
}

function getWelcomeMessage($conn) {
    return getAdminSetting($conn, 'welcome_message', 'Welcome to our chat application!');
}

function isRegistrationAllowed($conn) {
    return (bool)getAdminSetting($conn, 'allow_user_registration', true);
}

function getMaxMessageLength($conn) {
    return (int)getAdminSetting($conn, 'max_message_length', 1000);
}

function getMessageHistoryLimit($conn) {
    return (int)getAdminSetting($conn, 'message_history_limit', 100);
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . " week" . ($weeks > 1 ? "s" : "") . " ago";
    } else {
        return date("F j, Y", $time);
    }
}

function formatTime($string) {
    if (!$string) return '';
    
    $time = strtotime($string);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    }
    
    if ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . 'm ago';
    }
    
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . 'h ago';
    }
    
    if ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . 'd ago';
    }
    
    if ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . 'w ago';
    }
    
    if ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . 'mo ago';
    }
    
    $years = floor($diff / 31536000);
    return $years . 'y ago';
}

function shortTime($string) {
    if (!$string) return '';
    return date('g:i A', strtotime($string));
}

function shortTimeAgo($string) {
    if (!$string) return '';
    
    $time = strtotime($string);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'now';
    }
    
    if ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . 'm';
    }
    
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . 'h';
    }
    
    if ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . 'd';
    }
    
    if ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . 'w';
    }
    
    if ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . 'mo';
    }
    
    $years = floor($diff / 31536000);
    return $years . 'y';
}

function updateLastSeen($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}

function update_user_activity($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}

function update_typing_status($room_id, $user_id, $is_typing) {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO typing_status (room_id, user_id, is_typing, last_updated)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), last_updated = NOW()
    ");
    
    $stmt->bind_param("iii", $room_id, $user_id, $is_typing);
    $stmt->execute();
}

function get_user_avatar($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        return null;
    }
    
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['avatar'];
    }
    
    return null;
}

function send_message($room_id, $user_id, $content) {
    global $conn;
    
    // Validate input
    if (empty($room_id) || empty($user_id) || empty($content)) {
        throw new Exception('Invalid input parameters');
    }
    
    // Check if user is member of the room
    $stmt = $conn->prepare("SELECT 1 FROM room_members WHERE room_id = ? AND user_id = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $room_id, $user_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to verify room membership: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        throw new Exception('User is not a member of this room');
    }
    
    // Insert the message
    $stmt = $conn->prepare("
        INSERT INTO messages (room_id, user_id, content, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("iis", $room_id, $user_id, $content);
    if (!$stmt->execute()) {
        throw new Exception('Failed to send message: ' . $stmt->error);
    }
    
    $message_id = $stmt->insert_id;
    
    // Get the inserted message
    $stmt = $conn->prepare("
        SELECT m.*, DATE_FORMAT(m.created_at, '%h:%i %p') as formatted_time
        FROM messages m
        WHERE m.id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to fetch sent message');
    }
    
    $stmt->bind_param("i", $message_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to fetch sent message');
    }
    
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function get_or_create_room($user1_id, $user2_id) {
    global $conn;
    
    // First try to find an existing room
    $stmt = $conn->prepare("
        SELECT r.id 
        FROM rooms r
        JOIN room_members rm1 ON r.id = rm1.room_id AND rm1.user_id = ?
        JOIN room_members rm2 ON r.id = rm2.room_id AND rm2.user_id = ?
        LIMIT 1
    ");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $user1_id, $user2_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to check existing room: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($room = $result->fetch_assoc()) {
        return $room['id'];
    }
    
    // No existing room found, create a new one
    $conn->begin_transaction();
    
    try {
        // Create room
        $stmt = $conn->prepare("INSERT INTO rooms (created_at) VALUES (NOW())");
        if (!$stmt->execute()) {
            throw new Exception('Failed to create room: ' . $stmt->error);
        }
        
        $room_id = $stmt->insert_id;
        
        // Add members
        $stmt = $conn->prepare("INSERT INTO room_members (room_id, user_id) VALUES (?, ?), (?, ?)");
        $stmt->bind_param("iiii", $room_id, $user1_id, $room_id, $user2_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to add room members: ' . $stmt->error);
        }
        
        $conn->commit();
        return $room_id;
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function get_messages($room_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT m.id, m.user_id, m.content, 
               DATE_FORMAT(m.created_at, '%H:%i') as timestamp,
               u.username, u.avatar
        FROM messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.room_id = ?
        ORDER BY m.created_at ASC
    ");
    
    if (!$stmt) {
        error_log("Messages query prepare failed: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("i", $room_id);
    if (!$stmt->execute()) {
        error_log("Messages query execute failed: " . $stmt->error);
        return [];
    }
    
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return $messages;
}

function time_elapsed_string($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Calculate weeks properly from total days
    $weeks = floor($diff->days / 7);
    $remaining_days = $diff->days % 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );

    $parts = array();

    // Handle years, months if present
    if ($diff->y) $parts[] = $diff->y . ' ' . $string['y'] . ($diff->y > 1 ? 's' : '');
    if ($diff->m) $parts[] = $diff->m . ' ' . $string['m'] . ($diff->m > 1 ? 's' : '');
    
    // Add weeks if present
    if ($weeks) $parts[] = $weeks . ' ' . $string['w'] . ($weeks > 1 ? 's' : '');
    
    // Add remaining days
    if ($remaining_days) $parts[] = $remaining_days . ' ' . $string['d'] . ($remaining_days > 1 ? 's' : '');
    
    // Add hours, minutes, seconds
    if ($diff->h) $parts[] = $diff->h . ' ' . $string['h'] . ($diff->h > 1 ? 's' : '');
    if ($diff->i) $parts[] = $diff->i . ' ' . $string['i'] . ($diff->i > 1 ? 's' : '');
    if ($diff->s) $parts[] = $diff->s . ' ' . $string['s'] . ($diff->s > 1 ? 's' : '');

    if (empty($parts)) {
        return 'just now';
    }

    return $parts[0] . ' ago';
}

function formatTimestamp($timestamp) {
    if (!$timestamp) return 'Never';
    
    $now = new DateTime();
    $time = new DateTime($timestamp);
    $diff = $now->diff($time);
    
    if ($diff->days == 0) {
        if ($diff->h == 0) {
            if ($diff->i == 0) {
                return 'Just now';
            }
            return $diff->i . ' min' . ($diff->i != 1 ? 's' : '') . ' ago';
        }
        return $diff->h . ' hour' . ($diff->h != 1 ? 's' : '') . ' ago';
    } elseif ($diff->days == 1) {
        return 'Yesterday';
    } elseif ($diff->days < 7) {
        return $diff->days . ' day' . ($diff->days != 1 ? 's' : '') . ' ago';
    } else {
        return $time->format('M j, Y');
    }
}

function formatActivity($type, $data) {
    if (!is_array($data)) {
        $data = json_decode($data, true) ?? [];
    }

    switch ($type) {
        case 'status_update':
            return "Updated their status: " . htmlspecialchars($data['status_text'] ?? 'No status text');
        case 'story_post':
            return "Posted a new " . htmlspecialchars($data['story_type'] ?? 'text') . " story";
        case 'friend_added':
            return "Became friends with " . htmlspecialchars($data['friend_name'] ?? 'someone');
        case 'group_joined':
            return "Joined the " . htmlspecialchars($data['group_name'] ?? 'a group');
        default:
            return "Performed an activity";
    }
}

function formatSuggestionReason($reason) {
    $text = [];
    if (!empty($reason['mutual_friends'])) {
        $count = count($reason['mutual_friends']);
        $text[] = $count . ' mutual friend' . ($count > 1 ? 's' : '');
    }
    if (!empty($reason['common_interests'])) {
        $count = count($reason['common_interests']);
        $text[] = $count . ' shared interest' . ($count > 1 ? 's' : '');
    }
    if (!empty($reason['same_rooms'])) {
        $count = count($reason['same_rooms']);
        $text[] = 'In ' . $count . ' same room' . ($count > 1 ? 's' : '');
    }
    return implode(' • ', $text);
}

}
?>
