-- Add category sequence table for proper autoincrement behavior
-- This ensures that deleted book numbers are never reused

CREATE TABLE IF NOT EXISTS `category_sequences` (
    `code_category` VARCHAR(2) NOT NULL PRIMARY KEY,
    `last_number` INT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (`code_category`) REFERENCES `categories`(`code`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialize sequences for existing categories based on current max numbers
INSERT INTO `category_sequences` (`code_category`, `last_number`)
SELECT code_category, COALESCE(MAX(number_in_category), 0)
FROM books
GROUP BY code_category
ON DUPLICATE KEY UPDATE last_number = VALUES(last_number);

-- Add entries for categories without books yet
INSERT IGNORE INTO `category_sequences` (`code_category`, `last_number`)
SELECT code, 0
FROM categories
WHERE code NOT IN (SELECT code_category FROM category_sequences);
