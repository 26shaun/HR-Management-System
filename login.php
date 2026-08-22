<?php
require_once __DIR__ . '/config/db.php';

// If already logged in, redirect
if (isLoggedIn()) {
    if (isHR()) {
        header("Location: hr_dashboard.php");
    } else {
        header("Location: employee_dashboard.php");
    }
    exit;
}

$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Dayflow HRMS</title>
    
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

    <div class="auth-card">
        <!-- Logo Header -->
        <div style="text-align: center; margin-bottom: 2rem;">
            <div class="brand-icon" style="margin: 0 auto 0.75rem; width: 48px; height: 48px; font-size: 1.4rem;">
                <i class="fa-solid fa-layer-group" style="color: #fff;"></i>
            </div>
            <h2 style="font-size: 1.75rem; font-weight: 800; letter-spacing: -0.5px;">Sign in to Dayflow</h2>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">Enter your credentials to access your workspace</p>
        </div>

        <!-- Flash Notifications -->
        <?php if ($flashError): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($flashError) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($flashSuccess) ?></span>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form action="actions/auth_action.php" method="POST">
            <input type="hidden" name="action" value="login">

            <div class="form-group">
                <label for="loginEmail" class="form-label">Email Address</label>
                <input type="email" id="loginEmail" name="email" class="form-control" placeholder="name@company.com" required autocomplete="email">
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <label for="loginPassword" class="form-label">Password</label>
                </div>
                <input type="password" id="loginPassword" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" id="loginSubmitBtn" class="btn btn-primary" style="width: 100%; padding: 0.8rem; margin-top: 0.5rem;">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.5rem; font-size: 0.875rem; color: var(--text-muted);">
            Don't have an account? <a href="register.php" style="font-weight: 600;">Sign up here</a>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/main.js"></script>
</body>
</html>
