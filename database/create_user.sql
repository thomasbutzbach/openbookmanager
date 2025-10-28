-- Create dedicated MySQL user for OpenBookManager
-- This user has only the necessary permissions for the openbookmanager database

-- Create user (change password!)
CREATE USER IF NOT EXISTS 'openbookmanager'@'localhost' IDENTIFIED BY 'your_secure_password_here';

-- Grant necessary privileges
GRANT SELECT, INSERT, UPDATE, DELETE ON openbookmanager.* TO 'openbookmanager'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;

-- Verify grants (optional)
SHOW GRANTS FOR 'openbookmanager'@'localhost';
