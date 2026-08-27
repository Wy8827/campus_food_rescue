-- ============================================================================
-- RESET: Store test-token hashes in the original token_string column
-- ============================================================================
--
-- Every existing row receives a hash of a predictable 6-character
-- hexadecimal test token. For token_id 1, the raw test token is 000001;
-- for token_id 2, it is 000002, and so on. New raw tokens are generated in
-- PHP using:
-- strtoupper(bin2hex(random_bytes(3)))
--
-- Examples: A8F9D2, 7C2E1F, B3D6E1
--
-- ============================================================================

START TRANSACTION;

-- Replace the entire token_string column with test hashes.
UPDATE claim_tokens
SET token_string = SHA2(UPPER(LPAD(HEX(token_id), 6, '0')), 256);

ALTER TABLE claim_tokens
       MODIFY token_string CHAR(64) NOT NULL;

ALTER TABLE claim_tokens
       DROP COLUMN token_hash;

ALTER TABLE claim_tokens
       ADD UNIQUE KEY uq_claim_token_string (token_string);

-- Verify the update
SELECT COUNT(*) as total_tokens, 
       COUNT(CASE WHEN CHAR_LENGTH(token_string) = 64 THEN 1 END) as hashed_tokens
FROM claim_tokens;

-- All new claims going forward will use the new format
-- Old claims retain their claim status and timestamps; only token storage
-- changes. New claims store only the SHA-256 hash in token_string.

COMMIT;

-- ============================================================================
-- AFTER running this:
-- 1. Test creating a new claim
-- 2. You should see a 6-character hex token in the QR (e.g., A8F9D2)
-- 3. Check database: token_string should be a 64-character SHA-256 hash
-- ============================================================================
