-- Migration 015: Update straticus1 password hash
-- Created: 2025-10-27
-- Description: Updates straticus1@gmail.com with freshly generated Argon2id hash for TestPassword123!

DO $$
BEGIN
    -- Update straticus1@gmail.com password hash
    -- Generated: 2025-10-27 with PASSWORD_ARGON2ID, memory_cost=65536, time_cost=4, threads=3
    -- Password: TestPassword123!
    UPDATE users
    SET password_hash = '$argon2id$v=19$m=65536,t=4,p=3$dzhwZ0VMeHNFVmxvMHM0aw$K+NXvY2Z49VMzl6U2cb+qLaAv0PRMnEkJ9XVZY+5B9I'
    WHERE email = 'straticus1@gmail.com';

    RAISE NOTICE 'Updated password hash for straticus1@gmail.com';
END $$;
