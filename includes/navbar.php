<?php
$currentUser = getCurrentUser();
$currentRole = $currentUser['role'] ?? 'employee';
?>
<header class="top-navbar">
    <div class="navbar-left">
        <button class="btn-sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="page-title-box">
            <h1><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
            <p><?= htmlspecialchars($pageSubtitle ?? 'Welcome back, ' . ($currentUser['name'] ?? 'Team Member')) ?></p>
        </div>
    </div>

    <div class="navbar-right">
        <!-- Live Quick Time Display -->
        <div style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--text-muted); padding: 0.4rem 0.8rem; background: var(--bg-main); border-radius: var(--radius-md); border: 1px solid var(--border-color);" class="hide-mobile">
            <i class="fa-regular fa-clock" style="color: var(--primary);"></i>
            <span id="liveClock">--:--:--</span>
        </div>

        <!-- Role Badge Indicator -->
        <span class="role-badge <?= $currentRole ?>">
            <i class="fa-solid <?= $currentRole === 'hr' ? 'fa-shield-halved' : 'fa-user' ?>"></i>
            <?= $currentRole === 'hr' ? 'HR Portal' : 'Employee' ?>
        </span>

        <!-- Logout Action -->
        <a href="logout.php" class="btn btn-secondary btn-sm" title="Log Out">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span class="hide-mobile">Logout</span>
        </a>
    </div>
</header>
