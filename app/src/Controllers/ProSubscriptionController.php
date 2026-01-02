<?php

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Database;
use VeriBits\Utils\Auth;

class ProSubscriptionController extends BaseController
{
    /**
     * Validate Pro license key
     * POST /api/v1/pro/validate
     */
    public function validate(): void
    {
        $input = $this->getJsonInput();
        $licenseKey = $input['license_key'] ?? null;

        if (!$licenseKey) {
            Response::error('License key required', 400);
            return;
        }

        $db = Database::getInstance();

        // Check license in database
        $stmt = $db->prepare('
            SELECT u.id, u.email, p.plan, p.status, p.expires_at
            FROM users u
            JOIN pro_licenses p ON u.id = p.user_id
            WHERE p.license_key = ? AND p.status = ?
        ');
        $stmt->execute([$licenseKey, 'active']);
        $license = $stmt->fetch();

        if (!$license) {
            Response::error('Invalid or inactive license key', 403);
            return;
        }

        // Check expiration
        $expiresAt = new \DateTime($license['expires_at']);
        $now = new \DateTime();

        if ($expiresAt < $now) {
            Response::error('License expired', 403);
            return;
        }

        Response::success([
            'valid' => true,
            'user_id' => $license['id'],
            'email' => $license['email'],
            'plan' => $license['plan'],
            'expires_at' => $license['expires_at'],
            'features' => $this->getProFeatures($license['plan'])
        ]);
    }

    /**
     * Check Pro status for authenticated user
     * GET /api/v1/pro/status
     */
    public function status(): void
    {
        $user = Auth::requireAuth();

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT plan, status, license_key, expires_at, created_at
            FROM pro_licenses
            WHERE user_id = ? AND status = ?
        ');
        $stmt->execute([$user['id'], 'active']);
        $license = $stmt->fetch();

        if (!$license) {
            Response::success([
                'pro_enabled' => false,
                'plan' => 'free'
            ]);
            return;
        }

        Response::success([
            'pro_enabled' => true,
            'plan' => $license['plan'],
            'license_key' => $license['license_key'],
            'expires_at' => $license['expires_at'],
            'created_at' => $license['created_at'],
            'features' => $this->getProFeatures($license['plan'])
        ]);
    }

    /**
     * Generate new Pro license (admin only)
     * POST /api/v1/pro/generate
     */
    public function generate(): void
    {
        $user = Auth::requireAuth();

        // Check if user is admin
        if ($user['role'] !== 'admin') {
            Response::error('Admin access required', 403);
            return;
        }

        $input = $this->getJsonInput();
        $userId = $input['user_id'] ?? null;
        $plan = $input['plan'] ?? 'pro';
        $duration = $input['duration'] ?? 365; // days

        if (!$userId) {
            Response::error('User ID required', 400);
            return;
        }

        // Generate license key
        $licenseKey = 'VBPRO-' . strtoupper(bin2hex(random_bytes(16)));

        $expiresAt = new \DateTime();
        $expiresAt->modify("+$duration days");

        $db = Database::getInstance();

        // Deactivate old licenses
        $stmt = $db->prepare('UPDATE pro_licenses SET status = ? WHERE user_id = ?');
        $stmt->execute(['inactive', $userId]);

        // Create new license
        $stmt = $db->prepare('
            INSERT INTO pro_licenses (user_id, license_key, plan, status, expires_at, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([$userId, $licenseKey, $plan, 'active', $expiresAt->format('Y-m-d H:i:s')]);

        Response::success([
            'license_key' => $licenseKey,
            'plan' => $plan,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'features' => $this->getProFeatures($plan)
        ]);
    }

    /**
     * Get Pro features by plan
     */
    private function getProFeatures(string $plan): array
    {
        $features = [
            'pro' => [
                'job_scheduling' => true,
                'local_caching' => true,
                'offline_mode' => true,
                'batch_parallel' => 10,
                'api_rate_limit' => 10000,
                'priority_support' => true,
                'custom_integrations' => true,
                'advanced_analytics' => true
            ],
            'enterprise' => [
                'job_scheduling' => true,
                'local_caching' => true,
                'offline_mode' => true,
                'batch_parallel' => 100,
                'api_rate_limit' => -1, // unlimited
                'priority_support' => true,
                'custom_integrations' => true,
                'advanced_analytics' => true,
                'dedicated_account_manager' => true,
                'sla_guarantee' => '99.9%',
                'custom_deployment' => true
            ]
        ];

        return $features[$plan] ?? [];
    }
}
