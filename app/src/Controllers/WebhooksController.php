<?php

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Database;
use VeriBits\Utils\Auth;

class WebhooksController extends BaseController
{
    /**
     * Create webhook subscription
     * POST /api/v1/webhooks
     */
    public function create(): void
    {
        $user = Auth::requireAuth();

        $input = $this->getJsonInput();
        $url = $input['url'] ?? null;
        $events = $input['events'] ?? [];
        $description = $input['description'] ?? '';

        if (!$url || empty($events)) {
            Response::error('URL and events required', 400);
            return;
        }

        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Response::error('Invalid URL', 400);
            return;
        }

        // Validate events
        $validEvents = $this->getValidEvents();
        foreach ($events as $event) {
            if (!in_array($event, $validEvents)) {
                Response::error("Invalid event: $event", 400);
                return;
            }
        }

        // Generate webhook secret for verification
        $secret = bin2hex(random_bytes(32));

        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO webhooks
            (user_id, url, events, secret, description, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            $user['id'],
            $url,
            json_encode($events),
            $secret,
            $description,
            'active'
        ]);

        $webhookId = $db->lastInsertId();

        Response::success([
            'id' => $webhookId,
            'url' => $url,
            'events' => $events,
            'secret' => $secret,
            'description' => $description,
            'status' => 'active'
        ], 201);
    }

    /**
     * List user's webhooks
     * GET /api/v1/webhooks
     */
    public function list(): void
    {
        $user = Auth::requireAuth();

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT id, url, events, description, status, created_at, last_triggered_at
            FROM webhooks
            WHERE user_id = ?
            ORDER BY created_at DESC
        ');
        $stmt->execute([$user['id']]);
        $webhooks = $stmt->fetchAll();

        foreach ($webhooks as &$webhook) {
            $webhook['events'] = json_decode($webhook['events'], true);
        }

        Response::success(['webhooks' => $webhooks]);
    }

    /**
     * Get webhook details
     * GET /api/v1/webhooks/{id}
     */
    public function get(): void
    {
        $user = Auth::requireAuth();
        $webhookId = $this->getPathParam(4); // /api/v1/webhooks/{id}

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT id, url, events, secret, description, status, created_at, last_triggered_at
            FROM webhooks
            WHERE id = ? AND user_id = ?
        ');
        $stmt->execute([$webhookId, $user['id']]);
        $webhook = $stmt->fetch();

        if (!$webhook) {
            Response::error('Webhook not found', 404);
            return;
        }

        $webhook['events'] = json_decode($webhook['events'], true);

        Response::success($webhook);
    }

    /**
     * Update webhook
     * PUT /api/v1/webhooks/{id}
     */
    public function update(): void
    {
        $user = Auth::requireAuth();
        $webhookId = $this->getPathParam(4);

        $input = $this->getJsonInput();

        $db = Database::getInstance();

        // Verify ownership
        $stmt = $db->prepare('SELECT id FROM webhooks WHERE id = ? AND user_id = ?');
        $stmt->execute([$webhookId, $user['id']]);
        if (!$stmt->fetch()) {
            Response::error('Webhook not found', 404);
            return;
        }

        $updates = [];
        $params = [];

        if (isset($input['url'])) {
            if (!filter_var($input['url'], FILTER_VALIDATE_URL)) {
                Response::error('Invalid URL', 400);
                return;
            }
            $updates[] = 'url = ?';
            $params[] = $input['url'];
        }

        if (isset($input['events'])) {
            $validEvents = $this->getValidEvents();
            foreach ($input['events'] as $event) {
                if (!in_array($event, $validEvents)) {
                    Response::error("Invalid event: $event", 400);
                    return;
                }
            }
            $updates[] = 'events = ?';
            $params[] = json_encode($input['events']);
        }

        if (isset($input['description'])) {
            $updates[] = 'description = ?';
            $params[] = $input['description'];
        }

        if (isset($input['status'])) {
            $updates[] = 'status = ?';
            $params[] = $input['status'];
        }

        if (empty($updates)) {
            Response::error('No fields to update', 400);
            return;
        }

        $params[] = $webhookId;
        $params[] = $user['id'];

        $sql = 'UPDATE webhooks SET ' . implode(', ', $updates) . ' WHERE id = ? AND user_id = ?';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success(['updated' => true]);
    }

    /**
     * Delete webhook
     * DELETE /api/v1/webhooks/{id}
     */
    public function delete(): void
    {
        $user = Auth::requireAuth();
        $webhookId = $this->getPathParam(4);

        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM webhooks WHERE id = ? AND user_id = ?');
        $stmt->execute([$webhookId, $user['id']]);

        if ($stmt->rowCount() === 0) {
            Response::error('Webhook not found', 404);
            return;
        }

        Response::success(['deleted' => true]);
    }

    /**
     * Trigger webhook (called internally by system)
     */
    public static function trigger(string $event, array $data): void
    {
        $db = Database::getInstance();

        // Find all active webhooks subscribed to this event
        $stmt = $db->prepare("
            SELECT id, user_id, url, secret
            FROM webhooks
            WHERE status = 'active'
            AND JSON_CONTAINS(events, ?)
        ");
        $stmt->execute([json_encode($event)]);
        $webhooks = $stmt->fetchAll();

        foreach ($webhooks as $webhook) {
            self::sendWebhook($webhook, $event, $data);
        }
    }

    /**
     * Send webhook HTTP request
     */
    private static function sendWebhook(array $webhook, string $event, array $data): void
    {
        $payload = [
            'event' => $event,
            'data' => $data,
            'webhook_id' => $webhook['id'],
            'timestamp' => time()
        ];

        // Generate signature for verification
        $signature = hash_hmac('sha256', json_encode($payload), $webhook['secret']);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhook['url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-VeriBits-Signature: sha256=' . $signature,
            'X-VeriBits-Event: ' . $event,
            'User-Agent: VeriBits-Webhooks/1.0'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Log delivery
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO webhook_deliveries
            (webhook_id, event, payload, response_code, response_body, delivered_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            $webhook['id'],
            $event,
            json_encode($payload),
            $httpCode,
            $response
        ]);

        // Update last triggered
        $stmt = $db->prepare('UPDATE webhooks SET last_triggered_at = NOW() WHERE id = ?');
        $stmt->execute([$webhook['id']]);
    }

    /**
     * Get valid webhook events
     */
    private function getValidEvents(): array
    {
        return [
            'hash.found',
            'hash.not_found',
            'malware.detected',
            'malware.clean',
            'scan.started',
            'scan.completed',
            'file.uploaded',
            'analysis.completed',
            'alert.triggered',
            'quota.exceeded',
            'user.upgraded',
            'job.completed',
            'job.failed'
        ];
    }

    /**
     * Get path parameter
     */
    private function getPathParam(int $index): ?string
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $parts = explode('/', trim($path, '/'));
        return $parts[$index] ?? null;
    }

    /**
     * Get webhook delivery history
     * GET /api/v1/webhooks/{id}/deliveries
     */
    public function deliveries(): void
    {
        $user = Auth::requireAuth();
        $webhookId = $this->getPathParam(4);

        $db = Database::getInstance();

        // Verify ownership
        $stmt = $db->prepare('SELECT id FROM webhooks WHERE id = ? AND user_id = ?');
        $stmt->execute([$webhookId, $user['id']]);
        if (!$stmt->fetch()) {
            Response::error('Webhook not found', 404);
            return;
        }

        // Get deliveries
        $stmt = $db->prepare('
            SELECT id, event, response_code, delivered_at
            FROM webhook_deliveries
            WHERE webhook_id = ?
            ORDER BY delivered_at DESC
            LIMIT 100
        ');
        $stmt->execute([$webhookId]);
        $deliveries = $stmt->fetchAll();

        Response::success(['deliveries' => $deliveries]);
    }
}
