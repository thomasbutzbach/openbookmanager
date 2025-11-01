-- Migration: Fix category hierarchy to properly support composite keys
-- This allows the same subcategory code in different main categories
-- Example: SF PH (Science Fiction -> Philosophy) and WR PH (Research -> Philosophy)

-- Step 1: Add code_maincategory column to books table
ALTER TABLE `books` ADD COLUMN `code_maincategory` VARCHAR(2) AFTER `code_category`;

-- Step 2: Populate the new column from existing data
UPDATE `books` b
JOIN `categories` c ON b.code_category = c.code
SET b.code_maincategory = c.code_maincategory;

-- Step 3: Make the new column NOT NULL (now that it's populated)
ALTER TABLE `books` MODIFY `code_maincategory` VARCHAR(2) NOT NULL;

-- Step 4: Drop existing foreign key constraints
ALTER TABLE `books` DROP FOREIGN KEY `books_ibfk_1`;
ALTER TABLE `category_sequences` DROP FOREIGN KEY `category_sequences_ibfk_1`;

-- Step 5: Restructure categories table
-- Drop current primary key
ALTER TABLE `categories` DROP PRIMARY KEY;

-- Add composite primary key
ALTER TABLE `categories` ADD PRIMARY KEY (`code`, `code_maincategory`);

-- Step 6: Add foreign key from books to categories (composite)
ALTER TABLE `books`
    ADD CONSTRAINT `books_category_fk`
    FOREIGN KEY (`code_category`, `code_maincategory`)
    REFERENCES `categories` (`code`, `code_maincategory`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

-- Step 7: Update category_sequences to also use composite key
-- First add the maincategory column
ALTER TABLE `category_sequences` ADD COLUMN `code_maincategory` VARCHAR(2) AFTER `code_category`;

-- Populate from categories
UPDATE `category_sequences` cs
JOIN `categories` c ON cs.code_category = c.code
SET cs.code_maincategory = c.code_maincategory;

-- Make it NOT NULL
ALTER TABLE `category_sequences` MODIFY `code_maincategory` VARCHAR(2) NOT NULL;

-- Drop old primary key
ALTER TABLE `category_sequences` DROP PRIMARY KEY;

-- Add composite primary key
ALTER TABLE `category_sequences` ADD PRIMARY KEY (`code_category`, `code_maincategory`);

-- Add foreign key constraint
ALTER TABLE `category_sequences`
    ADD CONSTRAINT `category_sequences_fk`
    FOREIGN KEY (`code_category`, `code_maincategory`)
    REFERENCES `categories` (`code`, `code_maincategory`)
    ON DELETE CASCADE
    ON UPDATE CASCADE;
