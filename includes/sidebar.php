<?php
$currentUser = getCurrentUser();
$currentRole = $currentUser['role'] ?? 'employee';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <!-- Brand Logo Header -->
    <div class="sidebar-header">
        <a href="<?= $currentRole === 'hr' ? 'hr_dashboard.php' : 'employee_dashboard.php' ?>" class="brand-logo">
            <div class="brand-icon">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <span class="brand-text">Dayflow</span>
        </a>
    </div>

    <!-- Navigation Menu -->
    <div class="sidebar-menu">
        <span class="menu-label">Main Navigation</span>
        
        <?php if ($currentRole === 'hr'): ?>
            <!-- HR Management Links -->
            <a href="hr_dashboard.php" class="nav-link <?= $currentPage === 'hr_dashboard.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i>
                <span>HR Dashboard</span>
            </a>
            <a href="hr_dashboard.php#employeesSection" class="nav-link">
                <i class="fa-solid fa-users"></i>
                <span>Employee Directory</span>
            </a>
            <a href="hr_dashboard.php#leavesSection" class="nav-link">
                <i class="fa-solid fa-calendar-check"></i>
                <span>Leave Approvals</span>
            </a>
            <a href="hr_dashboard.php#attendanceSection" class="nav-link">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Attendance Logs</span>
            </a>
            <a href="hr_dashboard.php#announcementsSection" class="nav-link">
                <i class="fa-solid fa-bullhorn"></i>
                <span>Announcements</span>
            </a>

        <?php else: ?>
            <!-- Employee Portal Links -->
            <a href="employee_dashboard.php" class="nav-link <?= $currentPage === 'employee_dashboard.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-house"></i>
                <span>My Dashboard</span>
            </a>
            <a href="employee_dashboard.php#punchWidget" class="nav-link">
                <i class="fa-solid fa-fingerprint"></i>
                <span>Time & Clock In</span>
            </a>
            <a href="employee_dashboard.php#myLeavesSection" class="nav-link">
                <i class="fa-solid fa-plane-departure"></i>
                <span>Leave Requests</span>
            </a>
            <a href="employee_dashboard.php#companyFeed" class="nav-link">
                <i class="fa-solid fa-newspaper"></i>
                <span>Company News</span>
            </a>
            <a href="employee_dashboard.php#profileSection" class="nav-link">
                <i class="fa-solid fa-id-badge"></i>
                <span>My Profile</span>
            </a>
        <?php endif; ?>

        <span class="menu-label" style="margin-top: 1rem;">Account</span>
        <a href="logout.php" class="nav-link" style="color: #f87171;">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span>Sign Out</span>
        </a>
    </div>

    <!-- Mini User Footer Card -->
    <?php if ($currentUser): ?>
    <div class="sidebar-footer">
        <div class="user-mini-avatar">
            <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="user-mini-info">
            <div class="user-mini-name"><?= htmlspecialchars($currentUser['name'] ?? 'User') ?></div>
            <div class="user-mini-role">
                <span class="live-pulse" style="width: 6px; height: 6px;"></span>
                <?= strtoupper($currentUser['role'] ?? 'employee') ?>
            </div>
        </div>
        <a href="logout.php" title="Sign Out" style="color: #94a3b8; font-size: 1.1rem; padding: 4px;">
            <i class="fa-solid fa-power-off"></i>
        </a>
    </div>
    <?php endif; ?>
</aside>
