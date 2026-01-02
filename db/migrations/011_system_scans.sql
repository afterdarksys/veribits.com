-- Migration 011: System Scans and File Hashes
-- Purpose: Store system scan results from the VeriBits system client
-- Author: After Dark Systems
-- Date: 2025-10-27

BEGIN;

-- System scans table (stores metadata about each scan)
CREATE TABLE IF NOT EXISTS system_scans (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,

    -- System identification
    system_name VARCHAR(255) NOT NULL,
    system_ip VARCHAR(45),  -- IPv6 support
    system_public_ip VARCHAR(45),

    -- System information
    os_type VARCHAR(50) NOT NULL,
    os_version VARCHAR(255),
    hash_algorithms VARCHAR(100)[] DEFAULT ARRAY['sha512']::VARCHAR[],

    -- Scan metadata
    scan_date TIMESTAMP NOT NULL,
    total_files INTEGER NOT NULL DEFAULT 0,
    total_errors INTEGER NOT NULL DEFAULT 0,
    total_directories INTEGER NOT NULL DEFAULT 0,

    -- Processing status
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'completed', 'failed')),

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP,

    -- Indexing for fast lookups
    CONSTRAINT unique_system_scan UNIQUE (user_id, system_name, scan_date)
);

-- File hashes table (stores individual file hash records)
CREATE TABLE IF NOT EXISTS file_hashes (
    id BIGSERIAL PRIMARY KEY,
    scan_id INTEGER NOT NULL REFERENCES system_scans(id) ON DELETE CASCADE,

    -- File information
    directory_name TEXT NOT NULL,
    file_name TEXT NOT NULL,

    -- Hash values (support multiple algorithms)
    file_hash_sha256 VARCHAR(64),  -- SHA256 = 64 hex chars
    file_hash_sha512 VARCHAR(128), -- SHA512 = 128 hex chars

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexing for hash lookups
    CONSTRAINT file_hash_unique UNIQUE (scan_id, file_name)
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_system_scans_user_id ON system_scans(user_id);
CREATE INDEX IF NOT EXISTS idx_system_scans_system_name ON system_scans(system_name);
CREATE INDEX IF NOT EXISTS idx_system_scans_scan_date ON system_scans(scan_date DESC);
CREATE INDEX IF NOT EXISTS idx_system_scans_status ON system_scans(status);
CREATE INDEX IF NOT EXISTS idx_system_scans_created_at ON system_scans(created_at DESC);

CREATE INDEX IF NOT EXISTS idx_file_hashes_scan_id ON file_hashes(scan_id);
CREATE INDEX IF NOT EXISTS idx_file_hashes_sha256 ON file_hashes(file_hash_sha256) WHERE file_hash_sha256 IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_file_hashes_sha512 ON file_hashes(file_hash_sha512) WHERE file_hash_sha512 IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_file_hashes_directory ON file_hashes(directory_name);

-- Hash lookup table for malware/threat detection (optional future feature)
CREATE TABLE IF NOT EXISTS known_threat_hashes (
    id SERIAL PRIMARY KEY,
    hash_value VARCHAR(128) NOT NULL UNIQUE,
    hash_algorithm VARCHAR(20) NOT NULL CHECK (hash_algorithm IN ('sha256', 'sha512', 'md5', 'sha1')),
    threat_type VARCHAR(50) NOT NULL,  -- malware, virus, trojan, etc.
    threat_name VARCHAR(255),
    severity VARCHAR(20) DEFAULT 'medium' CHECK (severity IN ('critical', 'high', 'medium', 'low', 'info')),
    description TEXT,
    source VARCHAR(100),  -- VirusTotal, NIST, etc.
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_known_threats_hash ON known_threat_hashes(hash_value);
CREATE INDEX IF NOT EXISTS idx_known_threats_algorithm ON known_threat_hashes(hash_algorithm);

-- Update trigger for system_scans updated_at
CREATE OR REPLACE FUNCTION update_system_scans_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER system_scans_updated_at
    BEFORE UPDATE ON system_scans
    FOR EACH ROW
    EXECUTE FUNCTION update_system_scans_updated_at();

-- Grant permissions
GRANT SELECT, INSERT, UPDATE, DELETE ON system_scans TO veribits_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON file_hashes TO veribits_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON known_threat_hashes TO veribits_app;
GRANT USAGE, SELECT ON SEQUENCE system_scans_id_seq TO veribits_app;
GRANT USAGE, SELECT ON SEQUENCE file_hashes_id_seq TO veribits_app;
GRANT USAGE, SELECT ON SEQUENCE known_threat_hashes_id_seq TO veribits_app;

COMMIT;

-- Verification queries
SELECT 'Migration 011 completed successfully' AS status;
SELECT COUNT(*) AS system_scans_count FROM system_scans;
SELECT COUNT(*) AS file_hashes_count FROM file_hashes;
