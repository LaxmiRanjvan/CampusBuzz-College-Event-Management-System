-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 16, 2026 at 04:10 PM
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
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `cert_type` varchar(20) NOT NULL COMMENT 'participation | 1st | 2nd | 3rd | custom',
  `cert_label` varchar(255) NOT NULL COMMENT 'Human-readable label shown on certificate',
  `cert_id` varchar(100) NOT NULL COMMENT 'Unique certificate identifier printed on cert',
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `emailed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`id`, `user_id`, `recipient_email`, `subject`, `sent_date`) VALUES
(1, 2, 'd@gmail.com', 'Reminder: Innovision-Project Exhibition is coming up!', '2026-01-25 19:12:18'),
(2, 2, 'tanu@gmail.com', 'Reminder: Innovision-Project Exhibition is coming up!', '2026-01-25 19:12:22'),
(3, 1, 'laxmiranjvan59@gmail.com', 'Your Campus Event Manager Account Credentials', '2026-01-25 19:16:17'),
(4, 1, '201002iftanvipati@gmail.com', 'Your Campus Event Manager Account Credentials', '2026-01-25 22:01:24'),
(5, 1, '201002iftanvipatil@gmail.com', 'Your Campus Event Manager Account Credentials', '2026-01-25 22:03:33'),
(6, 1, '201002iftanvipatil@gmail.com', 'Your Campus Event Manager Account Credentials', '2026-01-25 22:07:39'),
(7, 1, 'laxmiranjvan01@gmail.com', 'Your Campus Event Manager Account Credentials', '2026-01-25 22:51:38'),
(8, 1, 'laxmiranjvan01@gmail.com', 'Your Campus Event Manager Account Credentials', '2026-01-25 22:51:57'),
(9, 1, 'laxmiranjvan01@gmail.com', 'Your Campus Event Manager Account Credentials', '2026-01-25 22:57:54'),
(10, 1, 'laxmiranjvan01@gmail.com', 'Your Campus Event Manager Account Credentials', '2026-01-25 22:59:49'),
(11, 1, 'laxmiranjvan17@gmail.com', 'Your Campus Event Manager Account Credentials', '2026-01-25 23:05:19'),
(12, 2, 'laxmiranjvan17@gmail.com', 'Your Ticket for Innovision-Project Exhibition', '2026-01-25 23:27:20'),
(13, 1, 'fs20if011@gmail.com', 'Your Campus Event Manager Account Credentials', '2026-01-25 23:35:49'),
(14, 2, 'laxmiranjvan59@gmail.com', 'You\'ve been invited as Co-Organizer - Innovision-Project Exhibition', '2026-01-26 22:24:38'),
(15, 1, 'diya44505@gmail.com', 'Your Campus Event Manager Account Credentials', '2026-01-27 18:06:45'),
(16, 1, 'fs20if026@gmail.com', 'Your Campus Event Manager Account Credentials', '2026-01-27 18:09:04'),
(17, 1, '777tanvipatil@gmail.com', 'Your Campus Event Manager Account Credentials', '2026-01-27 20:43:45'),
(18, 1, 'fs20if026spoken@gmail.com', 'Your Campus Event Manager Account Credentials', '2026-01-27 20:50:09'),
(19, 2, 'fs20if026spoken@gmail.com', 'Your Ticket for Chess Competition', '2026-01-27 20:53:00'),
(20, 1, 'sitara@gmail.com', 'Your Campus Event Manager Account Credentials', '2026-01-28 12:42:47'),
(21, 1, 'fs20if011@gmail.com', 'Your Campus Event Manager Account Credentials', '2026-02-21 17:07:13'),
(22, 2, 'prachi@gmail.com', '???? Back in Stock: Tote Bag', '2026-02-21 22:46:22'),
(23, 2, 'shruti@gmail.com', '???? Back in Stock: Tote Bag', '2026-02-21 22:46:26'),
(24, 2, 'laxmiom23@gmail.com', '???? Back in Stock: Tote Bag', '2026-02-21 22:46:30'),
(25, 2, 'fs20if011@gmail.com', '⚠️ Limited Stock: Tote Bag - Only 150 Left!', '2026-04-16 19:38:56'),
(26, 2, 'sakshi@gmail.com', '⚠️ Limited Stock: Tote Bag - Only 150 Left!', '2026-04-16 19:39:00'),
(27, 2, 'prachi@gmail.com', '⚠️ Limited Stock: Tote Bag - Only 150 Left!', '2026-04-16 19:39:04'),
(28, 2, 'shruti@gmail.com', '⚠️ Limited Stock: Tote Bag - Only 150 Left!', '2026-04-16 19:39:08'),
(29, 2, 'anushka@gmail.com', '⚠️ Limited Stock: Tote Bag - Only 150 Left!', '2026-04-16 19:39:12'),
(30, 2, 'kavya@gmail.com', '⚠️ Limited Stock: Tote Bag - Only 150 Left!', '2026-04-16 19:39:17'),
(31, 2, 'mona@gmail.com', '⚠️ Limited Stock: Tote Bag - Only 150 Left!', '2026-04-16 19:39:21'),
(32, 2, 'zeel@gmail.com', '⚠️ Limited Stock: Tote Bag - Only 150 Left!', '2026-04-16 19:39:25'),
(33, 2, 'chitra@gmail.com', '⚠️ Limited Stock: Tote Bag - Only 150 Left!', '2026-04-16 19:39:29'),
(34, 2, 'divya@gmail.com', '⚠️ Limited Stock: Tote Bag - Only 150 Left!', '2026-04-16 19:39:33'),
(35, 2, 'vidhi@gmail.com', '⚠️ Limited Stock: Tote Bag - Only 150 Left!', '2026-04-16 19:39:37'),
(36, 2, 'megha@gmail.com', '⚠️ Limited Stock: Tote Bag - Only 150 Left!', '2026-04-16 19:39:43'),
(37, 2, 'gauri@gmail.com', '⚠️ Limited Stock: Tote Bag - Only 150 Left!', '2026-04-16 19:39:48'),
(38, 2, '777tanvipatil@gmail.com', '⚠️ Limited Stock: Tote Bag - Only 150 Left!', '2026-04-16 19:39:52'),
(39, 2, 'sitara@gmail.com', '⚠️ Limited Stock: Tote Bag - Only 150 Left!', '2026-04-16 19:39:56'),
(40, 2, 'laxmiom23@gmail.com', '⚠️ Limited Stock: Tote Bag - Only 150 Left!', '2026-04-16 19:40:01');

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
(4, 'Navrang Dance Competition', 'Navrang celebrates the vibrant hues of dance and culture. From classical grace to modern grooves, witness a spectacle of talent, energy, and creativity!\r\n\r\n???? DRESS CODE :\r\nBring the heat with long skirts, trendy tops, and chic jumpsuits paired with statement ethnic jackets. Add a dash of sparkle with mirror-work stoles and make this Garba eve unforgettable! ???????????? \r\n\r\n???? Music, masti, food & memories await ????\r\n\r\n\r\nNote: Even if you pay offline you must fill out the Google form to get your passes.\r\n\r\n Limited slots. No entry without passes. ????️', 2, '2026-04-19 01:30:00', 'UMIT Foyer', 'offline', 'Cultural', 35, '2026-04-16 10:30:00', NULL, 'event_1763021679_6915936f0b241.jpg', 'upcoming', '2025-11-10 05:43:42', NULL),
(5, 'Smart India Hackathon', 'Hey Innovators!!\r\n\r\nIf you are looking to display your pathbreaking ideas, coding skills, team spirit and want to gain a real application based experience????\r\n\r\nThen quickly register your team for the internal hackathon of SIH IDEA PRESENTATION of UMIT- SNDTWU.', 2, '2026-04-29 12:00:00', 'Conference Hall', 'offline', 'Technical', 20, '2026-02-27 10:30:00', NULL, 'event_1763021645_6915934d96352.jpg', 'upcoming', '2025-11-10 05:50:08', NULL),
(7, 'Debattle Ground   [Debate Competiton]', 'DEBATE (10 AM): Engage in intellectual discourse and showcase your prowess in our exhilarating debate competition!\r\n\r\n???? For Debate, both single audition and duo is allowed.\r\nFor single, you will be given 1 min where you will first speak in favour of the motion and then in opposition of the motion.\r\nFor Duo, you will be given 1 min each and one will be speaking in favour of the motion and another one in opposition of the motion.', 2, '2026-04-18 10:30:00', 'Classroom no. 201', 'offline', 'Other', 20, '2026-04-16 23:00:00', NULL, 'event_1763021607_6915932779b0f.jpg', 'ongoing', '2025-11-10 06:09:16', NULL),
(8, 'Chess Competition', 'Battle of Kings is a thrilling chess competition that brings together sharp minds and strategic thinkers. Test your intellect, patience, and foresight as you face off against worthy opponents on the 64 squares of challenge. Whether you’re a seasoned player or a budding strategist, this event promises intense matches, tactical brilliance, and the ultimate test of concentration.', 2, '2026-04-24 11:30:00', 'Common Room', 'offline', 'Sports', 10, '2026-04-22 23:59:00', NULL, 'event_1763021593_69159319a9e12.jpg', 'upcoming', '2025-11-10 06:21:44', NULL),
(9, 'Innovision-Project Exhibition', 'Innovision is a platform for brilliant young minds to showcase their creativity, innovation, and technical excellence. From groundbreaking prototypes to ingenious real-world solutions, the exhibition celebrates the power of ideas that can shape the future.\r\n\r\nStudents from various departments will present their projects across diverse domains — technology, science, art, and sustainability. The event aims to inspire collaboration, spark curiosity, and recognize the next generation of innovators.', 2, '2026-05-01 10:30:00', 'UMIT Foyer', 'offline', 'Technical', 100, '2026-04-29 23:30:00', NULL, 'event_1763021555_691592f373c99.jpg', 'upcoming', '2025-11-10 06:40:09', NULL),
(10, 'Short Film Competition', 'Reel Vision – Short Film Competition 2025 invites budding filmmakers, storytellers, and visionaries to showcase their cinematic creativity.\r\nCapture powerful stories, emotions, and ideas in a few minutes of film and let your camera do the talking. Whether it’s drama, comedy, documentary, or experimental art — every frame counts!', 2, '2026-04-20 12:15:00', 'Conference Hall', 'offline', 'Competition', 10, '2026-04-19 16:08:00', NULL, 'event_1763021538_691592e251095.jpg', 'upcoming', '2025-11-10 06:49:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `event_comments`
--

CREATE TABLE `event_comments` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parent_comment_id` int(11) DEFAULT NULL,
  `comment_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_edited` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_comments`
--

INSERT INTO `event_comments` (`id`, `event_id`, `user_id`, `parent_comment_id`, `comment_text`, `created_at`, `updated_at`, `is_edited`) VALUES
(2, 9, 11, NULL, 'I\'m also part of this event as a co-organizer', '2026-01-26 17:55:31', '2026-01-26 17:55:31', 0),
(4, 9, 2, 2, 'Glad you are joining', '2026-01-26 17:57:54', '2026-01-26 17:57:54', 0),
(5, 10, 13, NULL, 'hi', '2026-02-26 19:08:00', '2026-02-26 19:08:00', 0),
(7, 10, 1, NULL, 'Admin is excited as well', '2026-04-16 13:35:13', '2026-04-16 13:35:13', 0);

-- --------------------------------------------------------

--
-- Table structure for table `event_co_organizers`
--

CREATE TABLE `event_co_organizers` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `organizer_id` int(11) NOT NULL COMMENT 'Co-organizer user ID',
  `invited_by` int(11) NOT NULL COMMENT 'Main organizer who invited',
  `permissions` enum('view','edit','full') DEFAULT 'view' COMMENT 'view=readonly, edit=modify, full=all access',
  `status` enum('pending','accepted','declined') DEFAULT 'pending',
  `invited_at` datetime NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_co_organizers`
--

INSERT INTO `event_co_organizers` (`id`, `event_id`, `organizer_id`, `invited_by`, `permissions`, `status`, `invited_at`, `responded_at`) VALUES
(1, 9, 11, 2, 'full', 'accepted', '2026-01-26 22:24:33', '2026-01-26 22:25:55');

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
(6, 3, 2, '2025-11-13 14:18:25'),
(7, 3, 1, '2025-11-13 16:38:01'),
(10, 7, 1, '2025-11-13 19:44:32'),
(11, 4, 1, '2025-11-13 19:46:05'),
(18, 7, 6, '2025-11-13 20:06:01'),
(19, 8, 2, '2025-11-14 03:07:04'),
(21, 9, 1, '2026-01-28 05:55:38'),
(22, 4, 28, '2026-04-04 15:52:34'),
(23, 10, 1, '2026-04-16 13:34:58');

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
  `upi_id` varchar(100) DEFAULT NULL,
  `qr_image` varchar(255) DEFAULT NULL,
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

INSERT INTO `merchandise` (`id`, `organizer_id`, `name`, `description`, `price`, `category`, `sizes_available`, `size_guide`, `quantity_available`, `contact_info`, `upi_id`, `qr_image`, `return_policy`, `distribution_date`, `distribution_venue`, `distribution_time`, `status`, `created_at`) VALUES
(1, 2, 'T SHIRT 2025', 'ON POPULAR DEMAND WE ARE BACK WITH OUR COLLEGE TSHIRT!!!!!*\r\n\r\nGet ready to turn heads because our freshest gear is here!\r\nBehold, the epic return of official *College Merch*! \r\n\r\n✨ MERCH LINK IS OPEN ONLY FOR 2 DAYS SO BOOK YOURS NOW✨\r\n\r\nDive into our Instagram for an exclusive peek: \r\n\r\nhttps://www.instagram.com/reel/C3ovE77B1bH/?igsh=MTc3bHdxeTd6cjgyNg==\r\n\r\n\r\n⚡Quick! Secure yours now before they vanish into the campus abyss:\r\n\r\nDon\'t just walk, strut like a legend! Grab yours TODAY! \r\nPayment details:\r\n\r\n▫️ Mode of payment: Cash/ UPI is accepted \r\n▫️ Pay offline to: Your CR and SR\r\n▫️ Pay online to:\r\n\r\n Tanisha Purohit \r\nGpay no.: 9082155840\r\n Vedika Sonawane\r\nGpay no.: 82618 75092\r\n\r\n\r\n*NOTE:- Whoever is paying offline need not fill the Google form kindly submit all your details to your CR/SR.*', 299.00, 't-shirt', 'XS,S,M,L,XL,2XL,3XL', '', 50, '9876543210', '9082155840', 'qr_1_1776348412_8701f0b2.jpeg', '', '2026-05-07', '1st floor auditorium', '13:00:00', 'available', '2025-11-10 08:24:13'),
(3, 2, 'Mug', 'Celebrate innovation and empowerment with every sip. The Women in STEM Mug is designed for thinkers, creators, and problem-solvers who are shaping the future. Made from durable, high-quality ceramic, this mug features a bold “Women in STEM” design and vibrant artwork that inspires confidence and creativity.\r\n\r\nWhether you’re fueling up for a long coding session, late-night lab work, or your morning coffee ritual, this mug is the perfect companion.\r\n\r\nMaterial: Premium ceramic\r\n\r\nCapacity: 11 oz / 325 ml\r\n\r\nDesign: “Women in STEM” print with colorful graphic artwork\r\n\r\nFinish: Glossy white exterior with durable, fade-resistant print\r\n\r\nCare: Microwave and dishwasher safe\r\n\r\nShow your support for women breaking barriers in science, technology, engineering, and mathematics — one cup of coffee at a time.', 199.00, 'cup', 'Free Size', '', 50, '9876543210', '8767214314', 'qr_3_1776348363_ab307548.jpeg', 'NO return, ONLY exchange', '2026-05-06', '1st floor auditorium', '12:00:00', 'available', '2025-11-13 08:28:44'),
(4, 2, 'Notebook', 'Fuel your creativity, capture your ideas, and take notes in style with the Women in STEM Notebook — designed for innovators, dreamers, and doers who are shaping the world through science, technology, engineering, and math.\r\n\r\nThis sleek, high-quality notebook features a bold “Women in STEM” design on the cover with vibrant, inspiring artwork that celebrates diversity and empowerment in innovation. Perfect for jotting down research notes, sketches, project ideas, or everyday thoughts.\r\n\r\nCover: Durable matte finish with “Women in STEM” printed design\r\n\r\nSize: A5 (5.8 x 8.3 in) — compact and easy to carry\r\n\r\nPages: 120 lined pages for writing, planning, or journaling\r\n\r\nPaper: Smooth, high-quality 100gsm paper to prevent ink bleed\r\n\r\nBinding: Spiral / Perfect bound (customizable based on your product)\r\n\r\nEmpower your note-taking — because every great discovery starts with a single idea.', 149.00, 'diary', 'Free Size', '', 80, '9876543210', '8767214314', 'qr_4_1776346720_da500b4d.jpeg', 'NO return, ONLY exchange', '2026-04-30', '1st floor auditorium', '12:00:00', 'available', '2025-11-13 08:30:33'),
(5, 2, 'Tote Bag', 'Carry confidence, creativity, and purpose wherever you go with the Women in STEM Tote Bag — available in both black and white. Designed for innovators, learners, and leaders, this tote celebrates the power of women in science, technology, engineering, and math.\r\n\r\nCrafted from durable, eco-friendly cotton canvas, it combines strength and style for everyday use. The vibrant “Women in STEM” design adds a bold pop of inspiration, making it perfect for school, work, or casual outings.\r\n\r\nColors: Black or White\r\n\r\nMaterial: 100% premium cotton canvas\r\n\r\nDesign: “Women in STEM” printed artwork celebrating empowerment and diversity\r\n\r\nSize: 15\" x 16\" (38cm x 40cm)\r\n\r\nHandles: Reinforced shoulder straps for comfortable carrying\r\n\r\nCare: Machine washable; air dry recommended\r\n\r\nSpacious, sustainable, and statement-making — this tote is more than just a bag; it’s a symbol of progress and pride for every woman making her mark in STEM.', 399.00, 'tote-bag', 'Free Size', '', 150, '8767214314', '8761214314', 'qr_5_1776348302_92a40b80.jpeg', 'NO return, ONLY exchange', '2026-04-23', '1st floor auditorium', '04:00:00', 'available', '2025-11-13 08:32:59');

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
  `payment_screenshot` varchar(255) DEFAULT NULL,
  `order_status` enum('pending','confirmed','collected','cancelled','rejected') DEFAULT 'pending',
  `organizer_comment` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `notified_at` datetime DEFAULT NULL,
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
(10, 9, 28, 'Tanvi', '777tanvipatil@gmail.com', '9146261587', 'Information Technology', '', 'registered', '', '2026-01-30 04:22:42'),
(11, 5, 28, 'Tanvi', '777tanvipatil@gmail.com', '9146261587', 'Information Technology', '2nd Year', 'registered', '', '2026-01-30 04:45:07'),
(12, 10, 28, 'Tanvi', '777tanvipatil@gmail.com', '9146261587', 'Information Technology', '2nd Year', 'cancelled', '', '2026-02-21 11:06:36'),
(13, 4, 28, 'tanvi patil', '777tanvipatil@gmail.com', '9146261587', 'Information Technology', 'Fourth Year', 'registered', '', '2026-04-04 09:40:46'),
(15, 10, 13, 'laxmi rann', 'fs20if011@gmail.com', '9123456787', 'Mechanical', 'Second Year', 'registered', '', '2026-02-26 19:15:45'),
(16, 4, 13, 'laxmi rann', 'fs20if011@gmail.com', '9123456780', 'Mechanical', 'Second Year', 'cancelled', '', '2026-02-26 19:01:35');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_verifications`
--

CREATE TABLE `ticket_verifications` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ticket_code` varchar(100) NOT NULL,
  `verified_by` int(11) NOT NULL,
  `verified_by_name` varchar(100) NOT NULL,
  `verified_at` datetime NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'admin', 'admin@campus.com', '91234567890', '$2y$10$Z29/XbpK3R4NVbLlvjiOquAH/5NyOJ3yt8DUfHJGwBMbJcZygY2Py', 'admin', 'System Administrator', 'Business Administration', 'Second Year', '', NULL, 'profile_1_1763063749.jpg', '2025-11-08 16:03:57', NULL),
(2, 'laxmi', 'diyakoli504@gmail.com', '8412032459', '$2y$10$B2OVJtTc/YjLUoLe2y/rZuCPi.KCuchgYolqp0H94j4ZKgjbdJ6bG', 'organizer', 'laxmi', 'Business Administration', 'Second Year', '', NULL, 'profile_2_1769749502.jpg', '2025-11-08 16:16:16', NULL),
(6, 'anu', '201002iftanvipatil@gmail.com', '9152427565', '$2y$10$TI5ywdntQlIjYGZ8qBQAZ.AqdXdVZbZP3u7ml4rFCmaH92wbtpU1.', 'organizer', 'anushri', 'Electronics', 'Second Year', NULL, NULL, NULL, '2025-11-10 06:56:25', 1),
(11, 'palvi', 'laxmiranjvan59@gmail.com', '8426155210', '123456', 'organizer', 'palvi', 'Business Administration', 'Second Year', NULL, NULL, NULL, '2026-01-25 13:44:53', 1),
(13, 'laxmiran', 'fs20if011@gmail.com', '912345678', '$2y$10$Twgp//0zUUNBg6EKr0Y.6eLSBCuazRecNGBCBcD5J4XjNjMgIaSPS', 'student', 'laxmi rann', 'Mechanical', 'Second Year', NULL, NULL, NULL, '2026-01-25 17:27:42', 1),
(15, 'Priya', 'fs20if026@gmail.com', '912345678', '$2y$10$YU6HukVTpS3H6afMCOFnY.fjhDSVL12kzBl9bFdSignCTNbXLkaCu', 'organizer', 'Priya', 'Electrical', 'Second Year', NULL, NULL, NULL, '2026-01-27 12:38:53', 1),
(16, 'Sakshi', 'sakshi@gmail.com', '9851236478', '$2y$10$9Kpa2vDAu.o34jc5QPfCZO4cHbx/jaCxs4Bf/RnmUbSwlM4eyAAny', 'student', 'Sakshi', 'Civil', 'First Year', NULL, NULL, NULL, '2026-01-27 14:02:13', 1),
(17, 'prachi', 'prachi@gmail.com', '9876543210', '$2y$10$2VY0cu/8QgU6EKp9V0Wi1ebr5IgeZuBSl149Cth0XONCJcA/5pZRq', 'student', 'Prachi', 'Computer Science', 'Second Year', NULL, NULL, NULL, '2026-01-27 14:46:38', 1),
(18, 'shruti', 'shruti@gmail.com', '9563227410', '$2y$10$jgQwpl5qz/f6fBWw4iMr3O4X3qei/yHbuGLM4FARQQP.ygNC25Gou', 'student', 'Shruti', 'Computer Science', 'Fourth Year', NULL, NULL, NULL, '2026-01-27 14:47:55', 1),
(19, 'anushka', 'anushka@gmail.com', '91234567890', '$2y$10$isKw6StRk8q9PzcrS71oIe3hF14kiKshI5QIMxREKiamtO7Bkt00y', 'student', 'Anushka', 'Information Technology', 'Third Year', NULL, NULL, NULL, '2026-01-27 14:50:18', 1),
(20, 'kavya', 'kavya@gmail.com', '8720156489', '$2y$10$YDV.EGJAcQ0cEZlaQACoZOd11wosupY57MFSsWz.arUZhhpXpDb9C', 'student', 'Kavya', 'Information Technology', 'Second Year', NULL, NULL, NULL, '2026-01-27 14:51:54', 1),
(21, 'mona', 'mona@gmail.com', '7201458896', '$2y$10$Wa6uDV9mZWbIgOHWxalSIO.5h268SozkDg4pr6jnGfhuGTnZO9OhG', 'student', 'Mona', 'Electronics', 'First Year', NULL, NULL, NULL, '2026-01-27 14:52:54', 1),
(22, 'zeel', 'zeel@gmail.com', '91234567890', '$2y$10$/ka9ikL2uMzKp/Xys820aOP9zDkBpBKv9pw3ZFVeQv0tg40PE6zP2', 'student', 'Zeel', 'Electronics', 'Third Year', NULL, NULL, NULL, '2026-01-27 14:53:31', 1),
(23, 'chitra', 'chitra@gmail.com', '7301145888', '$2y$10$BjCZaoe.e0gItqJo1NXwKOmaNKPyBIPOBlrbWAa7Z4sClsBPjE9RS', 'student', 'Chitra', 'Mechanical', 'First Year', NULL, NULL, NULL, '2026-01-27 14:56:33', 1),
(24, 'divya', 'divya@gmail.com', '9870224615', '$2y$10$RCUHL698OQ2quG9M0RTevupDKyLgNTYLePOz065SZl1pAxJapiaL.', 'student', 'Divya', 'Mechanical', 'Graduate', NULL, NULL, NULL, '2026-01-27 14:57:36', 1),
(25, 'vidhi', 'vidhi@gmail.com', '8455720014', '$2y$10$JRrCR/SfBLvcqwfrDfD7D.PBSCIyzIPIviJyGAG.d2.6QBhXUyNba', 'student', 'Vidhi', 'Electrical', 'Second Year', NULL, NULL, NULL, '2026-01-27 14:59:02', 1),
(26, 'megha', 'megha@gmail.com', '8456102277', '$2y$10$g8DI1IrPXskxcP/UH4YMz.K.Ib5dbP47W.TmS8/g6PdPhfFOEeLJu', 'student', 'Megha', 'Electrical', 'First Year', NULL, NULL, NULL, '2026-01-27 15:00:10', 1),
(27, 'gauri', 'gauri@gmail.com', '7500164895', '$2y$10$zGfr4zRoM0FKEogVzQdSb.dZ8HNYroiRz.ewTxF/D7nEFKL34vseO', 'student', 'Gauri', 'Civil', 'Graduate', NULL, NULL, NULL, '2026-01-27 15:01:25', 1),
(28, 'tanvi', '777tanvipatil@gmail.com', '9146261587', '$2y$10$w7j4Kc1.NY3NY1niYkxNKewDrr88xDRtQuFU1edCVNCrklgRk3ysS', 'student', 'tanvi patil', 'Information Technology', 'Fourth Year', '', NULL, NULL, '2026-01-27 15:13:37', 1),
(30, 'Sitara', 'sitara@gmail.com', '91234567890', '$2y$10$TI7tdhQLa4IF6Mn1gE243OFr/DI6hMD/apMll4w1ad2YSV2fTr8z6', 'student', 'Sitara', 'Electronics', 'Second Year', NULL, NULL, NULL, '2026-01-28 07:12:06', 1),
(31, 'Omranj', 'laxmiom23@gmail.com', '9146261587', '$2y$10$Fzf413pI5bO8pxI.Fh8kWOMOytmbfa3MdAL/bAwcvXJ6thhYOdiAW', 'student', 'laxmi Ranjvan', 'Computer Science', 'Fourth Year', NULL, NULL, NULL, '2026-02-21 10:00:22', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cert` (`event_id`,`user_id`),
  ADD KEY `idx_event` (`event_id`),
  ADD KEY `idx_user` (`user_id`);

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
-- Indexes for table `event_comments`
--
ALTER TABLE `event_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_id` (`event_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_parent_comment` (`parent_comment_id`);

--
-- Indexes for table `event_co_organizers`
--
ALTER TABLE `event_co_organizers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_co_organizer` (`event_id`,`organizer_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `organizer_id` (`organizer_id`),
  ADD KEY `invited_by` (`invited_by`),
  ADD KEY `idx_co_org_status` (`status`);

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
-- Indexes for table `ticket_verifications`
--
ALTER TABLE `ticket_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_verification` (`event_id`,`user_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_ticket_code` (`ticket_code`),
  ADD KEY `idx_verified_at` (`verified_at`);

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
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `event_comments`
--
ALTER TABLE `event_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `event_co_organizers`
--
ALTER TABLE `event_co_organizers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `event_likes`
--
ALTER TABLE `event_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `event_saves`
--
ALTER TABLE `event_saves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `merchandise`
--
ALTER TABLE `merchandise`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `merchandise_images`
--
ALTER TABLE `merchandise_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `merchandise_orders`
--
ALTER TABLE `merchandise_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `ticket_verifications`
--
ALTER TABLE `ticket_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `fk_cert_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cert_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `event_comments`
--
ALTER TABLE `event_comments`
  ADD CONSTRAINT `event_comments_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_comments_ibfk_3` FOREIGN KEY (`parent_comment_id`) REFERENCES `event_comments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_co_organizers`
--
ALTER TABLE `event_co_organizers`
  ADD CONSTRAINT `event_co_organizers_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_co_organizers_ibfk_2` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_co_organizers_ibfk_3` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `ticket_verifications`
--
ALTER TABLE `ticket_verifications`
  ADD CONSTRAINT `ticket_verifications_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_verifications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_verifications_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
