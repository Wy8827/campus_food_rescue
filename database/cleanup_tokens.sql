-- ============================================================================
-- CLEANUP: Reset token_hash column for new 6-character hex token system
-- ============================================================================
--
-- This script prepares your database for the new 6-character hexadecimal
-- token system using: strtoupper(bin2hex(random_bytes(3)))
--
-- Examples: A8F9D2, 7C2E1F, B3D6E1
--
-- ============================================================================

-- Clear old token hashes to prepare for new tokens
UPDATE claim_tokens 
SET token_hash = '' 
WHERE token_hash IS NOT NULL;

-- Verify the update
SELECT COUNT(*) as total_tokens, 
       COUNT(CASE WHEN token_hash = '' THEN 1 END) as cleared_hashes
FROM claim_tokens;

-- All new claims going forward will use the new format
-- Old claims with empty token_hash can be considered expired

-- ============================================================================
-- AFTER running this:
-- 1. Test creating a new claim
-- 2. You should see a 6-character hex token in the QR (e.g., A8F9D2)
-- 3. Check database: token_hash should be a 64-character SHA-256 hash
-- ============================================================================
