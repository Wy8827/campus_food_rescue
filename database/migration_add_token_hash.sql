-- ============================================================================
-- DATABASE MIGRATION: Use token_string for SHA-256 token hashes
-- ============================================================================
-- 
-- INSTRUCTIONS:
-- 1. Open phpMyAdmin
-- 2. Select your food_rescue database
-- 3. Go to the SQL tab
-- 4. Copy and paste this entire script
-- 5. Click "Go" to execute
--
-- Run this migration on the current table shown in phpMyAdmin. It replaces
-- the entire token_string column with hashes of predictable 6-character
-- hexadecimal test tokens and keeps token_string as the application column.
--
-- ============================================================================

START TRANSACTION;

UPDATE claim_tokens
SET token_string = SHA2(UPPER(LPAD(HEX(token_id), 6, '0')), 256);

ALTER TABLE claim_tokens
	MODIFY token_string CHAR(64) NOT NULL;

ALTER TABLE claim_tokens
	DROP COLUMN token_hash;

ALTER TABLE claim_tokens
	ADD UNIQUE KEY uq_claim_token_string (token_string);

COMMIT;

-- Verify table structure
DESCRIBE claim_tokens;

-- ============================================================================
-- EXPECTED OUTPUT:
-- 
-- Field               | Type      | Null | Key | Default | Extra
-- ----                | ----      | ---- | --- | ------- | -----
-- token_id            | int       | NO   | PRI | NULL    | auto_increment
-- claim_id            | int       | NO   |     | NULL    |
-- token_string        | char(64)  | NO   | UNI | NULL    |
-- expires_at          | datetime  | NO   |     | NULL    |
-- created_at          | datetime  | YES  |     | NULL    |
--
-- ============================================================================
