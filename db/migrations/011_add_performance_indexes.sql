-- Migration 011: Add Performance Indexes
-- Created: 2025-10-27
-- Purpose: Optimize database queries for high-traffic endpoints

-- =============================================
-- Rate Limiting Indexes
-- =============================================

-- Speed up rate limit lookups by identifier and timestamp
CREATE INDEX IF NOT EXISTS idx_rate_limits_identifier_timestamp
ON rate_limits(identifier, timestamp)
WHERE timestamp > NOW() - INTERVAL '1 hour';

-- Partial index for active rate limits only
CREATE INDEX IF NOT EXISTS idx_rate_limits_active
ON rate_limits(identifier)
WHERE timestamp > NOW() - INTERVAL '1 hour';

-- =============================================
-- API Key Indexes
-- =============================================

-- Speed up API key validation (most frequent query)
CREATE INDEX IF NOT EXISTS idx_api_keys_key_revoked
ON api_keys(key, revoked)
WHERE revoked = false;

-- Index for user's API keys listing
CREATE INDEX IF NOT EXISTS idx_api_keys_user_created
ON api_keys(user_id, created_at DESC)
WHERE revoked = false;

-- =============================================
-- User Indexes
-- =============================================

-- Speed up login queries
CREATE INDEX IF NOT EXISTS idx_users_email_status
ON users(LOWER(email), status)
WHERE status = 'active';

-- Index for user ID lookups
CREATE INDEX IF NOT EXISTS idx_users_id_status
ON users(id, status)
WHERE status = 'active';

-- =============================================
-- Audit Log Indexes
-- =============================================

-- Speed up audit log queries by user and operation
CREATE INDEX IF NOT EXISTS idx_audit_logs_user_operation
ON audit_logs(user_id, operation, created_at DESC);

-- Index for security audit queries
CREATE INDEX IF NOT EXISTS idx_audit_logs_operation_created
ON audit_logs(operation, created_at DESC)
WHERE operation IN ('login_failed', 'api_key_invalid', 'rate_limit_exceeded');

-- Partial index for recent audit logs only (last 30 days)
CREATE INDEX IF NOT EXISTS idx_audit_logs_recent
ON audit_logs(created_at DESC, user_id)
WHERE created_at > NOW() - INTERVAL '30 days';

-- =============================================
-- Webhook Indexes
-- =============================================

-- Speed up webhook delivery queries
CREATE INDEX IF NOT EXISTS idx_webhook_deliveries_webhook_created
ON webhook_deliveries(webhook_id, created_at DESC);

-- Index for failed webhook deliveries (for retry logic)
CREATE INDEX IF NOT EXISTS idx_webhook_deliveries_failed
ON webhook_deliveries(webhook_id, status, next_retry_at)
WHERE status = 'failed' AND next_retry_at IS NOT NULL;

-- =============================================
-- Billing & Quota Indexes
-- =============================================

-- Speed up quota checks
CREATE INDEX IF NOT EXISTS idx_quotas_user_period
ON quotas(user_id, period);

-- Index for usage tracking
CREATE INDEX IF NOT EXISTS idx_usage_logs_user_created
ON usage_logs(user_id, created_at DESC);

-- =============================================
-- Verification Indexes
-- =============================================

-- Speed up verification history queries
CREATE INDEX IF NOT EXISTS idx_verifications_user_created
ON verifications(user_id, created_at DESC);

-- Index for verification types
CREATE INDEX IF NOT EXISTS idx_verifications_type_status
ON verifications(verification_type, status, created_at DESC);

-- =============================================
-- Session Indexes
-- =============================================

-- Speed up session lookups
CREATE INDEX IF NOT EXISTS idx_sessions_token_expires
ON sessions(token, expires_at)
WHERE expires_at > NOW();

-- Cleanup expired sessions
CREATE INDEX IF NOT EXISTS idx_sessions_expired
ON sessions(expires_at)
WHERE expires_at < NOW();

-- =============================================
-- Analytics & Reporting Indexes
-- =============================================

-- Speed up daily usage reports
CREATE INDEX IF NOT EXISTS idx_usage_logs_date_user
ON usage_logs(DATE(created_at), user_id);

-- Speed up tool usage analytics
CREATE INDEX IF NOT EXISTS idx_usage_logs_tool_date
ON usage_logs(tool_name, DATE(created_at));

-- =============================================
-- Cleanup & Maintenance
-- =============================================

-- Analyze tables to update statistics
ANALYZE rate_limits;
ANALYZE api_keys;
ANALYZE users;
ANALYZE audit_logs;
ANALYZE webhook_deliveries;
ANALYZE quotas;
ANALYZE usage_logs;
ANALYZE verifications;

-- Display index sizes
SELECT
    schemaname,
    tablename,
    indexname,
    pg_size_pretty(pg_relation_size(indexrelid)) AS index_size
FROM pg_stat_user_indexes
WHERE schemaname = 'public'
ORDER BY pg_relation_size(indexrelid) DESC;

-- Display table sizes
SELECT
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS total_size,
    pg_size_pretty(pg_relation_size(schemaname||'.'||tablename)) AS table_size,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename) - pg_relation_size(schemaname||'.'||tablename)) AS indexes_size
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

-- Success message
DO $$
BEGIN
    RAISE NOTICE 'Migration 011 completed successfully!';
    RAISE NOTICE 'Performance indexes created for all high-traffic tables';
    RAISE NOTICE 'Run EXPLAIN ANALYZE on your queries to verify index usage';
END $$;
