-- ========================================================
-- Dayflow - HR & Employee Resource Management System Schema
-- ========================================================

CREATE DATABASE IF NOT EXISTS `hrms` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hrms`;

-- 1. Departments Table
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('employee', 'hr') NOT NULL DEFAULT 'employee',
    `department_id` INT DEFAULT NULL,
    `designation` VARCHAR(100) DEFAULT 'Staff Member',
    `phone` VARCHAR(20) DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `salary` DECIMAL(10,2) DEFAULT 45000.00,
    `join_date` DATE DEFAULT CURRENT_DATE,
    `status` ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
    `email_verified` TINYINT(1) DEFAULT 0,
    `verification_code` VARCHAR(10) DEFAULT NULL,
    `otp_expires_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 3. Attendance Table
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `clock_in` TIME DEFAULT NULL,
    `clock_out` TIME DEFAULT NULL,
    `total_hours` DECIMAL(4,2) DEFAULT 0.00,
    `status` ENUM('present', 'late', 'half_day', 'absent', 'on_leave') DEFAULT 'present',
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_date` (`user_id`, `date`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Leaves Table
CREATE TABLE IF NOT EXISTS `leaves` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `leave_type` ENUM('Casual Leave', 'Sick Leave', 'Annual Leave', 'Maternity/Paternity', 'Emergency', 'Loss of Pay (Unpaid)') NOT NULL,
    `leave_category` ENUM('paid', 'unpaid') NOT NULL DEFAULT 'paid',
    `is_paid` TINYINT(1) NOT NULL DEFAULT 1,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `total_days` INT NOT NULL DEFAULT 1,
    `reason` TEXT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `reviewed_by` INT DEFAULT NULL,
    `review_comment` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. Announcements Table
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `content` TEXT NOT NULL,
    `category` ENUM('general', 'urgent', 'event', 'holiday') DEFAULT 'general',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('announcement', 'leave_approved', 'leave_rejected', 'leave_applied', 'general') DEFAULT 'general',
    `link` VARCHAR(255) DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed Default Departments
INSERT IGNORE INTO `departments` (`id`, `name`, `description`) VALUES
(1, 'Human Resources', 'People operations, talent acquisition, and employee relations'),
(2, 'Engineering & Tech', 'Software development, architecture, and IT operations'),
(3, 'Product & Design', 'UI/UX design, product strategy, and user research'),
(4, 'Marketing & Sales', 'Brand growth, digital campaigns, and enterprise sales'),
(5, 'Finance & Legal', 'Financial planning, accounting, and compliance');

-- Seed Default Users (Password: password123 for all demo users)
-- Default bcrypt hash for 'password123': $2y$10$tZ2zN04q.H0m65.1r9w.m.VlH8O31Z3z1g6qHkY3B1yQ8hC4kG0S6
INSERT IGNORE INTO `users` (`id`, `name`, `email`, `password`, `role`, `department_id`, `designation`, `phone`, `salary`, `join_date`, `status`, `email_verified`) VALUES
(1, 'Eleanor Vance', 'hr@dayflow.com', '$2y$10$wTcvzUv8FGBbWjH729/MLe4QYqMhW0Ucx3h1Nn56P22Z8PZ7a28eK', 'hr', 1, 'Head of People & HR', '+1 (555) 019-2834', 85000.00, '2023-01-15', 'active', 1),
(2, 'Alex Morgan', 'alex@dayflow.com', '$2y$10$wTcvzUv8FGBbWjH729/MLe4QYqMhW0Ucx3h1Nn56P22Z8PZ7a28eK', 'employee', 2, 'Senior Frontend Engineer', '+1 (555) 014-9921', 65000.00, '2023-04-10', 'active', 1),
(3, 'Sarah Jenkins', 'sarah@dayflow.com', '$2y$10$wTcvzUv8FGBbWjH729/MLe4QYqMhW0Ucx3h1Nn56P22Z8PZ7a28eK', 'employee', 3, 'Lead UI/UX Designer', '+1 (555) 017-4832', 62000.00, '2023-06-01', 'active', 1),
(4, 'David Chen', 'david@dayflow.com', '$2y$10$wTcvzUv8FGBbWjH729/MLe4QYqMhW0Ucx3h1Nn56P22Z8PZ7a28eK', 'employee', 2, 'Backend Architect', '+1 (555) 018-7744', 72000.00, '2023-02-20', 'active', 1),
(5, 'Marcus Brody', 'marcus@dayflow.com', '$2y$10$wTcvzUv8FGBbWjH729/MLe4QYqMhW0Ucx3h1Nn56P22Z8PZ7a28eK', 'employee', 4, 'Growth Marketing Lead', '+1 (555) 019-3321', 58000.00, '2023-08-15', 'active', 1);

-- Seed Sample Announcements
INSERT IGNORE INTO `announcements` (`id`, `title`, `content`, `category`, `created_by`, `created_at`) VALUES
(1, 'Welcome to Dayflow Portal! 🎉', 'We are thrilled to unveil Dayflow, our state-of-the-art HR and workspace management platform. Manage your time, leaves, and connect with your team seamlessly.', 'general', 1, NOW() - INTERVAL 5 DAY),
(2, 'Upcoming Company Townhall 🚀', 'Join us this Friday at 4:00 PM for our Q3 Company Townhall to review roadmap milestones and celebrate top contributors.', 'event', 1, NOW() - INTERVAL 2 DAY),
(3, 'Wellness & Healthcare Benefits Update 🩺', 'Updated health insurance policies and annual health checkup slots are now open for booking through the HR desk.', 'urgent', 1, NOW() - INTERVAL 1 DAY);

-- Seed Sample Leaves
INSERT IGNORE INTO `leaves` (`id`, `user_id`, `leave_type`, `start_date`, `end_date`, `total_days`, `reason`, `status`, `reviewed_by`, `review_comment`, `created_at`) VALUES
(1, 2, 'Casual Leave', CURRENT_DATE + INTERVAL 3 DAY, CURRENT_DATE + INTERVAL 4 DAY, 2, 'Family gathering and personal travel.', 'pending', NULL, NULL, NOW() - INTERVAL 1 DAY),
(2, 3, 'Sick Leave', CURRENT_DATE - INTERVAL 7 DAY, CURRENT_DATE - INTERVAL 6 DAY, 2, 'Viral fever recovery.', 'approved', 1, 'Approved. Get well soon!', NOW() - INTERVAL 8 DAY),
(3, 4, 'Annual Leave', CURRENT_DATE + INTERVAL 10 DAY, CURRENT_DATE + INTERVAL 14 DAY, 5, 'Annual vacation trip.', 'pending', NULL, NULL, NOW());

-- Seed Sample Attendance for Today
INSERT IGNORE INTO `attendance` (`user_id`, `date`, `clock_in`, `clock_out`, `total_hours`, `status`) VALUES
(1, CURRENT_DATE, '08:45:00', NULL, 0.00, 'present'),
(2, CURRENT_DATE, '09:02:10', NULL, 0.00, 'present'),
(3, CURRENT_DATE, '09:35:00', NULL, 0.00, 'late'),
(4, CURRENT_DATE, '08:55:00', NULL, 0.00, 'present');