<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Username already exists";
        } else {
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Email already registered";
            } else {
                // Create user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, created_at, is_admin) VALUES (?, ?, ?, NOW(), 0)");
                $stmt->bind_param("sss", $username, $email, $hashed_password);
                
                if ($stmt->execute()) {
                    $_SESSION['user_id'] = $stmt->insert_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['is_admin'] = 0;
                    
                    header('Location: ../index.php');
                    exit;
                } else {
                    $error = "Registration failed. Please try again.";
                }
            }
        }
    }
}

$page_title = "Register";
include '../includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full backdrop-blur-lg bg-white/70 dark:bg-gray-800/70 rounded-2xl shadow-xl p-8 space-y-8 border border-white/20">
        <div>
            <div class="w-20 h-20 mx-auto bg-gradient-to-br from-[#9B6BFF] to-[#FF3399] rounded-2xl p-2 shadow-lg">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-full h-full fill-current text-white">
                    <path d="M12 2C6.48 2 2 6.48 2 12c0 2.17.7 4.19 1.94 5.83L2.87 21l3.17-1.07C7.69 21.27 9.79 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18c-2.03 0-3.93-.61-5.5-1.65l-.23-.16-2.37.79.79-2.37-.16-.23C3.61 15.93 3 14.03 3 12c0-4.97 4.03-9 9-9s9 4.03 9 9-4.03 9-9 9z"/>
                    <path d="M13 7h-2v4H7v2h4v4h2v-4h4v-2h-4V7zm-1-5C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                </svg>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold bg-gradient-to-r from-[#9B6BFF] to-[#FF3399] text-transparent bg-clip-text">
                Join Our Community
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                Or
                <a href="login.php" class="font-medium text-transparent bg-clip-text bg-gradient-to-r from-[#FF6B6B] to-[#FF3399] hover:from-[#FF6B6B] hover:to-[#FF9F4A] transition-all duration-300">
                    sign in to your account
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
                           class="appearance-none relative block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white/50 dark:bg-gray-900/50 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9B6BFF] focus:border-transparent transition-all duration-300 sm:text-sm" 
                           placeholder="Username"
                           value="<?php echo htmlspecialchars($username ?? ''); ?>">
                </div>
                <div>
                    <label for="email" class="sr-only">Email address</label>
                    <input id="email" name="email" type="email" required 
                           class="appearance-none relative block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white/50 dark:bg-gray-900/50 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9B6BFF] focus:border-transparent transition-all duration-300 sm:text-sm" 
                           placeholder="Email address"
                           value="<?php echo htmlspecialchars($email ?? ''); ?>">
                </div>
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" name="password" type="password" required 
                           class="appearance-none relative block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white/50 dark:bg-gray-900/50 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9B6BFF] focus:border-transparent transition-all duration-300 sm:text-sm" 
                           placeholder="Password">
                </div>
                <div>
                    <label for="confirm_password" class="sr-only">Confirm password</label>
                    <input id="confirm_password" name="confirm_password" type="password" required 
                           class="appearance-none relative block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white/50 dark:bg-gray-900/50 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9B6BFF] focus:border-transparent transition-all duration-300 sm:text-sm" 
                           placeholder="Confirm password">
                </div>
            </div>

            <div>
                <button type="submit" 
                        class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-xl text-white bg-gradient-to-r from-[#9B6BFF] to-[#FF3399] hover:from-[#FF6B6B] hover:to-[#FF9F4A] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#9B6BFF] transform transition-all duration-300 hover:scale-[1.02]">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-white/70 group-hover:text-white/90" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"/>
                        </svg>
                    </span>
                    Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
