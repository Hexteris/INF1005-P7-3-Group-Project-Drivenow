-- ============================================================
-- DriveNow Car Rental - Database Setup Script
-- Run this in MySQL: mysql -u root -p < setup.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS car_rental CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE car_rental;

-- 1. Members
CREATE TABLE IF NOT EXISTS members (
    member_id   INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    phone       VARCHAR(20),
    licence_no  VARCHAR(50) UNIQUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Cars
CREATE TABLE IF NOT EXISTS cars (
    car_id       INT AUTO_INCREMENT PRIMARY KEY,
    make         VARCHAR(50) NOT NULL,
    model        VARCHAR(50) NOT NULL,
    plate_no     VARCHAR(20) NOT NULL UNIQUE,
    category     ENUM('Economy','Comfort','SUV','Premium') NOT NULL,
    seats        INT NOT NULL DEFAULT 5,
    price_per_hr DECIMAL(6,2) NOT NULL,
    location     VARCHAR(100),
    image_url    VARCHAR(255),
    is_available TINYINT(1) DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Bookings
CREATE TABLE IF NOT EXISTS bookings (
    booking_id  INT AUTO_INCREMENT PRIMARY KEY,
    member_id   INT NOT NULL,
    car_id      INT NOT NULL,
    start_time  DATETIME NOT NULL,
    end_time    DATETIME NOT NULL,
    total_cost  DECIMAL(8,2),
    status      ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (car_id)    REFERENCES cars(car_id)       ON DELETE CASCADE
);

-- 4. Reviews
CREATE TABLE IF NOT EXISTS reviews (
    review_id  INT AUTO_INCREMENT PRIMARY KEY,
    member_id  INT NOT NULL,
    car_id     INT NOT NULL,
    rating     TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment    TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (car_id)    REFERENCES cars(car_id)       ON DELETE CASCADE
);

-- 5. Admin Users
CREATE TABLE IF NOT EXISTS admin_users (
    admin_id   INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Seed Data
-- ============================================================

INSERT INTO cars (make, model, plate_no, category, seats, price_per_hr, location, is_available) VALUES
('Toyota',     'Corolla',    'SBA1001A', 'Economy', 5,  8.50, 'Tampines Hub',     1),
('Honda',      'Jazz',       'SBB2002B', 'Economy', 5,  9.00, 'Punggol Drive',    1),
('Mazda',      'CX-30',      'SBC3003C', 'Comfort', 5, 13.50, 'Bishan MRT',       1),
('Toyota',     'Camry',      'SBD4004D', 'Comfort', 5, 15.00, 'Jurong East',      1),
('Hyundai',    'Tucson',     'SBE5005E', 'SUV',     7, 16.50, 'Woodlands',        1),
('Kia',        'Sorento',    'SBF6006F', 'SUV',     7, 18.00, 'Serangoon',        1),
('BMW',        '3 Series',   'SBG7007G', 'Premium', 5, 25.00, 'Orchard Road',     1),
('Mercedes',   'C-Class',    'SBH8008H', 'Premium', 5, 28.50, 'Marina Bay',       1);

-- Admin account: username=admin, password=Admin@123
-- Generate fresh hash by running: php -r "echo password_hash('Admin@123', PASSWORD_DEFAULT);"
-- Then UPDATE admin_users SET password='<new_hash>' WHERE username='admin';
INSERT INTO admin_users (username, password) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- NOTE: The hash above is a placeholder. Run the command below after setup:
-- php /var/www/html/admin/gen-admin-hash.php

-- Sample member (password: Test@1234)
INSERT INTO members (full_name, email, password, phone, licence_no) VALUES
('Test User', 'test@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+65 9999 0000', 'S9999999Z');

-- ============================================================
-- Payments table (added for payment feature)
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    payment_id     INT AUTO_INCREMENT PRIMARY KEY,
    booking_id     INT NOT NULL UNIQUE,
    member_id      INT NOT NULL,
    amount         DECIMAL(8,2) NOT NULL,
    card_name      VARCHAR(100) NOT NULL,
    card_last4     VARCHAR(4) NOT NULL,
    card_type      VARCHAR(20) NOT NULL,
    status         ENUM('paid','refunded','failed') DEFAULT 'paid',
    paid_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id)  REFERENCES members(member_id)   ON DELETE CASCADE
);
