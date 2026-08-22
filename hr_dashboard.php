<?php
require_once __DIR__ . '/config/db.php';
requireHR();

$pageTitle = "HR Command Center";
$pageSubtitle = "Workforce overview, leave approvals, and employee operations";

$db = getDBConnection();
$currentUser = getCurrentUser();
$today = date('Y-m-d');

// Get today's attendance for the logged-in HR user
$hrAttendanceStatement = $db->prepare("
    SELECT *
    FROM attendance
    WHERE user_id = ?
    AND date = ?
    LIMIT 1
");

$hrAttendanceStatement->execute([
    $currentUser['id'],
    $today
]);

$hrTodayAttendance = $hrAttendanceStatement->fetch();
// 1. Fetch Metrics
$totalEmployees = $db->query("SELECT COUNT(*) FROM users WHERE role = 'employee'")->fetchColumn();
$presentToday = $db->query("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE date = '$today'")->fetchColumn();
$pendingLeaves = $db->query("SELECT COUNT(*) FROM leaves WHERE status = 'pending'")->fetchColumn();
$totalDepartments = $db->query("SELECT COUNT(*) FROM departments")->fetchColumn();

// 2. Fetch Employees
$employeesStmt = $db->query("
    SELECT u.*, d.name AS department_name 
    FROM users u 
    LEFT JOIN departments d ON u.department_id = d.id 
    ORDER BY u.id DESC
");
$employees = $employeesStmt->fetchAll();
foreach ($employees as &$emp) {
    $emp['leave_balance'] = getUserLeaveBalance($emp['id']);
}
unset($emp);

// 3. Fetch Pending & Recent Leaves
$leavesStmt = $db->query("
    SELECT l.*, u.name AS employee_name, u.email AS employee_email, d.name AS department_name, r.name AS reviewer_name
    FROM leaves l
    JOIN users u ON l.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN users r ON l.reviewed_by = r.id
    ORDER BY CASE WHEN l.status = 'pending' THEN 1 ELSE 2 END, l.created_at DESC
");
$leaves = $leavesStmt->fetchAll();

// 4. Fetch Attendance Logs with Filters
$hasAttFilter = isset($_GET['att_filter_applied']) || isset($_GET['att_date_from']) || isset($_GET['att_search']) || isset($_GET['att_dept']) || isset($_GET['att_status']);

$attDateFrom = isset($_GET['att_date_from']) ? trim($_GET['att_date_from']) : ($hasAttFilter ? '' : $today);
$attDateTo = isset($_GET['att_date_to']) ? trim($_GET['att_date_to']) : ($hasAttFilter ? '' : $today);
$attSearch = trim($_GET['att_search'] ?? '');
$attDept = trim($_GET['att_dept'] ?? '');
$attStatus = trim($_GET['att_status'] ?? '');

$attWhere = [];
$attParams = [];

if ($attDateFrom !== '') {
    $attWhere[] = "a.date >= ?";
    $attParams[] = $attDateFrom;
}
if ($attDateTo !== '') {
    $attWhere[] = "a.date <= ?";
    $attParams[] = $attDateTo;
}
if ($attSearch !== '') {
    $attWhere[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $attParams[] = "%$attSearch%";
    $attParams[] = "%$attSearch%";
}
if ($attDept !== '') {
    $attWhere[] = "u.department_id = ?";
    $attParams[] = $attDept;
}
if ($attStatus !== '') {
    $attWhere[] = "a.status = ?";
    $attParams[] = $attStatus;
}

$attWhereClause = !empty($attWhere) ? "WHERE " . implode(" AND ", $attWhere) : "";

$attendanceStmt = $db->prepare("
    SELECT a.*, u.name AS employee_name, u.email AS employee_email, d.name AS department_name
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    $attWhereClause
    ORDER BY a.date DESC, a.clock_in DESC
");
$attendanceStmt->execute($attParams);
$attendanceLogs = $attendanceStmt->fetchAll();
$todayAttendance = $attendanceLogs;

// Filter summary counts
$attStats = [
    'total' => count($attendanceLogs),
    'present' => 0,
    'late' => 0,
    'half_day' => 0,
    'on_leave' => 0,
    'absent' => 0
];
foreach ($attendanceLogs as $log) {
    $st = strtolower($log['status'] ?? 'present');
    if (isset($attStats[$st])) {
        $attStats[$st]++;
    }
}

// 5. Fetch Announcements
$announcementsStmt = $db->query("
    SELECT a.*, u.name AS author_name 
    FROM announcements a 
    JOIN users u ON a.created_by = u.id 
    ORDER BY a.created_at DESC
");
$announcements = $announcementsStmt->fetchAll();

// 6. Fetch Departments for dropdown
$deptStmt = $db->query("SELECT id, name FROM departments ORDER BY name ASC");
$departments = $deptStmt->fetchAll();

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
$flashInfo = $_SESSION['flash_info'] ?? null;

unset(
    $_SESSION['flash_success'],
    $_SESSION['flash_error'],
    $_SESSION['flash_info']
);

include __DIR__ . '/includes/header.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <main class="content-body">
            <!-- Flash Notification Messages -->
            <?php if ($flashSuccess): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?= htmlspecialchars($flashSuccess) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($flashError) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($flashInfo): ?>
    <div class="alert alert-info">
        <i class="fa-solid fa-circle-info"></i>
        <span><?= htmlspecialchars($flashInfo) ?></span>
    </div>
<?php endif; ?>

            <!-- Stat Metric Cards -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-icon-wrapper purple">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?= $totalEmployees ?></div>
                        <div class="stat-title">Total Employees</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon-wrapper green">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?= $presentToday ?></div>
                        <div class="stat-title">Present Today</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon-wrapper amber">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?= $pendingLeaves ?></div>
                        <div class="stat-title">Pending Leave Approvals</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon-wrapper blue">
                        <i class="fa-solid fa-sitemap"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?= $totalDepartments ?></div>
                        <div class="stat-title">Active Departments</div>
                    </div>
                </div>
            </div>
<!-- HR Attendance Card -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <div class="card-title">
            <i
                class="fa-solid fa-user-clock"
                style="color: var(--primary);"
            ></i>

            My Attendance
        </div>

        <?php if (!$hrTodayAttendance): ?>
            <span class="status-pill pending">
                Not checked in
            </span>
        <?php elseif (empty($hrTodayAttendance['clock_out'])): ?>
            <span class="status-pill active">
                Working
            </span>
        <?php else: ?>
            <span class="status-pill approved">
                Completed
            </span>
        <?php endif; ?>
    </div>

    <div
        style="
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
            padding: 0.5rem;
        "
    >
        <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
            <div>
                <div
                    style="
                        color: var(--text-muted);
                        font-size: 0.75rem;
                        margin-bottom: 0.3rem;
                    "
                >
                    CHECK-IN TIME
                </div>

                <strong style="font-size: 1.1rem;">
                    <?=
                        $hrTodayAttendance
                            ? formatTime(
                                $hrTodayAttendance['clock_in']
                            )
                            : '--:--'
                    ?>
                </strong>
            </div>

            <div>
                <div
                    style="
                        color: var(--text-muted);
                        font-size: 0.75rem;
                        margin-bottom: 0.3rem;
                    "
                >
                    CHECK-OUT TIME
                </div>

                <strong style="font-size: 1.1rem;">
                    <?=
                        !empty($hrTodayAttendance['clock_out'])
                            ? formatTime(
                                $hrTodayAttendance['clock_out']
                            )
                            : '--:--'
                    ?>
                </strong>
            </div>

            <div>
                <div
                    style="
                        color: var(--text-muted);
                        font-size: 0.75rem;
                        margin-bottom: 0.3rem;
                    "
                >
                    TOTAL HOURS
                </div>

                <strong style="font-size: 1.1rem;">
                    <?=
                        !empty($hrTodayAttendance['clock_out'])
                            ? htmlspecialchars(
                                $hrTodayAttendance['total_hours']
                            ) . ' hrs'
                            : '--'
                    ?>
                </strong>
            </div>
        </div>

        <div>
            <?php if (!$hrTodayAttendance): ?>
                <form
                    action="actions/attendance_action.php"
                    method="POST"
                >
                    <input
                        type="hidden"
                        name="action"
                        value="clock_in"
                    >

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="fa-solid fa-right-to-bracket"></i>
                        Check In
                    </button>
                </form>

            <?php elseif (empty($hrTodayAttendance['clock_out'])): ?>
                <form
                    action="actions/attendance_action.php"
                    method="POST"
                >
                    <input
                        type="hidden"
                        name="action"
                        value="clock_out"
                    >

                    <button
                        type="submit"
                        class="btn btn-danger"
                    >
                        <i class="fa-solid fa-right-from-bracket"></i>
                        Check Out
                    </button>
                </form>

                <p
                    style="
                        margin-top: 0.5rem;
                        color: var(--text-muted);
                        font-size: 0.7rem;
                    "
                >
                    Checkout is allowed after completing six hours.
                </p>

            <?php else: ?>
                <span style="color: var(--text-muted);">
                    Attendance completed for today
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>
            <!-- Quick Action Bar -->
            <div style="display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap;">
                <button class="btn btn-primary" onclick="openModal('addEmployeeModal')">
                    <i class="fa-solid fa-user-plus"></i> Add New Employee
                </button>
                <button class="btn btn-secondary" onclick="openModal('addAnnouncementModal')">
                    <i class="fa-solid fa-bullhorn"></i> Post Announcement
                </button>
            </div>

            <!-- Leave Requests Review Section -->
            <div class="card" id="leavesSection" style="margin-bottom: 2rem;">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-calendar-check" style="color: var(--primary);"></i>
                        Leave Applications & Approvals
                    </div>
                    <span class="status-pill pending"><?= $pendingLeaves ?> Pending Review</span>
                </div>

                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Leave Type</th>
                                <th>Duration</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (empty($leaves)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                        No leave applications submitted yet.
                                    </td>
                                </tr>
                            <?php else: ?>

                                <?php foreach ($leaves as $l): ?>
                                    <tr>
                                        <td>
                                            <strong>
                                                <?= htmlspecialchars($l['employee_name']) ?>
                                            </strong>

                                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                <?= htmlspecialchars($l['employee_email']) ?>
                                            </div>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $l['department_name'] ?? 'General'
                                            ) ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= htmlspecialchars($l['leave_type']) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= formatNiceDate($l['start_date']) ?>
                                            to
                                            <?= formatNiceDate($l['end_date']) ?>

                                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                <?= (int) $l['total_days'] ?> day(s)
                                            </div>
                                        </td>

                                        <td style="max-width: 250px;">
                                            <span title="<?= htmlspecialchars($l['reason']) ?>">
                                                <?= htmlspecialchars(
                                                    mb_strimwidth(
                                                        $l['reason'],
                                                        0,
                                                        45,
                                                        '...'
                                                    )
                                                ) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="status-pill <?= htmlspecialchars(
                                                $l['status']
                                            ) ?>">
                                                <?= ucfirst($l['status']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php 
                                                $isPaidLeave = ($l['leave_category'] ?? ($l['is_paid'] ?? 1) == 1) === 'paid' || ($l['is_paid'] ?? 1) == 1;
                                            ?>
                                            <?php if ($isPaidLeave): ?>
                                                <span class="status-pill present" style="font-size: 0.75rem; padding: 3px 8px;">
                                                    <i class="fa-solid fa-circle-check"></i> Paid Leave
                                                </span>
                                            <?php else: ?>
                                                <span class="status-pill pending" style="font-size: 0.75rem; padding: 3px 8px; background: rgba(245, 158, 11, 0.15); color: #b45309;">
                                                    <i class="fa-solid fa-coins"></i> Unpaid (LWP)
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if ($l['status'] === 'pending'): ?>
                                                <div style="display: flex; gap: 0.4rem; align-items: flex-start;">
                                                    <!-- Approve form -->
                                                    <form action="actions/leave_action.php" method="POST">
                                                        <input type="hidden" name="action" value="review_leave">

                                                        <input type="hidden" name="leave_id" value="<?= (int) $l['id'] ?>">

                                                        <input type="hidden" name="status" value="approved">

                                                        <input type="hidden" name="review_comment" value="Approved by HR.">

                                                        <select name="payment_type" class="form-control" required
                                                            style="min-width: 145px; padding: 0.45rem; margin-bottom: 0.4rem;">
                                                            <option value="">
                                                                Select payment
                                                            </option>

                                                            <option value="paid">
                                                                Paid Leave
                                                            </option>

                                                            <option value="unpaid">
                                                                Unpaid Leave
                                                            </option>
                                                        </select>

                                                        <button type="submit" class="btn btn-success btn-sm" title="Approve">
                                                            <i class="fa-solid fa-check"></i>
                                                            Approve
                                                        </button>
                                                    </form>

                                                
                                                   <!-- Reject button -->
<details class="reject-details">
    <summary class="btn btn-danger btn-sm">
        <i class="fa-solid fa-xmark"></i>
        Reject
    </summary>

    <form
        action="actions/leave_action.php"
        method="POST"
        onsubmit="return prepareRejection(this)"
        
        style="
            margin-top: 0.7rem;
            min-width: 230px;
            padding: 0.75rem;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background: #fff7f7;
        "
    >
        <input
            type="hidden"
            name="action"
            value="review_leave"
        >

        <input
            type="hidden"
            name="leave_id"
            value="<?= (int)$l['id'] ?>"
        >

        <input
            type="hidden"
            name="status"
            value="rejected"
        >

        <label
            for="rejectComment<?= (int)$l['id'] ?>"
            style="
                display: block;
                margin-bottom: 0.4rem;
                font-size: 0.8rem;
                font-weight: 600;
            "
        >
            Reason for rejection
        </label>

        <textarea
            id="rejectComment<?= (int)$l['id'] ?>"
            name="review_comment"
            class="form-control"
            rows="3"
            minlength="5"
            placeholder="Explain why this leave is being rejected"
            required
            style="margin-bottom: 0.6rem;"
        ></textarea>

        <button
            type="submit"
            class="btn btn-danger btn-sm"
        >
            Confirm rejection
        </button>
    </form>
</details>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-size: 0.8rem; color: var(--text-muted);">
                                                Reviewed by <?= htmlspecialchars($l['reviewer_name'] ?? 'HR') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

            <div class="grid-3">
                <!-- Employee Directory -->
                <div class="card" id="employeesSection">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fa-solid fa-users-gear" style="color: var(--primary);"></i>
                            Employee Directory
                        </div>
                        <button class="btn btn-secondary btn-sm" onclick="openModal('addEmployeeModal')">
                            <i class="fa-solid fa-plus"></i> New
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Role & Dept</th>
                                    <th>Designation</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $emp): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;" onclick='openViewEmployeeModal(<?= htmlspecialchars(json_encode($emp), ENT_QUOTES, 'UTF-8') ?>)' title="Click to view full employee profile">
                                                <div class="user-mini-avatar"
                                                    style="width: 36px; height: 36px; font-size: 0.85rem; background: var(--primary-gradient); color: #ffffff; cursor: pointer;">
                                                    <?= strtoupper(substr($emp['name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--primary); text-decoration: underline; text-underline-offset: 2px;">
                                                        <?= htmlspecialchars($emp['name']) ?>
                                                    </div>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                        <?= htmlspecialchars($emp['email']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="role-badge <?= $emp['role'] ?>"
                                                style="font-size: 0.7rem; padding: 2px 8px;">
                                                <?= strtoupper($emp['role']) ?>
                                            </span>
                                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                                                <?= htmlspecialchars($emp['department_name'] ?? 'Unassigned') ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($emp['designation'] ?? 'Staff') ?></td>
                                        <td>
                                            <span class="status-pill <?= $emp['status'] ?>">
                                                <?= ucfirst($emp['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.35rem;">
                                                <button type="button" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 0.75rem;" onclick='openViewEmployeeModal(<?= htmlspecialchars(json_encode($emp), ENT_QUOTES, 'UTF-8') ?>)' title="View Full Details">
                                                    <i class="fa-regular fa-eye"></i> Details
                                                </button>
                                                <?php if ($emp['id'] !== $currentUser['id']): ?>
                                                    <form action="actions/employee_action.php" method="POST"
                                                        onsubmit="return confirm('Are you sure you want to remove this employee account?');"
                                                        style="display: inline;">
                                                        <input type="hidden" name="action" value="delete_employee">
                                                        <input type="hidden" name="user_id" value="<?= $emp['id'] ?>">
                                                        <button type="submit" class="btn btn-secondary btn-sm"
                                                            style="color: #ef4444; padding: 4px 8px;" title="Delete">
                                                            <i class="fa-regular fa-trash-can"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Today's Attendance Feed & Announcements -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <!-- Announcements Card -->
                    <div class="card" id="announcementsSection">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-bullhorn" style="color: var(--primary);"></i>
                                Company Announcements
                            </div>
                            <button class="btn btn-secondary btn-sm" onclick="openModal('addAnnouncementModal')">
                                <i class="fa-solid fa-plus"></i> Post
                            </button>
                        </div>

                        <div
                            style="display: flex; flex-direction: column; gap: 0.85rem; max-height: 280px; overflow-y: auto;">
                            <?php foreach ($announcements as $ann): ?>
                                <div
                                    style="padding: 0.85rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: #ffffff;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.35rem;">
                                        <strong style="font-size: 0.9rem;"><?= htmlspecialchars($ann['title']) ?></strong>
                                        <span
                                            class="status-pill <?= $ann['category'] === 'urgent' ? 'rejected' : ($ann['category'] === 'event' ? 'on_leave' : 'active') ?>"
                                            style="font-size: 0.65rem;">
                                            <?= ucfirst($ann['category']) ?>
                                        </span>
                                    </div>
                                    <p style="font-size: 0.825rem; color: var(--text-muted); margin-bottom: 0.4rem;">
                                        <?= nl2br(htmlspecialchars($ann['content'])) ?>
                                    </p>
                                    <div
                                        style="display: flex; justify-content: space-between; font-size: 0.725rem; color: var(--text-light);">
                                        <span>By <?= htmlspecialchars($ann['author_name']) ?></span>
                                        <span><?= formatNiceDate($ann['created_at']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Logs & Filter Center Section -->
            <div class="card" id="attendanceSection" style="margin-top: 2rem; margin-bottom: 2rem;">
                <div class="card-header" style="flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: center;">
                    <div class="card-title">
                        <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i>
                        Attendance Logs & Workforce Tracker
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <span class="status-pill active" style="font-weight: 600; font-size: 0.8rem;">
                            <i class="fa-solid fa-list-check"></i> <?= $attStats['total'] ?> Log(s) Found
                        </span>
                        <span class="status-pill present" style="font-size: 0.8rem;">
                            <i class="fa-solid fa-user-check"></i> <?= $attStats['present'] ?> Present
                        </span>
                        <span class="status-pill late" style="font-size: 0.8rem; background: #fef3c7; color: #d97706; border: 1px solid #fde68a;">
                            <i class="fa-solid fa-clock"></i> <?= $attStats['late'] ?> Late
                        </span>
                        <?php if ($attStats['half_day'] > 0): ?>
                            <span class="status-pill pending" style="font-size: 0.8rem;">
                                <i class="fa-solid fa-business-time"></i> <?= $attStats['half_day'] ?> Half Day
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Attendance Filters Form -->
                <form method="GET" action="hr_dashboard.php#attendanceSection" style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem;">
                    <input type="hidden" name="att_filter_applied" value="1">
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; align-items: end;">
                        <!-- Search Employee -->
                        <div>
                            <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                                <i class="fa-solid fa-magnifying-glass"></i> Search Employee
                            </label>
                            <input type="text" id="attSearchInput" name="att_search" class="form-control" placeholder="Name or email..." value="<?= htmlspecialchars($attSearch) ?>">
                        </div>

                        <!-- Date From -->
                        <div>
                            <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                                <i class="fa-regular fa-calendar"></i> Date From
                            </label>
                            <input type="date" name="att_date_from" class="form-control" value="<?= htmlspecialchars($attDateFrom) ?>">
                        </div>

                        <!-- Date To -->
                        <div>
                            <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                                <i class="fa-regular fa-calendar-check"></i> Date To
                            </label>
                            <input type="date" name="att_date_to" class="form-control" value="<?= htmlspecialchars($attDateTo) ?>">
                        </div>

                        <!-- Department Filter -->
                        <div>
                            <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                                <i class="fa-solid fa-building"></i> Department
                            </label>
                            <select name="att_dept" class="form-control">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= $attDept == $d['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                                <i class="fa-solid fa-filter"></i> Status
                            </label>
                            <select name="att_status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="present" <?= $attStatus === 'present' ? 'selected' : '' ?>>Present</option>
                                <option value="late" <?= $attStatus === 'late' ? 'selected' : '' ?>>Late</option>
                                <option value="half_day" <?= $attStatus === 'half_day' ? 'selected' : '' ?>>Half Day</option>
                                <option value="absent" <?= $attStatus === 'absent' ? 'selected' : '' ?>>Absent</option>
                                <option value="on_leave" <?= $attStatus === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
                            </select>
                        </div>

                        <!-- Action Buttons -->
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">
                                <i class="fa-solid fa-filter"></i> Filter
                            </button>
                            <a href="hr_dashboard.php#attendanceSection" class="btn btn-secondary" title="Reset Filters">
                                <i class="fa-solid fa-rotate-left"></i> Reset
                            </a>
                        </div>
                    </div>

                    <!-- Quick Filter Actions -->
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap; font-size: 0.8rem; color: var(--text-muted);">
                        <span style="font-weight: 600;">Quick Filters:</span>
                        <a href="hr_dashboard.php?att_filter_applied=1&att_date_from=<?= $today ?>&att_date_to=<?= $today ?>#attendanceSection" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 3px 10px;">Today</a>
                        <a href="hr_dashboard.php?att_filter_applied=1&att_date_from=<?= date('Y-m-d', strtotime('-1 day')) ?>&att_date_to=<?= date('Y-m-d', strtotime('-1 day')) ?>#attendanceSection" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 3px 10px;">Yesterday</a>
                        <a href="hr_dashboard.php?att_filter_applied=1&att_date_from=<?= date('Y-m-01') ?>&att_date_to=<?= date('Y-m-t') ?>#attendanceSection" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 3px 10px;">This Month</a>
                        <a href="hr_dashboard.php?att_filter_applied=1&att_date_from=&att_date_to=#attendanceSection" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 3px 10px;">All Dates</a>
                    </div>
                </form>

                <!-- Attendance Log Table -->
                <div class="table-responsive">
                    <table class="custom-table" id="attendanceLogsTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Date</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th>Total Hours</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendanceLogs)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                        <i class="fa-regular fa-calendar-xmark" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem; color: var(--text-light);"></i>
                                        No attendance records match your filter criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendanceLogs as $att): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <div class="user-mini-avatar" style="width: 32px; height: 32px; font-size: 0.8rem; background: var(--primary-gradient); color: #ffffff;">
                                                    <?= strtoupper(substr($att['employee_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--text-main);">
                                                        <?= htmlspecialchars($att['employee_name']) ?>
                                                    </div>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                        <?= htmlspecialchars($att['employee_email']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($att['department_name'] ?? 'Unassigned') ?></td>
                                        <td>
                                            <strong><?= formatNiceDate($att['date']) ?></strong>
                                        </td>
                                        <td>
                                            <span style="font-weight: 600; color: var(--success);">
                                                <i class="fa-solid fa-arrow-right-to-bracket"></i> <?= formatTime($att['clock_in']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($att['clock_out'])): ?>
                                                <span style="font-weight: 600; color: var(--danger);">
                                                    <i class="fa-solid fa-arrow-right-from-bracket"></i> <?= formatTime($att['clock_out']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="font-size: 0.8rem; color: var(--warning); font-style: italic;">Active Session</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= number_format((float)($att['total_hours'] ?? 0), 2) ?> hrs</strong>
                                        </td>
                                        <td>
                                            <?php 
                                                $stClass = strtolower($att['status']);
                                                if ($stClass === 'late') $stClass = 'rejected';
                                                elseif ($stClass === 'half_day') $stClass = 'pending';
                                            ?>
                                            <span class="status-pill <?= $stClass ?>">
                                                <?= ucfirst(str_replace('_', ' ', $att['status'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
                </div>
            </div>
        </main>

        <!-- Modal: Add Employee -->
        <div class="modal-overlay" id="addEmployeeModal">
            <div class="modal-card">
                <div class="modal-header">
                    <h3 style="font-size: 1.2rem; font-weight: 700;">Add New Employee</h3>
                    <button class="modal-close-btn" onclick="closeModal('addEmployeeModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form action="actions/employee_action.php" method="POST">
                        <input type="hidden" name="action" value="add_employee">

                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Rachel Adams"
                                required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" placeholder="rachel@dayflow.com"
                                required>
                        </div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <select name="department_id" class="form-control">
                                    <option value="">-- Select --</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-control">
                                    <option value="employee" selected>Employee</option>
                                    <option value="hr">HR Admin</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label class="form-label">Designation</label>
                                <input type="text" name="designation" class="form-control"
                                    placeholder="e.g. Frontend Engineer">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" placeholder="+1 (555) 000-0000">
                            </div>
                        </div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label class="form-label">Monthly Salary ($)</label>
                                <input type="number" step="100" name="salary" class="form-control" value="50000">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Password</label>
                                <input type="text" name="password" class="form-control" value="password123">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                            <i class="fa-solid fa-user-plus"></i> Save Employee Record
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal: Post Announcement -->
        <div class="modal-overlay" id="addAnnouncementModal">
            <div class="modal-card">
                <div class="modal-header">
                    <h3 style="font-size: 1.2rem; font-weight: 700;">Broadcast Company Announcement</h3>
                    <button class="modal-close-btn" onclick="closeModal('addAnnouncementModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form action="actions/announcement_action.php" method="POST">
                        <input type="hidden" name="action" value="create_announcement">

                        <div class="form-group">
                            <label class="form-label">Announcement Title *</label>
                            <input type="text" name="title" class="form-control"
                                placeholder="e.g. Holiday Schedule Announcement" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-control">
                                <option value="general">General Update</option>
                                <option value="urgent">Urgent Notice</option>
                                <option value="event">Company Event / Townhall</option>
                                <option value="holiday">Holiday Notice</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Message Content *</label>
                            <textarea name="content" class="form-control" rows="4"
                                placeholder="Write announcement details for all team members..." required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fa-solid fa-paper-plane"></i> Publish to Portal
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal: View Full Employee Profile & Details -->
        <div class="modal-overlay" id="viewEmployeeModal">
            <div class="modal-card" style="max-width: 620px;">
                <div class="modal-header" style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); color: #ffffff; padding: 1.25rem 1.5rem; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div id="vEmpAvatar" style="width: 52px; height: 52px; border-radius: 50%; background: #ffffff; color: var(--primary); font-size: 1.5rem; font-weight: 800; display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow-md); flex-shrink: 0;">
                            E
                        </div>
                        <div>
                            <h3 id="vEmpName" style="font-size: 1.3rem; font-weight: 800; margin: 0; color: #ffffff;">Employee Name</h3>
                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                <span id="vEmpRole" class="role-badge" style="font-size: 0.7rem; background: rgba(255,255,255,0.2); color: #fff;">ROLE</span>
                                <span id="vEmpStatus" class="status-pill active" style="font-size: 0.7rem;">Active</span>
                            </div>
                        </div>
                    </div>
                    <button class="modal-close-btn" onclick="closeModal('viewEmployeeModal')" style="color: #ffffff; font-size: 1.75rem;">&times;</button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <!-- Leave Allowance Summary Card -->
                    <div style="background: rgba(99, 102, 241, 0.06); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.5rem;">
                        <div style="font-weight: 700; font-size: 0.85rem; color: var(--primary); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-calculator"></i> Annual Leave Allowance Breakdown
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; text-align: center;">
                            <div style="background: #ffffff; padding: 0.6rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                                <div style="font-size: 0.68rem; color: var(--text-muted); font-weight: 600;">PAID REMAINING</div>
                                <div id="vEmpPaidRemaining" style="font-size: 1.25rem; font-weight: 800; color: #10b981;">0 Days</div>
                            </div>
                            <div style="background: #ffffff; padding: 0.6rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                                <div style="font-size: 0.68rem; color: var(--text-muted); font-weight: 600;">PAID TAKEN</div>
                                <div id="vEmpPaidUsed" style="font-size: 1.25rem; font-weight: 800; color: var(--primary);">0 Days</div>
                            </div>
                            <div style="background: #ffffff; padding: 0.6rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                                <div style="font-size: 0.68rem; color: var(--text-muted); font-weight: 600;">UNPAID (LWP)</div>
                                <div id="vEmpUnpaidUsed" style="font-size: 1.25rem; font-weight: 800; color: #f59e0b;">0 Days</div>
                            </div>
                        </div>
                    </div>

                    <!-- Full Information Grid -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.875rem;">
                        <div style="padding: 0.85rem; background: var(--bg-main); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                            <div style="font-size: 0.725rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Email Address</div>
                            <strong id="vEmpEmail" style="word-break: break-all;">-</strong>
                        </div>

                        <div style="padding: 0.85rem; background: var(--bg-main); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                            <div style="font-size: 0.725rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Department</div>
                            <strong id="vEmpDept">-</strong>
                        </div>

                        <div style="padding: 0.85rem; background: var(--bg-main); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                            <div style="font-size: 0.725rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Designation / Title</div>
                            <strong id="vEmpDesignation">-</strong>
                        </div>

                        <div style="padding: 0.85rem; background: var(--bg-main); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                            <div style="font-size: 0.725rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Phone Number</div>
                            <strong id="vEmpPhone">-</strong>
                        </div>

                        <div style="padding: 0.85rem; background: var(--bg-main); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                            <div style="font-size: 0.725rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Joining Date</div>
                            <strong id="vEmpJoinDate">-</strong>
                        </div>

                        <div style="padding: 0.85rem; background: var(--bg-main); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                            <div style="font-size: 0.725rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Email Verification</div>
                            <span id="vEmpVerified" class="status-pill active">Verified</span>
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem; text-align: right;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('viewEmployeeModal')">Close Profile</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        function openViewEmployeeModal(emp) {
            if (!emp) return;
            document.getElementById('vEmpName').textContent = emp.name || 'N/A';
            document.getElementById('vEmpAvatar').textContent = (emp.name || 'E').charAt(0).toUpperCase();
            document.getElementById('vEmpRole').textContent = (emp.role || 'employee').toUpperCase();
            document.getElementById('vEmpEmail').textContent = emp.email || 'N/A';
            document.getElementById('vEmpDept').textContent = emp.department_name || 'General';
            document.getElementById('vEmpDesignation').textContent = emp.designation || 'Staff Member';
            document.getElementById('vEmpPhone').textContent = emp.phone || 'Not Provided';
            document.getElementById('vEmpJoinDate').textContent = emp.join_date || 'N/A';
            
            // Status
            const statusEl = document.getElementById('vEmpStatus');
            statusEl.textContent = (emp.status || 'active').toUpperCase();
            statusEl.className = 'status-pill ' + (emp.status === 'active' ? 'active' : 'inactive');

            // Verification
            const verEl = document.getElementById('vEmpVerified');
            if (parseInt(emp.email_verified) === 1) {
                verEl.textContent = '✅ Email Verified';
                verEl.className = 'status-pill present';
            } else {
                verEl.textContent = '⚠️ Verification Pending';
                verEl.className = 'status-pill pending';
            }

            // Leave Balances
            const bal = emp.leave_balance || { paid_remaining: 18, paid_used: 0, unpaid_used: 0 };
            document.getElementById('vEmpPaidRemaining').textContent = bal.paid_remaining + ' Days';
            document.getElementById('vEmpPaidUsed').textContent = bal.paid_used + ' Days';
            document.getElementById('vEmpUnpaidUsed').textContent = bal.unpaid_used + ' Days';

            openModal('viewEmployeeModal');
        }
</script>
<script>
function prepareRejection(form) {
    const comment = form.querySelector(
        'textarea[name="review_comment"]'
    );

    if (!comment || comment.value.trim().length < 5) {
        alert(
            "Please enter at least 5 characters as the rejection reason."
        );

        if (comment) {
            comment.focus();
        }

        return false;
    }

    const button = form.querySelector(
        'button[type="submit"]'
    );

    if (button) {
        button.disabled = true;
        button.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> Rejecting...';
    }

    return true;
}

// Live client-side filter for Attendance Search Input
document.addEventListener('DOMContentLoaded', function() {
    const attSearchInput = document.getElementById('attSearchInput');
    const attTable = document.getElementById('attendanceLogsTable');
    if (attSearchInput && attTable) {
        attSearchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const rows = attTable.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }
});
</script>
        <?php include __DIR__ . '/includes/footer.php'; ?>