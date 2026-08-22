<?php
require_once __DIR__ . '/../config/db.php';
requireHR();

$db = getDBConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create_announcement') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = $_POST['category'] ?? 'general';

        if (empty($title) || empty($content)) {
            $_SESSION['flash_error'] = "Please provide both title and content for the announcement.";
            header("Location: ../hr_dashboard.php#announcementsSection");
            exit;
        }

        $stmt = $db->prepare("
            INSERT INTO announcements (title, content, category, created_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$title, $content, $category, $currentUser['id']]);

        // Broadcast in-website notification to all team members
        $snippet = mb_strimwidth($content, 0, 75, '...');
        broadcastNotification("📢 " . $title, $snippet, 'announcement', 'employee_dashboard.php#companyFeed');

        $_SESSION['flash_success'] = "Announcement published to company portal!";
        header("Location: ../hr_dashboard.php#announcementsSection");
        exit;
    }

    if ($action === 'delete_announcement') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->execute([$id]);

        $_SESSION['flash_success'] = "Announcement removed.";
        header("Location: ../hr_dashboard.php#announcementsSection");
        exit;
    }
}

// Fallback
header("Location: ../hr_dashboard.php");
exit;
