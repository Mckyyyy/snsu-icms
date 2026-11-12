-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 10, 2026 at 04:06 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `icms_dpt_rrt`
--

-- --------------------------------------------------------

--
-- Table structure for table `administrators`
--

CREATE TABLE `administrators` (
  `admin_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `department` varchar(150) NOT NULL DEFAULT 'Administration',
  `role` varchar(100) DEFAULT 'Administrator',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `administrators`
--

INSERT INTO `administrators` (`admin_id`, `name`, `email`, `password`, `must_change_password`, `department`, `role`, `status`, `last_login_at`, `created_at`, `archived_at`, `archived_by`) VALUES
(1, 'Admin User', 'admin@school.edu', '$2y$10$acCeh0ltJbYlIHQIEbmGLetMkUXQH/hs2ulCdkoq2XR7Yf2KsfMA.', 0, 'Administration', 'Administrator', 'Active', '2026-11-10 23:01:45', '2024-01-10 08:00:00', NULL, NULL),
(2, 'Engr. Carlos Mendoza', 'facilities@snsu.edu.ph', '$2y$10$VkLNYbo7ju1x6cRtzmFT1.gKFjkOvZ2TbjrTTe4mjuVO2gE35ZV6K', 0, 'Facilities & Maintenance', 'Department Head', 'Active', '2026-11-09 00:17:25', '2024-01-15 09:30:00', NULL, NULL),
(3, 'Dr. Elena Ramos', 'ccis.dean@snsu.edu.ph', '$2y$12$3mKzfLC8paoDd8DbrngpoORAb6jDcXOdItroijuMq/F4o9WGhu1Au', 0, 'College of Computing & Information Sciences', 'Dean', 'Active', NULL, '2024-02-01 10:00:00', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_logs`
--

CREATE TABLE `admin_activity_logs` (
  `activity_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `target_type` varchar(30) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_activity_logs`
--

INSERT INTO `admin_activity_logs` (`activity_id`, `admin_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'announcement_published', 'announcement', 3, '[Urgent] Class Suspension', '127.0.0.1', '2026-11-10 14:58:54'),
(2, 1, 'concern_assigned', 'concern', 11, 'SCF-2026-011 → Engr. Carlos Mendoza', '127.0.0.1', '2026-11-10 20:10:54'),
(3, 1, 'announcement_published', 'announcement', 1, 'Scheduled Maintenance', '127.0.0.1', '2026-11-09 20:58:54'),
(33, 1, 'student_approved', 'student', 7, 'Kevin Lim (SNSU-2025-07777)', '::1', '2026-11-10 23:03:04');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `category` varchar(100) DEFAULT 'General',
  `is_urgent` tinyint(1) DEFAULT 0,
  `published_at` datetime DEFAULT current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `admin_id`, `title`, `content`, `category`, `is_urgent`, `published_at`, `archived_at`, `archived_by`) VALUES
(1, 1, 'Scheduled Maintenance', 'The system will undergo maintenance on November 14, 2026 from 1:00 AM to 3:00 AM. Please save any pending work ahead of time.', 'Maintenance', 0, '2026-11-09 20:58:54', NULL, NULL),
(2, 1, 'ID Printing Schedule', 'ID printing will be available from November 16 to November 23, 2026 at the Admin Office between 8:00 AM and 4:00 PM.', 'Services', 0, '2026-11-08 20:58:54', NULL, NULL),
(3, 1, 'Class Suspension', 'Classes are suspended on November 13, 2026 (Friday) for the university foundation anniversary and school events.', 'Academic', 1, '2026-11-10 14:58:54', NULL, NULL),
(4, 1, 'Enrollment for the Second Semester', 'Online enrollment for the second semester opens on November 20, 2026. Settle your clearance at your college before enlisting your subjects.', 'Academic', 0, '2026-11-07 23:01:08', NULL, NULL),
(5, 2, 'Water Interruption Advisory', 'There will be no water supply in the main campus on November 12 from 8:00 AM to 12:00 NN due to pipe repairs near the gymnasium. Please store water ahead of time.', 'Maintenance', 1, '2026-11-10 20:01:08', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`, `is_active`, `created_at`, `archived_at`, `archived_by`) VALUES
(1, 'Facilities', 'Physical infrastructure, classrooms, restrooms, air-conditioning, lights, and lab equipment.', 1, '2026-11-09 13:47:13', NULL, NULL),
(2, 'Academic', 'Grading inquiries, examination schedules, faculty concerns, and course curriculum.', 1, '2026-11-09 13:47:13', NULL, NULL),
(3, 'Services', 'Campus IT network, student ID card printing, library services, and canteen.', 1, '2026-11-09 13:47:13', NULL, NULL),
(4, 'Student Welfare', 'Guidance, scholarships, health services, and student organizations.', 1, '2026-11-09 13:47:13', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `chat_presence`
--

CREATE TABLE `chat_presence` (
  `user_type` enum('student','admin') NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_seen_at` datetime DEFAULT NULL,
  `typing_conversation_id` int(11) DEFAULT NULL,
  `typing_at` datetime(3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_presence`
--

INSERT INTO `chat_presence` (`user_type`, `user_id`, `last_seen_at`, `typing_conversation_id`, `typing_at`) VALUES
('student', 1, NULL, NULL, NULL),
('student', 9, '2026-11-10 23:01:22', NULL, NULL),
('student', 13, '2026-11-10 23:06:04', NULL, NULL),
('student', 18, '2026-11-10 23:01:25', NULL, NULL),
('admin', 1, '2026-11-10 23:06:09', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `concerns`
--

CREATE TABLE `concerns` (
  `concern_id` int(11) NOT NULL,
  `ticket_id` varchar(50) NOT NULL,
  `student_id` int(11) NOT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `category_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL DEFAULT 1,
  `assigned_admin_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `priority` enum('Critical','High','Medium','Low') DEFAULT 'Medium',
  `ai_urgency_score` int(11) DEFAULT 50,
  `ai_reasons` text DEFAULT NULL,
  `ai_engine` varchar(20) DEFAULT NULL,
  `ai_fix_hours` smallint(6) DEFAULT NULL,
  `ai_fix_note` varchar(160) DEFAULT NULL,
  `ai_suggested_category_id` int(11) DEFAULT NULL,
  `sla_hours` int(11) DEFAULT 72,
  `sla_due_at` datetime DEFAULT NULL,
  `sla_alert_level` enum('none','at_risk','breached') NOT NULL DEFAULT 'none',
  `first_response_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `reopen_count` tinyint(4) NOT NULL DEFAULT 0,
  `last_reopened_at` datetime DEFAULT NULL,
  `withdrawn_at` datetime DEFAULT NULL,
  `withdraw_reason` varchar(255) DEFAULT NULL,
  `edited_at` datetime DEFAULT NULL,
  `edit_count` tinyint(4) NOT NULL DEFAULT 0,
  `is_rrt_alert` tinyint(1) DEFAULT 0,
  `date_submitted` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `concerns`
--

INSERT INTO `concerns` (`concern_id`, `ticket_id`, `student_id`, `is_anonymous`, `category_id`, `status_id`, `assigned_admin_id`, `title`, `description`, `attachment_path`, `priority`, `ai_urgency_score`, `ai_reasons`, `ai_engine`, `ai_fix_hours`, `ai_fix_note`, `ai_suggested_category_id`, `sla_hours`, `sla_due_at`, `sla_alert_level`, `first_response_at`, `resolved_at`, `reopen_count`, `last_reopened_at`, `withdrawn_at`, `withdraw_reason`, `edited_at`, `edit_count`, `is_rrt_alert`, `date_submitted`, `updated_at`, `archived_at`, `archived_by`) VALUES
(1, 'SCF-2026-001', 1, 0, 3, 2, 1, 'ID printing machine not working', 'The card printer in the student services office is malfunctioning and jamming cards.', NULL, 'Medium', 45, '[\"Malfunctioning card printer disrupts ID issuance service\",\"Not an immediate danger or blocking classes\"]', 'gemini', 48, 'Repair or service the ID card printer', NULL, 72, '2026-11-10 14:58:54', 'breached', '2026-11-07 19:58:54', NULL, 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-07 14:58:54', '2026-11-10 23:01:21', NULL, NULL),
(2, 'SCF-2026-002', 1, 0, 1, 3, 1, 'Canteen foods are always cold', 'Food served in the central canteen warmers is frequently served cold during lunch breaks.', NULL, 'Low', 20, '[\"Minor inconvenience regarding food temperature at the canteen\",\"No immediate danger, health hazard, or blocked classes\"]', 'gemini', 24, 'Inspect and adjust canteen food warmer temperature settings', NULL, 120, '2026-11-09 16:58:54', 'none', '2026-11-04 22:58:54', '2026-11-05 20:58:54', 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-04 16:58:54', '2026-11-10 22:58:54', NULL, NULL),
(3, 'SCF-2026-003', 1, 0, 2, 3, 3, 'Clarification about exam schedule', 'There is an overlap between IT 312 and CS 311 mid-term examination rooms.', NULL, 'Medium', 45, '[\"Exam schedule overlap requires coordination between departments\",\"Causes medium service disruption but no immediate danger\"]', 'gemini', 48, 'Reschedule exam room assignments', NULL, 72, '2026-11-09 08:58:54', 'none', '2026-11-07 04:58:54', '2026-11-07 04:58:54', 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-06 08:58:54', '2026-11-10 22:58:54', NULL, NULL),
(4, 'SCF-2026-004', 1, 0, 3, 1, NULL, 'Internet connection issue in Library', 'Wi-Fi connection drops every 5 minutes on the 2nd floor library study area.', NULL, 'Medium', 40, '[\"Intermittent Wi-Fi connection in the library study area\",\"Not blocking classes but affects research work\"]', 'gemini', 24, 'Troubleshoot and reconfigure access point', NULL, 72, '2026-11-10 14:58:54', 'breached', NULL, NULL, 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-07 14:58:54', '2026-11-10 23:01:21', NULL, NULL),
(5, 'SCF-2026-005', 1, 0, 1, 2, 1, 'Broken chairs in classroom Lab 2', 'There are several broken chairs in our classroom Lab 2. It is uncomfortable and unsafe to use. Please kindly address this issue. Thank you.', 'uploads/broken_chair.jpg', 'Medium', 45, '[\"Broken chairs affect student comfort and minor safety.\",\"No immediate danger to life or health.\",\"Routine maintenance is needed to replace or fix chairs.\"]', 'gemini', 48, 'Repair or replace broken classroom chairs', NULL, 72, '2026-11-12 16:58:54', 'none', '2026-11-09 20:58:54', NULL, 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-09 16:58:54', '2026-11-10 22:58:54', NULL, NULL),
(6, 'SCF-2026-006', 6, 0, 2, 3, 3, 'Online class attendance error', 'Attendance in LMS for May 2 meeting marked absent despite submitting activity on time.', NULL, 'Medium', 40, '[\"LMS attendance record discrepancy does not block ongoing classes.\",\"Issue requires minor correction by instructor or admin.\"]', 'gemini', 48, 'Verify submission timestamp and update LMS attendance record.', NULL, 72, '2026-11-04 18:58:54', 'none', '2026-11-03 00:58:54', '2026-11-05 12:58:54', 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-01 18:58:54', '2026-11-10 22:58:54', NULL, NULL),
(7, 'SCF-2026-007', 5, 0, 1, 1, NULL, 'Comfort room is not clean 3rd floor', 'The 3rd floor west wing comfort room lacks running water and sanitation supplies.', NULL, 'Medium', 45, '[\"Lack of water and sanitation supplies in restrooms\",\"Causes inconvenience and sanitation issues for students\"]', 'gemini', 24, 'Restore water supply and replenish supplies', NULL, 72, '2026-11-13 02:58:54', 'none', NULL, NULL, 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-10 02:58:54', '2026-11-10 22:58:54', NULL, NULL),
(8, 'SCF-2026-008', 4, 0, 2, 2, 3, 'Need more reference books for Academic', 'Library currently has only one copy of Modern Database Systems for 120 enrolled students.', NULL, 'Low', 30, '[\"Insufficient textbook copies for enrolled students\",\"Causes minor inconvenience without blocking classes\"]', 'gemini', 120, 'Procure additional reference book copies', NULL, 120, '2026-11-13 20:58:54', 'none', '2026-11-09 06:58:54', NULL, 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-08 20:58:54', '2026-11-10 22:58:54', NULL, NULL),
(9, 'SCF-2026-009', 2, 0, 3, 2, 1, 'ID printing machine error', 'Queuing error in printing machine software causes repeated duplicate student numbers.', NULL, 'Medium', 40, '[\"Software queuing error in ID printing machine.\",\"Causes inconvenience and duplicate student numbers.\",\"Does not pose an immediate safety hazard.\"]', 'gemini', 24, 'Troubleshoot and fix ID printing software error', NULL, 72, '2026-11-11 10:58:54', 'at_risk', '2026-11-08 16:58:54', NULL, 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-08 10:58:54', '2026-11-10 23:01:21', NULL, NULL),
(10, 'SCF-2026-010', 1, 0, 1, 1, NULL, 'Projector not working in Rm 304', 'The ceiling projector HDMI port in Room 304 is completely detached and not displaying output.', NULL, 'Medium', 50, '[\"Broken classroom equipment disrupting teaching activities\",\"Not an immediate life safety hazard\"]', 'gemini', 48, 'Repair or replace the HDMI port on the projector', NULL, 72, '2026-11-12 06:58:54', 'none', NULL, NULL, 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-09 06:58:54', '2026-11-10 22:58:54', NULL, NULL),
(11, 'SCF-2026-011', 1, 0, 1, 1, 2, 'Exposed live wiring and water leak near computer terminals in CCIS Lab 1', 'Water is dripping from the AC unit directly onto an exposed 220V power extension strip behind PC terminals 12-16. Sparks were observed. Immediate safety hazard!', 'uploads/lab1_hazard.jpg', 'Critical', 95, '[\"Immediate danger of electrocution and fire from water leaking on live wires\",\"Sparks observed near computer terminals posing a critical safety threat\"]', 'gemini', 2, 'Cut power, repair AC leak, and fix electrical wiring', NULL, 24, '2026-11-11 19:58:54', 'none', '2026-11-10 20:13:54', NULL, 0, NULL, NULL, NULL, NULL, 0, 1, '2026-11-10 19:58:54', '2026-11-10 22:58:54', NULL, NULL),
(12, 'SCF-2026-012', 2, 1, 4, 1, NULL, 'Request for counseling schedule during exam week', 'Several of us in BSCS 2-A are feeling a lot of stress before the midterms. Can the Guidance Office open extra counseling slots this week?', NULL, 'Low', 30, '[\"Request for counseling slots due to exam stress\",\"No immediate danger or crisis reported\"]', 'gemini', 48, 'Schedule extra counseling slots', 4, 120, '2026-11-15 17:58:54', 'none', NULL, NULL, 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-10 17:58:54', '2026-11-10 22:58:57', NULL, NULL),
(13, 'SCF-2026-013', 3, 0, 1, 3, 2, 'Aircon not working in Room 201', 'The air-conditioning unit in Room 201 of the CCIS building has not been working since Monday. The room gets very hot in the afternoon and around 40 students attend classes there.', NULL, 'Medium', 50, '[\"Broken air conditioning unit causes discomfort during afternoon classes\",\"Not an immediate life safety hazard but affects classroom environment\"]', 'gemini', 48, 'Repair or service the aircon unit', NULL, 72, '2026-10-31 04:58:57', 'none', '2026-10-28 09:58:57', '2026-10-29 10:58:57', 0, NULL, NULL, NULL, NULL, 0, 0, '2026-10-28 04:58:57', '2026-11-10 22:59:01', NULL, NULL),
(14, 'SCF-2026-014', 4, 0, 3, 3, 1, 'Enrollment portal very slow during peak hours', 'The online enrollment portal takes several minutes to load and often times out between 8 AM and 11 AM. Some of us could not finish enlisting our subjects.', NULL, 'Medium', 45, '[\"Portal slowness during peak hours is a real service problem\",\"Does not completely block classes but causes significant delays\"]', 'gemini', 24, 'Optimize server bandwidth and database queries', NULL, 72, '2026-11-01 10:58:57', 'none', '2026-10-29 13:58:57', '2026-10-31 12:58:57', 0, NULL, NULL, NULL, NULL, 0, 0, '2026-10-29 10:58:57', '2026-11-10 22:59:07', NULL, NULL),
(15, 'SCF-2026-015', 6, 0, 1, 3, 2, 'Leaking faucet in the 2nd floor comfort room, Engineering building', 'One faucet in the male comfort room on the 2nd floor of the Engineering building keeps running and cannot be closed. Water is being wasted the whole day.', NULL, 'Low', 35, '[\"Minor plumbing issue causing water wastage without causing immediate danger.\",\"Does not block classes or pose a health or safety threat.\"]', 'gemini', 24, 'Repair or replace faulty faucet valve.', NULL, 120, '2026-11-04 06:58:57', 'none', '2026-10-30 14:58:57', '2026-10-30 14:58:57', 0, NULL, NULL, NULL, NULL, 0, 0, '2026-10-30 06:58:57', '2026-11-10 22:59:26', NULL, NULL),
(16, 'SCF-2026-016', 2, 0, 2, 3, 3, 'Grade for IT 211 not yet encoded', 'My final grade for IT 211 (Data Structures) is still not showing in the student portal even though the deadline for encoding was last week.', NULL, 'High', 65, '[\"Final grade encoding deadline has already passed\",\"Missing grades disrupt academic evaluation and records\"]', 'gemini', 48, 'Instructor encoding missing grade in portal', NULL, 48, '2026-11-02 02:58:57', 'none', '2026-10-31 14:58:57', '2026-11-01 18:58:57', 0, NULL, NULL, NULL, NULL, 0, 0, '2026-10-31 02:58:57', '2026-11-10 22:59:35', NULL, NULL),
(17, 'SCF-2026-017', 5, 0, 4, 3, 3, 'Scholarship stipend delayed for two months', 'Our CHED scholarship stipend for September and October has not been released yet. Many of us rely on it for transportation and board.', NULL, 'Medium', 50, '[\"Delayed scholarship stipend affects student finances\",\"Real service issue causing personal inconvenience\"]', 'gemini', 72, 'Coordinate scholarship release with finance office', NULL, 72, '2026-11-03 22:58:57', 'none', '2026-11-01 18:58:57', '2026-11-06 08:58:57', 0, NULL, NULL, NULL, NULL, 0, 0, '2026-10-31 22:58:57', '2026-11-10 22:59:37', NULL, NULL),
(18, 'SCF-2026-018', 4, 0, 1, 3, 2, 'Broken door knob in Room 105', 'The door knob of Room 105 is broken and the door cannot be locked. Equipment inside the room is not secure after classes.', NULL, 'Medium', 45, '[\"Broken door knob poses a security risk to equipment.\",\"No immediate danger to life or safety.\",\"Requires maintenance repair under normal campus operations.\"]', 'gemini', 24, 'Replace or repair door knob', NULL, 72, '2026-11-05 14:58:57', 'none', '2026-11-02 20:58:57', '2026-11-02 20:58:57', 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-02 14:58:57', '2026-11-10 22:59:45', NULL, NULL),
(19, 'SCF-2026-019', 2, 0, 3, 3, 1, 'Library closes earlier than the posted schedule', 'The library posted that it is open until 7 PM but the staff has been closing it at 5 PM this week. We cannot study there after classes.', NULL, 'Low', 30, '[\"Library closing early disrupts evening study time\",\"No safety threat or class disruption\",\"Administrative service schedule discrepancy\"]', 'gemini', 24, 'Coordinate with library staff regarding operating hours', NULL, 120, '2026-11-08 20:58:57', 'none', '2026-11-04 06:58:57', '2026-11-04 06:58:57', 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-03 20:58:57', '2026-11-10 23:00:03', NULL, NULL),
(20, 'SCF-2026-020', 3, 0, 1, 3, 2, 'Flickering lights in Room 302 causing headaches', 'Most of the fluorescent lights in Room 302 are flickering. Several classmates complain of headaches and eye strain during long lectures.', NULL, 'Medium', 45, '[\"Flickering lights cause discomfort and headaches during classes.\",\"It is a service and facility problem not completely blocking classes.\"]', 'gemini', 24, 'Inspect and replace flickering fluorescent tubes.', NULL, 72, '2026-11-08 02:58:57', 'none', '2026-11-05 06:58:57', '2026-11-05 22:58:57', 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-05 02:58:57', '2026-11-10 23:00:07', NULL, NULL),
(21, 'SCF-2026-021', 1, 0, 2, 2, 3, 'Mabagal ang pag-release ng TOR sa Registrar', 'Nag-request ako ng Transcript of Records noong isang linggo para sa aking scholarship application pero hanggang ngayon wala pa. Kailangan ko na ito sa Biyernes.', NULL, 'High', 65, '[\"TOR needed for a scholarship application by Friday\",\"Delay in releasing critical academic document\"]', 'gemini', 48, 'Process and release the requested TOR', NULL, 48, '2026-11-10 20:58:57', 'breached', '2026-11-09 02:58:57', NULL, 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-08 20:58:57', '2026-11-10 23:01:21', NULL, NULL),
(22, 'SCF-2026-022', 6, 0, 2, 2, 3, 'Request for additional sections of CS 321', 'Only one section of CS 321 was opened and it is already full. At least 30 students still need the subject to graduate on time.', NULL, 'High', 65, '[\"Affects students\' ability to graduate on time\",\"Requires administrative scheduling action within days\"]', 'gemini', 48, 'Open additional class section and assign faculty', NULL, 48, '2026-11-08 22:58:57', 'breached', '2026-11-07 16:58:57', NULL, 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-06 22:58:57', '2026-11-10 23:01:21', NULL, NULL),
(23, 'SCF-2026-023', 5, 0, 1, 2, 2, 'Water dispenser empty in the CCIS lobby', 'The water dispenser in the CCIS lobby has been empty for three days. Students have to go to the canteen to buy water.', NULL, 'Low', 30, '[\"Minor inconvenience regarding campus water supply\",\"Alternative source available at the canteen\"]', 'gemini', 24, 'Refill or replace water container', NULL, 120, '2026-11-14 16:58:57', 'none', '2026-11-09 21:58:57', NULL, 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-09 16:58:57', '2026-11-10 23:00:32', NULL, NULL),
(24, 'SCF-2026-024', 3, 1, 4, 1, NULL, 'Classmates being bullied in our block group chat', 'Some students in our block group chat keep posting insulting messages and edited photos of two classmates. One of them stopped attending classes. Please help.', NULL, 'High', 65, '[\"Cyberbullying and harassment occurring within the student block group chat.\",\"Causes severe distress resulting in a student missing classes.\"]', 'gemini', 48, 'Guidance intervention and disciplinary investigation', NULL, 48, '2026-11-12 12:58:57', 'none', NULL, NULL, 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-10 12:58:57', '2026-11-10 23:00:55', NULL, NULL),
(25, 'SCF-2026-025', 6, 0, 3, 1, NULL, 'No Wi-Fi signal in the Engineering building', 'The campus Wi-Fi cannot be detected anywhere in the Engineering building since yesterday. We cannot access the LMS for our online quizzes.', NULL, 'Medium', 50, '[\"Wi-Fi outage disrupts online quizzes and learning tasks\",\"Moderate service issue with workarounds available\"]', 'gemini', 24, 'IT office to troubleshoot router or network switch in building', NULL, 72, '2026-11-13 08:58:57', 'none', NULL, NULL, 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-10 08:58:57', '2026-11-10 23:00:58', NULL, NULL),
(26, 'SCF-2026-026', 4, 0, 1, 1, NULL, 'Broken stair railing near the gymnasium', 'The metal railing of the stairs near the gymnasium is loose and partly detached. Students hold on to it while going down and someone could fall.', NULL, 'High', 75, '[\"Loose metal railing poses an immediate safety hazard\",\"High risk of falls and potential student injury\",\"Requires prompt repair within two days\"]', 'gemini', 24, 'Weld or secure the detached stair railing', NULL, 48, '2026-11-12 16:58:57', 'none', NULL, NULL, 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-10 16:58:57', '2026-11-10 23:01:05', NULL, NULL),
(27, 'SCF-2026-027', 5, 0, 1, 1, NULL, 'Guba ang electric fan sa Room 108', 'Ang duha ka electric fan sa Room 108 dili na molihok. Init kaayo sa sulod labi na sa hapon.', NULL, 'Medium', 45, '[\"Broken electric fans causing discomfort in the classroom\",\"Does not block classes immediately but needs maintenance\"]', 'gemini', 48, 'Repair or replace faulty electric fans', NULL, 72, '2026-11-13 02:58:57', 'none', NULL, NULL, 0, NULL, NULL, NULL, NULL, 0, 0, '2026-11-10 02:58:57', '2026-11-10 23:01:08', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `concern_attachments`
--

CREATE TABLE `concern_attachments` (
  `attachment_id` int(11) NOT NULL,
  `concern_id` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL,
  `sha256` char(64) NOT NULL,
  `file_data` longblob NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `concern_attachments`
--

INSERT INTO `concern_attachments` (`attachment_id`, `concern_id`, `original_name`, `mime_type`, `file_size`, `sha256`, `file_data`, `uploaded_at`) VALUES
(1, 11, 'demo_lab1_hazard.jpg', 'image/jpeg', 44954, '61b9a3ec83e6b398a69df18f761583cc03617f69dc9a25a44b423d47ec85843f', 0xffd8ffe000104a46494600010101006000600000fffe003b43524541544f523a2067642d6a7065672076312e3020287573696e6720494a47204a50454720763930292c207175616c697479203d2038350affdb0043000503040404030504040405050506070c08070707070f0b0b090c110f1212110f111113161c1713141a1511111821181a1d1d1f1f1f13172224221e241c1e1f1effdb0043010505050706070e08080e1e1411141e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1effc0001108025803c003012200021101031101ffc4001f0000010501010101010100000000000000000102030405060708090a0bffc400b5100002010303020403050504040000017d01020300041105122131410613516107227114328191a1082342b1c11552d1f02433627282090a161718191a25262728292a3435363738393a434445464748494a535455565758595a636465666768696a737475767778797a838485868788898a92939495969798999aa2a3a4a5a6a7a8a9aab2b3b4b5b6b7b8b9bac2c3c4c5c6c7c8c9cad2d3d4d5d6d7d8d9dae1e2e3e4e5e6e7e8e9eaf1f2f3f4f5f6f7f8f9faffc4001f0100030101010101010101010000000000000102030405060708090a0bffc400b51100020102040403040705040400010277000102031104052131061241510761711322328108144291a1b1c109233352f0156272d10a162434e125f11718191a262728292a35363738393a434445464748494a535455565758595a636465666768696a737475767778797a82838485868788898a92939495969798999aa2a3a4a5a6a7a8a9aab2b3b4b5b6b7b8b9bac2c3c4c5c6c7c8c9cad2d3d4d5d6d7d8d9dae2e3e4e5e6e7e8e9eaf2f3f4f5f6f7f8f9faffda000c03010002110311003f00f0fc518a7515e21fa7d86e28c53a8a02c3714629d450161b8a314ea280b0dc518a75140586e28c53a8a02c3714629d450161b8a314ea280b0dc518a75140586e28c53a8a02c3714629d450161b8a314ea280b0dc518a75140586e28c53a8a02c3714629d450161b8a314ea280b0dc518a75140586e28c53a8a02c3714629d450161b8a314ea280b0dc518a75140586e28c53a8a02c3714629d450161b8a314ea280b0dc518a75140586e28c53a8a02c3714629d450161b8a314ea280b0dc518a75140586e28c53a8a02c3714629d450161b8a314ea280b0dc518a75140586e28c53a8a02c3714629d450161b8a314ea280b0dc518a75140586e28c53a8a02c3714629d450161b8a314ea280b0dc518a75140586e28c53a8a02c3714629d450161b8a314ea280b0dc518a75140586e28c53a8a02c3714629d450161b8a314ea280b0dc518a75140586e28c53a8a02c3714629d450161b8a314ea280b0dc518a75140586e28c53a8a02c3714629d450161b8a314ea280b0dc518a75140586e28c53a8a02c3714629d450161b8a314ea280b0b4514503b051451405828a28a02c1451450160a28a280b051451405828a28a02c1451450160a28a280b051451405828a28a02c1451450160a28a280b051451405828a28a02c1451450160a28a280b051451405828a28a02c1451450160a28a280b051451405828a28a02c1451450160a28a280b051451405828a28a02c1451450160a28a280b051451405828a28a02c1451450160a28a280b051451405828a28a02c1451450160a28a280b051451405828a28a02c1451450160a28a280b051451405828a28a02c1451450160a28a280b051451405828a28a02c1451450160a28a280b051451405828a28a02c1451450160a28a280b051451405828a28a02c1451450160a28a280b051451405828a28a02c1451450161d8a314ea291561b8a314ea280b0dc518a757d19e37f15789edfc69ae5bdbf88f588618b51b848e34bd91551448c0000360003b56908a92396bd795292495ee7ce38a315eedff0009878b7fe868d6ff00f03e5ffe2a8ff84c3c5bff004346b7ff0081f2ff00f1555ecd7731fadcff00957deffc8f09c518af76ff0084c3c5bff4346b7ff81f2fff001547fc261e2dff00a1a35bff00c0f97ff8aa3d9aee1f5b9ff2afbdff0091e138a315eedff09878b7fe868d6fff0003e5ff00e2a8ff0084c3c5bff4346b7ff81f2fff001547b35dc3eb73fe55f7bff23c271462bddbfe130f16ff00d0d1adff00e07cbffc551ff09878b7fe868d6fff0003e5ff00e2a8f66bb87d6e7fcabef7fe4784e28c57bb7fc261e2dffa1a35bffc0f97ff008aa3fe130f16ff00d0d1adff00e07cbffc551ecd770fadcff957deff00c8f09c518af76ff84c3c5bff004346b7ff0081f2ff00f1547fc261e2dffa1a35bffc0f97ff008aa3d9aee1f5b9ff002afbdff91e138a315eedff0009878b7fe868d6ff00f03e5ffe2a8ff84c3c5bff004346b7ff0081f2ff00f1547b35dc3eb73fe55f7bff0023c271462bddbfe130f16ffd0d1adffe07cbff00c551ff0009878b7fe868d6ff00f03e5ffe2a8f66bb87d6e7fcabef7fe4784e28c57bb7fc261e2dff00a1a35bff00c0f97ff8aa3fe130f16ffd0d1adffe07cbff00c551ecd770fadcff00957deffc8f09c518af76ff0084c3c5bff4346b7ff81f2fff001547fc261e2dff00a1a35bff00c0f97ff8aa3d9aee1f5b9ff2afbdff0091e138a315eedff09878b7fe868d6fff0003e5ff00e2a8ff0084c3c5bff4346b7ff81f2fff001547b35dc3eb73fe55f7bff23c271462bddbfe130f16ff00d0d1adff00e07cbffc551ff09878b7fe868d6fff0003e5ff00e2a8f66bb87d6e7fcabef7fe4784e28c57bb7fc261e2dffa1a35bffc0f97ff008aa3fe130f16ff00d0d1adff00e07cbffc551ecd770fadcff957deff00c8f09c518af76ff84c3c5bff004346b7ff0081f2ff00f1547fc261e2dffa1a35bffc0f97ff008aa3d9aee1f5b9ff002afbdff91e138a315eedff0009878b7fe868d6ff00f03e5ffe2a8ff84c3c5bff004346b7ff0081f2ff00f1547b35dc3eb73fe55f7bff0023c271462bddbfe130f16ffd0d1adffe07cbff00c551ff0009878b7fe868d6ff00f03e5ffe2a8f66bb87d6e7fcabef7fe4784e28c57bb7fc261e2dff00a1a35bff00c0f97ff8aa3fe130f16ffd0d1adffe07cbff00c551ecd770fadcff00957deffc8f09c518af76ff0084c3c5bff4346b7ff81f2fff001547fc261e2dff00a1a35bff00c0f97ff8aa3d9aee1f5b9ff2afbdff0091e138a315eedff09878b7fe868d6fff0003e5ff00e2a8ff0084c3c5bff4346b7ff81f2fff001547b35dc3eb73fe55f7bff23c271462bddbfe130f16ff00d0d1adff00e07cbffc551ff09878b7fe868d6fff0003e5ff00e2a8f66bb87d6e7fcabef7fe4784e28c57bb7fc261e2dffa1a35bffc0f97ff008aa3fe130f16ff00d0d1adff00e07cbffc551ecd770fadcff957deff00c8f09c518af76ff84c3c5bff004346b7ff0081f2ff00f1547fc261e2dffa1a35bffc0f97ff008aa3d9aee1f5b9ff002afbdff91e138a315eedff0009878b7fe868d6ff00f03e5ffe2a8ff84c3c5bff004346b7ff0081f2ff00f1547b35dc3eb73fe55f7bff0023c271462bddbfe130f16ffd0d1adffe07cbff00c551ff0009878b7fe868d6ff00f03e5ffe2a8f66bb87d6e7fcabef7fe4784e28c57bb7fc261e2dff00a1a35bff00c0f97ff8aa3fe130f16ffd0d1adffe07cbff00c551ecd770fadcff00957deffc8f09c518af76ff0084c3c5bff4346b7ff81f2fff001547fc261e2dff00a1a35bff00c0f97ff8aa3d9aee1f5b9ff2afbdff0091e138a315eedff09878b7fe868d6fff0003e5ff00e2a8ff0084c3c5bff4346b7ff81f2fff001547b35dc3eb73fe55f7bff23c271462bddbfe130f16ff00d0d1adff00e07cbffc551ff09878b7fe868d6fff0003e5ff00e2a8f66bb87d6e7fcabef7fe4784e28c57bb7fc261e2dffa1a35bffc0f97ff008aa3fe130f16ff00d0d1adff00e07cbffc551ecd770fadcff957deff00c8f09c518af76ff84c3c5bff004346b7ff0081f2ff00f1547fc261e2dffa1a35bffc0f97ff008aa3d9aee1f5b9ff002afbdff91e138a315eedff0009878b7fe868d6ff00f03e5ffe2a8ff84c3c5bff004346b7ff0081f2ff00f1547b35dc3eb73fe55f7bff0023c271462bddbfe130f16ffd0d1adffe07cbff00c551ff0009878b7fe868d6ff00f03e5ffe2a8f66bb87d6e7fcabef7fe4784e28c57bb7fc261e2dff00a1a35bff00c0f97ff8aa3fe130f16ffd0d1adffe07cbff00c551ecd770fadcff00957deffc8f09c518af76ff0084c3c5bff4346b7ff81f2fff001547fc261e2dff00a1a35bff00c0f97ff8aa3d9aee1f5b9ff2afbdff0091e138a315eedff09878b7fe868d6fff0003e5ff00e2a8ff0084c3c5bff4346b7ff81f2fff001547b35dc3eb73fe55f7bff23c271462bddbfe130f16ff00d0d1adff00e07cbffc551ff09878b7fe868d6fff0003e5ff00e2a8f66bb87d6e7fcabef7fe4784e28c57bb7fc261e2dffa1a35bffc0f97ff008aa3fe130f16ff00d0d1adff00e07cbffc551ecd770fadcff957deff00c8f09c518af76ff84c3c5bff004346b7ff0081f2ff00f1547fc261e2dffa1a35bffc0f97ff008aa3d9aee1f5b9ff002afbdff91e138a315eedff0009878b7fe868d6ff00f03e5ffe2a8ff84c3c5bff004346b7ff0081f2ff00f1547b35dc3eb73fe55f7bff0023c271462bddbfe130f16ffd0d1adffe07cbff00c551ff0009878b7fe868d6ff00f03e5ffe2a8f66bb87d6e7fcabef7fe4784e28c57bb7fc261e2dff00a1a35bff00c0f97ff8aa3fe130f16ffd0d1adffe07cbff00c551ecd770fadcff00957deffc8f09c518af76ff0084c3c5bff4346b7ff81f2fff001547fc261e2dff00a1a35bff00c0f97ff8aa3d9aee1f5b9ff2afbdff0091e138a315eedff09878b7fe868d6fff0003e5ff00e2a8ff0084c3c5bff4346b7ff81f2fff001547b35dc3eb73fe55f7bff23c271462bddbfe130f16ff00d0d1adff00e07cbffc551ff09878b7fe868d6fff0003e5ff00e2a8f66bb87d6e7fcabef7fe4784e28c57bb7fc261e2dffa1a35bffc0f97ff008aa3fe130f16ff00d0d1adff00e07cbffc551ecd770fadcff957deff00c8f09c518af76ff84c3c5bff004346b7ff0081f2ff00f1547fc261e2dffa1a35bffc0f97ff008aa3d9aee1f5b9ff002afbdff91e138a315eedff0009878b7fe868d6ff00f03e5ffe2a8ff84c3c5bff004346b7ff0081f2ff00f1547b35dc3eb73fe55f7bff0023c271462bddbfe130f16ffd0d1adffe07cbff00c551ff0009878b7fe868d6ff00f03e5ffe2a8f66bb87d6e7fcabef7fe4784e28c57bb7fc261e2dff00a1a35bff00c0f97ff8aa3fe130f16ffd0d1adffe07cbff00c551ecd770fadcff00957deffc8f09c518af76ff0084c3c5bff4346b7ff81f2fff001547fc261e2dff00a1a35bff00c0f97ff8aa3d9aee1f5b9ff2afbdff0091e138a315eedff09878b7fe868d6fff0003e5ff00e2a8ff0084c3c5bff4346b7ff81f2fff001547b35dc3eb73fe55f7bff23c271462bddbfe130f16ff00d0d1adff00e07cbffc551ff09878b7fe868d6fff0003e5ff00e2a8f66bb87d6e7fcabef7fe4784e28c57bb7fc261e2dffa1a35bffc0f97ff008aa3fe130f16ff00d0d1adff00e07cbffc551ecd770fadcff957deff00c8f09c518af76ff84c3c5bff004346b7ff0081f2ff00f1547fc261e2dffa1a35bffc0f97ff008aa3d9aee1f5b9ff002afbdff91e138a315eedff0009878b7fe868d6ff00f03e5ffe2abcc7e2cffc954f16ff00d872f7ff0047bd4ca2a2ae6b43112a93e56ada5ff1397c518a751599d761b8a314ea280b0b452e28c52189452e28c500257b9f8fff00e47cf107fd852e7ff46b5786e2bdcfc7e3fe2bbf107fd84ee7ff0046b56d4f667062ff00891f47f9a30e8a5c518abb9cc2514b8a3145c04a29714628b809452e28c5170128a5c518a2e02514b8a3145c04a29714628b809452e28c5170128a5c518a2e02514b8a3145c04a29714628b809452e28c5170128a5c518a2e02514b8a3145c04a29714628b809452e28c5170128a5c518a2e02514b8a3145c04a29714628b809452e28c5170128a5c518a2e02514b8a3145c04a29714628b809452e28c5170128a5c518a2e02514b8a3145c04a29714628b809452e28c5170128a5c518a2e02514b8a3145c04a29714628b809452e28c5170128a5c518a2e02514b8a3145c04a29714628b809452e28c5170128a5c518a2e02514b8a3145c04a29714628b809452e28c5170128a5c518a2e02514b8a3145c04a29714628b809452e28c5170128a5c518a2e02514b8a3145c04a29714628b809452e28c5170128a5c518a2e02514b8a3145c04a29714628b809452e28c5170128a5c518a2e02514b8a3145c04ae37e2cffc954f16ff00d86ef7ff0047bd7678ae37e2c8ff008ba7e2dffb0ddeff00e8f7a8a9f09be17f8afd3f54731452e28c5627a22514b8a31400ea29714628284a29714628012bdcbc7e3fe2bbf107fd84ee7ff46b578762bdcfc7c3fe2bbf107fd84ee7ff0046b56b4f6679f8cfe247d1fe68c2c518a7628c559cd61b8a314ec518a02c3714629d8a3140586e28c53b146280b0dc518a7628c50161b8a314ec518a02c3714629d8a3140586e28c53b146280b0dc518a7628c50161b8a314ec518a02c3714629d8a3140586e28c53b146280b0dc518a7628c50161b8a314ec518a02c3714629d8a3140586e28c53b146280b0dc518a7628c50161b8a314ec518a02c3714629d8a3140586e28c53b146280b0dc518a7628c50161b8a314ec518a02c3714629d8a3140586e28c53b146280b0dc518a7628c50161b8a314ec518a02c3714629d8a3140586e28c53b146280b0dc518a7628c50161b8a314ec518a02c3714629d8a3140586e28c53b146280b0dc518a7628c50161b8a314ec518a02c3714629d8a3140586e28c53b146280b0dc518a7628c50161b8a314ec518a02c3714629d8a3140586e28c53b146280b0dc518a7628c50161b8a314ec518a02c3714629d8a3140586e28c53b146280b0dc518a7628c50161b8a314ec518a02c3714629d8a3140586e28c53b146280b0dc518a7628c50161b8a314ec518a02c3714629d8a3140586e28c53b146280b0dc518a7628c50161b8a314ec518a02c3715c77c58ff92a7e2dff00b0dde7fe8f7aecf15c6fc581ff00174fc5bff61bbcff00d1ef533f84df0bfc57e9faa398a2971462b13d2128a5c518a005c518a7628c5031b8a314ec518a006e2bdcfc7dff0023d7883fec2773ff00a35abc3b15ee3e3e1ff15d6bff00f613b9ff00d1ad5ad3d99e7e33f891f47f9a3128a31462ace60a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a002b8df8ae3fe2e978b3fec3779ff00a3debb2c571ff15c7fc5d2f167fd86ef3ff47bd4cfe137c27f15fa7ea8e5f14629d8a31589e90dc518a7628c5002d14b8a3148a128a5c518a004af71f1f7fc8f5aff00fd84ee7ff46b5787e2bdc7c7a3fe2bad7ffec2773ffa35ab6a7b33cec67f123e8ff3461d14b8a31567309452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002571df15ffe4a8f8b3fec3779ff00a3debb2c571df1587fc5d1f167fd86ef3ff47bd454f84e8c27f15fa7ea8e668a5c518ac4f484a2971462801714629d8a314143714629d8a31400dc57b878f7fe47ad7ffec2773ffa35abc4715edfe3d1ff0015cebfff00613b9ffd1ad5b52d99e7633f891f47f9a3128a5c518ab3984a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a29714628012b8ef8ac3fe2e8f8b3fec3779ffa3debb2c571ff001547fc5d0f15ff00d86af3ff0047bd454f84df09fc67e9faa398c518a7628c5627a63714629d8a31400b452e28c503128a5c518a004af6ff001e0ff8ae75ff00fb09dcff00e8d6af11c57b7f8f3fe479d7ff00ec2773ff00a35ab5a7b33cec6ff123e8ff003462628c52d15673098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b4500262b8ff8abff002543c57ff61abcff00d1ef5d8d71df1547fc5d0f15ff00d86af3ff0047bd4cfe137c27f19fa7ea8e6a8a5c518ac4f4c4a2971462801d452e28c54942514b8a3140095edde3cff91e35effb095c7fe8d6af12c57b778f3fe478d7bfec2571ff00a35ab6a7b33cdc6ff123e8ff003462d14515a1cc145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450015c77c54ff00929fe2bffb0d5e7fe8f7aec6b8ff008a83fe2e7f8aff00ec3579ff00a3dea2a7c26f84fe33f4fd51cd514b8a31581ea09452e28c5002d14ec518a06368a7628c50036bdb7c79ff0023c6bdff00612b8ffd1ad5e298af6cf1d8ff008ae35eff00b095c7fe8d6ada96ccf3b1bfc48fa3fcd189452e28c5687289452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c50025721f153fe4a7f8affec3579ffa3debb0c5723f14c7fc5cef157fd86af3ff00473d454f84e8c27f19fa7ea8e668a7628c5607a6368a7628c5002d14b450509452d140095ed7e3bff91e35effb095c7fe8d6af15af6cf1d8ff008adf5eff00b095c7fe8d6ada9eccf371bfc48fa3fcd189453b1462aee72d86d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b453b14628b8586d14ec518a2e161b5c8fc53ff00929de2affb0d5e7fe8e7aec315c87c52ff00929de2affb0cde7fe8e7a8a9f09d183fe33f4fd51cd514b45627a82514b45002d14ec518a9286d14ec518a006d7b678ebfe477d7bfec2571ff00a35abc5715ed7e3aff0091db5dff00b095c7fe8d6ade9eccf331dfc48fa3fcd18b452d1567289452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d140095c7fc52ff929be2aff00b0cde7fe8e7aec6b90f8a43fe2e6f8abfec3379ffa39ea2a7c27460ff8cfd3f54735453b1462b03d51b453b146280168a5a28189452d140095ed5e3aff0091db5dff00b095c7fe8d6af16af6af1d0ff8adb5dffb095c7fe8c6ade96ccf331dfc48fa3fcd18b452e28c559cb6128a5c518a02c2514b8a3140584a297146280b09452e28c5016128a5c518a02c2514b8a3140584a297146280b09452e28c50163bbf83de0f8bc4faa4b773dd24716993dbc8f03c0245b852cc4a1c91804263a1eb5d478d7c55e16f0df89eef45ff008579a35d7d9b67ef76449bb722b74f28e3ef63af6ac4f817e26b0d0b54b9d3aee1b9925d567b6860312a9556dccb96c9181971d33deba2f887f0c35ef1178c2fb58b2bcd32382e3cbd8b348e1c6d8d54e40423aa9ef5d504fd9fb9b9e0e2251fae35897685b4d5aeddbe652f0bf8d3c19ab6bb6fa7def81b40d2ede5ddbeea630ec8f0a48ce63039200ebdeb90f89da2e8ba5ea82ef45d774fd462be9e693c8b4d9b6d57702a9f2b1e30c40e07ddfcb73fe14b78a7fe7ff46ffbfd27ff001bae4749d1d63f1eda683a8849957544b4b808c42b812846c1e0e0f3cf06a27cf6b491d387587551d4a152e92d55efdfbfe8697c3df025ff008aeed8486e74fb2f21a48ef1ad19e3918305d80e402793dff84d7adeb7a1f80bc49a64da2e8b75e19b5bfb9dbe54b691c0f2aed60c7685209e148383d09a87e26dd4fe04f01d843e1493fb3912f444a3025c2b2c8ec3f79bbab735e13a26a77da2ea70ea7a64fe45dc3bbcb9362b632a54f0c08e84f6ab6e34bdd6afdce7846b6609d78cf952f857a77fc3b963c53a0dff0087f549ed2eedae52259e48e09e581a35b8546c6f5cf50460f04f515edbf08bc3da05f7c3cd2eeaf743d32e6e1fcddf2cd688eed899c0c9232780054fe26d2ec7c49f0badf5ad6a0fb55fdb688d7514bbd936cad0062d85201e541c118e2ae7c15ff9265a4ffdb6ff00d1d255d3a4a353c9a3971b8d956c2f69465676f47fe478f78d3e1ceb5a16a915a69d6fa86b513c02433c162fb558b30d9c16e7001ebdebd2fe18fc39b0d2f4b377ad5bdb6a32df410c9e45dd8aeeb56da4b27cc4f39600f03eefe5a1f05f5ed5bc43e16b9bdd62efed57097af12bf96a98508840c2803ab1fce8f873af6adab78a7c5d65a85df9d6fa7de88ad53cb55f2d77ca3190013c2af5cf4a70a704d497527158ac54a9ce949a5c96bb5d75febb1cee85a268b27c73d7b4e9348d3deca2d3d1e3b76b6431a36d8390b8c03f31e7dcfad3fe236b7e16f086b70e99ff00080e8d7be6db2cfe67951478cb32e31e59feef5cf7abbe1eff009383f11ffd8353ff0041b7a8fe2bfc3ed67c59e22b7d474ebad3e28a3b458089e4756dc1ddb3c29e30c2869f23e55adc23387d620ab4ad1e45d5ad6de4ce7742f1c785b54d6ec34cff00856fa343f6bb98e0f331136cdec1738f286719e99a83e37782ad74666f11d94d0c36f75731c09630db08d22fdd1c9041c72509c6d1f7bf3b5e1bf847e23d37c45a6ea33dee92d15addc53b849642c555c31032839c0a9fe3ff0089ec2ee23e168e1b917b67771cf23b2af9654c4c700e739fde0edeb50d3f66f9d6bd0ea84a1f5b82c2caf1b7bdab7d7cce87e2ef87b40b1f879aa5d59687a65b5c2795b2586d111d733203820647048af9f2be96f8d5ff0024cb56ff00b63ffa3a3af9ab15189494f436c92529506e4efafe884af6ef80ba268ba9783eee7d4748d3ef255d41d03cf6c92305f2e338c9078c93c7bd78962bdf7f674ff9126f3fec24ff00fa2e2a5875799a672dc70ada76d51ccebbe38f0b697addfe99ff000adf469bec973241e66225dfb18ae71e51c671d3354bfe163785bfe89968df9c5ffc66b43c49f08fc47a9788b52d460bdd2562babb96740f2c8182b39600e10f3835ca78d3e1eeb3e14d2e2d4751bad3e58a49c42040eecdb8ab367951c614d549d55776d3d119508e06a72c54af276fb52dedea71f452e28c5731ed584a297146280b1f46693a3f86ecfe1a5a6b53f86b49bb960d192e9f7dac7ba5658431cb153c923af3d6bcf7fe163785bfe89968df9c5ffc66bd5749b29752f84f69a740c8b2dd6849021724286680282719e326bcabfe14b78a7fe7ff0046ff00bfd27ff1baeda8a765c8bf23e63073c3b954fac4f5bbb6ad77eccccf1478d340d5b42b8d3ecbc0da6697712edd975094df1e18138c460f2011d7bd741fb3de93a5ea9fdb9fda7a6d95ef95f67f2fed102c9b33e6671b81c6703f2ae13c69e17bff000a6a9169da8cd6d2cb240260606665da59971c81ce54d687c2ed7756d23c536365a7ddf936fa85edbc5749e5ab798bbf18c9048e19ba63ad61193551739ea56a1196124b0ef7d6f77f9efd04f8bb696b63f10f54b5b2b686dadd3cad914318445cc284e00e07249a3e115a5adf7c43d2ed6f6da1b9b77f377c53461d1b10b9190783c806bbff00da0f41d26df4a4d7e1b5dba95cdec714d3798c772889c636e768fb8bd076a3f67cd0749b8d29f5f9ad776a56d7b2450cde630daa62418db9da7efb751deafd9bf6d6f99cff005c8ff6773ebb72fcf6333f684d274bd2ff00b0ff00b334db2b2f37ed1e67d9e058f7e3cbc676819c64fe75cff85fc69a0693a15be9f7be06d3354b88b76fba98a6f932c48ce63278040ebdaaa7c51d7756d5fc537d65a85df9d6fa7dedc456a9e5aaf96bbf18c8009e157ae7a57278a89ced36e27561b0bcd86842abbf5ddf5d77bdfa9f4af8d6c3c2de1bf0c5deb5ff0008868d75f66d9fbafb2c49bb73aaf5d871f7b3d3b5787f8e7c47a5f883ec7fd99e19b2d0fc8dfe67d9cafef776dc676a2f4c1f5eb5ee1f1abfe4996adff6c7ff0047475f3562b4c4bb3e547164b4d54a6eac9b6d3eefb7ad8fa0be11787b40bef879a5dd5ee87a65cdc3f9bbe59ad11ddb1338192464f000ae1ffe163785bfe89968df9c5ffc66bd2fe0affc932d27fedb7fe8e92be6ac53a9270846c2c1d18e22bd6551b767a6afbbeccf69f13786340f19f85adf57f0d1d32c6eecec9ae67b1d3e0492477740cb136c208605594646724f1dab9df825a245278e6f74ed7b484768b4f6736f7b6c0946df1e0ed71c1c375f43ef5bbfb34ffccc1ff6edff00b56b67c3dff2707e23ff00b06a7fe836f4d454b967dccea559d155b0d7ba8aba7d7a69f8f7297c46d6fc2de10d6e1d33fe101d1af7cdb659fccf2a28f19665c63cb3fddeb9ef5ccffc2c6f0b7fd132d1bf38bff8cd75ff0015fe1f6b3e2cf115bea3a75d69f1451da2c044f23ab6e0eed9e14f18615c3eadf08fc47a6e9777a8cf7da4b456b03cce12590b15552c40ca0e702954f68a4ecb4f445611e0a54a3ed27ef3dfde96ff0079c878a351b5d5b5db8d42cb4c874bb7976ecb5871b23c2807180072413d3bd7d13e28f01681ab68571a7d969ba6697712edd9750d8a6f8f0c09c63079008ebdebe64c57d6be28d6ad7c3da15c6b17b1cd2416fb77ac20173b9828c0240eac3bd187b4949c8acdf9e94a8c69377e9f85bd7e67cbfe29d06ffc3faa4f69776d72912cf24704f2c0d1adc2a3637ae7a82307827a8af6df845e1ed02fbe1e697757ba1e997370fe6ef966b44776c4ce0649193c002a9fc5ed16ebc5fe16d2fc45a6c90c369696535ec89704ac851d11c001411bb0a7be33deb7fe0aff00c932d27fedb7fe8e92aa95351a8d7431c6e2e55b05195ed24ecedf3ff2b9e19e06f095ff008a75682da38ee60b2767492f96d9a48e26542d8278193c0c647de15e8fe2ad57c19e0cb1d334c8fc3da07886ee38cc1772288564478c2a92e36b90cc4b704e720f5ad4fd9d3fe449bcff00b093ff00e8b8abc6fc743fe2b6d77fec2571ff00a31ab3fe1d3525bb3ad378cc5ce94dda30e8bafaeccf4df05f8b3c0faeea92da6a3e0ff0ee8b12406413ce61dacc1946ce635e7049ebdab2747f055af877e2fe8ba15ecd0eaf6f716d24ee26b6010fc92800a92c0e0a039ff0af2fc57a2fc38d7756f10fc5ad1af758bbfb55c2472c4afe5aa6144529030a00eac6946a29349ad6e695b092a119ce9c9f2f2bbabb7adb4b5eecedfe265ef85bc19fd9ff00f14368d7df6cf33fe58c51ecd9b3fe999ce777e95c6ffc2c6f0b7fd132d1bf38bff8cd77ff0017bc17aa78bffb2ffb36e2ce1fb279de67da1d973bf6631b54ff0074d701ff000a5bc53ff3ff00a37fdfe93ff8dd695154e67cab4f4470e0a5837462eb4fdeebef4bbfa9c878d35ab0d77548aef4ed0adb458920119820dbb598331dfc2af382074ed5874b8a315c8ddddd9f430a6a11518ec84a297146291763d5ff0067bd274bd53fb73fb4f4db2bdf2becfe5fda20593667ccce37038ce07e5573c6be2af0b786fc4f77a2ff00c2bcd1aebecdb3f7bb224ddb915ba79471f7b1d7b52fecd3ff003307fdbb7fed5a9be21fc30d7bc45e30bed62caf34c8e0b8f2f62cd23871b6355390108eaa7bd76454bd92e55a9f395254bebf355e568dbbb5ad976641e0af15785bc49e27b4d17fe15e68d6bf69dffbdd913edda8cdd3ca19fbb8ebdeb33c5fe0ab5f0ffc44d024f3a1b9b4d5f56ddf64fb3048e24f393f778c90cb87c740303a56d7c3cf861af7877c6163ac5ede6992416fe66f586472e7746ca300a01d5877aade3af13d86bbf12bc33a75a437292e95ac7933995542b379d1afcb82723287ae3b50d7b9efad6ffe438cd7d61ac34af0e577d5be92eff2d8b9f1eb44d174df07da4fa7691a7d9cada8221782d92362be5c871900719038f6af11afa67e2bf862ff00c59e1db7d3b4e9ada2963bb59c99d995768475c700f39615e63ff0a5bc53ff003ffa37fdfe93ff008dd4d7a5273bc51a6578ea30c3a5567addeffd33b8ff00847b40ff008541f6efec3d33ed7fd81e6f9ff644f337fd9f3bb7633bb3ce7ae6bcafe1ef812ffc5776c24373a7d9790d24778d68cf1c8c182ec072013c9eff00c26bdc756b2974df84f77a74ec8d2dae84f039424a96580a92338e322aafc15ff9265a4ffdb6ff00d1d256b2a4a538a7d8e2a58da9468549c1ddb959796ece43c69e2cf03e85aa4569a7783fc3bad44f0090cf01876ab1661b388db9c007af7a7f89bc31a078cfc2d6fabf868e9963776764d733d8e9f024923bba06589b6104302aca32339278ed5e2d8af64fd9a7fe660ffb76ff00dab58c27ed25cad68cf431585fa9d155a9c9f347bb7af4d55dafb8f3df0c5fc5e14d7a73af78653506f23cb3677a810c6c4ab07c3a1c1c0f4e8d5e83e0af15785bc49e27b4d17fe15e68d6bf69dffbdd913edda8cdd3ca19fbb8ebdeb8ef8d43fe2e6eadff006c7ff44c747c151ff173749ffb6dff00a264a98c9c67c9d2e6d5e942b619d77752e5be8df6f53d2be265ef85bc19fd9fff0014368d7df6cf33fe58c51ecd9b3fe999ce777e95e63e34f1668baee9715a69de0fd3f4595271219e029b9942b0d9c46bc6483d7b576dfb4b7fccbfff006f3ffb4abc6f14ebcda938ad88caf0f09d085595f9b5eafbbe97b1ea9f0c3c091400789bc5652c2d6d2778e4b3d4ed02248a500572d21000dcfc70795fcacffc269e0cff0084a7fb23fe106d03ec9f6dfb37dbb30f97b37edf37fd5e36e3e6eb8c77ef5dc7c6aff9265ab7fdb1ff00d1d1d7cd58aaa8fd95a3132c1c3fb414ab556fb249b497dcd5f7ea7aa7c4ff000245383e26f0a14bfb5bb9d238ecf4cb40e91a84219c34648237273c0e5bf3f2aafa5be0affc932d27fedb7fe8e92be6ac54568a5692ea7465b5a7273a3277e476bf5feb4128a5c518ac0f56c2514b8a3140584a297146280b09452e28c5016128a5c518a02c2514b8a3140584a297146280b09452e28c5016128a5c518a02c25723f147fe4a6f8a7fec3379ff00a39ebafc5723f147fe4a678a7fec3377ff00a39ea2a7c27460ff008cfd3f54737452d1581ea89452d1400ec7b518f6a5a28284c7b518f6a5a280131ed5ed3e39ff0091db5dff00b095c7fe8c6af17af69f1cff00c8edaeff00d84ae3ff0046356f4b667998efe247d1fe68c5a2968ab3904a2968a004a2968a004a2968a004a2968a004a2968a004a2968a004a2968a004a2968a004a2bb0f865ad68da66a86d359d0ec3518afa7863f3eef66db55dc433fcca78c302791f77f2ebbc4fe0bf06eadae5c6a165e38d034c825dbb2d6110ec8f0a01c624039209e9deb48d3e68dd338eae3552a9c938b4bbeff926fef393f82bff00253349ff00b6dffa264af4af8b9ff23b780bfec247ff00464158fe0af0b785fc37e26b4d6bfe16168d75f67dff00badf1a6edc8cbd7cd38fbd9e9dab33c5de34b5d7fe21e831f930db5a691ab6dfb57da43c72a79c9fbcce0055c267a9183d6b78fb94ecfbff0091e655be2316aa534eca2efa35d25dd23d1fe2b5b786aebc3b6f1f8a750b9b1b2176ac9240a4b19363e070adc63776edd6bcc7fb1be0f7fd0d7acffdfa6ffe315d1fc78d6f46d4bc2169069dabd85e4ab7e8e520b949182f9720ce013c648e7debc4e9579a53d931e57859cb0e9b9ca3abd169f9a3e9dd592ce3f84f771e9d2bcd64ba13adbc8e30cf1880ed2781c918ec3e955be0affc933d27fedb7fe8e92b37fe120d07fe150fd87fb6f4dfb5ff0060795e47da93ccdff67c6ddb9ceecf18eb9a3e11f88341b1f87ba65adeeb7a6db4e9e6ef8a6ba4475ccce4641391c106b7525cebd0f2a74aa2c3cd59fc7d9f67e4795f82fe20eb5e14d2e5d3b4eb5b096292733133c6ecdb8aaae3861c614577bfb3fdecba96a9e2ad46754596ea78667080850ccd3310339e326bc52bd5ff0067dd574bd33fb6ff00b4b52b3b2f37ecfe5fda2758f7e3ccce37119c647e75cb466f9d26f43deccf0f0587a92847de76bdb77aa3a4f0ff00fc9c1788bfec1a9ffa0dbd71bfb457fc8ed67ff60d4ffd192d747a1eb7a347f1c75ed464d5ec12ca5b0548ee1ae5046edb60e036704f078f63e95ca7c78d42c352f17da4fa75f5b5e44b60885e0956450de64871904f38238f7ad2a35ecdfa9c7838496320dafb0bf23cf68a5a2b90fa13e94f8d5ff24cf56ffb63ff00a3a3af9aabe95f1adff85fc49e19bbd17fe12ed1ad7ed1b3f7bf6a8df6ed756e9bc67eee3af7af35ff008575e17ffa297a37e51fff001eaebc441ce5747cf65388861e8b8d44d3bf67d9791e695ef9fb3aff00c89379ff006127ff00d1715799f8d3c29a3685a5c577a778bec35995e711982009b954ab1dfc3b7190074ef5defc07d6f46d37c217706a3abd859cad7eee127b948d8af9718ce091c641e7daa282e5a9a9d19a4d57c1b74d37aae8ff002b5cf2bf1cff00c8edaeff00d84ae3ff0046356357ac6b9e08f0bea7addf6a5ff0b1b468bed77324fe5e636d9bd8b633e68ce33d7154bfe15d785ffe8a5e8df947ff00c7aa654a4dbff3474d2c7d18c229df65f665d9791e69452d1589e809452d1401f4a7fcd13ffb973ff6dabe6aafa334ad5fc3979f0d6d3469fc4ba4dacb3e8e96afbeea3dd1334214e54b0e413d38e95e7dff000aebc2ff00f452f46fca3ffe3d5d75a2e76b763e7f2daf1c3ba8aa26af2ecfcfc8f34ab3a4decba6ea969a8c0a8d2dace9320704a96560c01c638c8aec3c4fe0bd0749d0ee350b2f1c69ba9cf16dd96b084df2658038c484f0093d3b570f5cce2e2f53dba75615e2dc76db66bf3b1f4558d945f137e1de9b3ebccf6cd24ef31164420dc8d24607cfbb8c7eb45f5945f0cbe1dea53e82cf72d1ce93017a438dced1c647c9b78c7eb5e7ff0002fc41f61d7e6b5d575bfb369a964fe5457375b21573221f9431da1b963c73d7de8f8e9e20fb76bf0dae95adfda74d7b24f362b6badf0b3891cfcc14ed2dc29e79e9ed5d7ed23c9cfd763e7bea757eb5f56bfeeefcd6b3b77b7f4cf3dd5af65d4b54bbd46754596ea7799c202143331620673c64d56a5aee3c31e0bd0756d0edf50bdf1c69ba64f2eedf6b304df1e18819cc80f2003d3bd7228b93d0fa1a95614229cb6db66ff2b9eb9f1abfe499eadff6c7ff0047475f3557d2be35bff0bf893c3377a2ff00c25da35afda367ef7ed51beddaeadd378cfddc75ef5e21e38f0ee97a07d8ff00b37c4d67adf9fbfccfb385fdd6ddb8ced76eb93e9d2ba312aef991e36495153a6e94934dbecfb77b58f6ff0082bff24cf49ffb6dff00a3a4af9aabe83f847e20d06c7e1ee996b7bade9b6d3a79bbe29ae911d733391904e47041ae1ffe15d785ff00e8a5e8df947ffc7a9d48b9c236160ab470f88acea26aef4d1beafb2363f669ff009983feddbff6ad6cf87ffe4e0bc45ff60d4ffd06deb37c4be27d07c19e17b7d27c3634dbebbbcb26b69efb4f9d23911d102acadb0125896661939c83cf7ae77e0a6b7147e38bdd475ed5d11a5b0643717b720176df1e06e73c9c0e9e83da9a928f2c3b1954a352b2ad89b5949592ebd35fc3b0efda2bfe476b3ffb06a7fe8c96bcd6bdc3e22e89e17f17eb70ea5ff09ee8d65e55b2c1e5f991c99c333673e60fef74c76ae6bfe15d785ffe8a5e8df947ff00c7ab3ab4e529b6bf3476e0b194a961e109dee97f2cbcfc8f34afa57e357fc933d5bfed8ffe8e8ebe7ef13e9d6ba4eb971a7d96a70ea7045b765d438d92654138c1238248ebdabdbfe2e788341bef87ba9dad96b7a6dccefe56c8a1ba4776c4c84e00393c0269d2d2334c8cc2f52b61e515a5fb3eebeef99cff00c39f1bda6b3a24be10f114d0d9a4b6d169b63f67864f3250ca63396f99437dce480327f2f51f0be8b6be1ed0adf47b2926920b7ddb1a620b9dcc58e48007563dabe59d0efbfb335bb1d4bcaf37ec9731cfe5eedbbf6306c670719c75c57d1be18f1e683ab6876fa85eea3a6e993cbbb7dacd7c9be3c310339c1e4007a77ad30f513f8b738736c1ca9bbd24f95bbb4bbfe7b5cc0fd9d7fe449bcffb093ffe8b8abc73c73ff23b6bbff612b8ff00d18d56bc0de2cbff000bead05cc725ccf648cef2592dcb471cacc8572472323839c1fba2bd1fc55a5f837c6565a66a51f88740f0f5dc9199eea3530b48ef205621cee4259486e48ce49e959ff129a8add1dd6783c5ceacd5e33eab5b7aad59e2b5e89f0e341d5bc3df16b46b2d62d3ecd3bc72caa9e62be54c528072a48eaa6ba3f05f853c11a16a92ddea3e2ff0eeb313c063104e21daac594efe5db9c023a77ac9d23c696be21f8bba36b97b0c3a4c16f6d240e66b90507c92904b10a064b818ff001a51a6a2d36f5b9a56c54abc670a717cbcaeeecd6b6d2c9d9fe0cd2fda5bfe65ff00fb79ff00da55e395ef9f12acbc2fe33fecff00f8ae346b1fb1f99ff2da3937efdbff004d0631b7f5ae3bfe15d785ff00e8a5e8df947ffc7a9d6a6e536d7e68cf2ec5d3a3868c2774d5feccbbfa1e69456ef8d345b0d0b548ad34ed72db5989e01219e0dbb558b30d9c3373800f5ef5875ced59d99ec426a71528ecc4a2968a451ec5fb34ff00ccc1ff006edffb56b8ef8d5ff253356ffb63ff00a263ae9bf67dd574bd33fb6ffb4b52b3b2f37ecfe5fda2758f7e3ccce37119c647e75c9fc5cbbb5bef885a9dd595cc37303f95b2585c3a36214070470790457449af6291e3508c96655256d2dfe47275b3e06ff91db42ffb095bff00e8c5ac7ad6f06cb141e2fd1a79e548a28efe0777760aaaa24524927a003bd611dd1ead6bfb397a3fc99ecbfb457fc89367ff006124ff00d172d781d7b6fc78d6f46d4bc2169069dabd85e4ab7e8e520b949182f9720ce013c648e7debc4eb6c43bccf3f268ca38549ab6acfa53fe689ffdcb9ffb6d47c15ff9267a4ffdb6ff00d1d2566ffc241a0ffc2a1fb0ff006de9bf6bfec0f2bc8fb52799bfecf8dbb739dd9e31d735e59f0fbc757fe15bb6320b9d42cbc868e3b36bb648e362c1b7818201e0f6fe235bba918ca2df63c9a782ab5e8558c56bcd7d7aeffe671f5ec7fb34ff00ccc1ff006edffb56a4f1a7853c11aeea915de9de2ff0ee8d124023304021dacc198efe1d79c103a76a7f897c4fa0f833c2f6fa4f86c69b7d777964d6d3df69f3a47223a205595b6024b12ccc32739079ef59421ece5ccde88efc5627eb94551a717cd2ee9ab75d5d92fb8e1fe357fc94cd5bfed8ff00e898e8f82bff00253349ff00b6dffa264ae56f6eeeafae5eeaf6e66b99df1be599cbbb6060649e4f000ae93e11dddad8fc42d32eaf6e61b6813cddf2cce111730b8192781c902b28caf52fe67755a4e9e0dd3dda8dbf03b7fda5bfe65fff00b79ffda55e395eb1fb416aba5ea7fd89fd9ba959def95f68f33ecf3ac9b33e5e33b49c6707f2af28aaaeef51919545c70904d77fcd9f4a7c6aff009267ab7fdb1ffd1d1d7cd55ea9f0c7c7514e0786bc5612fed6ee77924bcd4eec3a46a101542b20208dc9c72396fced7fc217e0dff84a3fb5bfe138d03ec9f6dfb4fd8710f97b37eef2bfd6636e3e5e98c76ed5a545ed6d289c58297f67a951aa9f74d26d3fb93b6dd4edfe0aff00c933d27fedb7fe8e92be6aaf54f89de3a8a007c35e1409616b693a491de6997611245284b2058c0006e7e793cafe5e59515a49da2ba1d396d09c5ceb495b9ddedd7aff00989452d1581ea09452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d140095c97c501ff1733c53c7fcc66eff00f473d75d5c97c50ff9297e29ff00b0cddffe8e7a8a9f09d383fe33f4fd51cde3da8c7b52d1581ea898f6a31ed4b45002d14ec518a431b453b1462801b5ed1e39ff0091db5dff00b095c7fe8c6af19c57b3f8e3fe475d77fec2371ffa31ab7a5b33ccc77f123e8ff3463514b45687209452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d140095c8fc50ff9297e29ff00b0cddffe8e7aebeb92f89e3fe2e5f8a3fec3377ffa39ea2afc274e0bf8cfd3f54737453b1462b9cf546d14ec518a005c518a5a28284c518a5a2801315ecfe381ff0015aebbff00611b8ffd18d5e335ecfe38ff0091d75cff00b08dc7fe8c6ada97c2cf2f1dfc58fa3fcd18d8a314b456872098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b4500262b92f89e3fe2e578a3fec3177ff00a39ebaeae4be27ff00c94af147fd862eff00f473d454f84e9c17f19fa7ea8e731462968ac0f584c518a5a2801714629d8a3140c6e28c53b1462801b8af66f1c0ff008ad75cff00b08dc7fe8c6af1bc57b2f8e3fe474d73fec2371ffa31ab6a5f0b3cbc77f163e8ff003463628c52d15672898a315af17867c492c6b2c5e1fd59d1c06565b3908607a1071d2ab6a5a46aba6aa36a3a65ed9ac84843710346188eb8dc066ab95f6335520dd9495fd57f99471462ac58d9dddfdc0b6b1b59eea760488e18cbb103af039ad1ff008457c4ff00f42e6b1ff80527ff00134926f643954845da4d2f9aff00331b1462a5b9827b69dedee619219a33b5e3914ab29f420f20d69af85fc4cca197c3babb2919045948411f950936129c23ab697dc63e28c56a5df87b5fb4b77b9bbd0f53b781065e496d1d5547b92302aa5858deea13fd9ec2cee2ee6c16f2e08cbb6077c019c51660a706ae9ab7c8ad8a315b3ff08af89ffe85cd63ff0000a4ff00e26a8ea3a76a1a6c8b16a3617566ee372acf0b4648f50081c53716b7428d4849d9493f9aff0032a628c52d152682628c55ad3b4fbfd4673069f6573792aaef29044d2305c819c01d3247e751dddb5c5a5c3db5dc12dbce870f1ca85594fb83c8a7627995ed7d4871462968a45098a314b4f8229679e38208de5964608888a4b331380001d4934011e28c55cd4b4cd4b4d645d474fbbb36901282e2168cb01d71b80cd54a76b09352574c4c518a5a290c4c518a5a280131462afea3a3eafa6c6b2ea3a55f59a39daad3dbbc609f40481cd51a6d5b7263252574ee2628c52d148a131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a2801314629695559982a82cc4e0003926801b8a315ab73e1cf10db40f7173a16a90c318dcf2496922aa8f5248c015974da6b726328cbe177131462968a45098a314b45002628c52d5bd374cd4b52675d3b4fbbbc68c02e2de1690a83d33b41c53b5c4da8abb653c518a9278a58279209e378a58d8a3a3a90cac0e0820f420d3290c4c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518abf3e8fabc160b7f3e957d159b8565b87b7758c86e843118c1ed5469b5625494b677131462968a45098a314b4500262b93f89c3fe2e578a3fec3177ffa39ebadae4fe270ff008b93e28ffb0c5dff00e8e7a9a9f09d182fe3bf4fd51ce628c53b1462b03d61b8a314ec518a005c518a5a29142628c52d140098af65f1b8ff008ad35cff00b08dc7fe8c6af1baf65f1bff00c8e9ae7fd846e3ff0046356f4be167958ffe2c7d1fe68c6c577ff063c2b65e21d52e6eefd83c5a7b464dbb20659b787e0fa6368ae06bd7ff0066ff00f98f7fdbbffed5ae9a093a8933c5cd2a4a9e167283b3ff0082735e35f1deb926b925b6977336956d640daac36f29dadb19806f638c0fc2a787c6563acf826f34af132a4da85b40e6c6ea50d249248cc4f6185c0c0c93dab8ef12ff00c8c5a9ff00d7dcbffa19aa70dbcf324924304b22443748ca84841ea7d293a92e6638e0a83a71d2d6b3badfeff3fd4f61d0f4fb2f01f8021f16c56d16a37d711c32a991761884aab950c33c726b80d3fc77e29b6bfb7b99759bdb98e2955de1798ed90020953ec7a57a4f8e7fe484e9bff5e965ff00a0a578856959b834a3d8e4cba9c3111a93aaaedc9ad7b2fcbe475df12758d135d7d3b51d320861bc96267d412346189491d4903777e45757f0375ed6b53f14dc5a6a1a9dcdcdbc760cc91c8e4aa90f18040fa135e5535bcf0a4724d04b1a4a3746cc84071ea3d6bd0ff67aff0091d2f3fec1cfff00a323a54a4dd54cd31d429c30338ad6c9dafaf5fe9147e2bebdad7fc259ac693fda773f60f302fd9f79d98daa718fad75bf02ec6d17c1fa96aa2de317d1dc4d1a5c63e755f290e33e99e6b80f8adff250b58ffaec3ff405acff000bf88b52f0e5f1bcd39d3714285250590838c9c67af142a9cb55b7e629e11d6c0c614f46d27ebebea58ff84d3c5bff00430ea1ff007f8d5ef1478d3fe120d0adec6f748b76bd8228e317ed21694edc64f4fe23927eb56bfe1676bfff003e7a3ffe018ff1aeabe2769565a8fc3ad3bc572c423d40da5b922201633e660b7cbf563424e517cb2b8a72853ab4fda5251bbb269f5f92478ee28c52d15ce7ae7a3fecf23fe2b4bcff00b073ff00e8c8eb9ff8ae3fe2e16b1ff5d87fe80b5d07ecf5ff0023a5e7fd839fff0046475cff00c56ff9285ac7fd761ffa02d743fe0af53c9a7ff2339ff851cbe2ac69b6373a8dfc363671f9b713b848d3701b98f4193c0a82bbdf85169e2546bdbed1bfb32da211ab3dc6a48c23c027946008c839cd6508f34ac77e26afb1a6e7a7cca1ff000acbc6ff00f404ff00c9a87ff8baada2691a8689f11746d3f53b7fb3dca6a16ccc9bd5b00c8a472a48ae8750f076bb7d7f717b2f8c3c38b25c4ad2b84d45c282c49381b781cd77df14238c6ade129022873ae400b6392323bd6ea92b5f6b1e54f309f32a6da97327b26ada3efb9cefc73d32fb59f12683a6e9b079f7534337971ef55ce30c796207406bcebc43e0ef11f87ec92f357d3becd03c82257f3a37cb1048185627a035e99f1f75abcb186cf4cb7112c5771b33c9b7f78bb5d48daddbdeb2fe09ea975abcb7be17d4364d606d259f2c332062c89c31f6634ea4632aae3d48c1d6ad43051aa92e55baeb6befff0002c794e28c56df8e74cb6d1bc59a869967bfc8b7902a6f396c6d0793f8d62d72b56763dda735522a4b67a898aec7e1b2f83a1b96bef14dfaa98d8aa5a496af22480afde2541c60ff002ae3e8a71972bb915a97b58385dabf6dcf6efda27fe45cd37febecff00e806bc4315ee1fb44ffc8bba6ffd7d9ffd00d78856d89fe21e7649fee6bd5fe62628c52d15ce7ac2628c5747e1897c16960e3c476faccb77e69d86cca6cd9818cee20e73bbf4ad5fb47c2cff009f1f147fdf517ff1556a175ba39a78871935c927e8bfe09c3e28c5751e2197c08fa63ae856bae477db86c6ba31f978cf39c127a57314a4ac6b4ea73abd9af5fe9898ad2f0f685aaf882f5ecf48b5fb4ce9199593cc54c28201396207522b3abd1ff67aff0091d2f3fec1cfff00a323a74e2a524999632b4a8509d48ee91c0eada75e695a8cda7dfc5e4dcc276c89b8360e33d4123bd69685e12f106b7a74ba8e97a7fda2d61728efe746b860031186607a115e996be11d27c55f10bc59fda9f68ff459a0f2fca936fde46ce783fdd15a5f096de3b4f08f886d62cf970ea7731ae4e4e0468056d1a1796bb6bf81e756cd7968de0bde5cb7edef1e1ba6d8dcea37f0d8d9c7e6dc4ee1234dc06e63d064f02ba7ff008565e37ffa027fe4d43ffc5d5ff85169e2546bdbed1bfb32da211ab3dc6a48c23c027946008c839cd59d43c1daedf5fdc5ecbe30f0e2c9712b4ae135170a0b124e06de073531a69c6f667456c64a355c14a292ef76eff2d8e32fbc3dabd8ebd1e877569e5ea12ba2243e629cb3e368dc0e39c8ef5eb1f0b7e1f9b5b5be1e2cd06d9a4674f23cd64938c1ce36938ed4efda1555343d367450b2fdafef8186e10e39a67ecf334d369fab99a5924c4b1e373138e1ab58538c2af2ee79f89c5d6af80f6c9dbbef7dfa3be879b78b3c1fafe808d7da8e9c2dad24b831c4c268db24e48185624700d73b8abbabdc5c4b7d7092cf2ba899880ce481c9aeff00e03683a7eaba9deea178aed2e9ed1342a08da778901dc31cf415cea3cf3b44f5aa57786c3ba9575b76fc3b9cf5b7c38f19dcdb45710e8fbe29503a37da611952320f2d581ace977da3ea52e9ba941e45d438f323de1b1950c395247422bd43c5da1788758f107db5fc51e19b716ffba8625bd68f6a2bb150ca17af3835a7e39b5d3756f02bdcebbaa69536b76714b229b0b85d8ed9f9460f27e50bc7ae6b574559dba1c54f329a9439ecd4bb2775dbd5773cb2fbc25e20b1d0a3d72eb4ff002f4f9511d26f3a33957c6d3b43679c8ed5a7ff000acbc6ff00f404ff00c9a87ff8baf41fd9ff00fd23c33a84771fbe45ba002bfcc00d838e6bcbf51f176b57de228b5d9a755b989e37544c88f298c6573d38e693842315277d4d2189c4d5ad3a51b7bbd75f9697fbd98fa958dce9d7f358de47e55c40e5244dc0ed61d464706abe2b6fc59e26d43c4b710cfa8c76aaf10201862d99cf5cf3cf4ac5ac656be87a549cdc173ab31314a8591d5d0e194e411d8d1454967bb58deddea3f01ae2f2fae24b8b892d2e37c921cb362470327e800af08c57b8681ff26f727fd7a5cffe8d7af10ae8afb47d0f232a494ab25fcccd3f0ef87b57f10dcc96fa3da7da658937baf98a985ce33f3115a1acf817c55a3e9b2ea5a9697e45ac38f324fb444d8cb051c2b13d48af61d77c3efa1784468de1bbed36c127f322966d4a5dacc8e0e42b01d79e2b13e1ee9da9f87af5a1d47c4be1abbd3276dd709f6c324870a76eddc00fbc466abd824eccc7fb56538ba90b596c9deed75d765e478ae2ba5d27c05e2cd574e8750b0d2bceb6986e8dfed112e4671d0b03dab4bc59a1db5b7c4082cfc2c7ed8a7cb98306f3915cbf258a8e141c66ba6f17687e2cd61ededb52f127856cdacf7058e1ba7848dd8ce46df61511a5bdfa1d75b1da41c1a5cdaeb7bfdcb5382d7fc17e25d0ac3eddaae9bf67b7de137f9f1bfcc7a0c2b13dabb8fd9cbfe423ac7fd718bf9b575bf0f7459b4bf04ea3697f7f63aa334d2ca24b798cc8a3cb500648183c138f715c97ece7ff00211d63feb8c5fcdab5853e4a917dce0c4631e230b5a2ecf96daaebaae8ce375bd2350d6fe22eb3a7e996ff0068b97d42e59537aae409189e588158fae691a8689a8be9fa9dbfd9ee500664dead8046472a48af77f10c71f86744d6355f0aa2de6a53df79930c79c51ddbe7185e4753c57cfb2c924afbe576763dd8e4d65560a1ea77e5f899e215d691492f3bd97dc86628c52d1581e91afe1bf0c6b9e23fb47f63597dabecfb7cdfdea26ddd9c7de233f74f4f4ab5a3f823c4fabc771269da679eb6f3b5bca7cf8d76c8b8c8e5867a8e4715df7ecdff00f31eff00b77ffdab5c3f8dbc45a95ef8aae2667484dacad0a2c00a290b23105803c9e7935bf2454149f53cc589af5313528c2d68db57e7fd7c8c3d674bbed1f52974dd4a0f22ea1c7991ef0d8ca861ca923a114ed0f48d435bd4534fd32dfed172e0b2a6f55c80327962057aeeb16f0f8c3e12a789756451a8410cf2ab40362965765191ce461477ad3f0c68dfd83f0f2d6fb41b8b1b5bfbd860b879f517fdd2b322ee0081903d0552a179796e632cd79696abdfbf2f95fbfa1e557df0efc6165653de5d691e5c1046d2caff006988ed55192701b2781dab96c57aef82744d6fc3dabadc3f8a7c33716b2958ee91ef99c98b702c141006719eb587f17b48d297c436f2f87dfed935ff00992cd1c0e25c30238017a719a99d2b47991b61f1ce55bd94da77d9a4d2f3bdff00cce7b40f05f8975db0fb7695a6fda2df794dfe7c69f30ea30cc0f7a9756f0178b34ad3a6d42ff4af26da11ba47fb444d819c740c4f7aef3c43a3f8c350d22db4ad435df0b69f1a149d51677864fba40cfcbe84fe22b77e11f87eeb484d552ff56d375559fc9c0b6b93304dbbf3bb20633918fa1ab8d04ddacce6ab99ce107539a2f5d95f6bf7d11e3ba1784bc41ade9d2ea3a5e9ff0068b585ca3bf9d1ae1800c461981e84558d1bc0be2ad634d8b52d374bf3ed66cf9727da225ce18a9e1981ea0d75bf0a1987c57d4e00c445fe927603f2fdf1daa97c68d7b503e299b428d920b3b165684420a1f9e34639c1e7926a7d9c143999bbc5622588f630b6c9dddf45f7eaefe871fe22f0f6afe1eb98edf58b4fb34b2a6f45f315f2b9c67e526b2f15d26ade33d6354f0f43a25dada35bc31c71ab88bf7984c63e6cfb735ce5632e5bfba7a145d571fdea57f21315a5e1ed0b55f105ebd9e916bf699d2332b2798a985040272c40ea456757a3fecf5ff23a5e7fd839ff00f46474e9c54a49333c656950a13a91dd2399b1f04789efb51bdd3ed74cf32e6c4aadca79f18d85812bc96c1ce0f4cd67f88742d57c3f7a967abdafd9a778c4aa9e62be549201ca923a835d57c61d76fee3c5f73a7ee4862b290ac6610519b72a93b883cf4e2bb2f08a278f3e1c5c0d7954b594e6289e11b5b6c71295c939cf2c735aaa7193715b9c52c6d6a54e15ea25caed7b6eafb7afa1e33a6d8dcea37f0d8d9c7e6dc4ee1234dc06e63d064f02ba7ff8565e37ff00a027fe4d43ff00c5d76df0ff004fb6d0be175cf8b6cd376a525b4ae4cb86406391c2e076e07ad79d69de2ed6ac7c452ebb0cead732bc8ec8f931e5f39c2e7a73c52e48c52e6ea68b135ebce6a85ad1d35eaff4462d8dacf7b7b059daa7993cf22c512640dccc70064f0393deb43c47e1bd6bc3b2431eb167f66698168c79a8fb80ebf749f5af5df88ba069d67e30f0d6b56e8c97373abdb44ea0809b430c6063af152fc48d22d35df885e19d2afbccfb3cf0dc6ff2db0df2a16183f502a9e1ec9f7d0e78e6ca52834bdd6a4df7d3b1e33e1ed0b55f105ebd9e916bf699d2332b2798a985040272c40ea4557d5b4ebcd2b519b4fbf8bc9b984ed913706c1c67a82477af68f0668161e1cf8b777a769de6f91fd8de67ef1b71c99533ce3da97c39a0e9faafc55f12ea178aed2e9f3c0d0a82369de8c0ee18e7a0a150ba5def61cb36519c9dbdc51525df5d0f37b6f871e33b9b68ae21d1f7c52a0746fb4c232a4641e5ab0359d2efb47d4a5d375283c8ba871e647bc3632a1872a48e8457a878bb42f10eb1e20fb6bf8a3c336e2dff00750c4b7ad1ed45762a1942f5e706b4fc736ba6eade057b9d7754d2a6d6ece2964536170bb1db3f28c1e4fca178f5cd0e8ab3b740a7994d4a1cf66a5d93baedeabb9e597de12f1058e851eb975a7f97a7ca88e9379d19cabe369da1b3ce476ad3ff008565e37ffa027fe4d43ffc5d7a0fecff00fe91e19d423b8fdf22dd0015fe6006c1c735e5fa8f8bb5abef1145aecd3aadcc4f1baa2644794c632b9e9c7349c2118a93bea690c4e26ad69d28dbddebafcb4bfdecc7d4ac6e74ebf9ac6f23f2ae20729226e076b0ea3238355f15b7e2cf136a1e25b8867d463b55788100c316cce7ae79e7a562d632b5f43d2a4e6e0b9d59898ad0d0345d4f5dbffb0e956df68b8d85f66f54f94753962077aa15eadf0535fd26df4abcd06e48b7bd99e5992e65dab185288a177139ce41e31554e2a52b36618dad3a145ce11bbfeb5f91cbff00c2b2f1bffd013ff26a1ffe2eb13c45e1ed5fc3d731dbeb169f669654de8be62be5738cfca4d759ff00082eb3ff00438f87ff00f066ff00fc4d4bf10e5f1669fe1eb2d1b55fecdbcb0fb3c5e5ddda46cf855e1732103938cfbe6add34937668e6a78b9caa462a7195fc9a7f2befe86cfc2ff8797d6baadd3f8af4281ad8c188bcd923906fdc3b2b1c719ae5fe21f82759d3350d57568b4a4b7d1a39f31ba4b1e151982ae1436eea476adffd9f2e2e26f10ea2b2cf2c805a0203393fc62b8ff89171707c6bad426794c7f6b71b0b9c75f4aa9727b24ec6145e23ebf38b92d12e8edf257d1f9999e1ed0b55f105ebd9e916bf699d2332b2798a985040272c40ea456858f823c4f7da8dee9f6ba67997362556e53cf8c6c2c095e4b60e707a66a8785e4923f11e9c6376426ea20769c646f1c57aff00c77d6af348d3ad2cac4451a6a625170e17e73b0c657047fbc6a6108b8393e874627135a9e223469dbdedafd2dbfae8796ebfe0bf12e8561f6ed574dfb3dbef09bfcf8dfe63d061589ed58fa6d8dcea37f0d8d9c7e6dc4ee1234dc06e63d064f02b7b43f1beb7a4691369709b69e09a4691bed11991812a17824fa0ae6d199183a31561c820e08ace5cba58eaa5eded2552d7e8d7f97fc13d4fc6d3f8bedfe1c43a3eade1a8acecada38213742f11c9d9803e504f5c579858dacf7b7b059daa7993cf22c512640dccc70064f0393debdafc76ccdf02f4e662598da591249e49da95e4fe08ff0091d343ff00b08dbffe8c5ad6b47df4bd0e1cbaa7fb3d4924934e5b5f757eed966ebc11e27b6d5ed3499f4cd97b781da08fcf8cef0a32dc86c0c01dc8a8fc43e0ef11f87ec92f357d3becd03c82257f3a37cb1048185627a035e99f1f75abcb186cf4cb7112c5771b33c9b7f78bb5d48daddbdeb2fe09ea975abcb7be17d4364d606d259f2c332062c89c31f6634dd2873f2114f1d88786589695baaeb6beebfc8f29c518adbf1ce996da378b350d32cf7f916f2054de72d8da0f27f1ac5ae76acec7af4e6aa45496cf51315ca7c4e1ff001727c4ff00f618bbff00d1cf5d65727f137fe4a4f89ffec3177ffa39eb3abf09d782fe3bf4fd51cee28c52d1581eb898a314b45002e3da8c7b53a8a431b8f6a31ed4ea2801b8f6af65f1b8ff008acf5cff00b08dc7fe8c6af1caf64f1b7fc8e7ae7fd846e3ff0046356f4be167978ffe2c7d1fe68c6c7b5741e08f145f785b531736dbe4b77606e2dc30513000e013838c6e3583456a9b8bba382a528d58b84d5d33d27c47a6780f5ebc8b53b6f135b68ad2c21a7b616cf37ef492cc4b6473ce3a76a4d4754f0b786bc172e8fa1cb0eb17ba84524175789ba2206495254e41c67180474af37a2b4f6bbb4b538d60348c6536e2ba69f2d777f79e8be03f18d8dc69ebe19f17a2dc69981e5cd2c842c2a8a3626d55c9e475cd56d3fc33e0782fede7b9f1d5b5cc31caaf2426c1d448a082573bb8c8e335c1d147b4d15d5ec53c0a5293a7371bee95adebaedf23acf897af69fac5edad8e9368b058698af05bbac858489918201008e9d39aeabe1c2f843c29aabea8fe3282e5a6b530987ec8e9b49656ce72738db8e95e53450aab52e6b6a153031951f62a4d47aedafde77bf112cfc31a95f6a7afd978b619ae2521d2cd6d5b2c70063793f8f4aa7f0eaebc24526d3bc4ba7400bef912fe491fe4e1404d8a39e72739ef5c7514bda7bdcd629613f73ec9cdf93d9ab7a5bf13b5ff00844fc1dff450adff00f05cff00fc5d6bfc44f166967c1d63e12d2a64d4a38eda147bc5253063c0c6c23bedcf5e335e67453f6964d256b92f05cf38caa4dcb9755b6ff2486e3da8c7b53a8ac8ed3b6f82dabe9ba278a6e6ef54bb4b585ec9e35760482c5d0e381e80fe55a7e2bd2bc1daef886f356ff84eadedfed2e1bcbfb0bb6de00ebb867a7a579b515aaa968f2b4714f05cd59d68cda6d5ba7ea8e8fc47a0f87b4ed3bed1a678b22d567de17c85b468ce0e727258f4aeb3c0fe2ad22e3c0573e10d5ae134a5581e38eed89937991d98fc800c633ebcd798514a35395dd22aae0d55a6a1524db4ee9e974ff23b5feccf07683ff132fedbb7f136cf97fb3fc87b7df9e33bf2718ebd2bb3f881e2df0e6a37de1b92cb548a65b5d5a19e7215be4407963915e2f4552aad2692329e5d1a938ce736dabf6eaade9fd6a7b0fc46d7bc1de25b9b3d33ed56ff00bd46ff0089afce7ec7821b1e5e06edd8dbd46339acdb1d57c2de06d0259743be875bd6672d01b845681a38d867383b81c32af1df35e61450eb36f9ada930cb211a6a9733e55d3bff005d9684da95e5d6a37d2dedeccd35c4a73248c39638c76aaf8f6a751591e8a8a4ac86e3daba0f0c68ba16a56b2cbaaf89a3d22447da91b5ab4bbc63ae4118f4ac1a284ecf544d483946d195bcf4fd4f6df889a9f83bc5da75b59ffc2596f65e44de66efb3bc9bb82318e3d6bc6754b7b7b5d467b7b4bb179046e5639c215120f5c76a828aba9539ddda39b07835858f2464daecec371ed463da9d4566760dc7b518f6a751400dc7b518f6a751400dc7b5751f0cbc491f85fc49f6d9adbce8e78becce4c9b3cb0ce84bf439c6de95ccd1551938bba33ab4a3560e12d99edbaefc42f0c6817cf7da05943aa5c6a2737b2473b44414002672a739dcdd31d28bef1d785346f0c4afa1c304d737afe64f6892b2957917e762c54e71803b5789515b7d62479ab26a16576ddb7d77b6d7f4e963d3fc0fe2ad22e3c0573e10d5ae134a5581e38eed89937991d98fc800c633ebcd62ff66783b41ff8997f6ddbf89b67cbfd9fe43dbefcf19df938c75e95c55151ed6e95d6c74ac0a8ca4e126949ddad3f3dd7c99eadf1afc4ba16b9a258c1a56a31dd491dc977550c3036919e45647c1cf17d9f876f66d3f508d63b6bc70cf74ce408b6ab63e500e72703a8eb5c0514dd6939f3931cba9470cf0edb6bf13d274cd2fe1fe9d7b7ba8dff0088ad75b568a431d9b5b3c5f392187cc09f423a77ae7f41f18c9a1789a6d4b49b1369a7ceead2e9e93643055200de573d493d3bd72d454ba8f4b2b1a47051f79549395d5b5dbee5a7cf73d27c47a6780f5ebc8b53b6f135b68ad2c21a7b616cf37ef492cc4b6473ce3a76aa5af9f03e91e14874fd392db5dd4e45911ef94bc2d1924956d8720e01c633dab83a29ba97be8898609c7953a926974d3f35afe27ab7c14f12e85a1e897d06aba8c76b249721d15831c8da067815e4f8f6a7515329b9452ec6b4b0b1a5567513d656fc06e3da8c7b53a8a83a06e3da9d122b4a8aedb14b005b19c0f5a28a02c7b6e9fa9f83ad3e1eb784bfe12cb77dd0cb1fda7ecee3efbb3676fb6ec75ed5e2fa8430c17f7105b5c0b986395923982ed12282406c76c8e7151515a4ea73db4d8e4c2e0d61dc9a937cceeef6dfe47a6687e2cd2fc4da04da1f8ce6449a38e47b7d466258891b21484503ee839ebce2ab685e1ef00d8eab0dd6a1e32b5d46d9376fb66b2740f95207cc18e30483f8579e514fdaded75723ea0a3cca9cdc53e8adf876f91ddf86fc61a468be3a7d4f4fd0d2d2c6683ec8d1adc921419149972572785fbb8fc6af78af4af076bbe21bcd5bfe13ab7b7fb4b86f2fec2edb7803aee19e9e95e6d451ed5dacd5c6f03153f690938bb5bbdd7cee7b5782fc5de10b0f0c5ee9c8f069ac9249185dceff006921157cee9f2eec74ed8ae63e08ebba46857da9c9ab5ea5aacb1c6232c09dc4139e82bcf28a7ed9dd3ec67fd974f92a46efdfb5fe4771a6f8e6e3c3fe35d5ef6c8b5ee99737d3cde407d8b2ee63b5b25491c60d55f89163e144b95bef0c6a70491c8c10d9c7130f2c05e5b731e727b63bd723454ba8dab3378e0e10a8aa45d9dacfcfd7fcc6e3da8c7b53a8accea3d27e076bfa3685fdb1fdad7d1da79fe4797bc13bb6f999e83dc7e75c0ebb2473eb77f3c2dbe392e647461dc16241aa9455b9b7151ec73d3c2c615a7593d656fc0f56d1bc4ba143f05df4497518d75036d3a08086ce5a472074c7208acef01f8c6c6e34f5f0cf8bd16e34cc0f2e696421615451b136aae4f23ae6bcea8abf6d2baf239ffb36972cd36fde77bf54fc8ef34ff0cf81e0bfb79ee7c756d730c72abc909b07512282095ceee3238cd4775e26d0346f1d5aea7e1ed0a25b6d3da54fdddc30174194a86e54edc6738e6b87a2a7da5b65634fa9f336ea4dcb4b5b65afa58f53f1c43e10f166b09ab3f8ce0b063024661fb23c982327ae47afa5697c37f11f83bc3df6fd37ed36f0f95e5aff00687cff00e9df78e7660ecdb9c7539cd78d5156ab352e64b531965919d2f6329be5e9b69fe7f33bdf879ad695a6fc4abfd4ef6f121b393cfd92b0383b9f23a0cf22b1be285fd9eabe3ad46ff4f9d6e2da5f2b648a080711203d7dc1ae6e8a8751b8f29d10c24235bdb27adac371ed463da9d4566750dc7b57a3fecf63fe2b3bcffb073ffe8c8ebceab6bc1de25bef0b6a726a1a7c56d2cb24261227562bb4b29ec473f28ad29c94669b39b1b4655b0f3a70ddaff23b7f1edaf8635bf17ea36d7b7d0f876e2ca4024b831b4e6f4b28e7008d9b76fbe777b541e27f1769ba0e8917877c16e81244596e2fa16237be0a3a94607a85539cf7ae13c41aa5c6b7ac5cea9749124d70c19d62042838038c927b7ad50aa955d5dba9cf4b2f5cb0551b6a36d3a5edf7bf2be8771f0f3c62ba7c3ff08f6b89f69d16e479455e4dab02b312cd80a4b673d322b534fd17e1cdbf88a5d42e7c4f697560ef232589b5914206ced5df9c9db91db9c5799d149556924d5ec695302a5294a1271e6ded6d7cfd7cd1d8f887c7f7fac789ac35492074b3b2b98ae63b2f3430dc8413f36d079c7a719af40d47c7fe14934cb4f121820b8d72d63fdcda79ac1a32e42baefdb8e0127a76af0ea29c6bc95fcc8ab95d0a8a292b72f6edd53f5fbcf71d13c7fe14b98ee3c47796f0586b5e4bc02232b3b48830cabbb6803247a579fd9f8fefacfc6977afdb5bbc76f7922bdc598946240aa4005f6f6c93d2b8ea2895793b052cae85372d2e9e9af45d91e93e23d33c07af5e45a9db789adb4569610d3db0b679bf7a496625b239e71d3b552d7cf81f48f0a43a7e9c96daeea722c88f7ca5e168c924ab6c390700e319ed5c1d149d4bdf445c304e3ca9d4934ba69f9ad7f13d5be0a789742d0f44be8355d463b5924b90e8ac18e46d033c0af27c7b53a8a994dca297635a5858d2ab3a89eb2b7e0371ed463da9d4541d0371ed5d9fc3abaf09149b4ef12e9d002fbe44bf9247f9385013628e79c9ce7bd71d455465caee655a8aab0716daf43b5ff844fc1dff00450adfff0005cfff00c5d6bfc44f166967c1d63e12d2a64d4a38eda147bc5253063c0c6c23bedcf5e335e67455fb4b2692b5ce7782e79c65526e5cbaadb7f924759f0afc4d0f863c4065b8b7124376120924326d10a97197e87381db8ae9e6b0f00dd78ca7d7b50f15dadddb4f33caf64d6ae01dc0803783d8907a76af2ca288d4b2b3570ab8153a8ea464e2dab3b5bf5dbe46fde6a3a44fe31b3bdd3f4d4d2ec619e2dc8b2990615f25f2403c8ed5d57c71d7f46d77fb1ffb26fa3bbf23cff3360236eef2f1d47b1fcabcda8a5ed1d9aee53c1c1d4854bbbc2e97cfbf51b8f6ad9f0b695a46a72cebab6bc9a3ac6a0c6cd6e65f309ce4704631591454276674ce2e51b276f3ff00873db75cd4fc1da9f812dbc2ff00f0965bc5e4430c7f68fb3bb6ef2c019dbc75c7af15e71a4d958d87c47d1adb4ed4d753b75bfb62b70b118c31322e46093d2b99ab3a5de4ba76a76ba840a8d2dacc9320704a965604671db8ad655799a6d1c34701ec2128c24ddefa3b6eefe47b57c633a25cde69da4eac91dab5d46e63d51896fb2852188f2c7dedd8dbd46339ae6ec755f0b781b40965d0efa1d6f599cb406e115a068e3619ce0ee070cabc77cd721e34f16ea5e2b9ada5d460b489add5953c84650412339cb1f4ae7eaa75bde6e28c30d96b54234eac9f9abe8f5fbfb68b426d4af2eb51be96f6f6669ae253992461cb1c63b557c7b53a8ac0f59452564371ed5ca7c4d1ff001723c4ff00f618bbff00d1cf5d6d727f133fe4a4789ffec3177ffa39eb3abf0fccebc17f1dfa7ea8e731ed463da9d45739eb0dc7b518f6a751400b8a314b8a314142628c52e28c500262bd8fc6dff239eb9ff611b8ff00d18d5e3b8af63f1b0ff8acf5bffb08dc7fe8c6ade97c2cf2b1ebf7b1f47f9a31e8a5c518abb9c96128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e16128a5c518a2e1612b94f8983fe2e4789ffec3177ffa39ebacc5729f1307fc5c7f137fd85eefff00473d4d4f87e674e057efdfa7ea8e771462971462b9cf5c4c518a5c518a007628c52d14ae3b098a314b45170b098af62f1b7fc8e5adff00d846e3ff00463578f57b178d7fe472d6ff00ec2171ff00a31ab7a5f0b3cac7ff00161e8ff3463d14b45687209452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d14009452d140095cafc4b1ff00171fc4dff617bbff00d1cd5d5d729f12ff00e4a3f89bfec2f77ffa39aa2a7c3f33a703fc77fe1fd51cf628c52d15cf73d7b098a314b45170b0b8a314b8a31415613146297146280b098af62f1a8ff8acb5bffb084fff00a31abc7b15ec5e35ff0091cb5bff00b084ff00fa31ab7a5f0b3c9cc3f8b0f47f9a31f1462968ad0e4131462ac58d9dddfdc0b7b2b59eea620911c319762075381cd6ff008cfc2327861105c6afa7dccece15ade17fde202090c54f207f8d3516d5cca55e9c66a9b7abe87318a314b40e4e05235131462af6a1a46ada746b26a1a5ded9a31daad3dbb4609f4048aa5434d6e28c9495e2ee2628c52d140c4c518a5a280131462968a004c518a5a280131462968a004c518a5ab9a7693aaea28efa7e9b7b78a870e6081a40a7df038a12bec2949455dbb14b14629ceac8c5594ab038208c1069281898a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d69db787b5fb9812e2db43d4e6864194923b476561ea08183424dec4ca7186b27632f14629d223c7234722b23a92195860823a822928284c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5ab1a7d85f6a131834fb3b8bb942ee29044cec17819c01d391f9d1b89b495d95b1462a6bbb6b8b3b87b6bbb796de74fbf1ca85597bf20f22a2a0134d5d098a314b4503131462af4fa3ead058adf4fa5df4568c032cef6ecb1907a1dc4639ed54a869a1464a5b3b898a314b4503131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5ab5a769da86a2ee9a7d85d5e320cb2c10b48547a9c0e28b5c4e4a2aed9531462adea3a6ea3a7322ea1617566ce09413c2d19603d3239aab435605252574ee2628c52d140c4c518a5a28013146296aedee8fab58dbadcdee977d6b03101649add9149232002463a0345989c926937b9471462968a062628c52d140098a314b45002628c52d140098a314b45002628c575cde05bf97c38bace9b7b6daae426eb6b20d2ca85803b485070467915c953945c7732a55e9d5bf23bdb462628c52d148d44c5729f12c7fc5c6f137fd85eefff0047357595ca7c4a1ff171bc4dff00617bbffd1cd5157e1f99d381fe3bff000fea8e7b1462971462b9cf62c2628c52e28c50161d8a314b4505584c518a5a280b098af60f1aff00c8e5adff00d8427ffd18d5e415ebfe351ff1596b7ff6109fff0046356f4be16793987f161e8ff3464514628c568719ec9f0d2c2cbc27e17935ff00125b47672492836f7257cc6f29d1718d9920139e2bc9352bfbcd4ae9aeafee65b99d8006491b2481d2bd93c69ff131f83f67169ffe9722476cacb07ce548519040e84578962ba2bfba9456c78f957ef6552bcfe26daf44bb75459d2ac2ef54d421b0b18bceb998e234dc172719ea481dab6757f06f89344b55bed534dfb3db89154bf9f1b724f1c2b135e87e18b383c31f0ba7f1369a81b509edd256330dea18311c0edc31ef5e6da5789754b3d75f54578e5965762c9302f1fcc79c293f954b84609736ecd69e2ab62273f62972c5db5ddbebe9f89e9ffb41ff00c8bba77fd7dffec86bcafc3be19d6fc42b3b68f65f691015127ef5136e738fbc467a1af62f8c56b15f47e1eb29f779571aac713ed383b5b838fc0d2f8c744bab4f0edb681a16a5a4e9d68f11495afa6292b804152ac0738e73f515d156973cdb7b23c9c063beaf85a74e36e6937abd92bebb1e49aef837c49a1d81bed534efb3db8609bfcf8db93d385626b374bd2750d4e2bb96c6dfcd4b381a79cef55d918ea79233f41cd7af7802caf744592c357f107876ef4c94b48c16ef7cbbc80060b0031c527c3ad334bd33e21eb761a54a2e2c96ca3dac6412039da48c8e0f24d65ec136bcced79aca10a9749b8aba693b3575df667984be13f1047a00d79ec31a7141279de727dd240076eeddd4fa51aaf84fc41a56931eab7f61e4d9c9b76c9e7236770c8e0313fa57b2780543f8a3c5f0380d0ade461633ca81f370074a8be19813f86f5e59c0955754b90a1fe600045c63354b0f176f9fe0633cdeac399b4ac9c7ee97eabbf5ec785d8dacf7b7b059daa7993cf22c71ae40dccc70064f039ae9ff00e15b78d7fe80bff9350fff00175d9fc38d32d349f87779e2bb64dda89b59d87998640632c5481dbee8ef5e79078ab5987c4875f5994dd191a4d87263cb020fcb9e9cd65c918a4e5d4ef58aaf5ea4e342c9434d7abfbf45e662cf13c333c32aed92362ac339c10706995ebbf18b46b19bc3367e252852f9d61898260210433138c75c9af22c54548724ac74e0b14b154bda256e8fd5051462badf853a1d96bde2afb25ff99e547034c36360ee565c7e1cd4c62e4d246b5eac68d395496c8a9a3f823c4fac69d16a3a7699e7dacb9d8fe7c6b9c12a782c0f506bd27e04da5c5841ae595dc7e5cf05d2c722641dac010464706b96f8c9ae5f9f134da1c4c9059d93234422050e5e3563923af24d767f04758bbd5343b986e961c5998e24644c330da7963dcf1d6bae8c611ab65ba3c0cc6ad7ab807526972cacf4dd2be9ebf81e2b7d1bcdac4f146373bdc32a8cf525b8ae8bfe15b78d7fe80bff009350ff00f1756fc0765afcbe2cbeb9d163b346512ee9afa3630801c123201f9ba7e19ad1d73c33e20d5f549b51b9f16786d269b1b8457eeaa30a07036fa0aca34d357699e856c64a15142328a492deedfdcb6fbce2bc43e1fd5fc3f3450eaf69f667954b20f311f201c7f0935df7c32f00ea116b4f3f8934485ec1ed4f97e6491c8379652380c4f4cd743e3eb3fb37c1c10ccf05c4d05bdb47e7c6770621d012ac7920d727f01e79e4f18ce924f23a8b17c067247df4ad1538c2aa4fa9c753195b1381a9522d2e5ba7beb6b6ab5d3f12bfc47f03ead6dacea7a9e9da4470e8d12abab24b1aaaa845dc42eecf5cf6ae02baff008b73ce3c7daac427904798c6cde71fead7b57296f6f3dccc21b78649a46e891a9663f80ac2adb9dd8f5300e7f578ba8d3d17e5d6eff136b42f07788f5cb1fb7697a77da2df794dfe7c6bc8ea30cc0f7a9f52f01f8af4eb19afaf34af2ade152f23fda223b47ae0366bb8f11693e2ed434b834bbfd73c2f611a32cca893bc2ff74819f97dcd6c7c27f0fdde932ea42fb57d3b5349923016dee4cdb705b3b810319cfe95b46826ed6679957349c29ba9cd1f449bd2fdf44785515ade3150be2fd655405517f38007403cc6ac9c573356763dd84b9e2a5dd2614518aebec66f8722ca017b69e226ba11af9c6331ec2f8f9b6e5ba6738a718dfa9156afb349f2b7e8affaa390a2bb5f3be187fcf9789bfefa8bff008aac5f143f85dc5bff00c2370ea7111bbcff00b614e7a6ddbb49f7cfe14dc2caf74674f10e72e5e492f55a7e662514628c541d26bf877c35ad7887cffec7b2fb4fd9f6f9bfbd44dbbb38fbc467ee9e956749f06789755fb4fd834df3becb3b4137efe35db22f51cb0cfd47153fc2d7913c77a52abb2ab4df300700fca7ad769f1cb5abdb1b98744b4f2a1b5b8856e2428bb5cb8761d47d056f1841c39d9e5d7c5578e2961e9db5574df4b6f7d75f2d8f3ff10f8535fd02da3b9d5ec3ecd148fb11bce47cb609c7cac7b0acdd2ac2ef54d421b0b18bceb998e234dc172719ea481dab72cbc69acdaf87df431f669ad5d5d4b4d19771bc107049ebcf15e97e00d122d2fc03fdafa5496d0ea5790a4866bd6fdca30623d320609fd288538ce5eeec188c6d5c2d2bd54b99bb2b6deafb7e279e1f86fe340093a3703fe9ea1ffe2eb97b3b79af2ee1b4b74df34f22c71ae40dccc70064f1d4d7a6e91a2f88f4dd764d621f157861e6919cb2c97ccc9f31c9c0db5a1e3bb0d03fe12cd0357d36ea096ee7d52de395219959428230768e9d0537455aeb4318665253e495a574ecd27a349bb3bfe679edef833c4b67a959e9d73a6ecbabd2c2dd3cf8cefda32790d81f8e2935df06f89343b037daa69df67b70c137f9f1b727a70ac4d7a5fc76d66f34fb4b2b0b61108ef124f31cafcebb4a11b4f6eb583f05759bdb8d51fc3d72639eca659276f31773eedaa3827b714e54a0a7c82a58ec54b0ab12d2b2dd6baabf4ede9a9e675ed3f00afef6ef4dd46dee6e65962b6312c08cd911821b81e9d0579c7c45d1acf41f165ce9961e67911aa15f31b2dca8279fc6bbefd9e3fe3d759ff7e1fe4f4a8271ab61e6d3856cbdd44b4766be6cf2cd7ffe43ba87fd7cc9ff00a11a82c6d67bdbd82ced53cc9e791638d7206e6638032781cd58d787fc4f6fff00ebe64ffd08d755f0b2cfc462e2eef7471a7c08b0ee69f5046f2b01bf85803c8c56518f34ec7a156afb1c3f3e9b2df45b22a7fc2b6f1aff00d017ff0026a1ff00e2eb0b5fd1353d06f56cf56b6fb3ced18902798af95248072a48ea0d76baa784f5ed47519efa7f16f875659dcbb08f5075504fa0dbc575df16ad23ff0084174f8e5f2ddc5c5bc4d2af391b48383e95b3a29c5b4ad63ce8e6538d5a709352e6d1d9356fbf73c83c3de1fd5fc4134b0e9169f697894338f3113009c7f1115b3ff0adbc6bff00405ffc9a87ff008baeebe231ff008427c1b6ba4e86aab0dd196091e51ba4dac0938618e724d796e87af6a9a46a90ea36d70cf3439da2562ca72a47233e86a650841f2cb736a389c4e2a0ead1b28f4ba7776efae8664d1bc32bc520dae8c55867a11d6ba0d1fc11e27d634e8b51d3b4cf3ed65cec7f3e35ce0953c1607a835bde0edbe3df1d2beb91c69e55a96516abe582558119eb9fbc6ba9f891a46b1abea3f63b7d77c3fa7d8dbb068617b968a552546edc00c75c91ec4510a29a72dd1388cc250a91a3a4656bb6eed2f4b6ff0081e55e21f0f6b1e1f9628b57b3fb33cca5a31e623e40ebf749acbaf75d22c2deefc2d2683e2cd6f449e2558e3824b3ba1bc229079661d7207eb5e1f791a477734719ca2c8c14e73900f153569f259aea6f81c63c473464b58f557b3f4b91514628c5647a06deb7e13f1068ba747a86a761e45b48c111fce46c9209030ac4f406b2f4eb3b9d42fa1b1b38fcdb89dc2469b80dc4f4193c57b77c5a852e3c1ba3412676497f6e8d8eb828c0d64eade10d23c31e2ff0b49a61b8dd717d87f364ddc0db8c703d6ba6742d2d36d3f13c4c3e6dcf4939af79f35acb4f77e679a7887c3fabf87e78a0d5ed3ecd24aa5907988f919c7f0935d4fc28b3f1458dccbaf68da1a6a30c9135b7cd729180772b1ea73d876ef5a5fb427fc86f4cff00af66ff00d0aab7c01793fe12eba8cbb6c160e76e78cf991f38a51828d6e54555c4ceb65aeb492d56ab5b6fea72de3f9afee3c5f7f36a766b6576ccbe640b20708762e3e61c1e307f1a6787bc2baf7882de59f48b0fb4c713ec73e72260e338f99855ff008b03fe2e16adfefa7fe8b5ae6e29e7841114d2460f242b1159caca6ee7750e7961a0e9d93b2f35b7afea7b47c45f01c773e1eb58fc35a15b2df09d4ca632919d9b1b3cb100f3b6bc7355d3eef4ad426d3efe1f26e6120489b8360919ea091d0d7b37c719658bc13a7b4523a31bc8c12ac41ff56f5e23233c8e5ddcb31ea58e49ad312a2a7648e0c8e556787e69caeaefbdf7ef7d8f6bf1d7fc911b2ffaf5b3fe495e7771f0f7c5f6f6b25d4da46d8634323b7da623850324e3767a57a9eb5aadce8bf0934ed42d1207952ced401326e5e55474af2af01f88754d37c49079328945dba5bcab365c6c675ce067af1575941c9737647365d2c4468d474ada4a5bf5ff002fc4e628af49f8e7a158699a859ea368aeb2dfb4866048da36840368c71d4d79b62b9ea41c24e2cf6f09888e268c6ac568c28a315bbe05b2d56ebc496874986279d24e1e68d9a14241c6fc0381c1a94aeec6b526a9c1c9f42e5afc3df17dd5ac5730691be2990488df69886548c8382de9547c43e14d7f40b68ee757b0fb34523ec46f391f2d8271f2b1ec2bb7f14683e26d6ef525bff13f862de4813c9090de3c60004f046debcd755a3e95269ff0aefed2f2eed350923b6b971343279a9cab630c47515d2a82775667892cd2a535193945dda5649f5f37a76e8781d14628c5729ef12d9dbcd79770da5ba6f9a791638d7206e663803278ea6b6ef7c19e25b3d4acf4eb9d3765d5e9616e9e7c677ed193c86c0fc71580a59583292181c820f22bde2cf51b8d33e0f5aeb1008e4bcb7b4568de55dd825c03efd0d6d4a119defd0f3b30c555c3387224f99db5efd35eddcf326f871e34552cda3600193fe950ff00f175c9d6ee91e2ad634bd6a4d5ade6579e4dfb964cb27cc7278cd759f0dede1f19f8dae353d623459ad9239e35b75089b91940c8e7238e6928c66d28ee3957af878caa5749c52be9dfb5b5fbce6f4df01f8af51b186facf4af36de650f1bfda221b87ae0b66b2fc41a26a9a0dea59ead6bf669de3122af98af95248072a48ea0d7a7f8ff0045d6f5ad58c6be21f0f595b5ab32dbc5f6b68a45538e1c01d78ad636565aaf849b4df166b1a34d710b030c9637201d8aa36825b9ce7767f0ad5d04ee91c31cd671519cacd3dd24eebb74b3f33c3ec6d67bdbd82ced53cc9e791638d7206e6638032781cd74ff00f0adbc6bff00405ffc9a87ff008bab7f0b2cfc462e2eef7471a7c08b0ee69f5046f2b01bf85803c8c55dd53c27af6a3a8cf7d3f8b7c3ab2cee5d847a83aa827d06de2b38534e37699d788c64a359c2338a4bbddbbfcb6fbce2b5fd1353d06f56cf56b6fb3ced18902798af95248072a48ea0d69fc38bfbdb3f1869b15adccb0a5cddc51cca8d8122ef1c1f515e99f192dd63f87769bbcb79525850cabce70a7a1f4af2af028ff8ad345ffafe87ff004314e50f675125e44e1f13f5cc14e735d24bcb44cee7f686ff00908e91ff005c64fe6b5e595ea9fb437fc84748ff00ae327f35af2bc52c47f119793ffb953f9fe6c28a31462b13d236f47f09ebfabe9926a5a75879f691960f279c8b82a32782c0f43e953e8fe08f146afa745a8e9fa679d6b36763f9f1ae70483c1607a835e9df0624687e1cdecaa01649e6619191908a6bcaa5f13eacfe215d70491c770b22c82340562ca8007cb9e9c56ee108c62df53c9a78ac4d6ab569c125c8f4bdfeedff001fc0cabfb59ec6f67b2ba8fcb9e090c722641dac0e08c8e0d7b57c6589e6f016970c4bb9e4bc81546719263702a87c45d36d757f8716be2bba4dba8adac2ff00bac2a13232ee2477ea7bd6efc4ed5eeb46f02da4f6890b3caf1c2de6a6e014c6d923d0f1d6b58d3e48cd3dac79f5b16f135684a2bde5269ae975fa1e4fa8f80bc59a7d8cd7d79a57956f021791fed111da075380d9ae66bb3f85daf6a369e27b7b0122cd06a13243389b2ff2e4f4c9e3ad753e24f05e8b2fc4db0b022e160d4927b89c2b8186f99be5e3819ed597b2538de07a4f1d2c3d574f116d9b4d765be97381d0bc1de23d72c7edda5e9df68b7de537f9f1af23a8c3303dea1f1178675cf0f242dac597d984e488ff007a8fbb18cfdd271d4576bf1a758bcb5d4a3f0eda98e0b289239d7cb5dafbb0c3923b60d687c26b993c51a0ea1e1dd5c249696d0aac6ea3f7803b313f31cf3c53f65072e45b993c762234562a497276eb67e7b5fcac79e787bc2baf7882de59f48b0fb4c713ec73e72260e338f99857a9fc45f01c773e1eb58fc35a15b2df09d4ca632919d9b1b3cb100f3b6bc9b5d8db4bd7f52b0b39e68e1b7bb96241bce485720671d4e057ae7c719658bc13a7b4523a31bc8c12ac41ff0056f554d4792575b19636759e26838492527a68fb75d753c6755d3eef4ad426d3efe1f26e6120489b8360919ea091d0d3f44d2aff0059d412c34d83cfb9704aa6f55c803279620554919e472eee598f52c724d7b445147e0af85f16b5a3229bcb88ede6769c6f019d54363a60735953829b6fa23d0c662a587846295e72765daffe479f5d7c3df185b5b4b733e91b22890bbb7da623850324f0d5cb574fe04f10ea9a6f8960f2a5128bb912de559b2e3633ae7033d6ba2f8e7a158699a859ea368aeb2dfb4866048da36840368c71d4d3708b87347a110c554a788542b59f36cd796f75a98ff0abc4a9a0ebe8ba86a32db696c1da5401994bedc02554139e956be307869f49d6bedf67a7456ba54a12389a32a017da491b7391d0f38ae26dadee2e65115b4324d21e4246a589fc057b27c7d9e06f0ed95b2cf119d6ed59a30e3701b1b923ae2aa1ef5269f430c47ee71f4e50fb774fe5d7d7cd9e2d4518a315ce7b015cb7c4a1ff001717c4bff617baff00d1cd5d4e2b97f895ff002517c4bff617baff00d1cd5157e1f99d581fe3bff0fea8e7b1462968ae73d8b098a314b45016168a7514ae3b0da29d45170b0daf60f1a0ff008ac75aff00b084ff00fa31abc86bd7bc69ff00238eb5ff006109ff00f46356f49fbacf27305fbd87a3fcd1938a314515a5ce3b1e8bf083c6163a1a4ba46a0ab0c13c8d31ba673853b400bb4039ce3ae6b9ff001d783afbc2f7403b35cd9b10b1dcec081988c918c93c735cd559bbbfbebb409757b733a8390b2cacc01f5e4d68ea5e1cafa6c714706e9e21d6a6eca5f12efd9aedf91d9f807c610c36c7c3be235175a44e1630647dab6ea324f0065b271dc74a65c59f83346bd93558753b7d7a332109a6889e1da18f077e4fddfa735c35147b576b357b03c0479dca127152dd2d9ff93f3563d5fe2e78ab49d474ed35b44d4d26bab6bc13028a7298070791eb8aa90788341f17f86dac7c51711586a36b1aa5bdf4bba4672c72cc114003ee818cf7af33a2a9d793936fa99432aa54e946116ef1774faaff0081e47a1e83a2780b4ebe373a878a6d75584230fb3b5abc609ec7218d4ff0dbc4be1eb2f15ea57f24316896735b2a450ef6900604679c679c135e6b45255b95a696c54f2e556328d49b7ccadd34f92d3f03d8fc19e2af0f597897c4f7575a9c514377748f03956c3a80d9238f7151fc3ef14e81a768bacc17ba9450c93ea33cb12956f99195403c0ef835e414552c4c95b4eff898cf26a53524e4f5e5edf6763b4f0078c0e96bfd89ab27da747b91e43a33ed5855dbe76e01278278c8ad58346f87a9e243a8c9e26b592c0c8cc2c3ecd200148202efce78241e9dabcda8a9559a49357b1d15301194e53849c79b7b5b5f3f5f34759e3ef185c788255b2b6536ba5c0022401f7ab152407ce011c11c7b57278a28a894dc9dd9d346842841420ac9062afe81ab5f689a8adee9f3b43201b58ae3e65c8257907ae2a8514949ad51a4a119a7192ba67a6ebf3f83bc67696faadd6a76fa06a9231372a6379d980f95413f28e8a0f4ef5b1f0f7c4fe10d16d6ef4ff3a0b3f25d63fb4e5cfdb7008f376e3e5cfa64f5af1aa2b65886a5cd6d4f32a65309d2f64e6f97a2edfe7f3b9e9df0f3c63a659dbea3a16a0c96b6d3c93ccb78cc48cb6005d807a64e7358bfd87e10d3bfd3ff00e12b8356f23f79f62fb23c7f68c7f06edc76e7a6706b8ba2a7db5d24d6c6cb2f519ca709b5cdbad35fbf55f23d73c63e2af0e6a1f0c0e9d617114170d0c012c94b318b0c84a6e2067001e7dab87f875e224f0cf887edd25b79e92c4606fde6cd8199496e8738c74ae6e8a255a52929760a39752a54674756a4db77f33d4b5ab5f046ade327d7aebc596cf04922349686d5f0c1542eddd9ef8f4ac27f13e89a3f8de1d4f42d0a34b5b232c4152e0e2e01dca1f254ede0e71cd715450eb37aa56153cbe315cb39b92b5acf456f95bf13d43c6d07847c53ac8d55bc65059318510c5f6469318cf7c8f5f4abdf0f3c41e10f0f4b79a77da204f29507f697ce3ed9d4fdcc1dbb738ea735e434552aed4b992d4ca59546747d8ca6dc7a6da7e1afccd1f144f0dd789b55bab77124335e4d246e3a3297241fcab3b145158b77773d2841422a2ba0628c51452b9560c518a28a2e160c518a28a2e16373c03796ba7f8c34dbdbc9561b78a5dcee73851835b9f19758d335af105a5c69776975125a046650461b7b1c73ec4570f455aa8d41c4e6961232c42af7d52b062bbcf00f8c2186d8f877c46a2eb489c2c60c8fb56dd4649e00cb64e3b8e95c1d14a13707745e230d0c443927ff00053ee8ee6e7c35e0b92f25962f1cdbc513485962162e76293c2e77738e956754bcf0b3f8d745b2f0de990c71c7a85bb9bc8e563e602c32bb587183df3dabcf6ac69b772586a36d7d0aa3496f2a4a81c65495208ce3b7157ed576b1ccf0527acaa39593493b25aab6b6b5fe773da7e2fbe8f2cba669baac51c26e96411ea0c49fb263692760fbd9e07518ae7346d43c25e09d2a5bcd3afa0d7b55690aa3aab40cb1b0008e770e08cfe35c8f8cbc5ba978a9ed5b5082d2236c1827908c33bb19ce58fa0ae7eb4a95d73b9451cb85cae4b0f1a5564d7749e8f5efbfc95916b55d42f755be7bdd42e1ae2e1c00cec064e06074f6af50f851aa7863c37a4cd25df886133de84792130b03095cf19e73d7dabc968aca155c25cdd4eec560a388a5ec5bb47cac6d78cedf4d835d95f4cd55352867cccd22c450233337c98279c0c73ef5d9780bc57a5b7836efc29aa4c9a6a1b69634bb625f7190b7f001db77af38af33a288d57195d056c146b5254e6de96b3eb75d7b1da7f64f84346ff8997fc24306bfe4f3fd9ff67787cfcf1f7f2718ceee9db15d2fc4cf14e85a9f82ad6d74abd88dca4d138817713180a78c91ce3815e4d45355ac9a4b732965ca752152a4db7177e9f92d3f53d2f49f14691e27f0f49a3f8be548ae618dcc1a84a4b1dec4e084503ee823bf38aa9a2681e04b2d521b9bff00185b6a16e99df6ed66e81f2a40e431c60907f0af3fa28f6d7b5d5c3fb3d4799539b8a7d15bf0bedf23a4bbf11c563e26fed2f0cd98d26244f28a472eff003406c93961c6463f2aea75f9fc1de33b4b7d56eb53b7d03549189b9531bcecc07caa09f9474507a77af32a292aaf54d5d173c041b8ca2dc64baf5b7677bdfe67a1dadb780b40d16f2796f2d7c4f77b90c30947b738c8040393d8e7f0af3e98a3caee89b159890b9ced1e94da294a7cdd2c6b430fec9b6e4e4df7ff0025a2fb8314628a2a2e7458f6ab1f1bf8575af0dc7fdbb1410cf6afbe1b579198b322fcadb828c6724547a37c40f0df882f527d7ec21d364b06125a4924ed27cc7ae30a318c0eb9af19a2ba3eb533c879261ececdabedaed7dedebd6e75bf147c4d078975c56b78024369be18e4126e132ee387c606323b735d3fc3d5f08f85b557d49bc6105d34b6c61317d95936e595b39c9feee3a5795d150ab352e76b53a6a65f19505878c9a8dada5b5fbceffe205a786354d4352d7ad3c590c93ca03c7682d5b2c42818dd9f6f4ae0314515139f33bd8df0f41d0828733696d7b7e87aceb5e21f0ff8dfc276d69a96ad16833c37224d8c8d392154a8e817aeefd2b03c48fe0ad2fc35069fa6456facea1223ac97ca5e2319c821b61c83d718cf6ae168ad2559cb75a9cd4b2d8d2b28cda8a77b74fbf7fc4f55f16f89343bcf85369a4db6a11c97a96f6cad080d90542ee1d31c60d79b6852c56fae584f33048e3b98ddd8f6018126a9d1533aae6d366b87c1430f4e508bd1b6fef3d23e35ebfa3eb8ba48d2afa3baf24cde66d046dcecc751ec6bcdf14514a751ce5cccbc2e16386a4a945dd20c576ff093c556be1bd4ae2def225fb3df3461e7321021081f9c60eeceef6ae228a509b83ba2b118786229ba73d99deeaba07842fb53babeff0084ee04fb44cf2ecfb0b9dbb989c67773d6ba7d37c61e156f87f7d6502c1a44b25bce89641da4f999481f363b9af1ba2b455f95b691c7532c556318d49b766adb74f45f9fc8314628a2b1b9e958315eab73e24d0dbe0f0d197508cdff00d9513c8c367707048e98e95e55455c2a385edd4e6c4e12388e4e67f0bb862b5bc2baf5f787b548ef6ce460bbd7ce8c103cd40c09424838ce319ac9a2a549a7746f3a71a917192ba67a578863f0578a96db553addbe857b2ab3ddc5e4bce59ce3a9ca8e31d877aae63f01e81e1d947996de26bf69be4387b7288540f7070467f1af3da2b475b5bd95ce18e5f68a87b4972ae9fa5f7b7ccf4cf0178af4b6f06ddf853549934d436d2c6976c4bee3216fe003b6ef5e7158ff00d93e10d1bfe265ff00090c1aff0093cff67fd9de1f3f3c7dfc9c633bba76c5717451ed9b4935b14b00a3394a126949ddad35f9eebe4fd0f58f89fe27d0356f03dbd969b79134e2589bc85dd94014e4648e71d2b8af8791e94be2182fb54d5d34e4b29639d03445fcd2ad9dbc1e3a75e6b9ca289567297334147011a341d1849d9df5d2fa9ea1f17aff00c37e20b48afec7c410bdcd9a154b65858997732e7e638c6064f43d2bcbf14515352a73caed1ae130ab0d49534db4bb8628c514545ce9b1eddf065913e1d5e3c91f988b3cc59338dc362e4571ba658782751d420d6aeb56b6d2a0690349a4185e4081782be664677637671c6eacbf0df8e356d07439b47b3b7b27826676669518b82c00382180ede95cbd744ab47962ad7b1e3d2cbea2ad5a6e4e3ccf4b3e9e9fd3f33b4f1ff8c0ea49fd87a42fd9b47b61e4aa23ee59d5186c6e4023a0e326ba1f8a9e24d0f56f05d958e9fa84771711dc46cd1a8604011b02791ea457955151ede5adfa9d2b2da29d371d391dfd5bee6bf82aeadec7c59a65ddd4822821b95791cf4500f5aec3e2978aade6f11e9ba9f86f530d2dbc0e9e6c6bf749278f987a1af38a292aad45c51ad4c142a578d696e9356e9a9e9dabea3e13f1b69315dea57d0683ab2c815e46569d9e3504638da0649cfe14d9b5fd03c1fe1b165e18b98b50d4ae91927be8f746c841cab156041fbc4633dabcce8aaf6ef7b6bdcc16590b28393704efcbd3d3bdbcae49773cb75752dd5c3992699da491cf5662724fe75eabad7887c3fe37f09db5a6a5ab45a0cf0dc89363234e48552a3a05ebbbf4af26a2a6155c6ebb9be23070ace32bd9c766ba7e8775e247f05697e1a834fd322b7d6750911d64be52f118ce410db0e41eb8c67b55af03f8c2cae2c47873c5aab71a71c6c9a5721625451b536a8c9e40e735e77453559a7748cde5f0953709b6ddef7bea9f976f9687a3f87f4ff036857736a73f88edb5878a22f6f6e6dde2c48086521b279e31d3bd72de34f13df789f52371725a3b746260b72c1844081900e0673815834529556d72a5645d2c146153dace4e52eeeda7a5b43d3fe18e81fd8013c5faedd7f67dba0db1a3c7b848b228dadb81e3af4c5717e37d7bfe125d7e4d57ec9f65de8a9e5f99bf1818ce703f9566cb7f7d2db0b696f6e648000046d2b150074e338aad44aa5e2a2b6268e0dc6b4abd47793d1764bb0628c51456773b6c18ae57e24ffc945f12ff00d85eebff0047357555cb7c49ff009289e25ffb0b5d7fe8e6a8aafddf99d5815fbf7fe1fd51cf514ea2b9ee7b161b453a8a2e161d4518a3148ab0514628c50160af5ef19ffc8e1ad7fd8427ff00d18d5e438af5ef19ff00c8e1ad7fd8427ffd18d5bd2f859e4661fc587a3fcd19345145687185145140051451400514514005145140051451400514514005145140051451400514514005145140051451400514514005145140051451400514514005145140051451400514514005145140051451400514514005145140051451400514514005145140051451400514514005145140051451400514514005145140051451400514514005145140051451400514514005145140051451400514514005145140051451400514514005145140051451400514514005145140051451400514514005145140051451400514514005145140051451400572df123fe4a27897fec2d75ffa39aba9ae5be240ff008b89e25ffb0b5d7fe8e6a8abf0fcceac07f1dff87f5460514628c5739ecd828a3146280b0ec518a5a281d84c518a5a280b098af5df198ff8ac35affb084fff00a31abc8ebd73c67ff2386b5ff6109fff0046356f4be16793987f161e8ff3464e28c514568718628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018ae5fe238ff008b87e24ffb0b5d7fe8e6aea2b98f88ff00f250fc49ff00616baffd1cd5157e1f99d380fe3bff000fea8e7f1462968ae73d9b098a314b45016168a5c518a572ac2514b8a3145c2c257aef8cbfe470d6bfec213ffe8c6af23c57ae78c87fc55fad7fd8427ffd18d5d147e1679198afdec3d1fe68c9a28c518ad0e3b0514628c50160a28c518a02c14518a31405828a3146280b0514628c50160a28c518a02c14518a31405828a3146280b0514628c50160a28c518a02c14518a31405828a3146280b0514628c50160a28c518a02c14518a31405828a3146280b0514628c50160a28c518a02c14518a31405828a3146280b0514628c50160a28c518a02c14518a31405828a3146280b0514628c50160a28c518a02c14518a31405828a3146280b0514628c50160a28c518a02c14518a31405828a3146280b0514628c50160a28c518a02c14518a31405828a3146280b0514628c50160a28c518a02c14518a31405828a3146280b0514628c50160a28c518a02c14518a31405828a3146280b0514628c50160a28c518a02c14518a31405828a3146280b0514628c50160a28c518a02c14518a31405828a3146280b0514628c50160ae5fe23ff00c943f127fd85aebff47357518ae63e238ff8b87e24ff00b0b5d7fe8e6a8abf07cff43ab00bf7effc3faa39fa2971462b9ae7b36128a5c518a2e161d8a314b8a31487613146297146280b098af5bf197fc8dfacff00d7fcff00fa31abc9715eb9e311ff00157eb3ff005ff3ff00e8c6ae9a3f0b3c8cc7f8b0f47f9a31e8a7628c568718da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400dae63e230ff8b85e24ff00b0b5d7fe8d6aea715cbfc461ff00170bc47ff616baff00d1ad59d5f83e7fa1d580ff00787fe1ff00db91818a314b8a315cc7b3613146297146280b0b453b146282ac368a7628c50161b5eb9e31ff0091bb59ff00aff9ff00f4635792e2bd6bc63ff2376b3ff5ff003ffe8c6ae8a3f0b3c7cc57ef61e8ff003465514515a5ce2b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c145145170b05145145c2c15cb7c45ff9283e23ff00b0add7fe8d6aea6b98f88a3fe2e0f88ffec2b75ffa35aa2afc1f3fd0ebcbd7fb43ff000ffedc8e7e8a7628c5731ed586d14ec518a02c2d14b8a3145cab09452e28c5170b095eb3e31ff91bb59ffaff009fff004635793e2bd63c623fe2aed67febfe7ffd18d5bd1f859e3e62bf7d0f47f9a32a8a31462b538828a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800a28c518a0028a31462800ae67e227fc940f11ffd856ebff46b574d8ae6be220ff8b81e23ff00b0add7fe8d6aceafc1f3fd0ebc02ff00687fe1ff00db9181452e28c573dcf6ac2514b8a3145c2c3b146297146290ec2628c52e28c50161315eb1e301ff001576b3ff005ff3ff00e8c6af28c57ac78c07fc55bac7fd7fcfff00a31aba28fc2cf1f32fe343d1fe68c9c518a5c518ad4e213146297146280131462971462801314629714628013146297146280131462971462801314629714628013146297146280131462971462801314629714628013146297146280131462971462801314629714628013146297146280131462971462801314629714628013146297146280131462971462801314629714628013146297146280131462971462801314629714628013146297146280131462971462801314629714628013146297146280131462971462801314629714628013146297146280131462971462801314629714628013146297146280131462971462801314629714628013146297146280131462971462801314629714628013146297146280131462971462801314629714628013146297146280131462971462801314629714628013146297146280131462971462801314629714628013146297146280131462971462801314629714628013146297146280131462971462801314629714628013146297146280131462971462801315ccfc441ff001703c45ff615baff00d1ad5d3e2b99f8863fe2bff117fd856ebff46b56757e0f9fe87565ff00ef0ffc3ffb723071462971462b98f6ec2628c52e28c5016168a5c518a0ab09452e28c501612bd63c61ff00236eb1ff005ff3ff00e8c6af28c57abf8c3fe46dd63febfe7ffd18d5d147e1678d997f1a1e8ff3465514515a9c4145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450014514500145145001451450015ccfc43ff91ffc45ff00615baffd1ad5d35735f10c7fc57fe22ffb0adcff00e8d6aceafc1f3fd0ebcbff00de1ff87ff6e460d14b8a315cc7b76128a5c518a02c3b1462971462a462628c52e28c500262bd5bc5e3fe2add63febfe7ff00d18d5e558af56f178ff8ab358ffafe9fff00463574d1f859e3e65fc687a3fcd1938a314ec518ad0e21b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e28c53b1462801b8a314ec518a006e2b9af8843fe2bef117fd852e7ff46b574f8ae6be210ff8afbc45ff00614b9ffd1ad5157e0f9fe87565ff00ef0ffc3ffb723071462971462b94f6c4c518a5c518a005a29d8a31414368a7628c50036bd5bc5fff002366b1ff005fd3ff00e8c6af2bc57aaf8b87fc559ac7fd7f4fff00a31aba68fc2cf1b32fe343d1fe68caa2971462b43844a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a29714628012b99f885ff23f7887fec2973ffa35aba7c5735f1047fc57be21ff00b0a5cffe8d6aceafc1f3fd0ebcbffde1ff0087ff006e460d14ec518ae63dc1b453b146280168a5c518a062514b8a3140095b9ff098f8bbfe869d73ff000612ff00f155898a3154a4d6cccea51a753e38a7ea93fcd336ff00e131f177fd0d3ae7fe0c25ff00e2a8ff0084c7c5dff434eb9ff83097ff008aac4c518a7ed27ddfdeff00cccfea787ff9f71ffc063fe46dff00c263e2effa1a75cffc184bff00c551ff00098f8bbfe869d73ff0612fff00155898a3147b49f77f7bff0030fa9e1ffe7dc7ff00018ff91b7ff098f8bbfe869d73ff000612ff00f1547fc263e2effa1a75cffc184bff00c5562628c51ed27ddfdeff00cc3ea787ff009f71ff00c063fe46dffc263e2eff00a1a75cff00c184bffc551ff098f8bbfe869d73ff000612ff00f155898a3147b49f77f7bff30fa9e1ff00e7dc7ff018ff0091b7ff00098f8bbfe869d73ff0612fff001547fc263e2eff00a1a75cff00c184bffc5562628c51ed27ddfdeffcc3ea787ff9f71ffc063fe46dff00c263e2effa1a75cffc184bff00c551ff00098f8bbfe869d73ff0612fff00155898a3147b49f77f7bff0030fa9e1ffe7dc7ff00018ff91b7ff098f8bbfe869d73ff000612ff00f1547fc263e2effa1a75cffc184bff00c5562628c51ed27ddfdeff00cc3ea787ff009f71ff00c063fe46dffc263e2eff00a1a75cff00c184bffc551ff098f8bbfe869d73ff000612ff00f155898a3147b49f77f7bff30fa9e1ff00e7dc7ff018ff0091b7ff00098f8bbfe869d73ff0612fff001547fc263e2eff00a1a75cff00c184bffc5562628c51ed27ddfdeffcc3ea787ff9f71ffc063fe46dff00c263e2effa1a75cffc184bff00c551ff00098f8bbfe869d73ff0612fff00155898a3147b49f77f7bff0030fa9e1ffe7dc7ff00018ff91b7ff098f8bbfe869d73ff000612ff00f1547fc263e2effa1a75cffc184bff00c5562628c51ed27ddfdeff00cc3ea787ff009f71ff00c063fe46dffc263e2eff00a1a75cff00c184bffc551ff098f8bbfe869d73ff000612ff00f155898a3147b49f77f7bff30fa9e1ff00e7dc7ff018ff0091b7ff00098f8bbfe869d73ff0612fff001547fc263e2eff00a1a75cff00c184bffc5562628c51ed27ddfdeffcc3ea787ff9f71ffc063fe46dff00c263e2effa1a75cffc184bff00c551ff00098f8bbfe869d73ff0612fff00155898a3147b49f77f7bff0030fa9e1ffe7dc7ff00018ff91b7ff098f8bbfe869d73ff000612ff00f1547fc263e2effa1a75cffc184bff00c5562628c51ed27ddfdeff00cc3ea787ff009f71ff00c063fe46dffc263e2eff00a1a75cff00c184bffc551ff098f8bbfe869d73ff000612ff00f155898a3147b49f77f7bff30fa9e1ff00e7dc7ff018ff0091b7ff00098f8bbfe869d73ff0612fff001547fc263e2eff00a1a75cff00c184bffc5562628c51ed27ddfdeffcc3ea787ff9f71ffc063fe46dff00c263e2effa1a75cffc184bff00c551ff00098f8bbfe869d73ff0612fff00155898a3147b49f77f7bff0030fa9e1ffe7dc7ff00018ff91b7ff098f8bbfe869d73ff000612ff00f1547fc263e2effa1a75cffc184bff00c5562628c51ed27ddfdeff00cc3ea787ff009f71ff00c063fe46dffc263e2eff00a1a75cff00c184bffc551ff098f8bbfe869d73ff000612ff00f155898a3147b49f77f7bff30fa9e1ff00e7dc7ff018ff0091b7ff00098f8bbfe869d73ff0612fff001547fc263e2eff00a1a75cff00c184bffc5562628c51ed27ddfdeffcc3ea787ff9f71ffc063fe46dff00c263e2effa1a75cffc184bff00c551ff00098f8bbfe869d73ff0612fff00155898a3147b49f77f7bff0030fa9e1ffe7dc7ff00018ff91b7ff098f8bbfe869d73ff000612ff00f1547fc263e2effa1a75cffc184bff00c5562628c51ed27ddfdeff00cc3ea787ff009f71ff00c063fe46dffc263e2eff00a1a75cff00c184bffc551ff098f8bbfe869d73ff000612ff00f155898a3147b49f77f7bff30fa9e1ff00e7dc7ff018ff0091b7ff00098f8bbfe869d73ff0612fff001547fc263e2eff00a1a75cff00c184bffc5562628c51ed27ddfdeffcc3ea787ff9f71ffc063fe46dff00c263e2effa1a75cffc184bff00c551ff00098f8bbfe869d73ff0612fff00155898a3147b49f77f7bff0030fa9e1ffe7dc7ff00018ff91b7ff098f8bbfe869d73ff000612ff00f1547fc263e2effa1a75cffc184bff00c5562628c51ed27ddfdeff00cc3ea787ff009f71ff00c063fe46dffc263e2eff00a1a75cff00c184bffc551ff098f8bbfe869d73ff000612ff00f155898a3147b49f77f7bff30fa9e1ff00e7dc7ff018ff0091b7ff00098f8bbfe869d73ff0612fff001547fc263e2eff00a1a75cff00c184bffc5562628c51ed27ddfdeffcc3ea787ff9f71ffc063fe46dff00c263e2effa1a75cffc184bff00c551ff00098f8bbfe869d73ff0612fff00155898a3147b49f77f7bff0030fa9e1ffe7dc7ff00018ff91b7ff098f8bbfe869d73ff000612ff00f1547fc263e2effa1a75cffc184bff00c5562628c51ed27ddfdeff00cc3ea787ff009f71ff00c063fe46dffc263e2eff00a1a75cff00c184bffc551ff098f8bbfe869d73ff000612ff00f155898a3147b49f77f7bff30fa9e1ff00e7dc7ff018ff0091b7ff00098f8bbfe869d73ff0612fff001547fc263e2eff00a1a75cff00c184bffc5562628c51ed27ddfdeffcc3ea787ff9f71ffc063fe46dff00c263e2effa1a75cffc184bff00c551ff00098f8bbfe869d73ff0612fff00155898a3147b49f77f7bff0030fa9e1ffe7dc7ff00018ff91b7ff098f8bbfe869d73ff000612ff00f1547fc263e2effa1a75cffc184bff00c5562628c51ed27ddfdeff00cc3ea787ff009f71ff00c063fe46dffc263e2eff00a1a75cff00c184bffc551ff098f8bbfe869d73ff000612ff00f155898a3147b49f77f7bff30fa9e1ff00e7dc7ff018ff0091b7ff00098f8bbfe869d73ff0612fff001547fc263e2eff00a1a75cff00c184bffc5562628c51ed27ddfdeffcc3ea787ff9f71ffc063fe46dff00c263e2effa1a75cffc184bff00c551ff00098f8bbfe869d73ff0612fff00155898a3147b49f77f7bff0030fa9e1ffe7dc7ff00018ff91b7ff098f8bbfe869d73ff000612ff00f1547fc263e2effa1a75cffc184bff00c5562628c51ed27ddfdeff00cc3ea787ff009f71ff00c063fe46dffc263e2eff00a1a75cff00c184bffc551ff098f8bbfe869d73ff000612ff00f155898a3147b49f77f7bff30fa9e1ff00e7dc7ff018ff0091b7ff00098f8bbfe869d73ff0612fff001547fc263e2eff00a1a75cff00c184bffc5562628c51ed27ddfdeffcc3ea787ff9f71ffc063fe46dff00c263e2effa1a75cffc184bff00c551ff00098f8bbfe869d73ff0612fff00155898a3147b49f77f7bff0030fa9e1ffe7dc7ff00018ff91b7ff098f8bbfe869d73ff000612ff00f1547fc263e2effa1a75cffc184bff00c5562628c51ed27ddfdeff00cc3ea787ff009f71ff00c063fe46dffc263e2eff00a1a75cff00c184bffc551ff098f8bbfe869d73ff000612ff00f155898a3147b49f77f7bff30fa9e1ff00e7dc7ff018ff0091b7ff00098f8bbfe869d73ff0612fff001547fc263e2eff00a1a75cff00c184bffc5562628c51ed27ddfdeffcc3ea787ff9f71ffc063fe46dff00c263e2effa1a75cffc184bff00c551ff00098f8bbfe869d73ff0612fff00155898a3147b49f77f7bff0030fa9e1ffe7dc7ff00018ff91b7ff098f8bbfe869d73ff000612ff00f1547fc263e2effa1a75cffc184bff00c5562628c51ed27ddfdeff00cc3ea787ff009f71ff00c063fe46dffc263e2eff00a1a75cff00c184bffc551ff098f8bbfe869d73ff000612ff00f155898a3147b49f77f7bff30fa9e1ff00e7dc7ff018ff0091b7ff00098f8bbfe869d73ff0612fff001547fc263e2eff00a1a75cff00c184bffc5562628c51ed27ddfdeffcc3ea787ff9f71ffc063fe46dff00c263e2effa1a75cffc184bff00c551ff00098f8bbfe869d73ff0612fff00155898a3147b49f77f7bff0030fa9e1ffe7dc7ff00018ff91b7ff098f8bbfe869d73ff000612ff00f1547fc263e2effa1a75cffc184bff00c5562628c51ed27ddfdeff00cc3ea787ff009f71ff00c063fe46dffc263e2eff00a1a75cff00c184bffc551ff098f8bbfe869d73ff000612ff00f155898a3147b49f77f7bff30fa9e1ff00e7dc7ff018ff0091b7ff00098f8bbfe869d73ff0612fff001547fc263e2eff00a1a75cff00c184bffc5562628c51ed27ddfdeffcc3ea787ff9f71ffc063fe46dff00c263e2effa1a75cffc184bff00c551ff00098f8bbfe869d73ff0612fff00155898a3147b49f77f7bff0030fa9e1ffe7dc7ff00018ff91b7ff098f8bbfe869d73ff000612ff00f1558f7134d717125c5c4b24d34ae5e491d8b33b1392493c924f7a6628c527293dd9a53a14a9bbc2297a24bf2484a2971462a4d04a2971462801d8a314ea29143714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d45003714629d4500145145030a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2803fffd9, '2026-11-10 22:58:54');
INSERT INTO `concern_attachments` (`attachment_id`, `concern_id`, `original_name`, `mime_type`, `file_size`, `sha256`, `file_data`, `uploaded_at`) VALUES
(2, 5, 'demo_broken_chairs.jpg', 'image/jpeg', 44625, '8244cbd1c62c9fa4e75bd205cca41402cbdd701dbf78f8a40d440e8d3cdb0d41', 0xffd8ffe000104a46494600010101006000600000fffe003b43524541544f523a2067642d6a7065672076312e3020287573696e6720494a47204a50454720763930292c207175616c697479203d2038350affdb0043000503040404030504040405050506070c08070707070f0b0b090c110f1212110f111113161c1713141a1511111821181a1d1d1f1f1f13172224221e241c1e1f1effdb0043010505050706070e08080e1e1411141e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1effc0001108025803c003012200021101031101ffc4001f0000010501010101010100000000000000000102030405060708090a0bffc400b5100002010303020403050504040000017d01020300041105122131410613516107227114328191a1082342b1c11552d1f02433627282090a161718191a25262728292a3435363738393a434445464748494a535455565758595a636465666768696a737475767778797a838485868788898a92939495969798999aa2a3a4a5a6a7a8a9aab2b3b4b5b6b7b8b9bac2c3c4c5c6c7c8c9cad2d3d4d5d6d7d8d9dae1e2e3e4e5e6e7e8e9eaf1f2f3f4f5f6f7f8f9faffc4001f0100030101010101010101010000000000000102030405060708090a0bffc400b51100020102040403040705040400010277000102031104052131061241510761711322328108144291a1b1c109233352f0156272d10a162434e125f11718191a262728292a35363738393a434445464748494a535455565758595a636465666768696a737475767778797a82838485868788898a92939495969798999aa2a3a4a5a6a7a8a9aab2b3b4b5b6b7b8b9bac2c3c4c5c6c7c8c9cad2d3d4d5d6d7d8d9dae2e3e4e5e6e7e8e9eaf2f3f4f5f6f7f8f9faffda000c03010002110311003f00f11a2971462bf483e744a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a2971462801d8f6a31ed4ec518a04371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400dc7b518f6a7628c500371ed463da9d8a31400ec518a7e28c530198a314fc518a006628c53f15f4578dbc51e27b7f19eb96f6fe23d5e1862d46e1238d2f6455451230000070001dab9ab5674da495ee6908292bb3e72c518af76ff0084bbc59ff4346b7ff81f2fff001547fc25de2cff00a1a35bff00c0f97ff8aacbeb53fe55f7bff22bd947b9e138a315eedff09778b3fe868d6fff0003e5ff00e2a8ff0084bbc59ff4346b7ff81f2fff001547d6a7fcabef7fe41eca3dcf09c518af76ff0084bbc59ff4346b7ff81f2fff001547fc25de2cff00a1a35bff00c0f97ff8aa3eb53fe55f7bff0020f651ee784e28c57bb7fc25de2cff00a1a35bff00c0f97ff8aa3fe12ef167fd0d1adffe07cbff00c551f5a9ff002afbdff907b28f73c271462bddbfe12ef167fd0d1adffe07cbff00c551ff0009778b3fe868d6ff00f03e5ffe2a8fad4ff957deff00c83d947b9e138a315eedff0009778b3fe868d6ff00f03e5ffe2a8ff84bbc59ff004346b7ff0081f2ff00f1547d6a7fcabef7fe41eca3dcf09c518af76ff84bbc59ff004346b7ff0081f2ff00f1547fc25de2cffa1a35bffc0f97ff008aa3eb53fe55f7bff20f651ee784e28c57bb7fc25de2cffa1a35bffc0f97ff008aa3fe12ef167fd0d1adff00e07cbffc551f5a9ff2afbdff00907b28f73c271462bddbfe12ef167fd0d1adff00e07cbffc551ff09778b3fe868d6fff0003e5ff00e2a8fad4ff00957deffc83d947b9e138a315eedff09778b3fe868d6fff0003e5ff00e2a8ff0084bbc59ff4346b7ff81f2fff001547d6a7fcabef7fe41eca3dcf09c518af76ff0084bbc59ff4346b7ff81f2fff001547fc25de2cff00a1a35bff00c0f97ff8aa3eb53fe55f7bff0020f651ee784e28c57bb7fc25de2cff00a1a35bff00c0f97ff8aa3fe12ef167fd0d1adffe07cbff00c551f5a9ff002afbdff907b28f73c271462bddbfe12ef167fd0d1adffe07cbff00c551ff0009778b3fe868d6ff00f03e5ffe2a8fad4ff957deff00c83d947b9e138a315eedff0009778b3fe868d6ff00f03e5ffe2a8ff84bbc59ff004346b7ff0081f2ff00f1547d6a7fcabef7fe41eca3dcf09c518af76ff84bbc59ff004346b7ff0081f2ff00f1547fc25de2cffa1a35bffc0f97ff008aa3eb53fe55f7bff20f651ee784e28c57bb7fc25de2cffa1a35bffc0f97ff008aa3fe12ef167fd0d1adff00e07cbffc551f5a9ff2afbdff00907b28f73c271462bddbfe12ef167fd0d1adff00e07cbffc551ff09778b3fe868d6fff0003e5ff00e2a8fad4ff00957deffc83d947b9e138a315eedff09778b3fe868d6fff0003e5ff00e2a8ff0084bbc59ff4346b7ff81f2fff001547d6a7fcabef7fe41eca3dcf09c518af76ff0084bbc59ff4346b7ff81f2fff001547fc25de2cff00a1a35bff00c0f97ff8aa3eb53fe55f7bff0020f651ee784e28c57bb7fc25de2cff00a1a35bff00c0f97ff8aa3fe12ef167fd0d1adffe07cbff00c551f5a9ff002afbdff907b28f73c271462bddbfe12ef167fd0d1adffe07cbff00c551ff0009778b3fe868d6ff00f03e5ffe2a8fad4ff957deff00c83d947b9e138a315eedff0009778b3fe868d6ff00f03e5ffe2a8ff84bbc59ff004346b7ff0081f2ff00f1547d6a7fcabef7fe41eca3dcf09c518af76ff84bbc59ff004346b7ff0081f2ff00f1547fc25de2cffa1a35bffc0f97ff008aa3eb53fe55f7bff20f651ee784e28c57bb7fc25de2cffa1a35bffc0f97ff008aa3fe12ef167fd0d1adff00e07cbffc551f5a9ff2afbdff00907b28f73c271462bddbfe12ef167fd0d1adff00e07cbffc551ff09778b3fe868d6fff0003e5ff00e2a8fad4ff00957deffc83d947b9e138a315eedff09778b3fe868d6fff0003e5ff00e2a8ff0084bbc59ff4346b7ff81f2fff001547d6a7fcabef7fe41eca3dcf09c518af76ff0084bbc59ff4346b7ff81f2fff001547fc25de2cff00a1a35bff00c0f97ff8aa3eb53fe55f7bff0020f651ee784e28c57bb7fc25de2cff00a1a35bff00c0f97ff8aa3fe12ef167fd0d1adffe07cbff00c551f5a9ff002afbdff907b28f73c271462bddbfe12ef167fd0d1adffe07cbff00c551ff0009778b3fe868d6ff00f03e5ffe2a8fad4ff957deff00c83d947b9e138a315eedff0009778b3fe868d6ff00f03e5ffe2a8ff84bbc59ff004346b7ff0081f2ff00f1547d6a7fcabef7fe41eca3dcf09c518af76ff84bbc59ff004346b7ff0081f2ff00f1547fc25de2cffa1a35bffc0f97ff008aa3eb53fe55f7bff20f651ee784e28c57bb7fc25de2cffa1a35bffc0f97ff008aa3fe12ef167fd0d1adff00e07cbffc551f5a9ff2afbdff00907b28f73c271462bddbfe12ef167fd0d1adff00e07cbffc551ff09778b3fe868d6fff0003e5ff00e2a8fad4ff00957deffc83d947b9e138a315eedff09778b3fe868d6fff0003e5ff00e2a8ff0084bbc59ff4346b7ff81f2fff001547d6a7fcabef7fe41eca3dcf09c518af76ff0084bbc59ff4346b7ff81f2fff001547fc25de2cff00a1a35bff00c0f97ff8aa3eb53fe55f7bff0020f651ee784e28c57bb7fc25de2cff00a1a35bff00c0f97ff8aa3fe12ef167fd0d1adffe07cbff00c551f5a9ff002afbdff907b28f73c271462bddbfe12ef167fd0d1adffe07cbff00c551ff0009778b3fe868d6ff00f03e5ffe2a8fad4ff957deff00c83d947b9e138a315eedff0009778b3fe868d6ff00f03e5ffe2a8ff84bbc59ff004346b7ff0081f2ff00f1547d6a7fcabef7fe41eca3dcf09c518af76ff84bbc59ff004346b7ff0081f2ff00f1547fc25de2cffa1a35bffc0f97ff008aa3eb53fe55f7bff20f651ee784e28c57bb7fc25de2cffa1a35bffc0f97ff008aa3fe12ef167fd0d1adff00e07cbffc551f5a9ff2afbdff00907b28f73c271462bddbfe12ef167fd0d1adff00e07cbffc551ff09778b3fe868d6fff0003e5ff00e2a8fad4ff00957deffc83d947b9e138a315eedff09778b3fe868d6fff0003e5ff00e2a8ff0084bbc59ff4346b7ff81f2fff001547d6a7fcabef7fe41eca3dcf09c518af76ff0084bbc59ff4346b7ff81f2fff001547fc25de2cff00a1a35bff00c0f97ff8aa3eb53fe55f7bff0020f651ee784e28c57bb7fc25de2cff00a1a35bff00c0f97ff8aa3fe12ef167fd0d1adffe07cbff00c551f5a9ff002afbdff907b28f73c271462bddbfe12ef167fd0d1adffe07cbff00c551ff0009778b3fe868d6ff00f03e5ffe2a8fad4ff957deff00c83d947b9e138a315eedff0009778b3fe868d6ff00f03e5ffe2a8ff84bbc59ff004346b7ff0081f2ff00f1547d6a7fcabef7fe41eca3dcf09c518af76ff84bbc59ff004346b7ff0081f2ff00f1547fc25de2cffa1a35bffc0f97ff008aa3eb53fe55f7bff20f651ee784e28c57bb7fc25de2cffa1a35bffc0f97ff008aa3fe12ef167fd0d1adff00e07cbffc551f5a9ff2afbdff00907b28f73c271462bddbfe12ef167fd0d1adff00e07cbffc551ff09778b3fe868d6fff0003e5ff00e2a8fad4ff00957deffc83d947b9e138a315eedff09778b3fe868d6fff0003e5ff00e2a8ff0084bbc59ff4346b7ff81f2fff001547d6a7fcabef7fe41eca3dcf09c518af76ff0084bbc59ff4346b7ff81f2fff001547fc25de2cff00a1a35bff00c0f97ff8aa3eb53fe55f7bff0020f651ee784e28c57bb7fc25de2cff00a1a35bff00c0f97ff8aa3fe12ef167fd0d1adffe07cbff00c551f5a9ff002afbdff907b28f73c271462bddbfe12ef167fd0d1adffe07cbff00c551ff0009778b3fe868d6ff00f03e5ffe2a8fad4ff957deff00c83d947b9e138a315eedff0009778b3fe868d6ff00f03e5ffe2a8ff84bbc59ff004346b7ff0081f2ff00f1547d6a7fcabef7fe41eca3dcf09c518af76ff84bbc59ff004346b7ff0081f2ff00f155e65f1607fc5d3f16ff00d86ef3ff0047bd6946bba92e56ac4ce0a2ae99cb628c53f1462ba8cc6628c53f1462801d8a314ea2810dc518a751400dc57b978fbfe47af107fd84ee7ff46b5787d7b8f8fbfe47ad7ffec2773ffa35ab8715f1c7d1fe68de97c2cc3a2968ac0b128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a280128a5a28012b8df8ae3fe2e978b3fec3779ffa3debb3ae3be2bffc952f167fd86ef3ff0047bd6d86fe2bf4fd48a9f09cc628c53a8aef301b8a314ea2801d452e3da8c7b53109452e3da8c7b500257b878f7fe47ad7ff00ec2773ff00a35abc431ed5ee3e3dff0091e75fff00b09dcffe8d6ae1c57c71f47f9a37a5f0b30e8a5a2b9ec682514b45160128a5a28b009452d145804a2968a2c02514b45160128a5a28b009452d145804a2968a2c02514b45160128a5a28b009452d145804a2968a2c02514b45160128a5a28b009452d145804a2968a2c02514b45160128a5a28b009452d145804a2968a2c02514b45160128a5a28b009452d145804a2968a2c02514b45160128a5a28b009452d145804a2968a2c02514b45160128a5a28b009452d145804a2968a2c02514b45160128a5a28b009452d145804a2968a2c02514b45160128a5a28b009452d145804a2968a2c02514b45160128a5a28b009452d145804a2968a2c02514b45160128a5a28b009452d145804a2968a2c02514b45160128a5a28b009452d145804a2968a2c02514b4516012b8ef8adff2547c59ff0061bbcffd1ef5d9571df1587fc5d1f1671ff31abcff00d1ef5b61bf8afd3f522afc3f3399a2971ed463dabd039c4a2971ed463da801d8a314fc518a043314629f8a31400cc57b7f8f07fc573aff00fd84ee7ff46b5789e2bdb7c79ff23cebff00f613b9ff00d1ad5c38af8e3e8ff346f4be1662628c52d1581a098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b4500262b8ff8aa3fe2e878affec3579ffa3debb1ae43e2a0ff008b9fe2bffb0d5e7fe8f7adb0dfc5f97ea455f87e67318a314fc518aef39c6628c53f1462801d8a314ec518a043714629d8a31400dc57b6f8f07fc571af7fd84ae3ff0046b578a62bdb3c77ff0023c6bdff00612b8ffd1ad5c38af8e3e8ff00347452f859898a314b4560682628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098ae43e2a0ff008b9fe2affb0d5e7fe8f7aec2b91f8a63fe2e778abfec3579ff00a39eb6c37f17e5fa99d5f87e67338a314ec518aef39c6e28c53b1462801714629d8a314c43714629d8a31400dc57b6f8ec7fc56faf7fd84ae3ff0046b578a62bdb3c75ff0023bebdff00612b8ffd1ad5c38bf8e3e8ff00347451f859898f6a31ed4ea2b9cd2e371ed463da9d450171b8f6a31ed4ea280b8dc7b518f6a751405c6e3da8c7b53a8a02e371ed463da9d450171b8f6a31ed4ea280b8dc7b518f6a751405c6e3da8c7b53a8a02e371ed463da9d450171b8f6a31ed4ea280b8dc7b518f6a751405c6e3da8c7b53a8a02e371ed463da9d450171b8f6a31ed4ea280b8dc7b518f6a751405c6e3da8c7b53a8a02e371ed463da9d450171b8f6a31ed4ea280b8dc7b518f6a751405c6e3da8c7b53a8a02e371ed463da9d450171b8f6a31ed4ea280b8dc7b518f6a751405c6e3da8c7b53a8a02e371ed463da9d450171b8f6a31ed4ea280b8dc7b518f6a751405c6e3da8c7b53a8a02e371ed463da9d450171b8f6a31ed4ea280b8dc7b518f6a751405c6e3da8c7b53a8a02e371ed463da9d450171b8f6a31ed4ea280b8dc7b518f6a751405c6e3da8c7b53a8a02e371ed463da9d450171b8f6a31ed4ea280b8dc7b518f6a751405c6e3da8c7b53a8a02e371ed463da9d450171b8f6a31ed4ea280b8dc7b518f6a751405c6e3da8c7b53a8a02e371ed463da9d450171b8f6a31ed4ea280b8dc7b518f6a751405c6e3da8c7b53a8a02e371ed463da9d450171b8f6a31ed4ea280b8dc7b518f6a751405c6e3da8c7b53a8a02e371ed463da9d450171b8f6ae43e298ff8b9de2aff00b0d5e7fe8e7aec6b90f8a43fe2e6f8abfec3379ffa39eb6c37f15fa7ea6757e1f99cce28c53b1462bd039c6e28c53b1462801f8f6a31ed4ec518a04371ed463da9d8a31400dc7b57b578ebfe476d77fec2571ffa35abc5f15ed3e39ff91db5dffb095c7fe8c6ae1c5fc71f47f9a3a28fc2cc5a29d45739a0da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d4500368a751400da29d450036b91f8a23fe2e6f8a78ff98cde7fe8e7aec2b91f8a23fe2e678a7fec3377ff00a39eb6c37f15fa7ea455f83e67358f6a31ed4ec518af40e61b8f6a31ed4ec518a007628c53b1462810dc518a7628c5003715ed3e39ff0091db5dff00b095c7fe8c6af18c57b478e47fc56baeff00d84ae3ff00463570e2be38fa3fcd1d147e1662d14b8a31581a89452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c50025725f1407fc5cbf14ff00d866efff00473d75d8ae4fe278ff008b97e28ffb0cddff00e8e7adb0dfc5f97ea6757e0f9fe87358a314ec518aef3986e28c53b1462801d8a314ec518a603714629d8a31400dc57b3f8e07fc56baeffd846e3ff463578d62bd9bc703fe2b5d73fec2371ffa31ab8715f1c7d1fe68e8a3f0b31b1462971462b9cd04c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c5725f13c7fc5caf147fd862efff00473d75d8ae4fe270ff008b95e28ffb0c5dff00e8e7adb0dfc5f97ea455f83e7fa1cde28c53b1462bd03986e28c53b1462801d8a314ec7b518f6a043714629d8f6a31ed400dc57b378dc7fc569ae7fd846e3ff463578de3dabd97c6ff00f23a6b9ff611b8ff00d18d5c38af8e3e8ff347451f8598d8a314b45739a898a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b4500262b92f89c3fe2e4f8a3fec3177ff00a39ebaeae4fe268ff8b93e27ff00b0c5dffe8e7adb0dfc57e9fa99d5f83e7fa1ce628c53b1ed463dabd03986e28c53b1ed463da801d8a314ec518a62b8dc518a7628c50171b8af65f1b0ff008acf5cff00b08dc7fe8c6af1cc57b1f8dbfe473d73fec2371ffa31ab8317f1c7d1fe68e9a3f0b31f1462968ae734131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c5727f1347fc5c8f13ffd862eff00f473d75b5ca7c4c1ff001723c4ff00f617bbff00d1cf5be1bf8afd3f533adf07cff439cc518a7628c57a0735c6e28c53b146280b8ec518a5c518a042628c52e28c500262bd8fc6dff239eb7ff611b8ff00d18d5e3b8af63f1a8ff8acb5bffb085c7fe8c6ae0c5fc71f47f9a3a68fc2cc7a2971462b9cd04a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a00edbe12f84e2f126a72ddcf7491c5a6cd048f03c02459d4b312a724607c98e87ad74be32f13f867c3be24bad1ff00e101d22e7ecfb3f7bb634ddb915ba79671f7b1d6b1be0a7892c744d4ee34fba8ae5e5d526b7860312a9556dcc3e6c9181f38e99ef5d078fbe1b6b9aff8b6f756b3bbd3a3827f2f6acb23861b6355390108ea0f7af22b497d69aaeed1b69ab5dbb1db4d3f629d35adf529f86bc63e10d575bb7b0bcf05685a6c12eedd73298b6c78524673181c90075ef5c9fc49d1f47d3753177a3eb761a8457b34d27916bb36daaee0557e563c7cc40e07ddadbff853de25ff009fed23fefec9ff00c6eb94d2f4958fc716ba1ea012655d4d2d2e02310ae04a11b078383cf3c1ade8aa2a6e74a77496ab7fcc8a9ed3954671f9ff00c31a1e02f045f78a2e98486e6c2cfc96923bb6b5678e460c176839009e4f7fe135ea9ace8be06f1169b2e8fa3dcf872dafae31e54b691c2f2aed218ed0a413c290707a66a2f891733f823c116317861fec089782251812e15964723e7ddfc5cd788e8da95ee8fa945a8e9d3f91750e763ed56c64153c3023a1358c635719fbd8cb952d97f9fe1dcb6e143dc6af7dff00e0163c4da1df683a9cf69776f72912cd24704f2c0d1acea8d8dcb9ea0f07827a8af65f851a0e877be00d36e6f345d3ae677f3774b2daa3b3625703248c9e00153788f4cb2f117c348358d620fb4df5be8cd7314bbca6d91a10c5b0a403ca838231573e0e7fc938d2bfedb7fe8e7ae7c562a5570fd9a767f89ad1a2a157c9a3c97c63f0fb58d13538ed34f82ff5889e11219e0b27daac5986de0b73c03d7bd7a37c36f87d63a6e986ef5882db5096f61864f22eac9775ab6d2597e6279f9803c0fbb57be0feb9aa6bfe19b8bcd5aebed33a5e344ade5aa614221030a00ea4d1f0ff005cd5355f1378aacefeebce82c2f3cab65f2d57cb5df28c64004f0abd73d29623118894254dbf8776ba8e952a4a4a4baec73fa268da3c9f1af5cd3e4d2ac1ece2b0578eddadd0c68db60e42e300f27f334ff883acf867c27acc3a77fc20da45e7996eb3eff2e38f19665c63cb3fddebef57341ff92f3e20ff00b07a7fe836f4cf8a3e02d5fc53afc1a869f73631451daac244eee1b70773d94f1f30a14e1eda2aacbdde55d5f6f207197b37c8b5bbeddce7f44f1a786752d66c74eff857ba445f6ab88e0df88db6ee60b9c794338cf4a87e33783adb4866f1059cb0c305cdc4702d9456c2358bf767241071c9427181f7aacf87be1578874ed7f4ed426bcd2da2b5ba8a67092c858aab827194ebc54df1dbc49637511f0cc715c8bcb4ba8e691d957cb2a6227839ce7e71dbd6b784a3f5982c3bbaebab7f999493f652f6ab5e9fd237fe2be83a1d9780352b9b3d174eb69d3cadb2c56a88cb99501c10323824578357d17f18ff00e49c6abff6c7ff0047257ceb8ad72a93951777d7fc88c6a4a6add84af65f81da368fa8f84eea7d434ab0bb956fdd03cf6e8ec17cb8ce32474e4fe75e378af73fd9fbfe44dbbffb083ffe8b8eaf33938e1db4faa270893a9a9ce6b7e34f0ce9bacdf69dff000af74897ecb71241bf11aeedac5738f28e338e954ffe160f867fe89c691f9c7ffc6aaff887e15788751d7f51d421bcd2d62baba96640f2c8182b3923384ebcd72fe30f016afe17d323d4350b9b1962926108103b96dc558f751c7ca6b3a4b093b479b57e72ff0032e6eb46eeda7a2393a2971462bd438c4a2971462803e81d2f49f0f5a7c3ab5d627f0ee97752c3a425cbefb58f74acb0863962a7938ebcf5ae0bfe160f867fe89c691f9c7ffc6abd3f4bb39751f85b6ba7c0c8b2dce8890a1724286680019c76e6bcc7fe14f7897fe7fb48ff00bfb27ff1baf070d2a2dcfdb4bae9abf3eccf4aaaa8947d9ae9d919be25f18e87aae89716167e0ad3b4d9e5dbb6e6229ba3c3027188c1e4023af7addf809a5e99a97f6d7f68e9d6779e5f91b3ed102c9b73e6671b81c6703f2ae23c61e1abef0bea71e9fa84b6f2cb24226060662bb4b30ee073f29abff0d35bd534af135959d85d79305fde5bc572be5ab798bbf18c9048e19ba63ad7a1569465866a8bdf5ddffc3f439a136aaaf6884f8af6b6d65e3fd4adaceda1b6813cadb144811573121380381c9268f8516b6d7be3fd36daf2da1b981fcddd14a81d5b1139190783c806bbbf8f3a1e9706969aec56bb751b9bc8e2966f318ee511371b73b47dc5e83b51f01b43d2e7d2df5d96d776a36d7924514de630daa625e36e769fbedd477ac7eb51fa9736bb5be7b17ec5fd62df3337e3de97a669bfd8bfd9da759d9f99e7effb3c0b1eec7978ced0338c9fceb0bc35e31d0f4ad12dec2f3c15a76a53c5bb75cca537499624673193c02075ed553e25eb7aa6abe26bdb3bfbaf3a0b0bcb88ad97cb55f2d77e3190013c2af5cf4ae5f15d1430f7a11854d7aeeffaea6552adaa3944fa2bc6563e19f0ef86eeb58ff845348b9fb3ecfdd7d9634ddb9d57aed38fbd9e95e2de35f10699aefd93fb3bc3767a2f91bf7fd9cafef776dc676a2f4c1f5eb5ed3f18ff00e49c6abff6c7ff0047257ceb8ae4cae0a70f692bb69f77dbd4db192e5972ad8f78f851a0e877be00d36e6f345d3ae677f3774b2daa3b3625703248c9e0015c5ffc2c1f0cff00d138d23f38ff00f8d57a37c1cff9271a57fdb6ff00d1cf5f3ae296169aad5aaf3df47ddf9f98eb4dc210e5edd91ec3e23f0de87e30f0cc1aaf874e9d65756966d713d95840923bbba06589b6104105594646724f1dab9ff835a345278d6f74fd734a4768ac19cdbde5b8251b7c783b5870707f235b7fb3a7fcc77feddfff006ad6be83ff0025e7c41ff60f4ffd06dea275274d55a17ba4ae9f5e9fe6546119f254b6effcca7f10759f0cf84f59874eff00841b48bcf32dd67dfe5c71e32ccb8c7967fbbd7deb9cff008583e19ffa271a47e71fff001aaeb3e28f80b57f14ebf06a1a7dcd8c51476ab0913bb86dc1dcf653c7cc2b8bd53e1578874ed32eb509ef34b68ada1799c24b2162aaa49c653af155867859538f3cbdef57fe62aaab293e55a7a2393f12ea16daaeb7717f67a6c3a6c12eddb6d1636c78500e3000e4827a77afa03c4be07d0f55d12e2c2cf4fd3b4d9e5dbb6e62b24dd1e18138c60f2011d7bd7cdf8afa9fc4bac5b681a25c6ad791cd24106ddcb10058ee60a300903a91de9e64e7074d53dfa7e1f7fcc585e5929b91f35f89b43bed07539ed2eedee52259a48e09e581a359d51b1b973d41e0f04f515ecbf0a341d0ef7c01a6dcde68ba75ccefe6ee965b54766c4ae0649193c002aa7c57d1ae7c59e19d375fd3a4862b5b5b396f1d27255ca3223800004670a7be33deb77e0e7fc938d2bfedb7fe8e7acf15897570aa57b34ecedf32e8d250acd74b1e29e0af0b5f789b5486de38ee60b3767492f56dda48e36542d8278193c0ea3ef0af42f13ea7e10f07d969ba747a0685afdd24660ba75112ba3c6154971b5882c49e0f3907ad697ecfdff226ddff00d841ff00f45c75e47e351ff1596b7ff610b8ff00d18d5d37789c4ca9c9d947a2ea65654a9292dd9e8fe0ef14f82f5bd4e4b4d43c27e1fd1e2484c8279cc3b598328dbca2f3c93d7b565e93e0eb6d03e2c68fa2de4b0eab04f6f24ec25b6014fc92800a92c0e0a039ff000af35c577ff0f75bd535ff008a9a3de6ad75f699d2396256f2d530a22908185007526aaae1e5454a507eed9e9f2d2c4c2aa9b4a4b5ba3b4f88d79e19f07fd83fe28ad22f7ed7e67fcb18e3d9b36ffb0739ddfa5721ff000b07c33ff44e348fce3ffe355dd7c57f076a7e2cfecdfece9ed22fb2f9bbfcf765ceed98c614ff0074d70dff000a7bc4bff3fda47fdfd93ff8dd726165867493ab2f7bd5ff0099b565554df22d3d11c9f8c758b1d6f538eef4fd12db478921119820dbb598331ddc2af3c81d3b562d2e28c57b708a845451c126e4eec4a2971462988f50f809a5e99a97f6d7f68e9d6779e5f91b3ed102c9b73e6671b81c6703f2ab7e32f13f867c3be24bad1ffe101d22e7ecfb3f7bb634ddb915ba79671f7b1d697f674ff98eff00dbbffed5a9bc7df0db5cd7fc5b7bab59dde9d1c13f97b56591c30db1aa9c80847507bd78b5254feb935565656eed76ec7a1152f611705afc883c1be27f0cf88bc496ba3ffc203a45b7da37fef76c6fb76a3374f2c67eee3ad6778afc1d6da178ff004393cd86e2d755d5777d97ecc11224f353f778c90c30f8e806074ad8f00fc36d7340f16d96ad7977a749041e66e58a472c7746ca300a01d48ef55bc6de24b1d6fe22f8734fb58ae525d2f57f26732aa8566f3a31f2e09c8f90f5c76a2325eddaa0ef1e577d5befdc4d3f67fbc5adf4fc0b7f1c746d1f4ef09dacfa7e9561692b5fa21782dd118af9721c640e9c0fcabc6abe8ef8a3e1bbef14e81069fa7cb6d14b1dd2cc4cecc1768471d81e7e615e6fff000a7bc4bff3fda47fdfd93ff8dd5e03174a146d527addee4e268ce552f15a1d9ff60e87ff000a9fedbfd8ba77dabfb0bcdf3beca9bf7f919ddbb19ce79cf5cd798f80bc117de28ba6121b9b0b3f25a48eedad59e3918305da0e402793dff84d7b4ea9672e9df0b6eb4f9d91a5b6d11e17284952cb010719edc556f839ff0024e34aff00b6dffa39eb969e2a74a8ce5177bcadf99b4a8c6738a7d8e4fc63e29f05e89a9c769a7f84fc3fac44f0890cf01876ab1661b7846e7807af7a7f88fc37a1f8c3c3306abe1d3a7595d5a59b5c4f65610248eeee819626d8410415651919c93c76af1ec57ae7ece9ff0031dffb77ff00dab5d988a1f56a4aa424ef1fc7f130a753dacf924b47f81c1786efa2f0beb939d73c3897ede4f966d2f10218d89560d86538381e9d1abbcf06f89fc33e22f125ae8fff00080e916df68dff00bddb1bedda8cdd3cb19fbb8eb5c97c631ff171f55ffb63ff00a2528f8383fe2e3e95ff006dbff44bd6b5a11ab41d67bf2df77dbd48849c2a722dafd91e8bf11af3c33e0ffb07fc515a45efdafccff9631c7b366dff0060e73bbf4af36f18f8a747d6f4c8ed34ff0009d868f2a4c2433c0537328561b7845e3907af6aecbf68bff9817fdbc7fed2af23c54e5f4632a51a8ef7f57fe656266d4dc56de88f4df86de088a003c47e2829636d6933c7259ea56a112452802b1321000dcfc70795fcacff00c263e10ff849bfb2bfe10ad0becbf6cfb3fdb7316cd9bf6f9bfeaf18c7cdd718efdebb4f8c7ff24e355ffb63ff00a392be75c567858fd714aad46fb249bd3f2fc4aacfd85a113d37e24f82229c1f11f85ca5f5b5dcc91c767a6da8748d4210cc0c648237273c0e5bf3f31afa2fe0e7fc938d2bfedb7fe8e7af9d715b606aca4e74a5af2bb5ccf1104b966ba89452e28c57a07309452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a3140095ca7c4b1ff00171fc4dff617bbff00d1cf5d662b94f8963fe2e3f89bfec2f77ffa39ab7c37f15fa7ea6757e0f9fe873b8a314b8a315e81cc2628c52e28c5003f1ed463da9d8a314086e3da8c7b53b1462801b8f6af61f1a8ff008acb5bff00b084ff00fa31abc8315ec1e351ff00158eb7ff006109ff00f46357062fe38fa3fcd1d347e1663e28c53b1462b9cd6e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e371462bacf871abe91a6ea46d757d16c7508af66863f3aeb66db65dc4337cca78f9b2791f76babf12f83fc23aa6b5717f67e34d0f4d825dbb6da2116d4c280718900e4827a77ae6a98a54e7cb34eddf7fcae6b1a5cf1bc59cb7c1d1ff171f4affb6dff00a25ebd17e2affc8e5e07ff00b081ff00d190d64783bc33e1af0f788ed758ff0084f348b9fb3eff00dd6e8d376e465ebe61c7decf4acff15f8c2db5df1f6871f950dbdae95aa63ed5f680c92a79a9f3e70028c267a9ebd6b82a7efb13cf05a28be8d747dce98feee972cb76ff00c8f40f8a16de1dbad0208fc4d7f7365662e94a490292c64d8f81c2b718dddbb579c7f647c25ffa1a357ffbf6dffc66ba0f8dfac691a8f852d60b0d56c6ee55be472905c23b05f2e419c03d391f9d78e629e030f2746fccd6af4ff8742c4d58aa9b27fd7a9f486a89691fc2eba8f4f95e5b35d11d6de471f33c6203b49e072463b0aadf077fe49c695ff6dbff00473d67ff006ee87ff0aa3ec5fdb3a77dabfb0fcaf27ed29bf7f918dbb739ce78c51f0ab5dd0ecbc05a6db5e6b3a75bce9e6ee8e5b94465ccae4641391c106b8254e7ec64acfe2ede4fc8ea538fb44efd3fc8f31f07f8ef57f0be99269fa7dbd8cb149319899d1cb6e2aa3b30e3e515dcfc09bc9750d4fc4fa84ea8b2dd4d14ce1010a199a52719edcd78ee2bd3fe03ea7a6e9bfdb3fda3a8da59f99e46cf3e658f763ccce3279c647e75eb63a8c5519ca31d5dbf33870d51ba914de88e8b41ff0092f1e20ffb07a7fe8305723fb400ff008acad3fec1e9ff00a324ae8345d63488fe34eb9a849aad8a59cb62a91ced7082376c43c06ce09e0fe46b97f8df7d63a8f8aed67b0bdb7bb896c510bc12aba86f32438c83d791f9d7361a32589836becafc8daac97b196bd7f5382c518a7628c57b2705cfa1fe317fc938d57fed8ffe8e4af9db15f4578c6f7c35e21f0e5d68ff00f0956916df68d9fbdfb4c6fb76bab74dc33f771d6bcebfe15ff86bfe8a3691f947ff00c76bc6cbab468d2719dd3bf67fe477e2a0ea4d38fe6bfccf3ac57b9fecff00ff00226ddffd841fff0045c75e71e2ff000be91a2e9b1dd5878b2c75795e611986009b954ab1ddc3b71c01d3bd773f04358d234ef0a5d417faad8da4ad7cee127b84462be5c633827a707f2adb1f25570d786baae8ff00c8cf0cb92ada5d8f31f1a8ff008acb5bff00b084ff00fa31ab2315ea3ad7833c35a96b37ba8ffc2c1d222fb55c4936ccc6db77316c67cd19c67ad53ff857fe1aff00a28da47e51ff00f1dade9e2e92824efb2e8fb7a19ca84db6ff0055fe679d628c53b1462bb0c2e3714629d8a31405cfa1ff00e68dff00dcbdff00b6f5f3b62be81d2f55f0fddfc3bb5d226f116976b2cda4a5b3efb98f744c610a72a587233d38e95c1ffc2bff000d7fd146d23f28ff00f8ed78f81ab1a2e6a77577d9f9f91dd8883a9cbcbdbbaff33ceb15674bbb974ed4ed75080234b6d324c81c12a5958119c76e2babf12783f43d2f45b8bfb3f1a69da94f16ddb6d104dcf9600e31213c024f4ed5c6e2bd48548558b6b6f46bf338e51707a9eff656717c47f0069d36b8cf6ecf334c45990837234883ef6ee31fad17b6717c38f006a33686cf70c932cc05e10e373b4687eeede31fad709f0535dfb16b935b6a7acfd9f4e4b37f2a3b8b9d90ab9910fca18e01e58f1ef47c6bd77edbae436da66b3f68d39ecd3cd8edee77c2ce2473f3053827853cfb578df56a9f58f617f72f7b74ef6fe99dfeda3ecbda7dadbcce0b54bb9751d4eeb509c22cb7333cce1010a1998938cf6e6ab629d8aecbc37e0fd0f54d16defef3c69a769b3cbbb75b4a1372618819cc80f2003d3bd7b33a90a514dede8dfe470462e6f43d57e317fc938d57fed8ff00e8e4af9db15f4578c6f7c35e21f0e5d68fff000956916df68d9fbdfb4c6fb76bab74dc33f771d6bc67c67e1ed3742fb27f67788ed359f3f7eff202feeb6edc670edd727d3a579995cd421ece574dbecfb7a1d78c579732d8f65f83bff24e34affb6dff00a39ebe76c57bcfc2ad7743b2f0169b6d79ace9d6f3a79bba396e5119732b91904e47041ae2ff00e15ff86bfe8a3691f947ff00c769616a2a35aaf3df57d9f9f90eb45ce10e5eddd1affb3aff00cc77feddff00f6ad6be83ff25e3c41ff0060f4ff00d060acef11788f43f08786a0d2bc3a34ebdbabbb36b79ef6c2748dd1d102acadb012492ccc327a83cd607c1cd6228fc6979a86b7aaa2b4b62c86e2f2e002edbe3c0dcc79381f90a89d39d4556bdac9ab25d7a7f91519c60e14efb3ff0031dfb400ff008acad3fec1e9ff00a324af3ac57b47c40d1bc35e2bd661d47fe138d22cfcbb758767991c99c333673e60fef74f6ae73fe15ff86bfe8a3691f947ff00c76bab0b89a74e8c632bdd2ecffc8c6b5294aa36bf35fe679d62be89f8c5ff0024e355ff00b63ffa392bc27c49a75b697ad5c5859ea50ea5045b76dcc58daf9504e3048e0923af6af66f8abaee877be02d4adacf59d3ae277f2b6c715ca3b3625427001c9e013518cbcead1945697ff22a85a309a7fd6e617c3ef19daeafa349e14f104d0da24b6f169d65e444fbe40ca63393f3283f779200c9af4af0d68f6da06896fa4d9c9349041bb6b4a4163b98b1c9000ea4f6af99b45bcfecdd66cb51f2fcdfb2dc4736cddb776d60d8cf38ce3ad7d07e1bf1be87aa68b6f7f79a869da6cf2eedd6d2dea6e4c310339c1e4007a77ae5cc70d283bd35eebdfd4db0b594b493d57e4617ecff00ff00226ddffd841fff0045c75e47e351ff001596b7ff006109ff00f46355af05f8a2fbc35aa43711c9733d9a33b4966b70d1c7233215c91c8c8e0f43f7457a0f89f4cf08f8becf4dd463d7b43d06e9e3335d229899dde40ac439dca49520f27d4f4aebb3c36265524aea5d57430baab49456e8f1dc577ff0f743d5340f8a7a459ead6df669de396555f315f2a62900395247506ba0f07f85fc19a26a725d6a1e2bd035789e131882710ed562ca7772edcf0474ef599a578c2db5ef8ada46b579143a54105bbc2c65b80547c92904b10a064b818aaab88956528c17bb67afcb4b0a14d41a727add1a1fb457fcc0bfede3ff00695791e2bdcfe22d9f86bc5ff60ff8acf48b2fb2799ff2da3937efdbfed8c636feb5c8ff00c2bff0d7fd146d23f28fff008ed460b110a5423195efe8fbfa1588a529d46d7e6bfccf3ac518adbf17e8d63a2ea51dad86b56fabc4f0890cd005daac5986de19b9e01ebdeb1b15e9c24a71524724972bb31b8a314ec518a62b9eb5fb3aff00cc77feddff00f6ad723f1887fc5c7d57fed8ff00e894ae8fe03ea7a6e9bfdb3fda3a8da59f99e46cf3e658f763ccce3279c647e75cbfc55b9b6bdf1eea57367730dc40fe56d92270ead88901c11c1e4115e6528bfaf4ddb4b7f91d736beaf15e7fe672d8ad7f050ff8acb44ffb0841ff00a316b2b15a7e1192283c57a44f34891451df40eeee4055512292493d057a153e097a3fc99cb07ef23d7bf680ff009136d3fec209ff00a2e4af0cc57b2fc6fd6348d47c296b0586ab63772adf239482e11d82f9720ce01e9c8fcebc7315c59645c70e935d59d18b69d5d0fa1ffe68dffdcbdffb6f47c1dff9271a57fdb6ff00d1cf59ff00dbba1ffc2a8fb17f6ce9df6afec3f2bc9fb4a6fdfe4636edce739e315e65e03f1adf7862e98c82e6fecfc968e3b46ba648e362c1b7018201e0f6fe235e7c30b52b52a914b5e6bfaee754ab46138b7dbfc8e4f15eb9fb3aff00cc77feddff00f6ad3fc61e17f066b7a9c775a7f8af40d22248446608043b598331ddc3af3c81d3b53fc45e23d0fc21e1a834af0e8d3af6eaeecdade7bdb09d23747440ab2b6c04924b330c9ea0f35d788aff0059a4a9c22ef2fc3f030a74fd94f9e4f45f89c5fc621ff171f55ffb63ff00a2528f83a3fe2e3e95ff006dbff44bd7317973737b72f73797335ccef8dd24ae5d9b030324f278005745f0aae6dacbc7ba6dcde5cc36f0279bba495c22ae6270324f03920575d4a6e18670ed1b7e06319a95652f33b3fda2bfe605ff006f1ffb4abc8f15ea3f1e353d3752fec6feced46d2f3cbf3f7f9132c9b73e5e3383c6707f2af30c54e5e9ac3c53f3fcc78a69d5763e87f8c5ff0024e355ff00b63ffa392be76c57a77c37f1ac5301e1df1384beb6ba99e47bbd46e83a46a1015521c1046e4e391cb559ff00843fc23ff092ff006aff00c269a1fd97ed9f68fb1622d9b37eef2bfd66318f97a7e15cb8597d4d4a9544fba693d7f3fc4dab2f6f69c59d97c1dff9271a57fdb6ff00d1cf5f3b62bd3be2478d62841f0ef860258db5acc9225de9d7411245284b2808000373f3c9e56bccf15b60694a2e7565a733bd8cf11522f960ba0dc518a7628c57a0735c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e2b95f8943fe2e3789bfec2f77ffa39abacc572bf1287fc5c5f137fd85eebff0047356f86fe2bf4fd4ceb7c1f3fd0e771ed463da9d8a315e81ca371ed463da9d8a31400ec7b518f6a7e28c5310cc7b518f6a7e28c500331ed5ebfe34ff91c75bffb084fff00a31abc8f15ebde341ff158eb5ff6109fff0046357062fe38fa3fcd1d347e1663d14ec518ae7341b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b453b1462801b5cb7c491ff1717c4bff00617baffd1cd5d5e2b96f8903fe2e27897fec2d75ff00a39ab7c2ff0015fa7ea456f83e7fa1cee3da8c7b53f1462bd0394663da8c7b53f1462801d8a314ea2810dc518a751400dc57aef8cffe470d6bfec213ff00e8c6af24af5df198ff008ac35aff00b084ff00fa31ab8317f1c7d1fe68e9a1f0b31e8a7628c5739a8da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400da29d8a31400dae5fe240ff008b89e25ffb0b5d7fe8e6aeab15cbfc47ff009287e24ffb0b5d7fe8e6adf0bfc57e9fa99d6f83e7fa1cf628c53a8af40e51b8a314ea280168a763da8c7b500368a763da8c7b50036bd77c65ff002386b5ff006109ff00f463579263dabd73c643fe2afd67febfe7ff00d18d5c38bf8e3e8ff3474d0f85991452e28c5739a09452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002572ff11ffe4a1f893fec2d75ff00a39aba9c5731f1187fc5c2f127fd85aebff46b56d86fe2bf4fd48adf07cff439ea29d8f6a31ed5e81ca368a763da8c7b5003b14629f8f6a31ed4c9198a314fc7b518f6a00662bd6fc643fe2afd67febfe7ff00d18d5e4f8f6af5af18ff00c8ddacff00d7fcff00fa31ab8317f1c7d1fe68eaa1f0b3231ed463da9d45739a8dc7b518f6a751400dc7b57b87c50d0f44b3f02ea37169a3e9f6f3a795b648ad9159732a03820647048af11af7ff008b7ff24fb53ffb65ff00a352bcbc7c9aad4927d7fc8ecc324e9cefdbfccf9fb1ed463da9d457a8718dc7b518f6ad2f0ee9373ae6b1069568f124d3eedad292146d52c73804f407b57a3ebda2f81fc27a76989af68b7175773c3891eda672a5d02ef3cbae012dc71f957356c4c694942cdb7d11ad3a2e69cb648f26c7b574bf0bed6daf3c75a75bdddbc57103f9bba395032b62272320f07900d769e13b3f871e25d464b0b0f0fdec72c7099899a670b8040ed21e7e61595e11f0f5ef86be29e91617f2dbc92c90c93030b12b831c83b81cfca6b0a98b8ce13859c6493dfd0d6141c65196eae4ff001c74cd374efec7fecfd3ed2d3ccf3f7f910aa6ec7978ce0738c9fcebcd31ed5eedf143407d57fb3b5297ca6b0d2fcd9ef626660f245f2332a63b95461c91d473585e13b3f871e25d464b0b0f0fdec72c7099899a670b8040ed21e7e615861318a9e1d369bb6fe5af5bb34af41caab4acafb1e4d8f6a31ed5b3e2cf0f5ef86b518ec2fe5b792592113030b12b8248ee073f29ac8af56138ce2a51774ce2945c5d98dc7b518f6a751542343c39a25eeb7a8c36b6b6f70d134d1c734d1c2d22c218e37363a0ea7923a1af4cd597c31e07f0edb595c69da3ebfa8c5379732ba469361b7386604330006d1cfa8af38f0ff88b58d03cff00ec9bcfb379fb7ccfdd23eedb9c7de071d4d54d5f51bcd5b509750d426f3ae65c6f7da1738000e0003a015c756854ad51733b4176ddfaff00c066f0a91a71d17bc7a9692be18f1c7876e6cadf4ed1f40d4659bcb85512379b0bb5cb28015882370e3d0d799f88f44bdd13519ad6eadee16259a48e19a485a359829c6e5cf51d0f04f515d97c1af0f5edceaf6fe23496dc5a5acd242e858f98498b1c0c631f38efeb517c62f10d96b3a8dbd85ac57092e9b34f0cc6450158e547cb82723e43d71dab9e8c9d3c4ba54dde3d7c8d6a253a2a72d1fe67018f6a31ed4ea2bd438c6e3da8c7b53a8a006e3da8c7b53a8a006e3da8c7b53a8a006e3da8c7b53a8a00f6ef8a1a1e8967e05d46e2d347d3ede74f2b6c915b22b2e65407040c8e0915e1f8f6afa47c73a4dceb9e16bcd2ad1e249a7d9b5a52428daeac73804f407b579be9b1fc3f8f51b6f0fdfe857b2ea8b3259cf324ade534d908cc3f780ed2d93d071dbb5789976279693bde4efeb656f367a38aa3cd356b2479b63da8c7b5773f13bc1a7419df53b316f16972cc90c10891da453b3273b874cab773d451f0c6dbc27aa4e9a3eb1a5dc5cea3713398a51232c6a813760e1c73f2b763d457a5f5a83a3ed62aebcbfae8727b1929f23d19c363da8c7b57b0da7c3ed274afeddd4b5db38aeac23dd3d9c504f26f8e25dec54f2b93b768e49e9d6abf84ecfe1c78975192c2c3c3f7b1cb1c26626699c2e0103b4879f98563fda14ece518b696ed7fc397f5595d26d26cf26c7b518f6ad9f16787af7c35a8c7617f2dbc92c90898185895c12477039f94d771e0ff03e9f6de1cbad77c4d6d15edb35a25ddbac13481d10233b0206d19236f73d3ad6d53154e9c14ef74f6b75338519ca4e3d8f2ec7b518f6af59f09d9fc38f12ea325858787ef6396384cc4cd3385c0207690f3f30af3ff16787af7c35a8c7617f2dbc92c90898185895c12477039f94d14b151a9374da69f67fd31ce8b8c79af75e4765f03b4cd3751fed8fed0d3ed2efcbf23679f0abedcf999c64719c0fcab99f8a16b6d67e3ad46ded2de2b7813cadb1c4815573121380381c926bb1fd9fff00e637ff006eff00fb52b37c5de1ebdf12fc53d5ec2c25b78e58e18e626662170238c76079f9857142a72636a393d12ff2379439b0f1b2d6ff00e679de3da8c7b57a4fc4ed37c1ba0c0fa659e917116a92c293413095da351bf073b9fae15bb1ea2b80d36d24bfd46dac2164596e664850b9c282c4019c76e6bbe8d75561ed2cd2f339aa5370972eecab8f6a31ed5eb375e17f0b784fc2b6975e29d2defaeda630c925a4d260925d9782ca301401d3afe745af85fc2de2cf0adddd785b4b7b1bb598431c9773498041466e0330c152474ebf9d61fda14edcd67cb7b5fa7e7fa1afd565b5d5fb753c9b1ed463dab674dd2d63f1a5b68b7e12555d452d6708c76b0f302b60f07079e7835ea1e2cf867a6dce9d1a7872d6deceec4c0bbcd71295298391ceee73b7b76ad2b63295194632ebd7a114f0f3a89b5d0f16c7b518f6af51f0e8f869ae6b1069569e1dd41269f76d696570a36a9639c4a4f407b572ff0010bc2b3f86b510e5adfec97734a6d52376664452301b70eb861dcf43453c5c6553d9b4d3f3fe984a8b51e64eebc8e5b1ed463da9d4575188dc7b518f6a756bf84fc3d7be25d464b0b096de396384cc4ccc42e0103b03cfcc2a6738c22e527648718b93b231b1ed463dabd67c5967f0e3c35a8c7617fe1fbd92592113030cce570491de41cfca6b3f4dbaf8617fa8db5843e1cd4d65b9992142f2b050588033897a735c91c6f3479d4256f97f99bbc3d9f2b92bff005e479b63dab57c23a37f6ff886d749fb47d9bcfdff00bdd9bf6ed466e9919e98eb5d27c4ef069d0677d4ecc5bc5a5cb32430422476914ecc9cee1d32addcf515d1f81b50f004fe29b38b44d0f50b6bf6dfe54b2b92abf23673fbc3fc391d2954c5a747da534de8fe5ebfd31c285aa72cddbf538ef883e0eff844fec3ff00131fb67dabccff00963e5eddbb7fda39ceefd2b94c7b57bb7c50baf0adb7f677fc24da6dddeeef37ecfe4311b31b37670ebd7e5f5e95e37e22974a9f589e5d12da5b6b06dbe54529cb2fca339e4ff164f5a303889d6a6b9d3bf7e9bff5d03134a3093e57f23371ed463dabd47e13785740d73c397177aad87da264bb68d5bce75c284438c2b01d49ae03c4b6d0d9f88f53b4b64d90c1772c71ae49daaae40193cf415b53c4c6a549535bc4ce74a51829bea66e3da8c7b5763f09b46d375cf11dc5a6ab6df68852d1a455decb860e8339520f4268f8b3a369ba1f88eded34ab6fb3c2f68b232ef66cb17719cb127a0147d663edbd8f5b5c3d94bd9fb4e871d8f6a31ed5ea3e0ff03e9f6de1cbad77c4d6d15edb35a25ddbac13481d10233b0206d19236f73d3ad67ff65f863c5dff0012df06e9b2e9d7f1fefe496f646d8621f2951867e77329e9d8f3eb9fd769b93493b2ddf45fd7a15f579595f77d3a9d7fc50d0f44b3f02ea37169a3e9f6f3a795b648ad9159732a03820647048af0fc7b57d03f16ff00e49f6a7ff6cbff0046a5793fc3df0acfe25d44b86b7fb25a4d11ba491d959d189c85da3ae14f71d457265b59430f29cdecff00c8df174f9aaa8c574396c7b518f6af51f110f869a1eb13e9577e1dd41e6836ee68a572a7728618cca0f423b557f89de048b4b81f58d1e3b7b6d3ade1412c46576919cbedc8ce78f997b8e86bae18d84a514d35cdb5fafe2632c3c926d34edb9e6d8f6a31ed5e83f07fc3ba3ebffda9fdad67f69f23c9f2ff007ae9b776fcfdd233d0568788bc2ba07863589f57d5ec3cfd026db05b5b5bcce65494a83b8e5871f2c9fc47a8e3d096369c6aba56775f8fa0961e4e0a7d0f2ec7b518f6af59b5f0bf85bc59e15bbbaf0b696f6376b3086392ee69300828cdc066182a48e9d7f3af38fb343a6788fec9aaa79f0da5df9774b113f3aa3e1c29e0f201c74fc2ae96263579924d35d3a933a2e166f67d4bde03f0d7fc251abcba7fdb3ec7e5db99b7f95bf386518c647f7bf4a3c79e1aff00845f578b4ffb67db3ccb7136ff002b6632cc318c9feefeb5e93f0d6f7c1773aecc9e1cd22f6ceec5b317799c9529b9723991b9cededda8f8957be0bb6d7614f11e917b79766d94a3c2e4284dcd81c48bce7776ef5c5f5dabf59e5e576b6d657fcff53a7eaf0f637babdf73c5b1ed463daad6a4f6b26a372f6113c568d339811cfcca993b41e4f2063b9aaf5eaa77573858dc7b518f6a7514c06e3da8c7b53abd5ed7c13a2f86bc2b77a8f8bac93509619810d693499d8c5140c650677127e95857c4428dafab7b2ea694e94aa5edd0f26c7b518f6af49d36ebe185fea36d610f87353596e664850bcac1416200ce25e9cd67fc4ef069d0677d4ecc5bc5a5cb32430422476914ecc9cee1d32addcf51511c5c5cd5392716f6bffc3b29d06a2e49a69763b7f8a1a1e8967e05d46e2d347d3ede74f2b6c915b22b2e65407040c8e0915e1f8f6afa47c73a4dceb9e16bcd2ad1e249a7d9b5a52428daeac73804f407b579be9b1fc3f8f51b6f0fdfe857b2ea8b3259cf324ade534d908cc3f780ed2d93d071dbb579d976279693bde4efeb656f3675e2a8f34d5ac91e6d8f6a31ed5dcfc4ef069d0677d4ecc5bc5a5cb32430422476914ecc9cee1d32addcf515cc787749b9d7358834ab4789269f76d69490a36a9639c027a03dabd5a75e1529fb44f438a54e519f235a99b8f6a31ed5eb3af68be07f09e9da626bda2dc5d5dcf0e247b699ca9740bbcf2eb804b71c7e54784ecfe1c78975192c2c3c3f7b1cb1c26626699c2e0103b4879f98573fd7e3c9cea0f97be9fe66bf567cdcbccaff00d791e4d8f6ad0f0d0b6ff848f4cfb608becdf6b8bcef371b366f1bb7678c6339cd59f16787af7c35a8c7617f2dbc92c90898185895c12477039f94d687c3d1e1a9b51361e20d3ae2f25bb9a286d0c6e5550b120eec32f1cafaf435bd4ab1749ce3aab74fe919460d4d45e8fcce9fe2d0f09ffc2396ff00d84344fb4fdad777d8bcadfb363e73b39c671fa5797e3dabd47e2cf85740d0fc396f77a5587d9e67bb58d9bce76ca94738c3311d40af30ac72f71747dd6dabbdf7fd4d3149aa9aa4bd06e3da8c7b57a8f83f42f0978a3c3975069fa54b16ad6d6888f3cf338433b2300c0073c6e527a7e1daac5d785fc2de13f0ada5d78a74b7bebb698c3249693498249765e0b28c050074ebf9d278f8297259f35ed6ebf98d61a4d735d5bb9e4d8f6a31ed5eb36be17f0b78b3c2b7775e16d2dec6ed6610c725dcd26010519b80cc305491d3afe75cbf83fc1577aaf88eead257b46874bbb48ef55a471e62ef60c10819e42375c751571c6d36a4e5a72ee9ee4bc3cd3496b738ec7b518f6af51f110f869a1eb13e9577e1dd41e6836ee68a572a7728618cca0f423b51e1d1f0d35cd620d2ad3c3ba824d3eedad2cae146d52c738949e80f6a9faefbbcfc92b6fd3fcc7f57f7b97995ff00af23cbb1ed463daba9f885e159fc35a8872d6ff64bb9a536a91bb332229180db875c30ee7a1ae62baa9d48d48a947666338b83e56371ed463da9d45592371ed463da9d4500371ed5cbfc461ff170bc47ff00616baffd1ad5d5572ff1147fc5c1f11ffd856ebff46b56f85fe2bf4fd4ceb7c1f3fd0e7f14629f8f6a31ed5e81c83314629f8f6a31ed400ec518a7628c5003714629d8a31400dc57ac78c47fc55dacff00d7fcff00fa31abca715eb1e301ff001576b3ff005ff3ff00e8c6ae0c5fc71f47f9a3a687c2ff00aee64628c53b1462b9cd46e28c53b1462801b8af7ff8b5ff0024fb53ff00b65ffa352bc0b15efbe2dbcf0eebde1fb9d27fe127d2adbcfd9fbcfb4c6fb76b86e9b867a63ad795985d55a52b689ff91dd84d6135e5fe6780628c57a0ff00c209e1dffa281a57e51fff001dac7f16786b49d1f4e8eeac7c5165aac8d308cc3085dca0827770edc70074ef5d90c5d29c9455fee7fe473ca84e2aeff35fe62f803c1f75e23bd8a69a1946922478e79e29515958264000e4f52bd8f5adbbcd07e1959ddcd6971e22d552682468e45d84ed653823222c7514bf08fc596da515d0aee28a286e27799af25b808b1feec60104639280751d6a5d63c21e1dd4757bcd43fe13cd2a2fb4cef36ccc676ee62719f339c66b8ead4a9ede4aa49c63d2dd7f06744211f669c126fadffe1d1b9f0d6cbc176daecd2787357bdbcbb36cc1d26421426e5c9e635e73b7bf7a7eb9ff0025bf42ff00af06ff00d067aabe05d53c3ba1eaf2e81bf4a3f65809fedaf3234fb4ee656d99f6dd8fbc7ee7e4dd6356d2a4f8c1a35fa6a764d691d932bce27531a9c4dc16ce01e47e62b8dc66ebc9eafdd7abf4f4fc3737528aa715a6eb6f52b7ed01ff00304ffb6fff00b4ebcab15ed7e3ff00107876ebec5a6634ad47edde641f6cf3a37fb06edabe67438eb9eabf73afa66784fc35e10d1b5192eafbc51a16ab1b426310cc22daa4907772edcf0474ef5d584c4fb0c3a538bbf4d37d7d34f998d7a5ed2ab716bfcbfaf23a7f8b5ff24fb53ffb65ff00a352bc0315d578dfc617be24b90505c58da792b1bdaadc978dc862db88c019e476fe115cbe2ba72fc3ca852e596ef531c5558d59de23714629d8a315dc730dc576ff000f3c092ebc45e6a71dc43a5c90b186786540cce1c2e307271c376ed547e1f691a4ea1a89bad5758b2b18ece68a4f26e76edb9192597e661c7cb83c1fbd5e9fe219741bdd1a1d3746f17e9ba12c537981ad2e114630d95c2bae012d9fa8af37198a945fb385d3eaecf4fc0ecc3d14d73cbeed3538ff0089de3a8b5481f47d1e4b7b9d3ae21432cad13ac8ae1f760671c7cabd8f535e6f8af41ff8413c3bff0045034afca3ff00e3b5cff8bf41d3b45fb2fd83c416babf9dbf7f91b7f758db8ce19bae4fa74ad30b3a104a9d3bfdcffc88af1a927cf3fcd7f99cf628c53b1462bb8e61b8aec3c3bf10b5ad0f47834ab4b5d3de18376d696372c7731639c301d49ed5c8e28c56752942aab4d5d170a9283bc5d8efbfe16c788ffe7cb4affbf527ff001747fc2d8f11ff00cf9695ff007ea4ff00e2eb81c518ac7ea387fe45fd7ccd3eb357f9997bc45aadceb9ac4faadda42934fb772c40851b5428c6493d00ef59f8a7628c574c62a2925b18b936eec6e28c53b1462988f7df8b5ff24fb53ffb65ff00a352bc0315f4078b7c55e1d83c3f732f9fa56b1b767fa1fda636f37e71dbe6e9f7ba1e95c469be1af0c5d6a36daecde28d22da39a64bb7d35c47b630487309cb8e07ddfba3e9dabc4cbab7b0a4f9e2f7ecfb6db7e3b1e962e1ed2a2e5676df097fe49f699ff6d7ff0046bd7957c251ff0017074cff00b6bffa29eb7fe2178c63841d03c3612cadeda65912eb4fb9da8e0a12ca020000dcfcf2795ae77e18dc5b59f8e34fb9bbb88ade14f377492b8555cc4e0649e3a915a52a3354aad46be2bbb75ebfe644ea45ce105f64ebbf680ff9827fdb7ffda75e558af4bf8dda969da8ff00647d82fed6efcbf3b7f9132bedcf978ce0f19c1fcabcdb15d59726b0f14fcff331c5b4eabb1d47c251ff0017074cff00b6bffa29ebabfda03fe609ff006dff00f69d798da5c5c59dc2dc5a5c4b6f32676c9139565c8c1c11cf426bd5340f1068be2bf0ecfa66be34fb3b9b5b45821bcbe9924767642ad22ef00820a82707b8e6b2c54250af1c45ae968ffaf997424a54dd2d9b3c9315efff0016bfe49f6a7ff6cbff0046a5731e13f0d784346d464babef14685aac6d098c43308b6a9241ddcbb73c11d3bd723e37f185ef892e4141716369e4ac6f6ab725e37218b6e23006791dbf845454be2ebc1c1691d6effa5d8a8fee29cb9b76757fb3ff00fcc6ff00ed87fed4af448359d367d727d122b9dd7f6f189258b630daa76f39c60fde5e87bd798fc11d4b4ed3bfb5fedf7f6b69e6793b3cf9953763ccce3279c647e7587e3fd5a44f1f6a57da36a6e8b22c6a27b49f018796991b94f2323f315cd5b08f118b9adb4d1f4e86b4ebaa5422ff00aea606bba2ea7a1dda5a6a96df6799e3122aef56ca924672a48ea0d7a2fecfff00f31bff00b61ffb52adcbaa7877c75e1cba6d49f4ad12fcc8218a5b89239255452af905b69c1cb0e3debcef4fd46e3c35e227fecfd4259edadeec6ff22631a5da46e719c1208233ebf7bbd7649cf154654a4ad25f77cbfe1cc128d0a8a69de277de38d3fc033f8a6f25d6f5bd42dafdb679b14484aafc8b8c7eecff000e0f5aabe1d3f0d743d620d56d3c43a83cd06edab2c4c54ee52a738881e84f7abdab1f0cf8dfc3d6f7971a8691a0ea32cde64ccef1bcd85dc815892ac411b4f3e82a8f877c27e14d335882faefc61a2ea10c7bb75bca22daf9520673211c120f4ed5cb16952e49ca49ad2dfd47f53669b9f34546ddff00a64be2ff0010e8faff008bbc25fd9379f69f22fc799fba74dbba48b1f780cf435abf1dbfe451b5ff00aff4ff00d1725723abeb5a2dd78e7494b0d2f4fd32db4fd4c07b9819024e8255c392140000527a9eb5d17c66d5b4abff000bdb4363a9d95d482f558a433abb01b1c6700f4e47e742a4e1568249a4afbfabeda039a942a36cf21c57bfff00cd23ff00b80ffedbd78162bdbbfb6f45ff00855ff63fed7d3fed3fd8be5793f694dfbfc8c6ddb9ce73c62ba3338b7ececbaff91960da5cf7ec787e28c53b1462bd43886e2b63c27e20bdf0d6a325fd8456f24b2426122652570483d88e7e515938ad8f09eab65a36a325d5f68f6faac6d098c433636a9241ddcab73c11d3bd675527069abf9772e0ed24ef63bdf165e7c39f12ea31dfdff882f6396384420430b85c024f78cf3f31acdb3d07e195e5dc3696fe22d55e69e458e35d846e663803262c7535a7e2cf0d784359d463bab1f14685a546b088cc3088b6b1049ddc3af3c81d3b552d1fc21e1dd3b57b3d43fe13cd2a5fb34e936ccc6376d60719f338ce2bc984e11a768ce6b4dadffda9dd28c9cf58c5f9ff004ce7bc7fe0fbaf0e5ecb3430ca7493224704f2ca8cccc532410307a86ec3a51f0947fc5c1d33fedaff00e8a7adcf8b9e2cb6d54b685691452c36f3a4cb7915c07593f767200031c1723a9e95c8784b57fec1f105b6adf67fb4f91bff0077bf66edc857ae0e3ae7a57653f6d570ad4d7bcd3ff8073cfd9c2bae5d933befda03fe609ff6dfff0069d79562bd8fc5b1e83e35d3b49ba9bc4fa6e952470991e179924653204254e5970576e3a7e5552ea7f087853c2d691a5be85e26bb598c6ee3caf30862edb8f0e70385fcbe95cf84c47b2a31a7cadcb5d3e6fab5636af4b9ea39dd25fd7ccd2f813ff228dd7fd7fbff00e8b8ebcabc623fe2aed67febfe7ffd18d56bc1fe25bdf0eea50dc23dc4d688cccf682e0a47212a57247232383d3b0aef3c47a6f853c5769a7dfc7ae68ba1dcbc666b9453133b3c81490e7729254e7afa9e9556786c4caa495e32eaba13755a8a8c778989f0247fc55d75ff005e0fff00a323aeab5cff0092dfa17fd7837fe833d55f02ea9e1dd0f579740dfa51fb2c04ff006d7991a7da7732b6ccfb6ec7de3f73f2e77e2dead1c9e30b4bed1b535668ec9544f693e4a9df2646e53c1c1fc8d6128cabe2de964e2d7e1fd69b9a26a9d05adeccd7fda03fe609ff006dff00f69d79562bd6f40f1068be2bf0ecfa66be34fb3b9b5b45821bcbe9924767642ad22ef00820a82707b8e6b17ec1e1df077fc4cffb434af15f9bfb8fb1feefe4cfcde6757e9b71d3f8bafaef85aae843d8ca2f997e3d77d97cd99d782a92f689e8ff000fd4efbe2d7fc93ed4ff00ed97fe8d4ab1a6a5ac9f0d2da3be95e2b46d1904ee83e654f246e2383c819ec6b23e276b7a2de781f50b6b4d5f4fb899fcadb1c5728ccd89509c0073d01ae23e1f78c64d3a63a56aa1efacaf1a2b7ff49b9fdd5b47cab7cac08db86e47030b5c1470d52a61ae96b195fcf65b1d552b4635acfaab7e64ff00d95f0b3fe865d57fefdb7ff19ae9fc27e22f00f86b4e92c2c35cb89229263313341216c900768c71f28ac8f11784fc29a9eb13df5a78c345d3e1936edb78845b530a01c624039209e9deac5a5bf843c29e16bb91ee342f135dacc24456f2bcc218a2ed1cb9c0e5bf3fad6f51c2ac145ca726eda7fc1e5b68650e6849b4a2bcff00a65df849710ddf88fc5d776efbe19eed648db046e567948383cf435c27c5a1ff00170753ff00b65ffa292ba9f847ace9506a3e20bab99ecb4b8eea68e486192654551990ed5ce320640e07a5727f13ae2daf3c71a85cda5c45710bf95b6489c32b6224070471d41adb0f071c6cb4d2cbf24675649e1d6bd7fcc7fc251ff17074cffb6bff00a29ebabfda03fe609ff6dfff0069d723f0c6e2dacfc71a7dcdddc456f0a79bba495c2aae6270324f1d48ae93e376a5a76a3fd91f60bfb5bbf2fcedfe44cafb73e5e3383c6707f2aaab17f5f83b696ff3141afab4979ff9153e048ff8abaebfebc1ff00f464747c761ff1575aff00d7827fe8c92b07c0be23ff00845f579750fb1fdafcc80c3b3cdd98cb29ce707fbbfad77de3ad23c3be28d5e2d43fe133d2ad3cb8043b3cc8df386639cef1fdefd2955bd2c6aab25eedbd7f21c3dfc3f22dee78fe28c57a8f8befbc29a2f876d74cb0d3f45d5ee66b4781ef20f2b7c4e1154487018e4924f51d3ad79862bba8567563cdcb65e6735587b376bdc6e28c53b1462b7323bef8123fe2aebaff00af07ff00d191d1f1d87fc55d6bff005e09ff00a324ae26c2f6f6c263358de5c5ac85769786428c475c6476e07e55ead2ea9e1df1d7872e9b527d2b44bf32086296e248e495514abe416da7072c38f7af32bc254712abdaead6f43b29c954a2e96cf73c7f1462bd234df05f862d751b6ba9bc71a45cc70cc923c2e23db200412a7321e0f4e86a87c4dd57419a77d2346d1f4d8960992417d69b3128d9cafcabd32dea795fcba638b539a84137e7b5bef48ca541c62e527fafe47a37c5aff927da9ffdb2ff00d1a95e018af70f89dade8b79e07d42dad357d3ee267f2b6c715ca333625427001cf406bc4715cb94c5c68b4d75ff00236c734ea2b763df7e12ff00c93ed33fedaffe8d7af2af00783eebc477b14d34328d2448f1cf3c52a2b2b04c8001c9ea57b1eb5e8df0c75bd16cfc0fa7db5deafa7dbcc9e6ee8e5b94565ccae464139e84572bf08fc596da515d0aee28a286e27799af25b808b1feec60104639280751d6b9a1eda1ede54d6b7ff3d8da5ece5ecd49e96ff212f341f865677735a5c788b5549a091a3917613b594e08c88b1d45745f0d6cbc176daecd2787357bdbcbb36cc1d26421426e5c9e635e73b7bf7ac3d63c21e1dd4757bcd43fe13cd2a2fb4cef36ccc676ee62719f339c66b57c0baa787743d5e5d037e947ecb013fdb5e6469f69dccadb33edbb1f78fdcfc9d66e745a8ca5276d57ddfdd57f96a2a6946a2ba497f5e673ff001d87fc55d6bff5e09ffa324ae53c1c3fe2aed1bfebfe0ffd18b5d27c66bdb2bff145b4d63796f7518b2552f0c81d41dee7191df91f9d5af0af85f41866d2b599bc69a6c7246d0dd3db3940ca4618a1264e0f6e9f8575d2a8a9e122a5d5767e7e46138b9d76d77ff23a7f8edff228daff00d7fa7fe8b92bc5715eebe3aff8477c51a445a7ff00c257a55a797389b7f9f1be70ac318de3fbdfa579278b348b2d1f518ed6c758b7d5636844866871b549246de19b9e01ebdea32b9a8d254ddefaf47fe456362dcf9d6da1ddfecfff00f31bff00b61ffb52b94f8b43fe2e0ea7ff006cbff4525743f04752d3b4efed7fb7dfdada799e4ecf3e654dd8f3338c9e7191f9d737f13ae2daf3c71a85cda5c45710bf95b6489c32b6224070471d41a2945fd7e6eda5bfc826d7d5a2bcff00cce6315d47c3df14cfe1ad44a05b7fb25dcd10ba79119991149c95da7ae18f63d057338ae9fe1f6aba4e9da89b5d5747b2be8ef268a3f3ae76edb619219be653c7cd93c8fbb5db898a95269c6fe473526d4d59d8e9fc447e1aeb9ac4faaddf8875049a7dbb96289828daa14633113d00ef50e93e19f871ab6a1169fa7ebfaacd732e7626ddb9c024f26203a0353788bc27e14d4f589efad3c61a2e9f0c9b76dbc422da98500e31201c904f4ef53784bc39e1dd07c416dab7fc26fa55cf91bff77ba34ddb90af5f30e3ae7a5799cf18d2f72724eda2b7e1f09dbcadcfde8c6d7dff00a670de32f0d5f78775174b881a3b4966905a3bc8acd2229e09dbd0e0af503ad6162bb4f8a3e278fc43a8c76b0db2247a7cd3469324de62ce095018600c0f973d4f5ae3715e9e1a5525493a8b538aaa8a9b50d86e28c53b1462b7331b8a314ec518a006e2b99f8883fe2e0788ff00ec2b75ff00a35aba8c5733f1107fc5c0f117fd856ebff46b56f85fe2bf4fd4ceb7c1f3fd0c0c518a7628c57a0728dc518a7628c5003b14629d45310dc518a751400dc57abf8c07fc55bac7fd7fcfff00a31abcaabd5fc5e3fe2add63febfe7ff00d18d5c18bfe247d1fe68eaa1f0bfebb9918a314ec518ae635b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8ae6be218ff008aff00c45ff615baff00d1ad5d3e2b9af887ff0023ff0088bfec2b73ff00a35ab7c2ff0015fa7ea675be0f9fe860628c53a8af44e41b8a314ea280171ed463da9f8a3140ae331ed463da9f8a31405c663dabd5fc5fff002366b1ff005ff3ff00e8c6af2bc57ab78b87fc559ac7fd7f4fff00a31ab8317fc48fa3fcd1d543e17ebfe664514ec518ac0d46d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d14ec518a006d735f1087fc57de22e3fe62973ff00a35aba7c5735f1047fc57de21ffb0a5cff00e8d6adb0dfc5f97ea675be0f9fe86063da8c7b53f1462bd0392e331ed463da9f8a31405c763da8c7b53b1462810dc7b518f6a7628c500371ed5eabe2e1ff00155eafff005fd3ff00e8c6af2cc57aa78bbfe46bd5ff00ebfa6ffd18d5c18bfe247d1fe68eaa1f0bf55fa9958a314b45739b098a3153d9da5d5ece20b3b69ae2523212242ed8fa0adbf16f85dfc3a8827d52c6e26660a6089ff78a08cee20f20544aa4232506f56528369c96c8e7714629681c9c0ab244c518ab97da5ea7611ac97da75e5aa31c2b4d032027d0122aa524d495d306adb898a314b4530131462968a004c518a5a280131462968a004c518a5a28013146296add8699a95fab358e9f7774a870c61859c29f7c0a4da4aed8257d8a78a314e656562ac0ab038208e41a4a602628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b45002628c52d140098a314b5a16fa1eb7710a4f6fa3ea12c4e32ae96cecac3d41039a52928eeec349bd8cec518a73ab23b23a9565386523041f4a4a62131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a28013146296a7b1b2bcbe94c365693dd48177148632e40f5c0edc8a4da4aec16bb15f1462a5bab79ed676b7b98248264fbd1c885597ea0f351d35a8098a314b45002628c55d9b4ad4e0b3179369d791db30044cf030420f43b88c7354e9269ecc6d5b7131462968a62131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5ab363617d7eec963657374c832c21899c81ef8149b495db05aec55c518ab57fa7dfd832adf595cda971951344c85be991cd56a134d5d306adb898a314b4530131462968a004c518a5ab979a56a76702dc5e69d796f0b1016496064524f2002462936968d8d2b94b1462968a62131462968a004c518a5a280131462968a004c518a5a280131462ba86f065ec9a02eada7dddbea5909ba0b4cc9221600ed200e08cf35cc5442a42a5f95ec54a0e3b898a314b45592262b9af8823fe2bdf10ffd852e7ff46b574d5cdfc401ff0015e7887fec2973ff00a35ab7c2ff0015fa7ea655fe0f9fe860e3da8c7b53b1462bd03906e3da8c7b53b1462801d8a314b8a314c5713146297146280b898af52f170ff8aaf57ffafe9bff00463579762bd4bc5a3fe2abd5ff00ebfa6ffd18d5c18bfe247d1fe68eaa1f0bf55fa9978a314b8a315cc6c7aafc3eb2b4f0cf8764d6f5fb78ed5de4060b823cc6f2dd571f7324679e2bcc750bcbbd42e4dcdedc4971310017739240e95eade2dff4ff0085b6b158ff00a53a476eacb0fce410a320815e458af3b01efb9d596eddbd2c75e2bdd5182dac4fa6d8dcea37d15959c5e6dc4a7089b80c9c67a9e2b5754f09ebfa45b0bcd46c3c8803aa96f391b9278e03135ddf876d61f0efc399bc416081afa681246328dca0862381db826b80d37c41a95aeb2da8ab24b248cc59250593e63cf19ad215ea5694bd9dad1d35ddbfd3f12654a14d2e7dd9e87f1c7fe40561ff005f5ffb29af37d0bc3dabeb8263a5da7da0438127ef11719ce3ef11e86bd53e2a5b47789a1da4dbbcb9f528e37c1c1c37071f9d2f8ab48b9b5d0adf44d1aff4cb0b668ca4ad7929491c0208dac073df3f5af3f0b8af6542105bb6f7db73aabd0f6956527b2b1e61acf8535ed1ec8de6a361e440182eef391b93d380c4d51d3b4bbdd423ba92ce1f356d61334c77a8da83a9e4f3f857a8f826d2ef48592cb54d7342b9d3e42cec16e77c9bc80060b0031c537c09a7e9da7f8eb57b2d36413d9ada26d62e1f39da4f2383ce6ba5e3e518cefab5add5ecd69f898ac2a6e3d2ff0079e77278675b8f441ad3d962c0a07f37cd4e84e01c673d4fa51a978675bd374c4d4af6cbcab5936ed93cd46cee191c024d7abf82807f11f8a61701a25ba40a879503e6e82a2f87a04da0eb4b30122aea53850fc8002ae3ad672cc6a46eda5a5bee7fe45ac241db5deff81e37676d35dddc3696e9be699c471ae40cb13803278eb5d17fc2bff16ffd027ff2622ffe2abacf0169f6da6f812ebc4b6e9baffecd330f330c80a16db81f80ae1a1f126ab16be75a5941b92ecfb4e7cbc9041f973ef5d4abd5ab392a56b474d7ab30f6508462e77d7b7631e689e195e2906d7462ac33d08eb4dc57a87c54d26ce5f0f5af883615bc758a3609809821989c7ae4d79862b7c35755e9f3233ad49d2972898a314b8ae9be1ae8f69acf893ecb7be6796909946c6c1dc0ae3f0e6b4ab5153839bd9110839c945752ae95e0ff00116a9611df5869fe75bcb9d8fe746b9c120f0581ea0d77ff00066d67b28758b4b94f2e686e55245c838600823238ae73e2b6b17bff000904ba3c6521b5b46568844369f99149ce3af24d75bf08755b9d4747b88ae162c5a948d19570cc31d58f73ef5e4e32756a615ce56b3b7cbb7a9df878d38d7e557ba3c8af23697559a2419779d95467a92d5bdff0affc5bff00409ffc988bff008aab3e0bb3d6a4f135e4fa4c76aa544b996f118c4006191900fcdd3f5ad0d63c3dae6a9a8cb7f71e26d0165971b8477acaa3000e06df6aeba989719a8a925a75bbfc8e78514e3ccd37f87e6721aee87aa6892c716a96bf677954b20f315b207fba4d76df0f7c137d16aed3ebfa444f64f6e76798e8e3712a470093d335bbe35b5fb3fc2a114af0cf34305ba79c8770621d012addc1ae63e0c4d3c9e2a996496475166f80cc48fbe95cf3c4d4ad859cd3b5ae9ffc0ec6d1a30a75e317adedfd320f1ef83b52b7d5b50d42c34c48b4a8c075649100550a33f2e73d73dab89c5753f13a69c78db528c4b208f2836ee38ff56bdab9a8209ae2511411492c87a2a29627f015db8573f631737d17e5d4e6afcbed1a8a35b46f0a6bdac59fdb34eb0f3e0dc57779c8bc8ebc3106a6bff057896c2ce5bcbbd37cb8215dcede7c6703e81b35d8ebda678a2fb4e874ebdd63c396488cb2aa24cd13f42067e5f7ad4f869a1dce9925f8bcd52c350595500582732edc16ea0818ce6b8a78f9c60e775e9aedebb1d11c2c5c946cfd74fc8f1ac518ad3f15a85f146acaa0002f66000edf39acdc57ab1973453ee714959b42628c52e2ba8b397c062d211776bae9b811af9a6331ed2f8e7196e99cd4d49f2746fd0718f375b1cb628c575de77c3bff009f4f10fe71ff00f15593e227f0eb883fb021d46323779df6b2bcf4db8da4fbfe9510ace4edcad7aaff008254a9d95f997f5f231f1462971462b63334b41f0feadae79dfd9569f68f236f99fbc45dbbb38fbc47a1ab1a6784b5fd4bed1f62b0f37ecf31865fdf46bb5c751cb0cfe153fc397917c67a6aabb2ab4bf300783f29eb5d67c62d5aeecee22d22dbcb8ade7896772ab872db88ea3e82b86ad7aaabaa51b6aafe9dfd4e9852a6e97b495f4388d73c33ade896e971a9d97d9e391f62b79a8d938ce3e527d2a869b6373a8df456567179b7129c226e032719ea78ad8b4f166ad6da1be8e05bcb6eeae09953738dd9ce0e7debd07c13a447a77827fb534d92de2bfbb895ccb78dfba46048f4c81827f4a2b626a5085ea2576ecbb7cfb053a30ab2f736ea7087c01e2c0093a4f03fe9e22ffe2ab9db5b796eaea2b68177cb3388d17206589c0193ef5e87a5e91afe9facbea917893c3ad33962caf78cc9f31e703157bc6565a2ff00c24da26a9a7dcc325ccda8c0922452a950a08e703a74159c71b252e576775ba4f7b75b972c3271bad3d6c70b77e12d7ed6fed6c2e2c365cddeef213ce8cefda32790d81f8d26b3e14d7b47b2379a8d87910060bbbce46e4f4e03135e83f19755bab1b5b4b2804412e95f7b95f9d7695c6d3dbad637c24d56ee7d45f42b8d935a4aaf337983736ec01c13db8a50c557950f6f656ea8254292abeceeee79ee2bd6be09dedddce9f7f05c5c492c56e6358558e420c3703f2ae0fc77a55ae8be26b8d3ecbccf263542bbdb279504f3f8d76bf02ffe3db56ff7e2fe4d4f30946a611cd7906113857e57e679beb43fe2737bff005f127fe846a1b3b69aeeee1b4b74df34ce238d7206589c0193c75a9f5a1ff139bdff00af893ff4235d2fc38b5d784f7377a50b185162dcd35f23797807f85803c8aeb9d4f674b9bcba9cf1873d4b157fe15ff8b7fe813ff93117ff001558dade8fa868b76b69a9dbf913320902ef56f94923395247635d76a3e19d6afafa6bc9bc4fa12c933976097cc1413e836d751f136d53fe10db18e4d8ee2782369179c8c10707d2b8d6364a708b69dfb26adf79d2f0c9c64d26addec796685a1ea9adcb245a5dafda1e250ce3cc55c03fef115adff0affc5bff00409ffc988bff008aaecbc7a7fe111f0a5b699a3aaac5706486479065f6b027a8c73c9af38d1f5ad474bd462bfb79d9a58b3b4484b29c823919f7ad6956ad5e2e74ec974bff00c3e844e9d3a4d4677bf533e58da295e27187462ac33d08adbd2bc1fe22d52c23beb0d3fceb7973b1fce8d738241e0b03d41adaf0ae3c69e320fac468be5db1602dd7602558119ebfde35d278fb4bd5753bff00b241ace89636703068a27b8314a09519dc00f5c91f5a9ab8c9466a9e89daeefaafc370a787528b9eeba1e6baee85aa6872451ea96bf676941641e62b640ff749acec57b36976505d7871f45f136afa44d1aaa242f6b703785520f2cc3ae40af1eba454b9952339457214e73919e2b5c2e25d6bc5eebee6457a3ececd6cfef22c518a5c518aeb3035b57f0ceb7a4d8c77da8597936f230557f351b2482470093d01aceb1b49efaf22b3b58fcc9e66091ae40c93ee78af5ff89b12cfe14d2607ced92f6056c7a1461599a9f85b4cf0f78a7c39269fe7e67bcc3f98fbba6318e3debcca3987353bcbe27cd6eda1db530969fbbb2b7e279f6b9a1ea7a24d1c3aa5b7d9de45dc83cc56c8e9fc24d749f0d2d3c45677126b5a4e8eb7f13c6d6ff35c24601ca93d4e7b0fceaffc71ff0090be9fff005eedff00a1541f04ddff00e127b88cbb6cfb139db9e33bd3b539d6954c17b4696daae9bfa8a34d4313c88e6fc6d2de4fe29bd9750b516974ccbe6421c385f946391c1e30699a1f86b5ad6e0926d2ecbed11c6db5cf9a8b838cff0011157be267fc8f3a9ffbe9ff00a02d604534f10222964407a85622baa9733a31e4b2764613b2a8f9b5d59eb3e3bf05c771a15bc7e1fd1add6f04ca64319543b36b679240eb8af29d4ac6eb4dbe96caf62f2ae223874dc1b0719ea323bd7acfc619248fc2362d1c8e8c6e9012ad83feadebc81d9dd8b3b1663d493926b972c75254af277577ebf7dcdf1aa0aa592ec7ae78cbfe49059ffd7b5aff0025ae127f0378a20b67b8974bdb14685d9bed111c281927ef57a46afa95c693f0c6c6f6d9217912d6d8012a6e5e428e95e6de0cd7751d3f5f87ca90482e9d609165cb0dace33819eb58e0dd654a6e16d1bdfaff0091a62153738a95f6473b8a315dff00c63d1acb4fbeb5beb557596f5a4328c8da36840303b75ae0715e9d0acab53535d4e2ab4dd39b8b1314629715b1e0eb4d4ae75fb63a6431b4c8fc34c85a24241c6fc0381d6ae72e48b93e84c63ccd22d5b781bc51736d15c43a5ee8a540e8df68886548c83cb553d73c33ade896e971a9d97d9e391f62b79a8d938ce3e527d2bb0f1168be21d5ef124bcf11787a09214f2b64576d180013d46debcd74ba5e9af63f0def6d6eee6d6fa44b7b8612c4fe62fdd6c6091d457992c74e0a2db4eef649fe676ac2c6574935e7a7e4789e28c52e28c57aa708fb5b796eaea2b68177cb3388d17206589c0193ef5af77e12d7ed6fed6c2e2c365cddeef213ce8cefda32790d81f8d63296560ca4820e4107a57b3dadfcfa77c2cb6d56111bdd416cac8d2aeec12c01fe75c78baf528b8f2d9dddbe7d0e8c3d28d4bf374d4f3c6f00f8b154b1d2b000c9ff00488bff008aae6715b3a5f89355d3b569353825569a4dd95932c9f31c9e335d3780608bc57e2e9f51d5235596dd2399161011372b003239e38a72ab568c653ab6692e82508546a30dfccc0b0f057896fece2bcb4d37cc8265dc8de7c6323e85b359bade8fa868b76b6ba9dbf913320902ef56ca924672a48ea0d7a2f8db48d5f56d4ca2ebba15a5bdbb32c317da5a375538e1801d78ad3fb25a6a5e196b0f136aba54b344c0c4f677001d8aa36825b9ce739ae558f92b4a5669f449dd7f9f99bbc2a774ae9f776b1e3d676d35dddc3696e9be699c471ae40cb13803278eb5d17fc2bff16ffd027ff2622ffe2aad7c38b5d784f7377a50b185162dcd35f23797807f85803c8ab7a8f8675abebe9af26f13e84b24ce5d825f30504fa0db5bd5c4b8d47152492ef77f91953a29c399a6fee5f99c8eb7a3ea1a2ddada6a76fe44cc8240bbd5be5248ce5491d8d68780af2eed3c53a7c76d7124493dcc51ca14e03aee1c1f6af42f8ad02c7e04b5dde5bca92428645e73853d0fa579b78347fc55ba4ffd7e45ff00a10a2957fac61a5292eff80ea52f635924fb1d8fc74ff8ff00d2ff00eb949fcc579be2bd23e39ffc7fe97ff5ca4fe62bce314f2eff00768ff5d5938bfe3484c518a5c518aed39cd6d2bc33adea9a7bea16365e75b465833f9a8b82064f0483d0d4da5f83fc45aa58477d63a779b6f2e763f9d1ae70483c1607a835e89f099da2f01ddc8a01649a56191c708b5e6d2788b537d757580e893abab88d0111e54003e5cf4e2b8215ebd5a95211b7baff00aff873aa54a9c21194afa9997b6b359ddcd69729b2685ca48b9070c0e08c8e2bd6fe2bc6f3782b4e8a31b9deea15519ea4a362a9f8ef4fb6d53c056de25b84db7e2da16fddfca84bb2eec8fc4d6c7c43d52e74af06db4d6c9133cae911f3137000c6dc8f43c75ae4ab8875a74a496a9b5f33a2149538d44de8d23cceff00c15e25b1b396f2eb4df2e0854bc8de7c67007b06cd73f8aeb7e1ceb57f6de2282c83acb0deca914c25cb7cb9edcf1d6ba3d7fc25a4c9f10ecac889c43a824d3cc15c0c37cc7e5e3819aec78a9529b855ed74d796e737b05522a54fbdb5389d1bc29af6b167f6cd3ac3cf83715dde722f23af0c41a875df0eeafa1ac4daa5a7d9c4c488ff00788d9c633f749f515d7fc5bd56eedafe3d0adf643691aa4c360dadbb0c3923b7357be195c49e23d16fb42d502c96b044ab1b01fbc1bcb13f31cfa547d6ab4692aed2e5edd6c57b0a6e7ecd5eff0085ce1343f0d6b5adc124da5d97da238db6b9f35170719fe222bd23c77e0b8ee342b78fc3fa35bade0994c8632a8766d6cf2481d715e65acc6da6eb7a8595a4b2a4305d491afce738562067df02bd43e30c9247e11b168e474637480956c1ff0056f59e26555d6a5cb2d1bd37edd75d4ba3187b39dd6dfd687936a56375a6df4b657b17957111c3a6e0d838cf5191de9fa46997bab5f2d969f0f9d70e0954dc17200c9e4902ab3b3bb16762cc7a92724d7ad47127847e1d47ab6928a6ea7482576986e019c2838e981cd75e22bca8c6296b27a2ed739e952551b7d16a70f71e05f14dbdbc93cda5ed8e342ee7ed111c00324fdeae6f15d178335dd46c35f87ca94482e9d609165cb0dace33819eb5bbf18f46b2d3efad6fad55d65bd690ca32368da100c0edd6a635e70aca954b6bb3453a5195373874dee65fc36d7d345d6945edfc96fa7306691406652db70090013e9563e29f87db4cd5bedb6b631db69b2048e3285402fb493f2e73d8f6ae46de09ee25115bc324b21e8a8a58fe42bd5be364d0b685696e268cccb7419a30c3701b1b9c75acaafeef17071fb5a3ff0032e9fbf4249f4d8f24c518a5c518af44e41315ce7c401ff15e7883fec2973ffa35aba4c5739e3f1ff15e7883fec2973ffa35ab7c2ff15fa7ea655be0f9fe861628c52e28c57a2725c4c518a5c518a02e3f14629d8f6a31ed4c43714629d8f6a31ed400dc57a8f8b47fc555abff00d7f4dffa19af30c7b57a8f8b3fe469d5bfebfa6ffd0cd79f8bfe247d1fe68ebc3fc2fd57ea65628c53a8ae7363baf85de29b3d1d65d32f956286691a5372ce70a768017681cf4eb9ac3f18f856f3c397387669ed18854b8d8103311923193d2b06ac5cdede5ca04b9bb9e6507204921600fe35cab0ee159d483df746ceaf353e492db63aaf0578a628a03a16bca2e34b982a02efb5605193d00c9c9c77ed4d9ed7c27a4ddbea516a306b51990edb0f2da2da09e0efc9fbbf4e6b8fa287858f33945b57dd2ebfe5f2055df2a4d5edb1e93f13fc49a65fd869eda3ea292dc5bdd09414072b8070791eb8aad0eb9a2f8a740367e239e3b2bfb640b0de49990b1272cc140007dd03af7af3fa2b38e0610828a6eeb67d4a962a5293935bf43b8d1748f05d8de1b8bef125bea510423c86b678c13d8e4135378075fd0ed3c4b7f7b2451e916b2dbaa4716f6900208cf38efd6b81a2aa584e752539377d3fa5b0a35f95a718a563d4fc27e24d0ed3c41e21b8b9d4638e2bab95785886f9c0ddc8e3de99e07f11e8961a4ead0ddea11c4f35f4d246083f32b2ae0f4f6af2fa2b3965d4e57d5eb6fc0b58b9ab69b5ff0013abf04f8a4e9cbfd91a9afda34ab81e4b233ed58959be76e01278278ad2874af0326be6f9fc436d2596f66fb17d9dc0c107037673c707a76ae0a8ada7854e4e51938df7b7f5b99c6bb49292bdbb9d2f8d7c533eb728b4b7536fa74202a421f72b152407ce011c11c57318a7515b53a51a51e58ad0ce73737cd21b8abba2ea779a45f2de594cd14806d62b8f9972091c8f6aa94554a2a4acf625369dd1e81ad4de15f165ac1a95c6a30e89a8c8c4dc294699881f2a82781d003f8d6af81bc45e17d26dae6cbcd86d7ca709f68cb1fb5e063ccdb8f973e9cf5af2aa2b8a5808ca1ecdc9dba797f5e674c71528cb9d2573d0bc0be2ad3ed20bfd1af596dede679a55bb662465b002ed03f1ce6b23fb1fc2f61fe9bff092c3a9f93f3fd93eccd1f9f8fe0dd938cfae2b94a2b458451939464d5f727dbb714a4af63d37c55e24d06fbe1d9b0b29a286768a10b68a5898f0ca4ae48e7007e95c7f8135d4f0f6b9f6c7b7f39248fc96f9f6ed0594eee8738c74ac2a28a783a70a72a7ba629e22529a9f547a26ad6de10d4fc56fad5cf89a068649119ed4db3e1805031bb3df1e958efe21d234af1845a8e8da34696f69e646024e71383950f923e5e0e71cd7274510c224ad2936ad6b797c872aedbba493bdcf43f1743e18f11eaa3526f15c3684c4a862fb2b3e31ef91ebe9573c0badf85f4392eec3cf857cb0a3edff0030fb5f53f73076edce3af35e614543c0a953f66e4edf2febef29625a9f3a8ab977c4734573e21d4ae6070f14b772ba30fe252e483f9550c53a8aec8c79525d8e66eeee3714629d45500dc518a751400dc518a751401ade0abab7b1f14d85ddd4a22823932ee7a01835b1f15b54d3f57d6ed67d3ae96e234b608cca08c1dcc71cfd6b91a2b07422eb2ab7d52b1a2aad5374fa31b8aed3c15e298a280e85af28b8d2e60a80bbed581464f40327271dfb571b45556a31ab1e590a9d495377475f71e1ff0009497524b1f8c608a3672cb1fd8d8ed19e067776e956351baf0e3f8bb49b4d034f8a348efa063751c8c7cc058657691c60fbf6ae22a7b0b97b2beb7bc8954c904ab2a861c12a4119f6e2b178696ee6de8ff2f2fd4d1565d2291eb1f149f4a924d3f4fd4a2488dc89025f3127ecd8da49d83ef6781d45616937de18f0869b25d585e43ad6a464211955a165460011cee1c119fc6b99f15f89affc48d6ed7d0db466dc304f25586776339c93e95875850c0b54542a37e6ba3febc8d6ae253a8e515e8c9f52bebbd4af1aeefa769e770033b01938181d2bd13e1a6a3e1ed034d964b9d762335d846788c4c0c44678cf39ebed5e69457557c346b53f677b2f230a559d3973eecd4f1641610eb323e9da92ea114d994bac65363331f979eb8e39f7aeafc17e25d39bc2973e1ad4654b04304a8b74c4bee2e4ff00081db3eb5c0514aa61a3529a849edd7d02159c26e496e757fd99e17d2bfe261fdbb0eb5e573f61f21a2f3b3c7dfc9c6339e9daba0f883e23d1b50f095bdb69b79199d2589c42b9ca000f19239c579a5150f08a538ce526daf429621c62e31492677fa6788b4cf10e86fa578a6548ae228dcc37d292c77b13821140e831df9c556d2344f06da6a315c5ef8a6defa04cef80daba07c820721b8c1c1fc2b89a28faa5aea12714fa2b7e1d83dbdece49368debad7a2b3f10fdbfc3d6a34c8d17cb291c9bfcc1bb24e48e33c7e55d16b537857c596b06a571a8c3a26a3231370a51a66207caa09e07400fe35e7f4554b0b16d38b69aebd7e77dc51acd5d3574fa1dc5b41e0bd1349ba9a5bbb7f10dd6e5314451e038c804679f5cfe15c3ca55a56645d8a5890b9ce07a5145694a97236dc9b6fbff0056227539ac92b586e28c53a8ad883d66cfc5fe1bd5f408ff00b6638619ad9f7c56cf23365917e56c80319c9a6695e37d035bbb49b5bb28ac1ecd8496cef3349f31eb8c28f415e534579dfd994b5b37e5aede875fd76a69b7f99d2fc46f1043e20d615a0842456bba24903eef357770d8c0c67d2ba1f032f863c39a93ea0de2986e0c96e6231fd99936e4a9ce727fbb5e73456d3c245d25493697f5dcca35da9fb46aeced3c6f6be1ed4afaff005ab6f1344f3480325a8b66cb10a063767dbd2b89c53a8ada8d2f651e5bdc8a93e777b58f4bd5b5cd13c5fe19b7b5bfd4e3d1668ae03ec653312154a8e817aeefd2b135e6f0969de1f86c74f8a0d56fa44657bc52d198ce410db4e41eb8ebdab8fa2b0860e34eca32764ef6feb5349621cb5695fb9e8fe26d7f47baf86b6da6417d1c978905bab4401c82a1723a638c5703a3491c1ac594d2b6d8e3b88d998f6018126ab515a51c3c6941c53deefef26a567524a4fa1de7c5cd6b4ad6174c1a6de25cf9465dfb411b73b31d47b1ae0714ea2aa8515429a847644d5a8ea49c98dc5761f0c7c496fa05fcf05d463c8bc640f317c0882eee71839ceef6ae468a75a946b41c25b30a7374e4a48ecb52d13c2f79a8dcde7fc2670a79f2b49b3ec6c71b8938ceef7ae8ac3c53e1b3e08bcb48561d2e59209952d03b3f241039c77af2ba2b9e7825512539376b5b6e86b1c438b6e315a8dc518a7515da738dc57a4dc6bfa3b7c2c1a4adf466f7ecea9e4e0e721c1c74c74af38a2b0ad4235b96ef67734a755d3bdbaab0dc569f86f5abcd0b524bbb49182ee5f3a3040f350104ae70719f5acea2b59454d38c9684464e2ee8efb5d4f0978905bea47578346bb9017b98fca698b31c753c74c761dea0d9e0cd1742907996fe21bd697e538780aa9007b8e08cfe35c4515cab0964a3ceecba7fc1dff13675eeefcaaffd7c8eff00c17e25d39bc2973e1ad4654b04304a8b74c4bee2e4ff00081db3eb597fd99e17d2bfe261fdbb0eb5e573f61f21a2f3b3c7dfc9c6339e9dab94a29ac2a8ca4e32693dd7f5a89d76d24d5ec7a57c44f10e89a9f83e0b4d3eee3698491b792b9ca00a78c91dba5725e068f4d5d721bcd47544b15b4912640d196f3486cede3a74ebcd61514e9e1634e93a516f5bfe213aee735368f43f8a17ba06b96b15ed9eb713cf6a8552dc44c4c9b9867e638c6064d79d629d455e1e82a10e44ee89ab51d4973343714629d456e667aefc27654f025db3a6f559a525738dc362f15ca69f65e10bebe8756b9d4e0d361670cfa5989e40a178dbe6647dec67a719acfd07c5fa968ba3cba5dac168f0caccccd22316f98007a301dbd2b9daf3a9e126aa549376e67a5bb7f5f33ae75e3c90495ec757e36f14ff006827f63e96bf67d2adc792a8afb966556f95b9008e838adcf891afe8fa9f84ad2cec6fa39e749a36645041002303d47a915e71456df53a69c1afb3afafa99fd626f9afd4d2f095c4167e26d3aeae6411c314eacee7a000f5aea7e2378920975ed3f50d03510d2410b2f9918fba493c723d0d709455cf0f19d5551f456fbc98d69460e08f41d52fbc33e2ed323b9d42f21d17535902b3b2b4ccc8a0f1c6d1c939fc2925d6f45f0b6802d3c3b3c77b7f708c935e4798ca9072adb4820f523af6af3fa2b25828d945b7cbdba7f9d8bfaccb7b2bf71d7334b73732dc4ee5e595cbbb1fe262724fe75e93ab6b9a278bfc336f6b7fa9c7a2cd15c07d8ca66242a951d02f5ddfa579a515ad6c3c6a38bbd9c76229d570badd33b0d79bc25a7787e1b1d3e28355be91195ef14b4663390436d3907ae3af6ab1e0ff0014da4f66341f13aacf6071b6591c8112a28dabb5464f23ae6b87a2a1e1232872c9b6f7bf5bf9761aaf252ba56f23bcd0ec7c1da35d4ba84daf5beaad1c65a080c0f1e24041521b27d31d3bd737e2df10de7886fccf3964815898602c1845903201c0ce7158f455430ea33e793bbf3e829566e3ca9591e85f0f344fec409e29d66e7ec3028db1a3c79122ba8c3641e3af4c5727e2fd6bfe120d69f52fb37d9b722aecdfbfa0c75c0aa325ede4b00b792ee778400046d212a31d38e955e953c3b551d59bbbdbd10e756f0508ab2fd46e28c53a8aea311b8ae73c7e3fe2bbf107fd84ee7ff46b574b5ce78f87fc575e20e3fe62773ffa35ab7c2ff15fa7ea655fe0f9fe861628c53b1ed463dabd138c6e28c53b1ed463da801d8a314ec518a0571b8a314ec518a02e3715ea1e2c1ff154eadff5fd37fe866bcc715ea1e2b1ff00154eadff005fb37fe866bcfc5ff123e8ff003475e1fe17eabf532b14629d8a315ce6b71b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3714629d8a31405c6e28c53b146280b8dc518a7628c50171b8a314ec518a02e3715cef8f47fc575aff00fd84ee7ff46b57498ae73c7a3fe2b9d7ff00ec2773ff00a35ab7c2ff0015fa7ea675fe0f9fe861e28c53b1462bd138ee3714629d8a31405c7628c53b1462801b8a314ec518a006e2bd3fc563fe2a8d5bfebf66ff00d0cd79962bd37c57ff002346adff005fb37fe866b8317fc48fa3fcd1d787f85faafd4ccc518a5a2b98d44c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a280131462968a004c518a5a2801315cef8f07fc573aff00fd84ee7ff46b57475cf78f07fc571af7fd84ae3ff46b56f85fe2bf4fd4cebfc1f3fd0c3c518a7628c57a2718dc518a7628c5003b14629d8a314c91b8a314ec518a006e2bd33c543fe2a8d57febf66ffd0cd79ae2bd33c55ff233eabff5fb37fe866bcfc5ff00123e8ff347661fe07eabf532f14629d45739b0dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc518a751400dc573fe3b1ff0015c6bdff00612b8ffd1ad5d1573de3a1ff0015bebdff00612b8ffd1ad5b617f8afd3f532affc3f9fe862628c53b1462bd23886e28c53b1462801d8f6a31ed4fc518a02e331ed463da9f8a31405c663dabd2fc53ff233eabff5fb37fe866bcdf15e93e291ff00153eabff005fb37fe866bcfc5ff123e8ff003475e1fe07eabf2666d14b8a315ce6a2514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a3140095cf78e87fc56daef1ff312b8ff00d1ad5d162b9ff1c8ff008adb5dff00b095c7fe8c6adf0bfc57e9fa99d7fe1fcff43131ed463da9f8a315e89c77198f6a31ed4fc518a02e3b1462968a042628c52d140098af49f140ff008a9b55ff00afc9bff4335e6f5e93e281ff001536a9ff005f937fe866b8317fc48fa3fcd1d787f81faafc999b8a314b8a315cc6c2628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c5002628c52e28c500262b9ff001c8ff8ad75dffb08dc7fe8c6ae871581e38ff91d75cffb08dc7fe8c6adf0bfc57e9faa32affc3f9fe862e28c52d15e89c62628c52d1400ec518a7628c5021b8a314ec518a006e2bd27c503fe2a5d53febf26ff00d0cd79c62bd27c4fff00232ea9ff005f937fe866b8317fc48fa3fcd1d9877ee3f55f9333314629d45731b5c6e28c53a8a02e3714629d450171b8a314ea280b8dc518a751405c6e28c53a8a02e3714629d450171b8a314ea280b8dc518a751405c6e28c53a8a02e3714629d450171b8a314ea280b8dc518a751405c6e28c53a8a02e3714629d450171b8a314ea280b8dc518a751405c6e28c53a8a02e3714629d450171b8a314ea280b8dc518a751405c6e28c53a8a02e3714629d450171b8a314ea280b8dc518a751405c6e28c53a8a02e3714629d450171b8a314ea280b8dc518a751405c6e28c53a8a02e3714629d450171b8a314ea280b8dc518a751405c6e28c53a8a02e3714629d450171b8a314ea280b8dc518a751405c6e28c53a8a02e3714629d450171b8a314ea280b8dc518a751405c6e28c53a8a02e3714629d450171b8a314ea280b8dc518a751405c6e28c53a8a02e3714629d450171b8a314ea280b8dc518a751405c6e28c53a8a02e3714629d450171b8a314ea280b8dc518a751405c6e28c53a8a02e3715cff008e07fc569ae7fd846e3ff46357455cff008dc7fc569ae7fd846e3ff46356f85fe2bf4fd519577fbbf9fe862e28c53b1462bd13886e28c53b1462801d8a314fc7b518f6a7610cc518a7e3da8c7b5160198af47f138ff8a9354ffafc97ff004335e778f6af45f130ff008a9353ff00afc97ff4335e7e33f891f47f9a3b30ff0003f55f9333b1462971462b98d84c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c518a5c518a004c56078d87fc567ae7fd846e3ff46357418ac1f1b0ff008acf5be3fe62371ffa31ab7c2ff15fa7ea8cabff000fe7fa18b8a314fc7b518f6af4ac710cc518a7e3da8c7b5160168a7628c5310da29d8a31400daf45f137fc8c9a9ffd7e4bff00a19af3cc57a278987fc547a9ff00d7e4bffa19af3f19fc48fa3fcd1d9877ee3f55f9333a8a5c518ae636b89452e28c5017128a5c518a02e2514b8a31405c4a297146280b89452e28c5017128a5c518a02e2514b8a31405c4a297146280b89452e28c5017128a5c518a02e2514b8a31405c4a297146280b89452e28c5017128a5c518a02e2514b8a31405c4a297146280b89452e28c5017128a5c518a02e2514b8a31405c4a297146280b89452e28c5017128a5c518a02e2514b8a31405c4a297146280b89452e28c5017128a5c518a02e2514b8a31405c4a297146280b89452e28c5017128a5c518a02e2514b8a31405c4a297146280b89452e28c5017128a5c518a02e2514b8a31405c4a297146280b89452e28c5017128a5c518a02e2514b8a31405c4a297146280b89452e28c5017128a5c518a02e2514b8a31405c4a297146280b89452e28c5017128a5c518a02e2514b8a31405c4a297146280b89452e28c5017128a5c518a02e2514b8a31405c4a297146280b89452e28c501712b07c6bff2396b7ff610b8ff00d18d5bf8ac2f1a8ff8acb5bffb084fff00a31ab7c2ff0015fa7ea8cb10ff0077f3fd0c6a29d8a315e91c4368a7628c5003b14629d45003714629d45003715e89e25ff918f53ffafb97ff004335e7b5e87e251ff151ea7ff5f72ffe866bcfc67f123e8ff3475e1fe07eabf2667514b8a315cc6c2514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009452e28c5002514b8a314009585e341ff00158eb5ff006109ff00f46356f62b0fc67ff2386b5ff6109fff0046356f85fe2bf4fd519623f87f3fd0c6c518a7515e91c63714629d45003f14629d8a314123714629d8a31400dc57a17893fe462d4bfebee5ff00d0cd79fe2bd07c483fe2a2d4bfebee5ffd0cd79f8cfe247d1fe68edc37c0fd57e4ccea2971462b9cd84a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a297146280128a5c518a004a29714628012b0fc663fe2b0d6bfec213ff00e8c6addc561f8c87fc55fad7fd8427ff00d18d5be17f8afd3f546588fe1fcff432314629d8a315e89c23714629d8a31400ec7b518f6a7628c5301b8f6a31ed4ec518a006e3dab5bfe125f11ffd07f56ffc0c93fc6b2f1462a254e13f8927eaae546728fc2ec6a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3153f57a5fc8bee5fe457b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d1ff00092f88ff00e83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ff00a0feadff0081927f8d1ff092f88ffe83fab7fe0649fe35978a3147d5e97f22fb97f907b6a9fccfef7fe66a7fc24be23ffa0feadff81927f8d664d2493ccf34cef24b231677739662792493d4d2628c55469c21f0c52f44bfc8994e52f89b6371ed463da9d8a315648dc7b518f6a7628c5003b146297146281098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a3140098a314b8a31400ec518a28a0418a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451400628c51450018a31451401fffd9, '2026-11-10 22:58:54');

-- --------------------------------------------------------

--
-- Table structure for table `concern_audit_logs`
--

CREATE TABLE `concern_audit_logs` (
  `log_id` int(11) NOT NULL,
  `concern_id` int(11) NOT NULL,
  `actor_type` enum('student','admin','system') NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `old_value` varchar(255) DEFAULT NULL,
  `new_value` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `concern_audit_logs`
--

INSERT INTO `concern_audit_logs` (`log_id`, `concern_id`, `actor_type`, `actor_id`, `action`, `old_value`, `new_value`, `created_at`) VALUES
(1, 11, 'student', NULL, 'created', NULL, 'Pending', '2026-11-10 19:58:54'),
(2, 11, 'system', NULL, 'ai_prioritized', NULL, 'Critical, SLA 24h', '2026-11-10 19:58:54'),
(3, 11, 'admin', 1, 'assigned', NULL, 'Engr. Carlos Mendoza', '2026-11-10 20:13:54'),
(4, 11, 'admin', 2, 'responded', NULL, 'RAPID RESPONSE TEAM DISPATCHED: CCIS Lab 1 main circuit breaker switched off. Facilities electrical crew en route with high priority.', '2026-11-10 20:13:54'),
(5, 10, 'student', NULL, 'created', NULL, 'Pending', '2026-11-09 06:58:54'),
(6, 10, 'system', NULL, 'ai_prioritized', NULL, 'Medium, SLA 72h', '2026-11-09 06:58:54'),
(7, 5, 'student', NULL, 'created', NULL, 'Pending', '2026-11-09 16:58:54'),
(8, 5, 'system', NULL, 'ai_prioritized', NULL, 'Medium, SLA 72h', '2026-11-09 16:58:54'),
(9, 5, 'admin', 1, 'assigned', NULL, 'Admin User', '2026-11-09 20:58:54'),
(10, 5, 'admin', 1, 'responded', NULL, 'We have already endorsed this to the maintenance team. Replacement chairs are being pulled from storage.', '2026-11-09 20:58:54'),
(11, 5, 'admin', 1, 'status_changed', 'Pending', 'In Progress', '2026-11-09 20:58:54'),
(12, 4, 'student', NULL, 'created', NULL, 'Pending', '2026-11-07 14:58:54'),
(13, 4, 'system', NULL, 'ai_prioritized', NULL, 'Medium, SLA 72h', '2026-11-07 14:58:54'),
(14, 7, 'student', NULL, 'created', NULL, 'Pending', '2026-11-10 02:58:54'),
(15, 7, 'system', NULL, 'ai_prioritized', NULL, 'Medium, SLA 72h', '2026-11-10 02:58:54'),
(16, 9, 'student', NULL, 'created', NULL, 'Pending', '2026-11-08 10:58:54'),
(17, 9, 'system', NULL, 'ai_prioritized', NULL, 'Medium, SLA 72h', '2026-11-08 10:58:54'),
(18, 9, 'admin', 1, 'assigned', NULL, 'Admin User', '2026-11-08 16:58:54'),
(19, 9, 'admin', 1, 'responded', NULL, 'MIS confirmed the duplicate-number bug in the ID printing software and is preparing a patch.', '2026-11-08 16:58:54'),
(20, 9, 'admin', 1, 'status_changed', 'Pending', 'In Progress', '2026-11-08 16:58:54'),
(21, 8, 'student', NULL, 'created', NULL, 'Pending', '2026-11-08 20:58:54'),
(22, 8, 'system', NULL, 'ai_prioritized', NULL, 'Low, SLA 120h', '2026-11-08 20:58:54'),
(23, 8, 'admin', 1, 'assigned', NULL, 'Dr. Elena Ramos', '2026-11-09 06:58:54'),
(24, 8, 'admin', 3, 'responded', NULL, 'Request forwarded to the University Library. Two additional copies are being ordered.', '2026-11-09 06:58:54'),
(25, 8, 'admin', 3, 'status_changed', 'Pending', 'In Progress', '2026-11-09 06:58:54'),
(26, 1, 'student', NULL, 'created', NULL, 'Pending', '2026-11-07 14:58:54'),
(27, 1, 'system', NULL, 'ai_prioritized', NULL, 'Medium, SLA 72h', '2026-11-07 14:58:54'),
(28, 1, 'admin', 1, 'assigned', NULL, 'Admin User', '2026-11-07 19:58:54'),
(29, 1, 'admin', 1, 'responded', NULL, 'Technician dispatched from the vendor to service the ID printer card feeder.', '2026-11-07 19:58:54'),
(30, 1, 'admin', 1, 'status_changed', 'Pending', 'In Progress', '2026-11-07 19:58:54'),
(31, 2, 'student', NULL, 'created', NULL, 'Pending', '2026-11-04 16:58:54'),
(32, 2, 'system', NULL, 'ai_prioritized', NULL, 'Low, SLA 120h', '2026-11-04 16:58:54'),
(33, 2, 'admin', 1, 'assigned', NULL, 'Admin User', '2026-11-04 22:58:54'),
(34, 2, 'admin', 1, 'responded', NULL, 'Meeting scheduled with the canteen concessionaire.', '2026-11-04 22:58:54'),
(35, 2, 'admin', 1, 'status_changed', 'Pending', 'In Progress', '2026-11-04 22:58:54'),
(36, 2, 'admin', 1, 'responded', NULL, 'Food warmer heating elements inspected and replaced. Food is now served at the proper temperature.', '2026-11-05 20:58:54'),
(37, 2, 'admin', 1, 'status_changed', 'In Progress', 'Resolved', '2026-11-05 20:58:54'),
(38, 3, 'student', NULL, 'created', NULL, 'Pending', '2026-11-06 08:58:54'),
(39, 3, 'system', NULL, 'ai_prioritized', NULL, 'Medium, SLA 72h', '2026-11-06 08:58:54'),
(40, 3, 'admin', 1, 'assigned', NULL, 'Dr. Elena Ramos', '2026-11-07 04:58:54'),
(41, 3, 'admin', 3, 'responded', NULL, 'Exam room schedule reconciled with the CCIS Dean office. IT 312 moved to Rm 402.', '2026-11-07 04:58:54'),
(42, 3, 'admin', 3, 'status_changed', 'Pending', 'Resolved', '2026-11-07 04:58:54'),
(43, 6, 'student', NULL, 'created', NULL, 'Pending', '2026-11-01 18:58:54'),
(44, 6, 'system', NULL, 'ai_prioritized', NULL, 'Medium, SLA 72h', '2026-11-01 18:58:54'),
(45, 6, 'admin', 1, 'assigned', NULL, 'Dr. Elena Ramos', '2026-11-03 00:58:54'),
(46, 6, 'admin', 3, 'responded', NULL, 'Checking the LMS attendance logs with the instructor.', '2026-11-03 00:58:54'),
(47, 6, 'admin', 3, 'status_changed', 'Pending', 'In Progress', '2026-11-03 00:58:54'),
(48, 6, 'admin', 3, 'responded', NULL, 'Attendance corrected in the LMS after verifying the submission timestamp.', '2026-11-05 12:58:54'),
(49, 6, 'admin', 3, 'status_changed', 'In Progress', 'Resolved', '2026-11-05 12:58:54'),
(50, 13, 'student', 3, 'created', NULL, 'Pending', '2026-10-28 04:58:57'),
(51, 13, 'system', NULL, 'ai_prioritized', NULL, 'Medium, SLA 72h', '2026-10-28 04:58:57'),
(52, 13, 'admin', 1, 'assigned', NULL, 'Engr. Carlos Mendoza', '2026-10-28 07:28:57'),
(53, 13, 'admin', 2, 'responded', NULL, 'Aircon technician scheduled to check the unit tomorrow morning.', '2026-10-28 09:58:57'),
(54, 13, 'admin', 2, 'status_changed', 'Pending', 'In Progress', '2026-10-28 09:58:57'),
(55, 13, 'admin', 2, 'responded', NULL, 'Compressor capacitor replaced. The unit is cooling normally again.', '2026-10-29 10:58:57'),
(56, 13, 'admin', 2, 'status_changed', 'In Progress', 'Resolved', '2026-10-29 10:58:57'),
(57, 14, 'student', 4, 'created', NULL, 'Pending', '2026-10-29 10:58:57'),
(58, 14, 'system', NULL, 'ai_prioritized', NULL, 'Medium, SLA 72h', '2026-10-29 10:58:57'),
(59, 14, 'admin', 1, 'assigned', NULL, 'Admin User', '2026-10-29 12:28:57'),
(60, 14, 'admin', 1, 'responded', NULL, 'Reported to MIS. They are adding server capacity for the enrollment period.', '2026-10-29 13:58:57'),
(61, 14, 'admin', 1, 'status_changed', 'Pending', 'In Progress', '2026-10-29 13:58:57'),
(62, 14, 'admin', 1, 'responded', NULL, 'MIS upgraded the enrollment server. Page loads are now under 3 seconds during peak hours.', '2026-10-31 12:58:57'),
(63, 14, 'admin', 1, 'status_changed', 'In Progress', 'Resolved', '2026-10-31 12:58:57'),
(64, 15, 'student', 6, 'created', NULL, 'Pending', '2026-10-30 06:58:57'),
(65, 15, 'system', NULL, 'ai_prioritized', NULL, 'Low, SLA 120h', '2026-10-30 06:58:57'),
(66, 15, 'admin', 1, 'assigned', NULL, 'Engr. Carlos Mendoza', '2026-10-30 10:58:57'),
(67, 15, 'admin', 2, 'responded', NULL, 'Plumber replaced the faucet cartridge. No more leaking.', '2026-10-30 14:58:57'),
(68, 15, 'admin', 2, 'status_changed', 'Pending', 'Resolved', '2026-10-30 14:58:57'),
(69, 16, 'student', 2, 'created', NULL, 'Pending', '2026-10-31 02:58:57'),
(70, 16, 'system', NULL, 'ai_prioritized', NULL, 'High, SLA 48h', '2026-10-31 02:58:57'),
(71, 16, 'admin', 1, 'assigned', NULL, 'Dr. Elena Ramos', '2026-10-31 08:58:57'),
(72, 16, 'admin', 3, 'responded', NULL, 'We contacted the instructor. The grade sheet is being reviewed by the department.', '2026-10-31 14:58:57'),
(73, 16, 'admin', 3, 'status_changed', 'Pending', 'In Progress', '2026-10-31 14:58:57'),
(74, 16, 'admin', 3, 'responded', NULL, 'Grades for IT 211 are now encoded and visible in the student portal.', '2026-11-01 18:58:57'),
(75, 16, 'admin', 3, 'status_changed', 'In Progress', 'Resolved', '2026-11-01 18:58:57'),
(76, 17, 'student', 5, 'created', NULL, 'Pending', '2026-10-31 22:58:57'),
(77, 17, 'system', NULL, 'ai_prioritized', NULL, 'Medium, SLA 72h', '2026-10-31 22:58:57'),
(78, 17, 'admin', 1, 'assigned', NULL, 'Dr. Elena Ramos', '2026-11-01 08:58:57'),
(79, 17, 'admin', 3, 'responded', NULL, 'Endorsed to the Scholarship Office. They are waiting for the fund transfer.', '2026-11-01 18:58:57'),
(80, 17, 'admin', 3, 'status_changed', 'Pending', 'In Progress', '2026-11-01 18:58:57'),
(81, 17, 'admin', 3, 'responded', NULL, 'The stipend for September and October has been released. Please check your accounts.', '2026-11-06 08:58:57'),
(82, 17, 'admin', 3, 'status_changed', 'In Progress', 'Resolved', '2026-11-06 08:58:57'),
(83, 18, 'student', 4, 'created', NULL, 'Pending', '2026-11-02 14:58:57'),
(84, 18, 'system', NULL, 'ai_prioritized', NULL, 'Medium, SLA 72h', '2026-11-02 14:58:57'),
(85, 18, 'admin', 1, 'assigned', NULL, 'Engr. Carlos Mendoza', '2026-11-02 17:58:57'),
(86, 18, 'admin', 2, 'responded', NULL, 'New lockset installed in Room 105.', '2026-11-02 20:58:57'),
(87, 18, 'admin', 2, 'status_changed', 'Pending', 'Resolved', '2026-11-02 20:58:57'),
(88, 19, 'student', 2, 'created', NULL, 'Pending', '2026-11-03 20:58:57'),
(89, 19, 'system', NULL, 'ai_prioritized', NULL, 'Low, SLA 120h', '2026-11-03 20:58:57'),
(90, 19, 'admin', 1, 'assigned', NULL, 'Admin User', '2026-11-04 01:58:57'),
(91, 19, 'admin', 1, 'responded', NULL, 'The Library confirmed the 7 PM schedule. A second staff member is now assigned for the evening shift.', '2026-11-04 06:58:57'),
(92, 19, 'admin', 1, 'status_changed', 'Pending', 'Resolved', '2026-11-04 06:58:57'),
(93, 20, 'student', 3, 'created', NULL, 'Pending', '2026-11-05 02:58:57'),
(94, 20, 'system', NULL, 'ai_prioritized', NULL, 'Medium, SLA 72h', '2026-11-05 02:58:57'),
(95, 20, 'admin', 1, 'assigned', NULL, 'Engr. Carlos Mendoza', '2026-11-05 04:58:57'),
(96, 20, 'admin', 2, 'responded', NULL, 'Electrician will replace the tubes and ballasts this afternoon.', '2026-11-05 06:58:57'),
(97, 20, 'admin', 2, 'status_changed', 'Pending', 'In Progress', '2026-11-05 06:58:57'),
(98, 20, 'admin', 2, 'responded', NULL, 'All lights in Room 302 replaced with LED tubes.', '2026-11-05 22:58:57'),
(99, 20, 'admin', 2, 'status_changed', 'In Progress', 'Resolved', '2026-11-05 22:58:57'),
(100, 21, 'student', 1, 'created', NULL, 'Pending', '2026-11-08 20:58:57'),
(101, 21, 'system', NULL, 'ai_prioritized', NULL, 'High, SLA 48h', '2026-11-08 20:58:57'),
(102, 21, 'admin', 1, 'assigned', NULL, 'Dr. Elena Ramos', '2026-11-08 23:58:57'),
(103, 21, 'admin', 3, 'responded', NULL, 'Your TOR request is being processed. It will be ready for pickup on Thursday.', '2026-11-09 02:58:57'),
(104, 21, 'admin', 3, 'status_changed', 'Pending', 'In Progress', '2026-11-09 02:58:57'),
(105, 22, 'student', 6, 'created', NULL, 'Pending', '2026-11-06 22:58:57'),
(106, 22, 'system', NULL, 'ai_prioritized', NULL, 'High, SLA 48h', '2026-11-06 22:58:57'),
(107, 22, 'admin', 1, 'assigned', NULL, 'Dr. Elena Ramos', '2026-11-07 07:58:57'),
(108, 22, 'admin', 3, 'responded', NULL, 'Forwarded to the CCIS Dean. A second section is being considered.', '2026-11-07 16:58:57'),
(109, 22, 'admin', 3, 'status_changed', 'Pending', 'In Progress', '2026-11-07 16:58:57'),
(110, 23, 'student', 5, 'created', NULL, 'Pending', '2026-11-09 16:58:57'),
(111, 23, 'system', NULL, 'ai_prioritized', NULL, 'Low, SLA 120h', '2026-11-09 16:58:57'),
(112, 23, 'admin', 1, 'assigned', NULL, 'Engr. Carlos Mendoza', '2026-11-09 19:28:57'),
(113, 23, 'admin', 2, 'responded', NULL, 'Water refill delivery requested from the supplier.', '2026-11-09 21:58:57'),
(114, 23, 'admin', 2, 'status_changed', 'Pending', 'In Progress', '2026-11-09 21:58:57'),
(115, 24, 'student', 3, 'created', NULL, 'Pending', '2026-11-10 12:58:57'),
(116, 24, 'system', NULL, 'ai_prioritized', NULL, 'High, SLA 48h', '2026-11-10 12:58:57'),
(117, 25, 'student', 6, 'created', NULL, 'Pending', '2026-11-10 08:58:57'),
(118, 25, 'system', NULL, 'ai_prioritized', NULL, 'Medium, SLA 72h', '2026-11-10 08:58:57'),
(119, 26, 'student', 4, 'created', NULL, 'Pending', '2026-11-10 16:58:57'),
(120, 26, 'system', NULL, 'ai_prioritized', NULL, 'High, SLA 48h', '2026-11-10 16:58:57'),
(121, 27, 'student', 5, 'created', NULL, 'Pending', '2026-11-10 02:58:57'),
(122, 27, 'system', NULL, 'ai_prioritized', NULL, 'Medium, SLA 72h', '2026-11-10 02:58:57');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `conversation_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `last_message_id` int(11) DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`conversation_id`, `student_id`, `last_message_id`, `last_message_at`, `created_at`) VALUES
(1, 1, 3, '2026-11-09 17:37:08', '2026-11-09 17:01:08'),
(2, 2, 5, '2026-11-10 17:31:08', '2026-11-10 17:01:08');

-- --------------------------------------------------------

--
-- Table structure for table `email_outbox`
--

CREATE TABLE `email_outbox` (
  `email_id` int(11) NOT NULL,
  `to_email` varchar(150) NOT NULL,
  `to_name` varchar(150) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body_text` text NOT NULL,
  `body_html` mediumtext DEFAULT NULL,
  `status` enum('Pending','Sent','Failed') NOT NULL DEFAULT 'Pending',
  `attempts` tinyint(4) NOT NULL DEFAULT 0,
  `last_error` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_outbox`
--

INSERT INTO `email_outbox` (`email_id`, `to_email`, `to_name`, `subject`, `body_text`, `body_html`, `status`, `attempts`, `last_error`, `created_at`, `sent_at`) VALUES
(50, 'kevin.lim@gmail.com', 'Kevin Lim', 'Your ICMS account is approved', 'Your ICMS account is approved\n\nHello Kevin Lim,\n\nYour student account (SNSU-2025-07777) has been approved. You can now sign in and submit concerns.\n\nSign in: http://localhost/TrackingSystem/index.php\n\n--\nSurigao del Norte State University\nAI-Powered Institutional Concern Management System\nThis is an automated message; please do not reply.', '<!doctype html><html><body style=\"margin:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;\"><table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"padding:24px 12px;\"><tr><td align=\"center\"><table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;\"><tr><td style=\"background:#0e2246;color:#ffffff;padding:18px 24px;font-size:13px;letter-spacing:.04em;\">SNSU &middot; INSTITUTIONAL CONCERN MANAGEMENT</td></tr><tr><td style=\"padding:24px;\"><h1 style=\"margin:0 0 16px;font-size:20px;color:#0f172a;\">Your ICMS account is approved</h1><p style=\"margin:0 0 14px;font-size:15px;line-height:1.55;color:#334155;\">Hello Kevin Lim,</p><p style=\"margin:0 0 14px;font-size:15px;line-height:1.55;color:#334155;\">Your student account (SNSU-2025-07777) has been approved. You can now sign in and submit concerns.</p><p style=\"margin:22px 0;\"><a href=\"http://localhost/TrackingSystem/index.php\" style=\"background:#2563eb;color:#ffffff;text-decoration:none;padding:11px 20px;border-radius:8px;font-weight:600;font-size:14px;display:inline-block;\">Sign in</a></p></td></tr><tr><td style=\"padding:14px 24px;background:#f8fafc;color:#94a3b8;font-size:12px;\">Automated message from the AI-Powered Institutional Concern Management System. Please do not reply.</td></tr></table></td></tr></table></body></html>', 'Sent', 1, NULL, '2026-11-10 23:03:04', '2026-11-10 23:03:10');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `concern_id` int(11) DEFAULT NULL,
  `student_name` varchar(150) NOT NULL,
  `student_email` varchar(150) NOT NULL,
  `feedback_type` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `rating` int(11) DEFAULT 5,
  `admin_reply` text DEFAULT NULL,
  `replied_by` int(11) DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `student_id`, `concern_id`, `student_name`, `student_email`, `feedback_type`, `subject`, `message`, `rating`, `admin_reply`, `replied_by`, `replied_at`, `submitted_at`) VALUES
(1, 1, NULL, 'Juan Dela Cruz', 'juan.delacruz@student.com', 'System Feedback', 'Very convenient concern tracking system', 'The real-time status tracking and transparent administrator replies help us follow up on issues without visiting offices physically.', 5, 'Thank you, Juan! We are glad the tracking helps. We will keep improving it based on student feedback.', 1, '2026-11-08 10:58:54', '2026-11-08 00:58:54'),
(2, 2, NULL, 'Maria Santos', 'maria.santos@school.edu', 'Facilities Feedback', 'Prompt action on room cleaning', 'Appreciate the quick response to our restroom cleaning report last week.', 4, NULL, NULL, NULL, '2026-11-05 02:58:54'),
(3, 1, 2, 'Juan Dela Cruz', 'juan.delacruz@student.com', 'Resolution Rating', 'Resolution of SCF-2026-002', 'The food warmers were fixed within a day. Thank you!', 5, NULL, NULL, NULL, '2026-11-05 22:58:54'),
(4, 6, 6, 'Mark Villanueva', 'mark.villanueva@school.edu', 'Resolution Rating', 'Resolution of SCF-2026-006', 'It was fixed, but it took almost four days and affected my grade computation.', 2, NULL, NULL, NULL, '2026-11-05 17:58:54'),
(5, 3, 13, 'John Reyes', 'john.reyes@school.edu', 'Resolution Rating', 'Resolution of SCF-2026-013', 'Fixed in about a day. The room is comfortable again, thank you!', 5, NULL, NULL, NULL, '2026-10-29 13:58:57'),
(6, 4, 14, 'Pedro Reyes', 'pedro.reyes@school.edu', 'Resolution Rating', 'Resolution of SCF-2026-014', 'Much faster now, but it took two days.', 4, NULL, NULL, NULL, '2026-10-31 15:58:57'),
(7, 6, 15, 'Mark Villanueva', 'mark.villanueva@school.edu', 'Resolution Rating', 'Resolution of SCF-2026-015', 'Quick repair. Thank you.', 4, NULL, NULL, NULL, '2026-10-30 17:58:57'),
(8, 2, 16, 'Maria Santos', 'maria.santos@school.edu', 'Resolution Rating', 'Resolution of SCF-2026-016', 'My grade is now visible. Thank you, Dean Ramos.', 5, NULL, NULL, NULL, '2026-11-01 21:58:57'),
(9, 5, 17, 'Anne Garcia', 'anne.garcia@school.edu', 'Resolution Rating', 'Resolution of SCF-2026-017', 'Released, but it took more than five days to get an update.', 3, NULL, NULL, NULL, '2026-11-06 11:58:57'),
(10, 4, 18, 'Pedro Reyes', 'pedro.reyes@school.edu', 'Resolution Rating', 'Resolution of SCF-2026-018', 'Door can be locked again.', 4, NULL, NULL, NULL, '2026-11-02 23:58:57'),
(11, 2, 19, 'Maria Santos', 'maria.santos@school.edu', 'Resolution Rating', 'Resolution of SCF-2026-019', 'Open until 7 PM again.', 4, NULL, NULL, NULL, '2026-11-04 09:58:57'),
(12, 3, 20, 'John Reyes', 'john.reyes@school.edu', 'Resolution Rating', 'Resolution of SCF-2026-020', 'Much better lighting now!', 5, NULL, NULL, NULL, '2026-11-06 01:58:57');

-- --------------------------------------------------------

--
-- Table structure for table `iso_evaluation_results`
--

CREATE TABLE `iso_evaluation_results` (
  `characteristic` varchar(40) NOT NULL,
  `mean_score` decimal(3,2) NOT NULL,
  `respondents` int(11) NOT NULL,
  `survey_date` date DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `attempt_id` int(11) NOT NULL,
  `identifier` varchar(150) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_type` enum('student','admin') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `reply_to_id` int(11) DEFAULT NULL,
  `forwarded_from_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  `edited_at` datetime DEFAULT NULL,
  `unsent_at` datetime DEFAULT NULL,
  `updated_at` datetime(3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `conversation_id`, `sender_type`, `sender_id`, `body`, `reply_to_id`, `forwarded_from_id`, `created_at`, `read_at`, `edited_at`, `unsent_at`, `updated_at`) VALUES
(1, 1, 'student', 1, 'Good morning po. May I ask if the projector in Room 304 will be fixed before our report on Friday?', NULL, NULL, '2026-11-09 17:01:08', '2026-11-09 17:07:08', NULL, NULL, '2026-11-09 17:01:08.000'),
(2, 1, 'admin', 1, 'Good morning, Juan. Your concern is already in our queue. We will update your ticket once the technician has checked it.', NULL, NULL, '2026-11-09 17:31:08', '2026-11-09 17:37:08', NULL, NULL, '2026-11-09 17:31:08.000'),
(3, 1, 'student', 1, 'Thank you po!', NULL, NULL, '2026-11-09 17:37:08', '2026-11-09 17:43:08', NULL, NULL, '2026-11-09 17:37:08.000'),
(4, 2, 'student', 2, 'Hello, is the Guidance Office open on Saturday for counseling?', NULL, NULL, '2026-11-10 17:01:08', '2026-11-10 17:07:08', NULL, NULL, '2026-11-10 17:01:08.000'),
(5, 2, 'admin', 3, 'Hi Maria. Yes, there are slots on Saturday from 8 AM to 12 NN. You can also follow your concern ticket for the schedule.', NULL, NULL, '2026-11-10 17:31:08', NULL, NULL, NULL, '2026-11-10 17:31:08.000');

-- --------------------------------------------------------

--
-- Table structure for table `message_hidden`
--

CREATE TABLE `message_hidden` (
  `message_id` int(11) NOT NULL,
  `user_type` enum('student','admin') NOT NULL,
  `user_id` int(11) NOT NULL,
  `hidden_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_reactions`
--

CREATE TABLE `message_reactions` (
  `message_id` int(11) NOT NULL,
  `user_type` enum('student','admin') NOT NULL,
  `user_id` int(11) NOT NULL,
  `emoji` varchar(16) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `recipient_type` enum('student','admin') NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `concern_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `recipient_type`, `recipient_id`, `concern_id`, `type`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 'student', 1, 5, 'status_change', 'SCF-2026-005 is now In Progress', 'We have already endorsed this to the maintenance team.', 0, '2026-11-09 20:58:54'),
(2, 'student', 1, 11, 'response', 'SCF-2026-011: new response', 'Rapid Response Team dispatched to CCIS Lab 1.', 0, '2026-11-10 20:13:54'),
(3, 'student', 1, 3, 'status_change', 'SCF-2026-003 is now Resolved', 'Exam room schedule reconciled with the CCIS Dean office.', 1, '2026-11-07 04:58:54'),
(4, 'admin', 1, 11, 'rrt', 'RRT Critical Alert: SCF-2026-011', 'Exposed live wiring and water leak near computer terminals in CCIS Lab 1 (Critical)', 0, '2026-11-10 19:58:54'),
(5, 'admin', 3, 11, 'rrt', 'RRT Critical Alert: SCF-2026-011', 'Exposed live wiring and water leak near computer terminals in CCIS Lab 1 (Critical)', 0, '2026-11-10 19:58:54'),
(6, 'admin', 2, 11, 'rrt', 'RRT Critical Alert: SCF-2026-011', 'Exposed live wiring and water leak near computer terminals in CCIS Lab 1 (Critical)', 0, '2026-11-10 19:58:54'),
(7, 'student', 3, 13, 'response', 'SCF-2026-013: new response', 'Aircon technician scheduled to check the unit tomorrow morning.', 1, '2026-10-28 09:58:57'),
(8, 'student', 3, 13, 'status_change', 'SCF-2026-013 is now Resolved', 'Compressor capacitor replaced. The unit is cooling normally again.', 1, '2026-10-29 10:58:57'),
(9, 'admin', 1, 13, 'new_concern', 'New concern: SCF-2026-013', 'Aircon not working in Room 201', 1, '2026-10-28 04:58:57'),
(10, 'admin', 3, 13, 'new_concern', 'New concern: SCF-2026-013', 'Aircon not working in Room 201', 1, '2026-10-28 04:58:57'),
(11, 'admin', 2, 13, 'new_concern', 'New concern: SCF-2026-013', 'Aircon not working in Room 201', 1, '2026-10-28 04:58:57'),
(12, 'student', 4, 14, 'response', 'SCF-2026-014: new response', 'Reported to MIS. They are adding server capacity for the enrollment period.', 1, '2026-10-29 13:58:57'),
(13, 'student', 4, 14, 'status_change', 'SCF-2026-014 is now Resolved', 'MIS upgraded the enrollment server. Page loads are now under 3 seconds during peak hours.', 1, '2026-10-31 12:58:57'),
(14, 'admin', 1, 14, 'new_concern', 'New concern: SCF-2026-014', 'Enrollment portal very slow during peak hours', 1, '2026-10-29 10:58:57'),
(15, 'admin', 3, 14, 'new_concern', 'New concern: SCF-2026-014', 'Enrollment portal very slow during peak hours', 1, '2026-10-29 10:58:57'),
(16, 'admin', 2, 14, 'new_concern', 'New concern: SCF-2026-014', 'Enrollment portal very slow during peak hours', 1, '2026-10-29 10:58:57'),
(17, 'student', 6, 15, 'status_change', 'SCF-2026-015 is now Resolved', 'Plumber replaced the faucet cartridge. No more leaking.', 1, '2026-10-30 14:58:57'),
(18, 'admin', 1, 15, 'new_concern', 'New concern: SCF-2026-015', 'Leaking faucet in the 2nd floor comfort room, Engineering building', 1, '2026-10-30 06:58:57'),
(19, 'admin', 3, 15, 'new_concern', 'New concern: SCF-2026-015', 'Leaking faucet in the 2nd floor comfort room, Engineering building', 1, '2026-10-30 06:58:57'),
(20, 'admin', 2, 15, 'new_concern', 'New concern: SCF-2026-015', 'Leaking faucet in the 2nd floor comfort room, Engineering building', 1, '2026-10-30 06:58:57'),
(21, 'student', 2, 16, 'response', 'SCF-2026-016: new response', 'We contacted the instructor. The grade sheet is being reviewed by the department.', 1, '2026-10-31 14:58:57'),
(22, 'student', 2, 16, 'status_change', 'SCF-2026-016 is now Resolved', 'Grades for IT 211 are now encoded and visible in the student portal.', 1, '2026-11-01 18:58:57'),
(23, 'admin', 1, 16, 'new_concern', 'New concern: SCF-2026-016', 'Grade for IT 211 not yet encoded', 1, '2026-10-31 02:58:57'),
(24, 'admin', 3, 16, 'new_concern', 'New concern: SCF-2026-016', 'Grade for IT 211 not yet encoded', 1, '2026-10-31 02:58:57'),
(25, 'admin', 2, 16, 'new_concern', 'New concern: SCF-2026-016', 'Grade for IT 211 not yet encoded', 1, '2026-10-31 02:58:57'),
(26, 'student', 5, 17, 'response', 'SCF-2026-017: new response', 'Endorsed to the Scholarship Office. They are waiting for the fund transfer.', 1, '2026-11-01 18:58:57'),
(27, 'student', 5, 17, 'status_change', 'SCF-2026-017 is now Resolved', 'The stipend for September and October has been released. Please check your accounts.', 1, '2026-11-06 08:58:57'),
(28, 'admin', 1, 17, 'new_concern', 'New concern: SCF-2026-017', 'Scholarship stipend delayed for two months', 1, '2026-10-31 22:58:57'),
(29, 'admin', 3, 17, 'new_concern', 'New concern: SCF-2026-017', 'Scholarship stipend delayed for two months', 1, '2026-10-31 22:58:57'),
(30, 'admin', 2, 17, 'new_concern', 'New concern: SCF-2026-017', 'Scholarship stipend delayed for two months', 1, '2026-10-31 22:58:57'),
(31, 'student', 4, 18, 'status_change', 'SCF-2026-018 is now Resolved', 'New lockset installed in Room 105.', 1, '2026-11-02 20:58:57'),
(32, 'admin', 1, 18, 'new_concern', 'New concern: SCF-2026-018', 'Broken door knob in Room 105', 1, '2026-11-02 14:58:57'),
(33, 'admin', 3, 18, 'new_concern', 'New concern: SCF-2026-018', 'Broken door knob in Room 105', 1, '2026-11-02 14:58:57'),
(34, 'admin', 2, 18, 'new_concern', 'New concern: SCF-2026-018', 'Broken door knob in Room 105', 1, '2026-11-02 14:58:57'),
(35, 'student', 2, 19, 'status_change', 'SCF-2026-019 is now Resolved', 'The Library confirmed the 7 PM schedule. A second staff member is now assigned for the evening shift.', 1, '2026-11-04 06:58:57'),
(36, 'admin', 1, 19, 'new_concern', 'New concern: SCF-2026-019', 'Library closes earlier than the posted schedule', 1, '2026-11-03 20:58:57'),
(37, 'admin', 3, 19, 'new_concern', 'New concern: SCF-2026-019', 'Library closes earlier than the posted schedule', 1, '2026-11-03 20:58:57'),
(38, 'admin', 2, 19, 'new_concern', 'New concern: SCF-2026-019', 'Library closes earlier than the posted schedule', 1, '2026-11-03 20:58:57'),
(39, 'student', 3, 20, 'response', 'SCF-2026-020: new response', 'Electrician will replace the tubes and ballasts this afternoon.', 1, '2026-11-05 06:58:57'),
(40, 'student', 3, 20, 'status_change', 'SCF-2026-020 is now Resolved', 'All lights in Room 302 replaced with LED tubes.', 1, '2026-11-05 22:58:57'),
(41, 'admin', 1, 20, 'new_concern', 'New concern: SCF-2026-020', 'Flickering lights in Room 302 causing headaches', 1, '2026-11-05 02:58:57'),
(42, 'admin', 3, 20, 'new_concern', 'New concern: SCF-2026-020', 'Flickering lights in Room 302 causing headaches', 1, '2026-11-05 02:58:57'),
(43, 'admin', 2, 20, 'new_concern', 'New concern: SCF-2026-020', 'Flickering lights in Room 302 causing headaches', 1, '2026-11-05 02:58:57'),
(44, 'student', 1, 21, 'response', 'SCF-2026-021: new response', 'Your TOR request is being processed. It will be ready for pickup on Thursday.', 1, '2026-11-09 02:58:57'),
(45, 'admin', 1, 21, 'new_concern', 'New concern: SCF-2026-021', 'Mabagal ang pag-release ng TOR sa Registrar', 1, '2026-11-08 20:58:57'),
(46, 'admin', 3, 21, 'new_concern', 'New concern: SCF-2026-021', 'Mabagal ang pag-release ng TOR sa Registrar', 1, '2026-11-08 20:58:57'),
(47, 'admin', 2, 21, 'new_concern', 'New concern: SCF-2026-021', 'Mabagal ang pag-release ng TOR sa Registrar', 1, '2026-11-08 20:58:57'),
(48, 'student', 6, 22, 'response', 'SCF-2026-022: new response', 'Forwarded to the CCIS Dean. A second section is being considered.', 1, '2026-11-07 16:58:57'),
(49, 'admin', 1, 22, 'new_concern', 'New concern: SCF-2026-022', 'Request for additional sections of CS 321', 1, '2026-11-06 22:58:57'),
(50, 'admin', 3, 22, 'new_concern', 'New concern: SCF-2026-022', 'Request for additional sections of CS 321', 1, '2026-11-06 22:58:57'),
(51, 'admin', 2, 22, 'new_concern', 'New concern: SCF-2026-022', 'Request for additional sections of CS 321', 1, '2026-11-06 22:58:57'),
(52, 'student', 5, 23, 'response', 'SCF-2026-023: new response', 'Water refill delivery requested from the supplier.', 1, '2026-11-09 21:58:57'),
(53, 'admin', 1, 23, 'new_concern', 'New concern: SCF-2026-023', 'Water dispenser empty in the CCIS lobby', 1, '2026-11-09 16:58:57'),
(54, 'admin', 3, 23, 'new_concern', 'New concern: SCF-2026-023', 'Water dispenser empty in the CCIS lobby', 1, '2026-11-09 16:58:57'),
(55, 'admin', 2, 23, 'new_concern', 'New concern: SCF-2026-023', 'Water dispenser empty in the CCIS lobby', 1, '2026-11-09 16:58:57'),
(56, 'admin', 1, 24, 'new_concern', 'New concern: SCF-2026-024', 'Classmates being bullied in our block group chat', 0, '2026-11-10 12:58:57'),
(57, 'admin', 3, 24, 'new_concern', 'New concern: SCF-2026-024', 'Classmates being bullied in our block group chat', 0, '2026-11-10 12:58:57'),
(58, 'admin', 2, 24, 'new_concern', 'New concern: SCF-2026-024', 'Classmates being bullied in our block group chat', 0, '2026-11-10 12:58:57'),
(59, 'admin', 1, 25, 'new_concern', 'New concern: SCF-2026-025', 'No Wi-Fi signal in the Engineering building', 0, '2026-11-10 08:58:57'),
(60, 'admin', 3, 25, 'new_concern', 'New concern: SCF-2026-025', 'No Wi-Fi signal in the Engineering building', 0, '2026-11-10 08:58:57'),
(61, 'admin', 2, 25, 'new_concern', 'New concern: SCF-2026-025', 'No Wi-Fi signal in the Engineering building', 0, '2026-11-10 08:58:57'),
(62, 'admin', 1, 26, 'new_concern', 'New concern: SCF-2026-026', 'Broken stair railing near the gymnasium', 0, '2026-11-10 16:58:57'),
(63, 'admin', 3, 26, 'new_concern', 'New concern: SCF-2026-026', 'Broken stair railing near the gymnasium', 0, '2026-11-10 16:58:57'),
(64, 'admin', 2, 26, 'new_concern', 'New concern: SCF-2026-026', 'Broken stair railing near the gymnasium', 0, '2026-11-10 16:58:57'),
(65, 'admin', 1, 27, 'new_concern', 'New concern: SCF-2026-027', 'Guba ang electric fan sa Room 108', 0, '2026-11-10 02:58:57'),
(66, 'admin', 3, 27, 'new_concern', 'New concern: SCF-2026-027', 'Guba ang electric fan sa Room 108', 0, '2026-11-10 02:58:57'),
(67, 'admin', 2, 27, 'new_concern', 'New concern: SCF-2026-027', 'Guba ang electric fan sa Room 108', 0, '2026-11-10 02:58:57');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_requests`
--

CREATE TABLE `password_reset_requests` (
  `request_id` int(11) NOT NULL,
  `user_type` enum('student','admin') NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('Pending','Completed','Dismissed') NOT NULL DEFAULT 'Pending',
  `requested_at` datetime DEFAULT current_timestamp(),
  `handled_by` int(11) DEFAULT NULL,
  `handled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `token_id` int(11) NOT NULL,
  `user_type` enum('student','admin') NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profile_photos`
--

CREATE TABLE `profile_photos` (
  `owner_type` enum('student','admin') NOT NULL,
  `owner_id` int(11) NOT NULL,
  `mime_type` varchar(50) NOT NULL DEFAULT 'image/jpeg',
  `file_size` int(11) NOT NULL,
  `sha256` char(64) NOT NULL,
  `photo_data` mediumblob NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registration_rejections`
--

CREATE TABLE `registration_rejections` (
  `rejection_id` int(11) NOT NULL,
  `student_no` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `department` varchar(150) DEFAULT NULL,
  `program` varchar(150) DEFAULT NULL,
  `registered_at` datetime DEFAULT NULL,
  `registration_note` varchar(255) DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `responses`
--

CREATE TABLE `responses` (
  `response_id` int(11) NOT NULL,
  `concern_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `response_text` text NOT NULL,
  `department_endorsed` varchar(150) DEFAULT NULL,
  `status_id` int(11) NOT NULL,
  `date_responded` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `responses`
--

INSERT INTO `responses` (`response_id`, `concern_id`, `admin_id`, `response_text`, `department_endorsed`, `status_id`, `date_responded`) VALUES
(6, 11, 2, 'RAPID RESPONSE TEAM DISPATCHED: CCIS Lab 1 main circuit breaker switched off. Facilities electrical crew en route with high priority.', 'Facilities & Maintenance', 1, '2026-11-10 20:13:54'),
(7, 5, 1, 'We have already endorsed this to the maintenance team. Replacement chairs are being pulled from storage.', 'Facilities & Maintenance', 2, '2026-11-09 20:58:54'),
(8, 9, 1, 'MIS confirmed the duplicate-number bug in the ID printing software and is preparing a patch.', 'MIS / IT Services', 2, '2026-11-08 16:58:54'),
(9, 8, 3, 'Request forwarded to the University Library. Two additional copies are being ordered.', 'Registrar / Academic Affairs', 2, '2026-11-09 06:58:54'),
(10, 1, 1, 'Technician dispatched from the vendor to service the ID printer card feeder.', 'MIS / IT Services', 2, '2026-11-07 19:58:54'),
(11, 2, 1, 'Meeting scheduled with the canteen concessionaire.', 'Student Services', 2, '2026-11-04 22:58:54'),
(12, 2, 1, 'Food warmer heating elements inspected and replaced. Food is now served at the proper temperature.', 'Student Services', 3, '2026-11-05 20:58:54'),
(13, 3, 3, 'Exam room schedule reconciled with the CCIS Dean office. IT 312 moved to Rm 402.', 'Academic Affairs / CCIS', 3, '2026-11-07 04:58:54'),
(14, 6, 3, 'Checking the LMS attendance logs with the instructor.', NULL, 2, '2026-11-03 00:58:54'),
(15, 6, 3, 'Attendance corrected in the LMS after verifying the submission timestamp.', 'Registrar / Academic Affairs', 3, '2026-11-05 12:58:54'),
(16, 13, 2, 'Aircon technician scheduled to check the unit tomorrow morning.', 'Facilities & Maintenance', 2, '2026-10-28 09:58:57'),
(17, 13, 2, 'Compressor capacitor replaced. The unit is cooling normally again.', 'Facilities & Maintenance', 3, '2026-10-29 10:58:57'),
(18, 14, 1, 'Reported to MIS. They are adding server capacity for the enrollment period.', 'MIS / IT Services', 2, '2026-10-29 13:58:57'),
(19, 14, 1, 'MIS upgraded the enrollment server. Page loads are now under 3 seconds during peak hours.', 'MIS / IT Services', 3, '2026-10-31 12:58:57'),
(20, 15, 2, 'Plumber replaced the faucet cartridge. No more leaking.', 'Facilities & Maintenance', 3, '2026-10-30 14:58:57'),
(21, 16, 3, 'We contacted the instructor. The grade sheet is being reviewed by the department.', 'Registrar / Academic Affairs', 2, '2026-10-31 14:58:57'),
(22, 16, 3, 'Grades for IT 211 are now encoded and visible in the student portal.', 'Registrar / Academic Affairs', 3, '2026-11-01 18:58:57'),
(23, 17, 3, 'Endorsed to the Scholarship Office. They are waiting for the fund transfer.', 'Office of Student Affairs', 2, '2026-11-01 18:58:57'),
(24, 17, 3, 'The stipend for September and October has been released. Please check your accounts.', 'Office of Student Affairs', 3, '2026-11-06 08:58:57'),
(25, 18, 2, 'New lockset installed in Room 105.', 'Facilities & Maintenance', 3, '2026-11-02 20:58:57'),
(26, 19, 1, 'The Library confirmed the 7 PM schedule. A second staff member is now assigned for the evening shift.', 'Student Services', 3, '2026-11-04 06:58:57'),
(27, 20, 2, 'Electrician will replace the tubes and ballasts this afternoon.', 'Facilities & Maintenance', 2, '2026-11-05 06:58:57'),
(28, 20, 2, 'All lights in Room 302 replaced with LED tubes.', 'Facilities & Maintenance', 3, '2026-11-05 22:58:57'),
(29, 21, 3, 'Your TOR request is being processed. It will be ready for pickup on Thursday.', 'Registrar / Academic Affairs', 2, '2026-11-09 02:58:57'),
(30, 22, 3, 'Forwarded to the CCIS Dean. A second section is being considered.', 'Academic Affairs / CCIS', 2, '2026-11-07 16:58:57'),
(31, 23, 2, 'Water refill delivery requested from the supplier.', 'Facilities & Maintenance', 2, '2026-11-09 21:58:57');

-- --------------------------------------------------------

--
-- Table structure for table `schema_migrations`
--

CREATE TABLE `schema_migrations` (
  `migration` varchar(150) NOT NULL,
  `applied_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schema_migrations`
--

INSERT INTO `schema_migrations` (`migration`, `applied_at`) VALUES
('000_baseline', '2026-11-08 23:08:13'),
('001_sla_tracking_audit_notifications', '2026-11-08 23:08:14'),
('002_auth_registration_security', '2026-11-08 23:18:46'),
('003_resolution_feedback', '2026-11-08 23:24:56'),
('004_email_reset_tokens_reopen', '2026-11-08 23:32:33'),
('005_assignment_sla_alerts_activity', '2026-11-09 00:08:11'),
('006_withdraw_concern', '2026-11-09 00:27:22'),
('007_concern_edits', '2026-11-09 13:19:25'),
('008_categories_feedback_replies', '2026-11-09 13:47:13'),
('009_registration_approval_masterlist', '2026-11-09 14:13:57'),
('010_anonymous_concerns', '2026-11-09 14:25:32'),
('011_iso_evaluation_results', '2026-11-09 14:42:37'),
('012_realtime_messaging', '2026-11-09 19:17:54'),
('013_concern_attachments_blob', '2026-11-09 19:45:29'),
('014_message_actions', '2026-11-09 21:23:09'),
('015_archive_instead_of_delete', '2026-11-10 16:20:37'),
('016_recovery_codes', '2026-11-10 17:40:12'),
('017_ai_engine_used', '2026-11-10 20:35:13'),
('018_ai_fix_estimate', '2026-11-10 21:12:45'),
('019_profile_photos', '2026-11-10 22:47:39');

-- --------------------------------------------------------

--
-- Table structure for table `statuses`
--

CREATE TABLE `statuses` (
  `status_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `statuses`
--

INSERT INTO `statuses` (`status_id`, `status_name`) VALUES
(2, 'In Progress'),
(1, 'Pending'),
(3, 'Resolved'),
(4, 'Withdrawn');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `student_no` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `recovery_code_hash` varchar(255) DEFAULT NULL,
  `recovery_code_set_at` datetime DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `department` varchar(150) NOT NULL DEFAULT 'College of Computing & Information Sciences',
  `program` varchar(150) NOT NULL DEFAULT 'Bachelor of Science in Information Technology',
  `phone` varchar(50) DEFAULT NULL,
  `status` enum('Active','Inactive','Pending') DEFAULT 'Active',
  `registration_source` enum('self','admin') NOT NULL DEFAULT 'admin',
  `masterlist_verified` tinyint(1) NOT NULL DEFAULT 0,
  `registration_note` varchar(255) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `student_no`, `name`, `email`, `password`, `recovery_code_hash`, `recovery_code_set_at`, `must_change_password`, `department`, `program`, `phone`, `status`, `registration_source`, `masterlist_verified`, `registration_note`, `approved_by`, `approved_at`, `last_login_at`, `created_at`, `archived_at`, `archived_by`) VALUES
(1, 'SNSU-2022-04189', 'Juan Dela Cruz', 'juan.delacruz@student.com', '$2y$10$QGDmisoExDIRHuc788De5.FWICPF6WrH/19gNaA8/1KS5Gn7iayXC', NULL, NULL, 0, 'College of Computing & Information Sciences', 'BS Information Technology', NULL, 'Active', 'admin', 1, NULL, NULL, NULL, '2026-11-10 23:01:21', '2024-04-15 08:30:00', NULL, NULL),
(2, 'SNSU-2023-01024', 'Maria Santos', 'maria.santos@school.edu', '$2y$10$2i1PZIYQKZ/0E5Gt0QBrSOZ56fn7VxJki9YoKWHoOaYNij9ASN0Ia', NULL, NULL, 0, 'College of Computing & Information Sciences', 'BS Computer Science', NULL, 'Active', 'admin', 1, NULL, NULL, NULL, NULL, '2024-04-20 09:15:00', NULL, NULL),
(3, 'SNSU-2021-08732', 'John Reyes', 'john.reyes@school.edu', '$2y$12$9Tlvd1H4YMJOzIhC2nL0F.3.5W8jx51k.BtBZvlYthvTn3y2YQLQm', NULL, NULL, 0, 'College of Technology', 'BS Industrial Technology', NULL, 'Active', 'admin', 1, NULL, NULL, NULL, NULL, '2024-04-22 10:00:00', NULL, NULL),
(4, 'SNSU-2022-03112', 'Pedro Reyes', 'pedro.reyes@school.edu', '$2y$12$9Tlvd1H4YMJOzIhC2nL0F.3.5W8jx51k.BtBZvlYthvTn3y2YQLQm', NULL, NULL, 0, 'College of Engineering', 'BS Civil Engineering', NULL, 'Active', 'admin', 1, NULL, NULL, NULL, NULL, '2024-04-25 11:20:00', NULL, NULL),
(5, 'SNSU-2023-05490', 'Anne Garcia', 'anne.garcia@school.edu', '$2y$12$9Tlvd1H4YMJOzIhC2nL0F.3.5W8jx51k.BtBZvlYthvTn3y2YQLQm', NULL, NULL, 0, 'College of Education', 'BSEd Major in English', NULL, 'Active', 'admin', 1, NULL, NULL, NULL, NULL, '2024-05-01 14:00:00', NULL, NULL),
(6, 'SNSU-2022-09871', 'Mark Villanueva', 'mark.villanueva@school.edu', '$2y$12$9Tlvd1H4YMJOzIhC2nL0F.3.5W8jx51k.BtBZvlYthvTn3y2YQLQm', NULL, NULL, 0, 'College of Computing & Information Sciences', 'BS Information Systems', NULL, 'Active', 'admin', 1, NULL, NULL, NULL, NULL, '2024-05-02 08:45:00', NULL, NULL),
(7, 'SNSU-2025-07777', 'Kevin Lim', 'kevin.lim@gmail.com', '$2y$10$/MBTZe1HciJ6B1AbRJ4HtOuBN3rARd64YZKcfE1sw3F2cLH0qEGFS', NULL, NULL, 0, 'College of Computing & Information Sciences', 'BS Computer Science', NULL, 'Active', 'self', 0, 'Student number is not on the masterlist.', 1, '2026-11-10 23:03:04', NULL, '2026-11-10 17:58:54', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_masterlist`
--

CREATE TABLE `student_masterlist` (
  `masterlist_id` int(11) NOT NULL,
  `student_no` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `program` varchar(150) DEFAULT NULL,
  `imported_by` int(11) DEFAULT NULL,
  `imported_at` datetime DEFAULT current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_masterlist`
--

INSERT INTO `student_masterlist` (`masterlist_id`, `student_no`, `name`, `email`, `department`, `program`, `imported_by`, `imported_at`, `archived_at`, `archived_by`) VALUES
(1, 'SNSU-2022-04189', 'Juan Dela Cruz', 'juan.delacruz@student.com', 'College of Computing & Information Sciences', 'BS Information Technology', 1, '2026-10-31 22:58:54', NULL, NULL),
(2, 'SNSU-2023-01024', 'Maria Santos', 'maria.santos@school.edu', 'College of Computing & Information Sciences', 'BS Computer Science', 1, '2026-10-31 22:58:54', NULL, NULL),
(3, 'SNSU-2021-08732', 'John Reyes', 'john.reyes@school.edu', 'College of Technology', 'BS Industrial Technology', 1, '2026-10-31 22:58:54', NULL, NULL),
(4, 'SNSU-2022-03112', 'Pedro Reyes', 'pedro.reyes@school.edu', 'College of Engineering', 'BS Civil Engineering', 1, '2026-10-31 22:58:54', NULL, NULL),
(5, 'SNSU-2023-05490', 'Anne Garcia', 'anne.garcia@school.edu', 'College of Education', 'BSEd Major in English', 1, '2026-10-31 22:58:54', NULL, NULL),
(6, 'SNSU-2022-09871', 'Mark Villanueva', 'mark.villanueva@school.edu', 'College of Computing & Information Sciences', 'BS Information Systems', 1, '2026-10-31 22:58:54', NULL, NULL),
(7, 'SNSU-2024-02233', 'Liza Mae Torres', NULL, 'College of Computing & Information Sciences', 'BS Information Technology', 1, '2026-10-31 22:58:54', NULL, NULL),
(8, 'SNSU-2024-04410', 'Ramon Bautista', NULL, 'College of Engineering', 'BS Electrical Engineering', 1, '2026-10-31 22:58:54', NULL, NULL),
(9, 'SNSU-2025-00318', 'Grace Ann Lim', 'grace.lim@gmail.com', 'College of Education', 'BEEd', 1, '2026-10-31 22:58:54', NULL, NULL),
(10, 'SNSU-2025-01150', 'Joshua Navarro', NULL, 'College of Technology', 'BS Industrial Technology', 1, '2026-10-31 22:58:54', NULL, NULL),
(171, 'SNSU-2025-02101', 'Kristine Joy Mendoza', 'kristine.mendoza@example.com', 'College of Computing & Information Sciences', 'BS Information Technology', 1, '2026-11-10 00:03:20', NULL, NULL),
(172, 'SNSU-2025-02102', 'Carlo Miguel Fernandez', 'carlo.fernandez@example.com', 'College of Computing & Information Sciences', 'BS Computer Science', 1, '2026-11-10 00:03:20', NULL, NULL),
(173, 'SNSU-2025-02103', 'Angelica Ramos', 'angelica.ramos@example.com', 'College of Engineering', 'BS Civil Engineering', 1, '2026-11-10 00:03:20', NULL, NULL),
(174, 'SNSU-2024-03204', 'Jerome Castillo', 'jerome.castillo@example.com', 'College of Engineering', 'BS Electrical Engineering', 1, '2026-11-10 00:03:20', NULL, NULL),
(175, 'SNSU-2024-03205', 'Rhea Mae Aquino', 'rhea.aquino@example.com', 'College of Technology', 'BS Industrial Technology', 1, '2026-11-10 00:03:20', NULL, NULL),
(176, 'SNSU-2024-03206', 'Vincent Paul Domingo', NULL, 'College of Education', 'Bachelor of Secondary Education', 1, '2026-11-10 00:03:20', NULL, NULL),
(177, 'SNSU-2023-04307', 'Patricia Anne Villareal', 'patricia.villareal@example.com', 'College of Arts & Sciences', 'BS Mathematics', 1, '2026-11-10 00:03:20', NULL, NULL),
(178, 'SNSU-2023-04308', 'Gonzales, Nathaniel B.', 'nathaniel.gonzales@example.com', 'College of Business & Management', 'BS Business Administration', 1, '2026-11-10 00:03:20', NULL, NULL),
(179, 'SNSU-2022-05409', 'Shiela Marie Cabrera', 'shiela.cabrera@example.com', 'College of Arts & Sciences', 'AB English Language', 1, '2026-11-10 00:03:20', NULL, NULL),
(180, 'SNSU-2022-05410', 'Rodel Santiago Jr.', 'rodel.santiago@example.com', 'College of Technology', 'BS Information Systems', 1, '2026-11-10 00:03:20', NULL, NULL),
(548, 'SNSU-2025-07777', 'Kevin Lim', 'kevin.lim@gmail.com', 'College of Computing & Information Sciences', 'BS Computer Science', 1, '2026-11-10 23:03:04', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `administrators`
--
ALTER TABLE `administrators`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `idx_activity_admin` (`admin_id`,`created_at`),
  ADD KEY `idx_activity_created` (`created_at`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `chat_presence`
--
ALTER TABLE `chat_presence`
  ADD PRIMARY KEY (`user_type`,`user_id`),
  ADD KEY `idx_presence_typing` (`typing_conversation_id`);

--
-- Indexes for table `concerns`
--
ALTER TABLE `concerns`
  ADD PRIMARY KEY (`concern_id`),
  ADD UNIQUE KEY `ticket_id` (`ticket_id`),
  ADD KEY `ticket_id_2` (`ticket_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `status_id` (`status_id`),
  ADD KEY `priority` (`priority`),
  ADD KEY `idx_concerns_sla_due` (`sla_due_at`),
  ADD KEY `idx_concerns_date_submitted` (`date_submitted`),
  ADD KEY `idx_concerns_resolved_at` (`resolved_at`),
  ADD KEY `idx_concerns_assigned` (`assigned_admin_id`),
  ADD KEY `idx_concerns_archived` (`archived_at`);

--
-- Indexes for table `concern_attachments`
--
ALTER TABLE `concern_attachments`
  ADD PRIMARY KEY (`attachment_id`),
  ADD UNIQUE KEY `uq_attachment_concern` (`concern_id`);

--
-- Indexes for table `concern_audit_logs`
--
ALTER TABLE `concern_audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `concern_id` (`concern_id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`conversation_id`),
  ADD UNIQUE KEY `uq_conversation_student` (`student_id`),
  ADD KEY `idx_conversation_last` (`last_message_at`);

--
-- Indexes for table `email_outbox`
--
ALTER TABLE `email_outbox`
  ADD PRIMARY KEY (`email_id`),
  ADD KEY `idx_outbox_status` (`status`,`attempts`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD UNIQUE KEY `uq_feedback_concern` (`concern_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `fk_feedback_replied_by` (`replied_by`);

--
-- Indexes for table `iso_evaluation_results`
--
ALTER TABLE `iso_evaluation_results`
  ADD PRIMARY KEY (`characteristic`),
  ADD KEY `fk_iso_updated_by` (`updated_by`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `idx_login_identifier` (`identifier`,`attempted_at`),
  ADD KEY `idx_login_ip` (`ip_address`,`attempted_at`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_message_conversation` (`conversation_id`,`message_id`),
  ADD KEY `idx_message_unread` (`sender_type`,`read_at`,`conversation_id`),
  ADD KEY `idx_message_updated` (`conversation_id`,`updated_at`);

--
-- Indexes for table `message_hidden`
--
ALTER TABLE `message_hidden`
  ADD PRIMARY KEY (`message_id`,`user_type`,`user_id`);

--
-- Indexes for table `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD PRIMARY KEY (`message_id`,`user_type`,`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notif_recipient` (`recipient_type`,`recipient_id`,`is_read`),
  ADD KEY `fk_notif_concern` (`concern_id`);

--
-- Indexes for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_reset_user` (`user_type`,`user_id`,`status`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`token_id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `idx_reset_token_user` (`user_type`,`user_id`);

--
-- Indexes for table `profile_photos`
--
ALTER TABLE `profile_photos`
  ADD PRIMARY KEY (`owner_type`,`owner_id`);

--
-- Indexes for table `registration_rejections`
--
ALTER TABLE `registration_rejections`
  ADD PRIMARY KEY (`rejection_id`),
  ADD KEY `idx_rejections_student_no` (`student_no`);

--
-- Indexes for table `responses`
--
ALTER TABLE `responses`
  ADD PRIMARY KEY (`response_id`),
  ADD KEY `concern_id` (`concern_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `fk_response_status` (`status_id`);

--
-- Indexes for table `schema_migrations`
--
ALTER TABLE `schema_migrations`
  ADD PRIMARY KEY (`migration`);

--
-- Indexes for table `statuses`
--
ALTER TABLE `statuses`
  ADD PRIMARY KEY (`status_id`),
  ADD UNIQUE KEY `status_name` (`status_name`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `student_no` (`student_no`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_students_approved_by` (`approved_by`);

--
-- Indexes for table `student_masterlist`
--
ALTER TABLE `student_masterlist`
  ADD PRIMARY KEY (`masterlist_id`),
  ADD UNIQUE KEY `uq_masterlist_student_no` (`student_no`),
  ADD KEY `fk_masterlist_imported_by` (`imported_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `administrators`
--
ALTER TABLE `administrators`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `concerns`
--
ALTER TABLE `concerns`
  MODIFY `concern_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `concern_attachments`
--
ALTER TABLE `concern_attachments`
  MODIFY `attachment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `concern_audit_logs`
--
ALTER TABLE `concern_audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `conversation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `email_outbox`
--
ALTER TABLE `email_outbox`
  MODIFY `email_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

--
-- AUTO_INCREMENT for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `token_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registration_rejections`
--
ALTER TABLE `registration_rejections`
  MODIFY `rejection_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `responses`
--
ALTER TABLE `responses`
  MODIFY `response_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `statuses`
--
ALTER TABLE `statuses`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `student_masterlist`
--
ALTER TABLE `student_masterlist`
  MODIFY `masterlist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=549;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD CONSTRAINT `fk_activity_admin` FOREIGN KEY (`admin_id`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcement_admin` FOREIGN KEY (`admin_id`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `concerns`
--
ALTER TABLE `concerns`
  ADD CONSTRAINT `fk_concern_assigned_admin` FOREIGN KEY (`assigned_admin_id`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_concern_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `fk_concern_status` FOREIGN KEY (`status_id`) REFERENCES `statuses` (`status_id`),
  ADD CONSTRAINT `fk_concern_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `concern_attachments`
--
ALTER TABLE `concern_attachments`
  ADD CONSTRAINT `fk_attachment_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`concern_id`) ON DELETE CASCADE;

--
-- Constraints for table `concern_audit_logs`
--
ALTER TABLE `concern_audit_logs`
  ADD CONSTRAINT `fk_audit_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`concern_id`) ON DELETE CASCADE;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `fk_conversation_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_feedback_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`concern_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_feedback_replied_by` FOREIGN KEY (`replied_by`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_feedback_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE SET NULL;

--
-- Constraints for table `iso_evaluation_results`
--
ALTER TABLE `iso_evaluation_results`
  ADD CONSTRAINT `fk_iso_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_message_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`conversation_id`) ON DELETE CASCADE;

--
-- Constraints for table `message_hidden`
--
ALTER TABLE `message_hidden`
  ADD CONSTRAINT `fk_hidden_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`message_id`) ON DELETE CASCADE;

--
-- Constraints for table `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD CONSTRAINT `fk_reaction_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`message_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`concern_id`) ON DELETE CASCADE;

--
-- Constraints for table `responses`
--
ALTER TABLE `responses`
  ADD CONSTRAINT `fk_response_admin` FOREIGN KEY (`admin_id`) REFERENCES `administrators` (`admin_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_response_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`concern_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_response_status` FOREIGN KEY (`status_id`) REFERENCES `statuses` (`status_id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `student_masterlist`
--
ALTER TABLE `student_masterlist`
  ADD CONSTRAINT `fk_masterlist_imported_by` FOREIGN KEY (`imported_by`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
