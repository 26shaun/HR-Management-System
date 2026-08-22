<?php

require_once __DIR__ . '/../config/db.php';

requireAuth();

$db = getDBConnection();
$currentUser = getCurrentUser();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$today = date('Y-m-d');
$nowTime = date('H:i:s');

// Employees and HR must work at least six hours before checking out.
$minimumWorkSeconds = 6 * 60 * 60;

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

        // Enforce the six-hour rule on the server. Hiding or enabling the
        // dashboard button must never be the only protection.
        if ($workedSeconds < $minimumWorkSeconds) {
            $remainingSeconds = $minimumWorkSeconds - $workedSeconds;
            $remainingHours = intdiv($remainingSeconds, 3600);
            $remainingMinutes = (int) ceil(($remainingSeconds % 3600) / 60);

            // Avoid displaying "60 minutes" after rounding.
            if ($remainingMinutes === 60) {
                $remainingHours++;
                $remainingMinutes = 0;
            }

            $remainingText = [];
            if ($remainingHours > 0) {
                $remainingText[] = $remainingHours .
                    ($remainingHours === 1 ? ' hour' : ' hours');
            }
            if ($remainingMinutes > 0) {
                $remainingText[] = $remainingMinutes .
                    ($remainingMinutes === 1 ? ' minute' : ' minutes');
            }

            $_SESSION['flash_error'] =
                "Checkout is available only after completing 6 hours. " .
                "Please wait " . implode(' and ', $remainingText) . ".";

            header("Location: " . $dashboardPage);
            exit;
        }

        $totalHours = round($workedSeconds / 3600, 2);

        // Preserve the check-in status after the minimum shift is completed.
        $finalStatus = $existingAttendance['status'] ?? 'present';

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

        $_SESSION['flash_success'] =
            "Checked out successfully at " . formatTime($nowTime) .
            ". Total shift time logged: " . $totalHours . " hrs.";

        header("Location: " . $dashboardPage);
        exit;
    }
}

header("Location: " . $dashboardPage);
exit;