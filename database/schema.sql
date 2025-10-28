-- OpenBookManager Database Schema
-- Version: 1.0
-- Description: Database schema for private book collection management

SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables if they exist (in correct order due to foreign keys)
DROP TABLE IF EXISTS `changelog`;
DROP TABLE IF EXISTS `book_author`;
DROP TABLE IF EXISTS `books`;
DROP TABLE IF EXISTS `wishlist`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `maincategories`;
DROP TABLE IF EXISTS `authors`;
DROP TABLE IF EXISTS `users`;

-- MAIN CATEGORIES
CREATE TABLE `maincategories` (
    `code` VARCHAR(2) NOT NULL PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CATEGORIES (SUBCATEGORIES)
CREATE TABLE `categories` (
    `code` VARCHAR(2) NOT NULL PRIMARY KEY,
    `code_maincategory` VARCHAR(2) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`code_maincategory`) REFERENCES `maincategories`(`code`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_maincategory` (`code_maincategory`),
    INDEX `idx_title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AUTHORS
CREATE TABLE `authors` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `surname` VARCHAR(255) NOT NULL,
    `lastname` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_lastname` (`lastname`),
    INDEX `idx_surname` (`surname`),
    UNIQUE KEY `unique_author` (`surname`, `lastname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BOOKS
CREATE TABLE `books` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(500) NOT NULL,
    `year` INT UNSIGNED NULL,
    `isbn` VARCHAR(20) NULL,
    `cover_image` VARCHAR(500) NULL,
    `code_category` VARCHAR(2) NOT NULL,
    `number_in_category` INT UNSIGNED NOT NULL,
    `publisher` VARCHAR(255) NULL,
    `language` VARCHAR(10) NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`code_category`) REFERENCES `categories`(`code`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `unique_category_number` (`code_category`, `number_in_category`),
    INDEX `idx_title` (`title`),
    INDEX `idx_isbn` (`isbn`),
    INDEX `idx_year` (`year`),
    INDEX `idx_category` (`code_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BOOK_AUTHOR (PIVOT TABLE)
CREATE TABLE `book_author` (
    `book_id` BIGINT UNSIGNED NOT NULL,
    `author_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`book_id`, `author_id`),
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`author_id`) REFERENCES `authors`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_author` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WISHLIST
CREATE TABLE `wishlist` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(500) NOT NULL,
    `author_name` VARCHAR(500) NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- USERS (Single-user authentication)
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CHANGE LOG (Optional: Track changes)
CREATE TABLE `changelog` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL,
    `table_name` VARCHAR(50) NOT NULL,
    `record_id` BIGINT UNSIGNED NOT NULL,
    `action` ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    `changes` JSON NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_table_record` (`table_name`, `record_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SAMPLE DATA FOR TESTING
-- Note: Admin user will be created during installation

-- Sample main categories
INSERT INTO `maincategories` (`code`, `title`) VALUES
('WR', 'Scientific / Research'),
('BL', 'Fiction'),
('SF', 'Non-Fiction'),
('KI', 'Children / Youth');

-- Sample categories
INSERT INTO `categories` (`code`, `code_maincategory`, `title`) VALUES
('PH', 'WR', 'Physics'),
('MA', 'WR', 'Mathematics'),
('PO', 'SF', 'Politics'),
('GE', 'SF', 'History'),
('RO', 'BL', 'Novel'),
('KR', 'BL', 'Crime');

-- Sample authors
INSERT INTO `authors` (`surname`, `lastname`) VALUES
('Arthur', 'Schopenhauer'),
('Isaac', 'Asimov'),
('Yuval Noah', 'Harari'),
('Stephen', 'Hawking');

-- Sample books
INSERT INTO `books` (`title`, `year`, `isbn`, `code_category`, `number_in_category`, `publisher`, `language`, `notes`) VALUES
('Die Welt als Wille und Vorstellung', 1819, '9783458352679', 'PH', 1, 'Insel Verlag', 'DE', 'Philosophy classic'),
('A Brief History of Time', 1988, '9783499626005', 'PH', 2, 'Bantam Books', 'EN', 'Popular science'),
('Foundation', 1951, '9780553293357', 'RO', 1, 'Gnome Press', 'EN', 'Science Fiction classic');

-- Link books to authors
INSERT INTO `book_author` (`book_id`, `author_id`) VALUES
(1, 1),
(2, 4),
(3, 2);

-- Sample wishlist entries
INSERT INTO `wishlist` (`title`, `author_name`, `notes`) VALUES
('Sapiens', 'Yuval Noah Harari', 'ISBN: 9783570552698'),
('The Magic Mountain', 'Thomas Mann', 'Classic novel');

SET FOREIGN_KEY_CHECKS = 1;
