<?php
require_once __DIR__ . '/config/db.php';

$currentUser = getCurrentUser();

if ($currentUser) {
    if (($currentUser['role'] ?? 'employee') === 'hr') {
        header("Location: hr_dashboard.php");
        exit;
    } else {
        header("Location: employee_dashboard.php");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}
