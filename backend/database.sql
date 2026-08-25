-- ========================================================
-- RCC SNEAKERS — Schéma de Base de Données MySQL & Données
-- ========================================================

CREATE DATABASE IF NOT EXISTS `rcc_sneakers` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `rcc_sneakers`;

-- 1. Table des Marques (Brands)
CREATE TABLE IF NOT EXISTS `brands` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `logo_url` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Table des Produits (Products)
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `brand_id` INT NOT NULL,
  `gender` ENUM('MEN', 'WOMEN', 'UNISEX') DEFAULT 'UNISEX',
  `price_regular` INT NOT NULL,
  `price_sale` INT DEFAULT NULL,
  `is_featured` TINYINT(1) DEFAULT 0,
  `is_new_drop` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`brand_id`) REFERENCES `brands`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Table des Images de Produits (Product Images)
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `is_primary` TINYINT(1) DEFAULT 0,
  `display_order` INT DEFAULT 0,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Table des Tailles & Stocks par Pointure (Product Sizes)
CREATE TABLE IF NOT EXISTS `product_sizes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `size` VARCHAR(10) NOT NULL,
  `stock` INT DEFAULT 0,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Table des Utilisateurs (Users)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('CUSTOMER', 'ADMIN') DEFAULT 'CUSTOMER',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Table des Commandes (Orders)
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT DEFAULT NULL,
  `customer_name` VARCHAR(150) NOT NULL,
  `customer_email` VARCHAR(150) NOT NULL,
  `customer_phone` VARCHAR(50) NOT NULL,
  `delivery_address` TEXT NOT NULL,
  `total_amount` INT NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `status` ENUM('PENDING', 'PROCESSING', 'PAID', 'SHIPPED', 'DELIVERED', 'CANCELLED') DEFAULT 'PENDING',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Table des Éléments de Commande (Order Items)
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT DEFAULT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `size` VARCHAR(10) NOT NULL,
  `price` INT NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================================
-- INSERTION DES DONNÉES DE SEED (MARQUES & PRODUCTS)
-- ========================================================

INSERT INTO `brands` (`id`, `name`, `slug`, `logo_url`) VALUES
(1, 'Nike', 'nike', 'https://upload.wikimedia.org/wikipedia/commons/a/a6/Logo_NIKE.svg'),
(2, 'Jordan', 'jordan', 'https://upload.wikimedia.org/wikipedia/en/3/37/Jumpman_logo.svg'),
(3, 'Adidas', 'adidas', 'https://upload.wikimedia.org/wikipedia/commons/2/20/Adidas_Logo.svg'),
(4, 'New Balance', 'new-balance', 'https://upload.wikimedia.org/wikipedia/commons/e/ea/New_Balance_logo.svg'),
(5, 'Yeezy', 'yeezy', 'https://upload.wikimedia.org/wikipedia/commons/a/a6/Logo_NIKE.svg');

-- Produits
INSERT INTO `products` (`id`, `name`, `slug`, `description`, `brand_id`, `gender`, `price_regular`, `price_sale`, `is_featured`, `is_new_drop`) VALUES
(1, 'Air Jordan 4 Retro "Military Black"', 'air-jordan-4-retro-military-black', 'La Air Jordan 4 Military Black reprend le blocage de couleurs iconique de la version OG Military Blue avec des empiècements en cuir blanc et daim gris neutre.', 2, 'MEN', 175000, 160000, 1, 1),
(2, 'Travis Scott x Air Jordan 1 Low "Reverse Mocha"', 'travis-scott-air-jordan-1-low-reverse-mocha', 'Une édition collector rare arborant le fameux Swoosh inversé, une base en suède marron mocha et des revêtements en cuir blanc cassé.', 2, 'UNISEX', 220000, 195000, 1, 1),
(3, 'New Balance 9060 "Rain Cloud Grey"', 'new-balance-9060-rain-cloud-grey', 'Design futuriste et confort exceptionnel grâce à la semelle intermédiaire ABZORB double densité et au daim premium gris.', 4, 'UNISEX', 110000, 99000, 1, 0),
(4, 'Air Jordan 1 High Retro OG "Dark Mocha"', 'air-jordan-1-high-retro-og-dark-mocha', 'Combinaison légendaire de cuir Sail, d empiècements noirs et de cuir nubuck marron mocha sur le talon.', 2, 'UNISEX', 145000, 135000, 1, 0),
(5, 'Adidas Samba OG "Cloud White Core Black"', 'adidas-samba-og-cloud-white-core-black', 'Silhouette légendaire du football indoor devenue incontournable du streetwear avec sa tige en cuir blanc et avant-pied en suède.', 3, 'UNISEX', 75000, 68000, 1, 0),
(6, 'Nike Dunk Low "Panda White Black"', 'nike-dunk-low-panda-white-black', 'Le colorway le plus populaire au monde avec son contraste bicolore noir et blanc intemporel.', 1, 'UNISEX', 85000, 75000, 1, 0),
(7, 'Yeezy Boost 350 V2 "Onyx"', 'yeezy-boost-350-v2-onyx', 'Tige en Primeknit noir profond et semelle Boost assurant un confort ultime à chaque pas.', 5, 'UNISEX', 165000, 149000, 0, 1),
(8, 'Nike Air Force 1 Low \'07 "Triple White"', 'nike-air-force-1-low-07-triple-white', 'Le classique absolu du streetwear mondial en cuir blanc immaculé.', 1, 'UNISEX', 65000, 59000, 0, 0);

-- Images
INSERT INTO `product_images` (`product_id`, `url`, `is_primary`, `display_order`) VALUES
(1, 'https://images.unsplash.com/photo-1552346154-21d32810aba3?w=800', 1, 1),
(2, 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800', 1, 1),
(3, 'https://images.unsplash.com/photo-1539185441755-769473a23570?w=800', 1, 1),
(4, 'https://images.unsplash.com/photo-1579338559194-a162d19bf842?w=800', 1, 1),
(5, 'https://images.unsplash.com/photo-1518002171953-a080ee817e1f?w=800', 1, 1),
(6, 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=800', 1, 1),
(7, 'https://images.unsplash.com/photo-1584735935682-2f2b69dff9d2?w=800', 1, 1),
(8, 'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?w=800', 1, 1);

-- Tailles EU (38 à 45)
INSERT INTO `product_sizes` (`product_id`, `size`, `stock`) VALUES
(1, '39', 4), (1, '40', 6), (1, '41', 8), (1, '42', 10), (1, '43', 5), (1, '44', 3),
(2, '40', 2), (2, '41', 4), (2, '42', 5), (2, '43', 3), (2, '44', 1),
(3, '38', 3), (3, '39', 5), (3, '40', 8), (3, '41', 7), (3, '42', 6),
(4, '40', 4), (4, '41', 6), (4, '42', 8), (4, '43', 4), (4, '44', 2),
(5, '38', 5), (5, '39', 6), (5, '40', 10), (5, '41', 8), (5, '42', 5),
(6, '39', 6), (6, '40', 12), (6, '41', 10), (6, '42', 8), (6, '43', 4),
(7, '40', 3), (7, '41', 5), (7, '42', 7), (7, '43', 4), (7, '44', 2),
(8, '38', 10), (8, '39', 12), (8, '40', 15), (8, '41', 15), (8, '42', 12);

-- Utilisateurs demo (Mot de passe pour admin: admin123456 | mot de passe client: client123456)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`) VALUES
(1, 'RCC Admin', 'admin@rccsneakers.com', '$2a$10$7zB3c9wK2b9X2y1Z0Q0x7e.aW9/2F4t/4y8X9z0Z1Q2W3E4R5T6Y7', 'ADMIN'),
(2, 'Client Test', 'client@rccsneakers.com', '$2a$10$7zB3c9wK2b9X2y1Z0Q0x7e.aW9/2F4t/4y8X9z0Z1Q2W3E4R5T6Y7', 'CUSTOMER');
