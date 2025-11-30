-- Migration: Add document upload fields to books table
-- Date: 2025-01-30
-- Description: Adds support for PDF/EPUB uploads and format type tracking

-- Add document_file column for storing the path to uploaded PDF/EPUB
ALTER TABLE books ADD COLUMN document_file VARCHAR(500) NULL AFTER cover_image;

-- Add format_type column to distinguish between physical, digital, or both
-- Default is 'physical' so existing books are correctly categorized
ALTER TABLE books ADD COLUMN format_type ENUM('physical', 'digital', 'both') NOT NULL DEFAULT 'physical' AFTER document_file;

-- Add index for format_type for potential future filtering
ALTER TABLE books ADD INDEX idx_format_type (format_type);
