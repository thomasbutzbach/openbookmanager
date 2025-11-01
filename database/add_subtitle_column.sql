-- Migration: Add subtitle column to books table
-- Many books have subtitles that should be stored separately

ALTER TABLE `books`
ADD COLUMN `subtitle` VARCHAR(255) DEFAULT NULL AFTER `title`;
