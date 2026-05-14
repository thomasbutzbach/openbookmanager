-- Migration: Sync database schema to current state
-- Date: 2026-05-14
-- Description: Brings existing installations up to date with schema.sql.
--              Covers all changes that accumulated in standalone migration files
--              (add_subtitle_column, add_missing_book_columns, add_document_fields,
--              add_scanned_books, extend_scanned_books_isbn, add_suggested_categories)
--              but were never reflected in schema.sql.

-- ── books: add missing columns ────────────────────────────────────────────────

ALTER TABLE `books`
    ADD COLUMN IF NOT EXISTS `subtitle`       VARCHAR(255)                           NULL   AFTER `title`,
    ADD COLUMN IF NOT EXISTS `pages`          INT UNSIGNED                           NULL   AFTER `year`,
    ADD COLUMN IF NOT EXISTS `document_file`  VARCHAR(500)                           NULL   AFTER `cover_image`,
    ADD COLUMN IF NOT EXISTS `format_type`    ENUM('physical','digital','both') NOT  NULL
                                              DEFAULT 'physical'                            AFTER `document_file`,
    ADD COLUMN IF NOT EXISTS `description`    TEXT                                   NULL   AFTER `notes`;

-- ── scanned_books: recreate with correct structure ───────────────────────────
-- This is a staging table; on a fresh installation it will be empty.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `scanned_books`;

CREATE TABLE `scanned_books` (
    `id`                         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `isbn`                       VARCHAR(50)     NOT NULL,
    `title`                      VARCHAR(255)    NULL,
    `subtitle`                   VARCHAR(255)    NULL,
    `authors_raw`                TEXT            NULL,
    `published_year`             INT             NULL,
    `publisher`                  VARCHAR(255)    NULL,
    `pages`                      INT             NULL,
    `language`                   VARCHAR(10)     NULL,
    `description`                TEXT            NULL,
    `suggested_code_maincategory` VARCHAR(2)     NULL,
    `suggested_code_category`    VARCHAR(2)      NULL,
    `cover_url`                  VARCHAR(500)    NULL,
    `cover_local`                VARCHAR(255)    NULL,
    `status`  ENUM('pending','reviewed','imported','skipped') DEFAULT 'pending',
    `imported_book_id`           BIGINT UNSIGNED NULL,
    `notes`                      TEXT            NULL,
    `scanned_at`                 TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    `imported_at`                TIMESTAMP       NULL,
    UNIQUE KEY `unique_isbn` (`isbn`),
    INDEX `idx_status`              (`status`),
    INDEX `idx_scanned_at`          (`scanned_at`),
    INDEX `idx_suggested_category`  (`suggested_code_category`, `suggested_code_maincategory`),
    FOREIGN KEY (`imported_book_id`)
        REFERENCES `books`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`suggested_code_category`, `suggested_code_maincategory`)
        REFERENCES `categories`(`code`, `code_maincategory`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
