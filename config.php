<?php
// config.php - Database Configuration and Constants

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'library_management');

// Application Configuration
define('SITE_URL', 'http://localhost/library_management');
define('SITE_NAME', 'Library Management System');
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// Default Settings (can be overridden by database)
define('DEFAULT_BORROWING_DAYS', 14);
define('DEFAULT_MAX_BOOKS', 5);
define('DEFAULT_FINE_PER_DAY', 5.00);

// User Roles
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_ADMIN', 'admin');
define('ROLE_STAFF', 'staff');
define('ROLE_USER', 'user');

// Database Connection Class
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            
            $this->conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            die("Database connection error: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function query($sql) {
        return $this->conn->query($sql);
    }
    
    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }
    
    public function escape($value) {
        return $this->conn->real_escape_string($value);
    }
    
    public function lastInsertId() {
        return $this->conn->insert_id;
    }
    
    public function affectedRows() {
        return $this->conn->affected_rows;
    }
    
    // Prevent cloning
    private function __clone() {}
}

// Helper Functions
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['role'], $roles);
}

function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        redirect('unauthorized.php');
    }
}

function flashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

function formatDate($date, $format = 'Y-m-d') {
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    return date($format, strtotime($date));
}

function calculateFine($dueDate, $returnDate = null) {
    $return = $returnDate ?? date('Y-m-d');
    $due = new DateTime($dueDate);
    $ret = new DateTime($return);
    
    if ($ret <= $due) {
        return 0;
    }
    
    $interval = $due->diff($ret);
    $daysOverdue = $interval->days;
    
    return $daysOverdue * DEFAULT_FINE_PER_DAY;
}

function logActivity($userId, $action, $entityType = null, $entityId = null, $description = null) {
    $db = Database::getInstance();
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $userId, $action, $entityType, $entityId, $description, $ipAddress);
    $stmt->execute();
    $stmt->close();
}

function jsonResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Auto-create upload directory
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
    mkdir(UPLOAD_PATH . 'books', 0755, true);
    mkdir(UPLOAD_PATH . 'profiles', 0755, true);
}
?>