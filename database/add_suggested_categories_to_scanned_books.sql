-- Add suggested category columns to scanned_books for two-phase workflow
-- Phase 1: Quick categorization without importing
-- Phase 2: Final import with suggested categories as defaults

ALTER TABLE `scanned_books`
    ADD COLUMN `suggested_code_maincategory` VARCHAR(2) DEFAULT NULL AFTER `description`,
    ADD COLUMN `suggested_code_category` VARCHAR(2) DEFAULT NULL AFTER `suggested_code_maincategory`;

-- Add indexes for faster filtering
ALTER TABLE `scanned_books`
    ADD INDEX `idx_suggested_categories` (`suggested_code_maincategory`, `suggested_code_category`);

-- Add foreign key constraint (optional - allows validation but requires categories to exist)
-- Commented out by default to allow flexibility
-- ALTER TABLE `scanned_books`
--     ADD CONSTRAINT `scanned_books_suggested_category_fk`
--     FOREIGN KEY (`suggested_code_category`, `suggested_code_maincategory`)
--     REFERENCES `categories` (`code`, `code_maincategory`)
--     ON DELETE SET NULL
--     ON UPDATE CASCADE;
