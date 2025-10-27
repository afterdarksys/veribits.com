-- Keystore conversion operations log (stores metadata, NOT files)
CREATE TABLE IF NOT EXISTS keystore_conversions (
    id SERIAL PRIMARY KEY,

    -- User identification
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    ip_address VARCHAR(45) NOT NULL,

    -- Operation details
    operation_type VARCHAR(50) NOT NULL, -- 'jks_to_pkcs12', 'pkcs12_to_jks', 'extract_pkcs12', 'extract_pkcs7'

    -- Source file metadata (NOT the file itself)
    source_filename VARCHAR(255) NOT NULL,
    source_filesize INTEGER NOT NULL,
    source_hash VARCHAR(64) NOT NULL, -- SHA-256 hash

    -- Output file metadata
    output_filename VARCHAR(255),
    output_filesize INTEGER,
    output_hash VARCHAR(64),

    -- Extraction details (for PKCS7/PKCS12 extraction)
    extracted_items JSONB, -- Array of {type: 'certificate'/'private_key', subject: '...', issuer: '...'}

    -- Success/Error tracking
    status VARCHAR(20) NOT NULL, -- 'success', 'error'
    error_message TEXT,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_keystore_conversions_user_id ON keystore_conversions(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_keystore_conversions_ip ON keystore_conversions(ip_address, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_keystore_conversions_operation ON keystore_conversions(operation_type);
CREATE INDEX IF NOT EXISTS idx_keystore_conversions_created_at ON keystore_conversions(created_at DESC);

COMMENT ON TABLE keystore_conversions IS 'Metadata log of keystore conversions and extractions (files are NOT stored)';
