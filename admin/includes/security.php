<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class AdminSecurity {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        global $conn;
        
        if (!isset($conn)) {
            // Database configuration
            $db_host = 'localhost';
            $db_user = 'root';
            $db_pass = '';
            $db_name = 'chat_app';

            // Create connection
            $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

            // Check connection
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
        }
        
        $this->conn = $conn;
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function verifyAdminAccess() {
        if (!isset($_SESSION['user_id'])) {
            $this->logSecurityEvent('Unauthorized access attempt', null);
            header('Location: ../auth/login.php');
            exit;
        }

        // Check admin status
        $stmt = $this->conn->prepare("SELECT is_admin, admin_level FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        // Verify admin status
        $is_admin = ($user && ($user['is_admin'] == 1 || $user['admin_level'] > 0));
        if (!$is_admin) {
            $this->logSecurityEvent('Non-admin access attempt', $_SESSION['user_id']);
            header('Location: ../index.php?error=unauthorized');
            exit;
        }

        // Store admin status in session
        $_SESSION['is_admin'] = true;
        
        return true;
    }

    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            $this->logSecurityEvent('CSRF token validation failed', $_SESSION['user_id'] ?? null);
            return false;
        }
        return true;
    }

    public function regenerateSession() {
        // Regenerate session ID to prevent session fixation
        if (!isset($_SESSION['last_regeneration']) || 
            time() - $_SESSION['last_regeneration'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }

    public function checkPermission($permission) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT 1 FROM admin_permissions 
            WHERE user_id = ? AND permission_name = ?
        ");
        $stmt->bind_param("is", $_SESSION['user_id'], $permission);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    public function logSecurityEvent($event_type, $user_id) {
        $stmt = $this->conn->prepare("
            INSERT INTO security_logs (user_id, event_type, ip_address, user_agent) 
            VALUES (?, ?, ?, ?)
        ");
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $stmt->bind_param("isss", $user_id, $event_type, $ip, $user_agent);
        $stmt->execute();
    }

    public function logAdminActivity($action_type, $action_details, $affected_user_id = null) {
        if (!isset($_SESSION['user_id'])) {
            return;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO admin_activity_logs (admin_id, action_type, action_details, affected_user_id, ip_address) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $admin_id = $_SESSION['user_id'];
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param("issss", $admin_id, $action_type, $action_details, $affected_user_id, $ip);
        $stmt->execute();
    }
}
?>
