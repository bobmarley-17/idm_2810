-- Modify defunct_users status column to include all needed statuses
ALTER TABLE defunct_users MODIFY COLUMN status ENUM('pending', 'deleted', 'active', 'inactive') NOT NULL DEFAULT 'pending';

-- Add status column to user_accounts table with matching statuses
ALTER TABLE user_accounts ADD COLUMN status ENUM('pending', 'deleted', 'active', 'inactive') NOT NULL DEFAULT 'active';

-- Update existing user_accounts to have status = 'active'
UPDATE user_accounts SET status = 'active' WHERE status IS NULL;
