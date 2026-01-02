-- Migration 019: Reset straticus1 password with production-generated hash
-- Created: 2025-10-27
-- Description: Update straticus1@gmail.com with production-compatible BCrypt hash
-- Password: TestPassword123!
-- Note: This hash is generated IN PRODUCTION to ensure compatibility

DO $$
BEGIN
    -- Generate fresh BCrypt hash in production environment
    -- This ensures the hash is compatible with ECS PHP runtime
    UPDATE users
    SET password_hash = crypt('TestPassword123!', gen_salt('bf', 10)),
        updated_at = CURRENT_TIMESTAMP
    WHERE email = 'straticus1@gmail.com';

    RAISE NOTICE 'Reset straticus1@gmail.com password with production-generated hash';
    RAISE NOTICE 'Password: TestPassword123!';
END $$;
