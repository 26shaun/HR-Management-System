<?php

date_default_timezone_set('Asia/Kolkata');
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

// SMTP Email Configuration (Set your SMTP credentials to send real emails to inboxes)
define('SMTP_HOST', 'smtp.gmail.com');          // e.g. smtp.gmail.com or smtp.office365.com or live SMTP host
define('SMTP_PORT', 587);                      // 587 (TLS) or 465 (SSL)
define('SMTP_SECURE', 'tls');                  // 'tls' or 'ssl'
define('SMTP_USER', 'hrmanagement381@gmail.com');                       // Your email address (e.g. yourname@gmail.com)
define('SMTP_PASS', 'rtni eukc doyz gxhh');                       // Your email app password (e.g. Google 16-character App Password)
define('SMTP_FROM_NAME', 'Dayflow HRMS');

/**
 * Get PDO Database Connection
 * Auto-initializes schema and demo data on first run if needed
 */
function getDBConnection()
{
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
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isHR()
{
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'hr';
}

function isEmployee()
{
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'employee';
}

function requireAuth()
{
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = "Please log in to access this page.";
        header("Location: login.php");
        exit;
    }
}

function requireHR()
{
    requireAuth();
    if (!isHR()) {
        $_SESSION['flash_error'] = "Access restricted. HR administrator rights required.";
        header("Location: employee_dashboard.php");
        exit;
    }
}

function getCurrentUser()
{
    if (!isLoggedIn())
        return null;
    $db = getDBConnection();
    if (!$db)
        return null;

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
function formatNiceDate($dateStr)
{
    if (empty($dateStr))
        return '-';
    $timestamp = strtotime($dateStr);
    return date('M d, Y', $timestamp);
}

function formatTime($timeStr)
{
    if (empty($timeStr))
        return '-';
    return date('h:i A', strtotime($timeStr));
}

/**
 * In-Website Notification Helpers
 */
function createNotification($userId, $title, $message, $type = 'general', $link = null)
{
    $db = getDBConnection();
    if (!$db) return false;
    
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    return $stmt->execute([$userId, $title, $message, $type, $link]);
}

function broadcastNotification($title, $message, $type = 'announcement', $link = null)
{
    $db = getDBConnection();
    if (!$db) return false;

    // Get all user IDs
    $users = $db->query("SELECT id FROM users WHERE status = 'active'")->fetchAll();
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    foreach ($users as $user) {
        $stmt->execute([$user['id'], $title, $message, $type, $link]);
    }
    return true;
}

function notifyHRAdmins($title, $message, $type = 'leave_applied', $link = null)
{
    $db = getDBConnection();
    if (!$db) return false;

    $hrUsers = $db->query("SELECT id FROM users WHERE role = 'hr'")->fetchAll();
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    foreach ($hrUsers as $hr) {
        $stmt->execute([$hr['id'], $title, $message, $type, $link]);
    }
    return true;
}

function getUserNotifications($userId, $limit = 10)
{
    $db = getDBConnection();
    if (!$db) return [];

    $stmt = $db->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT " . (int)$limit
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getUnreadNotificationCount($userId)
{
    $db = getDBConnection();
    if (!$db) return 0;

    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Send OTP Verification Email
 */
function sendOtpEmail($toEmail, $toName, $otpCode)
{
    require_once __DIR__ . '/smtp_mailer.php';

    $subject = "Your Dayflow Account Verification Code: " . $otpCode;

    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Email Verification</title>
    </head>
    <body style="font-family: Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px;">
        <div style="max-width: 500px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 30px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
            <div style="text-align: center; margin-bottom: 20px;">
                <h2 style="color: #6366f1; margin: 0; font-size: 24px;">Dayflow HRMS</h2>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Workforce & Resource Management</p>
            </div>
            <p style="font-size: 15px; color: #1e293b;">Hello <strong>' . htmlspecialchars($toName) . '</strong>,</p>
            <p style="font-size: 14px; color: #475569; line-height: 1.6;">Thank you for registering with Dayflow. Please use the verification code below to verify your email address and activate your account:</p>
            <div style="text-align: center; margin: 25px 0;">
                <div style="display: inline-block; background: #f1f5f9; border: 2px dashed #6366f1; border-radius: 10px; padding: 12px 30px; font-size: 32px; font-weight: 800; letter-spacing: 6px; color: #4f46e5; font-family: monospace;">
                    ' . htmlspecialchars($otpCode) . '
                </div>
            </div>
            <p style="font-size: 13px; color: #64748b; line-height: 1.5;">This OTP is valid for <strong>10 minutes</strong>. If you did not request this registration, please ignore this email.</p>
            <hr style="border: none; border-top: 1px solid #f1f5f9; margin: 25px 0;">
            <p style="font-size: 12px; color: #94a3b8; text-align: center; margin: 0;">&copy; ' . date('Y') . ' Dayflow HRMS. All rights reserved.</p>
        </div>
    </body>
    </html>';

    // Dispatch via SMTP mailer
    $mailer = new DayflowSMTPMailer(
        SMTP_HOST,
        SMTP_PORT,
        SMTP_USER,
        SMTP_PASS,
        SMTP_SECURE,
        SMTP_USER,
        SMTP_FROM_NAME
    );

    return $mailer->send($toEmail, $toName, $subject, $message);
}
?>