-- ============================================================
-- Migration: Add referral code (discount code) column to members table
-- Run once: mysql -u root -p car_rental < migration_referral_system.sql
-- ============================================================

ALTER TABLE members
    ADD COLUMN referral_code VARCHAR(20) UNIQUE;


CREATE TABLE referral_records (
  referral_id INT AUTO_INCREMENT PRIMARY KEY,
  referrer_user_id INT NOT NULL,        -- who owns the code
  referred_user_id INT NOT NULL, -- new user who used it (once code per user)
  booking_id INT NOT NULL UNIQUE,              -- which booking used the code
  discount_used BOOLEAN DEFAULT FALSE,
  used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  -- Foreign keys with proper delete rules
  FOREIGN KEY (referrer_user_id) 
    REFERENCES members(member_id)
    ON DELETE RESTRICT,

  FOREIGN KEY (referred_user_id) 
    REFERENCES members(member_id)
    ON DELETE RESTRICT,

  FOREIGN KEY (booking_id) 
    REFERENCES bookings(booking_id)
    ON DELETE CASCADE
);