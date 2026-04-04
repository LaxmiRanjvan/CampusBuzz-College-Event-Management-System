-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 21, 2026 at 06:36 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `campus_events`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','organizer','student') NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `year` varchar(20) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `phone`, `password`, `role`, `full_name`, `department`, `year`, `bio`, `address`, `profile_image`, `created_at`, `created_by`) VALUES
(1, 'admin', 'admin@campus.com', '', '$2y$10$Z29/XbpK3R4NVbLlvjiOquAH/5NyOJ3yt8DUfHJGwBMbJcZygY2Py', 'admin', 'System Administrator', 'manegment', NULL, '', NULL, 'profile_1_1763063749.jpg', '2025-11-08 16:03:57', NULL),
(2, 'laxmi', 'diyakoli504@gmail.com', '84120324596', '$2y$10$B2OVJtTc/YjLUoLe2y/rZuCPi.KCuchgYolqp0H94j4ZKgjbdJ6bG', 'organizer', 'laxmi', 'Business Administration', '', '', NULL, 'profile_2_1769749502.jpg', '2025-11-08 16:16:16', NULL),
(6, 'anu', '201002iftanvipatil@gmail.com', '9152427565', '$2y$10$TI5ywdntQlIjYGZ8qBQAZ.AqdXdVZbZP3u7ml4rFCmaH92wbtpU1.', 'organizer', 'anushri', 'Electronics', '', NULL, NULL, NULL, '2025-11-10 06:56:25', 1),
(11, 'palvi', 'laxmiranjvan59@gmail.com', '8426155210', '$2y$10$37EnDdb.K.HillNxMbBjgeNqbDdprUzogoCmwBGy.ERmN5FX1TF82', 'organizer', 'palvi', 'Business Administration', '', NULL, NULL, NULL, '2026-01-25 13:44:53', 1),
(13, 'laxmiran', 'fs20if011@gmail.com', '912345678', '$2y$10$Twgp//0zUUNBg6EKr0Y.6eLSBCuazRecNGBCBcD5J4XjNjMgIaSPS', 'student', 'laxmi rann', 'Mechanical', 'Second Year', NULL, NULL, NULL, '2026-01-25 17:27:42', 1),
(15, 'Priya', 'fs20if026@gmail.com', '912345678', '$2y$10$YU6HukVTpS3H6afMCOFnY.fjhDSVL12kzBl9bFdSignCTNbXLkaCu', 'organizer', 'Priya', 'Electrical', '', NULL, NULL, NULL, '2026-01-27 12:38:53', 1),
(16, 'Sakshi', 'sakshi@gmail.com', '9851236478', '$2y$10$9Kpa2vDAu.o34jc5QPfCZO4cHbx/jaCxs4Bf/RnmUbSwlM4eyAAny', 'student', 'Sakshi', 'Civil', 'First Year', NULL, NULL, NULL, '2026-01-27 14:02:13', 1),
(17, 'prachi', 'prachi@gmail.com', '9876543210', '$2y$10$2VY0cu/8QgU6EKp9V0Wi1ebr5IgeZuBSl149Cth0XONCJcA/5pZRq', 'student', 'Prachi', 'Computer Science', 'Second Year', NULL, NULL, NULL, '2026-01-27 14:46:38', 1),
(18, 'shruti', 'shruti@gmail.com', '9563227410', '$2y$10$jgQwpl5qz/f6fBWw4iMr3O4X3qei/yHbuGLM4FARQQP.ygNC25Gou', 'student', 'Shruti', 'Computer Science', 'Fourth Year', NULL, NULL, NULL, '2026-01-27 14:47:55', 1),
(19, 'anushka', 'anushka@gmail.com', '', '$2y$10$isKw6StRk8q9PzcrS71oIe3hF14kiKshI5QIMxREKiamtO7Bkt00y', 'student', 'Anushka', 'Information Technology', 'Third Year', NULL, NULL, NULL, '2026-01-27 14:50:18', 1),
(20, 'kavya', 'kavya@gmail.com', '8720156489', '$2y$10$YDV.EGJAcQ0cEZlaQACoZOd11wosupY57MFSsWz.arUZhhpXpDb9C', 'student', 'Kavya', 'Information Technology', 'Second Year', NULL, NULL, NULL, '2026-01-27 14:51:54', 1),
(21, 'mona', 'mona@gmail.com', '7201458896', '$2y$10$Wa6uDV9mZWbIgOHWxalSIO.5h268SozkDg4pr6jnGfhuGTnZO9OhG', 'student', 'Mona', 'Electronics', 'First Year', NULL, NULL, NULL, '2026-01-27 14:52:54', 1),
(22, 'zeel', 'zeel@gmail.com', '', '$2y$10$/ka9ikL2uMzKp/Xys820aOP9zDkBpBKv9pw3ZFVeQv0tg40PE6zP2', 'student', 'Zeel', 'Electronics', 'Third Year', NULL, NULL, NULL, '2026-01-27 14:53:31', 1),
(23, 'chitra', 'chitra@gmail.com', '7301145888', '$2y$10$BjCZaoe.e0gItqJo1NXwKOmaNKPyBIPOBlrbWAa7Z4sClsBPjE9RS', 'student', 'Chitra', 'Mechanical', 'First Year', NULL, NULL, NULL, '2026-01-27 14:56:33', 1),
(24, 'divya', 'divya@gmail.com', '9870224615', '$2y$10$RCUHL698OQ2quG9M0RTevupDKyLgNTYLePOz065SZl1pAxJapiaL.', 'student', 'Divya', 'Mechanical', 'Graduate', NULL, NULL, NULL, '2026-01-27 14:57:36', 1),
(25, 'vidhi', 'vidhi@gmail.com', '8455720014', '$2y$10$JRrCR/SfBLvcqwfrDfD7D.PBSCIyzIPIviJyGAG.d2.6QBhXUyNba', 'student', 'Vidhi', 'Electrical', 'Second Year', NULL, NULL, NULL, '2026-01-27 14:59:02', 1),
(26, 'megha', 'megha@gmail.com', '8456102277', '$2y$10$g8DI1IrPXskxcP/UH4YMz.K.Ib5dbP47W.TmS8/g6PdPhfFOEeLJu', 'student', 'Megha', 'Electrical', 'First Year', NULL, NULL, NULL, '2026-01-27 15:00:10', 1),
(27, 'gauri', 'gauri@gmail.com', '7500164895', '$2y$10$zGfr4zRoM0FKEogVzQdSb.dZ8HNYroiRz.ewTxF/D7nEFKL34vseO', 'student', 'Gauri', 'Civil', 'Graduate', NULL, NULL, NULL, '2026-01-27 15:01:25', 1),
(28, 'tanvi', '777tanvipatil@gmail.com', '9146261587', '$2y$10$w7j4Kc1.NY3NY1niYkxNKewDrr88xDRtQuFU1edCVNCrklgRk3ysS', 'student', 'tanvi patil', 'Information Technology', 'Fourth Year', '', NULL, NULL, '2026-01-27 15:13:37', 1),
(30, 'Sitara', 'sitara@gmail.com', '', '$2y$10$TI7tdhQLa4IF6Mn1gE243OFr/DI6hMD/apMll4w1ad2YSV2fTr8z6', 'student', 'Sitara', 'Electronics', 'Second Year', NULL, NULL, NULL, '2026-01-28 07:12:06', 1),
(31, 'Omranj', 'laxmiom23@gmail.com', '9146261587', '$2y$10$Fzf413pI5bO8pxI.Fh8kWOMOytmbfa3MdAL/bAwcvXJ6thhYOdiAW', 'student', 'laxmi Ranjvan', 'Computer Science', 'Fourth Year', NULL, NULL, NULL, '2026-02-21 10:00:22', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `created_by` (`created_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
