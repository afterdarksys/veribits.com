-- Create user account for rams3377@gmail.com with annual subscription
-- Password: Password@305

DO $$
DECLARE
    v_user_id UUID;
    v_password_hash TEXT;
BEGIN
    -- Generate password hash (bcrypt cost 12)
    -- This is the hash for 'Password@305'
    v_password_hash := '$2y$12$kVqimr8Vn5o.MK0Vx8/S0u8vpczSNt3POuPR/z8ht/GP8BGZdOTf.';

    -- Check if user exists
    SELECT id INTO v_user_id FROM users WHERE email = 'rams3377@gmail.com';

    IF v_user_id IS NULL THEN
        -- Create new user
        INSERT INTO users (email, password, status, email_verified)
        VALUES ('rams3377@gmail.com', v_password_hash, 'active', true)
        RETURNING id INTO v_user_id;

        RAISE NOTICE 'Created user: rams3377@gmail.com (ID: %)', v_user_id;

        -- Create billing account with annual plan
        INSERT INTO billing_accounts (user_id, plan, currency)
        VALUES (v_user_id, 'annual', 'USD');

        RAISE NOTICE 'Created billing account with annual plan';
    ELSE
        -- Update existing user
        UPDATE users
        SET password = v_password_hash,
            status = 'active',
            email_verified = true
        WHERE id = v_user_id;

        RAISE NOTICE 'Updated existing user: rams3377@gmail.com (ID: %)', v_user_id;

        -- Update or create billing account
        INSERT INTO billing_accounts (user_id, plan, currency)
        VALUES (v_user_id, 'annual', 'USD')
        ON CONFLICT (user_id)
        DO UPDATE SET plan = 'annual';

        RAISE NOTICE 'Updated billing account to annual plan';
    END IF;
END $$;
