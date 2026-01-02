-- Migration 018: Add rams3377@gmail.com with Enterprise plan
-- Created: 2025-10-27
-- Description: Add rams user with Password@123 and Enterprise plan

DO $$
DECLARE
    v_user_id UUID;
    v_api_key TEXT;
BEGIN
    -- Generate API key
    v_api_key := 'vb_' || encode(gen_random_bytes(24), 'hex');

    -- Insert user with BCrypt cost=10 hash for Password@123
    INSERT INTO users (email, password_hash, status)
    VALUES ('rams3377@gmail.com', '$2y$10$amTkasojuWGr841TiyWB/u36GeD0x1oaffzY.xDxNYsXv1E3hFR4G', 'active')
    ON CONFLICT (email) DO UPDATE
    SET password_hash = EXCLUDED.password_hash,
        status = EXCLUDED.status
    RETURNING id INTO v_user_id;

    -- If user already existed, get their ID
    IF v_user_id IS NULL THEN
        SELECT id INTO v_user_id FROM users WHERE email = 'rams3377@gmail.com';
    END IF;

    -- Insert or update API key
    INSERT INTO api_keys (user_id, key, name)
    VALUES (v_user_id, v_api_key, 'Default API Key')
    ON CONFLICT (user_id, name) DO UPDATE
    SET key = EXCLUDED.key;

    -- Insert or update billing account with Enterprise plan
    INSERT INTO billing_accounts (user_id, plan)
    VALUES (v_user_id, 'enterprise')
    ON CONFLICT (user_id) DO UPDATE
    SET plan = EXCLUDED.plan;

    -- Insert or update quotas for Enterprise plan
    INSERT INTO quotas (user_id, period, allowance, used)
    VALUES (v_user_id, 'monthly', 1000000, 0)
    ON CONFLICT (user_id, period) DO UPDATE
    SET allowance = EXCLUDED.allowance;

    RAISE NOTICE 'Added/updated rams3377@gmail.com with Enterprise plan';
    RAISE NOTICE 'API Key: %', v_api_key;
END $$;
