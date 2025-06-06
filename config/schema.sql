-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 06, 2025 at 09:40 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hostel_db`
--
CREATE DATABASE IF NOT EXISTS `hostel_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `hostel_db`;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `hostel_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `status` enum('Pending','Confirmed','Cancelled','Completed') DEFAULT 'Pending',
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `special_requests` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `hostel_id`, `room_id`, `user_id`, `check_in_date`, `check_out_date`, `semester`, `academic_year`, `status`, `booking_date`, `special_requests`) VALUES
(3, 19, 6, 28, '2025-05-31', '2025-10-23', NULL, NULL, 'Confirmed', '2025-05-31 01:56:51', '');

-- --------------------------------------------------------

--
-- Table structure for table `hostels`
--

DROP TABLE IF EXISTS `hostels`;
CREATE TABLE `hostels` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amenities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`amenities`)),
  `total_rooms` int(11) DEFAULT 0,
  `available_rooms` int(11) DEFAULT 0,
  `status` enum('Active','Inactive') DEFAULT 'Inactive',
  `contact` varchar(50) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hostels`
--

INSERT INTO `hostels` (`id`, `name`, `location`, `description`, `amenities`, `total_rooms`, `available_rooms`, `status`, `contact`, `image_path`, `manager_id`) VALUES
(19, 'New Life', 'Near Main Campus Gulu University', 'Welcome to New life Hostel; where Eternal Life is abundant', '[\"WiFi\",\"24\\/7 Security\",\"Water\"]', 88, 0, 'Active', '0778342178', 'assets/uploads/68367d22bb374_hostel-1.jpg', 27);

-- --------------------------------------------------------

--
-- Table structure for table `hostel_settings`
--

DROP TABLE IF EXISTS `hostel_settings`;
CREATE TABLE `hostel_settings` (
  `id` int(11) NOT NULL,
  `hostel_id` int(11) NOT NULL,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `type` enum('room_availability','payment_pending','new_booking','maintenance_request') NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `hostel_id` int(11) NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `recipient_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `expiry_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `title`, `message`, `hostel_id`, `related_id`, `recipient_id`, `created_at`, `is_read`, `priority`, `expiry_date`) VALUES
(6, 'new_booking', 'New Booking Request', 'A new booking request has been made for your hostel.', 19, 3, 27, '2025-05-31 01:56:51', 0, 'medium', NULL),
(7, '', 'Payment Received', 'Payment of 650,000 UGX has been received for booking #3.', 19, 3, 27, '2025-05-31 01:57:08', 0, 'medium', NULL),
(8, '', 'Room Assigned', 'Your room has been assigned: single-09', 19, 3, 28, '2025-05-31 02:39:53', 0, 'medium', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `hostel_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_type` enum('Deposit','Full Payment','Installment','Security Deposit','Late Fee') DEFAULT 'Full Payment',
  `status` enum('Pending','Completed','Failed','Refunded') DEFAULT 'Pending',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `hostel_id`, `user_id`, `amount`, `payment_date`, `payment_method`, `transaction_id`, `payment_type`, `status`, `notes`) VALUES
(3, 3, 19, 28, 650000.00, '2025-05-31 01:57:07', 'MTN Mobile Money', 'TEST-59FAEF3246', 'Full Payment', 'Completed', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

DROP TABLE IF EXISTS `payment_methods`;
CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `hostel_id` int(11) NOT NULL,
  `method_name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `instructions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_logs`
--

DROP TABLE IF EXISTS `report_logs`;
CREATE TABLE `report_logs` (
  `id` int(11) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `hostel_id` int(11) NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `format` varchar(10) NOT NULL,
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parameters`)),
  `file_path` varchar(255) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_templates`
--

DROP TABLE IF EXISTS `report_templates`;
CREATE TABLE `report_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('booking','payment','occupancy','revenue','custom') NOT NULL,
  `format` enum('pdf','excel','csv','html') NOT NULL,
  `time_period` enum('daily','weekly','monthly','quarterly','yearly','custom') NOT NULL,
  `user_id` int(11) NOT NULL,
  `hostel_id` int(11) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `last_generated` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `hostel_id` int(11) NOT NULL,
  `room_type` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `status` enum('Available','Occupied','Maintenance') DEFAULT 'Available',
  `price_per_semester` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `hostel_id`, `room_type`, `quantity`, `status`, `price_per_semester`, `created_at`, `updated_at`) VALUES
(5, 19, 'double', 72, 'Available', 900000.00, '2025-05-28 13:04:30', '2025-05-28 13:04:30'),
(6, 19, 'single', 10, 'Available', 650000.00, '2025-05-30 03:19:24', '2025-05-30 03:19:24');

-- --------------------------------------------------------

--
-- Table structure for table `room_assignments`
--

DROP TABLE IF EXISTS `room_assignments`;
CREATE TABLE `room_assignments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `assigned_room_number` varchar(20) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_assignments`
--

INSERT INTO `room_assignments` (`id`, `booking_id`, `room_id`, `assigned_room_number`, `assigned_at`) VALUES
(2, 3, 6, 'single-09', '2025-05-31 02:39:52');

-- --------------------------------------------------------

--
-- Table structure for table `room_numbering_templates`
--

DROP TABLE IF EXISTS `room_numbering_templates`;
CREATE TABLE `room_numbering_templates` (
  `id` int(11) NOT NULL,
  `hostel_id` int(11) NOT NULL,
  `room_type` varchar(100) NOT NULL,
  `prefix` varchar(10) DEFAULT NULL,
  `start_number` int(11) DEFAULT 1,
  `padding` int(11) DEFAULT 2,
  `floor_prefix` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_numbering_templates`
--

INSERT INTO `room_numbering_templates` (`id`, `hostel_id`, `room_type`, `prefix`, `start_number`, `padding`, `floor_prefix`) VALUES
(2, 19, 'single', 'NL', 1, 3, 0),
(3, 19, 'double', 'NL', 1, 3, 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('manager','student','admin') NOT NULL DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remember_token` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL DEFAULT current_timestamp(),
  `modified_by` int(11) NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `created_at`, `remember_token`, `created_by`, `modified_by`) VALUES
(27, 'levelup', 'levelup@gmail.com', '$2y$10$9VfpE3D.OhOOsdZqCKdFGOdSdFLYQu8IBMH0Jj7lvpC5vcIOUyMdK', 'manager', '2025-05-27 09:35:21', NULL, 27, 27),
(28, 'level', 'level@gmail.com', '$2y$10$QjvaoY0MDPEkmub8iTGSs.BAK3SuUaG5G5oYaD1NqA7qsUF/GmBMG', 'student', '2025-05-27 09:36:34', NULL, 28, 28);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hostel_id` (`hostel_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `check_in_date` (`check_in_date`,`check_out_date`);

--
-- Indexes for table `hostels`
--
ALTER TABLE `hostels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `hostel_settings`
--
ALTER TABLE `hostel_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hostel_setting_unique` (`hostel_id`,`setting_name`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hostel_id` (`hostel_id`),
  ADD KEY `recipient_id` (`recipient_id`,`is_read`),
  ADD KEY `type` (`type`,`created_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `hostel_id` (`hostel_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `payment_date` (`payment_date`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hostel_id` (`hostel_id`,`method_name`);

--
-- Indexes for table `report_logs`
--
ALTER TABLE `report_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`),
  ADD KEY `hostel_id` (`hostel_id`),
  ADD KEY `user_id` (`user_id`,`generated_at`);

--
-- Indexes for table `report_templates`
--
ALTER TABLE `report_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `hostel_id` (`hostel_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hostel_id` (`hostel_id`);

--
-- Indexes for table `room_assignments`
--
ALTER TABLE `room_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `room_numbering_templates`
--
ALTER TABLE `room_numbering_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hostel_id` (`hostel_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `remember_token` (`remember_token`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `hostels`
--
ALTER TABLE `hostels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `hostel_settings`
--
ALTER TABLE `hostel_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `report_logs`
--
ALTER TABLE `report_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_templates`
--
ALTER TABLE `report_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `room_assignments`
--
ALTER TABLE `room_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `room_numbering_templates`
--
ALTER TABLE `room_numbering_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hostels`
--
ALTER TABLE `hostels`
  ADD CONSTRAINT `hostels_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `hostel_settings`
--
ALTER TABLE `hostel_settings`
  ADD CONSTRAINT `fk_hostel_settings_hostel` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD CONSTRAINT `payment_methods_ibfk_1` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `report_logs`
--
ALTER TABLE `report_logs`
  ADD CONSTRAINT `report_logs_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `report_templates` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `report_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `report_logs_ibfk_3` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `report_templates`
--
ALTER TABLE `report_templates`
  ADD CONSTRAINT `report_templates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `report_templates_ibfk_2` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_assignments`
--
ALTER TABLE `room_assignments`
  ADD CONSTRAINT `room_assignments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_assignments_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_numbering_templates`
--
ALTER TABLE `room_numbering_templates`
  ADD CONSTRAINT `room_numbering_templates_ibfk_1` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
