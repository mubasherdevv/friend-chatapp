<?php
require_once '../config/database.php';
require_once 'auth_middleware.php';
requireAdmin();

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $setting_key = substr($key, 8); // Remove 'setting_' prefix
            
            // Check if setting exists
            $stmt = $conn->prepare("SELECT id FROM admin_settings WHERE setting_name = ?");
            $stmt->bind_param("s", $setting_key);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                // Update existing setting
                $stmt = $conn->prepare("UPDATE admin_settings SET setting_value = ? WHERE setting_name = ?");
                $stmt->bind_param("ss", $value, $setting_key);
            } else {
                // Insert new setting
                $stmt = $conn->prepare("INSERT INTO admin_settings (setting_name, setting_value) VALUES (?, ?)");
                $stmt->bind_param("ss", $setting_key, $value);
            }
            
            $stmt->execute();
        }
    }
    
    header('Location: settings.php?success=1');
    exit;
}

// Get current settings
$stmt = $conn->prepare("SELECT * FROM admin_settings");
$stmt->execute();
$settings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$settings = array_column($settings, 'setting_value', 'setting_name');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - Chat Room</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">Admin Settings</h1>
            <a href="admin_dashboard.php" class="text-indigo-600 hover:text-indigo-800">Back to Dashboard</a>
        </div>

        <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            Settings updated successfully!
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-md p-6">
            <form method="POST" class="space-y-6">
                <!-- General Settings -->
                <div>
                    <h2 class="text-xl font-semibold mb-4">General Settings</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Site Name</label>
                            <input type="text" name="setting_site_name" 
                                   value="<?php echo htmlspecialchars($settings['site_name'] ?? 'Chat Room'); ?>" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Welcome Message</label>
                            <textarea name="setting_welcome_message" 
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      rows="3"><?php echo htmlspecialchars($settings['welcome_message'] ?? 'Welcome to our chat room!'); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Room Settings -->
                <div>
                    <h2 class="text-xl font-semibold mb-4">Room Settings</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Maximum Members per Room</label>
                            <input type="number" name="setting_max_room_members" 
                                   value="<?php echo htmlspecialchars($settings['max_room_members'] ?? '50'); ?>" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Maximum Rooms per User</label>
                            <input type="number" name="setting_max_user_rooms" 
                                   value="<?php echo htmlspecialchars($settings['max_user_rooms'] ?? '5'); ?>" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                <!-- Message Settings -->
                <div>
                    <h2 class="text-xl font-semibold mb-4">Message Settings</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Maximum Message Length</label>
                            <input type="number" name="setting_max_message_length" 
                                   value="<?php echo htmlspecialchars($settings['max_message_length'] ?? '1000'); ?>" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Message History Limit</label>
                            <input type="number" name="setting_message_history_limit" 
                                   value="<?php echo htmlspecialchars($settings['message_history_limit'] ?? '100'); ?>" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">
                    Save Settings
                </button>
            </form>
        </div>
    </div>
</body>
</html>
