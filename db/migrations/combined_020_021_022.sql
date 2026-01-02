-- Combined Enterprise Migrations (020, 021, 022)
-- Run all enterprise features migrations in one transaction

BEGIN;

-- ============================================================================
-- Migration 020: Pro Subscriptions
-- ============================================================================
CREATE TABLE IF NOT EXISTS pro_licenses (
    id SERIAL PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    license_key VARCHAR(255) NOT NULL UNIQUE,
    plan VARCHAR(50) NOT NULL DEFAULT 'pro',
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_pro_licenses_license_key ON pro_licenses(license_key);
CREATE INDEX IF NOT EXISTS idx_pro_licenses_user_id ON pro_licenses(user_id);
CREATE INDEX IF NOT EXISTS idx_pro_licenses_status ON pro_licenses(status);

-- ============================================================================
-- Migration 021: OAuth2 & Webhooks
-- ============================================================================
CREATE TABLE IF NOT EXISTS oauth_clients (
    id SERIAL PRIMARY KEY,
    client_id VARCHAR(255) NOT NULL UNIQUE,
    client_secret VARCHAR(255) NOT NULL,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    redirect_uris JSONB NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_oauth_clients_client_id ON oauth_clients(client_id);

CREATE TABLE IF NOT EXISTS oauth_authorization_codes (
    id SERIAL PRIMARY KEY,
    code VARCHAR(255) NOT NULL UNIQUE,
    client_id VARCHAR(255) NOT NULL REFERENCES oauth_clients(client_id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    redirect_uri TEXT NOT NULL,
    scope VARCHAR(255) NOT NULL DEFAULT 'read write',
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_oauth_authorization_codes_code ON oauth_authorization_codes(code);
CREATE INDEX IF NOT EXISTS idx_oauth_authorization_codes_expires ON oauth_authorization_codes(expires_at);

CREATE TABLE IF NOT EXISTS oauth_access_tokens (
    id SERIAL PRIMARY KEY,
    token VARCHAR(255) NOT NULL UNIQUE,
    client_id VARCHAR(255) NOT NULL REFERENCES oauth_clients(client_id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    scope VARCHAR(255) NOT NULL DEFAULT 'read write',
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_oauth_access_tokens_token ON oauth_access_tokens(token);
CREATE INDEX IF NOT EXISTS idx_oauth_access_tokens_expires ON oauth_access_tokens(expires_at);

CREATE TABLE IF NOT EXISTS oauth_refresh_tokens (
    id SERIAL PRIMARY KEY,
    token VARCHAR(255) NOT NULL UNIQUE,
    client_id VARCHAR(255) NOT NULL REFERENCES oauth_clients(client_id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    scope VARCHAR(255) NOT NULL DEFAULT 'read write',
    expires_at TIMESTAMP NOT NULL,
    revoked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_oauth_refresh_tokens_token ON oauth_refresh_tokens(token);
CREATE INDEX IF NOT EXISTS idx_oauth_refresh_tokens_expires ON oauth_refresh_tokens(expires_at);

CREATE TABLE IF NOT EXISTS webhooks (
    id SERIAL PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    url TEXT NOT NULL,
    events JSONB NOT NULL,
    secret VARCHAR(255) NOT NULL,
    description VARCHAR(255) DEFAULT '',
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_triggered_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_webhooks_user_id ON webhooks(user_id);
CREATE INDEX IF NOT EXISTS idx_webhooks_status ON webhooks(status);

CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id SERIAL PRIMARY KEY,
    webhook_id INTEGER NOT NULL REFERENCES webhooks(id) ON DELETE CASCADE,
    event VARCHAR(100) NOT NULL,
    payload TEXT NOT NULL,
    response_code INTEGER,
    response_body TEXT,
    delivered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_webhook_deliveries_webhook_id ON webhook_deliveries(webhook_id);
CREATE INDEX IF NOT EXISTS idx_webhook_deliveries_delivered_at ON webhook_deliveries(delivered_at);

-- ============================================================================
-- Migration 022: Malware Detonation
-- ============================================================================
CREATE TABLE IF NOT EXISTS malware_submissions (
    id SERIAL PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    file_name VARCHAR(255) NOT NULL,
    file_hash VARCHAR(64) NOT NULL,
    file_size BIGINT NOT NULL,
    cuckoo_task_id INTEGER,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    priority SMALLINT DEFAULT 1,
    timeout INTEGER DEFAULT 120,
    enable_network BOOLEAN DEFAULT FALSE,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_malware_submissions_user_id ON malware_submissions(user_id);
CREATE INDEX IF NOT EXISTS idx_malware_submissions_file_hash ON malware_submissions(file_hash);
CREATE INDEX IF NOT EXISTS idx_malware_submissions_status ON malware_submissions(status);
CREATE INDEX IF NOT EXISTS idx_malware_submissions_submitted_at ON malware_submissions(submitted_at);

CREATE TABLE IF NOT EXISTS malware_analysis_results (
    id SERIAL PRIMARY KEY,
    submission_id INTEGER NOT NULL REFERENCES malware_submissions(id) ON DELETE CASCADE,
    score INTEGER,
    threats_detected INTEGER DEFAULT 0,
    signatures_matched TEXT,
    network_activity JSONB,
    file_operations JSONB,
    registry_operations JSONB,
    process_tree JSONB,
    iocs JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_malware_analysis_results_submission_id ON malware_analysis_results(submission_id);
CREATE INDEX IF NOT EXISTS idx_malware_analysis_results_score ON malware_analysis_results(score);

CREATE TABLE IF NOT EXISTS malware_screenshots (
    id SERIAL PRIMARY KEY,
    submission_id INTEGER NOT NULL REFERENCES malware_submissions(id) ON DELETE CASCADE,
    screenshot_path VARCHAR(255) NOT NULL,
    timestamp INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_malware_screenshots_submission_id ON malware_screenshots(submission_id);

-- ============================================================================
-- Insert test data
-- ============================================================================
-- Get straticus1@gmail.com user_id for test data
DO $$
DECLARE
    v_user_id UUID;
BEGIN
    SELECT id INTO v_user_id FROM users WHERE email = 'straticus1@gmail.com' LIMIT 1;

    IF v_user_id IS NOT NULL THEN
        -- Insert test Pro license
        INSERT INTO pro_licenses (user_id, license_key, plan, status, expires_at)
        VALUES (v_user_id, 'VBPRO-DEV-TEST-0000000000000000', 'pro', 'active', NOW() + INTERVAL '1 year')
        ON CONFLICT (license_key) DO NOTHING;

        -- Insert test OAuth client
        INSERT INTO oauth_clients (client_id, client_secret, user_id, name, redirect_uris)
        VALUES (
            'vb_zapier_test_client_0000000000',
            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            v_user_id,
            'Zapier Integration',
            '["https://zapier.com/dashboard/auth/oauth/return/App123456CLI/", "https://zapier.com/dashboard/auth/oauth/return/App123456API/"]'::jsonb
        )
        ON CONFLICT (client_id) DO NOTHING;
    END IF;
END $$;

COMMIT;

-- Verification
SELECT 'Tables created successfully:' as status;
SELECT tablename FROM pg_tables
WHERE schemaname = 'public'
  AND tablename IN ('pro_licenses', 'oauth_clients', 'oauth_authorization_codes',
                    'oauth_access_tokens', 'oauth_refresh_tokens', 'webhooks',
                    'webhook_deliveries', 'malware_submissions', 'malware_analysis_results',
                    'malware_screenshots')
ORDER BY tablename;
