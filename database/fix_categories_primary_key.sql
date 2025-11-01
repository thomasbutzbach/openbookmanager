-- Migration: Fix categories primary key to allow same code in different main categories
-- Example: SF PH (Science Fiction -> Philosophy) and WR PH (Research -> Philosophy) should both be possible

-- Step 1: Drop foreign key constraints that reference categories.code
ALTER TABLE `books` DROP FOREIGN KEY `books_ibfk_1`;
ALTER TABLE `category_sequences` DROP FOREIGN KEY `category_sequences_ibfk_1`;

-- Step 2: Drop the old primary key
ALTER TABLE `categories` DROP PRIMARY KEY;

-- Step 3: Add composite primary key (code + code_maincategory)
ALTER TABLE `categories` ADD PRIMARY KEY (`code`, `code_maincategory`);

-- Step 4: Recreate foreign key constraints
-- For books table: only code_category needs to match, but we need to reference the full primary key
-- This is tricky because books only stores code_category, not the main category
-- We need to keep the single-column reference but without the constraint, OR change the books table structure

-- For now, let's use a different approach: make code+main_category unique, but use auto-increment ID as primary key
ALTER TABLE `categories` DROP PRIMARY KEY;

-- Add an ID column as primary key
ALTER TABLE `categories` ADD COLUMN `id` INT AUTO_INCREMENT PRIMARY KEY FIRST;

-- Add unique constraint on code + code_maincategory
ALTER TABLE `categories` ADD UNIQUE KEY `unique_code_per_main` (`code`, `code_maincategory`);

-- Recreate foreign key for books (still references code only, which is fine - not strictly enforced)
ALTER TABLE `books`
    ADD CONSTRAINT `books_ibfk_1`
    FOREIGN KEY (`code_category`)
    REFERENCES `categories` (`code`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

-- Recreate foreign key for category_sequences
ALTER TABLE `category_sequences`
    ADD CONSTRAINT `category_sequences_ibfk_1`
    FOREIGN KEY (`code_category`)
    REFERENCES `categories` (`code`)
    ON DELETE CASCADE
    ON UPDATE CASCADE;
