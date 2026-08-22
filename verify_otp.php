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

$pendingEmail = $_SESSION['pending_verification_email'] ?? '';
$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashInfo = $_SESSION['flash_info'] ?? null;
$devOtp = $_SESSION['dev_simulated_otp'] ?? null;

unset($_SESSION['flash_error'], $_SESSION['flash_success'], $_SESSION['flash_info']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email OTP | Dayflow HRMS</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .otp-input-box {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: 12px;
            text-align: center;
            font-family: 'Outfit', monospace;
            padding: 0.85rem 1rem;
            color: var(--primary);
            border: 2px solid var(--border-color);
            background-color: #f8fafc;
            border-radius: var(--radius-md);
            transition: all var(--transition-fast);
        }
        .otp-input-box:focus {
            border-color: var(--primary);
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }
        .dev-otp-helper {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(139, 92, 246, 0.08) 100%);
            border: 1px dashed rgba(99, 102, 241, 0.4);
            border-radius: var(--radius-md);
            padding: 0.85rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.825rem;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
    </style>
</head>
<body class="auth-page-wrapper">

    <div class="auth-card" style="max-width: 480px;">
        <!-- Logo Header -->
        <div style="text-align: center; margin-bottom: 1.75rem;">
            <div class="brand-icon" style="margin: 0 auto 0.75rem; width: 48px; height: 48px; font-size: 1.4rem;">
                <i class="fa-solid fa-envelope-circle-check" style="color: #fff;"></i>
            </div>
            <h2 style="font-size: 1.65rem; font-weight: 800; letter-spacing: -0.5px;">Verify Your Email</h2>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
                We sent a 6-digit OTP code to: <br>
                <strong style="color: var(--text-main);"><?= htmlspecialchars($pendingEmail ?: 'your email address') ?></strong>
            </p>
        </div>

        <!-- Flash Alerts -->
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

        <?php if ($flashInfo): ?>
            <div class="alert alert-info">
                <i class="fa-solid fa-info-circle"></i>
                <span><?= htmlspecialchars($flashInfo) ?></span>
            </div>
        <?php endif; ?>

        <!-- OTP Form -->
        <form action="actions/auth_action.php" method="POST">
            <input type="hidden" name="action" value="verify_otp">
            <input type="hidden" name="email" value="<?= htmlspecialchars($pendingEmail) ?>">

            <div class="form-group">
                <label for="otpInput" class="form-label" style="text-align: center;">Enter 6-Digit Verification Code</label>
                <input 
                    type="text" 
                    id="otpInput" 
                    name="otp" 
                    class="form-control otp-input-box" 
                    maxlength="6" 
                    pattern="\d{6}" 
                    placeholder="••••••" 
                    required 
                    autocomplete="one-time-code"
                    autofocus
                >
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem; margin-top: 0.5rem;">
                <i class="fa-solid fa-shield-check"></i> Verify & Activate Account
            </button>
        </form>

        <!-- Resend OTP Section -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color); font-size: 0.85rem;">
            <span style="color: var(--text-muted);">Didn't receive the code?</span>
            <form action="actions/auth_action.php" method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="resend_otp">
                <input type="hidden" name="email" value="<?= htmlspecialchars($pendingEmail) ?>">
                <button type="submit" class="btn btn-secondary btn-sm" style="font-size: 0.8rem;">
                    <i class="fa-solid fa-rotate-right"></i> Resend OTP
                </button>
            </form>
        </div>

        <div style="text-align: center; margin-top: 1.25rem; font-size: 0.85rem;">
            <a href="login.php" style="color: var(--text-muted);"><i class="fa-solid fa-arrow-left"></i> Return to Sign In</a>
        </div>
    </div>

</body>
</html>
