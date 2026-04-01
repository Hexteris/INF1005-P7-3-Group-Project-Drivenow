-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 31, 2026 at 06:16 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `car_rental`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`admin_id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', '$2y$10$/Mirg7zGG86MrjEJm.deYOPkex0Z87HHtyGSgX3paz4vRFVkCFRGy', '2026-03-29 02:20:14');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `total_cost` decimal(8,2) DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `member_id`, `car_id`, `start_time`, `end_time`, `total_cost`, `status`, `created_at`) VALUES
(1, 3, 1, '2026-03-29 12:30:00', '2026-03-29 13:30:00', 8.50, 'completed', '2026-03-29 04:05:09'),
(2, 4, 3, '2026-03-31 21:45:00', '2026-04-01 22:15:00', 330.75, 'confirmed', '2026-03-31 13:19:16'),
(3, 4, 4, '2026-04-01 09:00:00', '2026-04-17 22:00:00', 5955.00, 'confirmed', '2026-03-31 13:20:37'),
(4, 4, 2, '2026-03-31 21:45:00', '2026-03-31 22:45:00', 9.00, 'pending', '2026-03-31 13:21:33');

-- --------------------------------------------------------

--
-- Table structure for table `cars`
--

CREATE TABLE `cars` (
  `car_id` int(11) NOT NULL,
  `make` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `plate_no` varchar(20) NOT NULL,
  `category` enum('Economy','Comfort','SUV','Premium') NOT NULL,
  `seats` int(11) NOT NULL DEFAULT 5,
  `price_per_hr` decimal(6,2) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cars`
--

INSERT INTO `cars` (`car_id`, `make`, `model`, `plate_no`, `category`, `seats`, `price_per_hr`, `location`, `lat`, `lng`, `image_url`, `is_available`, `created_at`) VALUES
(1, 'Toyota', 'Corolla', 'SBA1001A', 'Economy', 5, 8.50, 'Tampines Hub', 1.3533000, 103.9440000, 'toyota-corolla.jpg', 1, '2026-03-29 02:20:14'),
(2, 'Honda', 'Jazz', 'SBB2002B', 'Economy', 5, 9.00, 'Punggol Drive', 1.4043000, 103.9024000, 'honda-jazz.jpg', 1, '2026-03-29 02:20:14'),
(3, 'Mazda', 'CX-30', 'SBC3003C', 'Comfort', 5, 13.50, 'Bishan MRT', 1.3510000, 103.8481000, 'mazda-cx30.jpg', 1, '2026-03-29 02:20:14'),
(4, 'Toyota', 'Camry', 'SBD4004D', 'Comfort', 5, 15.00, 'Jurong East', 1.3329000, 103.7422000, 'toyota-camry.jpg', 1, '2026-03-29 02:20:14'),
(5, 'Hyundai', 'Tucson', 'SBE5005E', 'SUV', 7, 16.50, 'Woodlands', 1.4370000, 103.7867000, 'hyundai-tucson.jpg', 1, '2026-03-29 02:20:14'),
(6, 'Kia', 'Sorento', 'SBF6006F', 'SUV', 7, 18.00, 'Serangoon', 1.3500000, 103.8737000, 'kia-sorento.jpg', 1, '2026-03-29 02:20:14'),
(7, 'BMW', '3 Series', 'SBG7007G', 'Premium', 5, 25.00, 'Orchard Road', 1.3048000, 103.8318000, 'bmw-3-series.jpg', 1, '2026-03-29 02:20:14'),
(8, 'Mercedes', 'C-Class', 'SBH8008H', 'Premium', 5, 28.50, 'Marina Bay', 1.2817000, 103.8636000, 'mercedes-c-class.jpg', 1, '2026-03-29 02:20:14');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `member_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `licence_no` varchar(50) DEFAULT NULL,
  `points` int(11) NOT NULL DEFAULT 0,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(128) DEFAULT NULL,
  `verification_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `referral_code` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`member_id`, `full_name`, `email`, `password`, `phone`, `licence_no`, `points`, `email_verified`, `verification_token`, `verification_expires`, `created_at`, `referral_code`) VALUES
(1, 'Test User', 'test@example.com', '$2y$10$oS.cjGKyF3knTVoTko2wTezv5Hm3rIChzgd8HCiO6eG/3ByIX77ae', '+65 9999 0000', 'S9999999Z', 0, 1, NULL, NULL, '2026-03-29 02:20:14', NULL),
(3, 'Daniel', 'tayzhuhaodaniel32@gmail.com', '$2y$10$suJeKP09pi1nelvs9XLAfOyxveob0iYNA9Xc0yVYA3TeiRTOxhWd.', '94676017', '1234', 8, 0, NULL, NULL, '2026-03-29 04:02:13', 'DAN385J'),
(4, 'dan', '2501690@sit.singaporetech.edu.sg', '$2y$10$H4wiOHstB.NIsR07S4P5leZu84iqyy2bbZ.UmeJ3YmzXGUU6FkCle', '+6594676017', '1234556E', 6285, 0, NULL, NULL, '2026-03-31 13:17:45', 'DAN931E');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `amount` decimal(8,2) NOT NULL,
  `card_name` varchar(100) NOT NULL,
  `card_last4` varchar(4) NOT NULL,
  `card_type` varchar(20) NOT NULL,
  `status` enum('paid','refunded','failed') DEFAULT 'paid',
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `booking_id`, `member_id`, `amount`, `card_name`, `card_last4`, `card_type`, `status`, `paid_at`) VALUES
(1, 1, 3, 8.50, 'Daniel', '1111', 'Card', 'paid', '2026-03-29 04:05:24'),
(2, 2, 4, 330.75, 'Daniel', '3333', 'Card', 'paid', '2026-03-31 13:19:28'),
(3, 3, 4, 5955.00, 'Daniel', '2222', 'Card', 'paid', '2026-03-31 13:20:47');

-- --------------------------------------------------------

--
-- Table structure for table `points_log`
--

CREATE TABLE `points_log` (
  `log_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `points` int(11) NOT NULL,
  `type` enum('earned','redeemed') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `points_log`
--

INSERT INTO `points_log` (`log_id`, `member_id`, `booking_id`, `points`, `type`, `description`, `created_at`) VALUES
(1, 3, 1, 8, 'earned', 'Earned for booking #1 (S$8.5 paid)', '2026-03-29 04:05:24'),
(2, 4, 2, 330, 'earned', 'Earned for booking #2 (S$330.75 paid)', '2026-03-31 13:19:28'),
(3, 4, 3, 5955, 'earned', 'Earned for booking #3 (S$5955 paid)', '2026-03-31 13:20:47');

-- --------------------------------------------------------

--
-- Table structure for table `referral_records`
--

CREATE TABLE `referral_records` (
  `id` int(11) NOT NULL,
  `referrer_user_id` int(11) NOT NULL,
  `referred_user_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `discount_used` tinyint(1) DEFAULT 0,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `car_id` (`car_id`);

--
-- Indexes for table `cars`
--
ALTER TABLE `cars`
  ADD PRIMARY KEY (`car_id`),
  ADD UNIQUE KEY `plate_no` (`plate_no`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`member_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `licence_no` (`licence_no`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD KEY `idx_verification_token` (`verification_token`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `points_log`
--
ALTER TABLE `points_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `referral_records`
--
ALTER TABLE `referral_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`),
  ADD KEY `fk_referrer` (`referrer_user_id`),
  ADD KEY `fk_referred` (`referred_user_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `car_id` (`car_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `cars`
--
ALTER TABLE `cars`
  MODIFY `car_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `points_log`
--
ALTER TABLE `points_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `referral_records`
--
ALTER TABLE `referral_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`car_id`) REFERENCES `cars` (`car_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE;

--
-- Constraints for table `points_log`
--
ALTER TABLE `points_log`
  ADD CONSTRAINT `points_log_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `points_log_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL;

--
-- Constraints for table `referral_records`
--
ALTER TABLE `referral_records`
  ADD CONSTRAINT `fk_referral_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_referred` FOREIGN KEY (`referred_user_id`) REFERENCES `members` (`member_id`),
  ADD CONSTRAINT `fk_referrer` FOREIGN KEY (`referrer_user_id`) REFERENCES `members` (`member_id`),
  ADD CONSTRAINT `referral_records_ibfk_1` FOREIGN KEY (`referrer_user_id`) REFERENCES `members` (`member_id`),
  ADD CONSTRAINT `referral_records_ibfk_2` FOREIGN KEY (`referred_user_id`) REFERENCES `members` (`member_id`),
  ADD CONSTRAINT `referral_records_ibfk_3` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`car_id`) REFERENCES `cars` (`car_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
