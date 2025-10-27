-- Comprehensive audit log table for tracking all operations
CREATE TABLE IF NOT EXISTS audit_logs (
    id SERIAL PRIMARY KEY,

    -- User identification
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    api_key_id INTEGER REFERENCES api_keys(id) ON DELETE SET NULL,
    session_id VARCHAR(64),
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,

    -- Operation details
    operation_type VARCHAR(100) NOT NULL, -- e.g., 'tool:hash-validator', 'api:verify', 'auth:login'
    endpoint VARCHAR(255) NOT NULL, -- e.g., '/api/v1/tools/hash-validator'
    http_method VARCHAR(10) NOT NULL, -- GET, POST, PUT, DELETE

    -- Request/Response data (sanitized - no passwords/secrets)
    request_data JSONB, -- Sanitized request parameters
    response_status INTEGER, -- HTTP status code
    response_data JSONB, -- Sanitized response data (truncated if large)

    -- File metadata (when files are involved)
    files_metadata JSONB, -- Array of {name, size, hash, mime_type}

    -- Timing and performance
    duration_ms INTEGER, -- Request duration in milliseconds

    -- Error tracking
    error_message TEXT,
    error_code VARCHAR(50),
    stack_trace TEXT,

    -- Rate limiting info
    rate_limit_hit BOOLEAN DEFAULT false,
    quota_remaining INTEGER,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes for performance
    CONSTRAINT audit_logs_user_id_idx CHECK (user_id IS NOT NULL OR ip_address IS NOT NULL)
);

-- Indexes for common queries
CREATE INDEX IF NOT EXISTS idx_audit_logs_user_id ON audit_logs(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_logs_ip_address ON audit_logs(ip_address, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_logs_operation_type ON audit_logs(operation_type, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON audit_logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_logs_session_id ON audit_logs(session_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_endpoint ON audit_logs(endpoint);

-- Composite index for user activity queries
CREATE INDEX IF NOT EXISTS idx_audit_logs_user_activity ON audit_logs(user_id, operation_type, created_at DESC);

-- GIN index for JSONB queries
CREATE INDEX IF NOT EXISTS idx_audit_logs_request_data ON audit_logs USING GIN(request_data);
CREATE INDEX IF NOT EXISTS idx_audit_logs_files_metadata ON audit_logs USING GIN(files_metadata);

-- Add comment
COMMENT ON TABLE audit_logs IS 'Comprehensive audit log for all API operations, tool usage, and user activity';
