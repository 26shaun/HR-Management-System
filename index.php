<?php
require_once __DIR__ . '/config/db.php';

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    if (isHR()) {
        header("Location: hr_dashboard.php");
    } else {
        header("Location: employee_dashboard.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dayflow - Next-Gen HR & Employee Management Platform</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hero-banner {
            padding: 5rem 2rem 4rem;
            text-align: center;
            max-width: 900px;
            margin: 0 auto;
        }
        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            padding: 0.4rem 1.1rem;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        .hero-title {
            font-size: 3.25rem;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 1.25rem;
            line-height: 1.15;
        }
        .hero-title span {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-subtitle {
            font-size: 1.15rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 2.25rem;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.75rem;
            max-width: 1100px;
            margin: 2rem auto 5rem;
            padding: 0 1.5rem;
        }
        .feature-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            transition: all var(--transition-fast);
        }
        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }
        .feature-icon {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-md);
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 1.25rem;
        }
    </style>
</head>
<body style="background: radial-gradient(circle at 50% 0%, rgba(99, 102, 241, 0.08) 0%, rgba(248, 250, 252, 1) 70%);">

    <!-- Landing Navigation -->
    <header style="padding: 1.25rem 2rem; display: flex; align-items: center; justify-content: space-between; max-width: 1200px; margin: 0 auto;">
        <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 1.5rem; font-weight: 800;">
            <div class="brand-icon">
                <i class="fa-solid fa-layer-group" style="color:#fff;"></i>
            </div>
            <span style="font-family: 'Outfit', sans-serif; font-weight: 800;">Dayflow</span>
        </div>
        <div style="display: flex; align-items: center; gap: 1rem;">
            <a href="login.php" class="btn btn-secondary">Sign In</a>
            <a href="register.php" class="btn btn-primary">Get Started</a>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-banner">
        <div class="hero-tag">
            <span class="live-pulse"></span> Complete Workforce & People Operations
        </div>
        <h1 class="hero-title">
            Empower your team with <span>Dayflow</span> HRMS.
        </h1>
        <p class="hero-subtitle">
            One unified platform with dedicated interactive dashboards for <strong>Human Resources</strong> and <strong>Employees</strong>. Track live attendance, manage leaves, view directories, and streamline company operations seamlessly.
        </p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="login.php" class="btn btn-primary btn-lg">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Launch Dayflow Portal
            </a>
            <a href="register.php" class="btn btn-secondary btn-lg">
                <i class="fa-solid fa-user-plus"></i> Create Account
            </a>
        </div>
    </section>

    <!-- Feature Grid -->
    <section class="feature-grid">
        <div class="feature-card">
            <div class="feature-icon" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h3 style="margin-bottom: 0.5rem; font-size: 1.25rem;">HR Management Hub</h3>
            <p style="color: var(--text-muted); font-size: 0.925rem;">
                Manage the entire employee lifecycle. Review and approve leaves, inspect company-wide daily attendance, and broadcast notices in real-time.
            </p>
        </div>

        <div class="feature-card">
            <div class="feature-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                <i class="fa-solid fa-fingerprint"></i>
            </div>
            <h3 style="margin-bottom: 0.5rem; font-size: 1.25rem;">Employee Self-Service</h3>
            <p style="color: var(--text-muted); font-size: 0.925rem;">
                1-click live punch clock, leave application tracking, personal salary details, department info, and real-time company announcements feed.
            </p>
        </div>

        <div class="feature-card">
            <div class="feature-icon" style="background: rgba(14, 165, 233, 0.15); color: #0ea5e9;">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <h3 style="margin-bottom: 0.5rem; font-size: 1.25rem;">Instant 1-Click Demos</h3>
            <p style="color: var(--text-muted); font-size: 0.925rem;">
                Experience both HR and Employee perspectives with ready-to-use pre-configured sample profiles and simulated operations.
            </p>
        </div>
    </section>

    <footer style="text-align: center; padding: 2rem; border-top: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.875rem;">
        &copy; <?= date('Y') ?> <strong>Dayflow HRMS</strong>. Engineered for modern high-performance teams.
    </footer>

</body>
</html>
