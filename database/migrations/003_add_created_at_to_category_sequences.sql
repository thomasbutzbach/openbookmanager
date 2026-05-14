-- Migration: Add created_at to category_sequences table
-- Date: 2026-05-14
-- Description: Syncs category_sequences schema with the original add_category_sequences.sql
--              migration which included created_at. The column was lost when the table was
--              recreated during the composite-key refactoring.

ALTER TABLE `category_sequences`
    ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    AFTER `next_number`;
