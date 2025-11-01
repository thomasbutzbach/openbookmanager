-- Migration: Add category_sequences table
-- This table tracks the next available sequence number for each category
-- Used for automatic book numbering within categories

CREATE TABLE IF NOT EXISTS `category_sequences` (
    `code_category` VARCHAR(2) NOT NULL PRIMARY KEY,
    `next_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`code_category`) REFERENCES `categories`(`code`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialize sequences for existing categories based on current max numbers
INSERT INTO `category_sequences` (`code_category`, `next_number`)
SELECT
    c.code,
    COALESCE(MAX(b.number_in_category), 0) + 1 as next_number
FROM categories c
LEFT JOIN books b ON b.code_category = c.code
GROUP BY c.code
ON DUPLICATE KEY UPDATE
    `next_number` = VALUES(`next_number`);
