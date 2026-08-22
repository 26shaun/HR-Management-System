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

        // Notify HR Admins
        notifyHRAdmins(
            "📋 New Leave Application",
            $currentUser['name'] . " applied for " . $leaveType . " (" . $totalDays . " days: " . formatNiceDate($startDate) . " to " . formatNiceDate($endDate) . ").",
            'leave_applied',
            'hr_dashboard.php#leavesSection'
        );

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

        // Fetch leave details to notify the employee
        $leaveDetails = $db->prepare("SELECT user_id, leave_type, start_date, end_date FROM leaves WHERE id = ?");
        $leaveDetails->execute([$leaveId]);
        $leave = $leaveDetails->fetch();

        $stmt = $db->prepare("
            UPDATE leaves 
            SET status = ?, reviewed_by = ?, review_comment = ? 
            WHERE id = ?
        ");
        $stmt->execute([$status, $currentUser['id'], $comment, $leaveId]);

        // Notify Employee
        if ($leave) {
            $statusEmoji = ($status === 'approved') ? '✅' : '❌';
            $msg = "Your {$leave['leave_type']} request (" . formatNiceDate($leave['start_date']) . " to " . formatNiceDate($leave['end_date']) . ") was " . strtoupper($status) . " by HR.";
            if (!empty($comment)) {
                $msg .= " Note: " . $comment;
            }
            createNotification(
                $leave['user_id'],
                "{$statusEmoji} Leave Request " . ucfirst($status),
                $msg,
                ($status === 'approved' ? 'leave_approved' : 'leave_rejected'),
                'employee_dashboard.php#myLeavesSection'
            );
        }

        $_SESSION['flash_success'] = "Leave request marked as " . ucfirst($status) . ".";
        header("Location: ../hr_dashboard.php#leavesSection");
        exit;
    }
}

// Redirect back if reached directly
header("Location: ../index.php");
exit;
