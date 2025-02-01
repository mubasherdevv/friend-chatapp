<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/StreamChat.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, is_admin, avatar, email FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];
                $_SESSION['avatar'] = $user['avatar'];
                $_SESSION['email'] = $user['email'];

                // Update last seen
                $update_stmt = $conn->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();

                // Set redirect target based on user type
                $_SESSION['redirect_after_login'] = $user['is_admin'] ? '../admin/admin_dashboard.php' : '../index.php';
                
                // Redirect to loading page
                header('Location: auth_redirect.php');
                exit;
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password";
        }
    }
}

$page_title = "Login";
include '../includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full backdrop-blur-lg bg-white/70 dark:bg-gray-800/70 rounded-2xl shadow-xl p-8 space-y-8 border border-white/20">
        <div>
            <div class="w-20 h-20 mx-auto bg-gradient-to-br from-[#FF6B6B] to-[#FF3399] rounded-2xl p-2 shadow-lg">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-full h-full fill-current text-white">
                    <path d="M12 2C6.48 2 2 6.48 2 12c0 2.17.7 4.19 1.94 5.83L2.87 21l3.17-1.07C7.69 21.27 9.79 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18c-2.03 0-3.93-.61-5.5-1.65l-.23-.16-2.37.79.79-2.37-.16-.23C3.61 15.93 3 14.03 3 12c0-4.97 4.03-9 9-9s9 4.03 9 9-4.03 9-9 9z"/>
                    <path d="M17 9h-6c-.55 0-1 .45-1 1s.45 1 1 1h6c.55 0 1-.45 1-1s-.45-1-1-1zM17 13h-6c-.55 0-1 .45-1 1s.45 1 1 1h6c.55 0 1-.45 1-1s-.45-1-1-1z"/>
                </svg>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold bg-gradient-to-r from-[#FF6B6B] to-[#FF3399] text-transparent bg-clip-text">
                Welcome Back!
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                Or
                <a href="register.php" class="font-medium text-transparent bg-clip-text bg-gradient-to-r from-[#9B6BFF] to-[#FF3399] hover:from-[#FF6B6B] hover:to-[#FF9F4A] transition-all duration-300">
                    create a new account
                </a>
            </p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100/80 dark:bg-red-900/80 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded-xl relative" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <form class="mt-8 space-y-6" action="" method="POST">
            <div class="rounded-xl shadow-sm space-y-4">
                <div>
                    <label for="username" class="sr-only">Username</label>
                    <input id="username" name="username" type="text" required 
                           class="appearance-none relative block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white/50 dark:bg-gray-900/50 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#FF3399] focus:border-transparent transition-all duration-300 sm:text-sm" 
                           placeholder="Username">
                </div>
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" name="password" type="password" required 
                           class="appearance-none relative block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white/50 dark:bg-gray-900/50 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#FF3399] focus:border-transparent transition-all duration-300 sm:text-sm" 
                           placeholder="Password">
                </div>
            </div>

            <div>
                <button type="submit" 
                        class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-xl text-white bg-gradient-to-r from-[#FF6B6B] to-[#FF3399] hover:from-[#FF3399] hover:to-[#9B6BFF] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#FF3399] transform transition-all duration-300 hover:scale-[1.02]">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-white/70 group-hover:text-white/90" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    Sign in
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
