-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 30, 2025 at 04:58 PM
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
-- Database: `matrimony`
--

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
(1, '10 Tips for a Successful First Meeting', 'Planning your first meeting with a potential partner? Here are 10 essential tips to make it successful...', 1, 'Admin Team', 'uploads/author1.jpg', 'Dating Tips', 'published', '2025-08-28', '2025-08-27 10:33:43', '2025-08-27 10:33:43'),
(2, 'Understanding Horoscope Matching', 'Learn how horoscope matching works in traditional marriages and its significance...', 1, 'Admin Team', 'uploads/author1.jpg', 'Horoscope', 'published', '2025-09-15', '2025-09-09 10:33:46', '2025-09-09 10:33:46'),
(3, 'Hello World', 'huwehwbe hwefbhwfbe wejbhwedbhwehbwed wedihweidhbweidwed ediuwdhuieidweihudiued wejfweiduwei edidwhdwiueh wekhwdhuiwhuied', 23, 'Thanu', 'uploads/68d4fadfb7ce8.jpg', 'Communication', 'published', '2025-10-02', '2025-09-25 08:18:39', '2025-09-25 08:18:39');

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
(1, NULL, NULL, 'lingajan', '0755350101', '2025-09-26 13:33:00', 10, 'Scheduled', 'zcbhfgvbn'),
(2, NULL, NULL, 'lingajan', '12345678', '2025-09-26 13:33:00', 20, 'Scheduled', 'sdfgbgxfcb'),
(3, NULL, NULL, 'hmvhjk', '545465', '2025-09-26 16:38:00', 10, 'Completed', 'xfdyhj');

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
(1, 1, 'Higher', 'University of Colombo', 'BSc', 'Computer Science', 'CS12345', 2015, 2019, 'First'),
(2, 1, 'A/L', 'Jaffna Hindu College', 'Science', 'Physical Science', 'AL98765', 2012, 2014, '3A'),
(3, 2, 'Higher', 'University of Moratuwa', 'BEng', 'Electrical Engineering', 'EE54321', 2014, 2018, 'First'),
(4, 3, 'Higher', 'Medical College', 'MBBS', 'Medicine', 'MED1234', 2010, 2016, 'First'),
(5, 4, 'Higher', 'University of Peradeniya', 'BEd', 'Education', 'EDU5678', 2010, 2014, 'Second'),
(6, 10, 'Higher', 'Sliit', 'it', 'it', '1234', 2025, 2027, NULL),
(7, 12, 'Higher', 'AAAAAAAAAAAA@@', 'dfaaaaaaaaaa', '33', 'dsaffff', 4, 3333, NULL),
(8, 13, 'Higher', 'AAAAAAAAAAAA!!!', 'asd', '`12', 'sda', 12, 21, NULL),
(9, 13, 'Higher', '12', '12', '12', '12', 12, 12, NULL),
(10, 13, 'Higher', '34', '34', '34', '34', 34, 34, NULL),
(11, 14, 'Higher', '123', '213', '123', '213', 213, 123, NULL),
(18, 22, 'Higher', '2342', '234', '234', '234', 234, 234, NULL),
(19, 23, 'Higher', 'SLIIT', 'Diploma', 'FOC', 'IT23440722', 2016, 2020, NULL);

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
(1, 1, 'Nagarasa', 'Farmer', '0767875639', 'Amaravathy', 'Housewife', '0741885776', 1, 0),
(2, 2, 'Kumar', 'Business', '0771111111', 'Lakshmi', 'Teacher', '0772222222', 2, 1),
(3, 3, 'Sharma', 'Doctor', '0911111111', 'Geetha', 'Housewife', '0912222222', 1, 2),
(4, 4, 'Suresh', 'Government Officer', '0761111111', 'Malar', 'Housewife', '0762222222', 0, 1),
(5, 5, 'Kumar', 'Business', '0763333333', 'Rani', 'Housewife', '0764444444', 2, 0),
(6, 10, 'jdiofh', 'sdfhvgh', '01545154154651', 'fghfh', 'sdfghftgh', 'sdfghfgh', 5, 0),
(7, 12, 'Nad', '34ed', 'ed3', 'dsd', 'e3d', '3ed', 3, 3),
(8, 14, '213', '23', '213', '123', '231', '213', 312, 22),
(12, 22, '234', '243', '24', '34', '243', '234', 243, 234),
(13, 23, 'qgerw', 'agfdsf', 'sdaf', 'reqtewrter', 'adfg', 'sdfasdf', 2, 32);

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
(1, 1, '1995-08-27', '13:02:00', 'Leo', 'Magha', 'No', 'uploads/1756193534_horo1.jpg', 'uploads/1756193534_navamsha1.jpg'),
(2, 2, '1993-05-15', '08:30:00', 'Taurus', 'Rohini', 'No', 'uploads/1756193535_horo2.jpg', 'uploads/1756193535_navamsha2.jpg'),
(3, 3, '1990-11-20', '16:45:00', 'Scorpio', 'Anuradha', 'Yes', 'uploads/1756193536_horo3.jpg', 'uploads/1756193536_navamsha3.jpg'),
(4, 10, '2025-09-25', '07:20:00', 'Leo', '500', 'Yes', 'uploads/1758538163_12.jpg', 'uploads/1758538163_23.jpg'),
(6, 12, '2025-09-26', '15:21:00', 'v', 'csad', 'acsd', 'uploads/1758559895_Q5.png', 'uploads/1758559895_second.png'),
(7, 14, '0023-12-31', '22:52:00', '123', '123', '123', 'uploads/1758561687_second.png', 'uploads/1758561687_Q1.png'),
(11, 23, '2025-09-23', '10:46:00', '213', 'Roooooooooooooo', '234', 'uploads/1759209104_27fb1648-ddda-46f7-8c7f-2a939605361c.jpg', 'uploads/1759209104_67db7acf-0e5b-44d5-8aa8-81765cd8433d.jpg');

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
  `marital_status` enum('Single','Married','Divorced','Widowed') NOT NULL,
  `language` varchar(50) NOT NULL,
  `profession` varchar(100) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `smoking` enum('No','Yes','Occasionally') DEFAULT NULL,
  `drinking` enum('No','Yes','Occasionally') DEFAULT NULL,
  `present_address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `zip` varchar(20) DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `permanent_city` varchar(50) DEFAULT NULL,
  `package` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `income` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `name`, `photo`, `looking_for`, `dob`, `religion`, `gender`, `marital_status`, `language`, `profession`, `country`, `phone`, `smoking`, `drinking`, `present_address`, `city`, `zip`, `permanent_address`, `permanent_city`, `package`, `created_at`, `user_id`, `income`) VALUES
(1, 'Vithusan Nagarasha', 'uploads/1756192676_Screenshot (4).png', 'Male', '1995-08-27', 'Hindu', 'Male', 'Single', 'Tamil', 'Software Developer', 'Sri Lanka', '0767875639', 'Occasionally', 'Occasionally', 'Navatkadu varani', 'Kodikaamam', '4555', 'Navatkadu varani', 'Kodikaamam', 'Gold', '2025-08-26 07:17:56', 5, NULL),
(2, 'Sakthi Kumar', 'uploads/1756193087_profile2.jpg', 'Female', '1993-05-15', 'Hindu', 'Male', 'Single', 'Tamil, English', 'Engineer', 'Sri Lanka', '0771234567', 'No', 'No', 'Colombo 05', 'Colombo', '00500', 'Jaffna', 'Jaffna', 'Silver', '2025-08-26 07:24:47', 3, NULL),
(3, 'Priya Sharma', 'uploads/1756193150_profile3.jpg', 'Male', '1990-11-20', 'Hindu', 'Female', 'Single', 'Tamil, Hindi', 'Doctor', 'India', '0912345678', 'No', 'No', 'Chennai', 'Chennai', '600001', 'Chennai', 'Chennai', 'Premium', '2025-08-26 07:25:50', 4, NULL),
(4, 'Kavi Suresh', 'uploads/1758095481_profile4.jpg', 'Female', '1988-03-10', 'Hindu', 'Male', 'Divorced', 'Tamil', 'Teacher', 'Sri Lanka', '0765432198', 'No', 'Occasionally', 'Kandy', 'Kandy', '20000', 'Kandy', 'Kandy', 'Gold', '2025-09-17 07:51:21', 6, NULL),
(5, 'Saran Kumar', 'uploads/1758098690_profile5.jpg', 'Female', '1992-07-25', 'Hindu', 'Male', 'Single', 'Tamil', 'Business', 'Sri Lanka', '0767875639', 'No', 'No', 'Navatkadu varani', 'Kodikaamam', '4000', 'Navatkadu varani', 'Kodikaamam', 'Silver', '2025-09-17 08:44:50', 7, NULL),
(6, 'lingajan', 'uploads/1758534957_12.jpg', 'Male', '2025-09-18', 'Hindu', 'Male', 'Single', 'Tamil', 'Enginer', 'Srilanka', '12345678', 'No', 'No', 'sxfvdsfg', 'Jaffna', '40000', 'fgbtjhn', 'cghncgfhn', NULL, '2025-09-22 09:55:57', 8, NULL),
(7, 'lingajan', 'uploads/1758535374_12.jpg', 'Male', '2025-09-24', 'Hindu', 'Male', 'Single', 'Tamil', 'Enginer', 'Srilanka', '0755350101', 'No', 'No', '8uhynu8m', 'Jaffna', '40000', 'uh8u8j8u9', 'Jafna', NULL, '2025-09-22 10:02:54', 8, NULL),
(8, 'weding', 'uploads/1758535791_12.jpg', 'Male', '2025-09-09', 'Hindu', 'Female', 'Single', 'Tamil', 'Enginer', 'Srilanka', '0755350101', 'Occasionally', 'Occasionally', 'fgnm', 'Jaffna', '40000', 'vnbfvn', 'Jafna', NULL, '2025-09-22 10:09:51', 8, NULL),
(9, '', '', '', '0000-00-00', '', '', '', '', '', '', '', 'No', 'No', '', '', '', '', '', NULL, '2025-09-22 10:43:16', 8, NULL),
(10, '123', 'uploads/1758537858_23.jpg', 'Female', '2017-06-06', 'Hindu', 'Male', 'Single', 'Tamil', 'Enginer', 'Srilanka', '12345678', 'Yes', 'Yes', 'ghmhg', 'Jaffna', '40000', 'fcgjmnh', 'cghncgfhn', NULL, '2025-09-22 10:44:18', 8, NULL),
(11, '', '', '', '0000-00-00', '', '', '', '', '', '', '', 'No', 'No', '', '', '', '', '', NULL, '2025-09-22 10:51:24', 8, NULL),
(12, 'Kamalanathan Thananchayan', 'uploads/1758559576_3.png', 'Male', '2002-10-24', 'Hindu', 'Female', 'Single', 'ewjbjsdf', 'Software Engineering', 'Sri Lanka', '0771727687', 'Yes', 'No', 'Kantharmadam', 'Jaffna', 'wqdbhjqwhjwqd', 'Kantharmadam', 'Jaffna', NULL, '2025-09-22 16:46:16', 9, NULL),
(13, 'Kamalanathan Thananchayan', 'uploads/1758559963_second.png', 'Male', '2025-09-26', 'afdsasd', 'Male', 'Divorced', 'asd', 'adssss', 'Sri Lanka', 'adsssss', 'Yes', 'No', 'Kantharmadam', 'Jaffna', 'wqdbhjqwhjwqd', 'Kantharmadam', 'Jaffna', NULL, '2025-09-22 16:52:43', 9, NULL),
(14, 'Kamalanathan Thananchayan', 'uploads/1758561576_second.png', 'Male', '2025-10-02', 'sadddddd', 'Male', 'Married', 'ewjbjsdf', 'Software Engineering', 'Sri Lanka', '0771727687', 'Yes', 'Yes', 'Kantharmadam', 'Jaffna', 'wqdbhjqwhjwqd', 'Kantharmadam', 'Jaffna', NULL, '2025-09-22 17:19:36', 10, NULL),
(22, 'Kamalanathan Thananchayan', 'uploads/1758572272_second.png', 'Female', '2025-09-18', '123', 'Male', 'Single', '123', '123', 'Sri Lanka', '123', 'No', 'No', 'Kantharmadam', 'Jaffna', 'wqdbhjqwhjwqd', 'Kantharmadam', NULL, NULL, '2025-09-22 20:17:52', 11, NULL),
(23, 'Kajanan', 'uploads/1759208473_naga.jfif', 'Male', '2000-05-07', '123', 'Male', 'Single', 'WEFFFF', 'SDfffff', 'Sri Lanka', 'sdfsdff', 'Yes', 'Yes', '', '', '', '', '', NULL, '2025-09-30 05:01:13', 12, 59000);

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
(4, 'Admin', 'admin@matrimony.com', 'vc bm', 'ghmkghk', '2025-09-26 14:06:40', 'admin', NULL, NULL),
(5, 'Admin', 'admin@matrimony.com', 'vc bm', 'ghmkghk', '2025-09-26 14:07:20', 'admin', NULL, NULL),
(6, 'dgfbh', 'edhbasuyhbfdyhbsf@sdkjsdjf', 'abjsbkjkjbsdkjnsdkjdjk swdjkbwndjwe', 'ekjwdfekjwejnfkjbwe wekjnwfekjwefwef', '2025-09-26 16:38:22', 'admin', NULL, NULL),
(7, 'Hero', 'thananchayan@gmail.com', 'abjsbkjkjbsdkjnsdkjdjk swdjkbwndjwe', 'wqreeeeeeeeeeeeeeeeeee', '2025-09-26 16:38:57', 'admin', NULL, NULL);

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
(6, 'Platinum', 3223.00, 30, 'active', 'SFD', '2025-09-26 06:58:46', '23', '23', 'Unlimited', 'Yes', 'No', 'No'),
(7, 'Brownze', 12000.00, 90, 'active', 'Hello and join this package', '2025-09-26 07:52:19', '20', '10', 'Unlimited', 'Yes', 'Yes', 'Yes'),
(8, 'Gold', 23000.00, 365, 'active', 'Hello', '2025-09-26 09:52:28', '100', '50', 'Limited', 'No', 'No', 'No');

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
(1, 1, 'Sri Lanka', 24, 28, 150, 170, 'Never Married', 'Hindu', 'No', 'No'),
(2, 2, 'Sri Lanka', 25, 30, 155, 165, 'Never Married', 'Hindu', 'No', 'No'),
(3, 3, 'India', 30, 35, 160, 180, 'Never Married', 'Hindu', 'No', 'Occasionally'),
(4, 4, 'Sri Lanka', 28, 35, 150, 165, 'Never Married', 'Hindu', 'No', 'No'),
(5, 5, 'Sri Lanka', 25, 32, 155, 170, 'Never Married', 'Hindu', 'No', 'No'),
(6, 10, 'fghn', 50, 50, 50, 50, 'Never Married', 'Hindu', 'No', 'No'),
(7, 12, 'saddddd', 213, 1113, 21321, 233333, 'Never Married', '211111', 'Yes', 'No'),
(8, 14, '123', 123, 123, 123, 123, 'Never Married', '123', 'Yes', 'Yes'),
(12, 22, '234', 234, 24, 234, 234, 'Never Married', '234', 'Yes', 'Yes'),
(13, 23, 'Srilanka', 23, 34, 190, 233, 'Divorced', 'Christian', 'No', 'Yes');

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
(1, 1, 'Fair', 175.00, 70.00, 'A+', 'Brown', 'Black', 'No', '2025-08-26 07:30:13'),
(2, 2, 'Wheatish', 170.00, 65.00, 'B+', 'Brown', 'Black', 'No', '2025-08-26 07:32:15'),
(3, 3, 'Fair', 162.00, 55.00, 'O+', 'Brown', 'Black', 'No', '2025-08-26 07:33:20'),
(4, 4, 'Wheatish', 168.00, 68.00, 'AB+', 'Brown', 'Black', 'No', '2025-09-17 07:52:30'),
(5, 5, 'Fair', 172.00, 72.00, 'A-', 'Brown', 'Black', 'No', '2025-09-17 08:47:08'),
(6, 10, 'Wheatish', 170.00, 70.00, 'A-', 'Brown', 'Black', 'No', '2025-09-22 10:44:41'),
(7, 12, 'Fair', 232.00, 23.00, 'B+', '23', '232', 'Yes', '2025-09-22 16:47:11'),
(8, 13, 'Wheatish', 24.00, 21.00, 'A-', '32', '234', 'No', '2025-09-22 16:53:05'),
(9, 14, 'Fair', 123.00, 123.00, 'O+', '123', '123', 'Yes', '2025-09-22 17:20:24'),
(19, 22, 'Wheatish', 424.00, 234.00, 'O-', '234', '234', 'Yes', '2025-09-22 20:18:14'),
(20, 23, 'Dark', 190.00, 78.00, 'A-', 'Brown', 'Black', 'No', '2025-09-30 05:03:05');

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
(1, 5, 'Excellent platform! Found my perfect match within 3 months.', '2025-08-20 08:54:46', 'Rajesh Kumar', 'Engineer', 'India', '1755680086_review1.jpg'),
(2, 4, 'Good service but could improve search filters.', '2025-08-20 08:55:19', 'Priya Sharma', 'Doctor', 'India', '1755680119_review2.jpg'),
(3, 5, 'Amazing experience! Highly recommended.', '2025-08-20 09:03:52', 'Saman Perera', 'Teacher', 'Sri Lanka', '1755680632_review3.jpg'),
(4, 3, 'Merged all forms into a single form with one form section containing all subsections\r\n\r\nAdded a hidden input field complete_registration to identify when the form is submitted\r\n\r\nModified the PHP processing to handle all form data in one submission when the \"Finish Registration\" button is clicked', '2025-09-22 20:34:24', 'Thanu', 'Software Engineering', 'Sri Lanka', '1758573264_3.png'),
(5, 2, 'Merged all forms into a single form with one form section containing all subsections\r\n\r\nAdded a hidden input field complete_registration to identify when the form is submitted\r\n\r\nModified the PHP processing to handle all form data in one submission when the \"Finish Registration\" button is clicked', '2025-09-22 20:35:05', 'Kamalanathan Thananchayan', 'Software Engineering', 'Srilanka', '1758573305_1.jpg');

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

--
-- Dumping data for table `search_queries`
--

INSERT INTO `search_queries` (`id`, `looking_for`, `age_range`, `country`, `city`, `religion`, `user_ip`, `created_at`) VALUES
(1, 'Female', '25-30', 'Sri Lanka', 'Colombo', 'Hindu', '::1', '2025-08-26 09:52:26'),
(2, 'Male', '28-35', 'India', 'Chennai', 'Hindu', '::1', '2025-08-26 09:54:14');

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
  `position` varchar(100) DEFAULT NULL,
  `access_level` varchar(50) NOT NULL DEFAULT 'restricted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staff_id`, `name`, `email`, `password`, `role`, `phone`, `age`, `gender`, `address`, `position`, `access_level`, `created_at`, `updated_at`) VALUES
(1, 'Adminnn', 'thanu@gmail.com', '$2y$10$GEwIhXDhqZHcbKi2gDO.8.VQpKEvAlpiT51DAd387Wu008BvLkEJG\r\n', 'admin', '0771234567', 35, 'Male', 'Colombo', 'Administrator', 'full', '2025-09-08 07:10:40', '2025-09-26 03:41:21'),
(2, 'staff', 'thana@gmail.com', '$2y$10$GEwIhXDhqZHcbKi2gDO.8.VQpKEvAlpiT51DAd387Wu008BvLkEJG\r\n', 'staff', '0777654321', 28, 'Female', 'Kandy', 'staff', 'limited', '2025-09-08 08:34:42', '2025-09-23 14:51:24');

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

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `member_id`, `member_name`, `transaction_date`, `amount`, `status`, `payment_method`, `created_at`) VALUES
('TXN001', 1, 'Vithusan Nagarasha', '2025-08-27 10:00:00', 10000.00, 'Completed', 'Credit Card', '2025-09-02 10:39:06'),
('TXN002', 2, 'Sakthi Kumar', '2025-08-28 11:30:00', 5000.00, 'Completed', 'PayPal', '2025-09-23 10:40:15');

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
(12, 'Brownze', 90, 'accept', 'uploads/payment_slips/slip_12_1759224253.jfif', '2025-09-30 11:24:43', '2025-12-29 11:24:43', 12),
(13, 'Brownze', 90, 'accept', 'uploads/payment_slips/slip_13_1759242983.jfif', '2025-09-30 11:24:43', '2025-12-29 11:24:43', 13);

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
(1, 'admin', 'admin@matrimony.com', '$2y$10$ke.p5.4OoC48.G2NJRSfAOMpdtS178h.QiEAJSAylsthimv/BsAwu', '2025-09-17 05:44:35', 'admin', NULL, NULL),
(2, 'sam', 'sam@example.com', '$2y$10$V5Me0Gr2wPbuFzpjI7WBJ.KH3MJVPa6WsoycQi7BzA3et1WSoZZlC', '2025-09-17 06:02:15', 'user', NULL, NULL),
(3, 'vithujeya', 'jeya@example.com', '$2y$10$KyEP9m.BdRhy.vpFuzbyVOTsHeG4eEK5WSqEeMcXNT72kT7qoIpy6', '2025-09-17 06:07:56', 'user', NULL, NULL),
(4, 'abi', 'abi@example.com', '$2y$10$dccKP/YX0FS92UVOErd2SO0nToM0w39QiecindOvtqUSPGH8x3xmS', '2025-09-17 06:17:10', 'user', NULL, NULL),
(5, 'vithusan', 'vithusan@example.com', '$2y$10$aide/.w4KYrr0Ztvn7UcROZj9XTLnvv6Vu52dc26Go5rWTI1N5C9e', '2025-09-17 09:43:54', 'block', NULL, NULL),
(6, 'hamsa', 'hamsa@example.com', '$2y$10$FWl5GAhYbwLU0Ks2XplCv.wc2JdzlEce4wrhP0mi3gQCoKBAlTpYu', '2025-09-17 10:56:18', 'user', NULL, NULL),
(7, 'rina', 'rina@example.com', '$2y$10$qXi5Oa.7FhvbW5ZnjyoUYevk6LPsVOsCQemL17.SHvjNxfvSTNPlC', '2025-09-19 08:37:05', 'user', NULL, NULL),
(8, 'sam1', 'nallathamilan01@gmail.com', '$2y$10$DW/ANfjHPDVivKi8a.RqLeLyZ5of7NCUnwZegKcoqrWQ8BK9MNrbi', '2025-09-22 09:41:04', 'user', NULL, NULL),
(9, 'User1', 'kamalanathanthananchayan@gmail.com', '$2y$10$eOf7Yv2/GX6SquVC9DsO4ecY9wBcAACFEJVwPGvxzs2P6RWs.Ltvm', '2025-09-22 12:49:25', 'user', NULL, NULL),
(10, 'Nathan', 'Nathan@gmail.com', '$2y$10$G7lRDCi3U/WCNUuPNhuvoucUo9eT2yT1CDHu/NbNeACpdQOBhSrvy', '2025-09-22 13:50:15', 'user', NULL, NULL),
(11, 'Kuberan', 'Kuberan@gmail.com', '$2y$10$ZOiT7c4e4v2iD.QouQezv.OOldfFgwW.5WSgtVDl6hXYUe1ipS8UO', '2025-09-22 17:31:02', 'user', NULL, NULL),
(12, 'thanu', 'kamalanathanthananchayan04@gmail.com', '$2y$10$WNybLJtRcs8a5eigWyuqR.lYo/vKTzBZFEQ094pJn/IA3Ap72b9V.', '2025-09-23 03:36:12', 'user', NULL, NULL),
(13, 'Thananchayan', 'thananchayan2002@gmail.com', '$2y$10$Viv1HW/qBadgdRdoxRBdeeblq5IktQ1wAjZ79zUr05tmO9RVJ5RbG', '2025-09-30 14:14:11', 'user', NULL, NULL);

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
(21, 12, '2025-09-30', 10, '2025-09-30 13:40:16', '2025-09-30 13:43:41'),
(34, 9, '2025-09-30', 5, '2025-09-30 14:03:13', '2025-09-30 14:03:29'),
(39, 13, '2025-09-29', 4, '2025-09-30 14:14:28', '2025-09-30 14:32:02'),
(45, 13, '2025-09-30', 10, '2025-09-30 14:33:12', '2025-09-30 14:49:11');

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
(34, 12, 11, '2025-09-30', '2025-09-30 13:40:24'),
(35, 12, 12, '2025-09-30', '2025-09-30 13:40:27'),
(36, 12, 13, '2025-09-30', '2025-09-30 13:40:30'),
(37, 12, 10, '2025-09-30', '2025-09-30 13:40:32'),
(38, 12, 9, '2025-09-30', '2025-09-30 13:40:34'),
(39, 12, 8, '2025-09-30', '2025-09-30 13:40:36'),
(40, 12, 7, '2025-09-30', '2025-09-30 13:40:40'),
(41, 12, 23, '2025-09-30', '2025-09-30 13:43:37'),
(42, 12, 22, '2025-09-30', '2025-09-30 13:43:39'),
(43, 12, 14, '2025-09-30', '2025-09-30 13:43:41'),
(44, 9, 23, '2025-09-30', '2025-09-30 14:03:13'),
(45, 9, 22, '2025-09-30', '2025-09-30 14:03:15'),
(46, 9, 14, '2025-09-30', '2025-09-30 14:03:18'),
(47, 9, 12, '2025-09-30', '2025-09-30 14:03:25'),
(48, 9, 9, '2025-09-30', '2025-09-30 14:03:29'),
(49, 13, 23, '2025-09-29', '2025-09-30 14:14:28'),
(50, 13, 22, '2025-09-29', '2025-09-30 14:19:10'),
(51, 13, 14, '2025-09-29', '2025-09-30 14:19:20'),
(52, 13, 12, '2025-09-29', '2025-09-30 14:23:04'),
(55, 13, 13, '2025-09-30', '2025-09-30 14:33:12'),
(56, 13, 11, '2025-09-30', '2025-09-30 14:33:15'),
(57, 13, 8, '2025-09-30', '2025-09-30 14:33:22'),
(58, 13, 9, '2025-09-30', '2025-09-30 14:33:24'),
(59, 13, 10, '2025-09-30', '2025-09-30 14:33:27'),
(60, 13, 6, '2025-09-30', '2025-09-30 14:42:03'),
(61, 13, 7, '2025-09-30', '2025-09-30 14:42:39'),
(62, 13, 5, '2025-09-30', '2025-09-30 14:45:05'),
(63, 13, 1, '2025-09-30', '2025-09-30 14:49:08'),
(64, 13, 4, '2025-09-30', '2025-09-30 14:49:11');

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
-- Structure for view `user_interest_summary`
--
DROP TABLE IF EXISTS `user_interest_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_interest_summary`  AS SELECT `u`.`id` AS `user_id`, `u`.`username` AS `username`, coalesce(`dic`.`likes_count`,0) AS `today_likes_count`, coalesce(`p`.`interest_limit`,'5') AS `interest_limit`, coalesce(`p`.`name`,'Free User') AS `package_name`, CASE WHEN coalesce(`p`.`interest_limit`,'5') = 'Unlimited' THEN 'No limit' WHEN coalesce(`dic`.`likes_count`,0) >= cast(coalesce(`p`.`interest_limit`,'5') as unsigned) THEN 'Limit reached' ELSE concat(coalesce(`dic`.`likes_count`,0),'/',coalesce(`p`.`interest_limit`,'5')) END AS `interest_status` FROM (((`users` `u` left join `userpackage` `up` on(`u`.`id` = `up`.`user_id` and `up`.`requestPackage` = 'accept' and `up`.`end_date` > current_timestamp())) left join `packages` `p` on(`up`.`status` = `p`.`name`)) left join `user_daily_interest_counts` `dic` on(`u`.`id` = `dic`.`user_id` and `dic`.`interest_date` = curdate())) ;

--
-- Indexes for dumped tables
--

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
  ADD KEY `fk_members_user_id` (`user_id`);

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
  ADD UNIQUE KEY `email` (`email`);

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
-- Indexes for table `user_interests`
--
ALTER TABLE `user_interests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_interest` (`user_id`,`target_member_id`),
  ADD KEY `idx_user_date` (`user_id`,`interest_date`),
  ADD KEY `idx_target_member` (`target_member_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `blog`
--
ALTER TABLE `blog`
  MODIFY `blog_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `calls`
--
ALTER TABLE `calls`
  MODIFY `call_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `education`
--
ALTER TABLE `education`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `family`
--
ALTER TABLE `family`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `horoscope`
--
ALTER TABLE `horoscope`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `package_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `package_requests`
--
ALTER TABLE `package_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `partner_expectations`
--
ALTER TABLE `partner_expectations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `physical_info`
--
ALTER TABLE `physical_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `search_queries`
--
ALTER TABLE `search_queries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `userpackage`
--
ALTER TABLE `userpackage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_daily_interest_counts`
--
ALTER TABLE `user_daily_interest_counts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `user_interests`
--
ALTER TABLE `user_interests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

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
-- Constraints for table `user_interests`
--
ALTER TABLE `user_interests`
  ADD CONSTRAINT `user_interests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_interests_ibfk_2` FOREIGN KEY (`target_member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
