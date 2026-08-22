<?php
require_once __DIR__ . '/config/db.php';
requireHR();

$pageTitle = "HR Command Center";
$pageSubtitle = "Workforce overview, leave approvals, and employee operations";

$db = getDBConnection();
$today = date('Y-m-d');

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

// 4. Fetch Today's Attendance
$attendanceStmt = $db->query("
    SELECT a.*, u.name AS employee_name, u.email AS employee_email, d.name AS department_name
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE a.date = '$today'
    ORDER BY a.clock_in DESC
");
$todayAttendance = $attendanceStmt->fetchAll();

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
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

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
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leaves)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                        No leave applications submitted yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leaves as $l): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($l['employee_name']) ?></strong>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($l['employee_email']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($l['department_name'] ?? 'General') ?></td>
                                        <td><strong><?= htmlspecialchars($l['leave_type']) ?></strong></td>
                                        <td>
                                            <?= formatNiceDate($l['start_date']) ?> to <?= formatNiceDate($l['end_date']) ?>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?= $l['total_days'] ?> day(s)</div>
                                        </td>
                                        <td style="max-width: 250px;">
                                            <span title="<?= htmlspecialchars($l['reason']) ?>">
                                                <?= htmlspecialchars(mb_strimwidth($l['reason'], 0, 45, '...')) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-pill <?= htmlspecialchars($l['status']) ?>">
                                                <?= ucfirst($l['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($l['status'] === 'pending'): ?>
                                                <div style="display: flex; gap: 0.4rem;">
                                                    <!-- Approve Button -->
                                                    <form action="actions/leave_action.php" method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="review_leave">
                                                        <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
                                                        <input type="hidden" name="status" value="approved">
                                                        <input type="hidden" name="review_comment" value="Approved by HR.">
                                                        <button type="submit" class="btn btn-success btn-sm" title="Approve">
                                                            <i class="fa-solid fa-check"></i> Approve
                                                        </button>
                                                    </form>

                                                    <!-- Reject Button -->
                                                    <form action="actions/leave_action.php" method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="review_leave">
                                                        <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
                                                        <input type="hidden" name="status" value="rejected">
                                                        <input type="hidden" name="review_comment" value="Declined by HR.">
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Reject">
                                                            <i class="fa-solid fa-xmark"></i> Reject
                                                        </button>
                                                    </form>
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
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <div class="user-mini-avatar" style="width: 34px; height: 34px; font-size: 0.8rem;">
                                                    <?= strtoupper(substr($emp['name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600;"><?= htmlspecialchars($emp['name']) ?></div>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($emp['email']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="role-badge <?= $emp['role'] ?>" style="font-size: 0.7rem; padding: 2px 8px;">
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
                                            <?php if ($emp['id'] !== $currentUser['id']): ?>
                                                <form action="actions/employee_action.php" method="POST" onsubmit="return confirm('Are you sure you want to remove this employee account?');" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_employee">
                                                    <input type="hidden" name="user_id" value="<?= $emp['id'] ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm" style="color: #ef4444; padding: 4px 8px;" title="Delete">
                                                        <i class="fa-regular fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span style="font-size: 0.75rem; color: var(--text-light);">You</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Today's Attendance Feed & Announcements -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <!-- Attendance Card -->
                    <div class="card" id="attendanceSection">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-clock" style="color: var(--primary);"></i>
                                Today's Attendance Log
                            </div>
                            <span class="status-pill present"><?= count($todayAttendance) ?> Logged</span>
                        </div>

                        <?php if (empty($todayAttendance)): ?>
                            <p style="color: var(--text-muted); font-size: 0.875rem; text-align: center; padding: 1rem 0;">
                                No attendance records logged today yet.
                            </p>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 280px; overflow-y: auto;">
                                <?php foreach ($todayAttendance as $att): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.6rem 0.8rem; background: var(--bg-main); border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                                        <div>
                                            <strong style="font-size: 0.875rem;"><?= htmlspecialchars($att['employee_name']) ?></strong>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);">In: <?= formatTime($att['clock_in']) ?> <?= $att['clock_out'] ? '• Out: ' . formatTime($att['clock_out']) : '' ?></div>
                                        </div>
                                        <span class="status-pill <?= $att['status'] ?>"><?= ucfirst($att['status']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

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

                        <div style="display: flex; flex-direction: column; gap: 0.85rem; max-height: 280px; overflow-y: auto;">
                            <?php foreach ($announcements as $ann): ?>
                                <div style="padding: 0.85rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: #ffffff;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.35rem;">
                                        <strong style="font-size: 0.9rem;"><?= htmlspecialchars($ann['title']) ?></strong>
                                        <span class="status-pill <?= $ann['category'] === 'urgent' ? 'rejected' : ($ann['category'] === 'event' ? 'on_leave' : 'active') ?>" style="font-size: 0.65rem;">
                                            <?= ucfirst($ann['category']) ?>
                                        </span>
                                    </div>
                                    <p style="font-size: 0.825rem; color: var(--text-muted); margin-bottom: 0.4rem;">
                                        <?= nl2br(htmlspecialchars($ann['content'])) ?>
                                    </p>
                                    <div style="display: flex; justify-content: space-between; font-size: 0.725rem; color: var(--text-light);">
                                        <span>By <?= htmlspecialchars($ann['author_name']) ?></span>
                                        <span><?= formatNiceDate($ann['created_at']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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
                            <input type="text" name="name" class="form-control" placeholder="e.g. Rachel Adams" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" placeholder="rachel@dayflow.com" required>
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
                                <input type="text" name="designation" class="form-control" placeholder="e.g. Frontend Engineer">
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
                            <input type="text" name="title" class="form-control" placeholder="e.g. Holiday Schedule Announcement" required>
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
                            <textarea name="content" class="form-control" rows="4" placeholder="Write announcement details for all team members..." required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fa-solid fa-paper-plane"></i> Publish to Portal
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/includes/footer.php'; ?>
