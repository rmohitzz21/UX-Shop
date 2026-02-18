-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 18, 2026 at 01:12 PM
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
-- Database: `uxmerchandise`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `zip_code` varchar(20) NOT NULL,
  `country` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `size` varchar(20) DEFAULT NULL,
  `available_type` varchar(20) DEFAULT 'physical',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `product_id`, `quantity`, `size`, `available_type`, `created_at`) VALUES
(144, 31, 27, 1, '', 'digital', '2026-02-18 08:44:54');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `shipping` decimal(10,2) DEFAULT 50.00,
  `tax` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` enum('Pending','Processing','Shipped','Delivered','Cancelled') DEFAULT 'Pending',
  `shipping_address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `total`, `subtotal`, `shipping`, `tax`, `payment_method`, `status`, `shipping_address`, `created_at`) VALUES
(69, 'UXP-2026-020095', 32, 215.20, 140.00, 50.00, 25.20, 'upi', 'Pending', '{\"firstName\":\"Mohit\",\"lastName\":\"Rana\",\"email\":\"rmohittt21@gmail.com\",\"phone\":\"+918238014262\",\"address\":\"sakm, osoam, moamos, omsmoam,momaoma\",\"city\":\"Ahmedabad\",\"state\":\"gujarat\",\"zip\":\"380001\",\"country\":\"IN\"}', '2026-02-18 11:48:32'),
(70, 'UXP-2026-225341', 32, 710.80, 560.00, 50.00, 100.80, 'upi', 'Pending', '{\"firstName\":\"Mohit\",\"lastName\":\"Rana\",\"email\":\"rmohittt21@gmail.com\",\"phone\":\"+918238014262\",\"address\":\"sakm, osoam, moamos, omsmoam,momaoma\",\"city\":\"Ahmedabad\",\"state\":\"gujarat\",\"zip\":\"380001\",\"country\":\"IN\"}', '2026-02-18 11:51:04');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `size` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`, `size`) VALUES
(67, 69, 30, 2, 70.00, ''),
(68, 70, 30, 8, 70.00, '');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `available_type` enum('physical','digital','both') NOT NULL DEFAULT 'physical',
  `product_type` enum('physical','digital') NOT NULL DEFAULT 'physical',
  `price` decimal(10,2) NOT NULL,
  `commercial_price` decimal(10,2) DEFAULT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `rating` decimal(3,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `related_products` text DEFAULT NULL,
  `whats_included` text DEFAULT NULL,
  `file_specification` text DEFAULT NULL,
  `additional_images` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `category`, `available_type`, `product_type`, `price`, `commercial_price`, `old_price`, `image`, `stock`, `rating`, `created_at`, `updated_at`, `is_active`, `is_featured`, `related_products`, `whats_included`, `file_specification`, `additional_images`) VALUES
(17, 'Classic Tshirt', 'Anyone could access it\n\nIt can reset admin password\n\nIt is unsafe for production', 'T-Shirts', 'physical', 'physical', 25.00, NULL, 30.00, 'img/products/109af6c6bcc955751d850cb320457788.webp', 15, 4.00, '2026-02-11 08:06:02', '2026-02-17 05:43:03', 1, 0, '', 'Anyone could access it\r\n\r\nIt can reset admin password\r\n\r\nIt is unsafe for production', 'Anyone could access it\r\n\r\nIt can reset admin password\r\n\r\nIt is unsafe for production', '[\"img\\/products\\/109af6c6bcc955751d850cb320457788.webp\",\"img\\/products\\/d2ce28f6164f0750eca45e5a01373e89.webp\",\"img\\/products\\/2a710b6f024fc854561c58ee7bd681e4.webp\",\"img\\/products\\/4b40431bd39475683267e1102f29e1b0.webp\"]'),
(18, 'Yugan\'s Badge', 'The badges sann njsan ', 'Badges', 'physical', 'physical', 100.00, NULL, 125.00, 'img/products/b85a3c43de95dcb3d6b13d4d68c014dd.webp', 14, 4.00, '2026-02-12 02:15:28', '2026-02-12 06:47:28', 1, 0, '17', 'The badges sann njsan  ', 'The badges sann njsan  ', '[\"img\\/products\\/b85a3c43de95dcb3d6b13d4d68c014dd.webp\",\"img\\/products\\/8a9084f78369da3641069b125f6a0fa0.webp\"]'),
(19, 'MockUp', 'All About Mockup ......', 'Mockup', 'digital', 'physical', 25.00, 20.00, 30.00, 'img/products/0c246b8c4d3a6fde40af39219616b573.webp', 14, 4.00, '2026-02-12 02:26:36', '2026-02-17 12:00:01', 1, 0, '17,18', 'All About Mockup ......', 'All About Mockup ......', '[\"img\\/products\\/0c246b8c4d3a6fde40af39219616b573.webp\",\"img\\/products\\/cbb1810087997bebdf3f70c3dd784d01.webp\"]'),
(20, 'Design Stickers', 'All About Stickers ....', 'Stickers', 'physical', 'physical', 30.00, 25.00, 45.00, 'img/products/a105a35bc4a179d97c8be6c8445b9003.webp', 1, 4.00, '2026-02-12 02:28:01', '2026-02-17 09:20:43', 1, 0, '17,18,19', 'All About Stickers ....', 'All About Stickers ....', '[\"img\\/products\\/a105a35bc4a179d97c8be6c8445b9003.webp\",\"img\\/products\\/f5321b37b948ca064d362d928071c5ee.webp\",\"img\\/products\\/2b8b0fbaea445ab315253e7738ba8866.webp\",\"img\\/products\\/0a102023e94a5a34bf9aa26e48eee4ad.webp\"]'),
(21, 'Design Template', 'All About UI Template .....', 'Template', 'digital', 'physical', 50.00, 45.00, 70.00, 'img/products/4cf3cc419b193ae5455e8c834835e8ae.webp', 8, 4.00, '2026-02-12 02:31:25', '2026-02-16 10:13:41', 1, 0, '17,18,19,20', 'All About UI Template .....', 'All About UI Template .....', '[\"img\\/products\\/4cf3cc419b193ae5455e8c834835e8ae.webp\",\"img\\/products\\/5c3cf92efbd499b91cf8399aad5e4cb1.webp\",\"img\\/products\\/6269354c6e344fd90ca3fabdbd319b33.webp\",\"img\\/products\\/69d1d6e6e05b17d05c1069986662c1fa.webp\",\"img\\/products\\/a10749e360dce4aa317f68d4a4f36d23.webp\"]'),
(22, 'yugan Booklet', 'All About Booklet .....!', 'Booklet', 'both', 'digital', 45.00, 35.00, 50.00, 'img/products/9ba85bc44fb874ca023d01ecd9c4bf10.webp', 10, 4.00, '2026-02-12 02:36:48', '2026-02-18 11:46:12', 1, 0, '18,19,20,21', 'All About Booklet .....!', 'All About Booklet .....!', '[\"img\\/products\\/9ba85bc44fb874ca023d01ecd9c4bf10.webp\",\"img\\/products\\/e83dac9549102b1dc87fe59f29762d30.webp\",\"img\\/products\\/0fe5fd9f410358ec1b75081db325c586.webp\",\"img\\/products\\/c43f3eece8d124f14306d3e132854b01.webp\"]'),
(24, 'xyz-2111', 'jnnj', 'Stickers', 'physical', 'physical', 100.00, 100.00, 10.00, 'img/products/1529a0de946cb21243f7305f22e8c963.webp', 3, 2.20, '2026-02-16 10:56:56', '2026-02-17 11:20:53', 1, 0, '10,14,15', 'm', '', '[\"img\\/products\\/1529a0de946cb21243f7305f22e8c963.webp\",\"img\\/products\\/c55ec701ef716036eaa3b85235bf9e42.webp\"]'),
(26, 'almxm77', 'dnsdskmmd', 'Workbook', 'digital', 'physical', 100.00, NULL, 1000.00, 'img/products/863b68739c674b1cb40655bb3843c292.webp', 10, 0.00, '2026-02-17 08:00:29', '2026-02-17 10:53:46', 1, 0, '', '', '', '[\"img\\/products\\/863b68739c674b1cb40655bb3843c292.webp\",\"img\\/products\\/90d345a67c9eba8a737eda72afaf90ac.webp\"]'),
(27, 'new', 'msams', 'Mockup', 'digital', 'physical', 45.00, NULL, 152.00, 'img/products/f35a27d8d5cceecde382956e7278e21c.webp', 45, 0.00, '2026-02-17 11:27:39', '2026-02-17 11:27:39', 1, 0, '', 'makskamsa', 'masmamsla', '[\"img\\/products\\/f35a27d8d5cceecde382956e7278e21c.webp\",\"img\\/products\\/017acacbf51466530bfee318f53a0654.webp\"]'),
(29, 'graphic ', 'aksasnasakskasaz ,', 'Template', 'digital', 'physical', 10.00, 122.00, 25.00, 'img/products/8ff922a5f6739d4637011a6a650ff5a3.webp', 10, 0.00, '2026-02-18 06:56:09', '2026-02-18 06:56:09', 1, 0, '', '', '', '[\"img\\/products\\/8ff922a5f6739d4637011a6a650ff5a3.webp\",\"img\\/products\\/296ae3d00911430651dba9033a37648c.webp\"]'),
(30, 'new Booklet', 'smk', 'Booklet', 'physical', 'physical', 70.00, NULL, 100.00, 'img/products/cf8a7d0305e7a9a4d95cf846d1a4eaae.webp', 15, 0.00, '2026-02-18 07:18:05', '2026-02-18 07:36:24', 1, 0, '', '', '', '[\"img\\/products\\/cf8a7d0305e7a9a4d95cf846d1a4eaae.webp\"]');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('customer','admin') DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_blocked` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `role`, `created_at`, `updated_at`, `is_blocked`) VALUES
(2, 'admin@uxpacific.com', '$2y$10$vo1bNxks0BbQwKgtuvLbiu.VKoXEBOLpKluMsTakPbi17VqMYVawu', 'Admin', 'User', NULL, 'admin', '2026-02-09 15:33:31', '2026-02-11 12:22:10', 0),
(9, 'Hello@uxpacific.com', '$2y$10$Wdb2sWhpGyWr4vHSYO9S6OPZCTzM2PzFDjpkbhtvUWREB1BAYhkZ.', 'Admin', 'User', NULL, 'admin', '2026-02-11 12:33:58', '2026-02-11 12:33:58', 0),
(31, 'pacific@gmail.com', '$2y$10$3Y6F7GHmHGzDDoVEd0ni9ePJap9QYIzb5mgKDNLOYycpAEFfpsSDK', 'ux', 'pacific', '8238014262', 'customer', '2026-02-18 05:41:41', '2026-02-18 05:41:41', 0),
(32, 'test@gmail.com', '$2y$10$Me1a4MyAahhFAI0xuhTpG.TaSOvYnZdX5Dhd3sEmLBRSSX2YhzzOa', 'test', '', '7621048017', 'customer', '2026-02-18 11:21:38', '2026-02-18 11:21:38', 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_tokens`
--

CREATE TABLE `user_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `refresh_token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_tokens`
--

INSERT INTO `user_tokens` (`id`, `user_id`, `refresh_token`, `expires_at`, `created_at`) VALUES
(1, 31, 'bc803960ad9fd48eab781975bc3a92e0403ee83eee983c281b569f05530a7bb3', '2026-02-25 09:44:33', '2026-02-18 14:14:33'),
(2, 32, 'c640c89c73a25bb16d945447bf942bbc682c7e82c0d61f1db9695a9a8a663684', '2026-02-25 12:21:48', '2026-02-18 16:51:48'),
(3, 32, '240815abffc71db552a8b4165c37b79dd6e6cbd17f4e167a7dca325ca9f8e20a', '2026-02-25 13:07:01', '2026-02-18 17:37:01');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=160;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `user_tokens`
--
ALTER TABLE `user_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD CONSTRAINT `user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
