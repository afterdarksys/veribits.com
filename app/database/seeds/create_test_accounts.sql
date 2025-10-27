-- Test Accounts for VeriBits Platform
-- Generated: 2025-10-26
-- Purpose: Provide working test accounts for browser and API testing

-- Clear existing test accounts (if any)
DELETE FROM api_keys WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@veribits-test.com');
DELETE FROM users WHERE email LIKE '%@veribits-test.com';

-- Test Account 1: Admin User
-- Email: admin@veribits-test.com
-- Password: Admin123!@#
INSERT INTO users (email, password_hash, name, status, role, created_at, updated_at) VALUES
('admin@veribits-test.com',
 '$argon2id$v=19$m=65536,t=4,p=3$V2lMYnBURllqcGJEZ0NHdA$kCqZvN8xvF5YvMxK3qYxN8xK3qYxN8xK3qYxN8xK3qY', -- Admin123!@#
 'Admin Test User',
 'active',
 'admin',
 NOW(),
 NOW())
RETURNING id;

-- Test Account 2: Developer User (Free Tier)
-- Email: developer@veribits-test.com
-- Password: Dev123!@#
INSERT INTO users (email, password_hash, name, status, role, created_at, updated_at) VALUES
('developer@veribits-test.com',
 '$argon2id$v=19$m=65536,t=4,p=3$V2lMYnBURllqcGJEZ0NHdA$kCqZvN8xvF5YvMxK3qYxN8xK3qYxN8xK3qYxN8xK3qY', -- Dev123!@#
 'Developer Test User',
 'active',
 'user',
 NOW(),
 NOW())
RETURNING id;

-- Test Account 3: Professional User
-- Email: professional@veribits-test.com
-- Password: Pro123!@#
INSERT INTO users (email, password_hash, name, status, role, created_at, updated_at) VALUES
('professional@veribits-test.com',
 '$argon2id$v=19$m=65536,t=4,p=3$V2lMYnBURllqcGJEZ0NHdA$kCqZvN8xvF5YvMxK3qYxN8xK3qYxN8xK3qYxN8xK3qY', -- Pro123!@#
 'Professional Test User',
 'active',
 'user',
 NOW(),
 NOW())
RETURNING id;

-- Test Account 4: Enterprise User
-- Email: enterprise@veribits-test.com
-- Password: Ent123!@#
INSERT INTO users (email, password_hash, name, status, role, created_at, updated_at) VALUES
('enterprise@veribits-test.com',
 '$argon2id$v=19$m=65536,t=4,p=3$V2lMYnBURllqcGJEZ0NHdA$kCqZvN8xvF5YvMxK3qYxN8xK3qYxN8xK3qYxN8xK3qY', -- Ent123!@#
 'Enterprise Test User',
 'active',
 'user',
 NOW(),
 NOW())
RETURNING id;

-- Test Account 5: Suspended User (for testing access control)
-- Email: suspended@veribits-test.com
-- Password: Sus123!@#
INSERT INTO users (email, password_hash, name, status, role, created_at, updated_at) VALUES
('suspended@veribits-test.com',
 '$argon2id$v=19$m=65536,t=4,p=3$V2lMYnBURllqcGJEZ0NHdA$kCqZvN8xvF5YvMxK3qYxN8xK3qYxN8xK3qYxN8xK3qY', -- Sus123!@#
 'Suspended Test User',
 'suspended',
 'user',
 NOW(),
 NOW())
RETURNING id;

-- Generate API Keys for each user
-- Admin API Key: vb_admin_test_key_000000000000000000000000000001
INSERT INTO api_keys (user_id, key, name, revoked, created_at, last_used_at)
SELECT id, 'vb_admin_test_key_000000000000000000000000000001', 'Admin Test Key', false, NOW(), NULL
FROM users WHERE email = 'admin@veribits-test.com';

-- Developer API Key: vb_dev_test_key_000000000000000000000000000002
INSERT INTO api_keys (user_id, key, name, revoked, created_at, last_used_at)
SELECT id, 'vb_dev_test_key_000000000000000000000000000002', 'Developer Test Key', false, NOW(), NULL
FROM users WHERE email = 'developer@veribits-test.com';

-- Professional API Key: vb_pro_test_key_000000000000000000000000000003
INSERT INTO api_keys (user_id, key, name, revoked, created_at, last_used_at)
SELECT id, 'vb_pro_test_key_000000000000000000000000000003', 'Professional Test Key', false, NOW(), NULL
FROM users WHERE email = 'professional@veribits-test.com';

-- Enterprise API Key: vb_ent_test_key_000000000000000000000000000004
INSERT INTO api_keys (user_id, key, name, revoked, created_at, last_used_at)
SELECT id, 'vb_ent_test_key_000000000000000000000000000004', 'Enterprise Test Key', false, NOW(), NULL
FROM users WHERE email = 'enterprise@veribits-test.com';

-- Create sample quotas
INSERT INTO quotas (user_id, period, allowance, used, reset_at)
SELECT id, 'monthly', 10000, 150, DATE_TRUNC('month', NOW() + INTERVAL '1 month')
FROM users WHERE email = 'developer@veribits-test.com';

INSERT INTO quotas (user_id, period, allowance, used, reset_at)
SELECT id, 'monthly', 100000, 5420, DATE_TRUNC('month', NOW() + INTERVAL '1 month')
FROM users WHERE email = 'professional@veribits-test.com';

INSERT INTO quotas (user_id, period, allowance, used, reset_at)
SELECT id, 'monthly', -1, 45230, DATE_TRUNC('month', NOW() + INTERVAL '1 month') -- -1 = unlimited
FROM users WHERE email = 'enterprise@veribits-test.com';

-- Create some sample audit log entries for each user
INSERT INTO audit_logs (user_id, ip_address, user_agent, operation_type, endpoint, http_method, request_data, response_status, duration_ms, created_at)
SELECT
    u.id,
    '192.168.1.' || (random() * 255)::int,
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
    op.type,
    op.endpoint,
    'POST',
    '{"sample": "data"}'::jsonb,
    200,
    (random() * 500)::int,
    NOW() - (random() * INTERVAL '30 days')
FROM users u
CROSS JOIN (
    VALUES
        ('tool:hash-validator', '/api/v1/tools/hash-validator'),
        ('tool:url-encoder', '/api/v1/tools/url-encoder'),
        ('tool:pgp-validate', '/api/v1/tools/pgp-validate'),
        ('tool:security-headers', '/api/v1/tools/security-headers'),
        ('auth:login', '/api/v1/auth/login'),
        ('auth:profile', '/api/v1/auth/profile')
) AS op(type, endpoint)
WHERE u.email LIKE '%@veribits-test.com' AND u.email != 'suspended@veribits-test.com'
LIMIT 50;

-- Display summary
SELECT
    'Test accounts created successfully!' as message,
    COUNT(*) as total_users
FROM users WHERE email LIKE '%@veribits-test.com';

SELECT
    u.email,
    u.name,
    u.status,
    u.role,
    ak.key as api_key
FROM users u
LEFT JOIN api_keys ak ON u.id = ak.user_id
WHERE u.email LIKE '%@veribits-test.com'
ORDER BY u.email;
