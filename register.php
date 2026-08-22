<?php
require_once __DIR__ . '/config/db.php';

$db = getDBConnection();
$departments = [];
if ($db) {
    $deptStmt = $db->query("SELECT id, name FROM departments ORDER BY name ASC");
    $departments = $deptStmt->fetchAll();
}

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Account | Dayflow HRMS</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page-wrapper">

    <div class="auth-card" style="max-width: 520px;">
        <div style="text-align: center; margin-bottom: 1.75rem;">
            <div class="brand-icon" style="margin: 0 auto 0.75rem; width: 48px; height: 48px; font-size: 1.4rem;">
                <i class="fa-solid fa-layer-group" style="color: #fff;"></i>
            </div>
            <h2 style="font-size: 1.75rem; font-weight: 800; letter-spacing: -0.5px;">Create Dayflow Account</h2>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">Join your organization workspace</p>
        </div>

        <?php if ($flashError): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($flashError) ?></span>
            </div>
        <?php endif; ?>

        <form action="actions/auth_action.php" method="POST">
            <input type="hidden" name="action" value="register">

            <div class="form-group">
                <label for="regName" class="form-label">Full Name</label>
                <input type="text" id="regName" name="name" class="form-control" placeholder="e.g. Jane Cooper" required>
            </div>

            <div class="form-group">
                <label for="regEmail" class="form-label">Work Email</label>
                <input type="email" id="regEmail" name="email" class="form-control" placeholder="jane@dayflow.com" required>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="regRole" class="form-label">Role</label>
                    <select id="regRole" name="role" class="form-control" required>
                        <option value="employee" selected>Employee</option>
                        <option value="hr">HR Administrator</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="regDept" class="form-label">Department</label>
                    <select id="regDept" name="department_id" class="form-control">
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="regDesignation" class="form-label">Designation / Job Title</label>
                <input type="text" id="regDesignation" name="designation" class="form-control" placeholder="e.g. Software Engineer">
            </div>

            <div class="form-group">
                <label for="regPassword" class="form-label">Create Password</label>
                <input type="password" id="regPassword" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem; margin-top: 0.5rem;">
                <i class="fa-solid fa-user-plus"></i> Create Account
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.5rem; font-size: 0.875rem; color: var(--text-muted);">
            Already have an account? <a href="login.php" style="font-weight: 600;">Sign in here</a>
        </div>
    </div>

</body>
</html>
