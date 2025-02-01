<?php
session_start();
require_once '../config/database.php';

if (isset($_SESSION['user_id'])) {
    // Update user's last seen timestamp
    $stmt = $conn->prepare("UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
}

session_destroy();
header('Location: login.php');
exit;
?>
