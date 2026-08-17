-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 17, 2026 at 12:51 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `food_rescue`
--

-- --------------------------------------------------------

--
-- Table structure for table `claim`
--

CREATE TABLE `claim` (
  `claim_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `portion_claimed` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reservation_expires_at` datetime NOT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `status` enum('pending','confirmed','completed','expired','cancelled') NOT NULL DEFAULT 'pending'
) ;

--
-- Dumping data for table `claim`
--

INSERT INTO `claim` (`claim_id`, `listing_id`, `student_id`, `portion_claimed`, `created_at`, `reservation_expires_at`, `confirmed_at`, `status`) VALUES
(1, 1, 5, 1, '2026-08-16 01:12:43', '2026-08-16 01:22:43', NULL, 'pending'),
(2, 1, 6, 1, '2026-08-16 01:13:43', '2026-08-16 01:23:43', NULL, 'pending'),
(3, 1, 9, 1, '2026-08-15 23:17:43', '2026-08-15 23:27:43', '2026-08-15 23:22:43', 'completed'),
(4, 2, 5, 2, '2026-08-15 22:17:43', '2026-08-15 22:27:43', '2026-08-15 22:25:43', 'completed'),
(5, 2, 9, 3, '2026-08-15 22:17:43', '2026-08-15 22:28:43', '2026-08-15 22:26:43', 'completed'),
(6, 3, 6, 1, '2026-08-16 01:14:43', '2026-08-16 01:24:43', NULL, 'pending'),
(7, 5, 5, 1, '2026-08-15 20:17:43', '2026-08-15 20:27:43', NULL, 'expired'),
(8, 7, 9, 1, '2026-08-16 00:37:43', '2026-08-16 00:47:43', NULL, 'cancelled'),
(9, 10, 5, 1, '2026-08-15 23:47:43', '2026-08-15 23:57:43', '2026-08-15 23:55:43', 'completed'),
(10, 8, 6, 1, '2026-08-16 01:09:43', '2026-08-16 01:19:43', NULL, 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `claim_tokens`
--

CREATE TABLE `claim_tokens` (
  `token_id` int(11) NOT NULL,
  `claim_id` int(11) NOT NULL,
  `token_string` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL
) ;

--
-- Dumping data for table `claim_tokens`
--

INSERT INTO `claim_tokens` (`token_id`, `claim_id`, `token_string`, `created_at`, `expires_at`, `used_at`) VALUES
(1, 1, 'f8a6db00518ef9bd21d4f2dbe6309968e1168c3e2412623138324d3b1fae405b', '2026-08-16 01:12:43', '2026-08-16 01:22:43', NULL),
(2, 2, '159a7d66004d8a8f89eb0f73a3d88f2620306e7df53012cbb791b3e610d0f962', '2026-08-16 01:13:43', '2026-08-16 01:23:43', NULL),
(3, 3, '9c0758b64130b2324c8259185f136efc898362edd78fd9d7e00331bdcf9b0ba5', '2026-08-15 23:17:43', '2026-08-15 23:27:43', '2026-08-15 23:22:43'),
(4, 4, 'c7fa020575623352134227e659e7df200bd30d8943418804b3fb4811af413ba5', '2026-08-15 22:17:43', '2026-08-15 22:27:43', '2026-08-15 22:25:43'),
(5, 5, '41b0ab9109412c6cabacf002578024ad691628fa0188bf85a9f6d5617d831ed3', '2026-08-15 22:17:43', '2026-08-15 22:28:43', '2026-08-15 22:26:43'),
(6, 6, 'f0000d339c0f2e0d9377b64108c1cd579212d3de5f53f9b5499f9ad703573105', '2026-08-16 01:14:43', '2026-08-16 01:24:43', NULL),
(7, 9, '34028414eece1f79e7f5117999bde39e93b8df7aaf45802a2980a919ff4acd06', '2026-08-15 23:47:43', '2026-08-15 23:57:43', '2026-08-15 23:55:43'),
(8, 10, '39695f4292a42e24931adc7cc30d39e0f9ffa755c8a044abe48c3bcec1883126', '2026-08-16 01:09:43', '2026-08-16 01:19:43', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `food_listing`
--

CREATE TABLE `food_listing` (
  `listing_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `food_name` varchar(200) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `total_quantity` int(11) NOT NULL,
  `remain_quantity` int(11) NOT NULL,
  `weight_kg` decimal(6,2) DEFAULT NULL,
  `pickup_location` varchar(200) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('pending','active','expired','removed') NOT NULL DEFAULT 'pending',
  `expires_at` datetime NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `food_listing`
--

INSERT INTO `food_listing` (`listing_id`, `provider_id`, `food_name`, `description`, `total_quantity`, `remain_quantity`, `weight_kg`, `pickup_location`, `image`, `status`, `expires_at`, `approved_by`, `approved_at`, `created_at`) VALUES
(1, 1, 'Nasi Lemak Set', 'Leftover from morning rush. Still warm.', 8, 3, 2.80, 'Mamak Stall Counter, Block A GF', NULL, 'active', '2026-08-16 02:47:43', 1, '2026-08-16 00:57:43', '2026-08-16 00:47:43'),
(2, 1, 'Roti Canai Bundle', 'Set of 5 with dhal. Fully claimed.', 5, 0, 1.50, 'Mamak Stall Counter, Block A GF', NULL, 'active', '2026-08-16 02:17:43', 1, '2026-08-16 00:37:43', '2026-08-16 00:32:43'),
(3, 1, 'Mee Goreng Portions', 'Spicy fried noodles. Come now!', 4, 4, 1.20, 'Mamak Stall Counter, Block A GF', NULL, 'active', '2026-08-16 01:21:43', 1, '2026-08-15 23:27:43', '2026-08-15 23:17:43'),
(4, 1, 'Kuih Assortment', 'Assorted kuih from event. 20 pieces.', 20, 20, 3.00, 'Mamak Stall Counter, Block A GF', NULL, 'pending', '2026-08-16 04:17:43', NULL, NULL, '2026-08-16 01:07:43'),
(5, 1, 'Chicken Rice Set', 'Yesterday lunch. Now expired.', 10, 6, 3.50, 'Mamak Stall Counter, Block A GF', NULL, 'expired', '2026-08-15 23:17:43', 1, '2026-08-15 19:17:43', '2026-08-15 20:17:43'),
(6, 1, 'Mystery Box Food', 'Unknown content. Removed by admin.', 100, 100, 0.00, 'Unknown location', NULL, 'removed', '2026-08-17 01:17:43', NULL, NULL, '2026-08-16 00:17:43'),
(7, 2, 'Chicken Chop Plate', 'Western set from staff event.', 6, 4, 4.20, 'APU Canteen, Block C L1', NULL, 'active', '2026-08-16 01:42:43', 1, '2026-08-16 00:22:43', '2026-08-16 00:17:43'),
(8, 2, 'Mixed Salad Box', 'Fresh vegan salad boxes.', 10, 7, 1.80, 'APU Canteen, Block C L1', NULL, 'active', '2026-08-16 03:17:43', 1, '2026-08-16 01:02:43', '2026-08-16 00:57:43'),
(9, 2, 'Pasta Bake', 'Vegetarian pasta from staff event.', 12, 12, 5.00, 'APU Canteen, Block C L1', NULL, 'pending', '2026-08-16 05:17:43', NULL, NULL, '2026-08-16 01:12:43'),
(10, 1, 'Fried Rice Portions', 'For impact record testing.', 5, 3, 2.00, 'Mamak Stall Counter, Block A GF', NULL, 'active', '2026-08-16 02:17:43', 1, '2026-08-15 23:47:43', '2026-08-15 23:17:43');

-- --------------------------------------------------------

--
-- Table structure for table `food_listing_tags`
--

CREATE TABLE `food_listing_tags` (
  `food_listing_tags_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `food_listing_tags`
--

INSERT INTO `food_listing_tags` (`food_listing_tags_id`, `listing_id`, `tag_id`) VALUES
(1, 1, 1),
(2, 1, 7),
(4, 2, 1),
(5, 2, 3),
(7, 3, 1),
(8, 3, 7),
(11, 4, 1),
(12, 4, 3),
(10, 4, 4),
(13, 5, 1),
(14, 7, 1),
(17, 8, 2),
(18, 8, 3),
(15, 8, 4),
(16, 8, 5),
(23, 9, 3),
(22, 9, 6),
(25, 10, 1);

-- --------------------------------------------------------

--
-- Table structure for table `food_tags`
--

CREATE TABLE `food_tags` (
  `tag_id` int(11) NOT NULL,
  `tag_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `food_tags`
--

INSERT INTO `food_tags` (`tag_id`, `tag_name`) VALUES
(6, 'Contains nuts'),
(4, 'Dairy-free'),
(5, 'Gluten-free'),
(1, 'Halal'),
(7, 'Spicy'),
(2, 'Vegan'),
(3, 'Vegetarian');

-- --------------------------------------------------------

--
-- Table structure for table `impact_record`
--

CREATE TABLE `impact_record` (
  `impact_id` int(11) NOT NULL,
  `claim_id` int(11) NOT NULL,
  `co2_saved_kg` decimal(8,3) NOT NULL DEFAULT 0.000,
  `water_saved_litre` decimal(8,2) NOT NULL DEFAULT 0.00,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `impact_record`
--

INSERT INTO `impact_record` (`impact_id`, `claim_id`, `co2_saved_kg`, `water_saved_litre`, `recorded_at`) VALUES
(1, 3, 0.875, 17.50, '2026-08-15 23:22:43'),
(2, 4, 1.500, 30.00, '2026-08-15 22:25:43'),
(3, 5, 2.250, 45.00, '2026-08-15 22:26:43'),
(4, 9, 1.000, 20.00, '2026-08-15 23:55:43');

-- --------------------------------------------------------

--
-- Table structure for table `listing_audit_log`
--

CREATE TABLE `listing_audit_log` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `action_type` enum('approve_listing','reject_listing','remove_listing') NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `performed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `listing_audit_log`
--

INSERT INTO `listing_audit_log` (`log_id`, `admin_id`, `listing_id`, `action_type`, `notes`, `performed_at`) VALUES
(1, 1, 1, 'approve_listing', 'Looks legit.', '2026-08-16 00:57:43'),
(2, 1, 2, 'approve_listing', 'Verified with provider.', '2026-08-16 00:37:43'),
(3, 1, 3, 'approve_listing', 'Urgent listing, fast tracked.', '2026-08-15 23:27:43'),
(4, 1, 6, 'reject_listing', 'Unclear content. No photo.', '2026-08-16 00:22:43'),
(5, 1, 6, 'remove_listing', 'Spam listing removed.', '2026-08-16 00:23:43'),
(6, 1, 7, 'approve_listing', 'Second provider listing approved.', '2026-08-16 00:22:43'),
(7, 1, 8, 'approve_listing', 'Vegan listing verified.', '2026-08-16 01:02:43'),
(8, 1, 10, 'approve_listing', 'Approved for testing.', '2026-08-15 23:47:43'),
(9, 1, 5, 'approve_listing', 'Was active, now expired.', '2026-08-15 19:17:43');

-- --------------------------------------------------------

--
-- Table structure for table `penalty_log`
--

CREATE TABLE `penalty_log` (
  `penalty_id` int(11) NOT NULL,
  `claim_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `throttle_days` int(11) NOT NULL DEFAULT 1
) ;

--
-- Dumping data for table `penalty_log`
--

INSERT INTO `penalty_log` (`penalty_id`, `claim_id`, `student_id`, `reason`, `issued_at`, `throttle_days`) VALUES
(1, 7, 5, 'No-show: claim expired without pickup', '2026-08-15 20:27:43', 1);

-- --------------------------------------------------------

--
-- Table structure for table `provider`
--

CREATE TABLE `provider` (
  `provider_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `provider_name` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `location` varchar(200) NOT NULL,
  `operating_hours` varchar(100) DEFAULT NULL,
  `request_note` varchar(300) DEFAULT NULL,
  `provider_status` enum('pending_approval','active','suspended') NOT NULL DEFAULT 'pending_approval'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `provider`
--

INSERT INTO `provider` (`provider_id`, `user_id`, `provider_name`, `contact_number`, `location`, `operating_hours`, `request_note`, `provider_status`) VALUES
(1, 2, 'Mamak Stall APU', '0123456789', 'Block A, Ground Floor', 'Mon-Sun 7:00am-8:00pm', 'We serve halal food daily and have surplus every evening.', 'active'),
(2, 3, 'APU Campus Canteen', '0198765432', 'Block C, Level 1', 'Mon-Fri 8:00am-5:00pm', 'Official APU canteen with daily surplus from buffet line.', 'active'),
(3, 4, 'Dodgy Stall', '0111234567', 'Block F, Ground Floor', 'Mon-Fri 9:00am-3:00pm', 'Suspicious listing with no real food.', 'suspended');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `user_name` varchar(30) NOT NULL,
  `email` varchar(40) NOT NULL,
  `role` enum('student','provider','admin') NOT NULL DEFAULT 'student',
  `pass_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `account_status` enum('active','banned','throttled') NOT NULL DEFAULT 'active',
  `throttled_until` datetime DEFAULT NULL,
  `no_show_count` int(11) NOT NULL DEFAULT 0,
  `security_question` varchar(200) DEFAULT NULL,
  `security_answer` varchar(255) DEFAULT NULL
) ;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `user_name`, `email`, `role`, `pass_hash`, `created_at`, `account_status`, `throttled_until`, `no_show_count`, `security_question`, `security_answer`) VALUES
(1, 'Admin APU', 'admin@gmail.com', 'admin', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LknrdDOkhhe', '2025-01-01 08:00:00', 'active', NULL, 0, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(2, 'Mamak Stall', 'mamak@gmail.com', 'provider', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LknrdDOkhhe', '2025-01-05 09:00:00', 'active', NULL, 0, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(3, 'Campus Canteen', 'canteen@gmail.com', 'provider', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LknrdDOkhhe', '2025-01-06 09:00:00', 'active', NULL, 0, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(4, 'Dodgy Stall', 'dodgy@gmail.com', 'provider', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LknrdDOkhhe', '2025-01-07 09:00:00', 'banned', NULL, 0, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(5, 'Ahmad Luqman', 'ahmad@gmail.com', 'student', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LknrdDOkhhe', '2025-01-10 10:00:00', 'active', NULL, 1, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(6, 'Priya Nair', 'priya@gmail.com', 'student', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LknrdDOkhhe', '2025-01-10 10:00:00', 'active', NULL, 1, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(7, 'Kelvin Lim', 'kelvin@gmail.com', 'student', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LknrdDOkhhe', '2025-01-10 10:00:00', 'throttled', '2026-08-18 01:17:43', 3, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(8, 'Banned Student', 'banned@gmail.com', 'student', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LknrdDOkhhe', '2025-01-10 10:00:00', 'banned', NULL, 0, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(9, 'Fatimah Zahra', 'fatimah@gmail.com', 'student', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LknrdDOkhhe', '2025-01-10 10:00:00', 'active', NULL, 0, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- --------------------------------------------------------

--
-- Table structure for table `user_audit_log`
--

CREATE TABLE `user_audit_log` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `affected_user_id` int(11) NOT NULL,
  `action_type` enum('ban_user','unban_user','warn_user','throttle_user','assign_role','approve_provider','reject_provider') NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `performed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_audit_log`
--

INSERT INTO `user_audit_log` (`log_id`, `admin_id`, `affected_user_id`, `action_type`, `notes`, `performed_at`) VALUES
(1, 1, 4, 'ban_user', 'Repeated spam listings.', '2026-08-16 00:24:43'),
(2, 1, 5, 'warn_user', 'First no-show warning.', '2026-08-15 20:37:43'),
(3, 1, 7, 'throttle_user', '3 no-shows. Throttled 2 days.', '2026-08-15 01:17:43'),
(4, 1, 8, 'ban_user', 'Test ban account.', '2026-08-11 01:17:43'),
(5, 1, 2, 'approve_provider', 'Approved Mamak Stall registration.', '2026-07-17 01:17:43'),
(6, 1, 3, 'approve_provider', 'Approved Campus Canteen registration.', '2026-07-18 01:17:43'),
(7, 1, 4, 'reject_provider', 'Rejected suspicious stall registration.', '2026-07-19 01:17:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `claim`
--
ALTER TABLE `claim`
  ADD PRIMARY KEY (`claim_id`),
  ADD UNIQUE KEY `uq_student_listing` (`student_id`,`listing_id`),
  ADD KEY `fk_claim_listing` (`listing_id`);

--
-- Indexes for table `claim_tokens`
--
ALTER TABLE `claim_tokens`
  ADD PRIMARY KEY (`token_id`),
  ADD UNIQUE KEY `uq_token_claim` (`claim_id`),
  ADD UNIQUE KEY `uq_token_string` (`token_string`);

--
-- Indexes for table `food_listing`
--
ALTER TABLE `food_listing`
  ADD PRIMARY KEY (`listing_id`),
  ADD KEY `fk_listing_provider` (`provider_id`),
  ADD KEY `fk_listing_approved_by` (`approved_by`);

--
-- Indexes for table `food_listing_tags`
--
ALTER TABLE `food_listing_tags`
  ADD PRIMARY KEY (`food_listing_tags_id`),
  ADD UNIQUE KEY `uq_listing_tag` (`listing_id`,`tag_id`),
  ADD KEY `fk_flt_tag` (`tag_id`);

--
-- Indexes for table `food_tags`
--
ALTER TABLE `food_tags`
  ADD PRIMARY KEY (`tag_id`),
  ADD UNIQUE KEY `uq_tag_name` (`tag_name`);

--
-- Indexes for table `impact_record`
--
ALTER TABLE `impact_record`
  ADD PRIMARY KEY (`impact_id`),
  ADD UNIQUE KEY `uq_impact_claim` (`claim_id`);

--
-- Indexes for table `listing_audit_log`
--
ALTER TABLE `listing_audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_listing_log_admin` (`admin_id`),
  ADD KEY `fk_listing_log_listing` (`listing_id`);

--
-- Indexes for table `penalty_log`
--
ALTER TABLE `penalty_log`
  ADD PRIMARY KEY (`penalty_id`),
  ADD UNIQUE KEY `uq_penalty_claim` (`claim_id`),
  ADD KEY `fk_penalty_student` (`student_id`);

--
-- Indexes for table `provider`
--
ALTER TABLE `provider`
  ADD PRIMARY KEY (`provider_id`),
  ADD UNIQUE KEY `uq_provider_user` (`user_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_user_email` (`email`);

--
-- Indexes for table `user_audit_log`
--
ALTER TABLE `user_audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_user_log_admin` (`admin_id`),
  ADD KEY `fk_user_log_affected` (`affected_user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `claim`
--
ALTER TABLE `claim`
  MODIFY `claim_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `claim_tokens`
--
ALTER TABLE `claim_tokens`
  MODIFY `token_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `food_listing`
--
ALTER TABLE `food_listing`
  MODIFY `listing_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `food_listing_tags`
--
ALTER TABLE `food_listing_tags`
  MODIFY `food_listing_tags_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `food_tags`
--
ALTER TABLE `food_tags`
  MODIFY `tag_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `impact_record`
--
ALTER TABLE `impact_record`
  MODIFY `impact_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `listing_audit_log`
--
ALTER TABLE `listing_audit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `penalty_log`
--
ALTER TABLE `penalty_log`
  MODIFY `penalty_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `provider`
--
ALTER TABLE `provider`
  MODIFY `provider_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_audit_log`
--
ALTER TABLE `user_audit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `claim`
--
ALTER TABLE `claim`
  ADD CONSTRAINT `fk_claim_listing` FOREIGN KEY (`listing_id`) REFERENCES `food_listing` (`listing_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_claim_student` FOREIGN KEY (`student_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `claim_tokens`
--
ALTER TABLE `claim_tokens`
  ADD CONSTRAINT `fk_token_claim` FOREIGN KEY (`claim_id`) REFERENCES `claim` (`claim_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `food_listing`
--
ALTER TABLE `food_listing`
  ADD CONSTRAINT `fk_listing_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_listing_provider` FOREIGN KEY (`provider_id`) REFERENCES `provider` (`provider_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `food_listing_tags`
--
ALTER TABLE `food_listing_tags`
  ADD CONSTRAINT `fk_flt_listing` FOREIGN KEY (`listing_id`) REFERENCES `food_listing` (`listing_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_flt_tag` FOREIGN KEY (`tag_id`) REFERENCES `food_tags` (`tag_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `impact_record`
--
ALTER TABLE `impact_record`
  ADD CONSTRAINT `fk_impact_claim` FOREIGN KEY (`claim_id`) REFERENCES `claim` (`claim_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `listing_audit_log`
--
ALTER TABLE `listing_audit_log`
  ADD CONSTRAINT `fk_listing_log_admin` FOREIGN KEY (`admin_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_listing_log_listing` FOREIGN KEY (`listing_id`) REFERENCES `food_listing` (`listing_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `penalty_log`
--
ALTER TABLE `penalty_log`
  ADD CONSTRAINT `fk_penalty_claim` FOREIGN KEY (`claim_id`) REFERENCES `claim` (`claim_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_penalty_student` FOREIGN KEY (`student_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `provider`
--
ALTER TABLE `provider`
  ADD CONSTRAINT `fk_provider_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_audit_log`
--
ALTER TABLE `user_audit_log`
  ADD CONSTRAINT `fk_user_log_admin` FOREIGN KEY (`admin_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_log_affected` FOREIGN KEY (`affected_user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
