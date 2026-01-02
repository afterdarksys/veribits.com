-- Migration 017: Update password hash to cost=10
-- Created: 2025-10-27
-- Description: Update straticus1 password to use BCrypt cost=10 (was cost=12)
-- Password: TestPassword123!

DO $$
BEGIN
    -- BCrypt hash with cost=10 for TestPassword123!
    UPDATE users
    SET password_hash = '$2y$10$/G6mqzIWncBAWL1dIoM9DeNredkpiKs9kf.FDjG39Zh/0.wIX6Cee'
    WHERE email = 'straticus1@gmail.com';

    RAISE NOTICE 'Updated straticus1@gmail.com to BCrypt cost=10';
END $$;
