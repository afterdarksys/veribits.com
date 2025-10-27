-- Migration 012: Create Enterprise Account for Testing
-- This creates a second user account on the Enterprise plan for showcasing

DO $$
DECLARE
    v_user_id UUID;
    v_password_hash TEXT;
    v_api_key TEXT;
    v_billing_id UUID;
BEGIN
    -- Generate Argon2id hash for password: EnterpriseDemo2025!
    v_password_hash := '$argon2id$v=19$m=65536,t=4,p=1$L0FvN3ZpT3hDRHVqOXROQQ$q8C5EBKya8XuwgHiadzeP/MMJWCaEkSvUQvm0CVu/9U';
    v_api_key := 'vb_enterprise_d1dc4d1ac4a04cb51feeaf16e9e4afa3ab1cdbcace6afdac79757536976fe7d5';

    -- Check if enterprise user exists
    SELECT id INTO v_user_id FROM users WHERE email = 'enterprise@veribits.com';

    IF v_user_id IS NULL THEN
        -- Create new enterprise user
        INSERT INTO users (email, password_hash, status, email_verified)
        VALUES ('enterprise@veribits.com', v_password_hash, 'active', true)
        RETURNING id INTO v_user_id;

        RAISE NOTICE 'Created enterprise user: enterprise@veribits.com (ID: %)', v_user_id;

        -- Create API key
        INSERT INTO api_keys (user_id, key, name, revoked)
        VALUES (v_user_id, v_api_key, 'Default API Key', false);

        RAISE NOTICE 'Created API key: %', v_api_key;

        -- Create billing account with enterprise plan
        INSERT INTO billing_accounts (user_id, plan, currency)
        VALUES (v_user_id, 'enterprise', 'USD')
        RETURNING id INTO v_billing_id;

        RAISE NOTICE 'Created billing account with enterprise plan (ID: %)', v_billing_id;

        -- Create quota (unlimited for enterprise = 1000000 per month)
        INSERT INTO quotas (user_id, period, allowance, used)
        VALUES (v_user_id, 'monthly', 1000000, 0);

        RAISE NOTICE 'Created monthly quota: 1,000,000 requests';

    ELSE
        RAISE NOTICE 'Enterprise user already exists (ID: %)', v_user_id;

        -- Update to enterprise plan just in case
        UPDATE billing_accounts SET plan = 'enterprise' WHERE user_id = v_user_id;
        RAISE NOTICE 'Updated to enterprise plan';
    END IF;

    -- Also ensure straticus1@gmail.com is on free plan
    UPDATE billing_accounts ba
    SET plan = 'free'
    FROM users u
    WHERE ba.user_id = u.id
    AND u.email = 'straticus1@gmail.com';

    RAISE NOTICE 'Updated straticus1@gmail.com to free plan';

END $$;
