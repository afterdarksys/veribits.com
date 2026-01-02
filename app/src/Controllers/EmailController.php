<?php
namespace VeriBits\Controllers;

use VeriBits\Services\EmailService;
use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\Database;
use VeriBits\Utils\RateLimit;

/**
 * EmailController - Manual email operations and admin tools
 */
class EmailController {

    /**
     * Send test email (authenticated users only)
     */
    public function sendTest(): void {
        $user = Auth::authenticate();
        if (!$user) {
            Response::unauthorized();
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $testEmail = $body['email'] ?? $user['email'];

        $emailService = new EmailService();
        $result = $emailService->send(
            $testEmail,
            'VeriBits Test Email',
            $emailService->renderTemplate('test', [
                'username' => $user['email'],
                'message' => 'This is a test email from VeriBits.'
            ])
        );

        if ($result['success']) {
            Response::success($result, 'Test email sent successfully');
        } else {
            Response::error('Failed to send test email: ' . $result['error'], 500);
        }
    }

    /**
     * Send welcome email manually (admin only)
     */
    public function sendWelcome(): void {
        $user = Auth::authenticate();
        if (!$user || !in_array($user['role'] ?? '', ['admin', 'employee'])) {
            Response::unauthorized('Admin access required');
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($body['email'])) {
            Response::error('Email address required', 400);
            return;
        }

        $emailService = new EmailService();
        $username = explode('@', $body['email'])[0];
        $result = $emailService->sendWelcomeEmail($body['email'], $username);

        if ($result['success']) {
            Response::success($result, 'Welcome email sent');
        } else {
            Response::error('Failed to send welcome email: ' . $result['error'], 500);
        }
    }

    /**
     * Get SES sending statistics (admin only)
     */
    public function getStats(): void {
        $user = Auth::authenticate();
        if (!$user || !in_array($user['role'] ?? '', ['admin', 'employee'])) {
            Response::unauthorized('Admin access required');
            return;
        }

        $emailService = new EmailService();
        $stats = $emailService->getSendingStats();

        Response::success($stats);
    }

    /**
     * Send broadcast email (admin only)
     */
    public function sendBroadcast(): void {
        $user = Auth::authenticate();
        if (!$user || $user['role'] !== 'admin') {
            Response::unauthorized('Admin access required');
            return;
        }

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!RateLimit::check("broadcast:$clientIp", 5, 3600)) {
            Response::error('Broadcast rate limit exceeded', 429);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($body['subject']) || empty($body['content']) || empty($body['recipients'])) {
            Response::error('Subject, content, and recipients are required', 400);
            return;
        }

        $subject = $body['subject'];
        $content = $body['content'];
        $recipientGroup = $body['recipients']; // 'users', 'employees', 'all'

        // Get recipients from database
        $recipients = $this->getRecipients($recipientGroup);

        if (empty($recipients)) {
            Response::error('No recipients found', 404);
            return;
        }

        // Preview mode?
        if (isset($body['preview']) && $body['preview'] === true) {
            Response::success([
                'recipient_count' => count($recipients),
                'sample_recipients' => array_slice($recipients, 0, 5)
            ], 'Preview generated');
            return;
        }

        // Send broadcast
        $emailService = new EmailService();
        $result = $emailService->sendBroadcast($recipients, $subject, $content, $recipientGroup);

        Response::success($result, 'Broadcast completed');
    }

    /**
     * Get recipients from database
     */
    private function getRecipients(string $group): array {
        $db = Database::getInstance();

        switch ($group) {
            case 'users':
                $stmt = $db->prepare("
                    SELECT email, COALESCE(username, email) as name
                    FROM users
                    WHERE role != 'employee' OR role IS NULL
                ");
                break;

            case 'employees':
                $stmt = $db->prepare("
                    SELECT email, COALESCE(username, email) as name
                    FROM users
                    WHERE role IN ('employee', 'admin')
                ");
                break;

            case 'all':
                $stmt = $db->prepare("
                    SELECT email, COALESCE(username, email) as name
                    FROM users
                ");
                break;

            default:
                return [];
        }

        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
