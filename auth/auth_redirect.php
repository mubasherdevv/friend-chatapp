<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get the target page from session or default to index.php
$target_page = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : '../index.php';
unset($_SESSION['redirect_after_login']); // Clear the redirect URL

// Get user data for personalized welcome message
require_once '../config/database.php';
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$username = htmlspecialchars($user['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Chat Room</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/js/loader.js"></script>
    <style>
        .welcome-message {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.5s ease forwards;
            animation-delay: 0.5s;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .progress-bar {
            width: 0;
            animation: progress 2s ease forwards;
        }

        @keyframes progress {
            to {
                width: 100%;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-500 to-purple-600">
    <?php 
    define('LOADER_INCLUDED', true);
    include '../includes/loader.php'; 
    ?>

    <div class="fixed inset-0 flex items-center justify-center">
        <div class="text-center text-white">
            <div class="welcome-message">
                <h1 class="text-4xl font-bold mb-4">Welcome back, <?php echo $username; ?>!</h1>
                <p class="text-xl opacity-80">Preparing your chat experience...</p>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize loader
        const loader = new PageLoader();
        loader.show();

        // Let the loader's built-in simulation run for 5 seconds
        loader.simulateLoading();

        // Complete loading and redirect after simulation
        setTimeout(() => {
            loader.completeLoading();
            setTimeout(() => {
                window.location.href = <?php echo json_encode($target_page); ?>;
            }, 1000);
        }, 6000);
    });
    </script>
</body>
</html>
