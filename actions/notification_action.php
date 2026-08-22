<?php
require_once __DIR__ . '/../config/db.php';
requireAuth();

$db = getDBConnection();
$currentUser = getCurrentUser();
$userId = $currentUser['id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'mark_all_read') {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }

    // Redirect back
    $referrer = $_SERVER['HTTP_REFERER'] ?? ($currentUser['role'] === 'hr' ? '../hr_dashboard.php' : '../employee_dashboard.php');
    header("Location: " . $referrer);
    exit;
}

if ($action === 'mark_read') {
    $notifId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notifId, $userId]);

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }

    $link = $_GET['link'] ?? '';
    if (!empty($link)) {
        header("Location: ../" . ltrim($link, '/'));
        exit;
    }

    $referrer = $_SERVER['HTTP_REFERER'] ?? ($currentUser['role'] === 'hr' ? '../hr_dashboard.php' : '../employee_dashboard.php');
    header("Location: " . $referrer);
    exit;
}

// Fallback
$referrer = $_SERVER['HTTP_REFERER'] ?? ($currentUser['role'] === 'hr' ? '../hr_dashboard.php' : '../employee_dashboard.php');
header("Location: " . $referrer);
exit;
