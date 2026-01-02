-- Migration 011: Fix password hash encoding issues
-- This migration cleans up any password hashes that may have encoding issues
-- from database character set problems or invisible Unicode characters

BEGIN;

-- Step 1: Identify users with potentially corrupted password hashes
-- BCrypt hashes should be exactly 60 characters and match the pattern $2y$...
DO $$
DECLARE
    user_record RECORD;
    clean_hash TEXT;
    affected_count INT := 0;
BEGIN
    -- Check for hashes with incorrect length or invalid characters
    FOR user_record IN
        SELECT id, email, password_hash, LENGTH(password_hash) as hash_length
        FROM users
        WHERE password_hash IS NOT NULL
        AND (
            LENGTH(password_hash) != 60
            OR password_hash !~ '^\$2[axy]\$\d{2}\$[./A-Za-z0-9]{53}$'
            OR password_hash ~ '[\x00-\x1F\x7F-\xFF]'  -- Control chars or non-ASCII
        )
    LOOP
        RAISE NOTICE 'Found potentially corrupted hash for user % (email: %, length: %)',
            user_record.id, user_record.email, user_record.hash_length;

        -- Strip any whitespace and control characters
        clean_hash := REGEXP_REPLACE(user_record.password_hash, '[\x00-\x1F\x7F-\xFF\s]+', '', 'g');

        -- Only update if the cleaned hash is different
        IF clean_hash != user_record.password_hash THEN
            UPDATE users
            SET password_hash = clean_hash,
                updated_at = NOW()
            WHERE id = user_record.id;

            affected_count := affected_count + 1;
            RAISE NOTICE 'Cleaned hash for user % (before: %, after: %)',
                user_record.id,
                LENGTH(user_record.password_hash),
                LENGTH(clean_hash);
        END IF;
    END LOOP;

    RAISE NOTICE 'Migration complete. Cleaned % password hashes.', affected_count;
END $$;

-- Step 2: Ensure password_hash column has correct encoding
-- Force column to use bytea or ensure TEXT uses UTF-8
ALTER TABLE users
    ALTER COLUMN password_hash TYPE TEXT USING password_hash::TEXT;

-- Step 3: Add CHECK constraint to prevent future corruption
-- This ensures all password hashes are valid BCrypt format
DO $$
BEGIN
    -- Drop constraint if it exists
    IF EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'valid_password_hash_format'
    ) THEN
        ALTER TABLE users DROP CONSTRAINT valid_password_hash_format;
    END IF;

    -- Add new constraint
    ALTER TABLE users ADD CONSTRAINT valid_password_hash_format
        CHECK (
            password_hash IS NULL
            OR (
                LENGTH(password_hash) = 60
                AND password_hash ~ '^\$2[axy]\$\d{2}\$[./A-Za-z0-9]{53}$'
            )
        );
END $$;

-- Step 4: Log migration completion
INSERT INTO schema_migrations (version, applied_at)
VALUES (11, NOW())
ON CONFLICT (version) DO NOTHING;

COMMIT;

-- Verification query (run manually to check results)
-- SELECT id, email, LENGTH(password_hash) as hash_len,
--        SUBSTRING(password_hash, 1, 10) as hash_preview
-- FROM users
-- WHERE password_hash IS NOT NULL;
