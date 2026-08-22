<?php

require_once __DIR__ . '/../config/db.php';

requireAuth();

$db = getDBConnection();
$currentUser = getCurrentUser();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$today = date('Y-m-d');
$nowTime = date('H:i:s');

// Four hours minimum work requirement in seconds
$minimumWorkSeconds = 4 * 60 * 60;

// Return HR users to HR dashboard
$dashboardPage = isHR()
    ? '../hr_dashboard.php'
    : '../employee_dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check in
    if ($action === 'clock_in') {
        $statement = $db->prepare("
            SELECT id, clock_in
            FROM attendance
            WHERE user_id = ?
            AND date = ?
            LIMIT 1
        ");

        $statement->execute([
            $currentUser['id'],
            $today
        ]);

        $existingAttendance = $statement->fetch();

        if ($existingAttendance) {
            $_SESSION['flash_info'] =
                "You have already checked in today at " .
                formatTime($existingAttendance['clock_in']) .
                ".";
        } else {
            // Mark as late when checking in after 9:30 AM
            $isLate = strtotime($nowTime) > strtotime('09:30:00');
            $status = $isLate ? 'late' : 'present';

            $insertStatement = $db->prepare("
                INSERT INTO attendance (
                    user_id,
                    date,
                    clock_in,
                    status
                )
                VALUES (?, ?, ?, ?)
            ");

            $insertStatement->execute([
                $currentUser['id'],
                $today,
                $nowTime,
                $status
            ]);

            if ($isLate) {
                $_SESSION['flash_warning'] = "Checked in at " . formatTime($nowTime) . " (Marked as Late - Check-in cutoff is 9:30 AM).";
            } else {
                $_SESSION['flash_success'] = "Checked in successfully at " . formatTime($nowTime) . " (On Time).";
            }
        }

        header("Location: " . $dashboardPage);
        exit;
    }

    // Check out
    if ($action === 'clock_out') {
        $statement = $db->prepare("
            SELECT
                id,
                date,
                clock_in,
                clock_out,
                status
            FROM attendance
            WHERE user_id = ?
            AND date = ?
            LIMIT 1
        ");

        $statement->execute([
            $currentUser['id'],
            $today
        ]);

        $existingAttendance = $statement->fetch();

        if (!$existingAttendance) {
            $_SESSION['flash_error'] =
                "You must check in before checking out.";

            header("Location: " . $dashboardPage);
            exit;
        }

        if (!empty($existingAttendance['clock_out'])) {
            $_SESSION['flash_info'] =
                "You have already checked out today at " .
                formatTime($existingAttendance['clock_out']) .
                ".";

            header("Location: " . $dashboardPage);
            exit;
        }

        $checkInTimestamp = strtotime(
            $existingAttendance['date'] .
            ' ' .
            $existingAttendance['clock_in']
        );

        $currentTimestamp = strtotime($today . ' ' . $nowTime);
        $workedSeconds = max(0, $currentTimestamp - $checkInTimestamp);
        $totalHours = round($workedSeconds / 3600, 2);

        // Auto-determine status: if worked less than 4 hours, record as half_day
        $finalStatus = $existingAttendance['status'] ?? 'present';
        if ($workedSeconds < (4 * 3600)) {
            $finalStatus = 'half_day';
        }

        $updateStatement = $db->prepare("
            UPDATE attendance
            SET
                clock_out = ?,
                total_hours = ?,
                status = ?
            WHERE id = ?
        ");

        $updateStatement->execute([
            $nowTime,
            $totalHours,
            $finalStatus,
            $existingAttendance['id']
        ]);

        if ($finalStatus === 'half_day') {
            $_SESSION['flash_warning'] = "Checked out at " . formatTime($nowTime) . " (Worked " . $totalHours . " hrs - Recorded as Half-Day).";
        } else {
            $_SESSION['flash_success'] = "Checked out successfully at " . formatTime($nowTime) . ". Total shift time logged: " . $totalHours . " hrs.";
        }

        header("Location: " . $dashboardPage);
        exit;
    }
}

header("Location: " . $dashboardPage);
exit;