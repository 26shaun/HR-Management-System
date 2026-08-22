<?php
$currentUser = getCurrentUser();
$currentRole = $currentUser['role'] ?? 'employee';
$unreadCount = $currentUser ? getUnreadNotificationCount($currentUser['id']) : 0;
$recentNotifs = $currentUser ? getUserNotifications($currentUser['id'], 6) : [];
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

        <!-- In-Website Notification Bell & Dropdown -->
        <div class="notification-wrapper" style="position: relative;">
            <button type="button" id="notifBellBtn" onclick="toggleNotifDropdown(event)" class="btn-icon" style="position: relative; background: #ffffff; border: 1px solid var(--border-color); width: 40px; height: 40px; border-radius: var(--radius-md); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: var(--text-main); transition: all var(--transition-fast);">
                <i class="fa-regular fa-bell" style="pointer-events: none;"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="notif-badge" style="pointer-events: none; position: absolute; top: -4px; right: -4px; background: #ef4444; color: #fff; font-size: 0.65rem; font-weight: 800; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; animation: pulse 1.8s infinite;">
                        <?= $unreadCount > 9 ? '9+' : $unreadCount ?>
                    </span>
                <?php endif; ?>
            </button>

            <!-- Floating Notification Panel -->
            <div id="notifDropdown" class="notif-dropdown" style="display: none; position: absolute; top: calc(100% + 10px); right: 0; width: 340px; background: #ffffff; border-radius: var(--radius-lg); box-shadow: var(--shadow-xl); border: 1px solid var(--border-color); z-index: 1500; overflow: hidden; animation: fadeIn 0.2s ease;">
                <div style="padding: 0.85rem 1.15rem; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                    <div style="font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-bell" style="color: var(--primary);"></i> Notifications
                        <?php if ($unreadCount > 0): ?>
                            <span class="status-pill pending" style="font-size: 0.65rem; padding: 1px 6px;"><?= $unreadCount ?> new</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($unreadCount > 0): ?>
                        <form action="actions/notification_action.php" method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="mark_all_read">
                            <button type="submit" style="background: none; border: none; font-size: 0.75rem; color: var(--primary); font-weight: 600; cursor: pointer; padding: 0;">
                                Mark all as read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div style="max-height: 320px; overflow-y: auto; display: flex; flex-direction: column;">
                    <?php if (empty($recentNotifs)): ?>
                        <div style="padding: 2rem 1rem; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                            <i class="fa-regular fa-bell-slash" style="font-size: 1.75rem; opacity: 0.4; margin-bottom: 0.5rem; display: block;"></i>
                            No notifications yet
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentNotifs as $notif): ?>
                            <a href="actions/notification_action.php?action=mark_read&id=<?= $notif['id'] ?>&link=<?= urlencode($notif['link'] ?? '') ?>" style="display: block; padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-subtle); text-decoration: none; color: inherit; background: <?= $notif['is_read'] ? '#ffffff' : 'rgba(99, 102, 241, 0.05)' ?>; transition: background 0.15s ease;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 3px;">
                                    <strong style="font-size: 0.825rem; color: var(--text-main); font-weight: <?= $notif['is_read'] ? '600' : '700' ?>;">
                                        <?= htmlspecialchars($notif['title']) ?>
                                    </strong>
                                    <?php if (!$notif['is_read']): ?>
                                        <span style="display: inline-block; width: 7px; height: 7px; background: var(--primary); border-radius: 50%; flex-shrink: 0; margin-top: 4px;"></span>
                                    <?php endif; ?>
                                </div>
                                <p style="font-size: 0.78rem; color: var(--text-muted); margin: 0; line-height: 1.4;">
                                    <?= htmlspecialchars($notif['message']) ?>
                                </p>
                                <span style="font-size: 0.68rem; color: var(--text-light); margin-top: 4px; display: block;">
                                    <?= formatNiceDate($notif['created_at']) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
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

<script>
function toggleNotifDropdown(e) {
    if (e) e.stopPropagation();
    const dropdown = document.getElementById('notifDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('notifDropdown');
    const bellBtn = document.getElementById('notifBellBtn');
    if (dropdown && dropdown.classList.contains('show')) {
        if (!dropdown.contains(e.target) && e.target !== bellBtn) {
            dropdown.classList.remove('show');
        }
    }
});
</script>
