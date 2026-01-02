#!/bin/bash
################################################################################
# VeriBits Enterprise Features - Database Migration Script
# Run this script from an EC2 instance that has access to the RDS database
################################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Database Configuration
DB_HOST="${DB_HOST:-nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com}"
DB_USER="${DB_USER:-nitetext}"
DB_NAME="${DB_NAME:-veribits}"
DB_PASSWORD="${DB_PASSWORD:-NiteText2025!SecureProd}"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}VeriBits Enterprise Features Migration${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Test connection
echo -e "${YELLOW}[1/4] Testing database connection...${NC}"
if PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "SELECT version();" > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Database connection successful${NC}"
else
    echo -e "${RED}✗ Database connection failed${NC}"
    echo -e "${RED}Please check your database credentials and network access${NC}"
    exit 1
fi

# Migration 020: Pro Subscriptions
echo ""
echo -e "${YELLOW}[2/4] Running Migration 020: Pro Subscriptions${NC}"
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" << 'EOF'
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
-- User: straticus1@gmail.com (ID from users table)
INSERT INTO pro_licenses (user_id, license_key, plan, status, expires_at)
SELECT id, 'VBPRO-DEV-TEST-0000000000000000', 'pro', 'active', NOW() + INTERVAL '1 year'
FROM users WHERE email = 'straticus1@gmail.com'
ON CONFLICT (license_key) DO NOTHING;
EOF

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Migration 020 completed${NC}"
else
    echo -e "${RED}✗ Migration 020 failed${NC}"
    exit 1
fi

# Migration 021: OAuth2 & Webhooks
echo ""
echo -e "${YELLOW}[3/4] Running Migration 021: OAuth2 & Webhooks${NC}"
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" << 'EOF'
-- OAuth2 and Webhooks System (PostgreSQL)
-- Migration: 021
-- Description: Add OAuth2 authentication and webhooks for Zapier/n8n integration

-- OAuth Clients (Zapier, n8n, custom integrations)
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

-- OAuth Authorization Codes
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

-- OAuth Access Tokens
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

-- OAuth Refresh Tokens
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

-- Webhooks (for Zapier triggers, n8n triggers, etc.)
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

-- Webhook Delivery Log
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

-- Create a test OAuth client for Zapier development
INSERT INTO oauth_clients (client_id, client_secret, user_id, name, redirect_uris)
SELECT
    'vb_zapier_test_client_0000000000',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    id,
    'Zapier Integration',
    '[\"https://zapier.com/dashboard/auth/oauth/return/App123456CLI/\", \"https://zapier.com/dashboard/auth/oauth/return/App123456API/\"]'::jsonb
FROM users WHERE email = 'straticus1@gmail.com'
ON CONFLICT (client_id) DO NOTHING;
EOF

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Migration 021 completed${NC}"
else
    echo -e "${RED}✗ Migration 021 failed${NC}"
    exit 1
fi

# Migration 022: Malware Detonation
echo ""
echo -e "${YELLOW}[4/4] Running Migration 022: Malware Detonation${NC}"
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" << 'EOF'
-- Malware Detonation / Sandbox Analysis (PostgreSQL)
-- Migration: 022
-- Description: Add malware analysis and sandbox detonation tables

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

-- Malware Analysis Results Cache
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

-- Malware Screenshots
CREATE TABLE IF NOT EXISTS malware_screenshots (
    id SERIAL PRIMARY KEY,
    submission_id INTEGER NOT NULL REFERENCES malware_submissions(id) ON DELETE CASCADE,
    screenshot_path VARCHAR(255) NOT NULL,
    timestamp INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_malware_screenshots_submission_id ON malware_screenshots(submission_id);
EOF

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Migration 022 completed${NC}"
else
    echo -e "${RED}✗ Migration 022 failed${NC}"
    exit 1
fi

# Verify tables
echo ""
echo -e "${BLUE}Verifying tables...${NC}"
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" << 'EOF'
SELECT
    tablename,
    CASE
        WHEN tablename IN ('pro_licenses', 'oauth_clients', 'oauth_authorization_codes',
                          'oauth_access_tokens', 'oauth_refresh_tokens', 'webhooks',
                          'webhook_deliveries', 'malware_submissions', 'malware_analysis_results',
                          'malware_screenshots') THEN '✓'
        ELSE ''
    END as status
FROM pg_tables
WHERE schemaname = 'public'
  AND tablename IN ('pro_licenses', 'oauth_clients', 'oauth_authorization_codes',
                   'oauth_access_tokens', 'oauth_refresh_tokens', 'webhooks',
                   'webhook_deliveries', 'malware_submissions', 'malware_analysis_results',
                   'malware_screenshots')
ORDER BY tablename;
EOF

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}✓ All migrations completed successfully!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "${BLUE}New tables created:${NC}"
echo "  • pro_licenses"
echo "  • oauth_clients, oauth_authorization_codes"
echo "  • oauth_access_tokens, oauth_refresh_tokens"
echo "  • webhooks, webhook_deliveries"
echo "  • malware_submissions, malware_analysis_results"
echo "  • malware_screenshots"
echo ""
echo -e "${BLUE}Test credentials created:${NC}"
echo "  • Pro License: VBPRO-DEV-TEST-0000000000000000"
echo "  • OAuth Client: vb_zapier_test_client_0000000000"
echo ""
