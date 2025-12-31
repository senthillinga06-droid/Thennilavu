-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Dec 31, 2025 at 03:53 AM
-- Server version: 11.4.9-MariaDB
-- PHP Version: 8.4.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `thennilavu_thennilavu`
--

-- --------------------------------------------------------

--
-- Table structure for table `additional_photos`
--

CREATE TABLE `additional_photos` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `upload_order` int(11) DEFAULT 1,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `additional_photos`
--

INSERT INTO `additional_photos` (`id`, `member_id`, `photo_path`, `upload_order`, `uploaded_at`) VALUES
(2, 66, 'uploads/additional_photos/1764695341_1_img3.png', 2, '2025-12-02 17:09:01'),
(3, 66, 'uploads/additional_photos/1764695341_2_img4.png', 3, '2025-12-02 17:09:01'),
(4, 66, 'uploads/additional_photos/1764695341_3_img5.png', 4, '2025-12-02 17:09:01'),
(5, 66, 'uploads/additional_photos/1764695341_4_img20.png', 5, '2025-12-02 17:09:01'),
(13, 67, 'uploads/1764823385_additional_0_1234.jpg', 1, '2025-12-04 04:43:05'),
(14, 67, 'uploads/1764823385_additional_1_car1.jpeg', 2, '2025-12-04 04:43:05'),
(15, 67, 'uploads/1764823776_additional_0_363ad30a36deed40f28ac8e69b339a06.jpg', 3, '2025-12-04 04:49:36'),
(16, 67, 'uploads/1764823776_additional_1_750x450_811934-untitled1.webp', 4, '2025-12-04 04:49:36'),
(18, 68, 'uploads/1764823942_additional_1_man-7796384_1280.jpg', 2, '2025-12-04 04:52:22'),
(19, 68, 'uploads/1764823942_additional_2_images__3_.jpeg', 3, '2025-12-04 04:52:22'),
(20, 69, 'uploads/1764824976_additional_0_750x450_811934-untitled1.webp', 1, '2025-12-04 05:09:37'),
(21, 69, 'uploads/1764824976_additional_1_363ad30a36deed40f28ac8e69b339a06.jpg', 2, '2025-12-04 05:09:37'),
(26, 67, 'uploads/1764827850_additional_0_23.jpg', 5, '2025-12-04 05:57:30'),
(28, 59, 'uploads/additional_photos/1764695341_2_img4.png', 1, '2025-12-04 06:49:38'),
(29, 59, 'uploads/1764830978_additional_1_ca52f60e-9f96-4f9c-8c01-7a3f90344d90.jpg', 2, '2025-12-04 06:49:38'),
(30, 58, 'uploads/1764831237_additional_0_363ad30a36deed40f28ac8e69b339a06.jpg', 1, '2025-12-04 06:53:57'),
(31, 58, 'uploads/1764831237_additional_1_images__1_.jpeg', 2, '2025-12-04 06:53:57'),
(51, 70, 'uploads/1764844273_additional_1_70.png', 2, '2025-12-04 10:31:13'),
(52, 70, 'uploads/1764844273_additional_2_70.png', 3, '2025-12-04 10:31:13'),
(53, 71, 'uploads/additional_photos/1765180114_0_WhatsApp Image 2025-11-18 at 9.39.57 PM(1).jpeg', 1, '2025-12-08 07:48:34'),
(60, 52, 'uploads/1766130515_additional_1_52.jpg', 2, '2025-12-19 07:48:35'),
(61, 52, 'uploads/1766130597_additional_1_52.jfif', 3, '2025-12-19 07:49:57'),
(65, 72, 'uploads/1766743811_additional_0_images.jpeg', 1, '2025-12-26 10:10:11'),
(66, 72, 'uploads/1766743852_additional_0_images__1_.jpeg', 2, '2025-12-26 10:10:52'),
(67, 72, 'uploads/1766743853_additional_0_images__1_.jpeg', 3, '2025-12-26 10:10:54'),
(68, 72, 'uploads/1766743853_additional_1_wedding.jpg', 4, '2025-12-26 10:10:54'),
(70, 62, 'uploads/1766744225_additional_1_62.jpg', 1, '2025-12-26 10:17:05'),
(71, 52, 'uploads/1766748645_additional_1_52.jpg', 4, '2025-12-26 11:30:45');

-- --------------------------------------------------------

--
-- Table structure for table `approved_ips`
--

CREATE TABLE `approved_ips` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `approved_sessions`
--

CREATE TABLE `approved_sessions` (
  `session_id` varchar(128) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blocked_ips`
--

CREATE TABLE `blocked_ips` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog`
--

CREATE TABLE `blog` (
  `blog_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `author_id` int(11) DEFAULT 1,
  `author_name` varchar(100) DEFAULT NULL,
  `author_photo` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `publish_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blog`
--

INSERT INTO `blog` (`blog_id`, `title`, `content`, `author_id`, `author_name`, `author_photo`, `category`, `status`, `publish_date`, `created_at`, `updated_at`) VALUES
(10, 'This is a matrimony website', 'We met through this platform and instantly connected over our shared love for travel and music. After months of conversations and several dates, we knew we had found our perfect match. Thank you for bringing us together!\r\n\r\n', 23, 'Priya & Rahul', 'uploads/6926b3257a4cc.jpg', 'Dating Tips', 'published', '2025-09-30', '2025-10-20 22:03:55', '2025-11-26 07:58:29'),
(11, 'Finding the right life partner felt impossible', 'Our families were looking for matches when we found each other\'s profiles. The connection was immediate, and we realized we had so much in common. We are grateful for this wonderful platform that made our union possible.\r\n\r\n', 45, 'Anjali & Vikram', 'uploads/6926b316f2eaf.webp', 'Dating Tips', 'published', '2024-06-20', '2025-10-20 22:05:39', '2025-11-26 07:58:14'),
(12, 'Our families were looking for matches', 'Finding the right life partner felt impossible until we discovered this matrimony service. The detailed profiles and genuine members made our search meaningful. We\'re now happily married and couldn\'t be more thankful!\r\n\r\n', 78, 'Sneha & Arjun', 'uploads/6926b2fb1e883.webp', 'Relationships', 'published', '2023-03-01', '2025-10-20 22:06:55', '2025-11-26 07:57:47'),
(13, 'We\'re living proof that true love exists', 'What started as a simple profile match turned into the most beautiful journey of our lives. The platform\'s user-friendly interface and genuine profiles helped us find each other in this vast world.\r\n\r\n', 98, 'Divya & Karthik', 'uploads/6926b30b193c0.jpg', 'Dating Tips', 'published', '2025-06-12', '2025-10-20 22:08:03', '2025-11-26 07:58:03'),
(14, 'Our story began with a simple message', 'We\'re living proof that true love exists! This platform helped us discover each other despite being from different cities. The advanced search filters and detailed preferences made all the difference.\r\n\r\n', 89, 'Meera & Aditya', 'uploads/6926b335f1ab4.webp', 'Relationships', 'published', '2025-10-03', '2025-10-20 22:09:06', '2025-11-26 07:58:45'),
(15, 'We met through this platform', 'Our story began with a simple message on this platform. What we thought would be just another profile turned out to be our soulmate. Forever grateful for this wonderful service!\r\n\r\n', 43, 'Isha & Rohan', 'uploads/6926b34c182ed.jpg', 'Dating Tips', 'published', '2024-06-04', '2025-10-20 22:10:50', '2025-11-26 07:59:08'),
(22, 'à®¤à¯‚à®°à®®à¯ à®¤à®¾à®©à¯ à®†à®©à®¾à®²à¯ à®•à¯‚à®Ÿ à®µà®¾à®©à®®à¯ à®¤à®¾à®©à¯ à®µà¯‡à®£à¯à®Ÿà¯à®®à¯ à®Žà®©à¯à®±à¯ à®®à¯‡à®•à®®à¯ à®¤à®¾à®©à¯ à®…à®´à¯à®¤à®¾à®²à¯à®®à¯ à®¯à®¾à®°à¯ à®®à¯€à®¤à¯ à®¤à®µà®±à®¾à®•à¯à®®à¯ ? S.L', 'à®¤à¯‚à®°à®®à¯ à®¤à®¾à®©à¯ à®†à®©à®¾à®²à¯ à®•à¯‚à®Ÿ à®µà®¾à®©à®®à¯ à®¤à®¾à®©à¯ à®µà¯‡à®£à¯à®Ÿà¯à®®à¯ à®Žà®©à¯à®±à¯ à®®à¯‡à®•à®®à¯ à®¤à®¾à®©à¯ à®…à®´à¯à®¤à®¾à®²à¯à®®à¯ à®¯à®¾à®°à¯ à®®à¯€à®¤à¯ à®¤à®µà®±à®¾à®•à¯à®®à¯ ?? S.L\r\n', 100, 'S.Linga & ?????', 'uploads/691c5468a5c7c.jpg', 'Dating Tips', 'published', '2025-11-18', '2025-11-18 11:11:36', '2025-11-26 08:56:18');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `branch_name` varchar(100) NOT NULL,
  `branch_address` varchar(255) NOT NULL,
  `branch_phone` varchar(20) DEFAULT NULL,
  `status` enum('active','deactivate') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `branch_name`, `branch_address`, `branch_phone`, `status`) VALUES
(1, 'Vavuniya', 'kondavil', '02112005', 'deactivate'),
(2, 'Jafna', 'kondavil', '021120051511', 'active'),
(3, 'colombo', 'kondavil', '021120051511', 'active'),
(4, 'Kandy', 'kondavil', '021120051511', 'deactivate');

-- --------------------------------------------------------

--
-- Table structure for table `calls`
--

CREATE TABLE `calls` (
  `call_id` int(10) UNSIGNED NOT NULL,
  `caller_id` int(10) UNSIGNED DEFAULT NULL,
  `member_id` int(10) UNSIGNED DEFAULT NULL,
  `member_name` varchar(255) DEFAULT NULL,
  `call_phone` varchar(50) NOT NULL,
  `call_date` datetime NOT NULL DEFAULT current_timestamp(),
  `duration` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('Scheduled','Completed','Missed') NOT NULL DEFAULT 'Scheduled',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `calls`
--

INSERT INTO `calls` (`call_id`, `caller_id`, `member_id`, `member_name`, `call_phone`, `call_date`, `duration`, `status`, `notes`) VALUES
(1, NULL, NULL, 'lingajan', '0755350101', '2025-09-26 13:33:00', 10, 'Completed', 'zcbhfgvbn'),
(2, NULL, NULL, 'lingajan', '12345678', '2025-09-26 13:33:00', 20, 'Completed', 'sdfgbgxfcb'),
(3, NULL, NULL, 'hmvhjk', '545465', '2025-09-26 16:38:00', 10, 'Completed', 'xfdyhj'),
(4, NULL, NULL, 'Nathan', '0771727687', '2025-10-29 14:54:00', 25, 'Completed', 'Hello World'),
(5, NULL, NULL, 'Nathan', '0771727687', '2025-10-30 14:56:00', 20, 'Completed', 'Hi'),
(6, NULL, NULL, '345678', '7666', '2025-12-04 18:14:00', 30, 'Completed', 'fjgkjg'),
(7, NULL, NULL, 'fgitg', '+94 789567040', '2025-12-11 03:38:00', 23, 'Completed', 'jgrg');

-- --------------------------------------------------------

--
-- Table structure for table `company_details`
--

CREATE TABLE `company_details` (
  `id` int(11) NOT NULL,
  `mobile_number` varchar(20) DEFAULT NULL,
  `land_number` varchar(20) DEFAULT NULL,
  `whatsapp_number` varchar(20) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `branch` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `company_details`
--

INSERT INTO `company_details` (`id`, `mobile_number`, `land_number`, `whatsapp_number`, `location`, `email`, `bank_name`, `account_number`, `account_name`, `branch`, `created_at`, `updated_at`) VALUES
(2, '0755960565', '1234567890', '+94743094716', 'jaffna', 'nagarasha.vithusan@gmail.com', '240', '4.523', '1.20', '21.', '2025-12-11 07:14:48', '2025-12-17 10:05:34');

-- --------------------------------------------------------

--
-- Table structure for table `education`
--

CREATE TABLE `education` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `level` enum('O/L','A/L','Higher') DEFAULT NULL,
  `school_or_institute` varchar(100) DEFAULT NULL,
  `stream_or_degree` varchar(100) DEFAULT NULL,
  `field` varchar(100) DEFAULT NULL,
  `reg_number` varchar(50) DEFAULT NULL,
  `start_year` int(11) DEFAULT NULL,
  `end_year` int(11) DEFAULT NULL,
  `result` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `education`
--

INSERT INTO `education` (`id`, `member_id`, `level`, `school_or_institute`, `stream_or_degree`, `field`, `reg_number`, `start_year`, `end_year`, `result`) VALUES
(63, 4, 'Higher', 'University of Colombo', 'MBBS', 'Medicine', 'UNI00112', 2016, 2022, NULL),
(64, 4, 'Higher', 'Ananda College', 'Science', 'Physics', 'AL99001', 2014, 2016, NULL),
(65, 4, 'Higher', 'Ananda College', 'Science', 'Mathematics', 'OL88990', 2009, 2014, NULL),
(75, 8, 'Higher', 'University of Peradeniya', 'BEd', 'Teaching', 'UNI22334', 2015, 2018, NULL),
(76, 8, 'Higher', 'Mahinda College', 'Arts', 'Education', 'AL11223', 2013, 2015, NULL),
(77, 8, 'Higher', 'Mahinda College', 'Arts', 'Sinhala', 'OL00112', 2008, 2013, NULL),
(78, 10, 'Higher', 'University of Jaffna', 'MBA', 'Business Administration', 'UNI88990', 2013, 2015, NULL),
(79, 10, 'Higher', 'St. Joseph College', 'Commerce', 'Business Studies', 'AL77889', 2011, 2013, NULL),
(80, 10, 'Higher', 'St. Joseph College', 'Commerce', 'Accounting', 'OL66778', 2006, 2011, NULL),
(141, 9, 'Higher', 'University of Colombo', 'BSc', 'Information Technology', 'UNI55667', 2014, 2017, NULL),
(142, 9, 'Higher', 'Royal College, Colombo', 'Science', 'IT', 'AL44556', 2012, 2014, NULL),
(143, 9, 'Higher', 'Royal College, Colombo', 'Science', 'Mathematics', 'OL33445', 2007, 2012, NULL),
(150, 5, 'Higher', 'University of Moratuwa', 'BE', 'Civil Engineering', 'UNI33445', 2015, 2019, NULL),
(151, 5, 'Higher', 'Royal College, Colombo', 'Commerce', 'Business Studies', 'AL22334', 2013, 2015, NULL),
(152, 5, 'Higher', 'Royal College, Colombo', 'Arts', 'English', 'OL11223', 2008, 2013, NULL),
(153, 6, 'Higher', 'University of Colombo', 'LLB', 'Law', 'UNI66778', 2014, 2018, NULL),
(154, 6, 'Higher', 'St. Peter College', 'Arts', 'Law', 'AL55667', 2012, 2014, NULL),
(155, 6, 'Higher', 'St. Peter College', 'Science', 'Biology', 'OL44556', 2007, 2012, NULL),
(167, 2, 'Higher', 'University of Jaffna', 'BA', 'Education', 'UNI44556', 2014, 2017, NULL),
(168, 2, 'Higher', 'Jaffna Central College', 'Commerce', 'Accounting', 'AL33445', 2012, 2014, NULL),
(169, 2, 'Higher', 'Jaffna Central College', 'Arts', 'English', 'OL22334', 2007, 2012, NULL),
(171, 51, 'Higher', 'SLIIT', 'BSc', 'FOC', 'UNI11223', 2020, 2025, NULL),
(177, 53, 'Higher', 'SLIIT', 'Diploma', 'FOC', 'IT23440733', 2022, 2026, NULL),
(187, 65, 'Higher', '', '', '', '', 0, 0, NULL),
(188, 66, 'Higher', '', '', '', '', 0, 0, NULL),
(189, 66, 'Higher', 'asdasd', 'asdsad', '', '', 0, 0, NULL),
(202, 68, 'Higher', 'University of Colombo', 'BSc', 'FOC', 'UNI11223', 2020, 2025, NULL),
(204, 1, 'Higher', 'University of Colombo', 'BSc', 'Computer Science', 'UNI11223', 2015, 2018, NULL),
(205, 1, 'Higher', 'Royal College, Colombo', 'Science', 'Physics', 'AL54321', 2013, 2015, NULL),
(206, 1, 'Higher', 'Royal College, Colombo', 'Science', 'Mathematics', 'OL12345', 2008, 2013, NULL),
(209, 67, 'Higher', 'Jad', 'Diploma', 'FOC', 'IT23440722', 2012, 2013, NULL),
(211, 59, 'Higher', 'SLIIT', 'Diploma', 'FOC', 'IT23440722', 2002, 2006, NULL),
(215, 70, 'Higher', '', '', '', '', 0, 0, NULL),
(216, 69, 'Higher', '10', '10', '10', '10', 10, 10, NULL),
(217, 7, 'Higher', 'University of Jaffna', 'BCom', 'Finance', 'UNI99001', 2013, 2016, NULL),
(218, 7, 'Higher', 'Jaffna Hindu College', 'Commerce', 'Economics', 'AL88990', 2011, 2013, NULL),
(219, 7, 'Higher', 'Jaffna Hindu College', 'Commerce', 'Accounting', 'OL77889', 2006, 2011, NULL),
(220, 26, 'Higher', 'Software', 'Software engineer ', 'It', '56788', 1998, 1997, NULL),
(221, 3, 'Higher', 'University of Kelaniya', 'BBA', 'Management', 'UNI77889', 2013, 2016, NULL),
(222, 3, 'Higher', 'St. Joseph College', 'Science', 'Chemistry', 'AL66778', 2011, 2013, NULL),
(223, 3, 'Higher', 'St. Joseph College', 'Science', 'Biology', 'OL55667', 2006, 2011, NULL),
(224, 71, 'Higher', '', 'Bachelor of Science in Informatik', '', '', 0, 0, NULL),
(228, 62, 'Higher', 'Sliit', 'it', 'FOC', 'IT23440722', 2020, 2025, NULL),
(232, 52, 'Higher', 'SLIIT', 'Diploma', 'FOC', '', 2022, 2026, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `family`
--

CREATE TABLE `family` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `father_profession` varchar(100) DEFAULT NULL,
  `father_contact` varchar(20) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `mother_profession` varchar(100) DEFAULT NULL,
  `mother_contact` varchar(20) DEFAULT NULL,
  `brothers_count` int(11) DEFAULT NULL,
  `sisters_count` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `family`
--

INSERT INTO `family` (`id`, `member_id`, `father_name`, `father_profession`, `father_contact`, `mother_name`, `mother_profession`, `mother_contact`, `brothers_count`, `sisters_count`) VALUES
(1, 1, 'Suresh Kumar', 'Engineer', '0771234567', 'Anita Kumar', 'Teacher', '0777654321', 1, 1),
(2, 2, 'Rajiv Nadar', 'Doctor', '0772345678', 'Lakshmi Nadar', 'Homemaker', '0778765432', 2, 0),
(3, 3, 'Gopal Singh', 'Businessman', '0773456789', 'Sunita Singh', 'Teacher', '0779876543', 0, 2),
(4, 4, 'Dinesh Perera', 'Banker', '0774567890', 'Chathurika Perera', 'Nurse', '0770987654', 1, 2),
(5, 5, 'Mahesh Fernando', 'Lawyer', '0775678901', 'Anjali Fernando', 'Accountant', '0771098765', 2, 1),
(6, 6, 'Ramesh Jayasinghe', 'Professor', '0776789012', 'Nalini Jayasinghe', 'Homemaker', '0772109876', 1, 0),
(7, 7, 'Kumaravel Rajan', 'Engineer', '0777890123', 'Priya Rajan', 'Teacher', '0773210987', 0, 1),
(8, 8, 'Sathish Kumar', 'Doctor', '0778901234', 'Meena Kumar', 'Lawyer', '0774321098', 1, 1),
(9, 9, 'Vijay Perera', 'Businessman', '0779012345', 'Nirosha Perera', 'Teacher', '0775432109', 3, 0),
(10, 10, 'Arjun Silva', 'Engineer', '0770123456', 'Kavitha Silva', 'Nurse', '0776543210', 2, 2),
(15, 26, 'Ulaganathan', 'Business', '09998', 'Chelvi', 'House wife', '9988', 0, 1),
(30, 51, 'Saman', 'Engineer', '741255555', 'Sivakami', 'Housewife', '77523645', 2, 3),
(31, 52, 'Uthaman', 'Actor', '0773737373', 'Devi', 'House wife', '0339393933', 2, 1),
(32, 53, 'Kavin', 'Actor', '0771772722', 'Ramya', 'House wife', '0771717171', 3, 1),
(33, 54, 'Hello', 'Teacher', '76433223', 'Sivakami', 'House wife', '76544332', 2, 3),
(34, 59, 'qgerw', 'agfdsf', 'sdaf', 'Sivakami', 'House wife', '0771717171', 2, 1),
(35, 65, 'Siva', 'sivaa', 'sivaaa', 'sivi', 'sivii', 'siviii', 3, 3),
(36, 66, '234', 'agfdsf', 'sdaf', 'Sivakami', 'House wife', '0771717171', 3, 3),
(37, 67, 'Siva', 'Actor', '0771717171', 'Sivakami', 'House wife', '0771717172', 2, 1),
(38, 68, 'Suresh Kumar', 'sdfhvgh', '712345678', 'Anita Kumar', 'Teacher', '415940914', 20, 20),
(39, 69, '10', '10', '10', '10', '10', '10', 1, 2),
(40, 58, '', '', '', '', '', '', 0, 0),
(41, 62, 'Suresh Kumar', 'Engineer', '0771234567', 'Nalini Jayasinghe', 'Homemaker', 'sdfghfgh', 2, 2),
(42, 70, 'hhh', 'asd', '077172738', 'sdf', 'sdfsdf', '077171717', 2, 2),
(43, 71, 'à®¨à®¨à¯à®¤à®©à¯ à®‡à®°à®¤à¯à®¤à®¿à®©à®šà®ªà®¾à®ªà®¤à®¿', 'Business ', '+41 79 284 49 39', 'à®šà®šà®¿à®•à®²à®¾ à®®à¯à®°à¯à®•à¯‡à®šà¯', 'Home Work ', '+41 79 284 49 39', 2, 1),
(44, 63, '', '', '', '', '', '', 0, 0),
(45, 72, '', '', '', '', '', '', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `horoscope`
--

CREATE TABLE `horoscope` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `birth_time` time DEFAULT NULL,
  `zodiac` varchar(20) DEFAULT NULL,
  `nakshatra` varchar(20) DEFAULT NULL,
  `karmic_debt` varchar(50) DEFAULT NULL,
  `planet_image` varchar(255) DEFAULT NULL,
  `navamsha_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `horoscope`
--

INSERT INTO `horoscope` (`id`, `member_id`, `birth_date`, `birth_time`, `zodiac`, `nakshatra`, `karmic_debt`, `planet_image`, `navamsha_image`) VALUES
(1, 1, '1990-05-12', '06:45:00', 'Taurus', '1043', 'Past life financial debts', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg', 'https://thennilavu.lk/uploads/1764243382_nav_a6b1543d3a7e58543151ffd18c904f51.jpg'),
(2, 2, '1992-11-23', '14:30:00', 'Sagittarius', '1022', 'Relationship challenges', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg'),
(3, 3, '1988-08-15', '09:15:00', 'Leo', '1041', 'Health obstacles', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg'),
(4, 4, '1995-02-10', '20:05:00', 'Aquarius', '1001', 'Career delays', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg'),
(5, 5, '1991-07-28', '11:20:00', 'Cancer', '1019', 'Family responsibility', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg'),
(6, 6, '1993-01-19', '05:50:00', 'Capricorn', '1024', 'Past life learning', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg'),
(7, 7, '1994-09-02', '16:40:00', 'Virgo', '1026', 'Financial instability', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg'),
(8, 8, '1990-12-11', '08:10:00', 'Sagittarius', '1004', 'Career challenges', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg'),
(9, 9, '1989-04-05', '18:25:00', 'Aries', '1023', 'Health risks', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg'),
(10, 10, '1996-06-18', '07:35:00', 'Gemini', '1034', 'Relationship learning', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg', 'https://thennilavu.lk/uploads/1764243382_planet_a6b1543d3a7e58543151ffd18c904f51.jpg'),
(30, 51, '0000-00-00', '00:00:00', '', '1023', '', 'https://thennilavu.lk/uploads/1764243358_planet_a6b1543d3a7e58543151ffd18c904f51.jpg', 'https://thennilavu.lk/uploads/1764243358_nav_a6b1543d3a7e58543151ffd18c904f51.jpg'),
(31, 52, '2025-11-01', '17:24:00', '213', '1001', '123', 'uploads/1766749363_planet_apple2.webp', 'uploads/1766749363_navamsha_WholeWheatBread3.jfif'),
(32, 53, '2000-10-06', '10:00:00', '213', '1005', '234', 'uploads/1764246703_projectCharterPack.webp', 'uploads/1764246703_projectCharterPack.webp'),
(33, 54, '1999-10-09', '10:00:00', '234', '1014', '234', 'https://thennilavu.lk/uploads/1764248380_planet_Blue_Yellow_Simple_Empathy_Map_Brainstorm.png', 'https://thennilavu.lk/uploads/1764248380_nav_ca52f60e-9f96-4f9c-8c01-7a3f90344d90.jpg'),
(34, 59, '2025-12-03', '00:00:00', '213', '1001', '12', 'uploads/1764670385_1764044849_contact.png', 'uploads/1764670385_1764044849_contact.png'),
(35, 65, '2025-12-04', '22:24:00', '213', '1001', '234', 'uploads/1764694441_FireShot Capture 016 - WhatsApp - [web.whatsapp.com].png', 'uploads/1764694441_FireShot Capture 017 - Invalid Tamil Nadu PIN - [chatgpt.com].png'),
(36, 66, '2002-12-01', '22:42:00', '213', '1001', '234', 'uploads/1764695460_img5.png', 'uploads/1764695460_img4.png'),
(37, 67, '2002-10-10', '23:46:00', 'Singham', '1025', '12', 'uploads/1764699404_bd66c-veeram2b03_memekadai.blogspot.com_.jpg', 'uploads/1764699404_GAq3XOuWwAAJfoT.jpg'),
(38, 68, '2025-12-10', '10:21:00', 'Leo', '1014', 'Health obstacles', 'https://thennilavu.lk/uploads/1764823942_planet_a6b1543d3a7e58543151ffd18c904f51.jpg', 'https://thennilavu.lk/uploads/1764823942_nav_a6b1543d3a7e58543151ffd18c904f51.jpg'),
(39, 69, '2026-01-08', '22:00:00', '10', '1001', '10', '', ''),
(40, 58, '0000-00-00', '00:00:00', '', '', '', '', ''),
(41, 62, '2025-12-19', '00:00:00', 'Leo', 'Rohini', 'Yes', 'uploads/1766127531_planet_a6b1543d3a7e58543151ffd18c904f51.jpg', 'uploads/1766127531_navamsha_Baby_Shower_Ceremony.webp'),
(42, 70, '0000-00-00', '00:00:00', '', '1001', '', '', ''),
(43, 26, '0000-00-00', '00:00:00', '', '', '', '', ''),
(44, 71, '1994-05-23', '03:16:00', 'à®¤à¯à®²à®¾à®®à¯', '1031', '', 'uploads/1765182315_WhatsApp Image 2025-11-18 at 9.39.57 PM(2).jpeg', 'uploads/1765182315_WhatsApp Image 2025-11-18 at 9.39.57 PM(2).jpeg'),
(45, 63, '0000-00-00', '00:00:00', '', '', '', '', ''),
(46, 72, '0000-00-00', '00:00:00', '', '', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `looking_for` enum('Male','Female','Other') NOT NULL,
  `dob` date NOT NULL,
  `religion` varchar(50) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `marital_status` enum('Single','Married','Divorced','Widowed','Deactivated') NOT NULL,
  `language` varchar(50) NOT NULL,
  `profession` varchar(100) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `smoking` enum('No','Yes','Occasionally') DEFAULT NULL,
  `drinking` enum('No','Yes','Occasionally') DEFAULT NULL,
  `present_address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `zip` varchar(20) DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `permanent_city` varchar(50) DEFAULT NULL,
  `package` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `income` float DEFAULT NULL,
  `profile_hidden` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `name`, `photo`, `looking_for`, `dob`, `religion`, `gender`, `marital_status`, `language`, `profession`, `country`, `phone`, `smoking`, `drinking`, `present_address`, `city`, `branch_id`, `zip`, `permanent_address`, `permanent_city`, `package`, `created_at`, `user_id`, `income`, `profile_hidden`) VALUES
(1, 'Sneka', 'uploads/1764826272_363ad30a36deed40f28ac8e69b339a06.jpg', 'Male', '1995-06-15', 'Hindu', 'Female', 'Single', 'tamil', 'Doctor', 'Sri Lanka', '71 234 5698', 'No', 'No', '', '', NULL, '', '', '', 'Gold', '2025-11-27 04:35:44', 1, NULL, 0),
(2, 'Priya Nadar', 'uploads/1764221701_363ad30a36deed40f28ac8e69b339a06.jpg', 'Male', '1994-08-22', 'Hindu', 'Female', 'Single', '', 'Teacher', 'Sri Lanka', '78 765 4321', '', '', '', '', NULL, '', '', '', 'Silver', '2025-11-27 04:35:44', 2, NULL, 0),
(3, 'Saran Kumar', 'uploads/1764225269_smart-boy-picture-7279162_1280.jpg', 'Female', '2000-06-07', 'Hindu', 'Male', 'Divorced', 'tamil', 'Business', 'Sri Lanka', '71 122 3344', 'Yes', 'Occasionally', '', '', NULL, '', '', '', 'Gold', '2025-11-27 04:35:44', 3, NULL, 0),
(4, 'Nisha Ram', 'uploads/1764221752_images.jpeg', 'Male', '1996-12-05', 'Hindu', 'Female', 'Single', '', 'Doctor', 'Sri Lanka', '79 988 7766', '', '', '', '', NULL, '', '', '', 'Premium', '2025-11-27 04:35:44', 4, NULL, 0),
(5, 'Arjun ', 'uploads/1764221787_img5.jpg', 'Male', '1992-03-14', 'Hindu', 'Female', 'Single', 'tamil', 'Engineer', 'Sri Lanka', '75 566 7788', '', '', '', '', NULL, '', '', '', 'Silver', '2025-11-27 04:35:44', 5, NULL, 0),
(6, 'Mala Devi', 'uploads/1764221903_images__2_.jpeg', 'Male', '1999-02-21', 'Hindu', 'Female', 'Divorced', 'tamil', 'Lawyer', 'Sri Lanka', '76 677 8899', 'No', 'Occasionally', '', '', NULL, '', '', '', 'Gold', '2025-11-27 04:35:44', 6, NULL, 0),
(7, 'Daniel Raji', 'uploads/1764221933_images__3_.jpeg', 'Female', '2000-06-30', 'Hindu', 'Male', 'Divorced', 'english', 'Accountant', 'Sri Lanka', '74 455 6677', 'Yes', 'Occasionally', '', '', NULL, '', '', '', 'Silver', '2025-11-27 04:35:44', 7, NULL, 0),
(8, 'Meena Kavi', 'uploads/1764221961_images__3_.jpeg', 'Male', '1995-01-17', 'Hindu', 'Female', 'Single', 'tamil', 'Teacher', 'Sri Lanka', '73 344 5566', '', '', '', '', NULL, '', '', '', 'Premium', '2025-11-27 04:35:44', 8, NULL, 0),
(9, 'Vithu Kumar', 'uploads/1764225214_man-7796384_1280.jpg', 'Female', '1993-05-25', 'Hindu', 'Male', 'Single', 'tamil', 'Software Engineer', 'Sri Lanka', '72 233 4455', '', '', '', '', NULL, '', '', '', 'Gold', '2025-11-27 04:35:44', 9, NULL, 0),
(10, 'Kavi Priya', 'uploads/1764222001_images.jpeg', 'Male', '1994-09-19', 'Hindu', 'Female', 'Single', 'tamil', 'Business', 'Sri Lanka', '71 122 4433', '', '', '', '', NULL, '', '', '', 'Silver', '2025-11-27 04:35:44', 10, NULL, 0),
(26, 'Ulaganathan chartheepan', 'uploads/1762833511_1000090971.jpg', 'Female', '2000-02-25', 'Hindu', 'Male', 'Divorced', 'tamil', 'Business', 'Sri Lanka', '76 998 8123', 'Yes', 'Occasionally', '', '', NULL, '', '', '', 'free', '2025-11-11 03:58:31', 24, 500000, 0),
(51, 'lingajan', 'uploads/1764225169_man-7796384_1280.jpg', 'Female', '2001-07-11', 'Hindu', 'Male', 'Single', 'tamil', 'Doctor', 'Sri Lanka', '71 234 5623', 'No', 'Occasionally', '', '', NULL, '', '', '', NULL, '2025-11-27 06:14:02', NULL, NULL, 0),
(52, 'Kamalhasan', 'uploads/1766749206_whitebread3.jfif', 'Male', '2002-10-06', 'Hindu', 'Female', 'Single', 'Tamil', 'Software Engineering', 'Sri Lanka', '0771727586', 'No', 'No', '', '', NULL, '', '', '', NULL, '2025-11-27 11:49:35', 12, 50000, 0),
(53, 'Jeeva', 'uploads/1764246523_GAq3XOuWwAAJfoT.jpg', 'Female', '1990-02-09', 'Hindu', 'Male', 'Single', 'Tamil', 'Software Engineering', 'Sri Lanka', '772727272', 'No', 'No', '', '', NULL, '', '', '', NULL, '2025-11-27 12:28:43', 32, 27000, 0),
(54, 'Palani', 'uploads/1764248380_images.jfif', 'Female', '1999-10-09', 'Hindu', 'Male', 'Single', 'english', 'Software Engineering', 'Sri Lanka', '0771727374', 'No', 'Yes', '', '', 1, '', '', '', NULL, '2025-11-27 12:59:40', NULL, NULL, 0),
(55, 'Subashini Vishnuvarthan', 'uploads/1764663723_WhatsApp Image 2025-11-18 at 9.05.26 PM.jpeg', 'Male', '1982-07-29', 'Hindu', 'Female', 'Divorced', 'Tamil', 'Chef', 'Colombo', '78 846 18 82', 'No', 'No', '', '', NULL, '', '', '', NULL, '2025-12-02 08:22:03', 33, 50000, 0),
(56, 'Subashini Vishnuvarthan', 'uploads/1764663724_WhatsApp Image 2025-11-18 at 9.05.26 PM.jpeg', 'Male', '1982-07-29', 'Hindu', 'Female', 'Divorced', 'Tamil', 'Chef', 'Colombo', '78 846 18 82', 'No', 'No', '', '', NULL, '', '', '', NULL, '2025-12-02 08:22:04', 33, 50000, 0),
(57, 'Subashini Vishnuvarthan', 'uploads/1764663726_WhatsApp Image 2025-11-18 at 9.05.26 PM.jpeg', 'Male', '1982-07-29', 'Hindu', 'Female', 'Divorced', 'Tamil', 'Chef', 'Colombo', '78 846 18 82', 'No', 'No', '', '', NULL, '', '', '', NULL, '2025-12-02 08:22:06', 33, 50000, 0),
(58, 'Kamalanathan Thananchayan', 'uploads/1764670264_1764044849_contact.png', 'Male', '2025-12-03', 'Hindu', 'Male', 'Single', 'tamil', 'Software Engineering', 'Sri Lanka', '0740536517', 'No', 'No', '', '', NULL, '', '', '', NULL, '2025-12-02 10:11:04', 2, 50000, 0),
(59, 'Sneka', 'uploads/1764826272_363ad30a36deed40f28ac8e69b339a06.jpg', 'Male', '1995-06-15', 'Hindu', 'Female', 'Single', 'tamil', 'Doctor', 'Sri Lanka', '71 234 5698', 'No', 'No', '', '', NULL, '', '', '', NULL, '2025-12-02 10:11:05', 1, 50000, 0),
(60, 'Kamalanathan Thananchayan', 'uploads/1764671950_img4.png', 'Male', '2002-10-06', 'Hindu', 'Male', 'Single', 'Tamil', 'Software Engineering', 'Sri Lanka', '0740536517', 'No', 'No', '', '', NULL, '', '', '', NULL, '2025-12-02 10:39:10', 36, 12000, 0),
(61, 'Kamalanathan Thananchayan', 'uploads/1764671951_img4.png', 'Male', '2002-10-06', 'Hindu', 'Male', 'Single', 'Tamil', 'Software Engineering', 'Sri Lanka', '0740536517', 'No', 'No', '', '', NULL, '', '', '', NULL, '2025-12-02 10:39:11', 36, 12000, 0),
(62, 'lingajan', 'uploads/1766743946_car1.jpeg', 'Male', '2025-12-23', 'Hindu', 'Male', 'Divorced', 'tamil', 'Teacher', 'Sri Lanka', '0755350101', 'No', 'No', '', '', NULL, '', '', '', NULL, '2025-12-02 11:05:31', 35, 5000, 0),
(63, 'Jogi Babu', 'uploads/1764688862_img113.png', 'Male', '2002-10-06', 'Hindu', 'Male', 'Married', 'tamil', 'Software Engineering', 'Sri Lanka', '0740536517', 'No', 'No', '', '', NULL, '', '', '', NULL, '2025-12-02 15:21:02', 28, 3000, 0),
(64, 'Kamalanathan Thananchayan', 'uploads/1764692851_FireShot Capture 014 - DeepSeek - Into the Unknown - [chat.deepseek.com].png', 'Male', '2002-10-10', 'Hindu', 'Male', 'Single', 'Tamil', 'Software Engineering', 'Sri Lanka', '0740536517', 'No', 'No', '', '', 2, '', '', '', NULL, '2025-12-02 16:27:31', 33, 50000, 0),
(65, 'Siruthai', 'uploads/1764694223_FireShot Capture 017 - Invalid Tamil Nadu PIN - [chatgpt.com].png', 'Male', '2002-10-06', 'Hindu', 'Male', 'Single', 'Tamil', 'Software Engineering', 'Sri Lanka', '0740536517', 'Yes', 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-02 16:50:23', 30, 27000, 0),
(66, 'Megna', 'uploads/1764695341_img1.png', 'Male', '2002-10-10', 'Hindu', 'Male', 'Single', 'English', 'Software Engineering', 'Sri Lanka', '0740536517', 'No', 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-02 17:09:01', 31, 50000, 0),
(67, 'Sathish', 'uploads/1764699224_8f2e99ff-1e40-4ad2-b4e4-b9c01200c8cf.jpg', 'Female', '2002-10-06', 'Hindu', 'Male', 'Single', 'tamil', 'Software Engineer', 'Jaffna, Srilanka', '0740536517', 'No', 'No', '', '', NULL, '', '', '', NULL, '2025-12-02 18:13:44', 37, 50000, 0),
(68, 'lingajan', 'uploads/1764823942_750x450_811934-untitled1.webp', 'Male', '2025-12-10', 'Christian', 'Female', 'Single', 'tamil', 'Teacher', 'Sri Lanka', '0755350101', 'No', 'No', '', '', 2, '', '', '', NULL, '2025-12-04 04:52:22', NULL, NULL, 1),
(69, 'lingajan', 'uploads/1764824976_1234.jpg', 'Female', '2000-01-21', 'Hindu', 'Male', 'Divorced', 'english', 'Business', 'Sri Lanka', '75 535 0101', 'Yes', 'Occasionally', '', '', NULL, '', '', '', NULL, '2025-12-04 05:09:36', NULL, NULL, 0),
(70, 'Thanu', 'uploads/1764843931_Screenshot (178).png', 'Female', '2002-10-06', 'Hindu', 'Male', 'Single', 'Tamil', 'Vivasayam', 'Sri lanka', '0771727687', 'No', 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-04 10:25:31', 39, 20000, 0),
(71, 'à®µà®¿à®ªà¯‚à®šà®©à¯ à®¨à®¨à¯à®¤à®©à¯', 'uploads/1765180114_WhatsApp Image 2025-11-18 at 9.39.57 PM.jpeg', 'Female', '1994-05-23', 'Hindu', 'Male', 'Single', 'Tamil', 'Sorfware Entwickler', 'Bern', '79 284 49 39', 'No', 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-08 07:48:34', 41, 50000, 0),
(72, 'lingajan', 'uploads/1766743946_car1.jpeg', 'Male', '2025-12-23', 'Hindu', 'Male', 'Divorced', 'tamil', 'Teacher', 'Sri Lanka', '0755350101', 'No', 'No', '', '', NULL, '', '', '', NULL, '2025-12-24 05:04:37', 35, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(10) UNSIGNED NOT NULL,
  `sender_name` varchar(120) NOT NULL,
  `sender_email` varchar(190) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message_text` text NOT NULL,
  `sent_time` datetime NOT NULL DEFAULT current_timestamp(),
  `message_type` enum('user','admin') NOT NULL DEFAULT 'user',
  `reply_text` text DEFAULT NULL,
  `reply_sent_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_name`, `sender_email`, `subject`, `message_text`, `sent_time`, `message_type`, `reply_text`, `reply_sent_time`) VALUES
(1, 'Admin', 'admin@matrimony.com', 'Medical', 'bsdjgvopmnfgviong', '2025-09-26 13:59:41', 'user', 'nfhyjmhm', '2025-09-26 14:01:32'),
(3, 'Admin', 'admin@matrimony.com', 'Medical', 'bsdjgvopmnfgviong', '2025-09-26 14:01:16', 'user', 'giuhoibgvibijhvk', '2025-09-26 14:07:34'),
(5, 'Admin', 'admin@matrimony.com', 'vc bm', 'ghmkghk', '2025-09-26 14:07:20', 'admin', NULL, NULL),
(7, 'Hero', 'thananchayan@gmail.com', 'abjsbkjkjbsdkjnsdkjdjk swdjkbwndjwe', 'wqreeeeeeeeeeeeeeeeeee', '2025-09-26 16:38:57', 'admin', NULL, NULL),
(8, 'Nathan', 'kamalanathanthananchayan04@gmail.com', 'Hi Everyone', 'From (Sender Name)\r\nNathan\r\nFrom (Sender Email)\r\nkamalanathanthananchayan04@gmail.com\r\nTo (Recipient Email)\r\nkamalanathanthananchayan@gmail.com\r\nPriority\r\n\r\nHigh\r\nSubject', '2025-10-28 14:47:45', 'admin', NULL, NULL),
(9, 'Admin', 'nallathamilan01@gmail.com', 'uio', 'uk,', '2025-11-26 04:16:50', 'admin', NULL, NULL),
(10, 'SL@GMAIL.COM', 'nallathamilan01@gmail.com', 'Medical', 'h', '2025-11-26 04:17:33', 'admin', NULL, NULL),
(11, 'SL@GMAIL.COM', 'nallathamilan01@gmail.com', 'Medical', 'h', '2025-11-26 04:54:34', 'admin', NULL, NULL),
(12, 'SL@GMAIL.COM', 'nallathamilan01@gmail.com', 'Medical', 'h', '2025-11-26 04:54:39', 'admin', NULL, NULL),
(13, 'Admin', 'nallathamilan01@gmail.com', 'Medical', 'njib nk', '2025-11-26 04:55:02', 'admin', NULL, NULL),
(14, 'Admin123', 'nallathamilan01@gmail.com', 'nijn', 'i9jhj', '2025-11-26 04:55:28', 'admin', NULL, NULL),
(15, 'Admin', 'nallathamilan01@gmail.com', 'XFG', 'bvhu', '2025-11-26 05:13:49', 'admin', NULL, NULL),
(16, 'Admin', 'SL@GMAIL.COM', 'Medical', 'cxytfvhubvu', '2025-11-26 05:15:47', 'admin', NULL, NULL),
(17, 'Kamalanathan Thananchayan', 'kamalanathanthananchayan04@gmail.com', 'Low Balance Alert - Saki Home', 'asdfghjk', '2025-11-26 05:17:32', 'user', 'vfgviugvbiugi', '2025-11-26 05:17:51'),
(18, 'Admin', 'SL@GMAIL.COM', 'Medical', 'cxytfvhubvu', '2025-11-26 05:17:36', 'admin', NULL, NULL),
(19, 'Admin', 'nallathamilan01@gmail.com', 'rwyujh', 'asdf', '2025-11-26 05:21:58', 'admin', NULL, NULL),
(20, 'Admin', 'nallathamilan01@gmail.com', 'rwyujh', 'asdf', '2025-11-26 05:23:19', 'admin', NULL, NULL),
(21, 'Kajan', 'nallathamilan@gmail.com', 'Jajjaja', 'Jajajja', '2025-12-01 08:56:42', 'user', NULL, NULL),
(22, 'Kajan', 'nallathamilan@gmail.com', 'Jajjaja', 'Jajajja', '2025-12-01 08:57:16', 'user', NULL, NULL),
(23, 'S. Linga', 'senthillinga06@gmail.com', 'Ty', 'Hjj', '2025-12-01 08:57:46', 'user', NULL, NULL),
(24, 'S. Linga', 'senthillinga06@gmail.com', 'Ty', 'Hjj', '2025-12-01 09:09:57', 'user', 'rgr', '2025-12-04 05:08:55'),
(25, 'S. Linga', 'senthillinga06@gmail.com', 'Ty', 'Hjj', '2025-12-01 09:10:47', 'user', 'jnbskrlgmlsr', '2025-12-04 04:37:33');

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `package_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `status` enum('active','inactive','draft') DEFAULT 'active',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_views_limit` varchar(50) DEFAULT NULL,
  `interest_limit` varchar(50) DEFAULT NULL,
  `search_access` enum('Limited','Unlimited') DEFAULT 'Limited',
  `profile_view_enabled` enum('Yes','No') DEFAULT 'No',
  `profile_hide_enabled` enum('Yes','No') DEFAULT 'No',
  `matchmaker_enabled` enum('Yes','No') DEFAULT 'No'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`package_id`, `name`, `price`, `duration_days`, `status`, `description`, `created_at`, `profile_views_limit`, `interest_limit`, `search_access`, `profile_view_enabled`, `profile_hide_enabled`, `matchmaker_enabled`) VALUES
(10, 'Silver', 2000.00, 30, 'active', 'Hello World', '2025-11-18 09:07:23', '500', '10', 'Limited', 'Yes', 'Yes', 'Yes'),
(11, 'Brownze', 20000.00, 180, 'active', 'Hi everyone', '2025-11-18 09:10:44', '500', '30', 'Unlimited', 'Yes', 'Yes', 'Yes'),
(12, 'Premium', 5000.00, 30, 'active', 'Hi guys', '2025-11-18 09:14:51', '500', '36', 'Unlimited', 'Yes', 'Yes', 'Yes');

-- --------------------------------------------------------

--
-- Table structure for table `package_requests`
--

CREATE TABLE `package_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `status` enum('pending','approved') DEFAULT 'pending',
  `requested_at` datetime DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `partner_expectations`
--

CREATE TABLE `partner_expectations` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `preferred_country` varchar(50) DEFAULT NULL,
  `min_age` int(11) DEFAULT NULL,
  `max_age` int(11) DEFAULT NULL,
  `min_height` int(11) DEFAULT NULL,
  `max_height` int(11) DEFAULT NULL,
  `marital_status` enum('Never Married','Divorced','Widowed','Separated') DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `smoking` enum('Yes','No','Occasionally') DEFAULT NULL,
  `drinking` enum('Yes','No','Occasionally') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `partner_expectations`
--

INSERT INTO `partner_expectations` (`id`, `member_id`, `preferred_country`, `min_age`, `max_age`, `min_height`, `max_height`, `marital_status`, `religion`, `smoking`, `drinking`) VALUES
(1, 1, 'Sri Lanka', 20, 30, 160, 180, 'Divorced', 'hindu', 'Yes', 'Occasionally'),
(2, 2, 'India', 28, 35, 165, 185, '', 'hindu', 'No', 'No'),
(3, 3, 'Sri Lanka', 27, 33, 162, 178, 'Never Married', 'hindu', 'Occasionally', 'Occasionally'),
(4, 4, 'Canada', 26, 32, 158, 175, '', 'muslim', 'No', 'Occasionally'),
(5, 5, 'Australia', 24, 31, 160, 180, '', 'hindu', 'No', 'No'),
(6, 6, 'United States', 29, 36, 163, 182, 'Never Married', 'christian', 'Yes', 'Occasionally'),
(7, 7, 'Singapore', 25, 30, 160, 177, 'Never Married', 'hindu', 'No', 'Occasionally'),
(8, 8, 'New Zealand', 27, 34, 162, 180, '', 'muslim', 'Occasionally', 'Occasionally'),
(9, 9, 'India', 28, 32, 161, 179, '', 'hindu', 'No', 'No'),
(10, 10, 'Sri Lanka', 26, 33, 160, 181, '', 'christian', 'No', 'Occasionally'),
(28, 51, 'Sri Lanka', 20, 30, 160, 180, 'Never Married', 'hindu', 'Yes', 'Yes'),
(29, 26, '', 0, 0, 0, 0, NULL, '', '', ''),
(30, 52, 'Sri lanka', 23, 30, 160, 180, 'Never Married', 'Hindu', 'No', 'No'),
(31, 53, 'Sri lanka', 20, 26, 170, 189, 'Never Married', 'Hindu', 'Yes', 'Yes'),
(32, 54, 'Sri Lanka', 22, 33, 167, 177, 'Never Married', 'hindu', 'Yes', 'Yes'),
(33, 59, 'Srilanka', 24, 34, 120, 150, 'Separated', 'hindu', 'Yes', 'Yes'),
(34, 65, 'London', 24, 34, 160, 180, 'Never Married', 'Hindu', 'Yes', 'No'),
(35, 66, 'Srilanka', 34, 44, 110, 120, 'Never Married', 'Hindu', 'Yes', 'No'),
(36, 67, 'Sri lanka', 23, 33, 170, 180, 'Never Married', 'hindu', 'Yes', 'No'),
(37, 68, 'Sri Lanka', 20, 30, 120, 120, 'Never Married', 'hindu', 'Yes', 'Yes'),
(38, 69, 'Sri Lanka', 10, 1, 1, 1, 'Widowed', 'muslim', 'Yes', 'Yes'),
(39, 58, '', 0, 0, 0, 0, NULL, '', '', ''),
(40, 62, 'Sri Lanka', 20, 100, 100, 0, 'Never Married', 'Hindu', 'No', 'No'),
(41, 70, 'Sri lanka', 23, 33, 120, 140, 'Never Married', 'Hindu', 'Yes', 'Yes'),
(42, 71, 'switzerland', 22, 28, 145, 160, 'Never Married', 'Hindu', 'No', 'No'),
(43, 63, '', 0, 0, 0, 0, NULL, '', '', ''),
(44, 72, '', 0, 0, 0, 0, NULL, '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `physical_info`
--

CREATE TABLE `physical_info` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `complexion` varchar(20) DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `eye_color` varchar(30) DEFAULT NULL,
  `hair_color` varchar(30) DEFAULT NULL,
  `disability` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `physical_info`
--

INSERT INTO `physical_info` (`id`, `member_id`, `complexion`, `height_cm`, `weight_kg`, `blood_group`, `eye_color`, `hair_color`, `disability`, `created_at`) VALUES
(1, 1, 'Fair', 172.50, 68.00, 'O+', 'Brown', 'Black', 'No', '2025-11-27 04:47:02'),
(2, 2, 'Wheatish', 168.00, 70.50, 'A+', 'Black', 'Brown', 'None', '2025-11-27 04:47:02'),
(3, 3, 'Dusky', 175.00, 75.00, 'B+', 'Black', 'Black', 'None', '2025-11-27 04:47:02'),
(4, 4, 'Fair', 160.00, 55.00, 'AB+', 'Brown', 'Black', 'None', '2025-11-27 04:47:02'),
(5, 5, 'Wheatish', 165.50, 60.00, 'O-', 'Black', 'Brown', 'None', '2025-11-27 04:47:02'),
(6, 6, 'Dusky', 178.00, 80.00, 'A-', 'Brown', 'Black', 'None', '2025-11-27 04:47:02'),
(7, 7, 'Fair', 170.00, 65.00, 'B-', 'Black', 'Black', 'None', '2025-11-27 04:47:02'),
(8, 8, 'Wheatish', 167.50, 62.50, 'AB-', 'Brown', 'Brown', 'None', '2025-11-27 04:47:02'),
(9, 9, 'Dusky', 173.00, 70.00, 'O+', 'Black', 'Black', 'None', '2025-11-27 04:47:02'),
(10, 10, 'Fair', 169.00, 66.00, 'A+', 'Brown', 'Black', 'None', '2025-11-27 04:47:02'),
(39, 51, 'Dark', 170.00, 67.00, 'A+', 'Black', 'Black', 'None', '2025-11-27 06:14:02'),
(40, 26, '', 170.00, 0.00, NULL, '', '', '', '2025-11-27 10:30:02'),
(41, 52, 'Fair', 175.00, 56.00, 'A+', 'Brown', 'Black', 'No', '2025-11-27 11:50:01'),
(42, 53, 'Fair', 170.00, 78.00, 'A+', 'Brown', 'Black', 'No', '2025-11-27 12:29:02'),
(43, 54, 'Fair', 150.00, 67.00, 'A+', 'Brown', 'Black', 'No', '2025-11-27 12:59:40'),
(44, 57, 'Fair', 152.00, 57.00, 'A+', 'Brown', 'Black', 'No', '2025-12-02 08:28:05'),
(45, 59, 'Fair', 170.00, 65.00, 'B+', 'Brown', 'Black', 'No', '2025-12-02 10:11:25'),
(46, 62, 'Fair', 160.00, 65.00, 'A-', 'Black', 'Black', 'No', '2025-12-02 11:05:49'),
(47, 63, 'Fair', 160.00, 180.00, 'A+', 'Brown', 'Black', 'No', '2025-12-02 15:21:34'),
(48, 65, 'Dark', 180.00, 85.00, 'A+', 'Black', 'Black', 'Yes', '2025-12-02 16:51:18'),
(49, 66, 'Fair', 150.00, 67.00, 'A+', 'Brown', 'Black', 'No', '2025-12-02 17:09:36'),
(50, 67, 'Dark', 170.00, 90.00, 'A+', 'Brown', 'Black', 'Yes', '2025-12-02 18:14:11'),
(51, 68, 'Fair', 120.00, 20.00, 'A+', 'Brown', 'Black', 'No', '2025-12-04 04:52:22'),
(52, 69, 'Fair', 170.00, 50.00, 'A+', '10', '010', '10', '2025-12-04 05:09:36'),
(53, 58, '', 0.00, 0.00, NULL, '', '', '', '2025-12-04 06:53:57'),
(54, 70, 'Fair', 120.00, 65.00, 'B+', 'Brown', 'Black', 'No', '2025-12-04 10:26:01'),
(55, 71, 'Wheatish', 168.00, 65.00, 'A+', 'Brown', 'Black', 'No', '2025-12-08 07:49:55'),
(56, 72, '', 0.00, 0.00, NULL, '', '', '', '2025-12-26 09:46:14');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `review_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `name` varchar(100) DEFAULT NULL,
  `profession` varchar(100) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`review_id`, `rating`, `comment`, `review_date`, `name`, `profession`, `country`, `photo`) VALUES
(6, 4, 'TheanNilavu truly changed my life! I met someone who shares my values and goals. The process was simple, and the support from the team was amazing', '2025-10-22 13:16:30', 'Kavisha Fernando', 'Software Engineer', 'Sri Lanka', '1761138990_1c282bd5c77d49d7bae64ed7a01ff0ab.jpg'),
(7, 3, 'I loved how personalized the matching experience was. Within a month, I met my partner and we instantly connected. Highly recommend TheanNilavu!', '2025-10-22 13:17:35', 'Arjun Patel', 'Graphic Designer', 'India', '1761139055_d4ab9fa85e1530032ebcd2afa6a17e44.jpg'),
(8, 5, 'At first, I was skeptical, but TheanNilavu made it easy to find someone genuine. I’m now happily engaged! Thank you for making dreams come true.', '2025-10-22 13:18:12', 'Nisha Perera', 'Teacher', 'Canada', '1761139092_fixthephoto-wedding-photo-retouching-services-after_1701303442_wh480.jpg'),
(9, 3, 'I appreciated the privacy and thoughtful match suggestions. I met my partner after just two weeks of joining — couldn’t be happier!', '2025-10-22 13:18:54', 'Dinesh Kumar', 'Civil Engineer', 'Sri Lanka', '1761139134_d81b6668b4cd875545b963b9d8541773.jpg'),
(10, 5, 'TheanNilavu is not just a platform — it’s a bridge between hearts. I met someone kind, respectful, and loving. A truly special experience.', '2025-10-22 13:19:36', 'Tharushi Jayasekara', 'Doctor', 'Australia', '1761139176_4.jpg'),
(11, 5, 'I found my soulmate through TheanNilavu. The interface was smooth and user-friendly, and the verification system gave me confidence.', '2025-10-22 13:20:17', 'Mohamed Rizwan', 'Entrepreneur', 'United Arab Emirates', '1761139217_R.jpg'),
(12, 5, 'Hello guys and how are you', '2025-11-24 12:27:08', 'Rutra', 'Actor', 'Sri Lanka', '1763987228_contact.png'),
(13, 5, 'Jdkdmdk', '2025-12-02 06:53:55', 'Kajan', 'Do', 'Srilanka', '1764658435_20251127_130232.jpg'),
(14, 4, 'If you meant something else by â€œmain conentâ€, tell me and Iâ€™ll give the exact output you want.', '2025-12-11 09:01:17', 'Nagarasa Vithusan', 'Software Developer', 'Srilanka', '1765443677_bd66c-veeram2b03_memekadai.blogspot.com_.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `search_queries`
--

CREATE TABLE `search_queries` (
  `id` int(11) NOT NULL,
  `looking_for` varchar(20) DEFAULT NULL,
  `age_range` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `user_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `access_level` varchar(50) NOT NULL DEFAULT 'restricted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staff_id`, `name`, `email`, `password`, `role`, `phone`, `age`, `gender`, `address`, `branch_id`, `position`, `access_level`, `created_at`, `updated_at`) VALUES
(7, 'lingajan', 'admin@thennilavu.com', '$2y$10$kE43RRl4ae5j3uK.oDK9mutqopxoClyyxYwob.1cRARCpwDAsw8L6', 'admin', '074521236', 50, 'Male', 'vbuy7fgvu7y', 1, 'staff', '0', '2025-11-10 09:42:22', '2025-11-24 04:26:28'),
(8, 'Staff User', 'staff@thennilavu.com', '$2y$10$mBw.G/Sdn.qLQKe.eGr1hObipJZHrymc18OFtR.CrGU8DKtuIRa.K', 'staff', '771111111', 50, 'Male', NULL, 2, 'staff', '0', '2025-11-10 09:42:22', '2025-12-17 09:13:21'),
(9, 'lingajan', 'nallathamilan01@gmail.com', '$2y$10$swp/z8XFxd99xPxrMoalBOGn.B9KC8j861.ajCb8aiEEg5bBBNy/u', 'staff', '755350101', 50, 'Male', 'vbuy7fgvu7y', 3, NULL, '0', '2025-11-24 04:26:49', '2025-12-16 06:32:26');

-- --------------------------------------------------------

--
-- Table structure for table `staff_logins`
--

CREATE TABLE `staff_logins` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `ip_address` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `logout_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_login_requests`
--

CREATE TABLE `staff_login_requests` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `ua` text DEFAULT NULL,
  `token` varchar(128) DEFAULT NULL,
  `status` enum('pending','approved','rejected','blocked') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_login_requests`
--

INSERT INTO `staff_login_requests` (`id`, `staff_id`, `name`, `branch_id`, `ip`, `ua`, `token`, `status`, `created_at`, `approved_by`, `approved_at`) VALUES
(1, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '6b8b077c72ab5f4493d7c88198ba04ec', 'approved', '2025-12-17 02:46:38', 7, '2025-12-17 02:46:58'),
(2, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'c1d549754e6d02532a7a5b66906785ab', 'approved', '2025-12-17 02:48:28', 7, '2025-12-17 02:48:45'),
(3, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '290bedbe28170fdff13f2f1629bf9707', 'approved', '2025-12-17 02:49:42', 7, '2025-12-17 02:49:52'),
(4, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'ee41f99a6e8acd2da894e206427c3b81', 'approved', '2025-12-17 02:55:56', 7, '2025-12-17 02:56:08'),
(5, 9, 'lingajan', 3, '175.157.85.114', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', '66c3c2e32e19c56ce848f7445f09d28a', 'approved', '2025-12-17 03:02:50', 7, '2025-12-17 03:03:07'),
(6, 9, 'lingajan', 3, '175.157.85.114', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 'ec58ad861a966c5230527b4fd6230c81', 'approved', '2025-12-17 03:05:47', 7, '2025-12-17 03:06:18'),
(7, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '5c6fd94e65953c2650f11ca812a34ffd', 'approved', '2025-12-17 03:11:40', 7, '2025-12-17 03:11:50'),
(8, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '62d5b4a91d26971efedb44d7a8181187', 'approved', '2025-12-17 03:27:24', 7, '2025-12-17 03:27:34'),
(9, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '25258622c0c98dd895f99d69c3f3aae3', 'approved', '2025-12-17 03:37:17', 7, '2025-12-17 03:41:47'),
(10, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2da8235cd3c418ca6e0f685ad580d149', 'approved', '2025-12-17 03:45:51', 7, '2025-12-17 03:52:57'),
(11, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '870e541f452824f36c90700252a9fffd', 'approved', '2025-12-17 03:54:07', 7, '2025-12-17 03:54:24'),
(12, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '701dcc52f00845f600373e82ea13475f', 'approved', '2025-12-17 03:55:53', 7, '2025-12-17 03:56:41'),
(13, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '3f6580c2c9a2396711fa15e3dad68d6e', 'approved', '2025-12-17 03:57:16', 7, '2025-12-17 03:57:46'),
(14, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'a4d212962c055e9a3ee09ab919e113f0', 'approved', '2025-12-17 03:58:09', 7, '2025-12-17 03:58:27'),
(15, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '9fc1e389c19f758ebbb445efe26a23c3', 'approved', '2025-12-17 04:03:09', 7, '2025-12-17 04:03:19'),
(16, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '4b4c391da88c824fef67a9f7d7a675e7', 'blocked', '2025-12-17 04:03:45', 7, '2025-12-17 04:03:59'),
(17, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'c4f7c5443612aa001de6d2b270b42b61', 'blocked', '2025-12-17 04:04:25', 7, '2025-12-17 04:04:57'),
(18, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '7426dc372b45f957d5907b2cdf7a5046', 'approved', '2025-12-17 04:05:51', 7, '2025-12-17 04:06:19'),
(19, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '973dc6b6f56620ccac09c9c1c5955ecc', 'rejected', '2025-12-17 04:07:04', NULL, NULL),
(20, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'b409256e7166812f93fef130aea66569', 'rejected', '2025-12-17 04:08:01', NULL, NULL),
(21, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '22bef6577248b337c60af91540a7320e', 'approved', '2025-12-17 04:08:16', 7, '2025-12-17 04:08:44'),
(22, 8, 'Staff User', 2, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '344662d871f6960643000274ec015ba0', 'approved', '2025-12-17 04:17:14', 7, '2025-12-17 04:17:25'),
(23, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'adf51a85382c141775d6b09000e27123', 'approved', '2025-12-17 04:41:52', 7, '2025-12-17 04:49:09'),
(24, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'f2a6993439bfcdf20b43da995e528cbf', 'blocked', '2025-12-17 04:46:26', 7, '2025-12-17 04:49:02'),
(25, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '98a93454b6dcf20053fc29747aea7f37', 'approved', '2025-12-17 04:49:23', 7, '2025-12-17 04:54:58'),
(26, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'f4389128c929827022658a289a01c3e3', 'approved', '2025-12-17 04:49:32', 7, '2025-12-17 04:54:49'),
(27, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'aef9916bcf1889ab08bcbe390297c504', 'approved', '2025-12-17 04:55:24', 7, '2025-12-17 05:01:02'),
(28, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2dead7acd719c6a7b3c0fb302b338cf5', 'approved', '2025-12-17 05:01:21', NULL, '2025-12-17 05:01:33'),
(29, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '0e62ddddc177fa54a90668c860f26a7c', 'approved', '2025-12-17 05:02:20', NULL, '2025-12-17 05:02:49'),
(30, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'dab04f88a133e2be0d890884ae624a9b', 'approved', '2025-12-17 05:03:37', NULL, '2025-12-17 05:04:04'),
(31, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '624036a5db992203947d52ec85d3ef98', 'approved', '2025-12-17 05:05:54', NULL, '2025-12-17 05:07:50'),
(32, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '02f2d93ba237a57f82052cff21128d7d', 'rejected', '2025-12-17 05:08:11', NULL, '2025-12-17 05:08:31'),
(33, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '50e6d5aa6060e6578d232564411d88b4', 'approved', '2025-12-17 05:18:28', 7, '2025-12-17 05:19:23'),
(34, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'd05742b71f85a800ae2e30fe1528d69d', 'approved', '2025-12-17 05:23:20', 7, '2025-12-17 05:23:30'),
(35, 9, 'lingajan', 3, '175.157.139.159', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 'f6a63a6889fe0ea37134750a6009f1f2', 'approved', '2025-12-17 06:07:34', 7, '2025-12-17 06:07:51');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` varchar(20) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `member_name` varchar(100) DEFAULT NULL,
  `transaction_date` datetime DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `userpackage`
--

CREATE TABLE `userpackage` (
  `id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `duration` int(11) NOT NULL,
  `requestPackage` varchar(50) NOT NULL,
  `slip` varchar(255) DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `userpackage`
--

INSERT INTO `userpackage` (`id`, `status`, `duration`, `requestPackage`, `slip`, `start_date`, `end_date`, `user_id`) VALUES
(42, 'Brownze', 180, 'accept', 'uploads/payment_slips/slip_35_1765451259.jpg', '2025-12-11 11:07:53', '2026-06-09 11:07:53', 35),
(56, 'Silver', 30, 'yes', 'uploads/payment_slips/slip_35_1766144443.jpg', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 35),
(57, 'Silver', 30, 'yes', 'uploads/payment_slips/slip_35_1766144445.jpg', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 35),
(58, 'Silver', 30, 'yes', 'uploads/payment_slips/slip_35_1766144445.jpg', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 35),
(59, 'Premium', 30, 'yes', 'uploads/payment_slips/slip_12_1766144535.jpg', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 12),
(60, 'Brownze', 180, 'yes', 'uploads/payment_slips/slip_35_1766468540.png', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 35),
(61, 'Premium', 30, 'yes', 'uploads/payment_slips/slip_35_1766551892.png', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 35),
(62, 'Brownze', 180, 'yes', 'uploads/payment_slips/slip_35_1766556478.jpg', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 35),
(63, 'Brownze', 180, 'accept', 'uploads/payment_slips/slip_12_1766742668.jpg', '2025-12-26 09:51:41', '2026-06-24 09:51:41', 12),
(64, 'Premium', 30, 'yes', '/uploads/payment_slips/slip_12_1766749760.jpg', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 12);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('admin','user','block') NOT NULL DEFAULT 'user',
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `created_at`, `role`, `password_reset_token`, `password_reset_expires`) VALUES
(1, 'kaja01', 'kaja01@example.com', '$2y$10$vLvpxL.vkLetLukU8qNCC.jzSM.Qpj7MV63tjjIbKkk3KeF/AJH.m', '2025-11-27 04:35:44', 'user', NULL, NULL),
(2, 'vithu99', 'vithu99@example.com', '$2y$10$vLvpxL.vkLetLukU8qNCC.jzSM.Qpj7MV63tjjIbKkk3KeF/AJH.m', '2025-11-27 04:35:44', 'user', NULL, NULL),
(3, 'priya88', 'priya88@example.com', '$2y$10$vLvpxL.vkLetLukU8qNCC.jzSM.Qpj7MV63tjjIbKkk3KeF/AJH.m', '2025-11-27 04:35:44', 'user', NULL, NULL),
(4, 'saran77', 'saran77@example.com', '$2y$10$vLvpxL.vkLetLukU8qNCC.jzSM.Qpj7MV63tjjIbKkk3KeF/AJH.m', '2025-11-27 04:35:44', 'user', NULL, NULL),
(5, 'kavi66', 'kavi66@example.com', '$2y$10$vLvpxL.vkLetLukU8qNCC.jzSM.Qpj7MV63tjjIbKkk3KeF/AJH.m', '2025-11-27 04:35:44', 'user', NULL, NULL),
(6, 'nisha55', 'nisha55@example.com', '$2y$10$vLvpxL.vkLetLukU8qNCC.jzSM.Qpj7MV63tjjIbKkk3KeF/AJH.m', '2025-11-27 04:35:44', 'user', NULL, NULL),
(7, 'arjun44', 'arjun44@example.com', '$2y$10$vLvpxL.vkLetLukU8qNCC.jzSM.Qpj7MV63tjjIbKkk3KeF/AJH.m', '2025-11-27 04:35:44', 'user', NULL, NULL),
(8, 'mala33', 'mala33@example.com', '$2y$10$vLvpxL.vkLetLukU8qNCC.jzSM.Qpj7MV63tjjIbKkk3KeF/AJH.m', '2025-11-27 04:35:44', 'user', NULL, NULL),
(9, 'daniel22', 'daniel22@example.com', '$2y$10$vLvpxL.vkLetLukU8qNCC.jzSM.Qpj7MV63tjjIbKkk3KeF/AJH.m', '2025-11-27 04:35:44', 'user', NULL, NULL),
(10, 'meena11', 'meena11@example.com', '$2y$10$vLvpxL.vkLetLukU8qNCC.jzSM.Qpj7MV63tjjIbKkk3KeF/AJH.m', '2025-11-27 04:35:44', 'user', NULL, NULL),
(12, 'thanu', 'kamalanathanthananchayan04@gmail.com', '$2y$10$ReHFTAuub4DPak5S5cideOYZczjbRoyJvDyWcALYtJRwH/NZsOjmq', '2025-09-23 03:36:12', 'user', '335794', '2025-11-26 09:03:39'),
(17, 'Thanu', 'kamalanathanthananchayan05@gmail.com', '$2y$10$apzgG.GPmX6STKfUjkY1tutuyIq3Y66oo1czE6M3Txcw4bWmRbakG', '2025-10-26 14:09:32', 'user', NULL, NULL),
(22, 'admin', 'admin@thennilavu.com', '0192023a7bbd73250516f069df18b500', '2025-11-10 09:29:41', 'admin', NULL, NULL),
(24, 'chartheepan@outlook.com', 'chartheepan@outlook.com', '$2y$10$RFixV3hsvwyGvoVdivBWtOHgGkDD2gKF49bpGYQLemf5oVheK0vbS', '2025-11-11 03:55:19', 'block', NULL, NULL),
(25, 'chartheepan2@outlook.com', 'chartheepan2@outlook.com', '$2y$10$TWN/LWu3GP1oJO2YZdznkeAvb1G2EchrVhNVLubeQGeYTJU1EtWs6', '2025-11-14 11:46:45', 'user', NULL, NULL),
(26, 'Thananchayan', 'kamalanathanthananchayan09@gmail.com', '$2y$10$IlvDbfpMGxCem8KCThXG/.bPB8IrBPe/kU8fVd9JdeEZviSF6IT6e', '2025-11-25 04:23:04', 'user', NULL, NULL),
(28, 'Kamalanathan', 'kamalanathanthananchayan22@gmail.com', '$2y$10$2wvjrKa4xmttYRhBLbv.w.4i5yCDUnY0rgUmu1jheMbbjxbh.7AAm', '2025-11-25 08:01:32', 'user', NULL, NULL),
(30, 'Malikapuram', 'kamalanathanthananchayan11@gmail.com', '$2y$10$qY1hqJhlFyAvfciXIiE6EuawVkoEw/20UYxOz72jxdcjLsYWoJGnS', '2025-11-26 04:43:10', 'user', NULL, NULL),
(31, 'Sam', 'nallathamilan01@gmail.com', '$2y$10$1RrkuO.tXsYSLjPLHb9/7uA/d3c8C1WIu.33wrA.ef/bTYZLRctoG', '2025-11-27 05:28:11', 'user', '545134', '2025-12-19 05:37:52'),
(32, 'kamal', 'kamalanathanthananchayan44@gmail.com', '$2y$10$3NvY.VpOp3X6HOnBRhKPCe.6XkMAybDaWMn2YcFabu7o8YtU1Rqye', '2025-11-27 12:27:22', 'user', NULL, NULL),
(33, 'Subashini Vishnuvarthan', 'thennilavumatrimon.y@gmail.com', '$2y$10$vu/Tcc7tk8NjMrT/80WRi.41mwFN.LrUpdVLTGdb3PWs5nkAJf4ty', '2025-12-02 08:05:10', 'user', NULL, NULL),
(34, 'webbuilders', 'k@gmail.com', '$2y$10$oeX.AyiI4CWKNKgh9LbTJOHQ1DprGtZAYZnpUyzRIIAcY2KyBHP3O', '2025-12-02 09:02:48', 'user', NULL, NULL),
(35, 'Sam', 'nallathamilan0101@gmail.com', '$2y$10$vLvpxL.vkLetLukU8qNCC.jzSM.Qpj7MV63tjjIbKkk3KeF/AJH.m', '2025-12-02 09:57:45', 'user', NULL, NULL),
(36, 'kamal', 'kamalanathan@gmail.com', '$2y$10$SAHcEOuxG4T0rAoJsAtkoOcfYcTZnNP27F0KiANB02pqvsDhWCsGa', '2025-12-02 10:09:02', 'user', NULL, NULL),
(37, 'san', 'kamala@gmail.com', '$2y$10$ReHFTAuub4DPak5S5cideOYZczjbRoyJvDyWcALYtJRwH/NZsOjmq', '2025-12-02 14:03:31', 'user', NULL, NULL),
(38, 'sam', 'nallathamilan02@gmail.com', '$2y$10$6DXV1Wv7HvTt8X7jP5JVXOzjKhalX64c/eW9O2owHjWgzY0zmfkSq', '2025-12-04 03:46:24', 'user', NULL, NULL),
(39, 'Thanu', 'Thanu@gmail.com', '$2y$10$5oh5MlMRy/LWNfrDR7OlJOH0CUwVXz0hE9BT9w1tNvX6HSeZMdzim', '2025-12-04 10:02:57', 'user', NULL, NULL),
(40, 'sam1', 'sam@gmail.com', '$2y$10$aiuiKjrJiWbsXKiZlfx0kek6G40DMyZe17lo0wlPdkt2/GWrb95te', '2025-12-08 03:56:12', 'user', NULL, NULL),
(41, 'Vibhushan Nandan', 'thennilavumatrimo.ny@gmail.com', '$2y$10$0GE.qz/UIy1TuNcSlOKhOuzg/OI61U9DVBMxPQASyamQ0Y7Fsh.ha', '2025-12-08 07:42:32', 'user', NULL, NULL),
(42, 'it23412866', 'nallathamilan010101@gmail.com', '$2y$10$kvfteuJdAWBs04gEVOiHMurjPwEQ5lpDO1mtInkpIYHgOhR3Zfc8W', '2025-12-19 05:31:59', 'user', NULL, NULL),
(43, 'Vithusan', 'nagarasha.vithusan@gmail.com', '$2y$10$yvJtim1o/yb2OLnypo.VY.aeDiI6FSh0VxQNzkFOLb9zo8sCaVf4u', '2025-12-26 03:31:38', 'user', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_daily_interest_counts`
--

CREATE TABLE `user_daily_interest_counts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `interest_date` date NOT NULL DEFAULT curdate(),
  `likes_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_daily_interest_counts`
--

INSERT INTO `user_daily_interest_counts` (`id`, `user_id`, `interest_date`, `likes_count`, `created_at`, `updated_at`) VALUES
(146, 1, '2025-11-27', 5, '2025-11-27 05:45:18', '2025-11-27 05:45:27'),
(151, 37, '2025-12-02', 36, '2025-12-02 17:49:09', '2025-12-02 18:47:00'),
(187, 35, '2025-12-04', 1, '2025-12-04 09:49:25', '2025-12-04 09:49:25'),
(188, 39, '2025-12-04', 5, '2025-12-04 10:28:46', '2025-12-04 10:28:55'),
(193, 12, '2025-12-09', 36, '2025-12-09 06:48:07', '2025-12-09 06:52:19'),
(229, 35, '2025-12-10', 1, '2025-12-11 04:45:44', '2025-12-11 04:45:44'),
(230, 12, '2025-12-11', 5, '2025-12-11 09:56:53', '2025-12-11 09:56:57'),
(235, 35, '2025-12-11', 1, '2025-12-11 10:51:02', '2025-12-11 10:51:02'),
(236, 35, '2025-12-19', 7, '2025-12-19 09:54:02', '2025-12-19 11:21:41'),
(243, 35, '2025-12-23', 4, '2025-12-23 08:56:11', '2025-12-23 10:31:47'),
(247, 12, '2025-12-26', 5, '2025-12-26 07:24:08', '2025-12-26 07:24:15'),
(252, 35, '2025-12-26', 4, '2025-12-26 09:27:16', '2025-12-26 09:27:19');

-- --------------------------------------------------------

--
-- Table structure for table `user_daily_profile_views`
--

CREATE TABLE `user_daily_profile_views` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `view_date` date NOT NULL DEFAULT curdate(),
  `views_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_daily_profile_views`
--

INSERT INTO `user_daily_profile_views` (`id`, `user_id`, `view_date`, `views_count`, `created_at`, `updated_at`) VALUES
(232, 1, '2025-11-27', 9, '2025-11-27 05:45:28', '2025-11-28 03:01:30'),
(238, 12, '2025-11-27', 29, '2025-11-27 12:22:18', '2025-11-27 14:28:05'),
(270, 12, '2025-11-30', 7, '2025-12-01 03:52:20', '2025-12-01 04:49:51'),
(277, 12, '2025-12-01', 19, '2025-12-01 05:01:30', '2025-12-01 13:59:36'),
(281, 1, '2025-12-01', 12, '2025-12-01 06:33:51', '2025-12-01 14:37:07'),
(308, 12, '2025-12-02', 1, '2025-12-02 05:27:44', '2025-12-02 05:27:44'),
(309, 33, '2025-12-02', 10, '2025-12-02 09:12:43', '2025-12-02 09:16:18'),
(319, 36, '2025-12-02', 10, '2025-12-02 10:14:14', '2025-12-02 10:24:14'),
(329, 37, '2025-12-02', 36, '2025-12-02 15:23:45', '2025-12-02 18:35:14'),
(365, 38, '2025-12-03', 3, '2025-12-04 03:47:24', '2025-12-04 04:53:58'),
(368, 12, '2025-12-04', 2, '2025-12-04 05:41:43', '2025-12-04 05:44:12'),
(369, 1, '2025-12-04', 13, '2025-12-04 05:44:11', '2025-12-04 10:41:03'),
(371, 37, '2025-12-04', 4, '2025-12-04 05:54:21', '2025-12-04 07:04:10'),
(386, 35, '2025-12-04', 4, '2025-12-04 08:04:40', '2025-12-04 09:50:35'),
(390, 39, '2025-12-04', 1, '2025-12-04 10:29:04', '2025-12-04 10:29:04'),
(392, 33, '2025-12-08', 10, '2025-12-08 07:28:22', '2025-12-08 07:32:00'),
(402, 41, '2025-12-08', 2, '2025-12-08 08:25:38', '2025-12-08 08:25:55'),
(404, 12, '2025-12-09', 100, '2025-12-09 05:43:53', '2025-12-09 10:24:43'),
(450, 35, '2025-12-09', 8, '2025-12-09 07:37:48', '2025-12-09 11:31:54'),
(512, 35, '2025-12-10', 3, '2025-12-11 04:44:26', '2025-12-11 04:45:35'),
(515, 12, '2025-12-11', 10, '2025-12-11 07:28:25', '2025-12-11 11:06:22'),
(516, 35, '2025-12-11', 24, '2025-12-11 07:30:44', '2025-12-11 11:15:49'),
(549, 35, '2025-12-19', 46, '2025-12-19 06:20:41', '2025-12-19 11:22:05'),
(595, 35, '2025-12-22', 1, '2025-12-23 04:58:29', '2025-12-23 04:58:29'),
(596, 35, '2025-12-23', 134, '2025-12-23 05:21:10', '2025-12-23 10:31:04'),
(739, 35, '2025-12-24', 86, '2025-12-24 06:11:53', '2025-12-25 04:06:06'),
(815, 12, '2025-12-24', 1, '2025-12-24 07:54:02', '2025-12-24 07:54:02'),
(826, 35, '2025-12-25', 17, '2025-12-26 03:45:32', '2025-12-26 04:05:53'),
(843, 35, '2025-12-26', 204, '2025-12-26 05:12:39', '2025-12-26 11:54:08'),
(975, 12, '2025-12-26', 73, '2025-12-26 07:24:59', '2025-12-26 11:28:12');

-- --------------------------------------------------------

--
-- Table structure for table `user_interests`
--

CREATE TABLE `user_interests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `target_member_id` int(11) NOT NULL,
  `interest_date` date NOT NULL DEFAULT curdate(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_interests`
--

INSERT INTO `user_interests` (`id`, `user_id`, `target_member_id`, `interest_date`, `created_at`) VALUES
(165, 1, 2, '2025-11-27', '2025-11-27 05:45:18'),
(166, 1, 3, '2025-11-27', '2025-11-27 05:45:20'),
(167, 1, 4, '2025-11-27', '2025-11-27 05:45:23'),
(168, 1, 5, '2025-11-27', '2025-11-27 05:45:25'),
(169, 1, 6, '2025-11-27', '2025-11-27 05:45:27'),
(170, 37, 59, '2025-12-02', '2025-12-02 17:49:09'),
(177, 37, 58, '2025-12-02', '2025-12-02 17:49:15'),
(198, 37, 60, '2025-12-02', '2025-12-02 17:52:38'),
(205, 37, 65, '2025-12-02', '2025-12-02 18:47:00'),
(206, 35, 68, '2025-12-04', '2025-12-04 09:49:25'),
(207, 39, 70, '2025-12-04', '2025-12-04 10:28:46'),
(212, 12, 52, '2025-12-09', '2025-12-09 06:48:07'),
(218, 12, 53, '2025-12-09', '2025-12-09 06:48:20'),
(220, 12, 51, '2025-12-09', '2025-12-09 06:52:07'),
(248, 35, 26, '2025-12-10', '2025-12-11 04:45:44'),
(249, 12, 70, '2025-12-11', '2025-12-11 09:56:53'),
(254, 35, 52, '2025-12-11', '2025-12-11 10:51:02'),
(255, 35, 51, '2025-12-19', '2025-12-19 09:54:02'),
(256, 35, 1, '2025-12-19', '2025-12-19 09:54:05'),
(257, 35, 2, '2025-12-19', '2025-12-19 09:54:08'),
(258, 35, 67, '2025-12-19', '2025-12-19 10:52:00'),
(259, 35, 4, '2025-12-19', '2025-12-19 10:53:21'),
(260, 35, 3, '2025-12-19', '2025-12-19 10:53:22'),
(261, 35, 71, '2025-12-19', '2025-12-19 11:21:41'),
(264, 35, 70, '2025-12-23', '2025-12-23 10:31:45'),
(266, 12, 67, '2025-12-26', '2025-12-26 07:24:08'),
(267, 12, 72, '2025-12-26', '2025-12-26 07:24:12'),
(271, 35, 69, '2025-12-26', '2025-12-26 09:27:16');

-- --------------------------------------------------------

--
-- Table structure for table `user_interest_events`
--

CREATE TABLE `user_interest_events` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `target_member_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_interest_events`
--

INSERT INTO `user_interest_events` (`id`, `user_id`, `target_member_id`, `created_at`) VALUES
(65, 37, 59, '2025-12-02 17:49:09'),
(66, 37, 59, '2025-12-02 17:49:10'),
(67, 37, 59, '2025-12-02 17:49:11'),
(68, 37, 59, '2025-12-02 17:49:11'),
(69, 37, 59, '2025-12-02 17:49:12'),
(70, 37, 59, '2025-12-02 17:49:12'),
(71, 37, 58, '2025-12-02 17:49:16'),
(72, 37, 58, '2025-12-02 17:49:16'),
(73, 37, 58, '2025-12-02 17:49:17'),
(74, 37, 59, '2025-12-02 17:52:19'),
(75, 37, 59, '2025-12-02 17:52:19'),
(76, 37, 59, '2025-12-02 17:52:20'),
(77, 37, 59, '2025-12-02 17:52:20'),
(78, 37, 59, '2025-12-02 17:52:21'),
(79, 37, 59, '2025-12-02 17:52:21'),
(80, 37, 59, '2025-12-02 17:52:22'),
(81, 37, 59, '2025-12-02 17:52:22'),
(82, 37, 59, '2025-12-02 17:52:23'),
(83, 37, 59, '2025-12-02 17:52:23'),
(84, 37, 59, '2025-12-02 17:52:24'),
(85, 37, 59, '2025-12-02 17:52:24'),
(86, 37, 59, '2025-12-02 17:52:24'),
(87, 37, 59, '2025-12-02 17:52:25'),
(88, 37, 59, '2025-12-02 17:52:25'),
(89, 37, 59, '2025-12-02 17:52:26'),
(90, 37, 59, '2025-12-02 17:52:28'),
(91, 37, 60, '2025-12-02 17:52:39'),
(92, 37, 60, '2025-12-02 17:52:42'),
(93, 37, 60, '2025-12-02 17:52:42'),
(94, 37, 60, '2025-12-02 17:52:43'),
(95, 37, 60, '2025-12-02 17:52:43'),
(96, 37, 60, '2025-12-02 17:52:44'),
(97, 39, 70, '2025-12-04 10:28:48'),
(98, 39, 70, '2025-12-04 10:28:50'),
(99, 39, 70, '2025-12-04 10:28:54'),
(100, 39, 70, '2025-12-04 10:28:55'),
(101, 12, 52, '2025-12-09 06:48:16'),
(102, 12, 52, '2025-12-09 06:48:17'),
(103, 12, 52, '2025-12-09 06:48:17'),
(104, 12, 52, '2025-12-09 06:48:18'),
(105, 12, 52, '2025-12-09 06:48:18'),
(106, 12, 53, '2025-12-09 06:48:21'),
(107, 12, 51, '2025-12-09 06:52:07'),
(108, 12, 51, '2025-12-09 06:52:08'),
(109, 12, 51, '2025-12-09 06:52:08'),
(110, 12, 51, '2025-12-09 06:52:09'),
(111, 12, 51, '2025-12-09 06:52:09'),
(112, 12, 51, '2025-12-09 06:52:09'),
(113, 12, 51, '2025-12-09 06:52:10'),
(114, 12, 51, '2025-12-09 06:52:10'),
(115, 12, 51, '2025-12-09 06:52:11'),
(116, 12, 51, '2025-12-09 06:52:11'),
(117, 12, 51, '2025-12-09 06:52:12'),
(118, 12, 51, '2025-12-09 06:52:12'),
(119, 12, 51, '2025-12-09 06:52:12'),
(120, 12, 51, '2025-12-09 06:52:13'),
(121, 12, 51, '2025-12-09 06:52:14'),
(122, 12, 51, '2025-12-09 06:52:14'),
(123, 12, 51, '2025-12-09 06:52:14'),
(124, 12, 51, '2025-12-09 06:52:15'),
(125, 12, 51, '2025-12-09 06:52:15'),
(126, 12, 51, '2025-12-09 06:52:16'),
(127, 12, 51, '2025-12-09 06:52:16'),
(128, 12, 51, '2025-12-09 06:52:17'),
(129, 12, 51, '2025-12-09 06:52:17'),
(130, 12, 51, '2025-12-09 06:52:17'),
(131, 12, 51, '2025-12-09 06:52:18'),
(132, 12, 51, '2025-12-09 06:52:18'),
(133, 12, 51, '2025-12-09 06:52:19'),
(134, 12, 70, '2025-12-11 09:56:54'),
(135, 12, 70, '2025-12-11 09:56:55'),
(136, 12, 70, '2025-12-11 09:56:56'),
(137, 12, 70, '2025-12-11 09:56:57'),
(138, 35, 71, '2025-12-23 08:56:11'),
(139, 35, 71, '2025-12-23 08:56:14'),
(140, 35, 70, '2025-12-23 10:31:47'),
(141, 12, 72, '2025-12-26 07:24:14'),
(142, 12, 72, '2025-12-26 07:24:15'),
(143, 12, 72, '2025-12-26 07:24:15'),
(144, 35, 69, '2025-12-26 09:27:18'),
(145, 35, 69, '2025-12-26 09:27:18'),
(146, 35, 69, '2025-12-26 09:27:19');

-- --------------------------------------------------------

--
-- Stand-in structure for view `user_interest_summary`
-- (See below for the actual view)
--
CREATE TABLE `user_interest_summary` (
`user_id` int(11)
,`username` varchar(100)
,`today_likes_count` int(11)
,`interest_limit` varchar(50)
,`package_name` varchar(100)
,`interest_status` varchar(62)
);

-- --------------------------------------------------------

--
-- Table structure for table `user_profile_views`
--

CREATE TABLE `user_profile_views` (
  `id` int(11) NOT NULL,
  `viewer_id` int(11) NOT NULL,
  `viewed_member_id` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profile_views`
--

INSERT INTO `user_profile_views` (`id`, `viewer_id`, `viewed_member_id`, `viewed_at`) VALUES
(312, 1, 3, '2025-12-01 14:29:28'),
(397, 1, 3, '2025-12-04 10:41:03'),
(239, 1, 4, '2025-11-27 06:30:36'),
(309, 1, 4, '2025-12-01 14:27:35'),
(234, 1, 5, '2025-11-27 05:45:28'),
(235, 1, 5, '2025-11-27 05:45:39'),
(236, 1, 6, '2025-11-27 05:45:44'),
(313, 1, 9, '2025-12-01 14:37:07'),
(237, 1, 51, '2025-11-27 06:29:37'),
(238, 1, 51, '2025-11-27 06:29:55'),
(269, 1, 51, '2025-11-28 02:56:01'),
(270, 1, 51, '2025-11-28 02:57:37'),
(271, 1, 51, '2025-11-28 03:01:30'),
(284, 1, 51, '2025-12-01 06:33:51'),
(287, 1, 53, '2025-12-01 06:37:13'),
(288, 1, 53, '2025-12-01 06:41:51'),
(291, 1, 53, '2025-12-01 07:09:59'),
(296, 1, 53, '2025-12-01 11:06:44'),
(310, 1, 53, '2025-12-01 14:28:06'),
(311, 1, 53, '2025-12-01 14:29:02'),
(289, 1, 54, '2025-12-01 07:02:56'),
(290, 1, 54, '2025-12-01 07:09:24'),
(375, 1, 59, '2025-12-04 05:44:11'),
(378, 1, 59, '2025-12-04 05:56:31'),
(380, 1, 59, '2025-12-04 05:59:30'),
(381, 1, 59, '2025-12-04 06:12:38'),
(384, 1, 59, '2025-12-04 06:18:37'),
(386, 1, 59, '2025-12-04 06:34:37'),
(388, 1, 59, '2025-12-04 06:50:15'),
(389, 1, 59, '2025-12-04 06:52:28'),
(382, 1, 68, '2025-12-04 06:13:55'),
(383, 1, 68, '2025-12-04 06:18:23'),
(385, 1, 68, '2025-12-04 06:34:26'),
(387, 1, 68, '2025-12-04 06:37:52'),
(240, 12, 1, '2025-11-27 12:22:18'),
(242, 12, 1, '2025-11-27 12:41:34'),
(247, 12, 1, '2025-11-27 12:44:09'),
(248, 12, 1, '2025-11-27 12:47:00'),
(249, 12, 1, '2025-11-27 13:04:58'),
(250, 12, 1, '2025-11-27 13:07:05'),
(251, 12, 1, '2025-11-27 13:14:07'),
(252, 12, 1, '2025-11-27 13:14:48'),
(253, 12, 1, '2025-11-27 13:16:01'),
(254, 12, 1, '2025-11-27 13:27:58'),
(255, 12, 1, '2025-11-27 13:29:16'),
(256, 12, 1, '2025-11-27 13:29:36'),
(257, 12, 1, '2025-11-27 13:30:39'),
(258, 12, 1, '2025-11-27 13:47:23'),
(259, 12, 1, '2025-11-27 13:51:26'),
(260, 12, 1, '2025-11-27 13:56:33'),
(261, 12, 1, '2025-11-27 13:59:51'),
(262, 12, 1, '2025-11-27 14:00:39'),
(263, 12, 1, '2025-11-27 14:07:42'),
(264, 12, 1, '2025-11-27 14:17:12'),
(267, 12, 1, '2025-11-27 14:27:41'),
(281, 12, 1, '2025-12-01 06:20:26'),
(282, 12, 1, '2025-12-01 06:20:41'),
(450, 12, 2, '2025-12-09 06:55:28'),
(454, 12, 2, '2025-12-09 07:01:11'),
(268, 12, 3, '2025-11-27 14:28:05'),
(307, 12, 3, '2025-12-01 13:48:55'),
(308, 12, 3, '2025-12-01 13:59:36'),
(451, 12, 3, '2025-12-09 06:55:36'),
(452, 12, 4, '2025-12-09 06:55:58'),
(421, 12, 7, '2025-12-09 06:06:13'),
(1274, 12, 8, '2025-12-26 10:18:13'),
(422, 12, 26, '2025-12-09 06:08:06'),
(245, 12, 51, '2025-11-27 12:43:55'),
(266, 12, 51, '2025-11-27 14:25:41'),
(280, 12, 51, '2025-12-01 05:01:30'),
(427, 12, 51, '2025-12-09 06:28:44'),
(440, 12, 51, '2025-12-09 06:48:00'),
(491, 12, 51, '2025-12-09 08:21:43'),
(496, 12, 51, '2025-12-09 08:24:47'),
(500, 12, 51, '2025-12-09 08:30:23'),
(501, 12, 51, '2025-12-09 08:30:47'),
(246, 12, 52, '2025-11-27 12:44:01'),
(276, 12, 52, '2025-12-01 03:54:10'),
(300, 12, 52, '2025-12-01 13:36:57'),
(303, 12, 52, '2025-12-01 13:47:17'),
(423, 12, 52, '2025-12-09 06:12:49'),
(424, 12, 52, '2025-12-09 06:13:38'),
(425, 12, 52, '2025-12-09 06:14:21'),
(426, 12, 52, '2025-12-09 06:20:24'),
(428, 12, 52, '2025-12-09 06:29:07'),
(429, 12, 52, '2025-12-09 06:30:45'),
(430, 12, 52, '2025-12-09 06:30:54'),
(431, 12, 52, '2025-12-09 06:31:04'),
(432, 12, 52, '2025-12-09 06:31:18'),
(433, 12, 52, '2025-12-09 06:31:39'),
(434, 12, 52, '2025-12-09 06:31:45'),
(436, 12, 52, '2025-12-09 06:35:56'),
(437, 12, 52, '2025-12-09 06:36:04'),
(438, 12, 52, '2025-12-09 06:36:20'),
(439, 12, 52, '2025-12-09 06:47:43'),
(441, 12, 52, '2025-12-09 06:48:09'),
(445, 12, 52, '2025-12-09 06:51:04'),
(446, 12, 52, '2025-12-09 06:51:20'),
(448, 12, 52, '2025-12-09 06:54:07'),
(453, 12, 52, '2025-12-09 07:00:55'),
(455, 12, 52, '2025-12-09 07:02:54'),
(460, 12, 52, '2025-12-09 07:39:57'),
(461, 12, 52, '2025-12-09 07:40:07'),
(462, 12, 52, '2025-12-09 07:44:20'),
(464, 12, 52, '2025-12-09 07:44:35'),
(465, 12, 52, '2025-12-09 07:47:11'),
(466, 12, 52, '2025-12-09 07:51:52'),
(467, 12, 52, '2025-12-09 07:52:38'),
(479, 12, 52, '2025-12-09 08:01:21'),
(480, 12, 52, '2025-12-09 08:01:41'),
(483, 12, 52, '2025-12-09 08:02:15'),
(484, 12, 52, '2025-12-09 08:04:55'),
(489, 12, 52, '2025-12-09 08:10:57'),
(490, 12, 52, '2025-12-09 08:21:39'),
(493, 12, 52, '2025-12-09 08:22:45'),
(495, 12, 52, '2025-12-09 08:24:32'),
(497, 12, 52, '2025-12-09 08:24:50'),
(498, 12, 52, '2025-12-09 08:27:05'),
(499, 12, 52, '2025-12-09 08:30:19'),
(502, 12, 52, '2025-12-09 08:30:51'),
(503, 12, 52, '2025-12-09 08:36:49'),
(505, 12, 52, '2025-12-09 08:37:07'),
(550, 12, 52, '2025-12-11 11:06:22'),
(241, 12, 53, '2025-11-27 12:38:44'),
(243, 12, 53, '2025-11-27 12:41:51'),
(244, 12, 53, '2025-11-27 12:43:49'),
(275, 12, 53, '2025-12-01 03:54:05'),
(279, 12, 53, '2025-12-01 04:49:51'),
(293, 12, 53, '2025-12-01 08:41:03'),
(294, 12, 53, '2025-12-01 08:46:33'),
(295, 12, 53, '2025-12-01 08:46:53'),
(297, 12, 53, '2025-12-01 13:00:23'),
(299, 12, 53, '2025-12-01 13:36:41'),
(314, 12, 53, '2025-12-02 05:27:44'),
(449, 12, 53, '2025-12-09 06:54:56'),
(463, 12, 53, '2025-12-09 07:44:31'),
(481, 12, 53, '2025-12-09 08:01:45'),
(265, 12, 54, '2025-11-27 14:18:00'),
(272, 12, 54, '2025-12-01 03:52:20'),
(273, 12, 54, '2025-12-01 03:53:48'),
(277, 12, 54, '2025-12-01 03:54:20'),
(278, 12, 54, '2025-12-01 03:59:39'),
(283, 12, 54, '2025-12-01 06:21:50'),
(292, 12, 54, '2025-12-01 08:40:45'),
(298, 12, 54, '2025-12-01 13:32:45'),
(301, 12, 54, '2025-12-01 13:40:23'),
(304, 12, 54, '2025-12-01 13:47:27'),
(305, 12, 54, '2025-12-01 13:48:08'),
(306, 12, 54, '2025-12-01 13:48:36'),
(494, 12, 54, '2025-12-09 08:24:27'),
(456, 12, 55, '2025-12-09 07:03:17'),
(487, 12, 55, '2025-12-09 08:10:09'),
(488, 12, 55, '2025-12-09 08:10:52'),
(492, 12, 55, '2025-12-09 08:22:40'),
(482, 12, 56, '2025-12-09 08:01:52'),
(504, 12, 56, '2025-12-09 08:36:56'),
(513, 12, 56, '2025-12-09 08:55:03'),
(515, 12, 56, '2025-12-09 08:56:19'),
(435, 12, 57, '2025-12-09 06:32:04'),
(444, 12, 58, '2025-12-09 06:50:55'),
(478, 12, 58, '2025-12-09 08:00:53'),
(376, 12, 59, '2025-12-04 05:44:12'),
(442, 12, 59, '2025-12-09 06:48:40'),
(486, 12, 59, '2025-12-09 08:10:03'),
(1223, 12, 60, '2025-12-26 10:05:45'),
(1224, 12, 60, '2025-12-26 10:05:46'),
(1225, 12, 60, '2025-12-26 10:07:06'),
(1226, 12, 60, '2025-12-26 10:07:07'),
(1221, 12, 61, '2025-12-26 10:05:23'),
(1222, 12, 61, '2025-12-26 10:05:24'),
(508, 12, 62, '2025-12-09 08:40:11'),
(1258, 12, 62, '2025-12-26 10:14:57'),
(1259, 12, 62, '2025-12-26 10:14:58'),
(1260, 12, 62, '2025-12-26 10:15:52'),
(1261, 12, 62, '2025-12-26 10:15:54'),
(1262, 12, 62, '2025-12-26 10:15:56'),
(1263, 12, 62, '2025-12-26 10:16:00'),
(1264, 12, 62, '2025-12-26 10:16:05'),
(1268, 12, 62, '2025-12-26 10:16:07'),
(1269, 12, 62, '2025-12-26 10:16:08'),
(1272, 12, 62, '2025-12-26 10:17:45'),
(443, 12, 63, '2025-12-09 06:49:04'),
(468, 12, 63, '2025-12-09 07:54:41'),
(469, 12, 63, '2025-12-09 07:55:17'),
(470, 12, 63, '2025-12-09 07:57:10'),
(471, 12, 63, '2025-12-09 07:58:21'),
(472, 12, 63, '2025-12-09 07:59:05'),
(473, 12, 63, '2025-12-09 07:59:20'),
(474, 12, 63, '2025-12-09 07:59:24'),
(475, 12, 63, '2025-12-09 08:00:06'),
(476, 12, 63, '2025-12-09 08:00:20'),
(477, 12, 63, '2025-12-09 08:00:48'),
(485, 12, 63, '2025-12-09 08:05:05'),
(509, 12, 65, '2025-12-09 08:43:39'),
(522, 12, 65, '2025-12-11 07:28:25'),
(507, 12, 66, '2025-12-09 08:37:28'),
(1215, 12, 66, '2025-12-26 10:00:52'),
(1217, 12, 66, '2025-12-26 10:02:39'),
(1219, 12, 66, '2025-12-26 10:05:11'),
(1233, 12, 66, '2025-12-26 10:08:19'),
(1234, 12, 66, '2025-12-26 10:08:20'),
(447, 12, 67, '2025-12-09 06:53:52'),
(526, 12, 67, '2025-12-11 08:58:40'),
(545, 12, 67, '2025-12-11 09:55:55'),
(547, 12, 67, '2025-12-11 09:57:06'),
(548, 12, 67, '2025-12-11 09:58:27'),
(852, 12, 67, '2025-12-24 07:54:02'),
(1122, 12, 67, '2025-12-26 07:28:25'),
(1123, 12, 67, '2025-12-26 07:28:26'),
(1175, 12, 67, '2025-12-26 09:52:18'),
(1176, 12, 67, '2025-12-26 09:52:19'),
(1193, 12, 67, '2025-12-26 09:57:44'),
(1209, 12, 67, '2025-12-26 10:00:17'),
(1211, 12, 67, '2025-12-26 10:00:35'),
(1212, 12, 67, '2025-12-26 10:00:36'),
(1276, 12, 67, '2025-12-26 10:18:55'),
(1277, 12, 67, '2025-12-26 10:18:56'),
(1278, 12, 67, '2025-12-26 10:18:57'),
(1280, 12, 67, '2025-12-26 10:19:50'),
(374, 12, 68, '2025-12-04 05:41:43'),
(544, 12, 68, '2025-12-11 09:55:45'),
(1213, 12, 68, '2025-12-26 10:00:41'),
(1214, 12, 68, '2025-12-26 10:00:42'),
(1248, 12, 68, '2025-12-26 10:12:34'),
(1250, 12, 68, '2025-12-26 10:12:45'),
(1251, 12, 68, '2025-12-26 10:12:46'),
(546, 12, 69, '2025-12-11 09:56:27'),
(1187, 12, 69, '2025-12-26 09:55:21'),
(1188, 12, 69, '2025-12-26 09:55:22'),
(1191, 12, 69, '2025-12-26 09:57:35'),
(1298, 12, 69, '2025-12-26 11:28:11'),
(1299, 12, 69, '2025-12-26 11:28:12'),
(411, 12, 70, '2025-12-09 05:44:09'),
(413, 12, 70, '2025-12-09 05:46:39'),
(414, 12, 70, '2025-12-09 05:48:21'),
(417, 12, 70, '2025-12-09 05:51:30'),
(1116, 12, 70, '2025-12-26 07:26:54'),
(1240, 12, 70, '2025-12-26 10:09:10'),
(1241, 12, 70, '2025-12-26 10:09:12'),
(1244, 12, 70, '2025-12-26 10:09:18'),
(1296, 12, 70, '2025-12-26 11:27:47'),
(410, 12, 71, '2025-12-09 05:43:53'),
(412, 12, 71, '2025-12-09 05:44:26'),
(415, 12, 71, '2025-12-09 05:49:22'),
(416, 12, 71, '2025-12-09 05:50:37'),
(418, 12, 71, '2025-12-09 05:52:07'),
(419, 12, 71, '2025-12-09 06:01:21'),
(506, 12, 71, '2025-12-09 08:37:17'),
(516, 12, 71, '2025-12-09 10:23:37'),
(517, 12, 71, '2025-12-09 10:24:43'),
(543, 12, 71, '2025-12-11 09:55:20'),
(549, 12, 71, '2025-12-11 10:03:22'),
(1120, 12, 71, '2025-12-26 07:27:44'),
(1121, 12, 71, '2025-12-26 07:27:45'),
(1185, 12, 71, '2025-12-26 09:55:09'),
(1237, 12, 71, '2025-12-26 10:08:51'),
(1239, 12, 71, '2025-12-26 10:08:52'),
(1282, 12, 71, '2025-12-26 10:20:38'),
(1283, 12, 71, '2025-12-26 10:20:39'),
(1114, 12, 72, '2025-12-26 07:24:59'),
(1115, 12, 72, '2025-12-26 07:25:00'),
(1118, 12, 72, '2025-12-26 07:27:28'),
(1124, 12, 72, '2025-12-26 07:28:30'),
(1125, 12, 72, '2025-12-26 07:28:31'),
(1177, 12, 72, '2025-12-26 09:52:29'),
(1178, 12, 72, '2025-12-26 09:52:31'),
(1180, 12, 72, '2025-12-26 09:52:41'),
(1183, 12, 72, '2025-12-26 09:55:03'),
(1246, 12, 72, '2025-12-26 10:11:15'),
(1252, 12, 72, '2025-12-26 10:12:59'),
(1253, 12, 72, '2025-12-26 10:13:00'),
(1254, 12, 72, '2025-12-26 10:14:19'),
(1255, 12, 72, '2025-12-26 10:14:21'),
(1256, 12, 72, '2025-12-26 10:14:22'),
(1257, 12, 72, '2025-12-26 10:14:24'),
(1284, 12, 72, '2025-12-26 10:21:06'),
(318, 33, 54, '2025-12-02 09:13:16'),
(317, 33, 55, '2025-12-02 09:13:00'),
(319, 33, 55, '2025-12-02 09:13:39'),
(316, 33, 56, '2025-12-02 09:12:54'),
(323, 33, 56, '2025-12-02 09:16:08'),
(315, 33, 57, '2025-12-02 09:12:43'),
(320, 33, 57, '2025-12-02 09:14:25'),
(321, 33, 57, '2025-12-02 09:15:33'),
(322, 33, 57, '2025-12-02 09:15:58'),
(324, 33, 57, '2025-12-02 09:16:18'),
(398, 33, 57, '2025-12-08 07:28:22'),
(399, 33, 57, '2025-12-08 07:29:12'),
(400, 33, 57, '2025-12-08 07:29:28'),
(403, 33, 57, '2025-12-08 07:30:26'),
(406, 33, 57, '2025-12-08 07:31:01'),
(401, 33, 58, '2025-12-08 07:29:33'),
(402, 33, 58, '2025-12-08 07:29:51'),
(404, 33, 58, '2025-12-08 07:30:40'),
(407, 33, 58, '2025-12-08 07:32:00'),
(405, 33, 69, '2025-12-08 07:30:52'),
(998, 35, 1, '2025-12-26 06:10:37'),
(1001, 35, 1, '2025-12-26 06:11:51'),
(1010, 35, 1, '2025-12-26 06:18:11'),
(1011, 35, 1, '2025-12-26 06:18:12'),
(1012, 35, 1, '2025-12-26 06:18:50'),
(1024, 35, 1, '2025-12-26 06:22:25'),
(1025, 35, 1, '2025-12-26 06:22:26'),
(1032, 35, 1, '2025-12-26 06:24:37'),
(1101, 35, 1, '2025-12-26 07:07:18'),
(1026, 35, 2, '2025-12-26 06:23:12'),
(1027, 35, 2, '2025-12-26 06:23:13'),
(1028, 35, 2, '2025-12-26 06:23:19'),
(1029, 35, 2, '2025-12-26 06:23:20'),
(585, 35, 3, '2025-12-19 09:53:05'),
(590, 35, 3, '2025-12-19 09:59:42'),
(591, 35, 3, '2025-12-19 10:00:14'),
(587, 35, 4, '2025-12-19 09:54:13'),
(510, 35, 5, '2025-12-09 08:53:44'),
(1056, 35, 5, '2025-12-26 06:38:08'),
(1058, 35, 5, '2025-12-26 06:38:09'),
(1041, 35, 6, '2025-12-26 06:37:52'),
(1043, 35, 6, '2025-12-26 06:37:54'),
(1045, 35, 6, '2025-12-26 06:37:56'),
(1062, 35, 6, '2025-12-26 06:43:25'),
(1063, 35, 6, '2025-12-26 06:43:26'),
(520, 35, 8, '2025-12-11 04:44:44'),
(1047, 35, 8, '2025-12-26 06:37:58'),
(1049, 35, 8, '2025-12-26 06:37:59'),
(1052, 35, 8, '2025-12-26 06:38:00'),
(519, 35, 26, '2025-12-11 04:44:26'),
(521, 35, 26, '2025-12-11 04:45:35'),
(999, 35, 26, '2025-12-26 06:11:24'),
(540, 35, 52, '2025-12-11 09:39:21'),
(559, 35, 52, '2025-12-19 07:15:25'),
(560, 35, 52, '2025-12-19 07:15:51'),
(561, 35, 52, '2025-12-19 07:17:14'),
(562, 35, 52, '2025-12-19 07:18:07'),
(563, 35, 52, '2025-12-19 07:18:19'),
(564, 35, 52, '2025-12-19 07:31:40'),
(565, 35, 52, '2025-12-19 07:32:12'),
(566, 35, 52, '2025-12-19 07:32:36'),
(567, 35, 52, '2025-12-19 07:49:28'),
(568, 35, 52, '2025-12-19 07:50:24'),
(569, 35, 52, '2025-12-19 07:50:37'),
(586, 35, 52, '2025-12-19 09:53:56'),
(997, 35, 52, '2025-12-26 06:10:20'),
(1301, 35, 52, '2025-12-26 11:31:07'),
(1302, 35, 52, '2025-12-26 11:32:10'),
(1304, 35, 52, '2025-12-26 11:32:25'),
(1305, 35, 52, '2025-12-26 11:42:12'),
(1306, 35, 52, '2025-12-26 11:42:30'),
(1307, 35, 52, '2025-12-26 11:42:50'),
(1014, 35, 55, '2025-12-26 06:20:31'),
(584, 35, 56, '2025-12-19 09:52:32'),
(1008, 35, 56, '2025-12-26 06:17:53'),
(514, 35, 57, '2025-12-09 08:55:28'),
(1030, 35, 58, '2025-12-26 06:24:28'),
(1034, 35, 58, '2025-12-26 06:28:27'),
(511, 35, 59, '2025-12-09 08:54:25'),
(512, 35, 59, '2025-12-09 08:54:58'),
(600, 35, 59, '2025-12-19 11:14:51'),
(682, 35, 59, '2025-12-23 09:11:36'),
(693, 35, 59, '2025-12-23 09:37:05'),
(708, 35, 59, '2025-12-23 09:51:24'),
(709, 35, 59, '2025-12-23 09:51:26'),
(710, 35, 59, '2025-12-23 09:51:27'),
(733, 35, 59, '2025-12-23 10:10:57'),
(1000, 35, 59, '2025-12-26 06:11:36'),
(1006, 35, 59, '2025-12-26 06:17:27'),
(1016, 35, 59, '2025-12-26 06:20:44'),
(1018, 35, 59, '2025-12-26 06:21:03'),
(1019, 35, 59, '2025-12-26 06:21:04'),
(1020, 35, 59, '2025-12-26 06:21:33'),
(1021, 35, 59, '2025-12-26 06:21:34'),
(1022, 35, 59, '2025-12-26 06:21:50'),
(712, 35, 60, '2025-12-23 09:51:28'),
(732, 35, 60, '2025-12-23 10:10:49'),
(734, 35, 60, '2025-12-23 10:11:29'),
(750, 35, 60, '2025-12-23 10:25:41'),
(795, 35, 60, '2025-12-24 06:47:50'),
(903, 35, 60, '2025-12-26 05:32:14'),
(969, 35, 60, '2025-12-26 05:51:52'),
(970, 35, 60, '2025-12-26 05:51:53'),
(991, 35, 60, '2025-12-26 06:08:58'),
(523, 35, 61, '2025-12-11 07:30:44'),
(524, 35, 61, '2025-12-11 07:39:52'),
(641, 35, 61, '2025-12-23 06:27:59'),
(715, 35, 61, '2025-12-23 09:51:32'),
(716, 35, 61, '2025-12-23 09:51:34'),
(785, 35, 61, '2025-12-24 06:47:01'),
(786, 35, 61, '2025-12-24 06:47:02'),
(793, 35, 61, '2025-12-24 06:47:43'),
(854, 35, 61, '2025-12-25 04:04:33'),
(855, 35, 61, '2025-12-25 04:04:34'),
(862, 35, 61, '2025-12-25 04:05:51'),
(915, 35, 61, '2025-12-26 05:44:39'),
(917, 35, 61, '2025-12-26 05:44:41'),
(919, 35, 61, '2025-12-26 05:44:42'),
(979, 35, 61, '2025-12-26 06:01:18'),
(980, 35, 61, '2025-12-26 06:01:19'),
(990, 35, 61, '2025-12-26 06:08:50'),
(556, 35, 62, '2025-12-19 06:20:41'),
(557, 35, 62, '2025-12-19 06:21:05'),
(558, 35, 62, '2025-12-19 06:30:17'),
(570, 35, 62, '2025-12-19 09:07:21'),
(571, 35, 62, '2025-12-19 09:07:22'),
(574, 35, 62, '2025-12-19 09:07:23'),
(636, 35, 62, '2025-12-23 06:22:03'),
(642, 35, 62, '2025-12-23 06:40:31'),
(645, 35, 62, '2025-12-23 06:43:49'),
(647, 35, 62, '2025-12-23 06:47:55'),
(649, 35, 62, '2025-12-23 06:50:40'),
(673, 35, 62, '2025-12-23 08:46:51'),
(695, 35, 62, '2025-12-23 09:37:16'),
(696, 35, 62, '2025-12-23 09:40:59'),
(697, 35, 62, '2025-12-23 09:41:04'),
(698, 35, 62, '2025-12-23 09:41:11'),
(699, 35, 62, '2025-12-23 09:41:34'),
(700, 35, 62, '2025-12-23 09:44:09'),
(706, 35, 62, '2025-12-23 09:49:25'),
(707, 35, 62, '2025-12-23 09:50:17'),
(742, 35, 62, '2025-12-23 10:15:03'),
(743, 35, 62, '2025-12-23 10:15:09'),
(776, 35, 62, '2025-12-24 06:30:36'),
(782, 35, 62, '2025-12-24 06:39:31'),
(856, 35, 62, '2025-12-25 04:04:51'),
(905, 35, 62, '2025-12-26 05:32:19'),
(913, 35, 62, '2025-12-26 05:34:27'),
(914, 35, 62, '2025-12-26 05:34:28'),
(952, 35, 62, '2025-12-26 05:47:58'),
(953, 35, 62, '2025-12-26 05:47:59'),
(954, 35, 62, '2025-12-26 05:48:32'),
(955, 35, 62, '2025-12-26 05:48:33'),
(956, 35, 62, '2025-12-26 05:48:34'),
(959, 35, 62, '2025-12-26 05:48:35'),
(963, 35, 62, '2025-12-26 05:51:20'),
(965, 35, 62, '2025-12-26 05:51:24'),
(992, 35, 62, '2025-12-26 06:09:06'),
(1060, 35, 62, '2025-12-26 06:43:08'),
(578, 35, 64, '2025-12-19 09:19:42'),
(634, 35, 64, '2025-12-23 06:19:44'),
(637, 35, 64, '2025-12-23 06:24:56'),
(648, 35, 64, '2025-12-23 06:50:20'),
(650, 35, 64, '2025-12-23 06:51:18'),
(651, 35, 64, '2025-12-23 06:52:07'),
(677, 35, 64, '2025-12-23 08:47:55'),
(694, 35, 64, '2025-12-23 09:37:11'),
(705, 35, 64, '2025-12-23 09:49:21'),
(744, 35, 64, '2025-12-23 10:18:25'),
(771, 35, 64, '2025-12-24 06:24:13'),
(799, 35, 64, '2025-12-24 06:48:01'),
(807, 35, 64, '2025-12-24 06:49:34'),
(808, 35, 64, '2025-12-24 06:49:35'),
(864, 35, 64, '2025-12-25 04:05:57'),
(870, 35, 64, '2025-12-26 03:46:21'),
(929, 35, 64, '2025-12-26 05:47:51'),
(931, 35, 64, '2025-12-26 05:47:52'),
(935, 35, 64, '2025-12-26 05:47:53'),
(940, 35, 64, '2025-12-26 05:47:54'),
(941, 35, 64, '2025-12-26 05:47:55'),
(947, 35, 64, '2025-12-26 05:47:56'),
(950, 35, 64, '2025-12-26 05:47:57'),
(951, 35, 64, '2025-12-26 05:47:58'),
(993, 35, 64, '2025-12-26 06:09:27'),
(739, 35, 65, '2025-12-23 10:13:44'),
(797, 35, 65, '2025-12-24 06:47:55'),
(889, 35, 65, '2025-12-26 03:56:00'),
(1242, 35, 65, '2025-12-26 10:09:16'),
(1243, 35, 65, '2025-12-26 10:09:17'),
(589, 35, 66, '2025-12-19 09:58:32'),
(675, 35, 66, '2025-12-23 08:47:35'),
(766, 35, 66, '2025-12-24 06:18:58'),
(770, 35, 66, '2025-12-24 06:24:05'),
(801, 35, 66, '2025-12-24 06:48:06'),
(802, 35, 66, '2025-12-24 06:48:07'),
(809, 35, 66, '2025-12-24 06:50:06'),
(810, 35, 66, '2025-12-24 06:50:07'),
(868, 35, 66, '2025-12-26 03:45:32'),
(529, 35, 67, '2025-12-11 09:29:56'),
(577, 35, 67, '2025-12-19 09:19:25'),
(621, 35, 67, '2025-12-23 05:51:33'),
(622, 35, 67, '2025-12-23 05:51:41'),
(623, 35, 67, '2025-12-23 05:58:09'),
(628, 35, 67, '2025-12-23 06:10:48'),
(629, 35, 67, '2025-12-23 06:10:56'),
(635, 35, 67, '2025-12-23 06:20:21'),
(672, 35, 67, '2025-12-23 08:41:01'),
(760, 35, 67, '2025-12-24 06:17:38'),
(763, 35, 67, '2025-12-24 06:18:22'),
(764, 35, 67, '2025-12-24 06:18:38'),
(765, 35, 67, '2025-12-24 06:18:45'),
(803, 35, 67, '2025-12-24 06:48:11'),
(848, 35, 67, '2025-12-24 07:23:36'),
(927, 35, 67, '2025-12-26 05:46:14'),
(1199, 35, 67, '2025-12-26 09:58:06'),
(1235, 35, 67, '2025-12-26 10:08:27'),
(1290, 35, 67, '2025-12-26 10:21:51'),
(392, 35, 68, '2025-12-04 08:04:40'),
(393, 35, 68, '2025-12-04 09:49:05'),
(394, 35, 68, '2025-12-04 09:49:52'),
(395, 35, 68, '2025-12-04 09:50:35'),
(527, 35, 68, '2025-12-11 09:29:43'),
(528, 35, 68, '2025-12-11 09:29:51'),
(530, 35, 68, '2025-12-11 09:30:03'),
(555, 35, 68, '2025-12-11 11:15:49'),
(579, 35, 68, '2025-12-19 09:19:50'),
(593, 35, 68, '2025-12-19 10:25:51'),
(601, 35, 68, '2025-12-19 11:17:03'),
(602, 35, 68, '2025-12-19 11:17:12'),
(608, 35, 68, '2025-12-23 05:25:32'),
(616, 35, 68, '2025-12-23 05:40:25'),
(638, 35, 68, '2025-12-23 06:25:19'),
(654, 35, 68, '2025-12-23 07:30:47'),
(659, 35, 68, '2025-12-23 07:37:41'),
(660, 35, 68, '2025-12-23 07:39:21'),
(665, 35, 68, '2025-12-23 07:50:32'),
(674, 35, 68, '2025-12-23 08:47:31'),
(701, 35, 68, '2025-12-23 09:45:50'),
(703, 35, 68, '2025-12-23 09:46:56'),
(735, 35, 68, '2025-12-23 10:13:43'),
(736, 35, 68, '2025-12-23 10:13:44'),
(745, 35, 68, '2025-12-23 10:18:30'),
(758, 35, 68, '2025-12-24 06:14:27'),
(769, 35, 68, '2025-12-24 06:24:00'),
(812, 35, 68, '2025-12-24 06:51:02'),
(825, 35, 68, '2025-12-24 06:55:11'),
(826, 35, 68, '2025-12-24 06:55:12'),
(866, 35, 68, '2025-12-25 04:06:05'),
(867, 35, 68, '2025-12-25 04:06:06'),
(872, 35, 68, '2025-12-26 03:48:05'),
(873, 35, 68, '2025-12-26 03:48:06'),
(879, 35, 68, '2025-12-26 03:55:54'),
(881, 35, 68, '2025-12-26 03:55:55'),
(894, 35, 68, '2025-12-26 04:01:25'),
(977, 35, 68, '2025-12-26 05:54:33'),
(978, 35, 68, '2025-12-26 05:54:34'),
(995, 35, 68, '2025-12-26 06:09:41'),
(1286, 35, 68, '2025-12-26 10:21:28'),
(1287, 35, 68, '2025-12-26 10:21:29'),
(1292, 35, 68, '2025-12-26 10:22:00'),
(580, 35, 69, '2025-12-19 09:20:02'),
(581, 35, 69, '2025-12-19 09:20:04'),
(582, 35, 69, '2025-12-19 09:20:06'),
(583, 35, 69, '2025-12-19 09:20:07'),
(592, 35, 69, '2025-12-19 10:25:17'),
(607, 35, 69, '2025-12-23 05:24:59'),
(609, 35, 69, '2025-12-23 05:26:31'),
(610, 35, 69, '2025-12-23 05:27:14'),
(611, 35, 69, '2025-12-23 05:27:50'),
(617, 35, 69, '2025-12-23 05:42:36'),
(618, 35, 69, '2025-12-23 05:42:42'),
(620, 35, 69, '2025-12-23 05:49:53'),
(625, 35, 69, '2025-12-23 06:10:27'),
(626, 35, 69, '2025-12-23 06:10:34'),
(627, 35, 69, '2025-12-23 06:10:42'),
(632, 35, 69, '2025-12-23 06:17:32'),
(643, 35, 69, '2025-12-23 06:40:47'),
(644, 35, 69, '2025-12-23 06:43:30'),
(662, 35, 69, '2025-12-23 07:45:33'),
(663, 35, 69, '2025-12-23 07:46:53'),
(666, 35, 69, '2025-12-23 08:28:26'),
(667, 35, 69, '2025-12-23 08:32:54'),
(671, 35, 69, '2025-12-23 08:40:39'),
(678, 35, 69, '2025-12-23 08:50:36'),
(680, 35, 69, '2025-12-23 09:10:45'),
(687, 35, 69, '2025-12-23 09:29:26'),
(691, 35, 69, '2025-12-23 09:34:55'),
(692, 35, 69, '2025-12-23 09:35:48'),
(717, 35, 69, '2025-12-23 09:51:49'),
(728, 35, 69, '2025-12-23 10:07:21'),
(731, 35, 69, '2025-12-23 10:10:36'),
(752, 35, 69, '2025-12-23 10:25:54'),
(777, 35, 69, '2025-12-24 06:31:43'),
(787, 35, 69, '2025-12-24 06:47:16'),
(811, 35, 69, '2025-12-24 06:50:47'),
(814, 35, 69, '2025-12-24 06:51:15'),
(815, 35, 69, '2025-12-24 06:51:29'),
(819, 35, 69, '2025-12-24 06:52:09'),
(820, 35, 69, '2025-12-24 06:52:10'),
(874, 35, 69, '2025-12-26 03:55:48'),
(875, 35, 69, '2025-12-26 03:55:50'),
(891, 35, 69, '2025-12-26 04:01:15'),
(892, 35, 69, '2025-12-26 04:01:17'),
(897, 35, 69, '2025-12-26 04:05:53'),
(899, 35, 69, '2025-12-26 05:12:39'),
(911, 35, 69, '2025-12-26 05:33:16'),
(912, 35, 69, '2025-12-26 05:33:17'),
(981, 35, 69, '2025-12-26 06:02:50'),
(984, 35, 69, '2025-12-26 06:03:25'),
(985, 35, 69, '2025-12-26 06:03:47'),
(986, 35, 69, '2025-12-26 06:04:53'),
(994, 35, 69, '2025-12-26 06:09:34'),
(1002, 35, 69, '2025-12-26 06:12:00'),
(1036, 35, 69, '2025-12-26 06:28:40'),
(1064, 35, 69, '2025-12-26 06:55:11'),
(1066, 35, 69, '2025-12-26 06:55:12'),
(1067, 35, 69, '2025-12-26 06:55:13'),
(1075, 35, 69, '2025-12-26 06:55:14'),
(1077, 35, 69, '2025-12-26 06:55:20'),
(1078, 35, 69, '2025-12-26 06:55:21'),
(1163, 35, 69, '2025-12-26 09:25:54'),
(1167, 35, 69, '2025-12-26 09:26:33'),
(1231, 35, 69, '2025-12-26 10:08:13'),
(459, 35, 70, '2025-12-09 07:39:03'),
(518, 35, 70, '2025-12-09 11:31:54'),
(525, 35, 70, '2025-12-11 08:05:06'),
(538, 35, 70, '2025-12-11 09:38:51'),
(552, 35, 70, '2025-12-11 11:08:09'),
(576, 35, 70, '2025-12-19 09:07:45'),
(594, 35, 70, '2025-12-19 10:36:41'),
(605, 35, 70, '2025-12-23 04:58:29'),
(612, 35, 70, '2025-12-23 05:37:11'),
(613, 35, 70, '2025-12-23 05:37:35'),
(614, 35, 70, '2025-12-23 05:37:41'),
(619, 35, 70, '2025-12-23 05:49:47'),
(633, 35, 70, '2025-12-23 06:18:40'),
(639, 35, 70, '2025-12-23 06:25:45'),
(640, 35, 70, '2025-12-23 06:26:21'),
(646, 35, 70, '2025-12-23 06:47:43'),
(652, 35, 70, '2025-12-23 07:26:53'),
(653, 35, 70, '2025-12-23 07:30:33'),
(655, 35, 70, '2025-12-23 07:31:05'),
(658, 35, 70, '2025-12-23 07:36:46'),
(661, 35, 70, '2025-12-23 07:39:35'),
(664, 35, 70, '2025-12-23 07:47:22'),
(681, 35, 70, '2025-12-23 09:11:00'),
(688, 35, 70, '2025-12-23 09:31:17'),
(689, 35, 70, '2025-12-23 09:33:29'),
(690, 35, 70, '2025-12-23 09:33:56'),
(702, 35, 70, '2025-12-23 09:46:05'),
(704, 35, 70, '2025-12-23 09:47:14'),
(720, 35, 70, '2025-12-23 09:55:38'),
(722, 35, 70, '2025-12-23 09:59:39'),
(723, 35, 70, '2025-12-23 09:59:47'),
(724, 35, 70, '2025-12-23 10:02:10'),
(725, 35, 70, '2025-12-23 10:02:31'),
(726, 35, 70, '2025-12-23 10:02:59'),
(727, 35, 70, '2025-12-23 10:03:11'),
(729, 35, 70, '2025-12-23 10:07:37'),
(730, 35, 70, '2025-12-23 10:10:16'),
(746, 35, 70, '2025-12-23 10:18:35'),
(747, 35, 70, '2025-12-23 10:18:39'),
(749, 35, 70, '2025-12-23 10:25:27'),
(754, 35, 70, '2025-12-23 10:31:04'),
(757, 35, 70, '2025-12-24 06:14:15'),
(762, 35, 70, '2025-12-24 06:18:09'),
(768, 35, 70, '2025-12-24 06:23:49'),
(774, 35, 70, '2025-12-24 06:28:58'),
(784, 35, 70, '2025-12-24 06:40:14'),
(823, 35, 70, '2025-12-24 06:53:59'),
(840, 35, 70, '2025-12-24 07:14:52'),
(844, 35, 70, '2025-12-24 07:23:17'),
(846, 35, 70, '2025-12-24 07:23:24'),
(847, 35, 70, '2025-12-24 07:23:25'),
(858, 35, 70, '2025-12-25 04:05:12'),
(859, 35, 70, '2025-12-25 04:05:13'),
(883, 35, 70, '2025-12-26 03:55:56'),
(925, 35, 70, '2025-12-26 05:46:06'),
(973, 35, 70, '2025-12-26 05:52:57'),
(983, 35, 70, '2025-12-26 06:02:57'),
(987, 35, 70, '2025-12-26 06:06:14'),
(988, 35, 70, '2025-12-26 06:07:12'),
(996, 35, 70, '2025-12-26 06:10:00'),
(1038, 35, 70, '2025-12-26 06:30:00'),
(1079, 35, 70, '2025-12-26 06:56:22'),
(1081, 35, 70, '2025-12-26 06:56:24'),
(1083, 35, 70, '2025-12-26 06:56:25'),
(1086, 35, 70, '2025-12-26 06:56:45'),
(1087, 35, 70, '2025-12-26 06:56:47'),
(1088, 35, 70, '2025-12-26 06:56:48'),
(1089, 35, 70, '2025-12-26 06:56:49'),
(1097, 35, 70, '2025-12-26 06:57:58'),
(1099, 35, 70, '2025-12-26 07:06:40'),
(1111, 35, 70, '2025-12-26 07:21:36'),
(1155, 35, 70, '2025-12-26 07:37:09'),
(1161, 35, 70, '2025-12-26 09:25:32'),
(1162, 35, 70, '2025-12-26 09:25:33'),
(1165, 35, 70, '2025-12-26 09:25:58'),
(1169, 35, 70, '2025-12-26 09:26:42'),
(1170, 35, 70, '2025-12-26 09:26:43'),
(1201, 35, 70, '2025-12-26 09:58:19'),
(1202, 35, 70, '2025-12-26 09:58:20'),
(1227, 35, 70, '2025-12-26 10:07:31'),
(1228, 35, 70, '2025-12-26 10:07:33'),
(1229, 35, 70, '2025-12-26 10:08:06'),
(1230, 35, 70, '2025-12-26 10:08:07'),
(1308, 35, 70, '2025-12-26 11:54:07'),
(1309, 35, 70, '2025-12-26 11:54:08'),
(457, 35, 71, '2025-12-09 07:37:48'),
(458, 35, 71, '2025-12-09 07:38:35'),
(531, 35, 71, '2025-12-11 09:31:56'),
(532, 35, 71, '2025-12-11 09:32:18'),
(533, 35, 71, '2025-12-11 09:32:43'),
(534, 35, 71, '2025-12-11 09:32:46'),
(535, 35, 71, '2025-12-11 09:32:56'),
(536, 35, 71, '2025-12-11 09:33:09'),
(537, 35, 71, '2025-12-11 09:38:43'),
(539, 35, 71, '2025-12-11 09:39:01'),
(541, 35, 71, '2025-12-11 09:39:31'),
(542, 35, 71, '2025-12-11 09:39:59'),
(551, 35, 71, '2025-12-11 11:08:02'),
(553, 35, 71, '2025-12-11 11:08:22'),
(554, 35, 71, '2025-12-11 11:15:41'),
(588, 35, 71, '2025-12-19 09:57:41'),
(595, 35, 71, '2025-12-19 10:48:07'),
(596, 35, 71, '2025-12-19 10:48:21'),
(597, 35, 71, '2025-12-19 10:48:46'),
(598, 35, 71, '2025-12-19 10:51:45'),
(599, 35, 71, '2025-12-19 11:10:27'),
(603, 35, 71, '2025-12-19 11:21:47'),
(604, 35, 71, '2025-12-19 11:22:05'),
(606, 35, 71, '2025-12-23 05:21:10'),
(615, 35, 71, '2025-12-23 05:40:03'),
(624, 35, 71, '2025-12-23 05:58:27'),
(630, 35, 71, '2025-12-23 06:11:11'),
(631, 35, 71, '2025-12-23 06:11:17'),
(656, 35, 71, '2025-12-23 07:35:55'),
(657, 35, 71, '2025-12-23 07:36:21'),
(668, 35, 71, '2025-12-23 08:33:17'),
(669, 35, 71, '2025-12-23 08:37:59'),
(670, 35, 71, '2025-12-23 08:39:37'),
(676, 35, 71, '2025-12-23 08:47:49'),
(679, 35, 71, '2025-12-23 08:51:02'),
(683, 35, 71, '2025-12-23 09:14:25'),
(684, 35, 71, '2025-12-23 09:14:57'),
(685, 35, 71, '2025-12-23 09:16:18'),
(686, 35, 71, '2025-12-23 09:16:19'),
(718, 35, 71, '2025-12-23 09:52:13'),
(719, 35, 71, '2025-12-23 09:55:21'),
(721, 35, 71, '2025-12-23 09:59:22'),
(741, 35, 71, '2025-12-23 10:14:13'),
(748, 35, 71, '2025-12-23 10:18:46'),
(751, 35, 71, '2025-12-23 10:25:46'),
(753, 35, 71, '2025-12-23 10:26:03'),
(756, 35, 71, '2025-12-24 06:12:01'),
(759, 35, 71, '2025-12-24 06:17:29'),
(775, 35, 71, '2025-12-24 06:30:05'),
(778, 35, 71, '2025-12-24 06:31:59'),
(779, 35, 71, '2025-12-24 06:32:32'),
(780, 35, 71, '2025-12-24 06:33:08'),
(781, 35, 71, '2025-12-24 06:33:16'),
(791, 35, 71, '2025-12-24 06:47:25'),
(805, 35, 71, '2025-12-24 06:49:06'),
(827, 35, 71, '2025-12-24 06:55:38'),
(833, 35, 71, '2025-12-24 07:12:24'),
(834, 35, 71, '2025-12-24 07:12:25'),
(837, 35, 71, '2025-12-24 07:13:11'),
(838, 35, 71, '2025-12-24 07:13:12'),
(850, 35, 71, '2025-12-24 07:25:22'),
(885, 35, 71, '2025-12-26 03:55:57'),
(895, 35, 71, '2025-12-26 04:02:10'),
(907, 35, 71, '2025-12-26 05:32:33'),
(989, 35, 71, '2025-12-26 06:08:00'),
(1090, 35, 71, '2025-12-26 06:57:22'),
(1092, 35, 71, '2025-12-26 06:57:23'),
(1094, 35, 71, '2025-12-26 06:57:24'),
(1096, 35, 71, '2025-12-26 06:57:27'),
(1107, 35, 71, '2025-12-26 07:17:05'),
(1108, 35, 71, '2025-12-26 07:17:06'),
(1148, 35, 71, '2025-12-26 07:35:24'),
(1150, 35, 71, '2025-12-26 07:36:19'),
(1152, 35, 71, '2025-12-26 07:36:20'),
(1157, 35, 71, '2025-12-26 07:38:22'),
(1159, 35, 71, '2025-12-26 07:46:34'),
(1160, 35, 71, '2025-12-26 07:46:35'),
(1189, 35, 71, '2025-12-26 09:56:53'),
(1197, 35, 71, '2025-12-26 09:57:57'),
(1198, 35, 71, '2025-12-26 09:57:58'),
(1203, 35, 71, '2025-12-26 09:58:58'),
(1204, 35, 71, '2025-12-26 09:58:59'),
(1205, 35, 71, '2025-12-26 09:59:13'),
(1206, 35, 71, '2025-12-26 09:59:14'),
(1207, 35, 71, '2025-12-26 09:59:29'),
(1294, 35, 71, '2025-12-26 10:22:37'),
(755, 35, 72, '2025-12-24 06:11:53'),
(761, 35, 72, '2025-12-24 06:17:51'),
(767, 35, 72, '2025-12-24 06:19:29'),
(772, 35, 72, '2025-12-24 06:25:05'),
(773, 35, 72, '2025-12-24 06:25:57'),
(783, 35, 72, '2025-12-24 06:40:09'),
(789, 35, 72, '2025-12-24 06:47:21'),
(790, 35, 72, '2025-12-24 06:47:22'),
(817, 35, 72, '2025-12-24 06:51:47'),
(821, 35, 72, '2025-12-24 06:53:03'),
(822, 35, 72, '2025-12-24 06:53:04'),
(829, 35, 72, '2025-12-24 07:01:43'),
(831, 35, 72, '2025-12-24 07:05:47'),
(835, 35, 72, '2025-12-24 07:12:48'),
(839, 35, 72, '2025-12-24 07:13:38'),
(842, 35, 72, '2025-12-24 07:15:04'),
(860, 35, 72, '2025-12-25 04:05:20'),
(887, 35, 72, '2025-12-26 03:55:58'),
(901, 35, 72, '2025-12-26 05:32:04'),
(909, 35, 72, '2025-12-26 05:33:06'),
(910, 35, 72, '2025-12-26 05:33:07'),
(967, 35, 72, '2025-12-26 05:51:36'),
(968, 35, 72, '2025-12-26 05:51:37'),
(971, 35, 72, '2025-12-26 05:52:07'),
(972, 35, 72, '2025-12-26 05:52:08'),
(975, 35, 72, '2025-12-26 05:54:20'),
(976, 35, 72, '2025-12-26 05:54:21'),
(1003, 35, 72, '2025-12-26 06:15:19'),
(1004, 35, 72, '2025-12-26 06:17:05'),
(1103, 35, 72, '2025-12-26 07:08:16'),
(1104, 35, 72, '2025-12-26 07:08:17'),
(1105, 35, 72, '2025-12-26 07:12:10'),
(1106, 35, 72, '2025-12-26 07:12:11'),
(1109, 35, 72, '2025-12-26 07:17:24'),
(1112, 35, 72, '2025-12-26 07:21:53'),
(1113, 35, 72, '2025-12-26 07:21:54'),
(1126, 35, 72, '2025-12-26 07:28:53'),
(1127, 35, 72, '2025-12-26 07:28:54'),
(1128, 35, 72, '2025-12-26 07:29:17'),
(1129, 35, 72, '2025-12-26 07:29:18'),
(1130, 35, 72, '2025-12-26 07:30:11'),
(1132, 35, 72, '2025-12-26 07:30:31'),
(1134, 35, 72, '2025-12-26 07:30:55'),
(1136, 35, 72, '2025-12-26 07:31:24'),
(1138, 35, 72, '2025-12-26 07:31:56'),
(1139, 35, 72, '2025-12-26 07:31:57'),
(1140, 35, 72, '2025-12-26 07:32:37'),
(1141, 35, 72, '2025-12-26 07:32:38'),
(1142, 35, 72, '2025-12-26 07:33:26'),
(1143, 35, 72, '2025-12-26 07:33:27'),
(1144, 35, 72, '2025-12-26 07:34:21'),
(1145, 35, 72, '2025-12-26 07:34:22'),
(1146, 35, 72, '2025-12-26 07:34:35'),
(1171, 35, 72, '2025-12-26 09:37:12'),
(1172, 35, 72, '2025-12-26 09:37:14'),
(1173, 35, 72, '2025-12-26 09:37:15'),
(1174, 35, 72, '2025-12-26 09:37:16'),
(1195, 35, 72, '2025-12-26 09:57:49'),
(1196, 35, 72, '2025-12-26 09:57:50'),
(1270, 35, 72, '2025-12-26 10:16:34'),
(1271, 35, 72, '2025-12-26 10:16:35'),
(1288, 35, 72, '2025-12-26 10:21:41'),
(332, 36, 55, '2025-12-02 10:23:52'),
(331, 36, 56, '2025-12-02 10:23:44'),
(334, 36, 56, '2025-12-02 10:24:14'),
(329, 36, 57, '2025-12-02 10:22:59'),
(330, 36, 57, '2025-12-02 10:23:29'),
(333, 36, 57, '2025-12-02 10:24:01'),
(326, 36, 58, '2025-12-02 10:15:54'),
(328, 36, 58, '2025-12-02 10:16:58'),
(325, 36, 59, '2025-12-02 10:14:14'),
(327, 36, 59, '2025-12-02 10:16:14'),
(335, 37, 63, '2025-12-02 15:23:45'),
(336, 37, 65, '2025-12-02 16:50:35'),
(337, 37, 65, '2025-12-02 16:51:25'),
(338, 37, 65, '2025-12-02 16:51:48'),
(339, 37, 65, '2025-12-02 16:53:16'),
(340, 37, 65, '2025-12-02 16:54:07'),
(341, 37, 65, '2025-12-02 16:55:38'),
(353, 37, 65, '2025-12-02 17:45:48'),
(358, 37, 65, '2025-12-02 17:52:00'),
(361, 37, 65, '2025-12-02 17:53:09'),
(364, 37, 65, '2025-12-02 17:53:26'),
(342, 37, 66, '2025-12-02 17:11:11'),
(343, 37, 66, '2025-12-02 17:21:16'),
(344, 37, 66, '2025-12-02 17:22:14'),
(345, 37, 66, '2025-12-02 17:22:43'),
(346, 37, 66, '2025-12-02 17:26:22'),
(347, 37, 66, '2025-12-02 17:31:33'),
(348, 37, 66, '2025-12-02 17:35:30'),
(349, 37, 66, '2025-12-02 17:36:46'),
(350, 37, 66, '2025-12-02 17:38:09'),
(351, 37, 66, '2025-12-02 17:40:25'),
(352, 37, 66, '2025-12-02 17:43:14'),
(354, 37, 66, '2025-12-02 17:45:59'),
(355, 37, 66, '2025-12-02 17:46:43'),
(356, 37, 66, '2025-12-02 17:50:38'),
(357, 37, 66, '2025-12-02 17:51:40'),
(359, 37, 66, '2025-12-02 17:52:11'),
(360, 37, 66, '2025-12-02 17:52:55'),
(362, 37, 66, '2025-12-02 17:53:13'),
(363, 37, 66, '2025-12-02 17:53:23'),
(365, 37, 67, '2025-12-02 18:17:32'),
(366, 37, 67, '2025-12-02 18:22:46'),
(367, 37, 67, '2025-12-02 18:23:39'),
(368, 37, 67, '2025-12-02 18:24:05'),
(369, 37, 67, '2025-12-02 18:25:52'),
(370, 37, 67, '2025-12-02 18:35:14'),
(377, 37, 67, '2025-12-04 05:54:21'),
(379, 37, 67, '2025-12-04 05:57:17'),
(390, 37, 67, '2025-12-04 06:53:07'),
(391, 37, 68, '2025-12-04 07:04:10'),
(371, 38, 67, '2025-12-04 03:47:24'),
(372, 38, 67, '2025-12-04 04:43:51'),
(373, 38, 68, '2025-12-04 04:53:58'),
(396, 39, 70, '2025-12-04 10:29:04'),
(409, 41, 70, '2025-12-08 08:25:55'),
(408, 41, 71, '2025-12-08 08:25:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `additional_photos`
--
ALTER TABLE `additional_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `approved_ips`
--
ALTER TABLE `approved_ips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip` (`ip`);

--
-- Indexes for table `approved_sessions`
--
ALTER TABLE `approved_sessions`
  ADD PRIMARY KEY (`session_id`);

--
-- Indexes for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip` (`ip`);

--
-- Indexes for table `blog`
--
ALTER TABLE `blog`
  ADD PRIMARY KEY (`blog_id`),
  ADD KEY `idx_author` (`author_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_publish_date` (`publish_date`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_branch_name` (`branch_name`);

--
-- Indexes for table `calls`
--
ALTER TABLE `calls`
  ADD PRIMARY KEY (`call_id`),
  ADD KEY `idx_call_phone` (`call_phone`),
  ADD KEY `idx_call_date` (`call_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_caller_id` (`caller_id`);

--
-- Indexes for table `company_details`
--
ALTER TABLE `company_details`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `education`
--
ALTER TABLE `education`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_education_member_id` (`member_id`);

--
-- Indexes for table `family`
--
ALTER TABLE `family`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_family_member_id` (`member_id`);

--
-- Indexes for table `horoscope`
--
ALTER TABLE `horoscope`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_horoscope_member_id` (`member_id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_members_user_id` (`user_id`),
  ADD KEY `fk_members_branch` (`branch_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_message_type_time` (`message_type`,`sent_time`),
  ADD KEY `idx_sender_email` (`sender_email`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`package_id`),
  ADD UNIQUE KEY `unique_package_name` (`name`);

--
-- Indexes for table `package_requests`
--
ALTER TABLE `package_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `package_id` (`package_id`);

--
-- Indexes for table `partner_expectations`
--
ALTER TABLE `partner_expectations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_partner_expectations_member_id` (`member_id`);

--
-- Indexes for table `physical_info`
--
ALTER TABLE `physical_info`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_physical_info_member_id` (`member_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`);

--
-- Indexes for table `search_queries`
--
ALTER TABLE `search_queries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_staff_branch` (`branch_id`);

--
-- Indexes for table `staff_logins`
--
ALTER TABLE `staff_logins`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff_login_requests`
--
ALTER TABLE `staff_login_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `userpackage`
--
ALTER TABLE `userpackage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_package_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_daily_interest_counts`
--
ALTER TABLE `user_daily_interest_counts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`interest_date`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_date` (`interest_date`);

--
-- Indexes for table `user_daily_profile_views`
--
ALTER TABLE `user_daily_profile_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`view_date`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_view_date` (`view_date`);

--
-- Indexes for table `user_interests`
--
ALTER TABLE `user_interests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_interest` (`user_id`,`target_member_id`),
  ADD KEY `idx_user_date` (`user_id`,`interest_date`),
  ADD KEY `idx_target_member` (`target_member_id`);

--
-- Indexes for table `user_interest_events`
--
ALTER TABLE `user_interest_events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_profile_views`
--
ALTER TABLE `user_profile_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_view` (`viewer_id`,`viewed_member_id`,`viewed_at`),
  ADD KEY `idx_viewer_id` (`viewer_id`),
  ADD KEY `idx_viewed_member_id` (`viewed_member_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `additional_photos`
--
ALTER TABLE `additional_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `approved_ips`
--
ALTER TABLE `approved_ips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `blog`
--
ALTER TABLE `blog`
  MODIFY `blog_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `calls`
--
ALTER TABLE `calls`
  MODIFY `call_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `company_details`
--
ALTER TABLE `company_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `education`
--
ALTER TABLE `education`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=233;

--
-- AUTO_INCREMENT for table `family`
--
ALTER TABLE `family`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `horoscope`
--
ALTER TABLE `horoscope`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `package_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `package_requests`
--
ALTER TABLE `package_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `partner_expectations`
--
ALTER TABLE `partner_expectations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `physical_info`
--
ALTER TABLE `physical_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `search_queries`
--
ALTER TABLE `search_queries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `staff_logins`
--
ALTER TABLE `staff_logins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_login_requests`
--
ALTER TABLE `staff_login_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `userpackage`
--
ALTER TABLE `userpackage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `user_daily_interest_counts`
--
ALTER TABLE `user_daily_interest_counts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=256;

--
-- AUTO_INCREMENT for table `user_daily_profile_views`
--
ALTER TABLE `user_daily_profile_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1120;

--
-- AUTO_INCREMENT for table `user_interests`
--
ALTER TABLE `user_interests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=275;

--
-- AUTO_INCREMENT for table `user_interest_events`
--
ALTER TABLE `user_interest_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT for table `user_profile_views`
--
ALTER TABLE `user_profile_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1311;

-- --------------------------------------------------------

--
-- Structure for view `user_interest_summary`
--
DROP TABLE IF EXISTS `user_interest_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`thennilavu`@`localhost` SQL SECURITY DEFINER VIEW `user_interest_summary`  AS SELECT `u`.`id` AS `user_id`, `u`.`username` AS `username`, coalesce(`dic`.`likes_count`,0) AS `today_likes_count`, coalesce(`p`.`interest_limit`,'5') AS `interest_limit`, coalesce(`p`.`name`,'Free User') AS `package_name`, CASE WHEN coalesce(`p`.`interest_limit`,'5') = 'Unlimited' THEN 'No limit' WHEN coalesce(`dic`.`likes_count`,0) >= cast(coalesce(`p`.`interest_limit`,'5') as unsigned) THEN 'Limit reached' ELSE concat(coalesce(`dic`.`likes_count`,0),'/',coalesce(`p`.`interest_limit`,'5')) END AS `interest_status` FROM (((`users` `u` left join `userpackage` `up` on(`u`.`id` = `up`.`user_id` and `up`.`requestPackage` = 'accept' and `up`.`end_date` > current_timestamp())) left join `packages` `p` on(`up`.`status` = `p`.`name`)) left join `user_daily_interest_counts` `dic` on(`u`.`id` = `dic`.`user_id` and `dic`.`interest_date` = curdate())) ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `education`
--
ALTER TABLE `education`
  ADD CONSTRAINT `fk_education_member_id` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `family`
--
ALTER TABLE `family`
  ADD CONSTRAINT `fk_family_member_id` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `horoscope`
--
ALTER TABLE `horoscope`
  ADD CONSTRAINT `fk_horoscope_member_id` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `fk_members_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `fk_members_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `package_requests`
--
ALTER TABLE `package_requests`
  ADD CONSTRAINT `package_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `package_requests_ibfk_2` FOREIGN KEY (`package_id`) REFERENCES `packages` (`package_id`);

--
-- Constraints for table `partner_expectations`
--
ALTER TABLE `partner_expectations`
  ADD CONSTRAINT `fk_partner_expectations_member_id` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `physical_info`
--
ALTER TABLE `physical_info`
  ADD CONSTRAINT `fk_physical_info_member_id` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `fk_staff_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`);

--
-- Constraints for table `userpackage`
--
ALTER TABLE `userpackage`
  ADD CONSTRAINT `fk_user_package_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_daily_interest_counts`
--
ALTER TABLE `user_daily_interest_counts`
  ADD CONSTRAINT `user_daily_interest_counts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_daily_profile_views`
--
ALTER TABLE `user_daily_profile_views`
  ADD CONSTRAINT `fk_user_daily_profile_views_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_interests`
--
ALTER TABLE `user_interests`
  ADD CONSTRAINT `user_interests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_interests_ibfk_2` FOREIGN KEY (`target_member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_profile_views`
--
ALTER TABLE `user_profile_views`
  ADD CONSTRAINT `fk_user_profile_views_viewed_member_id` FOREIGN KEY (`viewed_member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_profile_views_viewer_id` FOREIGN KEY (`viewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
