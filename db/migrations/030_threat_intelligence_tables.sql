-- Threat Intelligence Tables Migration
-- Created: 2026-01-12
-- Description: Tables for threat intelligence, IOCs, and YARA scans

-- Threat lookups history
CREATE TABLE IF NOT EXISTS threat_lookups (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    hash VARCHAR(64) NOT NULL,
    hash_type VARCHAR(10) NOT NULL, -- md5, sha1, sha256
    results JSONB NOT NULL,
    threat_score INTEGER NOT NULL DEFAULT 0,
    is_malicious BOOLEAN DEFAULT FALSE,
    sources TEXT[], -- Array of sources checked
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_threat_lookups_hash ON threat_lookups(hash);
CREATE INDEX idx_threat_lookups_user_id ON threat_lookups(user_id);
CREATE INDEX idx_threat_lookups_created_at ON threat_lookups(created_at);
CREATE INDEX idx_threat_lookups_threat_score ON threat_lookups(threat_score);

-- YARA scans history
CREATE TABLE IF NOT EXISTS yara_scans (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    file_path TEXT NOT NULL,
    file_hash VARCHAR(64),
    matches JSONB NOT NULL DEFAULT '[]'::jsonb,
    rules_used TEXT[] NOT NULL,
    is_malicious BOOLEAN DEFAULT FALSE,
    scan_duration_ms INTEGER,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_yara_scans_user_id ON yara_scans(user_id);
CREATE INDEX idx_yara_scans_file_hash ON yara_scans(file_hash);
CREATE INDEX idx_yara_scans_created_at ON yara_scans(created_at);

-- IOC (Indicators of Compromise) database
CREATE TABLE IF NOT EXISTS iocs (
    id SERIAL PRIMARY KEY,
    ioc_type VARCHAR(20) NOT NULL, -- ip, domain, hash, url, email
    ioc_value TEXT NOT NULL,
    threat_type VARCHAR(50), -- malware, phishing, c2, ransomware, apt
    threat_family VARCHAR(100),
    severity VARCHAR(20) DEFAULT 'medium', -- low, medium, high, critical
    confidence INTEGER DEFAULT 50, -- 0-100
    source VARCHAR(100) NOT NULL, -- Source of IOC
    tags TEXT[],
    metadata JSONB DEFAULT '{}'::jsonb,
    first_seen TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(ioc_type, ioc_value, source)
);

CREATE INDEX idx_iocs_type ON iocs(ioc_type);
CREATE INDEX idx_iocs_value ON iocs(ioc_value);
CREATE INDEX idx_iocs_threat_type ON iocs(threat_type);
CREATE INDEX idx_iocs_severity ON iocs(severity);
CREATE INDEX idx_iocs_first_seen ON iocs(first_seen);
CREATE INDEX idx_iocs_is_active ON iocs(is_active);
CREATE INDEX idx_iocs_tags ON iocs USING gin(tags);

-- File fingerprints cache
CREATE TABLE IF NOT EXISTS file_fingerprints (
    id SERIAL PRIMARY KEY,
    file_hash VARCHAR(64) NOT NULL UNIQUE,
    file_size BIGINT NOT NULL,
    file_type VARCHAR(100),
    entropy DECIMAL(5,3),
    packers TEXT[],
    suspicious_strings TEXT[],
    embedded_urls TEXT[],
    embedded_ips TEXT[],
    pe_info JSONB,
    risk_score INTEGER DEFAULT 0,
    risk_indicators TEXT[],
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_fingerprints_hash ON file_fingerprints(file_hash);
CREATE INDEX idx_fingerprints_entropy ON file_fingerprints(entropy);
CREATE INDEX idx_fingerprints_risk_score ON file_fingerprints(risk_score);
CREATE INDEX idx_fingerprints_created_at ON file_fingerprints(created_at);

-- YARA rules library
CREATE TABLE IF NOT EXISTS yara_rules (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    rule_text TEXT NOT NULL,
    category VARCHAR(50), -- malware, apt, ransomware, packer, etc
    tags TEXT[],
    author VARCHAR(100),
    reference TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_yara_rules_name ON yara_rules(name);
CREATE INDEX idx_yara_rules_category ON yara_rules(category);
CREATE INDEX idx_yara_rules_is_active ON yara_rules(is_active);
CREATE INDEX idx_yara_rules_tags ON yara_rules USING gin(tags);

-- Threat intelligence sources configuration
CREATE TABLE IF NOT EXISTS threat_intel_sources (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    source_type VARCHAR(50) NOT NULL, -- api, feed, manual
    api_endpoint TEXT,
    api_key_encrypted TEXT,
    update_frequency_hours INTEGER DEFAULT 24,
    last_update TIMESTAMP,
    is_enabled BOOLEAN DEFAULT TRUE,
    config JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_threat_sources_name ON threat_intel_sources(name);
CREATE INDEX idx_threat_sources_enabled ON threat_intel_sources(is_enabled);

-- Threat intelligence API quotas
CREATE TABLE IF NOT EXISTS threat_intel_quotas (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    source_name VARCHAR(100) NOT NULL,
    quota_limit INTEGER NOT NULL,
    quota_used INTEGER DEFAULT 0,
    reset_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, source_name)
);

CREATE INDEX idx_threat_quotas_user_id ON threat_intel_quotas(user_id);
CREATE INDEX idx_threat_quotas_reset_at ON threat_intel_quotas(reset_at);

-- Insert default YARA rules
INSERT INTO yara_rules (name, description, rule_text, category, tags, author) VALUES
('generic_malware', 'Generic malware detection', 'rule generic_malware { condition: false }', 'malware', ARRAY['generic', 'malware'], 'VeriBits'),
('ransomware_detection', 'Ransomware behavioral detection', 'rule ransomware { condition: false }', 'ransomware', ARRAY['ransomware', 'crypto'], 'VeriBits'),
('apt_indicators', 'APT (Advanced Persistent Threat) indicators', 'rule apt { condition: false }', 'apt', ARRAY['apt', 'targeted'], 'VeriBits')
ON CONFLICT (name) DO NOTHING;

-- Insert default threat intelligence sources
INSERT INTO threat_intel_sources (name, source_type, api_endpoint, is_enabled) VALUES
('VirusTotal', 'api', 'https://www.virustotal.com/api/v3', TRUE),
('MalwareBazaar', 'api', 'https://mb-api.abuse.ch/api/v1', TRUE),
('Hybrid Analysis', 'api', 'https://www.hybrid-analysis.com/api/v2', TRUE),
('AlienVault OTX', 'feed', 'https://otx.alienvault.com/api/v1', TRUE)
ON CONFLICT (name) DO NOTHING;

-- Comments
COMMENT ON TABLE threat_lookups IS 'History of threat intelligence lookups for hashes';
COMMENT ON TABLE yara_scans IS 'History of YARA rule scans performed';
COMMENT ON TABLE iocs IS 'Indicators of Compromise database';
COMMENT ON TABLE file_fingerprints IS 'Cached file fingerprints and analysis results';
COMMENT ON TABLE yara_rules IS 'YARA rules library for malware detection';
COMMENT ON TABLE threat_intel_sources IS 'Configuration for threat intelligence API sources';
COMMENT ON TABLE threat_intel_quotas IS 'API quota tracking per user per source';
