-- Migration: Extend ISBN column in scanned_books table
-- Needed to support manually added books with generated ISBNs like "MANUAL-1730489234-7392"

ALTER TABLE `scanned_books`
MODIFY `isbn` VARCHAR(50) NOT NULL;
