<?php
require_once __DIR__ . '/config/db.php';
requireAuth();

$currentUser = getCurrentUser();
$pageTitle = "Employee Self-Service Portal";
$pageSubtitle = "Welcome back, " . htmlspecialchars($currentUser['name'] ?? '');

$db = getDBConnection();
$today = date('Y-m-d');
$userId = $currentUser['id'];

// 1. Fetch Today's Attendance for User
$attStmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ? LIMIT 1");
$attStmt->execute([$userId, $today]);
$todayAttendance = $attStmt->fetch();

// 2. Fetch User Leaves
$leaveStmt = $db->prepare("
    SELECT l.*, r.name AS reviewer_name 
    FROM leaves l 
    LEFT JOIN users r ON l.reviewed_by = r.id 
    WHERE l.user_id = ? 
    ORDER BY l.created_at DESC
");
$leaveStmt->execute([$userId]);
$myLeaves = $leaveStmt->fetchAll();

// 3. Fetch User Attendance History (Last 10 days)
$histStmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? ORDER BY date DESC LIMIT 10");
$histStmt->execute([$userId]);
$myAttendanceHistory = $histStmt->fetchAll();

// 3b. Fetch Current Week Attendance (Mon - Sun)
$mondayThisWeek = date('Y-m-d', strtotime('monday this week'));
$sundayThisWeek = date('Y-m-d', strtotime('sunday this week'));
$weeklyAttStmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date BETWEEN ? AND ? ORDER BY date ASC");
$weeklyAttStmt->execute([$userId, $mondayThisWeek, $sundayThisWeek]);
$myWeeklyAttendance = $weeklyAttStmt->fetchAll();

// 4. Fetch Announcements
$announcementsStmt = $db->query("
    SELECT a.*, u.name AS author_name 
    FROM announcements a 
    JOIN users u ON a.created_by = u.id 
    ORDER BY a.created_at DESC LIMIT 5
");
$announcements = $announcementsStmt ? $announcementsStmt->fetchAll() : [];

// 5. Fetch User Notifications
$myNotifications = getUserNotifications($userId, 10);

// 6. Fetch Paid vs Unpaid Leave Allowance Balance
$leaveBalance = getUserLeaveBalance($userId);

// 7. Metrics calculation
$leavesTakenCount = 0;
$pendingLeavesCount = 0;
foreach ($myLeaves as $l) {
    if ($l['status'] === 'approved') $leavesTakenCount += $l['total_days'];
    if ($l['status'] === 'pending') $pendingLeavesCount++;
}
$presentDaysThisMonth = count($myAttendanceHistory);

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
$flashWarning = $_SESSION['flash_warning'] ?? null;
$flashInfo = $_SESSION['flash_info'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_warning'], $_SESSION['flash_info']);

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

            <?php if ($flashWarning): ?>
                <div class="alert alert-warning">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?= htmlspecialchars($flashWarning) ?></span>
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
                    <i class="fa-solid fa-info-circle"></i>
                    <span><?= htmlspecialchars($flashInfo) ?></span>
                </div>
            <?php endif; ?>

            <!-- Punch Clock Banner & Today's Status -->
            <div class="grid-3" id="punchWidget" style="margin-bottom: 2rem;">
                <!-- Live Punch Widget Card -->
                <div class="clock-widget-card">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <span style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8;">
                                <span class="live-pulse"></span> Live Workplace Clock
                            </span>
                            <span id="liveDate" style="font-size: 0.85rem; color: #cbd5e1;">--</span>
                        </div>
                        <div class="digital-clock-display" id="liveClock">
                            --:--:--
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid rgba(255, 255, 255, 0.12); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <div style="font-size: 0.8rem; color: #94a3b8;">Today's Punch Status</div>
                            <div style="font-size: 1rem; font-weight: 700; color: #fff; margin-top: 2px;">
                                <?php if (!$todayAttendance): ?>
                                    <span style="color: #fca5a5;"><i class="fa-solid fa-circle-xmark"></i> Not Clocked In</span>
                                <?php elseif (empty($todayAttendance['clock_out'])): ?>
                                    <span style="color: #6ee7b7;"><i class="fa-solid fa-circle-check"></i> Clocked In at <?= formatTime($todayAttendance['clock_in']) ?></span>
                                <?php else: ?>
                                    <?php
                                    $inSec = strtotime($todayAttendance['date'] . ' ' . $todayAttendance['clock_in']);
                                    $outSec = strtotime($todayAttendance['date'] . ' ' . $todayAttendance['clock_out']);
                                    $durSec = max(0, $outSec - $inSec);
                                    if ($durSec < 60) {
                                        $durText = '< 1 min';
                                    } elseif ($durSec < 3600) {
                                        $durText = round($durSec / 60) . ' mins';
                                    } else {
                                        $durText = number_format($todayAttendance['total_hours'], 2) . ' hrs';
                                    }
                                    ?>
                                    <span style="color: #93c5fd;"><i class="fa-solid fa-flag-checkered"></i> Clocked Out at <?= formatTime($todayAttendance['clock_out']) ?> (<?= $durText ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Clock Actions -->
                        <div style="display: flex; gap: 0.75rem;">
                            <?php if (!$todayAttendance): ?>
                                <form action="actions/attendance_action.php" method="POST">
                                    <input type="hidden" name="action" value="clock_in">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Clock In Now
                                    </button>
                                </form>
                            <?php elseif (empty($todayAttendance['clock_out'])): ?>
                                <form action="actions/attendance_action.php" method="POST">
                                    <input type="hidden" name="action" value="clock_out">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fa-solid fa-arrow-right-from-bracket"></i> Clock Out
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-secondary" disabled style="opacity: 0.7; cursor: not-allowed;">
                                    <i class="fa-solid fa-check-double"></i> Shift Completed
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Profile Summary Card -->
                <div class="card" id="profileSection" style="display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div class="card-header" style="margin-bottom: 0.75rem;">
                            <div class="card-title">
                                <i class="fa-solid fa-id-card" style="color: var(--primary);"></i>
                                Employment Profile
                            </div>
                            <span class="role-badge employee"><?= strtoupper($currentUser['role'] ?? 'EMPLOYEE') ?></span>
                        </div>

                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.25rem;">
                            <div class="user-mini-avatar" style="width: 52px; height: 52px; font-size: 1.25rem;">
                                <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div>
                                <h3 style="font-size: 1.1rem; margin-bottom: 2px;"><?= htmlspecialchars($currentUser['name'] ?? '') ?></h3>
                                <div style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($currentUser['designation'] ?? 'Staff') ?></div>
                                <div style="font-size: 0.75rem; color: var(--primary); font-weight: 600;"><?= htmlspecialchars($currentUser['department_name'] ?? 'General') ?></div>
                            </div>
                        </div>

                        <div style="font-size: 0.825rem; color: var(--text-muted); display: flex; flex-direction: column; gap: 6px; padding: 0.75rem; background: var(--bg-main); border-radius: var(--radius-md);">
                            <div><strong>Email:</strong> <?= htmlspecialchars($currentUser['email'] ?? '') ?></div>
                            <div><strong>Joined:</strong> <?= formatNiceDate($currentUser['join_date'] ?? '') ?></div>
                            <div><strong>Status:</strong> <span class="status-pill active" style="font-size: 0.7rem;"><?= ucfirst($currentUser['status'] ?? 'Active') ?></span></div>
                        </div>
                    </div>

                    <button class="btn btn-primary" onclick="openModal('applyLeaveModal')" style="margin-top: 1rem;">
                        <i class="fa-solid fa-paper-plane"></i> Apply for Leave
                    </button>
                </div>
            </div>

            <!-- Profile Edit & Salary Details Row -->
            <div class="grid-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <!-- Personal & Contact Details (Self-Editable) -->
                <div class="card">
                    <div class="card-header" style="margin-bottom: 1rem;">
                        <div class="card-title">
                            <i class="fa-solid fa-user-pen" style="color: var(--primary);"></i> Contact & Personal Info
                        </div>
                    </div>
                    <form action="actions/employee_action.php" method="POST">
                        <input type="hidden" name="update_self_profile" value="1">
                        
                        <div class="form-group" style="margin-bottom: 0.75rem;">
                            <label class="form-label" style="font-size: 0.8rem; color: var(--text-muted);">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($currentUser['phone'] ?? ''); ?>" placeholder="e.g. +91 9876543210">
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label class="form-label" style="font-size: 0.8rem; color: var(--text-muted);">Home Address</label>
                            <textarea name="address" rows="2" class="form-control" placeholder="Enter residential address"><?= htmlspecialchars($currentUser['address'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-floppy-disk"></i> Update Contact Info
                        </button>
                    </form>
                </div>

                <!-- Read-Only Salary Breakdown -->
                <div class="card">
                    <div class="card-header" style="margin-bottom: 1rem;">
                        <div class="card-title">
                            <i class="fa-solid fa-wallet" style="color: var(--primary);"></i> My Salary Structure (Read-Only)
                        </div>
                    </div>
                    <table class="custom-table" style="font-size: 0.875rem; margin-bottom: 0.75rem;">
                        <tbody>
                            <tr>
                                <td style="color: var(--text-muted);">Basic Monthly Salary</td>
                                <td style="text-align: right; font-weight: 600;">₹<?= number_format((float)($currentUser['basic_salary'] ?? 0), 2); ?></td>
                            </tr>
                            <tr>
                                <td style="color: var(--text-muted);">Allowances</td>
                                <td style="text-align: right; color: #10b981; font-weight: 600;">+ ₹<?= number_format((float)($currentUser['allowances'] ?? 0), 2); ?></td>
                            </tr>
                            <tr>
                                <td style="color: var(--text-muted);">Deductions / Taxes</td>
                                <td style="text-align: right; color: #ef4444; font-weight: 600;">- ₹<?= number_format((float)($currentUser['deductions'] ?? 0), 2); ?></td>
                            </tr>
                            <tr style="background: var(--bg-main);">
                                <td><strong>Net Take-Home Salary</strong></td>
                                <td style="text-align: right;"><strong style="color: var(--primary); font-size: 1.05rem;">₹<?= number_format((float)($currentUser['net_salary'] ?? 0), 2); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">Contact the HR department for any discrepancies in your salary structure.</span>
                </div>
            </div>

            <!-- Stat Metric Cards & Leave Allowance Overview -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-icon-wrapper green">
                        <i class="fa-solid fa-umbrella-beach"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?= $leaveBalance['paid_remaining'] ?> <span style="font-size: 0.9rem; font-weight: 500; color: var(--text-muted);">/ <?= $leaveBalance['total_allowance'] ?> Days</span></div>
                        <div class="stat-title">Remaining Paid Leave Balance</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon-wrapper amber">
                        <i class="fa-solid fa-calculator"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?= $leaveBalance['unpaid_used'] ?> <span style="font-size: 0.9rem; font-weight: 500; color: var(--text-muted);">Days</span></div>
                        <div class="stat-title">Unpaid Leave / Loss of Pay (LWP)</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon-wrapper purple">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?= $pendingLeavesCount ?></div>
                        <div class="stat-title">Pending Leave Applications</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon-wrapper blue">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?= $presentDaysThisMonth ?></div>
                        <div class="stat-title">Recent Days Logged</div>
                    </div>
                </div>
            </div>

            <!-- Main Content Split: Leaves & Announcements -->
            <div class="grid-3">
                <!-- My Leaves Table -->
                <div class="card" id="myLeavesSection">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fa-solid fa-plane-departure" style="color: var(--primary);"></i>
                            My Leave Applications
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="openModal('applyLeaveModal')">
                            <i class="fa-solid fa-plus"></i> Request Leave
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Category</th>
                                    <th>Dates</th>
                                    <th>Days</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($myLeaves)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                            You haven't requested any leaves yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($myLeaves as $l): ?>
                                        <?php $isPaidLeave = ($l['leave_category'] ?? ($l['is_paid'] ?? 1) == 1) === 'paid' || ($l['is_paid'] ?? 1) == 1; ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($l['leave_type']) ?></strong></td>
                                            <td>
                                                <?php if ($l['status'] === 'pending'): ?>
                                                    <span class="status-pill pending" style="font-size: 0.7rem; padding: 2px 7px;">
                                                        <i class="fa-solid fa-hourglass-half"></i> Pending HR Review
                                                    </span>
                                                <?php elseif ($isPaidLeave): ?>
                                                    <span class="status-pill present" style="font-size: 0.7rem; padding: 2px 7px;">
                                                        <i class="fa-solid fa-circle-check"></i> Paid Leave
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-pill pending" style="font-size: 0.7rem; padding: 2px 7px; background: rgba(245, 158, 11, 0.15); color: #b45309;">
                                                        <i class="fa-solid fa-coins"></i> Unpaid (LWP)
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= formatNiceDate($l['start_date']) ?> - <?= formatNiceDate($l['end_date']) ?>
                                            </td>
                                            <td><?= $l['total_days'] ?> day(s)</td>
                                            <td style="max-width: 220px;">
                                                <span title="<?= htmlspecialchars($l['reason']) ?>">
                                                    <?= htmlspecialchars(mb_strimwidth($l['reason'], 0, 35, '...')) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-pill <?= htmlspecialchars($l['status']) ?>">
                                                    <?= ucfirst($l['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Company Announcements & Notifications -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <!-- My In-Website Notifications Card -->
                    <div class="card" id="notificationsSection">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-bell" style="color: var(--primary);"></i>
                                My Notifications
                            </div>
                            <?php if (!empty($myNotifications)): ?>
                                <form action="actions/notification_action.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="mark_all_read">
                                    <button type="submit" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 2px 8px;">
                                        Mark All Read
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 280px; overflow-y: auto;">
                            <?php if (empty($myNotifications)): ?>
                                <div style="text-align: center; color: var(--text-muted); font-size: 0.85rem; padding: 1rem;">
                                    <i class="fa-regular fa-bell-slash" style="font-size: 1.5rem; opacity: 0.4; margin-bottom: 0.35rem; display: block;"></i>
                                    No notifications yet
                                </div>
                            <?php else: ?>
                                <?php foreach ($myNotifications as $n): ?>
                                    <div style="padding: 0.75rem 0.85rem; border: 1px solid <?= $n['is_read'] ? 'var(--border-color)' : 'rgba(99, 102, 241, 0.3)' ?>; border-radius: var(--radius-md); background: <?= $n['is_read'] ? '#ffffff' : 'rgba(99, 102, 241, 0.05)' ?>; position: relative;">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.25rem;">
                                            <strong style="font-size: 0.85rem; color: var(--text-main); font-weight: <?= $n['is_read'] ? '600' : '700' ?>;">
                                                <?= htmlspecialchars($n['title']) ?>
                                            </strong>
                                            <?php if (!$n['is_read']): ?>
                                                <span class="status-pill pending" style="font-size: 0.6rem; padding: 1px 6px;">New</span>
                                            <?php endif; ?>
                                        </div>
                                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.35rem; line-height: 1.4;">
                                            <?= htmlspecialchars($n['message']) ?>
                                        </p>
                                        <div style="font-size: 0.7rem; color: var(--text-light); text-align: right;">
                                            <?= formatNiceDate($n['created_at']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Announcements -->
                    <div class="card" id="companyFeed">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-bullhorn" style="color: var(--primary);"></i>
                                Company Notices
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 0.85rem; max-height: 280px; overflow-y: auto;">
                            <?php if (empty($announcements)): ?>
                                <p style="color: var(--text-muted); font-size: 0.85rem; text-align: center;">No notices posted yet.</p>
                            <?php else: ?>
                                <?php foreach ($announcements as $ann): ?>
                                    <div style="padding: 0.85rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: #ffffff;">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.35rem;">
                                            <strong style="font-size: 0.875rem;"><?= htmlspecialchars($ann['title']) ?></strong>
                                            <span class="status-pill <?= $ann['category'] === 'urgent' ? 'rejected' : ($ann['category'] === 'event' ? 'on_leave' : 'active') ?>" style="font-size: 0.65rem;">
                                                <?= ucfirst($ann['category']) ?>
                                            </span>
                                        </div>
                                        <p style="font-size: 0.825rem; color: var(--text-muted); margin-bottom: 0.4rem;">
                                            <?= nl2br(htmlspecialchars($ann['content'])) ?>
                                        </p>
                                        <div style="font-size: 0.725rem; color: var(--text-light); text-align: right;">
                                            <?= formatNiceDate($ann['created_at']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Attendance Log (Daily & Weekly Views) -->
                    <div class="card" id="attendanceSection">
                        <div class="card-header" style="flex-wrap: wrap; gap: 0.5rem;">
                            <div class="card-title">
                                <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i>
                                My Attendance View
                            </div>
                            <div style="display: flex; gap: 4px; background: var(--bg-main); padding: 3px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                                <button type="button" id="tabDailyBtn" onclick="switchAttTab('daily')" class="btn btn-sm btn-primary" style="font-size: 0.7rem; padding: 2px 8px;">Daily</button>
                                <button type="button" id="tabWeeklyBtn" onclick="switchAttTab('weekly')" class="btn btn-sm btn-secondary" style="font-size: 0.7rem; padding: 2px 8px;">Weekly</button>
                            </div>
                        </div>

                        <!-- Daily / Recent View -->
                        <div id="attDailyView" style="display: flex; flex-direction: column; gap: 0.6rem; max-height: 240px; overflow-y: auto;">
                            <?php if (empty($myAttendanceHistory)): ?>
                                <p style="color: var(--text-muted); font-size: 0.85rem; text-align: center; padding: 1rem;">No recent attendance logs yet.</p>
                            <?php else: ?>
                                <?php foreach ($myAttendanceHistory as $att): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.55rem 0.75rem; background: var(--bg-main); border-radius: var(--radius-sm); font-size: 0.825rem; border: 1px solid var(--border-color);">
                                        <div>
                                            <strong><?= formatNiceDate($att['date']) ?></strong>
                                            <div style="font-size: 0.725rem; color: var(--text-muted);">
                                                <?= formatTime($att['clock_in']) ?> - <?= $att['clock_out'] ? formatTime($att['clock_out']) : 'In Progress' ?>
                                                <?php if (!empty($att['total_hours']) && $att['total_hours'] > 0): ?>
                                                    (<?= $att['total_hours'] ?> hrs)
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="status-pill <?= htmlspecialchars($att['status']) ?>" style="font-size: 0.7rem;">
                                            <?= ucfirst(str_replace('_', ' ', $att['status'])) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Weekly View (Mon-Sun) -->
                        <div id="attWeeklyView" style="display: none; flex-direction: column; gap: 0.6rem; max-height: 240px; overflow-y: auto;">
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 2px;">
                                WEEK OF <?= formatNiceDate($mondayThisWeek) ?> - <?= formatNiceDate($sundayThisWeek) ?>
                            </div>
                            <?php if (empty($myWeeklyAttendance)): ?>
                                <p style="color: var(--text-muted); font-size: 0.85rem; text-align: center; padding: 1rem;">No attendance logged for this week yet.</p>
                            <?php else: ?>
                                <?php foreach ($myWeeklyAttendance as $wAtt): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.55rem 0.75rem; background: #ffffff; border-radius: var(--radius-sm); font-size: 0.825rem; border: 1px solid var(--border-color);">
                                        <div>
                                            <strong><?= date('D, M d', strtotime($wAtt['date'])) ?></strong>
                                            <div style="font-size: 0.725rem; color: var(--text-muted);">
                                                <?= formatTime($wAtt['clock_in']) ?> to <?= $wAtt['clock_out'] ? formatTime($wAtt['clock_out']) : 'In Progress' ?>
                                                <?= $wAtt['total_hours'] > 0 ? ' • ' . $wAtt['total_hours'] . ' hrs' : '' ?>
                                            </div>
                                        </div>
                                        <span class="status-pill <?= htmlspecialchars($wAtt['status']) ?>" style="font-size: 0.7rem;">
                                            <?= ucfirst(str_replace('_', ' ', $wAtt['status'])) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            function switchAttTab(view) {
                const dView = document.getElementById('attDailyView');
                const wView = document.getElementById('attWeeklyView');
                const dBtn = document.getElementById('tabDailyBtn');
                const wBtn = document.getElementById('tabWeeklyBtn');

                if (view === 'daily') {
                    dView.style.display = 'flex';
                    wView.style.display = 'none';
                    dBtn.className = 'btn btn-sm btn-primary';
                    wBtn.className = 'btn btn-sm btn-secondary';
                } else {
                    dView.style.display = 'none';
                    wView.style.display = 'flex';
                    dBtn.className = 'btn btn-sm btn-secondary';
                    wBtn.className = 'btn btn-sm btn-primary';
                }
            }
            </script>
        </main>

        <!-- Modal: Apply for Leave -->
        <div class="modal-overlay" id="applyLeaveModal">
            <div class="modal-card">
                <div class="modal-header">
                    <h3 style="font-size: 1.2rem; font-weight: 700;">Submit Leave Application</h3>
                    <button class="modal-close-btn" onclick="closeModal('applyLeaveModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <!-- Balance Summary Badge -->
                    <div style="padding: 0.85rem 1rem; background: rgba(99, 102, 241, 0.08); border-radius: var(--radius-md); border: 1px solid rgba(99, 102, 241, 0.2); margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">PAID LEAVE ALLOWANCE</div>
                            <div style="font-size: 0.95rem; font-weight: 700; color: var(--primary);">
                                <?= $leaveBalance['paid_remaining'] ?> of <?= $leaveBalance['total_allowance'] ?> Days Remaining
                            </div>
                        </div>
                        <span class="status-pill present" style="font-size: 0.7rem;"><?= $leaveBalance['paid_used'] ?> Days Used</span>
                    </div>

                    <form action="actions/leave_action.php" method="POST">
                        <input type="hidden" name="action" value="apply_leave">

                        <div class="form-group">
                            <label class="form-label">Leave Type *</label>
                            <select name="leave_type" class="form-control" required>
                                <option value="Casual Leave">Casual Leave</option>
                                <option value="Sick Leave">Sick Leave</option>
                                <option value="Annual Leave">Annual Vacation Leave</option>
                                <option value="Maternity/Paternity">Maternity/Paternity</option>
                                <option value="Emergency">Emergency Leave</option>
                            </select>
                        </div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label class="form-label">Start Date *</label>
                                <input type="date" name="start_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">End Date *</label>
                                <input type="date" name="end_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Reason for Leave *</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Please provide brief reason for leave..." required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                            <i class="fa-solid fa-paper-plane"></i> Submit Leave Request
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/includes/footer.php'; ?>


        