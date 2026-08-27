-- ============================================================================
-- DATABASE MIGRATION: Add token_hash column to claim_tokens table
-- ============================================================================
-- 
-- INSTRUCTIONS:
-- 1. Open phpMyAdmin
-- 2. Select your food_rescue database
-- 3. Go to the SQL tab
-- 4. Copy and paste this entire script
-- 5. Click "Go" to execute
--
-- This migration:
-- - Adds the token_hash column if it doesn't exist
-- - Creates a UNIQUE index for token_hash for fast lookups
-- - Token hashes are SHA-256 (64 characters)
--
-- ============================================================================

-- Check if column exists before adding (MySQL 5.7+)
ALTER TABLE claim_tokens
ADD COLUMN IF NOT EXISTS token_hash CHAR(64) NOT NULL;

-- Add unique constraint for token_hash if it doesn't exist
-- Note: If this fails because an index already exists, that's OK
ALTER TABLE claim_tokens
ADD UNIQUE KEY IF NOT EXISTS uq_claim_token_hash (token_hash);

-- Verify table structure
DESCRIBE claim_tokens;

-- ============================================================================
-- EXPECTED OUTPUT:
-- 
-- Field               | Type      | Null | Key | Default | Extra
-- ----                | ----      | ---- | --- | ------- | -----
-- token_id            | int       | NO   | PRI | NULL    | auto_increment
-- claim_id            | int       | NO   |     | NULL    |
-- token_hash          | char(64)  | NO   | UNI | NULL    |
-- expires_at          | datetime  | NO   |     | NULL    |
-- created_at          | datetime  | YES  |     | NULL    |
--
-- ============================================================================
