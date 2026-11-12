-- ICMS-DPT-RRT Database Schema
-- Institutional Concern Management System with Dynamic Prioritization and Resolution Tracking
-- Surigao del Norte State University (SNSU)

CREATE DATABASE IF NOT EXISTS `icms_dpt_rrt` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `icms_dpt_rrt`;

-- 1. Students Table
CREATE TABLE IF NOT EXISTS `students` (
  `student_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_no` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `department` VARCHAR(150) NOT NULL DEFAULT 'College of Computing & Information Sciences',
  `program` VARCHAR(150) NOT NULL DEFAULT 'Bachelor of Science in Information Technology',
  `phone` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Administrators Table
CREATE TABLE IF NOT EXISTS `administrators` (
  `admin_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `department` VARCHAR(150) NOT NULL DEFAULT 'Administration',
  `role` VARCHAR(100) DEFAULT 'Administrator',
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Statuses Table
CREATE TABLE IF NOT EXISTS `statuses` (
  `status_id` INT AUTO_INCREMENT PRIMARY KEY,
  `status_name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Concerns Table
CREATE TABLE IF NOT EXISTS `concerns` (
  `concern_id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` VARCHAR(50) NOT NULL UNIQUE,
  `student_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `status_id` INT NOT NULL DEFAULT 1,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `attachment_path` VARCHAR(255) DEFAULT NULL,
  `priority` ENUM('Critical', 'High', 'Medium', 'Low') DEFAULT 'Medium',
  `ai_urgency_score` INT DEFAULT 50,
  `sla_hours` INT DEFAULT 72,
  `is_rrt_alert` TINYINT(1) DEFAULT 0,
  `date_submitted` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`ticket_id`),
  INDEX (`student_id`),
  INDEX (`category_id`),
  INDEX (`status_id`),
  INDEX (`priority`),
  CONSTRAINT `fk_concern_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_concern_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_concern_status` FOREIGN KEY (`status_id`) REFERENCES `statuses` (`status_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Responses Table (Audit trail & timeline updates)
CREATE TABLE IF NOT EXISTS `responses` (
  `response_id` INT AUTO_INCREMENT PRIMARY KEY,
  `concern_id` INT NOT NULL,
  `admin_id` INT NOT NULL,
  `response_text` TEXT NOT NULL,
  `department_endorsed` VARCHAR(150) DEFAULT NULL,
  `status_id` INT NOT NULL,
  `date_responded` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`concern_id`),
  INDEX (`admin_id`),
  CONSTRAINT `fk_response_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`concern_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_response_admin` FOREIGN KEY (`admin_id`) REFERENCES `administrators` (`admin_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_response_status` FOREIGN KEY (`status_id`) REFERENCES `statuses` (`status_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Announcements Table
CREATE TABLE IF NOT EXISTS `announcements` (
  `announcement_id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `category` VARCHAR(100) DEFAULT 'General',
  `is_urgent` TINYINT(1) DEFAULT 0,
  `published_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`admin_id`),
  CONSTRAINT `fk_announcement_admin` FOREIGN KEY (`admin_id`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Feedback Table
CREATE TABLE IF NOT EXISTS `feedback` (
  `feedback_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT DEFAULT NULL,
  `student_name` VARCHAR(150) NOT NULL,
  `student_email` VARCHAR(150) NOT NULL,
  `feedback_type` VARCHAR(100) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `rating` INT DEFAULT 5,
  `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`student_id`),
  CONSTRAINT `fk_feedback_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
