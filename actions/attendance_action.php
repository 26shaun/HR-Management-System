<?php
require_once __DIR__ . '/../config/db.php';
requireAuth();

$db = getDBConnection();
$currentUser = getCurrentUser();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$today = date('Y-m-d');
$nowTime = date('H:i:s');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Clock In
    if ($action === 'clock_in') {
        // Check if attendance entry already exists for today
        $stmt = $db->prepare("SELECT id, clock_in FROM attendance WHERE user_id = ? AND date = ? LIMIT 1");
        $stmt->execute([$currentUser['id'], $today]);
        $existing = $stmt->fetch();

        if ($existing) {
            $_SESSION['flash_info'] = "You have already clocked in today at " . formatTime($existing['clock_in']) . ".";
        } else {
            // Determine if late (e.g. after 09:15 AM)
            $status = (strtotime($nowTime) > strtotime('09:15:00')) ? 'late' : 'present';

            $insertStmt = $db->prepare("
                INSERT INTO attendance (user_id, date, clock_in, status)
                VALUES (?, ?, ?, ?)
            ");
            $insertStmt->execute([$currentUser['id'], $today, $nowTime, $status]);
            $_SESSION['flash_success'] = "Clocked in successfully at " . formatTime($nowTime) . " (" . ucfirst($status) . ").";
        }

        header("Location: ../employee_dashboard.php");
        exit;
    }

    // 2. Clock Out
    if ($action === 'clock_out') {
        $stmt = $db->prepare("SELECT id, clock_in, clock_out FROM attendance WHERE user_id = ? AND date = ? LIMIT 1");
        $stmt->execute([$currentUser['id'], $today]);
        $existing = $stmt->fetch();

        if (!$existing) {
            $_SESSION['flash_error'] = "You have not clocked in today yet!";
        } elseif (!empty($existing['clock_out'])) {
            $_SESSION['flash_info'] = "You have already clocked out for today at " . formatTime($existing['clock_out']) . ".";
        } else {
            $clockInTime = strtotime($existing['clock_in']);
            $clockOutTime = strtotime($nowTime);
            $secondsDiff = max(0, $clockOutTime - $clockInTime);
            $hours = round($secondsDiff / 3600, 2);

            $updateStmt = $db->prepare("
                UPDATE attendance 
                SET clock_out = ?, total_hours = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$nowTime, $hours, $existing['id']]);
            $_SESSION['flash_success'] = "Clocked out successfully at " . formatTime($nowTime) . ". Total time worked: " . $hours . " hrs.";
        }

        header("Location: ../employee_dashboard.php");
        exit;
    }
}

// Default fallback
header("Location: ../employee_dashboard.php");
exit;
