-- ICMS-DPT-RRT Database Seed Data
-- Realistic mock records matching Figures 1-15 of SNSU Capstone Document

USE `icms_dpt_rrt`;

-- 1. Statuses
INSERT INTO `statuses` (`status_id`, `status_name`) VALUES
(1, 'Pending'),
(2, 'In Progress'),
(3, 'Resolved')
ON DUPLICATE KEY UPDATE `status_name` = VALUES(`status_name`);

-- 2. Categories
INSERT INTO `categories` (`category_id`, `category_name`, `description`) VALUES
(1, 'Facilities', 'Physical infrastructure, classrooms, restrooms, air-conditioning, lights, and lab equipment.'),
(2, 'Academic', 'Grading inquiries, examination schedules, faculty concerns, and course curriculum.'),
(3, 'Services', 'Campus IT network, student ID card printing, library services, and canteen.'),
(4, 'Student Welfare', 'Guidance, scholarships, health services, and student organizations.')
ON DUPLICATE KEY UPDATE `category_name` = VALUES(`category_name`);

-- 3. Students
-- Default Password for all demo students: student123
INSERT INTO `students` (`student_id`, `student_no`, `name`, `email`, `password`, `department`, `program`, `status`, `created_at`) VALUES
(1, 'SNSU-2022-04189', 'Juan Dela Cruz', 'juan.delacruz@student.com', '$2y$12$9Tlvd1H4YMJOzIhC2nL0F.3.5W8jx51k.BtBZvlYthvTn3y2YQLQm', 'College of Computing & Information Sciences', 'BS Information Technology', 'Active', '2024-04-15 08:30:00'),
(2, 'SNSU-2023-01024', 'Maria Santos', 'maria.santos@school.edu', '$2y$12$9Tlvd1H4YMJOzIhC2nL0F.3.5W8jx51k.BtBZvlYthvTn3y2YQLQm', 'College of Computing & Information Sciences', 'BS Computer Science', 'Active', '2024-04-20 09:15:00'),
(3, 'SNSU-2021-08732', 'John Reyes', 'john.reyes@school.edu', '$2y$12$9Tlvd1H4YMJOzIhC2nL0F.3.5W8jx51k.BtBZvlYthvTn3y2YQLQm', 'College of Technology', 'BS Industrial Technology', 'Active', '2024-04-22 10:00:00'),
(4, 'SNSU-2022-03112', 'Pedro Reyes', 'pedro.reyes@school.edu', '$2y$12$9Tlvd1H4YMJOzIhC2nL0F.3.5W8jx51k.BtBZvlYthvTn3y2YQLQm', 'College of Engineering', 'BS Civil Engineering', 'Active', '2024-04-25 11:20:00'),
(5, 'SNSU-2023-05490', 'Anne Garcia', 'anne.garcia@school.edu', '$2y$12$9Tlvd1H4YMJOzIhC2nL0F.3.5W8jx51k.BtBZvlYthvTn3y2YQLQm', 'College of Education', 'BSEd Major in English', 'Active', '2024-05-01 14:00:00'),
(6, 'SNSU-2022-09871', 'Mark Villanueva', 'mark.villanueva@school.edu', '$2y$12$9Tlvd1H4YMJOzIhC2nL0F.3.5W8jx51k.BtBZvlYthvTn3y2YQLQm', 'College of Computing & Information Sciences', 'BS Information Systems', 'Active', '2024-05-02 08:45:00')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

-- 4. Administrators
-- Default Password for administrators: admin123
INSERT INTO `administrators` (`admin_id`, `name`, `email`, `password`, `department`, `role`, `status`, `created_at`) VALUES
(1, 'Admin User', 'admin@school.edu', '$2y$12$3mKzfLC8paoDd8DbrngpoORAb6jDcXOdItroijuMq/F4o9WGhu1Au', 'Administration', 'Administrator', 'Active', '2024-01-10 08:00:00'),
(2, 'Engr. Carlos Mendoza', 'facilities@snsu.edu.ph', '$2y$12$3mKzfLC8paoDd8DbrngpoORAb6jDcXOdItroijuMq/F4o9WGhu1Au', 'Facilities & Maintenance', 'Department Head', 'Active', '2024-01-15 09:30:00'),
(3, 'Dr. Elena Ramos', 'ccis.dean@snsu.edu.ph', '$2y$12$3mKzfLC8paoDd8DbrngpoORAb6jDcXOdItroijuMq/F4o9WGhu1Au', 'College of Computing & Information Sciences', 'Dean', 'Active', '2024-02-01 10:00:00')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

-- 5. Concerns (Matching Figures 2, 5, 10, 15)
INSERT INTO `concerns` (`concern_id`, `ticket_id`, `student_id`, `category_id`, `status_id`, `title`, `description`, `attachment_path`, `priority`, `ai_urgency_score`, `sla_hours`, `is_rrt_alert`, `date_submitted`) VALUES
(1, 'SCF-2024-001', 1, 3, 2, 'ID printing machine not working', 'The card printer in the student services office is malfunctioning and jamming cards.', NULL, 'Low', 35, 120, 0, '2024-05-03 09:14:00'),
(2, 'SCF-2024-002', 1, 1, 3, 'Canteen foods are always cold', 'Food served in the central canteen warmers is frequently served cold during lunch breaks.', NULL, 'Low', 28, 120, 0, '2024-05-05 12:30:00'),
(3, 'SCF-2024-003', 1, 2, 3, 'Clarification about exam schedule', 'There is an overlap between IT 312 and CS 311 mid-term examination rooms.', NULL, 'Medium', 52, 72, 0, '2024-05-08 14:15:00'),
(4, 'SCF-2024-004', 1, 3, 1, 'Internet connection issue in Library', 'Wi-Fi connection drops every 5 minutes on the 2nd floor library study area.', NULL, 'Medium', 58, 72, 0, '2024-05-10 11:00:00'),
(5, 'SCF-2024-005', 1, 1, 2, 'Broken chairs in classroom Lab 2', 'There are several broken chairs in our classroom Lab 2. It is uncomfortable and unsafe to use. Please kindly address this issue. Thank you.', 'uploads/broken_chair.jpg', 'High', 78, 48, 0, '2024-05-12 09:30:00'),
(6, 'SCF-2024-028', 6, 2, 3, 'Online class attendance error', 'Attendance in LMS for May 2 meeting marked absent despite submitting activity on time.', NULL, 'Medium', 45, 72, 0, '2024-05-02 16:20:00'),
(7, 'SCF-2024-029', 5, 1, 1, 'Comfort room is not clean 3rd floor', 'The 3rd floor west wing comfort room lacks running water and sanitation supplies.', NULL, 'Medium', 54, 72, 0, '2024-05-05 15:10:00'),
(8, 'SCF-2024-030', 4, 2, 2, 'Need more reference books for Academic', 'Library currently has only one copy of Modern Database Systems for 120 enrolled students.', NULL, 'Low', 32, 120, 0, '2024-05-08 10:45:00'),
(9, 'SCF-2024-031', 2, 3, 2, 'ID printing machine error', 'Queuing error in printing machine software causes repeated duplicate student numbers.', NULL, 'Medium', 50, 72, 0, '2024-05-10 13:25:00'),
(10, 'SCF-2024-032', 1, 1, 1, 'Projector not working in Rm 304', 'The ceiling projector HDMI port in Room 304 is completely detached and not displaying output.', NULL, 'High', 74, 48, 0, '2024-05-12 08:15:00'),
(11, 'SCF-2024-033', 1, 1, 1, 'Exposed live wiring and water leak near computer terminals in CCIS Lab 1', 'Water is dripping from the AC unit directly onto an exposed 220V power extension strip behind PC terminals 12-16. Sparks were observed. Immediate safety hazard!', 'uploads/lab1_hazard.jpg', 'Critical', 98, 24, 1, '2024-05-13 07:50:00')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- 6. Responses
INSERT INTO `responses` (`response_id`, `concern_id`, `admin_id`, `response_text`, `department_endorsed`, `status_id`, `date_responded`) VALUES
(1, 5, 1, 'We have already endorsed this to the maintenance team. Thank you.', 'Facilities & Maintenance', 2, '2024-05-13 10:15:00'),
(2, 3, 1, 'Exam room schedule reconciled with CCIS Dean office. IT 312 moved to Rm 402.', 'Academic Affairs / CCIS', 3, '2024-05-09 11:30:00'),
(3, 2, 1, 'Meeting held with canteen concessionaire. Food warmer heating elements inspected and replaced.', 'Student Services', 3, '2024-05-06 14:00:00'),
(4, 1, 1, 'Technician dispatched from vendor to service ID printer card feeder.', 'MIS & IT Services', 2, '2024-05-04 10:00:00'),
(5, 11, 2, 'RAPID RESPONSE TEAM DISPATCHED: CCIS Lab 1 main circuit breaker switched off. Facilities electrical crew en route with high priority.', 'Facilities & Maintenance', 1, '2024-05-13 08:05:00')
ON DUPLICATE KEY UPDATE `response_text` = VALUES(`response_text`);

-- 7. Announcements (Matching Figures 7 & 12)
INSERT INTO `announcements` (`announcement_id`, `admin_id`, `title`, `content`, `category`, `is_urgent`, `published_at`) VALUES
(1, 1, 'Scheduled Maintenance', 'The system will undergo maintenance on May 20, 2024 from 1:00 AM to 3:00 AM. Please save any pending work ahead of time.', 'Maintenance', 0, '2024-05-15 09:00:00'),
(2, 1, 'ID Printing Schedule', 'ID printing will be available from May 18 to May 25, 2024 at the Admin Office between 8:00 AM and 4:00 PM.', 'Services', 0, '2024-05-14 10:30:00'),
(3, 1, 'Class Suspension', 'Classes are suspended on May 17, 2024 (Friday) for the university annual foundation anniversary and school events.', 'Academic', 1, '2024-05-13 08:00:00')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- 8. Feedback (Matching Figures 3 & 13)
INSERT INTO `feedback` (`feedback_id`, `student_id`, `student_name`, `student_email`, `feedback_type`, `subject`, `message`, `rating`, `submitted_at`) VALUES
(1, 1, 'Juan Dela Cruz', 'juan.delacruz@student.com', 'System Feedback', 'Very convenient concern tracking system', 'The real-time status tracking and transparent administrator replies help us follow up on issues without visiting offices physically.', 5, '2024-05-12 15:30:00'),
(2, 2, 'Maria Santos', 'maria.santos@school.edu', 'Facilities Feedback', 'Prompt action on room cleaning', 'Appreciate the quick response to our restroom cleaning report last week.', 4, '2024-05-10 16:45:00')
ON DUPLICATE KEY UPDATE `subject` = VALUES(`subject`);
