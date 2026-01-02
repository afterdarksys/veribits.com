<?php
namespace VeriBits\Services;

use Aws\Ses\SesClient;
use Aws\Exception\AwsException;

/**
 * EmailService - AWS SES Integration for VeriBits
 *
 * Sends site-themed HTML emails via apps.afterdarksys.com
 */
class EmailService {

    private SesClient $sesClient;
    private string $fromEmail = 'noreply@apps.afterdarksys.com';
    private string $fromName = 'VeriBits';
    private string $replyTo = 'support@afterdarksys.com';

    public function __construct() {
        $this->sesClient = new SesClient([
            'version' => 'latest',
            'region'  => 'us-east-1'
        ]);
    }

    /**
     * Send a single email with HTML template
     */
    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): array {
        try {
            // Strip HTML tags for plain text version if not provided
            if ($textBody === null) {
                $textBody = strip_tags($htmlBody);
            }

            $result = $this->sesClient->sendEmail([
                'Source' => "{$this->fromName} <{$this->fromEmail}>",
                'Destination' => [
                    'ToAddresses' => [$to]
                ],
                'Message' => [
                    'Subject' => [
                        'Data' => $subject,
                        'Charset' => 'UTF-8'
                    ],
                    'Body' => [
                        'Html' => [
                            'Data' => $htmlBody,
                            'Charset' => 'UTF-8'
                        ],
                        'Text' => [
                            'Data' => $textBody,
                            'Charset' => 'UTF-8'
                        ]
                    ]
                ],
                'ReplyToAddresses' => [$this->replyTo]
            ]);

            return [
                'success' => true,
                'messageId' => $result['MessageId'],
                'to' => $to,
                'subject' => $subject
            ];

        } catch (AwsException $e) {
            error_log("SES Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'to' => $to
            ];
        }
    }

    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail(string $email, string $username): array {
        $subject = "Welcome to VeriBits - Your Security Toolkit Awaits";

        $htmlBody = $this->renderTemplate('welcome', [
            'username' => $username,
            'email' => $email,
            'loginUrl' => 'https://veribits.com/login.php',
            'toolsUrl' => 'https://veribits.com/tools.php',
            'cliUrl' => 'https://veribits.com/cli.php'
        ]);

        return $this->send($email, $subject, $htmlBody);
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(string $email, string $resetToken): array {
        $subject = "Reset Your VeriBits Password";
        $resetUrl = "https://veribits.com/reset-password.php?token=" . urlencode($resetToken);

        $htmlBody = $this->renderTemplate('password-reset', [
            'email' => $email,
            'resetUrl' => $resetUrl,
            'expiresIn' => '1 hour'
        ]);

        return $this->send($email, $subject, $htmlBody);
    }

    /**
     * Send broadcast email to multiple recipients
     */
    public function sendBroadcast(array $recipients, string $subject, string $content, string $recipientType = 'users'): array {
        $results = [
            'total' => count($recipients),
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($recipients as $recipient) {
            $email = is_array($recipient) ? $recipient['email'] : $recipient;
            $name = is_array($recipient) ? ($recipient['name'] ?? '') : '';

            $htmlBody = $this->renderTemplate('broadcast', [
                'name' => $name,
                'content' => $content,
                'recipientType' => $recipientType
            ]);

            $result = $this->send($email, $subject, $htmlBody);

            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'email' => $email,
                    'error' => $result['error']
                ];
            }

            // Rate limiting: 14 emails/second is SES limit
            usleep(75000); // 75ms delay = ~13 emails/second
        }

        return $results;
    }

    /**
     * Render email template with variables
     */
    private function renderTemplate(string $templateName, array $vars = []): string {
        $templatePath = __DIR__ . "/../Templates/Email/{$templateName}.html";

        if (!file_exists($templatePath)) {
            // Fallback to inline template
            return $this->getInlineTemplate($templateName, $vars);
        }

        $template = file_get_contents($templatePath);

        // Replace variables
        foreach ($vars as $key => $value) {
            $template = str_replace("{{" . $key . "}}", $value, $template);
        }

        return $template;
    }

    /**
     * Get inline template if file doesn't exist
     */
    private function getInlineTemplate(string $templateName, array $vars): string {
        $baseTemplate = $this->getBaseTemplate();

        switch ($templateName) {
            case 'welcome':
                $content = "<h1>Welcome to VeriBits, {$vars['username']}!</h1>
                    <p>Thank you for joining VeriBits, your comprehensive security and cryptographic toolkit.</p>
                    <p>You now have access to over 35 professional-grade security tools including:</p>
                    <ul style='text-align: left; max-width: 500px; margin: 20px auto;'>
                        <li>Hash Generation & Validation</li>
                        <li>SSL/TLS Certificate Tools</li>
                        <li>DNS & Network Analysis</li>
                        <li>Security Headers Checker</li>
                        <li>Code Signing & Validation</li>
                        <li>And many more...</li>
                    </ul>
                    <p style='margin-top: 30px;'>
                        <a href='{$vars['toolsUrl']}' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;'>Explore All Tools</a>
                    </p>
                    <p style='margin-top: 20px; color: #666;'>
                        Don't forget to check out our <a href='{$vars['cliUrl']}' style='color: #667eea;'>Command-Line Interface</a> for automation and scripting!
                    </p>";
                break;

            case 'password-reset':
                $content = "<h1>Password Reset Request</h1>
                    <p>We received a request to reset your VeriBits password.</p>
                    <p>Click the button below to reset your password:</p>
                    <p style='margin-top: 30px;'>
                        <a href='{$vars['resetUrl']}' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;'>Reset Password</a>
                    </p>
                    <p style='margin-top: 20px; color: #666; font-size: 14px;'>
                        This link will expire in {$vars['expiresIn']}. If you didn't request this, please ignore this email.
                    </p>
                    <p style='margin-top: 10px; color: #999; font-size: 12px;'>
                        For security reasons, this link can only be used once.
                    </p>";
                break;

            case 'broadcast':
                $greeting = !empty($vars['name']) ? "Hello {$vars['name']}," : "Hello,";
                $content = "<h1>Important Update from VeriBits</h1>
                    <p>{$greeting}</p>
                    <div style='text-align: left; max-width: 600px; margin: 30px auto; padding: 20px; background: #f9f9f9; border-radius: 8px;'>
                        {$vars['content']}
                    </div>";
                break;

            default:
                $content = "<h1>Email from VeriBits</h1>
                    <p>" . ($vars['content'] ?? 'No content provided') . "</p>";
        }

        return str_replace('{{CONTENT}}', $content, $baseTemplate);
    }

    /**
     * Base HTML template with VeriBits branding
     */
    private function getBaseTemplate(): string {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VeriBits</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: white; font-size: 32px; font-weight: bold; letter-spacing: -0.5px;">
                                <span style="font-size: 40px;">🔒</span> VeriBits
                            </h1>
                            <p style="margin: 10px 0 0 0; color: rgba(255,255,255,0.9); font-size: 14px;">Professional Security & Cryptographic Tools</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px; text-align: center; color: #333; line-height: 1.6;">
                            {{CONTENT}}
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background: #f5f5f5; padding: 30px; text-align: center; border-top: 1px solid #e0e0e0;">
                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">
                                <a href="https://veribits.com" style="color: #667eea; text-decoration: none; font-weight: 600;">VeriBits.com</a>
                            </p>
                            <p style="margin: 0 0 10px 0; font-size: 12px; color: #999;">
                                <a href="https://veribits.com/tools.php" style="color: #999; text-decoration: none; margin: 0 10px;">Tools</a> |
                                <a href="https://veribits.com/docs.php" style="color: #999; text-decoration: none; margin: 0 10px;">Documentation</a> |
                                <a href="https://veribits.com/support.php" style="color: #999; text-decoration: none; margin: 0 10px;">Support</a>
                            </p>
                            <p style="margin: 15px 0 0 0; font-size: 11px; color: #999;">
                                © 2025 VeriBits. All rights reserved.<br>
                                Powered by AfterDark Systems
                            </p>
                            <p style="margin: 10px 0 0 0; font-size: 10px; color: #aaa;">
                                This email was sent from apps.afterdarksys.com
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Verify email address format
     */
    public static function isValidEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Get sending statistics
     */
    public function getSendingStats(): array {
        try {
            $result = $this->sesClient->getSendQuota();
            return [
                'max24HourSend' => $result['Max24HourSend'],
                'maxSendRate' => $result['MaxSendRate'],
                'sentLast24Hours' => $result['SentLast24Hours'],
                'remaining24Hour' => $result['Max24HourSend'] - $result['SentLast24Hours']
            ];
        } catch (AwsException $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
