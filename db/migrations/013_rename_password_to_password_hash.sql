-- Migration: Rename password column to password_hash
-- Created: 2025-10-27
-- Description: Renames users.password to users.password_hash for consistency

DO $$
BEGIN
    -- Check if password column exists and password_hash doesn't
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'users' AND column_name = 'password'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'users' AND column_name = 'password_hash'
    ) THEN
        ALTER TABLE users RENAME COLUMN password TO password_hash;
        RAISE NOTICE 'Renamed users.password to users.password_hash';
    ELSE
        RAISE NOTICE 'Column already renamed or migration not needed';
    END IF;
END $$;
