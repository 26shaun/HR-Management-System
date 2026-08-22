<?php
require_once __DIR__ . '/../config/db.php';
requireAuth();

$db = getDBConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Employee applies for leave
    if ($action === 'apply_leave') {
        $leaveType = $_POST['leave_type'] ?? 'Casual Leave';
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $reason = trim($_POST['reason'] ?? '');

        if (empty($startDate) || empty($endDate) || empty($reason)) {
            $_SESSION['flash_error'] = "Please fill in all required leave application fields.";
            header("Location: ../employee_dashboard.php");
            exit;
        }

        // Calculate days
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        if ($end < $start) {
            $_SESSION['flash_error'] = "End date cannot be earlier than start date.";
            header("Location: ../employee_dashboard.php");
            exit;
        }
        $diff = $start->diff($end);
        $totalDays = $diff->days + 1;

        $stmt = $db->prepare("
            INSERT INTO leaves (user_id, leave_type, start_date, end_date, total_days, reason, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$currentUser['id'], $leaveType, $startDate, $endDate, $totalDays, $reason]);

        $_SESSION['flash_success'] = "Leave request submitted successfully for approval.";
        header("Location: ../employee_dashboard.php#myLeavesSection");
        exit;
    }

    // 2. HR approves or rejects leave
    if ($action === 'review_leave') {
        requireHR();
        $leaveId = (int)($_POST['leave_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        $comment = trim($_POST['review_comment'] ?? '');

        if (!in_array($status, ['approved', 'rejected'])) {
            $_SESSION['flash_error'] = "Invalid leave status action.";
            header("Location: ../hr_dashboard.php");
            exit;
        }

        $stmt = $db->prepare("
            UPDATE leaves 
            SET status = ?, reviewed_by = ?, review_comment = ? 
            WHERE id = ?
        ");
        $stmt->execute([$status, $currentUser['id'], $comment, $leaveId]);

        $_SESSION['flash_success'] = "Leave request marked as " . ucfirst($status) . ".";
        header("Location: ../hr_dashboard.php#leavesSection");
        exit;
    }
}

// Redirect back if reached directly
header("Location: ../index.php");
exit;
