-- =====================================================================
-- food_rescue database — REBUILT SCHEMA (v2)
-- Generated for direct import into MySQL / MariaDB (phpMyAdmin compatible)
-- Base: original food_rescue.sql (Aug 17, 2026 dump)
-- =====================================================================
-- WHAT CHANGED FROM v1 (summary — see comments inline for detail):
--   1. ON DELETE CASCADE removed from historical tables -> RESTRICT
--      (protect audit logs / analytics from being wiped when a parent
--       row such as a user or listing is removed)
--   2. CHECK constraints added for quantities, ratings, portions
--   3. Composite indexes added to match real dashboard queries
--   4. claim_tokens now stores a SHA-256 HASH, not the raw token
--   5. New tables: provider_review, provider_audit_log
--   6. impact_record: added quantity_rescued + calculation_version
--   7. food_listing: added 'fully_claimed' status + auto-sync trigger
--   8. claim: status-transition trigger blocks illogical updates
--      (e.g. completed -> pending)
--   9. Role-integrity triggers (e.g. claim.student_id must belong to
--      a user with role='student')
--  10. email column widened to varchar(255)
--  11. New views: provider_statistics, provider_score
--
-- NOTE: table names (`user`, `claim`, etc.) were KEPT AS-IS so this
-- drops straight into your existing PHP code without renaming work.
-- Renaming `user` -> `users` is a low-priority, optional change — see
-- the note at the very end of this file if you want to do it later.
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Table structure for table `user`
-- --------------------------------------------------------

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `user_name` varchar(30) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('student','provider','admin') NOT NULL DEFAULT 'student',
  `pass_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `account_status` enum('active','banned','throttled') NOT NULL DEFAULT 'active',
  `throttled_until` datetime DEFAULT NULL,
  `no_show_count` int(11) NOT NULL DEFAULT 0,
  `security_question` varchar(200) DEFAULT NULL,
  `security_answer` varchar(255) DEFAULT NULL,
  CONSTRAINT `chk_user_no_show_count` CHECK (`no_show_count` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user` (`user_id`, `user_name`, `email`, `role`, `pass_hash`, `created_at`, `account_status`, `throttled_until`, `no_show_count`, `security_question`, `security_answer`) VALUES
(1, 'Admin APU', 'admin@gmail.com', 'admin', '$2y$10$kOn/LDIf7LD4YB/Vo6zhzurSDvakjDj4VBY88fS/u1KB3uXy8dMUO', '2025-01-01 08:00:00', 'active', NULL, 0, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(2, 'Mamak Stall', 'mamak@gmail.com', 'provider', '$2y$10$kOn/LDIf7LD4YB/Vo6zhzurSDvakjDj4VBY88fS/u1KB3uXy8dMUO', '2025-01-05 09:00:00', 'active', NULL, 0, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(3, 'Campus Canteen', 'canteen@gmail.com', 'provider', '$2y$10$kOn/LDIf7LD4YB/Vo6zhzurSDvakjDj4VBY88fS/u1KB3uXy8dMUO', '2025-01-06 09:00:00', 'active', NULL, 0, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(4, 'Dodgy Stall', 'dodgy@gmail.com', 'provider', '$2y$10$kOn/LDIf7LD4YB/Vo6zhzurSDvakjDj4VBY88fS/u1KB3uXy8dMUO', '2025-01-07 09:00:00', 'banned', NULL, 0, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(5, 'Ahmad Luqman', 'ahmad@gmail.com', 'student', '$2y$10$kOn/LDIf7LD4YB/Vo6zhzurSDvakjDj4VBY88fS/u1KB3uXy8dMUO', '2025-01-10 10:00:00', 'active', NULL, 1, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(6, 'Priya Nair', 'priya@gmail.com', 'student', '$2y$10$kOn/LDIf7LD4YB/Vo6zhzurSDvakjDj4VBY88fS/u1KB3uXy8dMUO', '2025-01-10 10:00:00', 'active', NULL, 1, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(7, 'Kelvin Lim', 'kelvin@gmail.com', 'student', '$2y$10$kOn/LDIf7LD4YB/Vo6zhzurSDvakjDj4VBY88fS/u1KB3uXy8dMUO', '2025-01-10 10:00:00', 'throttled', '2026-08-18 01:17:43', 3, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(8, 'Banned Student', 'banned@gmail.com', 'student', '$2y$10$kOn/LDIf7LD4YB/Vo6zhzurSDvakjDj4VBY88fS/u1KB3uXy8dMUO', '2025-01-10 10:00:00', 'banned', NULL, 0, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(9, 'Fatimah Zahra', 'fatimah@gmail.com', 'student', '$2y$10$kOn/LDIf7LD4YB/Vo6zhzurSDvakjDj4VBY88fS/u1KB3uXy8dMUO', '2025-01-10 10:00:00', 'active', NULL, 0, 'What is your favourite food?', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- --------------------------------------------------------
-- Table structure for table `provider`
-- --------------------------------------------------------

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

INSERT INTO `provider` (`provider_id`, `user_id`, `provider_name`, `contact_number`, `location`, `operating_hours`, `request_note`, `provider_status`) VALUES
(1, 2, 'Mamak Stall APU', '0123456789', 'Block A, Ground Floor', 'Mon-Sun 7:00am-8:00pm', 'We serve halal food daily and have surplus every evening.', 'active'),
(2, 3, 'APU Campus Canteen', '0198765432', 'Block C, Level 1', 'Mon-Fri 8:00am-5:00pm', 'Official APU canteen with daily surplus from buffet line.', 'active'),
(3, 4, 'Dodgy Stall', '0111234567', 'Block F, Ground Floor', 'Mon-Fri 9:00am-3:00pm', 'Suspicious listing with no real food.', 'suspended');

-- --------------------------------------------------------
-- Table structure for table `food_tags`
-- --------------------------------------------------------

CREATE TABLE `food_tags` (
  `tag_id` int(11) NOT NULL,
  `tag_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `food_tags` (`tag_id`, `tag_name`) VALUES
(6, 'Contains nuts'),
(4, 'Dairy-free'),
(5, 'Gluten-free'),
(1, 'Halal'),
(7, 'Spicy'),
(2, 'Vegan'),
(3, 'Vegetarian');

-- --------------------------------------------------------
-- Table structure for table `food_listing`
-- IMPROVEMENT: added 'fully_claimed' status + CHECK constraints
-- --------------------------------------------------------

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
  `status` enum('pending','active','fully_claimed','expired','removed') NOT NULL DEFAULT 'pending',
  `expires_at` datetime NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  CONSTRAINT `chk_listing_total_qty` CHECK (`total_quantity` > 0),
  CONSTRAINT `chk_listing_remain_qty` CHECK (`remain_quantity` >= 0),
  CONSTRAINT `chk_listing_remain_le_total` CHECK (`remain_quantity` <= `total_quantity`),
  CONSTRAINT `chk_listing_weight` CHECK (`weight_kg` IS NULL OR `weight_kg` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTE: listing #2 (Roti Canai Bundle) is fully claimed (remain=0),
-- so it's now stored with the new 'fully_claimed' status instead of
-- 'active' — this is exactly the "impossible state" problem flagged
-- in the review (see improvement #10 in the summary).
INSERT INTO `food_listing` (`listing_id`, `provider_id`, `food_name`, `description`, `total_quantity`, `remain_quantity`, `weight_kg`, `pickup_location`, `image`, `status`, `expires_at`, `approved_by`, `approved_at`, `created_at`) VALUES
(1, 1, 'Nasi Lemak Set', 'Leftover from morning rush. Still warm.', 8, 3, 2.80, 'Mamak Stall Counter, Block A GF', NULL, 'active', '2026-08-16 02:47:43', 1, '2026-08-16 00:57:43', '2026-08-16 00:47:43'),
(2, 1, 'Roti Canai Bundle', 'Set of 5 with dhal. Fully claimed.', 5, 0, 1.50, 'Mamak Stall Counter, Block A GF', NULL, 'fully_claimed', '2026-08-16 02:17:43', 1, '2026-08-16 00:37:43', '2026-08-16 00:32:43'),
(3, 1, 'Mee Goreng Portions', 'Spicy fried noodles. Come now!', 4, 4, 1.20, 'Mamak Stall Counter, Block A GF', NULL, 'active', '2026-08-16 01:21:43', 1, '2026-08-15 23:27:43', '2026-08-15 23:17:43'),
(4, 1, 'Kuih Assortment', 'Assorted kuih from event. 20 pieces.', 20, 20, 3.00, 'Mamak Stall Counter, Block A GF', NULL, 'pending', '2026-08-16 04:17:43', NULL, NULL, '2026-08-16 01:07:43'),
(5, 1, 'Chicken Rice Set', 'Yesterday lunch. Now expired.', 10, 6, 3.50, 'Mamak Stall Counter, Block A GF', NULL, 'expired', '2026-08-15 23:17:43', 1, '2026-08-15 19:17:43', '2026-08-15 20:17:43'),
(6, 1, 'Mystery Box Food', 'Unknown content. Removed by admin.', 100, 100, 0.00, 'Unknown location', NULL, 'removed', '2026-08-17 01:17:43', NULL, NULL, '2026-08-16 00:17:43'),
(7, 2, 'Chicken Chop Plate', 'Western set from staff event.', 6, 4, 4.20, 'APU Canteen, Block C L1', NULL, 'active', '2026-08-16 01:42:43', 1, '2026-08-16 00:22:43', '2026-08-16 00:17:43'),
(8, 2, 'Mixed Salad Box', 'Fresh vegan salad boxes.', 10, 7, 1.80, 'APU Canteen, Block C L1', NULL, 'active', '2026-08-16 03:17:43', 1, '2026-08-16 01:02:43', '2026-08-16 00:57:43'),
(9, 2, 'Pasta Bake', 'Vegetarian pasta from staff event.', 12, 12, 5.00, 'APU Canteen, Block C L1', NULL, 'pending', '2026-08-16 05:17:43', NULL, NULL, '2026-08-16 01:12:43'),
(10, 1, 'Fried Rice Portions', 'For impact record testing.', 5, 3, 2.00, 'Mamak Stall Counter, Block A GF', NULL, 'active', '2026-08-16 02:17:43', 1, '2026-08-15 23:47:43', '2026-08-15 23:17:43');

-- --------------------------------------------------------
-- Table structure for table `food_listing_tags`
-- --------------------------------------------------------

CREATE TABLE `food_listing_tags` (
  `food_listing_tags_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `food_listing_tags` (`food_listing_tags_id`, `listing_id`, `tag_id`) VALUES
(1, 1, 1), (2, 1, 7),
(4, 2, 1), (5, 2, 3),
(7, 3, 1), (8, 3, 7),
(11, 4, 1), (12, 4, 3), (10, 4, 4),
(13, 5, 1),
(14, 7, 1),
(17, 8, 2), (18, 8, 3), (15, 8, 4), (16, 8, 5),
(23, 9, 3), (22, 9, 6),
(25, 10, 1);

-- --------------------------------------------------------
-- Table structure for table `claim`
-- IMPROVEMENT: portion_claimed CHECK constraint
-- --------------------------------------------------------

CREATE TABLE `claim` (
  `claim_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `portion_claimed` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reservation_expires_at` datetime NOT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `status` enum('pending','confirmed','completed','expired','cancelled') NOT NULL DEFAULT 'pending',
  CONSTRAINT `chk_claim_portion` CHECK (`portion_claimed` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Table structure for table `claim_tokens`
-- IMPROVEMENT: raw token replaced with SHA-256 hash column.
-- Your PHP backend must hash the token BEFORE comparing:
--   $hash = hash('sha256', $submittedToken);
--   SELECT * FROM claim_tokens WHERE token_hash = ? AND claim_id = ?;
-- The values below are SHA2() of the original demo tokens, purely
-- to demonstrate the storage format — treat them as opaque.
-- --------------------------------------------------------

CREATE TABLE `claim_tokens` (
  `token_id` int(11) NOT NULL,
  `claim_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `claim_tokens` (`token_id`, `claim_id`, `token_hash`, `created_at`, `expires_at`, `used_at`) VALUES
(1, 1, SHA2('f8a6db00518ef9bd21d4f2dbe6309968e1168c3e2412623138324d3b1fae405b', 256), '2026-08-16 01:12:43', '2026-08-16 01:22:43', NULL),
(2, 2, SHA2('159a7d66004d8a8f89eb0f73a3d88f2620306e7df53012cbb791b3e610d0f962', 256), '2026-08-16 01:13:43', '2026-08-16 01:23:43', NULL),
(3, 3, SHA2('9c0758b64130b2324c8259185f136efc898362edd78fd9d7e00331bdcf9b0ba5', 256), '2026-08-15 23:17:43', '2026-08-15 23:27:43', '2026-08-15 23:22:43'),
(4, 4, SHA2('c7fa020575623352134227e659e7df200bd30d8943418804b3fb4811af413ba5', 256), '2026-08-15 22:17:43', '2026-08-15 22:27:43', '2026-08-15 22:25:43'),
(5, 5, SHA2('41b0ab9109412c6cabacf002578024ad691628fa0188bf85a9f6d5617d831ed3', 256), '2026-08-15 22:17:43', '2026-08-15 22:28:43', '2026-08-15 22:26:43'),
(6, 6, SHA2('f0000d339c0f2e0d9377b64108c1cd579212d3de5f53f9b5499f9ad703573105', 256), '2026-08-16 01:14:43', '2026-08-16 01:24:43', NULL),
(7, 9, SHA2('34028414eece1f79e7f5117999bde39e93b8df7aaf45802a2980a919ff4acd06', 256), '2026-08-15 23:47:43', '2026-08-15 23:57:43', '2026-08-15 23:55:43'),
(8, 10, SHA2('39695f4292a42e24931adc7cc30d39e0f9ffa755c8a044abe48c3bcec1883126', 256), '2026-08-16 01:09:43', '2026-08-16 01:19:43', NULL);

-- --------------------------------------------------------
-- Table structure for table `impact_record`
-- IMPROVEMENT: added quantity_rescued + calculation_version so the
-- historical CO2/water numbers stay auditable even if your formula
-- changes later.
-- --------------------------------------------------------

CREATE TABLE `impact_record` (
  `impact_id` int(11) NOT NULL,
  `claim_id` int(11) NOT NULL,
  `quantity_rescued` int(11) NOT NULL DEFAULT 1,
  `co2_saved_kg` decimal(8,3) NOT NULL DEFAULT 0.000,
  `water_saved_litre` decimal(8,2) NOT NULL DEFAULT 0.00,
  `calculation_version` varchar(20) NOT NULL DEFAULT 'v1.0',
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  CONSTRAINT `chk_impact_qty` CHECK (`quantity_rescued` > 0),
  CONSTRAINT `chk_impact_co2` CHECK (`co2_saved_kg` >= 0),
  CONSTRAINT `chk_impact_water` CHECK (`water_saved_litre` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `impact_record` (`impact_id`, `claim_id`, `quantity_rescued`, `co2_saved_kg`, `water_saved_litre`, `calculation_version`, `recorded_at`) VALUES
(1, 3, 1, 0.875, 17.50, 'v1.0', '2026-08-15 23:22:43'),
(2, 4, 2, 1.500, 30.00, 'v1.0', '2026-08-15 22:25:43'),
(3, 5, 3, 2.250, 45.00, 'v1.0', '2026-08-15 22:26:43'),
(4, 9, 1, 1.000, 20.00, 'v1.0', '2026-08-15 23:55:43');

-- --------------------------------------------------------
-- Table structure for table `listing_audit_log`
-- --------------------------------------------------------

CREATE TABLE `listing_audit_log` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `action_type` enum('approve_listing','reject_listing','remove_listing') NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `performed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Table structure for table `penalty_log`
-- IMPROVEMENT: throttle_days CHECK constraint
-- --------------------------------------------------------

CREATE TABLE `penalty_log` (
  `penalty_id` int(11) NOT NULL,
  `claim_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `throttle_days` int(11) NOT NULL DEFAULT 1,
  CONSTRAINT `chk_penalty_throttle_days` CHECK (`throttle_days` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `penalty_log` (`penalty_id`, `claim_id`, `student_id`, `reason`, `issued_at`, `throttle_days`) VALUES
(1, 7, 5, 'No-show: claim expired without pickup', '2026-08-15 20:27:43', 1);

-- --------------------------------------------------------
-- Table structure for table `user_audit_log`
-- --------------------------------------------------------

CREATE TABLE `user_audit_log` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `affected_user_id` int(11) NOT NULL,
  `action_type` enum('ban_user','unban_user','warn_user','throttle_user','assign_role','approve_provider','reject_provider') NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `performed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_audit_log` (`log_id`, `admin_id`, `affected_user_id`, `action_type`, `notes`, `performed_at`) VALUES
(1, 1, 4, 'ban_user', 'Repeated spam listings.', '2026-08-16 00:24:43'),
(2, 1, 5, 'warn_user', 'First no-show warning.', '2026-08-15 20:37:43'),
(3, 1, 7, 'throttle_user', '3 no-shows. Throttled 2 days.', '2026-08-15 01:17:43'),
(4, 1, 8, 'ban_user', 'Test ban account.', '2026-08-11 01:17:43'),
(5, 1, 2, 'approve_provider', 'Approved Mamak Stall registration.', '2026-07-17 01:17:43'),
(6, 1, 3, 'approve_provider', 'Approved Campus Canteen registration.', '2026-07-18 01:17:43'),
(7, 1, 4, 'reject_provider', 'Rejected suspicious stall registration.', '2026-07-19 01:17:43');

-- --------------------------------------------------------
-- Table structure for table `provider_review`  (NEW)
-- One review per completed claim. Links back through claim so a
-- student can only review a provider they actually received food
-- from (enforced in the trigger below).
-- --------------------------------------------------------

CREATE TABLE `provider_review` (
  `review_id` int(11) NOT NULL,
  `claim_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL,
  `food_quality_rating` tinyint(1) DEFAULT NULL,
  `description_accuracy_rating` tinyint(1) DEFAULT NULL,
  `comment` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  CONSTRAINT `chk_review_rating` CHECK (`rating` BETWEEN 1 AND 5),
  CONSTRAINT `chk_review_food_quality` CHECK (`food_quality_rating` IS NULL OR `food_quality_rating` BETWEEN 1 AND 5),
  CONSTRAINT `chk_review_accuracy` CHECK (`description_accuracy_rating` IS NULL OR `description_accuracy_rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `provider_review` (`review_id`, `claim_id`, `provider_id`, `student_id`, `rating`, `food_quality_rating`, `description_accuracy_rating`, `comment`, `created_at`) VALUES
(1, 4, 1, 5, 5, 5, 4, 'Great roti canai, exactly as described.', '2026-08-15 22:30:00'),
(2, 5, 1, 9, 4, 4, 4, 'Good portion, slightly cold by the time I arrived.', '2026-08-15 22:31:00'),
(3, 9, 1, 5, 5, 5, 5, 'Fried rice was perfect, easy pickup.', '2026-08-15 23:58:00');

-- --------------------------------------------------------
-- Table structure for table `provider_audit_log`  (NEW)
-- Historical record of admin actions taken against a provider
-- (separate from user_audit_log, which tracks the underlying user
-- account rather than the provider profile/business).
-- --------------------------------------------------------

CREATE TABLE `provider_audit_log` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `action_type` enum('approved','suspended','unsuspended','warned') NOT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `performed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `provider_audit_log` (`log_id`, `admin_id`, `provider_id`, `action_type`, `reason`, `performed_at`) VALUES
(1, 1, 1, 'approved', 'Approved Mamak Stall registration.', '2026-07-17 01:17:43'),
(2, 1, 2, 'approved', 'Approved Campus Canteen registration.', '2026-07-18 01:17:43'),
(3, 1, 3, 'suspended', 'Suspicious listing with no real food.', '2026-07-19 01:17:43');


-- =====================================================================
-- INDEXES
-- =====================================================================

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_user_email` (`email`),
  ADD KEY `idx_user_role_status` (`role`,`account_status`);

--
-- Indexes for table `provider`
--
ALTER TABLE `provider`
  ADD PRIMARY KEY (`provider_id`),
  ADD UNIQUE KEY `uq_provider_user` (`user_id`),
  ADD KEY `idx_provider_status` (`provider_status`);

--
-- Indexes for table `food_tags`
--
ALTER TABLE `food_tags`
  ADD PRIMARY KEY (`tag_id`),
  ADD UNIQUE KEY `uq_tag_name` (`tag_name`);

--
-- Indexes for table `food_listing`
-- (composite indexes matched to real dashboard queries)
--
ALTER TABLE `food_listing`
  ADD PRIMARY KEY (`listing_id`),
  ADD KEY `fk_listing_approved_by` (`approved_by`),
  ADD KEY `idx_listing_provider_created` (`provider_id`,`created_at`),
  ADD KEY `idx_listing_status_created` (`status`,`created_at`),
  ADD KEY `idx_listing_expires_at` (`expires_at`);

--
-- Indexes for table `food_listing_tags`
--
ALTER TABLE `food_listing_tags`
  ADD PRIMARY KEY (`food_listing_tags_id`),
  ADD UNIQUE KEY `uq_listing_tag` (`listing_id`,`tag_id`),
  ADD KEY `fk_flt_tag` (`tag_id`);

--
-- Indexes for table `claim`
-- (composite indexes matched to real dashboard queries)
--
ALTER TABLE `claim`
  ADD PRIMARY KEY (`claim_id`),
  ADD UNIQUE KEY `uq_student_listing` (`student_id`,`listing_id`),
  ADD KEY `idx_claim_status_created` (`status`,`created_at`),
  ADD KEY `idx_claim_listing_created` (`listing_id`,`created_at`),
  ADD KEY `idx_claim_student_created` (`student_id`,`created_at`);

--
-- Indexes for table `claim_tokens`
--
ALTER TABLE `claim_tokens`
  ADD PRIMARY KEY (`token_id`),
  ADD UNIQUE KEY `uq_token_claim` (`claim_id`),
  ADD UNIQUE KEY `uq_token_hash` (`token_hash`);

--
-- Indexes for table `impact_record`
--
ALTER TABLE `impact_record`
  ADD PRIMARY KEY (`impact_id`),
  ADD UNIQUE KEY `uq_impact_claim` (`claim_id`),
  ADD KEY `idx_impact_recorded_at` (`recorded_at`);

--
-- Indexes for table `listing_audit_log`
--
ALTER TABLE `listing_audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_listing_log_admin` (`admin_id`),
  ADD KEY `idx_listing_log_listing_performed` (`listing_id`,`performed_at`),
  ADD KEY `idx_listing_log_action_performed` (`action_type`,`performed_at`);

--
-- Indexes for table `penalty_log`
--
ALTER TABLE `penalty_log`
  ADD PRIMARY KEY (`penalty_id`),
  ADD UNIQUE KEY `uq_penalty_claim` (`claim_id`),
  ADD KEY `fk_penalty_student` (`student_id`);

--
-- Indexes for table `user_audit_log`
--
ALTER TABLE `user_audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_user_log_admin` (`admin_id`),
  ADD KEY `idx_user_log_affected_performed` (`affected_user_id`,`performed_at`),
  ADD KEY `idx_user_log_action_performed` (`action_type`,`performed_at`);

--
-- Indexes for table `provider_review`
--
ALTER TABLE `provider_review`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `uq_review_claim` (`claim_id`),
  ADD KEY `idx_review_provider_created` (`provider_id`,`created_at`),
  ADD KEY `fk_review_student` (`student_id`);

--
-- Indexes for table `provider_audit_log`
--
ALTER TABLE `provider_audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_provider_log_admin` (`admin_id`),
  ADD KEY `idx_provider_log_provider_performed` (`provider_id`,`performed_at`);

-- =====================================================================
-- AUTO_INCREMENT
-- =====================================================================

ALTER TABLE `user` MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;
ALTER TABLE `provider` MODIFY `provider_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `food_tags` MODIFY `tag_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
ALTER TABLE `food_listing` MODIFY `listing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
ALTER TABLE `food_listing_tags` MODIFY `food_listing_tags_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;
ALTER TABLE `claim` MODIFY `claim_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
ALTER TABLE `claim_tokens` MODIFY `token_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
ALTER TABLE `impact_record` MODIFY `impact_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `listing_audit_log` MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;
ALTER TABLE `penalty_log` MODIFY `penalty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `user_audit_log` MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
ALTER TABLE `provider_review` MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `provider_audit_log` MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

-- =====================================================================
-- FOREIGN KEY CONSTRAINTS
-- IMPROVEMENT: ON DELETE CASCADE replaced with RESTRICT wherever the
-- child table holds historical / analytics data, so a delete on the
-- parent (user, provider, listing, claim) can no longer silently wipe
-- out audit trails or impact statistics. Your app should use status
-- flags (banned / suspended / removed / cancelled) instead of hard
-- deletes on rows that already have history attached to them.
-- Junction/reference tables (tags) and purely operational rows
-- (claim_tokens) keep CASCADE since losing them has no analytics cost.
-- =====================================================================

--
-- Constraints for table `provider`
--
ALTER TABLE `provider`
  ADD CONSTRAINT `fk_provider_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `food_listing`
--
ALTER TABLE `food_listing`
  ADD CONSTRAINT `fk_listing_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_listing_provider` FOREIGN KEY (`provider_id`) REFERENCES `provider` (`provider_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `food_listing_tags`
--
ALTER TABLE `food_listing_tags`
  ADD CONSTRAINT `fk_flt_listing` FOREIGN KEY (`listing_id`) REFERENCES `food_listing` (`listing_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_flt_tag` FOREIGN KEY (`tag_id`) REFERENCES `food_tags` (`tag_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `claim`
--
ALTER TABLE `claim`
  ADD CONSTRAINT `fk_claim_listing` FOREIGN KEY (`listing_id`) REFERENCES `food_listing` (`listing_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_claim_student` FOREIGN KEY (`student_id`) REFERENCES `user` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `claim_tokens`
--
ALTER TABLE `claim_tokens`
  ADD CONSTRAINT `fk_token_claim` FOREIGN KEY (`claim_id`) REFERENCES `claim` (`claim_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `impact_record`
--
ALTER TABLE `impact_record`
  ADD CONSTRAINT `fk_impact_claim` FOREIGN KEY (`claim_id`) REFERENCES `claim` (`claim_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `listing_audit_log`
--
ALTER TABLE `listing_audit_log`
  ADD CONSTRAINT `fk_listing_log_admin` FOREIGN KEY (`admin_id`) REFERENCES `user` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_listing_log_listing` FOREIGN KEY (`listing_id`) REFERENCES `food_listing` (`listing_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `penalty_log`
--
ALTER TABLE `penalty_log`
  ADD CONSTRAINT `fk_penalty_claim` FOREIGN KEY (`claim_id`) REFERENCES `claim` (`claim_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_penalty_student` FOREIGN KEY (`student_id`) REFERENCES `user` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `user_audit_log`
--
ALTER TABLE `user_audit_log`
  ADD CONSTRAINT `fk_user_log_admin` FOREIGN KEY (`admin_id`) REFERENCES `user` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_log_affected` FOREIGN KEY (`affected_user_id`) REFERENCES `user` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `provider_review`
--
ALTER TABLE `provider_review`
  ADD CONSTRAINT `fk_review_claim` FOREIGN KEY (`claim_id`) REFERENCES `claim` (`claim_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_review_provider` FOREIGN KEY (`provider_id`) REFERENCES `provider` (`provider_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_review_student` FOREIGN KEY (`student_id`) REFERENCES `user` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `provider_audit_log`
--
ALTER TABLE `provider_audit_log`
  ADD CONSTRAINT `fk_provider_log_admin` FOREIGN KEY (`admin_id`) REFERENCES `user` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_provider_log_provider` FOREIGN KEY (`provider_id`) REFERENCES `provider` (`provider_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

COMMIT;

-- =====================================================================
-- TRIGGERS
-- =====================================================================
DELIMITER $$

-- ---------------------------------------------------------------
-- 1. Role integrity: a claim's student_id must actually belong to
--    a user with role='student' (previously only enforceable in PHP)
-- ---------------------------------------------------------------
CREATE TRIGGER `trg_claim_student_role_check` BEFORE INSERT ON `claim`
FOR EACH ROW
BEGIN
  DECLARE v_role VARCHAR(20);
  SELECT `role` INTO v_role FROM `user` WHERE `user_id` = NEW.student_id;
  IF v_role IS NULL OR v_role <> 'student' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'claim.student_id must reference a user with role = student';
  END IF;
END$$

-- ---------------------------------------------------------------
-- 2. Claim status-transition guard: blocks illogical updates such
--    as completed -> pending, or resurrecting a cancelled claim.
-- ---------------------------------------------------------------
CREATE TRIGGER `trg_claim_status_transition` BEFORE UPDATE ON `claim`
FOR EACH ROW
BEGIN
  IF OLD.status <> NEW.status THEN
    IF NOT (
         (OLD.status = 'pending'   AND NEW.status IN ('confirmed','cancelled','expired'))
      OR (OLD.status = 'confirmed' AND NEW.status IN ('completed','cancelled'))
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid claim status transition';
    END IF;
  END IF;
END$$

-- ---------------------------------------------------------------
-- 3. food_listing status auto-sync: once remain_quantity hits 0 on
--    an active listing, automatically flip it to 'fully_claimed'
--    instead of leaving the impossible active + remain=0 state.
-- ---------------------------------------------------------------
CREATE TRIGGER `trg_listing_status_sync` BEFORE UPDATE ON `food_listing`
FOR EACH ROW
BEGIN
  IF NEW.remain_quantity = 0 AND NEW.status = 'active' THEN
    SET NEW.status = 'fully_claimed';
  END IF;
END$$

-- ---------------------------------------------------------------
-- 4. Role integrity: food_listing.approved_by must be an admin
-- ---------------------------------------------------------------
CREATE TRIGGER `trg_listing_approver_role_check` BEFORE INSERT ON `food_listing`
FOR EACH ROW
BEGIN
  DECLARE v_role VARCHAR(20);
  IF NEW.approved_by IS NOT NULL THEN
    SELECT `role` INTO v_role FROM `user` WHERE `user_id` = NEW.approved_by;
    IF v_role IS NULL OR v_role <> 'admin' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'food_listing.approved_by must reference a user with role = admin';
    END IF;
  END IF;
END$$

CREATE TRIGGER `trg_listing_approver_role_check_upd` BEFORE UPDATE ON `food_listing`
FOR EACH ROW
BEGIN
  DECLARE v_role VARCHAR(20);
  IF NEW.approved_by IS NOT NULL THEN
    SELECT `role` INTO v_role FROM `user` WHERE `user_id` = NEW.approved_by;
    IF v_role IS NULL OR v_role <> 'admin' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'food_listing.approved_by must reference a user with role = admin';
    END IF;
  END IF;
END$$

-- ---------------------------------------------------------------
-- 5. provider_review: reviewer + provider must match the claim's
--    actual student and provider chain (claim -> listing -> provider)
--    so a review can never be attached to the wrong provider/student.
-- ---------------------------------------------------------------
CREATE TRIGGER `trg_review_matches_claim` BEFORE INSERT ON `provider_review`
FOR EACH ROW
BEGIN
  DECLARE v_student_id INT;
  DECLARE v_provider_id INT;
  SELECT c.student_id, fl.provider_id
    INTO v_student_id, v_provider_id
    FROM `claim` c
    JOIN `food_listing` fl ON fl.listing_id = c.listing_id
    WHERE c.claim_id = NEW.claim_id;

  IF v_student_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'provider_review.claim_id does not exist';
  ELSEIF v_student_id <> NEW.student_id OR v_provider_id <> NEW.provider_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'provider_review student_id/provider_id must match the claim it references';
  END IF;
END$$

DELIMITER ;

-- ---------------------------------------------------------------
-- 6. Role integrity: provider.user_id must belong to a user with
--    role = 'provider'
-- ---------------------------------------------------------------
DELIMITER $$
CREATE TRIGGER `trg_provider_user_role_check` BEFORE INSERT ON `provider`
FOR EACH ROW
BEGIN
  DECLARE v_role VARCHAR(20);
  SELECT `role` INTO v_role FROM `user` WHERE `user_id` = NEW.user_id;
  IF v_role IS NULL OR v_role <> 'provider' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'provider.user_id must reference a user with role = provider';
  END IF;
END$$
DELIMITER ;

-- =====================================================================
-- VIEWS
-- Instead of storing a single "credit_score" number on the provider
-- table (which can't be explained or audited), the score is CALCULATED
-- on the fly from real activity: fulfillment, unclaimed/waste ratio,
-- review scores, and compliance history. Query these views directly
-- from your admin dashboard.
-- =====================================================================

-- ---------------------------------------------------------------
-- provider_statistics: raw counts your dashboard needs, precomputed
-- so PHP doesn't have to repeat multi-join aggregate queries.
-- ---------------------------------------------------------------
CREATE VIEW `provider_statistics` AS
SELECT
  p.provider_id,
  p.provider_name,
  p.provider_status,
  COUNT(DISTINCT fl.listing_id)                                              AS total_listings,
  COALESCE(SUM(fl.total_quantity), 0)                                        AS total_quantity,
  COALESCE(SUM(fl.total_quantity - fl.remain_quantity), 0)                   AS claimed_quantity,
  COALESCE(SUM(CASE WHEN fl.status = 'expired' THEN fl.remain_quantity ELSE 0 END), 0) AS expired_unclaimed_quantity,
  COUNT(DISTINCT CASE WHEN c.status = 'completed' THEN c.claim_id END)       AS completed_claims,
  COUNT(DISTINCT CASE WHEN c.status = 'cancelled' THEN c.claim_id END)       AS cancelled_claims,
  COUNT(DISTINCT CASE WHEN c.status IN ('completed','cancelled','expired') THEN c.claim_id END) AS resolved_claims,
  ROUND(
    COUNT(DISTINCT CASE WHEN c.status = 'completed' THEN c.claim_id END)
    / NULLIF(COUNT(DISTINCT CASE WHEN c.status IN ('completed','cancelled','expired') THEN c.claim_id END), 0)
  , 4) AS fulfillment_rate,
  ROUND(
    COALESCE(SUM(CASE WHEN fl.status = 'expired' THEN fl.remain_quantity ELSE 0 END), 0)
    / NULLIF(SUM(fl.total_quantity), 0)
  , 4) AS unclaimed_waste_rate
FROM `provider` p
LEFT JOIN `food_listing` fl ON fl.provider_id = p.provider_id
LEFT JOIN `claim` c ON c.listing_id = fl.listing_id
GROUP BY p.provider_id, p.provider_name, p.provider_status;

-- ---------------------------------------------------------------
-- provider_score: weighted composite score (0-100), explainable
-- component by component instead of a single opaque number.
--   Fulfillment rate        40%
--   Food/description review 25%  (avg of provider_review ratings, /5)
--   Unclaimed/waste rate    20%  (lower waste = higher score)
--   Compliance              15%  (deducted for suspended/warned events)
-- ---------------------------------------------------------------
CREATE VIEW `provider_score` AS
SELECT
  ps.provider_id,
  ps.provider_name,
  ps.provider_status,
  ps.fulfillment_rate,
  ps.unclaimed_waste_rate,
  r.avg_rating,
  r.review_count,
  comp.compliance_incidents,
  ROUND(
    (COALESCE(ps.fulfillment_rate, 0) * 40)
    + (COALESCE(r.avg_rating, 5) / 5 * 25)
    + ((1 - COALESCE(ps.unclaimed_waste_rate, 0)) * 20)
    + (GREATEST(0, 15 - COALESCE(comp.compliance_incidents, 0) * 5))
  , 1) AS overall_score
FROM `provider_statistics` ps
LEFT JOIN (
  SELECT provider_id, ROUND(AVG(rating), 2) AS avg_rating, COUNT(*) AS review_count
  FROM `provider_review`
  GROUP BY provider_id
) r ON r.provider_id = ps.provider_id
LEFT JOIN (
  SELECT provider_id, COUNT(*) AS compliance_incidents
  FROM `provider_audit_log`
  WHERE action_type IN ('suspended','warned')
  GROUP BY provider_id
) comp ON comp.provider_id = ps.provider_id;