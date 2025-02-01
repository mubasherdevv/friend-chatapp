<?php

function getDefaultAvatarUrl() {
    // Generate an SVG-based avatar with initials if available
    if (isset($_SESSION['username'])) {
        $initials = strtoupper(substr($_SESSION['username'], 0, 2));
        $colors = ['#4F46E5', '#7C3AED', '#EC4899', '#EF4444', '#F59E0B', '#10B981'];
        $bgColor = $colors[array_rand($colors)];
        
        return "data:image/svg+xml," . urlencode('
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">
                <rect width="40" height="40" fill="' . $bgColor . '"/>
                <text x="50%" y="50%" dy=".1em" fill="white" text-anchor="middle" dominant-baseline="middle" 
                      font-family="Arial" font-size="20" font-weight="bold">' . $initials . '</text>
            </svg>
        ');
    }
    
    // Fallback to a generic user icon
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23A0AEC0'%3E%3Cpath d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z'/%3E%3C/svg%3E";
}

function getAvatarUrl($avatar) {
    if (!$avatar || $avatar === 'default.png') {
        return getDefaultAvatarUrl();
    }
    
    $avatar_path = "uploads/avatars/" . htmlspecialchars($avatar);
    
    // Check if file exists and is readable
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $avatar_path) && is_readable($_SERVER['DOCUMENT_ROOT'] . '/' . $avatar_path)) {
        return $avatar_path;
    }
    
    return getDefaultAvatarUrl();
}
