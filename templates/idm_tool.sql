-- --------------------------------------------------------

-- Table structure for table `defunct_users`

CREATE TABLE IF NOT EXISTS `defunct_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `source_id` int(11) NOT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','deleted') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_source_unique` (`user_id`, `source_id`),
  KEY `source_id` (`source_id`),
  CONSTRAINT `defunct_users_source_fk` FOREIGN KEY (`source_id`) REFERENCES `account_sources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add pending_deletion column to user_accounts if not exists
ALTER TABLE `user_accounts` 
ADD COLUMN IF NOT EXISTS `pending_deletion` tinyint(1) DEFAULT 0;
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 08, 2025 at 04:27 PM
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
-- Database: `idm_tool`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_sources`
--

CREATE TABLE `account_sources` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('LDAP','CSV','Database','Unix','XML','API') NOT NULL,
  `description` text DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `last_sync` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category` enum('Application','OS','Database') NOT NULL DEFAULT 'Application',
  `is_baseline` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `correlation_rules`
--

CREATE TABLE `correlation_rules` (
  `id` int(11) NOT NULL,
  `source_id` int(11) NOT NULL,
  `priority` int(11) NOT NULL,
  `match_field` varchar(100) NOT NULL,
  `match_type` enum('exact','partial','regex') DEFAULT 'exact',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','terminated') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_accounts`
--

CREATE TABLE `user_accounts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `source_id` int(11) NOT NULL,
  `account_id` varchar(255) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `matched_by` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `uncorrelated_accounts`
--

CREATE TABLE `uncorrelated_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_id` int(11) NOT NULL,
  `account_id` varchar(255) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `account_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`account_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `source_id` (`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `role_accounts`
CREATE TABLE `role_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL UNIQUE,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `uncorrelated_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_id` int(11) NOT NULL,
  `account_id` varchar(255) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `account_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`account_data`)),
  `role_account_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `source_id` (`source_id`),
  KEY `role_account_id` (`role_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_sources`
--
ALTER TABLE `account_sources`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `correlation_rules`
--
ALTER TABLE `correlation_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_accounts`
--
ALTER TABLE `user_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `source_id` (`source_id`,`account_id`),
  ADD KEY `fk_user_accounts_user` (`user_id`);

--
-- Indexes for table `uncorrelated_accounts`
--
ALTER TABLE `uncorrelated_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `source_id` (`source_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_sources`
--
ALTER TABLE `account_sources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `correlation_rules`
--
ALTER TABLE `correlation_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_accounts`
--
ALTER TABLE `user_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `uncorrelated_accounts`
--
ALTER TABLE `uncorrelated_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `correlation_rules`
--
ALTER TABLE `correlation_rules`
  ADD CONSTRAINT `correlation_rules_ibfk_1` FOREIGN KEY (`source_id`) REFERENCES `account_sources` (`id`);

--
-- Constraints for table `user_accounts`
--
ALTER TABLE `user_accounts`
  ADD CONSTRAINT `fk_user_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `user_accounts_ibfk_2` FOREIGN KEY (`source_id`) REFERENCES `account_sources` (`id`);

--
-- Constraints for table `uncorrelated_accounts`
--
ALTER TABLE `uncorrelated_accounts`
  ADD CONSTRAINT `uncorrelated_accounts_ibfk_1` FOREIGN KEY (`source_id`) REFERENCES `account_sources` (`id`);

ALTER TABLE `uncorrelated_accounts`
  ADD CONSTRAINT `fk_role_account_id` FOREIGN KEY (`role_account_id`) REFERENCES `role_accounts` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
