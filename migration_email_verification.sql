-- ============================================================
-- Migration: Add email verification columns to members table
-- Run once: mysql -u root -p car_rental < migration_email_verification.sql
-- ============================================================

ALTER TABLE members
    ADD COLUMN email_verified        TINYINT(1)   NOT NULL DEFAULT 0
                                     AFTER licence_no,
    ADD COLUMN verification_token    VARCHAR(128) NULL DEFAULT NULL
                                     AFTER email_verified,
    ADD COLUMN verification_expires  DATETIME     NULL DEFAULT NULL
                                     AFTER verification_token;

-- Index for fast token look-ups
CREATE INDEX idx_members_verification_token
    ON members (verification_token);

-- Mark any existing accounts as already verified
-- (so existing users are not locked out)
UPDATE members SET email_verified = 1 WHERE email_verified = 0;
