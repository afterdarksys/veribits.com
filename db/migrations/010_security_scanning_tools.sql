-- Migration 010: Security Scanning Tools
-- Created: 2025-10-26
-- Description: Tables for IAM, Secrets, Docker, Terraform, K8s, API, SBOM, DB Connection, and Security Headers scanning

-- Generic security scans table (parent table for all scan types)
CREATE TABLE IF NOT EXISTS security_scans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    scan_type VARCHAR(50) NOT NULL, -- 'iam_policy', 'secrets', 'docker_image', 'terraform', 'k8s', 'api', 'sbom', 'db_connection', 'security_headers'
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'scanning', 'completed', 'failed'
    severity VARCHAR(20), -- 'critical', 'high', 'medium', 'low', 'info'
    score INTEGER, -- 0-100 risk/security score
    findings_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,
    metadata JSONB -- Flexible storage for scan-specific data
);

CREATE INDEX IF NOT EXISTS idx_security_scans_user ON security_scans(user_id);
CREATE INDEX IF NOT EXISTS idx_security_scans_type ON security_scans(scan_type);
CREATE INDEX IF NOT EXISTS idx_security_scans_created ON security_scans(created_at DESC);

-- IAM Policy Analysis
CREATE TABLE IF NOT EXISTS iam_policy_scans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    scan_id UUID REFERENCES security_scans(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    policy_name VARCHAR(255),
    policy_document JSONB NOT NULL,
    risk_score INTEGER NOT NULL, -- 0-100
    findings JSONB NOT NULL, -- Array of {severity, issue, recommendation, line}
    has_wildcards BOOLEAN DEFAULT false,
    has_public_access BOOLEAN DEFAULT false,
    has_admin_access BOOLEAN DEFAULT false,
    affected_resources TEXT[],
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_iam_policy_user ON iam_policy_scans(user_id);
CREATE INDEX IF NOT EXISTS idx_iam_policy_score ON iam_policy_scans(risk_score DESC);

-- Secrets Detection
CREATE TABLE IF NOT EXISTS secret_scans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    scan_id UUID REFERENCES security_scans(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    source_type VARCHAR(50), -- 'git', 'docker', 'file', 'text'
    source_name VARCHAR(500),
    secrets_found INTEGER DEFAULT 0,
    secrets JSONB NOT NULL, -- Array of {type, location, is_valid, severity}
    live_validation_performed BOOLEAN DEFAULT false,
    active_secrets_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_secret_scans_user ON secret_scans(user_id);
CREATE INDEX IF NOT EXISTS idx_secret_scans_source ON secret_scans(source_type);

-- Docker Image Scanning
CREATE TABLE IF NOT EXISTS docker_image_scans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    scan_id UUID REFERENCES security_scans(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    image_name VARCHAR(500) NOT NULL,
    image_tag VARCHAR(100),
    image_digest VARCHAR(100),
    registry VARCHAR(100), -- 'dockerhub', 'ecr', 'gcr', 'acr'
    vulnerabilities JSONB NOT NULL, -- {critical: count, high: count, medium: count, low: count}
    cve_list JSONB, -- Array of CVE objects
    base_image VARCHAR(255),
    layers_count INTEGER,
    total_size_bytes BIGINT,
    sbom JSONB, -- Software Bill of Materials
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_docker_scans_user ON docker_image_scans(user_id);
CREATE INDEX IF NOT EXISTS idx_docker_scans_image ON docker_image_scans(image_name);

-- Terraform/IaC Scanning
CREATE TABLE IF NOT EXISTS terraform_scans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    scan_id UUID REFERENCES security_scans(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    iac_type VARCHAR(50), -- 'terraform', 'cloudformation', 'pulumi', 'arm'
    file_name VARCHAR(500),
    file_content TEXT,
    issues JSONB NOT NULL, -- Array of {severity, rule, resource, line, recommendation}
    resources_analyzed INTEGER DEFAULT 0,
    public_resources INTEGER DEFAULT 0,
    unencrypted_resources INTEGER DEFAULT 0,
    compliance_failures JSONB, -- CIS benchmark failures
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_terraform_scans_user ON terraform_scans(user_id);
CREATE INDEX IF NOT EXISTS idx_terraform_scans_type ON terraform_scans(iac_type);

-- Kubernetes Manifest Validation
CREATE TABLE IF NOT EXISTS k8s_manifest_scans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    scan_id UUID REFERENCES security_scans(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    manifest_type VARCHAR(50), -- 'deployment', 'pod', 'service', 'configmap', 'secret'
    manifest_name VARCHAR(255),
    namespace VARCHAR(100),
    manifest_content TEXT,
    issues JSONB NOT NULL, -- Array of security issues
    privileged_containers INTEGER DEFAULT 0,
    host_path_mounts INTEGER DEFAULT 0,
    missing_resource_limits INTEGER DEFAULT 0,
    security_standard VARCHAR(50), -- 'restricted', 'baseline', 'privileged'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_k8s_scans_user ON k8s_manifest_scans(user_id);
CREATE INDEX IF NOT EXISTS idx_k8s_scans_type ON k8s_manifest_scans(manifest_type);

-- API Security Auditing
CREATE TABLE IF NOT EXISTS api_security_scans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    scan_id UUID REFERENCES security_scans(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    api_url VARCHAR(500),
    openapi_spec JSONB,
    spec_version VARCHAR(20), -- '2.0', '3.0', '3.1'
    endpoints_count INTEGER DEFAULT 0,
    issues JSONB NOT NULL, -- OWASP API Top 10 findings
    missing_auth_endpoints INTEGER DEFAULT 0,
    missing_rate_limiting INTEGER DEFAULT 0,
    pii_exposure_risk BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_api_scans_user ON api_security_scans(user_id);
CREATE INDEX IF NOT EXISTS idx_api_scans_url ON api_security_scans(api_url);

-- SBOM Generation
CREATE TABLE IF NOT EXISTS sbom_scans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    scan_id UUID REFERENCES security_scans(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    project_name VARCHAR(255),
    ecosystem VARCHAR(50), -- 'npm', 'pip', 'maven', 'go', 'docker'
    sbom_format VARCHAR(20), -- 'cyclonedx', 'spdx'
    sbom_document JSONB NOT NULL,
    components_count INTEGER DEFAULT 0,
    vulnerabilities JSONB, -- CVE findings
    license_issues JSONB, -- GPL in proprietary software, etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_sbom_scans_user ON sbom_scans(user_id);
CREATE INDEX IF NOT EXISTS idx_sbom_scans_project ON sbom_scans(project_name);

-- Database Connection String Auditing
CREATE TABLE IF NOT EXISTS db_connection_scans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    scan_id UUID REFERENCES security_scans(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    db_type VARCHAR(50), -- 'postgresql', 'mysql', 'mongodb', 'redis'
    connection_string TEXT NOT NULL,
    issues JSONB NOT NULL, -- Security issues found
    has_plaintext_password BOOLEAN DEFAULT false,
    ssl_enabled BOOLEAN DEFAULT false,
    uses_default_port BOOLEAN DEFAULT false,
    public_ip BOOLEAN DEFAULT false,
    recommendations JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_db_connection_scans_user ON db_connection_scans(user_id);
CREATE INDEX IF NOT EXISTS idx_db_connection_scans_type ON db_connection_scans(db_type);

-- Security Headers Analysis
CREATE TABLE IF NOT EXISTS security_header_scans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    scan_id UUID REFERENCES security_scans(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    url VARCHAR(500) NOT NULL,
    grade VARCHAR(2), -- 'A', 'B', 'C', 'D', 'F'
    score INTEGER, -- 0-100
    headers_present JSONB, -- {hsts: true, csp: true, ...}
    headers_missing JSONB, -- Missing security headers
    csp_policy TEXT,
    csp_issues JSONB,
    recommendations JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_security_header_scans_user ON security_header_scans(user_id);
CREATE INDEX IF NOT EXISTS idx_security_header_scans_url ON security_header_scans(url);

-- Scan statistics and analytics
CREATE TABLE IF NOT EXISTS scan_statistics (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    scan_type VARCHAR(50) NOT NULL,
    total_scans INTEGER DEFAULT 0,
    critical_findings INTEGER DEFAULT 0,
    high_findings INTEGER DEFAULT 0,
    medium_findings INTEGER DEFAULT 0,
    low_findings INTEGER DEFAULT 0,
    average_score NUMERIC(5,2),
    last_scan_at TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, scan_type)
);

CREATE INDEX IF NOT EXISTS idx_scan_stats_user ON scan_statistics(user_id);

-- Grant permissions
GRANT SELECT, INSERT, UPDATE, DELETE ON security_scans TO veribits_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON iam_policy_scans TO veribits_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON secret_scans TO veribits_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON docker_image_scans TO veribits_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON terraform_scans TO veribits_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON k8s_manifest_scans TO veribits_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON api_security_scans TO veribits_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON sbom_scans TO veribits_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON db_connection_scans TO veribits_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON security_header_scans TO veribits_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON scan_statistics TO veribits_app;
