-- Migration 025: Performance Indexes
-- Date: 2025-12-29
-- Description: Add missing indexes for better query performance

-- Rate limits table indexes
CREATE INDEX IF NOT EXISTS idx_rate_limits_identifier_ts ON rate_limits(identifier, timestamp);
CREATE INDEX IF NOT EXISTS idx_rate_limits_cleanup ON rate_limits(timestamp);

-- Audit logs indexes
CREATE INDEX IF NOT EXISTS idx_audit_logs_user_id_created ON audit_logs(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_logs_operation ON audit_logs(operation_type);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created_desc ON audit_logs(created_at DESC);

-- API keys indexes (some may already exist)
CREATE INDEX IF NOT EXISTS idx_api_keys_key ON api_keys(key);
CREATE INDEX IF NOT EXISTS idx_api_keys_user_revoked ON api_keys(user_id, revoked);

-- Verifications history
CREATE INDEX IF NOT EXISTS idx_verifications_user_created ON verifications(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_verifications_hash ON verifications(hash);

-- Quotas
CREATE INDEX IF NOT EXISTS idx_quotas_user_period ON quotas(user_id, period);

-- Webhooks
CREATE INDEX IF NOT EXISTS idx_webhooks_user_active ON webhooks(user_id, active);
CREATE INDEX IF NOT EXISTS idx_webhook_deliveries_webhook_created ON webhook_deliveries(webhook_id, created_at DESC);

-- System scans
CREATE INDEX IF NOT EXISTS idx_system_scans_user_created ON system_scans(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_system_scan_hashes_scan ON system_scan_hashes(scan_id);

-- Log the migration
DO $$
BEGIN
    RAISE NOTICE 'Migration 025: Performance indexes added';
END $$;
