-- System Information Table
-- Stores version and other system-level information as JSON

CREATE TABLE IF NOT EXISTS `system_info` (
    `key` VARCHAR(50) NOT NULL PRIMARY KEY,
    `value` JSON NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial version information
INSERT INTO `system_info` (`key`, `value`) VALUES
('version', JSON_OBJECT(
    'version', '1.0.0',
    'installed_at', NOW(),
    'last_update', NOW()
))
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
