-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 17, 2025 at 10:25 AM
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
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `sent_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `organizer_id` int(11) NOT NULL,
  `event_date` datetime NOT NULL,
  `venue` varchar(200) DEFAULT NULL,
  `event_type` enum('online','offline','hybrid') DEFAULT 'offline',
  `category` varchar(50) DEFAULT NULL,
  `max_participants` int(11) DEFAULT 100,
  `registration_deadline` datetime DEFAULT NULL,
  `registration_link` varchar(500) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('upcoming','ongoing','completed','cancelled') DEFAULT 'upcoming',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `contact_info` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `organizer_id`, `event_date`, `venue`, `event_type`, `category`, `max_participants`, `registration_deadline`, `registration_link`, `image`, `status`, `created_at`, `contact_info`) VALUES
(3, 'hackaton', 'hackers are invited.', 6, '2025-11-20 12:50:00', 'classroom 105', 'offline', 'Competition', 50, '2025-11-12 12:48:00', NULL, '', 'upcoming', '2025-11-10 07:19:32', NULL),
(4, 'Navrang Dance Competition', 'Navrang celebrates the vibrant hues of dance and culture. From classical grace to modern grooves, witness a spectacle of talent, energy, and creativity!\r\n\r\n???? DRESS CODE :\r\nBring the heat with long skirts, trendy tops, and chic jumpsuits paired with statement ethnic jackets. Add a dash of sparkle with mirror-work stoles and make this Garba eve unforgettable! ???????????? \r\n\r\n???? Music, masti, food & memories await ????\r\n\r\n\r\nNote: Even if you pay offline you must fill out the Google form to get your passes.\r\n\r\n Limited slots. No entry without passes. ????️', 2, '2025-11-21 12:30:00', 'UMIT Foyer', 'offline', 'Cultural', 15, '2025-11-15 09:30:00', NULL, 'event_1763021679_6915936f0b241.jpg', 'upcoming', '2025-11-10 05:43:42', NULL),
(5, 'Smart India Hackathon', 'Hey Innovators!!\r\n\r\n????If you are looking to display your pathbreaking ideas, coding skills, team spirit and want to gain a real application based experience????\r\n\r\n????Then quickly register your team for the internal hackathon of SIH IDEA PRESENTATION of UMIT- SNDTWU.', 2, '2025-12-01 10:00:00', 'Conference Hall', 'offline', 'Technical', 1, '2025-11-23 18:45:00', NULL, 'event_1763021645_6915934d96352.jpg', 'upcoming', '2025-11-10 05:50:08', NULL),
(7, 'Debattle Ground   [Debate Competiton]', 'DEBATE (10 AM): Engage in intellectual discourse and showcase your prowess in our exhilarating debate competition!\r\n\r\n???? For Debate, both single audition and duo is allowed.\r\nFor single, you will be given 1 min where you will first speak in favour of the motion and then in opposition of the motion.\r\nFor Duo, you will be given 1 min each and one will be speaking in favour of the motion and another one in opposition of the motion.', 2, '2025-11-19 09:30:00', 'Classroom no. 200', 'offline', 'Other', 40, '2025-11-13 23:00:00', NULL, 'event_1763021607_6915932779b0f.jpg', 'upcoming', '2025-11-10 06:09:16', NULL),
(8, 'Chess Competition', 'Battle of Kings is a thrilling chess competition that brings together sharp minds and strategic thinkers. Test your intellect, patience, and foresight as you face off against worthy opponents on the 64 squares of challenge. Whether you’re a seasoned player or a budding strategist, this event promises intense matches, tactical brilliance, and the ultimate test of concentration.', 2, '2025-11-28 11:25:00', 'Common Room', 'offline', 'Sports', 10, '2025-11-22 23:59:00', NULL, 'event_1763021593_69159319a9e12.jpg', 'upcoming', '2025-11-10 06:21:44', NULL),
(9, 'Innovision-Project Exhibition', 'Innovision is a platform for brilliant young minds to showcase their creativity, innovation, and technical excellence. From groundbreaking prototypes to ingenious real-world solutions, the exhibition celebrates the power of ideas that can shape the future.\r\n\r\nStudents from various departments will present their projects across diverse domains — technology, science, art, and sustainability. The event aims to inspire collaboration, spark curiosity, and recognize the next generation of innovators.', 2, '2025-12-15 10:30:00', 'UMIT Foyer', 'offline', 'Technical', 100, '2025-12-05 23:50:00', NULL, 'event_1763021555_691592f373c99.jpg', 'upcoming', '2025-11-10 06:40:09', NULL),
(10, 'Short Film Competition', 'Reel Vision – Short Film Competition 2025 invites budding filmmakers, storytellers, and visionaries to showcase their cinematic creativity.\r\nCapture powerful stories, emotions, and ideas in a few minutes of film and let your camera do the talking. Whether it’s drama, comedy, documentary, or experimental art — every frame counts!', 2, '2025-12-11 12:14:00', 'Conference Hall', 'offline', 'Competition', 10, '2025-11-30 05:30:00', NULL, 'event_1763021538_691592e251095.jpg', 'completed', '2025-11-10 06:49:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `event_likes`
--

CREATE TABLE `event_likes` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `liked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_likes`
--

INSERT INTO `event_likes` (`id`, `event_id`, `user_id`, `liked_at`) VALUES
(3, 7, 2, '2025-11-13 09:12:17'),
(4, 3, 8, '2025-11-13 13:47:58'),
(6, 3, 2, '2025-11-13 14:18:25'),
(7, 3, 1, '2025-11-13 16:38:01'),
(8, 7, 8, '2025-11-13 17:27:51'),
(10, 7, 1, '2025-11-13 19:44:32'),
(11, 4, 1, '2025-11-13 19:46:05'),
(12, 4, 8, '2025-11-13 20:01:27'),
(14, 3, 7, '2025-11-13 20:02:22'),
(15, 4, 7, '2025-11-13 20:02:24'),
(17, 9, 7, '2025-11-13 20:04:21'),
(18, 7, 6, '2025-11-13 20:06:01'),
(19, 8, 2, '2025-11-14 03:07:04');

-- --------------------------------------------------------

--
-- Table structure for table `event_saves`
--

CREATE TABLE `event_saves` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `saved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_saves`
--

INSERT INTO `event_saves` (`id`, `event_id`, `user_id`, `saved_at`) VALUES
(5, 7, 8, '2025-11-13 13:48:01'),
(8, 4, 7, '2025-11-13 20:03:24'),
(9, 3, 7, '2025-11-13 20:03:30');

-- --------------------------------------------------------

--
-- Table structure for table `merchandise`
--

CREATE TABLE `merchandise` (
  `id` int(11) NOT NULL,
  `organizer_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` enum('t-shirt','oversized-tshirt','hoodie','cap','tote-bag','cup','sweatshirt','mask','diary','magazine','other') NOT NULL,
  `sizes_available` varchar(100) DEFAULT NULL,
  `size_guide` text DEFAULT NULL,
  `quantity_available` int(11) DEFAULT 0,
  `contact_info` varchar(200) DEFAULT NULL,
  `order_form_link` varchar(500) DEFAULT NULL,
  `return_policy` text DEFAULT NULL,
  `distribution_date` date DEFAULT NULL,
  `distribution_venue` varchar(200) DEFAULT NULL,
  `distribution_time` time DEFAULT NULL,
  `status` enum('available','out_of_stock','discontinued') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `merchandise`
--

INSERT INTO `merchandise` (`id`, `organizer_id`, `name`, `description`, `price`, `category`, `sizes_available`, `size_guide`, `quantity_available`, `contact_info`, `order_form_link`, `return_policy`, `distribution_date`, `distribution_venue`, `distribution_time`, `status`, `created_at`) VALUES
(1, 2, 'T SHIRT 2025', 'ON POPULAR DEMAND WE ARE BACK WITH OUR COLLEGE TSHIRT!!!!!*\r\n\r\nGet ready to turn heads because our freshest gear is here!\r\nBehold, the epic return of official *College Merch*! \r\n\r\n✨ MERCH LINK IS OPEN ONLY FOR 2 DAYS SO BOOK YOURS NOW✨\r\n\r\nDive into our Instagram for an exclusive peek: \r\n\r\nhttps://www.instagram.com/reel/C3ovE77B1bH/?igsh=MTc3bHdxeTd6cjgyNg==\r\n\r\n\r\n⚡Quick! Secure yours now before they vanish into the campus abyss:\r\n\r\nDon\'t just walk, strut like a legend! Grab yours TODAY! \r\nPayment details:\r\n\r\n▫️ Mode of payment: Cash/ UPI is accepted \r\n▫️ Pay offline to: Your CR and SR\r\n▫️ Pay online to:\r\n\r\n Tanisha Purohit \r\nGpay no.: 9082155840\r\n Vedika Sonawane\r\nGpay no.: 82618 75092\r\n\r\n\r\n*NOTE:- Whoever is paying offline need not fill the Google form kindly submit all your details to your CR/SR.*', 299.00, 't-shirt', 'XS,S,M,L,XL,2XL,3XL', '', 50, '9876543210', 'https://forms.gle/jTqQsP3Xc4F77vfx7', '', '2025-11-23', '1st floor auditorium', '13:00:00', 'available', '2025-11-10 08:24:13'),
(3, 2, 'Mug', 'Celebrate innovation and empowerment with every sip. The Women in STEM Mug is designed for thinkers, creators, and problem-solvers who are shaping the future. Made from durable, high-quality ceramic, this mug features a bold “Women in STEM” design and vibrant artwork that inspires confidence and creativity.\r\n\r\nWhether you’re fueling up for a long coding session, late-night lab work, or your morning coffee ritual, this mug is the perfect companion.\r\n\r\nMaterial: Premium ceramic\r\n\r\nCapacity: 11 oz / 325 ml\r\n\r\nDesign: “Women in STEM” print with colorful graphic artwork\r\n\r\nFinish: Glossy white exterior with durable, fade-resistant print\r\n\r\nCare: Microwave and dishwasher safe\r\n\r\nShow your support for women breaking barriers in science, technology, engineering, and mathematics — one cup of coffee at a time.', 199.00, 'cup', 'Free Size', '', 50, '9876543210', 'https://forms.gle/jTqQsP3Xc4F77vfx7', 'NO return, ONLY exchange', '2025-11-25', '1st floor auditorium', '12:00:00', 'available', '2025-11-13 08:28:44'),
(4, 2, 'Notebook', 'Fuel your creativity, capture your ideas, and take notes in style with the Women in STEM Notebook — designed for innovators, dreamers, and doers who are shaping the world through science, technology, engineering, and math.\r\n\r\nThis sleek, high-quality notebook features a bold “Women in STEM” design on the cover with vibrant, inspiring artwork that celebrates diversity and empowerment in innovation. Perfect for jotting down research notes, sketches, project ideas, or everyday thoughts.\r\n\r\nCover: Durable matte finish with “Women in STEM” printed design\r\n\r\nSize: A5 (5.8 x 8.3 in) — compact and easy to carry\r\n\r\nPages: 120 lined pages for writing, planning, or journaling\r\n\r\nPaper: Smooth, high-quality 100gsm paper to prevent ink bleed\r\n\r\nBinding: Spiral / Perfect bound (customizable based on your product)\r\n\r\nEmpower your note-taking — because every great discovery starts with a single idea.', 149.00, 'diary', 'Free Size', '', 80, '9876543210', 'https://forms.gle/jTqQsP3Xc4F77vfx7', 'NO return, ONLY exchange', '2025-11-25', '1st floor auditorium', '12:00:00', 'available', '2025-11-13 08:30:33'),
(5, 2, 'Tote Bag', 'Carry confidence, creativity, and purpose wherever you go with the Women in STEM Tote Bag — available in both black and white. Designed for innovators, learners, and leaders, this tote celebrates the power of women in science, technology, engineering, and math.\r\n\r\nCrafted from durable, eco-friendly cotton canvas, it combines strength and style for everyday use. The vibrant “Women in STEM” design adds a bold pop of inspiration, making it perfect for school, work, or casual outings.\r\n\r\nColors: Black or White\r\n\r\nMaterial: 100% premium cotton canvas\r\n\r\nDesign: “Women in STEM” printed artwork celebrating empowerment and diversity\r\n\r\nSize: 15\" x 16\" (38cm x 40cm)\r\n\r\nHandles: Reinforced shoulder straps for comfortable carrying\r\n\r\nCare: Machine washable; air dry recommended\r\n\r\nSpacious, sustainable, and statement-making — this tote is more than just a bag; it’s a symbol of progress and pride for every woman making her mark in STEM.', 399.00, 'tote-bag', 'Free Size', '', 150, '9876543210', 'https://forms.gle/jTqQsP3Xc4F77vfx7', 'NO return, ONLY exchange', '2025-11-25', '1st floor auditorium', '04:00:00', 'available', '2025-11-13 08:32:59');

-- --------------------------------------------------------

--
-- Table structure for table `merchandise_images`
--

CREATE TABLE `merchandise_images` (
  `id` int(11) NOT NULL,
  `merchandise_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `merchandise_images`
--

INSERT INTO `merchandise_images` (`id`, `merchandise_id`, `image_path`, `is_primary`, `created_at`) VALUES
(1, 1, 'merch_1_1762763053_0.jpeg', 1, '2025-11-10 08:24:13'),
(2, 1, 'merch_1_1762763053_1.jpeg', 0, '2025-11-10 08:24:13'),
(4, 3, 'merch_3_1763022524_0.jpg', 1, '2025-11-13 08:28:44'),
(5, 4, 'merch_4_1763022633_0.jpg', 1, '2025-11-13 08:30:33'),
(6, 5, 'merch_5_1763022779_0.jpg', 1, '2025-11-13 08:32:59'),
(7, 5, 'merch_5_1763022779_1.jpg', 0, '2025-11-13 08:32:59');

-- --------------------------------------------------------

--
-- Table structure for table `merchandise_orders`
--

CREATE TABLE `merchandise_orders` (
  `id` int(11) NOT NULL,
  `merchandise_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `size` varchar(20) DEFAULT NULL,
  `order_status` enum('pending','confirmed','collected','cancelled') DEFAULT 'pending',
  `ordered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `year` varchar(50) DEFAULT NULL,
  `status` enum('registered','waitlisted','cancelled') DEFAULT 'registered',
  `notes` text DEFAULT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registrations`
--

INSERT INTO `registrations` (`id`, `event_id`, `user_id`, `name`, `email`, `phone`, `department`, `year`, `status`, `notes`, `registration_date`) VALUES
(2, 7, 8, 'Tanvi', 'tanu@gmail.com', '222222', 'Computer Science', '2nd Year', 'cancelled', 'hi', '2025-11-13 14:06:30'),
(3, 4, 8, 'Tanvi', 'tanu@gmail.com', '222222', 'Computer Science', '', 'registered', 'excited', '2025-11-13 19:18:50'),
(4, 7, 7, 'Diya', 'd@gmail.com', '1111111111', 'Information Technology', '2nd Year', 'cancelled', 'hi there', '2025-11-13 20:02:41'),
(5, 9, 7, 'Diya', 'd@gmail.com', '1111111111', 'Information Technology', '', 'registered', 'hi', '2025-11-13 20:04:27'),
(6, 9, 8, 'Tanvi', 'tanu@gmail.com', '222222', 'Computer Science', '4th Year', 'registered', '', '2025-11-14 04:40:48'),
(7, 3, 8, 'Tanvi', 'tanu@gmail.com', '222222', 'Computer Science', '2nd Year', 'registered', 'hi', '2025-11-14 12:57:08');

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
(1, 'admin', 'admin@campus.com', '', '0192023a7bbd73250516f069df18b500', 'admin', 'System Administrator', 'manegment', NULL, '', NULL, 'profile_1_1763063749.jpg', '2025-11-08 16:03:57', NULL),
(2, 'laxmi', 'laxmi@gmail.com', '', 'e10adc3949ba59abbe56e057f20f883e', 'organizer', 'laxmi', NULL, '', '', NULL, 'profile_2_1763043142.jpeg', '2025-11-08 16:16:16', NULL),
(6, 'anu', 'anu@gmail.com', '', '9904fd42e4977d5815b5d5679a935ed5', 'organizer', 'anushri', 'Electronics', '', NULL, NULL, NULL, '2025-11-10 06:56:25', 1),
(7, 'diya', 'd@gmail.com', '1111111111', 'e10adc3949ba59abbe56e057f20f883e', 'student', 'Diya', 'Information Technology', 'Second Year', '', NULL, NULL, '2025-11-10 09:02:27', 1),
(8, 'Tanvi', 'tanu@gmail.com', '222222', 'e10adc3949ba59abbe56e057f20f883e', 'student', 'Tanvi', 'Computer Science', 'Third Year', NULL, NULL, NULL, '2025-11-10 09:04:06', 1),
(10, 'surbhi', 'hello@gmail.com', '11111111', 'e10adc3949ba59abbe56e057f20f883e', 'student', 'surbhi', 'Mechanical', 'Second Year', NULL, NULL, NULL, '2025-11-14 04:56:20', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organizer_id` (`organizer_id`);

--
-- Indexes for table `event_likes`
--
ALTER TABLE `event_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_like` (`event_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `event_saves`
--
ALTER TABLE `event_saves`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_save` (`event_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `merchandise`
--
ALTER TABLE `merchandise`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organizer_id` (`organizer_id`);

--
-- Indexes for table `merchandise_images`
--
ALTER TABLE `merchandise_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `merchandise_id` (`merchandise_id`);

--
-- Indexes for table `merchandise_orders`
--
ALTER TABLE `merchandise_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `merchandise_id` (`merchandise_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_registration` (`event_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

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
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `event_likes`
--
ALTER TABLE `event_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `event_saves`
--
ALTER TABLE `event_saves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `merchandise`
--
ALTER TABLE `merchandise`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `merchandise_images`
--
ALTER TABLE `merchandise_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `merchandise_orders`
--
ALTER TABLE `merchandise_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `event_likes`
--
ALTER TABLE `event_likes`
  ADD CONSTRAINT `event_likes_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_saves`
--
ALTER TABLE `event_saves`
  ADD CONSTRAINT `event_saves_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_saves_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `merchandise`
--
ALTER TABLE `merchandise`
  ADD CONSTRAINT `merchandise_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `merchandise_images`
--
ALTER TABLE `merchandise_images`
  ADD CONSTRAINT `merchandise_images_ibfk_1` FOREIGN KEY (`merchandise_id`) REFERENCES `merchandise` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `merchandise_orders`
--
ALTER TABLE `merchandise_orders`
  ADD CONSTRAINT `merchandise_orders_ibfk_1` FOREIGN KEY (`merchandise_id`) REFERENCES `merchandise` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `merchandise_orders_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
