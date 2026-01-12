<?php

namespace App\Controllers;

use App\Utils\Auth;
use App\Utils\Database;
use App\Utils\Logger;

/**
 * Smart Cryptographic Services Controller
 *
 * Advanced crypto features:
 * - Public key repository & trust network
 * - Certificate Transparency monitoring
 * - Automated key expiry alerts
 * - Reputation scoring
 */
class CryptoServicesController
{
    private $db;
    private $auth;
    private $logger;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
        $this->logger = new Logger('CryptoServices');
    }

    /**
     * Publish public key to repository
     * POST /api/v1/crypto/keys/publish
     */
    public function publishKey()
    {
        try {
            $user = $this->auth->getUserFromToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $keyData = $input['key_data'] ?? null;
            $keyType = $input['key_type'] ?? 'pgp'; // pgp, ssh, x509
            $keyFingerprint = $input['fingerprint'] ?? null;

            if (!$keyData) {
                http_response_code(400);
                echo json_encode(['error' => 'Key data required']);
                return;
            }

            // Verify key
            $verif = $this->verifyKey($keyData, $keyType);
            if (!$verif['valid']) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid key: ' . $verif['error']]);
                return;
            }

            // Save key
            $keyId = $this->db->query(
                "INSERT INTO public_keys (user_id, key_type, key_data, fingerprint, reputation_score, created_at)
                VALUES (?, ?, ?, ?, 0, NOW()) RETURNING id",
                [$user['id'], $keyType, $keyData, $keyFingerprint ?? $verif['fingerprint']]
            )->fetch()['id'];

            echo json_encode([
                'key_id' => $keyId,
                'fingerprint' => $keyFingerprint ?? $verif['fingerprint'],
                'url' => getenv('API_URL') . '/api/v1/crypto/keys/' . $keyId
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Key publish failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Monitor certificate transparency logs
     * POST /api/v1/crypto/ct-monitor/add
     */
    public function addCTMonitor()
    {
        try {
            $user = $this->auth->getUserFromToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $domain = $input['domain'] ?? null;
            $alertEmail = $input['alert_email'] ?? $user['email'];

            if (!$domain) {
                http_response_code(400);
                echo json_encode(['error' => 'Domain required']);
                return;
            }

            $monitorId = $this->db->query(
                "INSERT INTO ct_monitors (user_id, domain, alert_email, is_active, created_at)
                VALUES (?, ?, ?, TRUE, NOW()) RETURNING id",
                [$user['id'], $domain, $alertEmail]
            )->fetch()['id'];

            echo json_encode([
                'monitor_id' => $monitorId,
                'domain' => $domain,
                'status' => 'active'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('CT monitor add failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Set up key expiry alert
     * POST /api/v1/crypto/alerts/expiry
     */
    public function setupExpiryAlert()
    {
        try {
            $user = $this->auth->getUserFromToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $keyId = $input['key_id'] ?? null;
            $alertDays = $input['alert_days_before'] ?? 30;

            if (!$keyId) {
                http_response_code(400);
                echo json_encode(['error' => 'Key ID required']);
                return;
            }

            $this->db->query(
                "INSERT INTO key_expiry_alerts (user_id, key_id, alert_days_before, is_active)
                VALUES (?, ?, ?, TRUE)
                ON CONFLICT (user_id, key_id) DO UPDATE SET alert_days_before = EXCLUDED.alert_days_before",
                [$user['id'], $keyId, $alertDays]
            );

            echo json_encode(['status' => 'alert_configured', 'alert_days_before' => $alertDays]);

        } catch (\Exception $e) {
            $this->logger->error('Expiry alert setup failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function verifyKey($keyData, $keyType)
    {
        // TODO: Implement actual key verification
        return ['valid' => true, 'fingerprint' => hash('sha256', $keyData)];
    }
}
