-- OAuth2 and Webhooks System
-- Migration: 021
-- Description: Add OAuth2 authentication and webhooks for Zapier/n8n integration

-- OAuth Clients (Zapier, n8n, custom integrations)
CREATE TABLE IF NOT EXISTS oauth_clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id VARCHAR(255) NOT NULL UNIQUE,
    client_secret VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    redirect_uris TEXT NOT NULL, -- JSON array
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_client_id (client_id)
);

-- OAuth Authorization Codes
CREATE TABLE IF NOT EXISTS oauth_authorization_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(255) NOT NULL UNIQUE,
    client_id VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    redirect_uri TEXT NOT NULL,
    scope VARCHAR(255) NOT NULL DEFAULT 'read write',
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES oauth_clients(client_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_code (code),
    INDEX idx_expires (expires_at)
);

-- OAuth Access Tokens
CREATE TABLE IF NOT EXISTS oauth_access_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(255) NOT NULL UNIQUE,
    client_id VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    scope VARCHAR(255) NOT NULL DEFAULT 'read write',
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES oauth_clients(client_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- OAuth Refresh Tokens
CREATE TABLE IF NOT EXISTS oauth_refresh_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(255) NOT NULL UNIQUE,
    client_id VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    scope VARCHAR(255) NOT NULL DEFAULT 'read write',
    expires_at TIMESTAMP NOT NULL,
    revoked TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES oauth_clients(client_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- Webhooks (for Zapier triggers, n8n triggers, etc.)
CREATE TABLE IF NOT EXISTS webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    url TEXT NOT NULL,
    events JSON NOT NULL, -- ['hash.found', 'malware.detected', etc.]
    secret VARCHAR(255) NOT NULL, -- For signature verification
    description VARCHAR(255) DEFAULT '',
    status VARCHAR(50) DEFAULT 'active', -- active, paused, failed
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_triggered_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- Webhook Delivery Log
CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT NOT NULL,
    event VARCHAR(100) NOT NULL,
    payload TEXT NOT NULL,
    response_code INT,
    response_body TEXT,
    delivered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE,
    INDEX idx_webhook_id (webhook_id),
    INDEX idx_delivered_at (delivered_at)
);

-- Create a test OAuth client for Zapier development
INSERT INTO oauth_clients (client_id, client_secret, user_id, name, redirect_uris)
VALUES (
    'vb_zapier_test_client_0000000000',
    -- Password: test_secret_change_me
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    1,
    'Zapier Integration',
    JSON_ARRAY(
        'https://zapier.com/dashboard/auth/oauth/return/App123456CLI/',
        'https://zapier.com/dashboard/auth/oauth/return/App123456API/'
    )
) ON DUPLICATE KEY UPDATE client_id=client_id;
