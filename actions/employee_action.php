<?php
require_once __DIR__ . '/../config/db.php';
requireHR();

$db = getDBConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Add Employee
    if ($action === 'add_employee') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? 'password123';
        $role = $_POST['role'] ?? 'employee';
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $designation = trim($_POST['designation'] ?? 'Staff Member');
        $phone = trim($_POST['phone'] ?? '');
        $salary = !empty($_POST['salary']) ? (float)$_POST['salary'] : 50000.00;
        $join_date = !empty($_POST['join_date']) ? $_POST['join_date'] : date('Y-m-d');
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || empty($email)) {
            $_SESSION['flash_error'] = "Employee name and email are mandatory.";
            header("Location: ../hr_dashboard.php#employeesSection");
            exit;
        }

        // Check if email already exists
        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            $_SESSION['flash_error'] = "An employee with this email already exists.";
            header("Location: ../hr_dashboard.php#employeesSection");
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $insertStmt = $db->prepare("
            INSERT INTO users (name, email, password, role, department_id, designation, phone, salary, join_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([$name, $email, $hashedPassword, $role, $department_id, $designation, $phone, $salary, $join_date, $status]);

        $_SESSION['flash_success'] = "Employee " . htmlspecialchars($name) . " added successfully!";
        header("Location: ../hr_dashboard.php#employeesSection");
        exit;
    }

    // 2. Delete / Deactivate Employee
    if ($action === 'delete_employee') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === (int)$_SESSION['user_id']) {
            $_SESSION['flash_error'] = "You cannot delete your own HR account!";
            header("Location: ../hr_dashboard.php#employeesSection");
            exit;
        }

        $delStmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $delStmt->execute([$userId]);

        $_SESSION['flash_success'] = "Employee record removed successfully.";
        header("Location: ../hr_dashboard.php#employeesSection");
        exit;
    }
}

// Fallback
header("Location: ../hr_dashboard.php");
exit;
