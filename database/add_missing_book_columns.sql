-- Migration: Add missing columns to books table
-- These columns exist in scanned_books but are missing in books

-- Add pages column
ALTER TABLE `books`
ADD COLUMN `pages` INT UNSIGNED DEFAULT NULL AFTER `year`;

-- Add description column
ALTER TABLE `books`
ADD COLUMN `description` TEXT DEFAULT NULL AFTER `notes`;
