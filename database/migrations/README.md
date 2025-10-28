# Database Migrations

This folder contains database migration files for version upgrades.

## Naming Convention

Migrations must follow this naming pattern:
```
NNN_to_X.Y.Z.sql
```

Where:
- `NNN` = Migration number (001, 002, 003, ...)
- `X.Y.Z` = Target version (semantic versioning)

Examples:
- `001_to_1.0.1.sql` - First migration (1.0.0 → 1.0.1)
- `002_to_1.1.0.sql` - Second migration (1.0.1 → 1.1.0)
- `003_to_1.1.1.sql` - Third migration (1.1.0 → 1.1.1)

## Migration Structure

Each migration file should:

1. Include a header comment with description
2. Contain all necessary SQL statements
3. **Always update the version** at the end:

```sql
-- Migration: X.Y.Z → A.B.C
-- Description: What this migration does
-- Date: YYYY-MM-DD

-- Your SQL statements here
ALTER TABLE ...;

-- IMPORTANT: Update version
UPDATE system_info
SET value = JSON_SET(value, '$.version', 'A.B.C', '$.last_update', NOW())
WHERE `key` = 'version';
```

## Execution Order

Migrations are executed in numerical order (001, 002, 003...) based on:
- Current database version
- Target application version

Only migrations between these two versions will be executed.

## Best Practices

1. **Test migrations** on a backup database first
2. **Keep migrations small** - one logical change per migration
3. **Never modify** existing migration files after they've been released
4. **Always backup** before running migrations (done automatically)
5. **Use transactions** where possible
6. **Handle errors** gracefully

## Example Migration

```sql
-- Migration: 1.0.0 → 1.1.0
-- Description: Add tags support for books
-- Date: 2025-10-30

-- Add tags column to books
ALTER TABLE books ADD COLUMN tags TEXT AFTER notes;

-- Create tags table
CREATE TABLE IF NOT EXISTS tags (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) UNIQUE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update version
UPDATE system_info
SET value = JSON_SET(value, '$.version', '1.1.0', '$.last_update', NOW())
WHERE `key` = 'version';
```

## Rollback

Currently, rollback is not supported. Always:
- Keep database backups before updates
- Test migrations on a copy of the database
- Plan migrations carefully

Future versions may support down-migrations for rollback.
