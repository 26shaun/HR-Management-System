<?php
require_once __DIR__ . '/config/db.php';
requireAuth();

$currentUser = getCurrentUser();
$pageTitle = "Employee Self-Service Portal";
$pageSubtitle = "Welcome back, " . htmlspecialchars($currentUser['name']);

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

// 4. Fetch Announcements
$announcementsStmt = $db->query("
    SELECT a.*, u.name AS author_name 
    FROM announcements a 
    JOIN users u ON a.created_by = u.id 
    ORDER BY a.created_at DESC LIMIT 5
");
$announcements = $announcementsStmt->fetchAll();

// 5. Metrics calculation
$leavesTakenCount = 0;
$pendingLeavesCount = 0;
foreach ($myLeaves as $l) {
    if ($l['status'] === 'approved') $leavesTakenCount += $l['total_days'];
    if ($l['status'] === 'pending') $pendingLeavesCount++;
}
$presentDaysThisMonth = count($myAttendanceHistory);

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
$flashInfo = $_SESSION['flash_info'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_info']);

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
                                    <span style="color: #93c5fd;"><i class="fa-solid fa-flag-checkered"></i> Clocked Out at <?= formatTime($todayAttendance['clock_out']) ?> (<?= $todayAttendance['total_hours'] ?> hrs)</span>
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
                            <span class="role-badge employee"><?= strtoupper($currentUser['role']) ?></span>
                        </div>

                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.25rem;">
                            <div class="user-mini-avatar" style="width: 52px; height: 52px; font-size: 1.25rem;">
                                <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                            </div>
                            <div>
                                <h3 style="font-size: 1.1rem; margin-bottom: 2px;"><?= htmlspecialchars($currentUser['name']) ?></h3>
                                <div style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($currentUser['designation'] ?? 'Staff') ?></div>
                                <div style="font-size: 0.75rem; color: var(--primary); font-weight: 600;"><?= htmlspecialchars($currentUser['department_name'] ?? 'General') ?></div>
                            </div>
                        </div>

                        <div style="font-size: 0.825rem; color: var(--text-muted); display: flex; flex-direction: column; gap: 6px; padding: 0.75rem; background: var(--bg-main); border-radius: var(--radius-md);">
                            <div><strong>Email:</strong> <?= htmlspecialchars($currentUser['email']) ?></div>
                            <div><strong>Joined:</strong> <?= formatNiceDate($currentUser['join_date']) ?></div>
                            <div><strong>Status:</strong> <span class="status-pill active" style="font-size: 0.7rem;"><?= ucfirst($currentUser['status']) ?></span></div>
                        </div>
                    </div>

                    <button class="btn btn-primary" onclick="openModal('applyLeaveModal')" style="margin-top: 1rem;">
                        <i class="fa-solid fa-paper-plane"></i> Apply for Leave
                    </button>
                </div>
            </div>

            <!-- Stat Metric Cards -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-icon-wrapper green">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?= $presentDaysThisMonth ?></div>
                        <div class="stat-title">Recent Days Logged</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon-wrapper amber">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?= $pendingLeavesCount ?></div>
                        <div class="stat-title">Pending Leave Requests</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon-wrapper purple">
                        <i class="fa-solid fa-umbrella-beach"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?= $leavesTakenCount ?></div>
                        <div class="stat-title">Days On Leave Approved</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon-wrapper blue">
                        <i class="fa-solid fa-building"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number" style="font-size: 1.15rem; font-weight: 700;"><?= htmlspecialchars($currentUser['department_name'] ?? 'General') ?></div>
                        <div class="stat-title">My Department</div>
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
                                    <th>Dates</th>
                                    <th>Days</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($myLeaves)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                            You haven't requested any leaves yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($myLeaves as $l): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($l['leave_type']) ?></strong></td>
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

                <!-- Company Announcements & Attendance Log -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <!-- Announcements -->
                    <div class="card" id="companyFeed">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-bullhorn" style="color: var(--primary);"></i>
                                Company Notices
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 0.85rem; max-height: 280px; overflow-y: auto;">
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
                        </div>
                    </div>

                    <!-- Attendance Log -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i>
                                My Recent Logs
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 0.6rem; max-height: 220px; overflow-y: auto;">
                            <?php if (empty($myAttendanceHistory)): ?>
                                <p style="color: var(--text-muted); font-size: 0.85rem; text-align: center;">No logs yet.</p>
                            <?php else: ?>
                                <?php foreach ($myAttendanceHistory as $att): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0.75rem; background: var(--bg-main); border-radius: var(--radius-sm); font-size: 0.825rem; border: 1px solid var(--border-color);">
                                        <div>
                                            <strong><?= formatNiceDate($att['date']) ?></strong>
                                            <div style="font-size: 0.725rem; color: var(--text-muted);">
                                                <?= formatTime($att['clock_in']) ?> - <?= $att['clock_out'] ? formatTime($att['clock_out']) : 'In Progress' ?>
                                            </div>
                                        </div>
                                        <span class="status-pill <?= $att['status'] ?>" style="font-size: 0.7rem;">
                                            <?= ucfirst($att['status']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Modal: Apply for Leave -->
        <div class="modal-overlay" id="applyLeaveModal">
            <div class="modal-card">
                <div class="modal-header">
                    <h3 style="font-size: 1.2rem; font-weight: 700;">Submit Leave Application</h3>
                    <button class="modal-close-btn" onclick="closeModal('applyLeaveModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form action="actions/leave_action.php" method="POST">
                        <input type="hidden" name="action" value="apply_leave">

                        <div class="form-group">
                            <label class="form-label">Leave Type *</label>
                            <select name="leave_type" class="form-control" required>
                                <option value="Casual Leave">Casual Leave</option>
                                <option value="Sick Leave">Sick Leave</option>
                                <option value="Annual Leave">Annual Vacation Leave</option>
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
