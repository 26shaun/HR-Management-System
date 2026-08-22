<?php
/**
 * Dayflow HRMS - Database Configuration & Helpers
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hrms');
define('APP_NAME', 'Dayflow');
define('APP_URL', 'http://localhost/HR Resource management system');

/**
 * Get PDO Database Connection
 * Auto-initializes schema and demo data on first run if needed
 */
function getDBConnection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        // First try connecting directly to the hrms database
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // If database doesn't exist, connect to server and create database & tables
        try {
            $rootDsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
            $rootPdo = new PDO($rootDsn, DB_USER, DB_PASS);
            $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $rootPdo->exec("USE `" . DB_NAME . "`;");

            // Execute database.sql content if present
            $sqlFile = __DIR__ . '/../database.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $rootPdo->exec($sql);
            }

            // Now connect with standard PDO
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return $pdo;
        } catch (Exception $ex) {
            // Return null or handle gracefully with an error screen
            error_log("Database connection error: " . $ex->getMessage());
            return null;
        }
    }
}

/**
 * Authentication Helpers
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isHR() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'hr';
}

function isEmployee() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'employee';
}

function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = "Please log in to access this page.";
        header("Location: login.php");
        exit;
    }
}

function requireHR() {
    requireAuth();
    if (!isHR()) {
        $_SESSION['flash_error'] = "Access restricted. HR administrator rights required.";
        header("Location: employee_dashboard.php");
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $db = getDBConnection();
    if (!$db) return null;
    
    $stmt = $db->prepare("
        SELECT u.*, d.name AS department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id 
        WHERE u.id = ? LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Format Date Helper
 */
function formatNiceDate($dateStr) {
    if (empty($dateStr)) return '-';
    $timestamp = strtotime($dateStr);
    return date('M d, Y', $timestamp);
}

function formatTime($timeStr) {
    if (empty($timeStr)) return '-';
    return date('h:i A', strtotime($timeStr));
}
?>
