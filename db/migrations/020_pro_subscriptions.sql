-- Pro Subscriptions and Licenses
-- Migration: 020
-- Description: Add Pro subscription system with license keys

CREATE TABLE IF NOT EXISTS pro_licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    license_key VARCHAR(255) NOT NULL UNIQUE,
    plan VARCHAR(50) NOT NULL DEFAULT 'pro',
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_license_key (license_key),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- Insert a test Pro license for development
-- License: VBPRO-DEV-TEST-0000000000000000
-- User: straticus1@gmail.com (ID: 1)
INSERT INTO pro_licenses (user_id, license_key, plan, status, expires_at)
VALUES (
    1,
    'VBPRO-DEV-TEST-0000000000000000',
    'pro',
    'active',
    DATE_ADD(NOW(), INTERVAL 1 YEAR)
) ON DUPLICATE KEY UPDATE license_key=license_key;
