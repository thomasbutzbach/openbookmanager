-- Migration: Add scanned_books table for ISBN scanning workflow
-- This table stores raw data from Google Books API before importing to books table

CREATE TABLE IF NOT EXISTS `scanned_books` (
    `id` BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `isbn` VARCHAR(13) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `subtitle` VARCHAR(255) DEFAULT NULL,
    `authors_raw` TEXT DEFAULT NULL COMMENT 'Comma-separated author names from API',
    `published_year` INT DEFAULT NULL,
    `publisher` VARCHAR(255) DEFAULT NULL,
    `pages` INT DEFAULT NULL,
    `language` VARCHAR(10) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `cover_url` VARCHAR(500) DEFAULT NULL COMMENT 'Original URL from Google Books',
    `cover_local` VARCHAR(255) DEFAULT NULL COMMENT 'Local path after download',
    `status` ENUM('pending', 'reviewed', 'imported', 'skipped') DEFAULT 'pending',
    `imported_book_id` BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'Reference to books.id after import',
    `notes` TEXT DEFAULT NULL COMMENT 'Manual notes during review',
    `scanned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `imported_at` TIMESTAMP NULL DEFAULT NULL,

    INDEX `idx_status` (`status`),
    INDEX `idx_isbn` (`isbn`),
    INDEX `idx_scanned_at` (`scanned_at`),

    FOREIGN KEY (`imported_book_id`) REFERENCES `books`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
