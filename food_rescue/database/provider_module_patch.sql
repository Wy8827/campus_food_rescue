-- =====================================================================
-- provider_module_patch.sql
-- Run this AFTER importing food_rescue.sql.
-- Adds the extra `provider` fields needed for the Food Provider
-- "Manage Store Details" page (Settings), which the base schema
-- did not yet cover (cuisine category, pickup instructions shown to
-- students, and per-provider notification preferences).
-- Safe to run once; re-running will error on duplicate columns.
-- =====================================================================

ALTER TABLE `provider`
  ADD COLUMN `cuisine_category`     VARCHAR(100) DEFAULT NULL AFTER `provider_name`,
  ADD COLUMN `contact_person`       VARCHAR(100) DEFAULT NULL AFTER `contact_number`,
  ADD COLUMN `pickup_instructions`  VARCHAR(500) DEFAULT NULL AFTER `operating_hours`,
  ADD COLUMN `notify_on_claim`      TINYINT(1) NOT NULL DEFAULT 1 AFTER `provider_status`,
  ADD COLUMN `notify_expiry_alert`  TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_on_claim`,
  ADD COLUMN `notify_weekly_digest` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notify_expiry_alert`;

-- Fill in demo values for the existing seeded providers
UPDATE `provider` SET
  `cuisine_category` = 'Malaysian / Indian-Muslim',
  `contact_person` = 'Muthu Kumar',
  `pickup_instructions` = 'Please present your claim QR code directly at the counter. Students bringing their own container get extra bonus portions!'
WHERE `provider_id` = 1;

UPDATE `provider` SET
  `cuisine_category` = 'Malaysian / Western',
  `contact_person` = 'Siti Aminah'
WHERE `provider_id` = 2;
