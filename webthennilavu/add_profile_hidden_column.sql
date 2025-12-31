-- Add profile_hidden column to members table if it doesn't exist
ALTER TABLE members ADD COLUMN IF NOT EXISTS profile_hidden TINYINT(1) DEFAULT 0;

-- Update description: This column tracks whether a user has hidden their profile
-- 0 = Profile is visible (default)
-- 1 = Profile is hidden

-- You can run this SQL in phpMyAdmin or MySQL command line to ensure the column exists