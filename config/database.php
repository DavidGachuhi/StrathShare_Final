<?php


// Database credentials - UPDATE THESE IF YOUR SETUP IS DIFFERENT
define('DB_HOST', 'localhost');
define('DB_NAME', 'strathshare_db');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP default is empty password

/**
 * Database Class - PDO Connection Handler
 * Provides secure database connection with error handling
 */
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $conn = null;

    /**
     * Get PDO database connection
     * @return PDO|null Database connection or null on failure
     */
    public function getConnection() {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $e) {
            // Log error but don't expose details to user
            error_log("Database Connection Error: " . $e->getMessage());
            
            // In development, you can uncomment this to see the error:
            // echo "Connection Error: " . $e->getMessage();
            
            return null;
        }

        return $this->conn;
    }
    
    /**
     * Close database connection
     */
    public function closeConnection() {
        $this->conn = null;
    }
}

/**
 * Create global $pdo variable for backward compatibility
 * This allows existing code using $pdo to continue working
 */
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if ($pdo === null) {
        // If connection fails, set error flag
        $db_connection_error = true;
    }
} catch (Exception $e) {
    error_log("Global PDO initialization error: " . $e->getMessage());
    $pdo = null;
    $db_connection_error = true;
}

/**
 * MySQLi connection for any legacy code that needs it
 * Most of our code uses PDO, but this is here just in case
 */
function getMySQLiConnection() {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        error_log("MySQLi Connection Error: " . $mysqli->connect_error);
        return null;
    }
    
    $mysqli->set_charset("utf8mb4");
    return $mysqli;
}

/**
 * Helper function to check if database is connected
 * @return bool True if connected, false otherwise
 */
function isDatabaseConnected() {
    global $pdo;
    return ($pdo !== null);
}

/**
 * Helper function for safe string escaping (XSS prevention)
 * Use this when outputting user data to HTML
 * @param string $string Input string
 * @return string Escaped string safe for HTML output
 */
function safeOutput($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Helper function to sanitize input
 * Use this for cleaning user input before processing
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}
?>
