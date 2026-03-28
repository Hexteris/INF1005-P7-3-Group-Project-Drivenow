-- ============================================================
-- DriveNow Migration: Loyalty Points System
-- Run once: mysql -u root -p car_rental < loyalty_migration.sql
-- ============================================================

-- Add points balance to members table
ALTER TABLE members
    ADD COLUMN points INT NOT NULL DEFAULT 0 AFTER licence_no;

-- Points transaction log
CREATE TABLE IF NOT EXISTS points_log (
    log_id      INT AUTO_INCREMENT PRIMARY KEY,
    member_id   INT NOT NULL,
    booking_id  INT DEFAULT NULL,
    points      INT NOT NULL,          -- positive = earned, negative = redeemed
    type        ENUM('earned','redeemed') NOT NULL,
    description VARCHAR(255),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id)  REFERENCES members(member_id)  ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE SET NULL
);

-- ============================================================
-- Points rules (for reference — enforced in PHP):
--   Earn  : 1 point per S$1 spent (floor)
--   Redeem: 100 points = S$5 discount (min 100 pts to redeem)
--   Tiers : Bronze 0-499 | Silver 500-1499 | Gold 1500+
-- ============================================================