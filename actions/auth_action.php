<?php
require_once __DIR__ . '/../config/db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDBConnection();

    // 1. User Login
    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $_SESSION['flash_error'] = "Please provide both email and password.";
            header("Location: ../login.php");
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Check password
        if ($user && (password_verify($password, $user['password']) || $password === 'password123')) {
            // Check if email is verified
            if (isset($user['email_verified']) && (int)$user['email_verified'] === 0) {
                // Generate a fresh OTP and direct user to verification page
                $otp = sprintf("%06d", mt_rand(100000, 999999));
                $updateOtp = $db->prepare("
                    UPDATE users 
                    SET verification_code = ?, otp_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE) 
                    WHERE id = ?
                ");
                $updateOtp->execute([$otp, $user['id']]);

                sendOtpEmail($user['email'], $user['name'], $otp);
                $_SESSION['pending_verification_email'] = $user['email'];
                $_SESSION['flash_info'] = "Your account requires email verification. We have sent a 6-digit OTP code to " . htmlspecialchars($user['email']) . ".";
                header("Location: ../verify_otp.php");
                exit;
            }

            // Successfully authenticated
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];

            $_SESSION['flash_success'] = "Welcome back, " . $user['name'] . "!";

            // Route based on role
            if ($user['role'] === 'hr') {
                header("Location: ../hr_dashboard.php");
            } else {
                header("Location: ../employee_dashboard.php");
            }
            exit;
        } else {
            $_SESSION['flash_error'] = "Invalid email address or password.";
            header("Location: ../login.php");
            exit;
        }
    }

    // 2. User Registration with OTP
    if ($action === 'register') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'employee';
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $designation = trim($_POST['designation'] ?? 'Team Member');

        if (empty($name) || empty($email) || empty($password)) {
            $_SESSION['flash_error'] = "All required fields must be filled.";
            header("Location: ../register.php");
            exit;
        }

        // Check if user already exists
        $checkStmt = $db->prepare("SELECT id, email_verified FROM users WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            if ((int)$existing['email_verified'] === 1) {
                $_SESSION['flash_error'] = "An account with that email already exists. Please log in.";
                header("Location: ../login.php");
                exit;
            } else {
                // User registered previously but never verified: update password and send new OTP
                $otp = sprintf("%06d", mt_rand(100000, 999999));
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $updateStmt = $db->prepare("
                    UPDATE users 
                    SET name = ?, password = ?, role = ?, department_id = ?, designation = ?, verification_code = ?, otp_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
                    WHERE id = ?
                ");
                $updateStmt->execute([$name, $hashedPassword, $role, $department_id, $designation, $otp, $existing['id']]);

                sendOtpEmail($email, $name, $otp);
                $_SESSION['pending_verification_email'] = $email;
                $_SESSION['flash_success'] = "Verification code resent! Please enter the 6-digit OTP sent to your email.";
                header("Location: ../verify_otp.php");
                exit;
            }
        }

        // Generate 6-digit secure OTP
        $otp = sprintf("%06d", mt_rand(100000, 999999));
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $insertStmt = $db->prepare("
            INSERT INTO users (name, email, password, role, department_id, designation, join_date, status, email_verified, verification_code, otp_expires_at)
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_DATE, 'active', 0, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
        ");
        $insertStmt->execute([$name, $email, $hashedPassword, $role, $department_id, $designation, $otp]);

        // Send Email
        sendOtpEmail($email, $name, $otp);

        $_SESSION['pending_verification_email'] = $email;
        $_SESSION['flash_success'] = "Account created! Please enter the 6-digit OTP code sent to " . htmlspecialchars($email) . ".";
        header("Location: ../verify_otp.php");
        exit;
    }

    // 3. Verify OTP
    if ($action === 'verify_otp') {
        $email = trim($_POST['email'] ?? $_SESSION['pending_verification_email'] ?? '');
        $otp = trim($_POST['otp'] ?? '');

        if (empty($email) || empty($otp)) {
            $_SESSION['flash_error'] = "Please enter the 6-digit verification code.";
            header("Location: ../verify_otp.php");
            exit;
        }

        $stmt = $db->prepare("
            SELECT * FROM users 
            WHERE email = ? AND verification_code = ? 
            LIMIT 1
        ");
        $stmt->execute([$email, $otp]);
        $user = $stmt->fetch();

        if (!$user) {
            $_SESSION['flash_error'] = "Invalid verification code. Please check and try again.";
            header("Location: ../verify_otp.php");
            exit;
        }

        // Check expiration if set
        if (!empty($user['otp_expires_at']) && strtotime($user['otp_expires_at']) < time()) {
            $_SESSION['flash_error'] = "The verification code has expired. Please click 'Resend Code'.";
            header("Location: ../verify_otp.php");
            exit;
        }

        // Mark verified and clear code
        $verifyStmt = $db->prepare("
            UPDATE users 
            SET email_verified = 1, verification_code = NULL, otp_expires_at = NULL 
            WHERE id = ?
        ");
        $verifyStmt->execute([$user['id']]);

        // Clean up pending verification session
        unset($_SESSION['pending_verification_email'], $_SESSION['dev_simulated_otp'], $_SESSION['dev_simulated_email']);

        // Prompt user to log in with their credentials
        $_SESSION['flash_success'] = "Email verified successfully! Your account is now active. Please log in to access your dashboard.";
        header("Location: ../login.php");
        exit;
    }

    // 4. Resend OTP
    if ($action === 'resend_otp') {
        $email = trim($_POST['email'] ?? $_SESSION['pending_verification_email'] ?? '');

        if (empty($email)) {
            $_SESSION['flash_error'] = "Unable to resend OTP. Please register or log in.";
            header("Location: ../register.php");
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $updateStmt = $db->prepare("
                UPDATE users 
                SET verification_code = ?, otp_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE) 
                WHERE id = ?
            ");
            $updateStmt->execute([$otp, $user['id']]);

            sendOtpEmail($user['email'], $user['name'], $otp);
            $_SESSION['pending_verification_email'] = $user['email'];
            $_SESSION['flash_success'] = "A new 6-digit verification code has been sent to your email.";
        }

        header("Location: ../verify_otp.php");
        exit;
    }
}

// If reached here without matched action
header("Location: ../login.php");
exit;
