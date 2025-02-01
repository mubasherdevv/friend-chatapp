<?php

// Function to create a simple avatar with initials
function createInitialAvatar($text, $width, $height, $filename) {
    // Create image
    $image = imagecreatetruecolor($width, $height);
    
    // Colors
    $bg_color = imagecolorallocate($image, 100, 100, 240);  // Light blue background
    $text_color = imagecolorallocate($image, 255, 255, 255);  // White text
    
    // Fill background
    imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);
    
    // Add text
    $font_size = min($width, $height) / 2;
    $font = 5;  // Built-in font
    
    // Get text size
    $text_box = imagettfbbox($font_size, 0, $font, $text);
    $text_width = abs($text_box[4] - $text_box[0]);
    $text_height = abs($text_box[5] - $text_box[1]);
    
    // Center text
    $x = ($width - $text_width) / 2;
    $y = ($height - $text_height) / 2;
    
    // Add text to image
    imagestring($image, $font, $x, $y, $text, $text_color);
    
    // Save image
    imagepng($image, $filename);
    imagedestroy($image);
}

// Create default user avatar
createInitialAvatar('U', 200, 200, 'default.png');

// Create default room avatar
createInitialAvatar('R', 200, 200, 'default_room.png');

echo "Default avatars created successfully!\n";
?>
