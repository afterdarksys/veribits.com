-- Pro Subscriptions and Licenses (PostgreSQL)
-- Migration: 020
-- Description: Add Pro subscription system with license keys

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

-- Insert a test Pro license for development
-- License: VBPRO-DEV-TEST-0000000000000000
-- User: straticus1@gmail.com (ID: 1)
INSERT INTO pro_licenses (user_id, license_key, plan, status, expires_at)
VALUES (
    1,
    'VBPRO-DEV-TEST-0000000000000000',
    'pro',
    'active',
    NOW() + INTERVAL '1 year'
) ON CONFLICT (license_key) DO NOTHING;
