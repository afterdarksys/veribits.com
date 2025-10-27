-- Migration: Create straticus1@gmail.com user account
-- Created: 2025-10-27
-- Description: Creates test user account with API key and quota

DO $$
DECLARE
    v_user_id UUID;
    v_password_hash TEXT;
    v_api_key TEXT;
BEGIN
    -- Argon2id password hash for 'TestPassword123!'
    v_password_hash := '$argon2id$v=19$m=65536,t=4,p=1$ZnlEYkJvaUJ5V1RKQmZBRQ$xGfJLhKZvz8aMqV5kVYD5BZ8yFqR7bY9WkX3vN2pL8M';
    v_api_key := 'vb_f4837536eaae908c4cf38a47ac732e9c3cedf970951fcd45';

    -- Check if user exists
    SELECT id INTO v_user_id FROM users WHERE email = 'straticus1@gmail.com';

    IF v_user_id IS NULL THEN
        -- Create new user
        INSERT INTO users (email, password_hash, status, email_verified)
        VALUES ('straticus1@gmail.com', v_password_hash, 'active', true)
        RETURNING id INTO v_user_id;

        RAISE NOTICE 'Created user: straticus1@gmail.com';

        -- Create API key
        INSERT INTO api_keys (user_id, key, name, revoked)
        VALUES (v_user_id, v_api_key, 'Default API Key', false);

        RAISE NOTICE 'Created API key';

        -- Create billing account
        INSERT INTO billing_accounts (user_id, plan, currency)
        VALUES (v_user_id, 'free', 'USD');

        RAISE NOTICE 'Created billing account';

        -- Create quota
        INSERT INTO quotas (user_id, period, allowance, used)
        VALUES (v_user_id, 'monthly', 1000, 0);

        RAISE NOTICE 'Created quota';

        RAISE NOTICE 'User straticus1@gmail.com created successfully';
    ELSE
        RAISE NOTICE 'User straticus1@gmail.com already exists (ID: %)', v_user_id;
    END IF;
END $$;
