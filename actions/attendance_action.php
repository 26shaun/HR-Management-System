<?php

require_once __DIR__ . '/../config/db.php';

requireAuth();

$db = getDBConnection();
$currentUser = getCurrentUser();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$today = date('Y-m-d');
$nowTime = date('H:i:s');

// Six hours in seconds
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
            // Mark as late when checking in after 9:15 AM
            $status = strtotime($nowTime) > strtotime('09:15:00')
                ? 'late'
                : 'present';

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

            $_SESSION['flash_success'] =
                "Checked in successfully at " .
                formatTime($nowTime) .
                ".";
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
                clock_out
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

        $currentTimestamp = time();

        $workedSeconds =
            $currentTimestamp - $checkInTimestamp;

        // Prevent checkout before six hours
        if ($workedSeconds < $minimumWorkSeconds) {
            $minimumCheckoutTimestamp =
                $checkInTimestamp + $minimumWorkSeconds;

            $remainingSeconds =
                $minimumWorkSeconds - $workedSeconds;

            $remainingHours = intdiv(
                $remainingSeconds,
                3600
            );

            $remainingMinutes = (int)ceil(
                ($remainingSeconds % 3600) / 60
            );

            if ($remainingMinutes === 60) {
                $remainingHours++;
                $remainingMinutes = 0;
            }

            $_SESSION['flash_error'] =
                "You can check out only after completing 6 hours. " .
                "Checkout will be available at " .
                date('h:i A', $minimumCheckoutTimestamp) .
                ". Remaining time: " .
                $remainingHours .
                " hour(s) and " .
                $remainingMinutes .
                " minute(s).";

            header("Location: " . $dashboardPage);
            exit;
        }

        $totalHours = round(
            $workedSeconds / 3600,
            2
        );

        $updateStatement = $db->prepare("
            UPDATE attendance
            SET
                clock_out = ?,
                total_hours = ?
            WHERE id = ?
        ");

        $updateStatement->execute([
            $nowTime,
            $totalHours,
            $existingAttendance['id']
        ]);

        $_SESSION['flash_success'] =
            "Checked out successfully at " .
            formatTime($nowTime) .
            ". Total time worked: " .
            $totalHours .
            " hours.";

        header("Location: " . $dashboardPage);
        exit;
    }
}

header("Location: " . $dashboardPage);
exit;