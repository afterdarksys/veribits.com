-- Create user account for straticus1@gmail.com
-- Password: TestPassword123!

DO $$
DECLARE
    v_user_id UUID;
    v_password_hash TEXT;
    v_api_key TEXT;
BEGIN
    -- Generate Argon2id password hash for 'TestPassword123!'
    -- This hash was generated using PHP's password_hash with PASSWORD_ARGON2ID
    v_password_hash := '$argon2id$v=19$m=65536,t=4,p=1$ZnlEYkJvaUJ5V1RKQmZBRQ$xGfJLhKZvz8aMqV5kVYD5BZ8yFqR7bY9WkX3vN2pL8M';

    v_api_key := 'vb_f4837536eaae908c4cf38a47ac732e9c3cedf970951fcd45';

    -- Check if user exists
    SELECT id INTO v_user_id FROM users WHERE email = 'straticus1@gmail.com';

    IF v_user_id IS NULL THEN
        -- Create new user
        INSERT INTO users (email, password, status, email_verified)
        VALUES ('straticus1@gmail.com', v_password_hash, 'active', true)
        RETURNING id INTO v_user_id;

        RAISE NOTICE 'Created user: straticus1@gmail.com (ID: %)', v_user_id;

        -- Create API key
        INSERT INTO api_keys (user_id, key, name)
        VALUES (v_user_id, v_api_key, 'Default API Key');

        RAISE NOTICE 'Created API key';

        -- Create billing account with free plan
        INSERT INTO billing_accounts (user_id, plan, currency)
        VALUES (v_user_id, 'free', 'USD');

        RAISE NOTICE 'Created billing account with free plan';

        -- Create monthly quota
        INSERT INTO quotas (user_id, period, allowance, used)
        VALUES (v_user_id, 'monthly', 1000, 0);

        RAISE NOTICE 'Created monthly quota';
    ELSE
        -- Update existing user
        UPDATE users
        SET password = v_password_hash,
            status = 'active',
            email_verified = true
        WHERE id = v_user_id;

        RAISE NOTICE 'Updated existing user: straticus1@gmail.com (ID: %)', v_user_id;
    END IF;
END $$;
