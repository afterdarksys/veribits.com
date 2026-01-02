#!/usr/bin/env php
<?php
/**
 * VeriBits Password Reset CLI Tool
 *
 * Usage:
 *   php scripts/reset-password.php straticus1@gmail.com
 *   php scripts/reset-password.php --email rams3377@gmail.com --password NewPassword123!
 *
 * Features:
 * - Generates production-compatible BCrypt hashes (cost=10)
 * - Updates database directly
 * - Sends branded password reset email via AWS SES
 * - Supports custom passwords or auto-generated secure passwords
 */

require_once __DIR__ . '/../app/src/Utils/Database.php';
require_once __DIR__ . '/../app/src/Utils/Config.php';

use VeriBits\Utils\Database;
use VeriBits\Utils\Config;

// Parse command line arguments
$options = getopt('', ['email:', 'password:', 'no-email']);
$email = $options['email'] ?? ($argv[1] ?? null);
$customPassword = $options['password'] ?? null;
$sendEmail = !isset($options['no-email']);

if (!$email) {
    echo "Usage: php reset-password.php <email> [--password <new_password>] [--no-email]\n";
    echo "\nExamples:\n";
    echo "  php reset-password.php straticus1@gmail.com\n";
    echo "  php reset-password.php --email rams3377@gmail.com --password NewPass123!\n";
    echo "  php reset-password.php user@example.com --no-email\n";
    exit(1);
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "❌ Invalid email address: $email\n";
    exit(1);
}

try {
    // Check if user exists
    $user = Database::fetch(
        "SELECT id, email, status FROM users WHERE email = :email",
        ['email' => $email]
    );

    if (!$user) {
        echo "❌ User not found: $email\n";
        exit(1);
    }

    // Generate new password if not provided
    if ($customPassword) {
        $newPassword = $customPassword;
        echo "🔐 Using custom password\n";
    } else {
        // Generate secure random password
        $newPassword = generateSecurePassword();
        echo "🔐 Generated secure password: $newPassword\n";
    }

    // Validate password strength
    if (strlen($newPassword) < 8) {
        echo "❌ Password must be at least 8 characters\n";
        exit(1);
    }

    // Generate production-compatible BCrypt hash (cost=10)
    echo "🔨 Generating BCrypt hash (cost=10)...\n";
    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);

    // Verify hash works before updating database
    if (!password_verify($newPassword, $passwordHash)) {
        echo "❌ Hash verification failed! This should never happen.\n";
        exit(1);
    }
    echo "✅ Hash verified locally\n";

    // Update database
    echo "💾 Updating database...\n";
    $updated = Database::execute(
        "UPDATE users SET password_hash = :hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id",
        ['hash' => $passwordHash, 'id' => $user['id']]
    );

    if (!$updated) {
        echo "❌ Failed to update database\n";
        exit(1);
    }

    echo "✅ Password updated in database\n";
    echo "   User: {$user['email']}\n";
    echo "   Hash: " . substr($passwordHash, 0, 30) . "...\n";

    // Send email notification
    if ($sendEmail) {
        echo "\n📧 Sending password reset email via AWS SES...\n";
        $emailSent = sendPasswordResetEmail($user['email'], $newPassword);

        if ($emailSent) {
            echo "✅ Email sent successfully\n";
        } else {
            echo "⚠️  Email sending failed (but password was updated)\n";
        }
    } else {
        echo "\n⏭️  Skipping email (--no-email flag)\n";
    }

    echo "\n✅ Password reset complete!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📋 Login Credentials:\n";
    echo "   Email:    {$user['email']}\n";
    echo "   Password: $newPassword\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Generate a secure random password
 */
function generateSecurePassword(int $length = 16): string {
    $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lowercase = 'abcdefghjkmnpqrstuvwxyz';
    $numbers = '23456789';
    $special = '!@#$%^&*';

    $password = '';
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];

    $all = $uppercase . $lowercase . $numbers . $special;
    for ($i = 4; $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }

    return str_shuffle($password);
}

/**
 * Send branded password reset email via AWS SES
 */
function sendPasswordResetEmail(string $email, string $newPassword): bool {
    $subject = 'Your VeriBits Password Has Been Reset';
    $htmlBody = getPasswordResetEmailTemplate($email, $newPassword);
    $textBody = getPasswordResetEmailText($email, $newPassword);

    $sesClient = new Aws\Ses\SesClient([
        'version' => 'latest',
        'region' => 'us-east-1'
    ]);

    try {
        $result = $sesClient->sendEmail([
            'Source' => 'VeriBits Security <noreply@apps.afterdarksys.com>',
            'Destination' => [
                'ToAddresses' => [$email],
            ],
            'Message' => [
                'Subject' => [
                    'Data' => $subject,
                    'Charset' => 'UTF-8',
                ],
                'Body' => [
                    'Html' => [
                        'Data' => $htmlBody,
                        'Charset' => 'UTF-8',
                    ],
                    'Text' => [
                        'Data' => $textBody,
                        'Charset' => 'UTF-8',
                    ],
                ],
            ],
        ]);

        return true;
    } catch (\Exception $e) {
        echo "   SES Error: " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * Get branded HTML email template
 */
function getPasswordResetEmailTemplate(string $email, string $newPassword): string {
    $loginUrl = 'https://veribits.com/login.php';
    $supportUrl = 'https://veribits.com/support.php';

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - VeriBits</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #0a0a0a; color: #e0e0e0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0a0a0a; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #1a1a1a; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); padding: 40px; text-align: center;">
                            <h1 style="margin: 0; color: #000; font-size: 32px; font-weight: bold;">VeriBits</h1>
                            <p style="margin: 10px 0 0 0; color: #000; font-size: 14px; opacity: 0.8;">Security & Developer Tools</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="margin: 0 0 20px 0; color: #fbbf24; font-size: 24px;">Password Reset Successful</h2>

                            <p style="margin: 0 0 20px 0; color: #e0e0e0; font-size: 16px; line-height: 1.6;">
                                Your VeriBits password has been reset. You can now log in with your new credentials.
                            </p>

                            <!-- Credentials Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0a0a0a; border-radius: 8px; padding: 24px; margin: 24px 0; border: 2px solid #fbbf24;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 12px 0; color: #9ca3af; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Your Login Credentials</p>

                                        <table width="100%" cellpadding="8" cellspacing="0">
                                            <tr>
                                                <td style="color: #9ca3af; font-size: 14px; width: 80px;">Email:</td>
                                                <td style="color: #e0e0e0; font-size: 14px; font-family: 'Courier New', monospace;">{$email}</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #9ca3af; font-size: 14px;">Password:</td>
                                                <td style="color: #fbbf24; font-size: 16px; font-weight: bold; font-family: 'Courier New', monospace;">{$newPassword}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 32px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{$loginUrl}" style="display: inline-block; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #000; text-decoration: none; padding: 16px 48px; border-radius: 8px; font-size: 16px; font-weight: bold;">
                                            Log In to VeriBits
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Security Notice -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; padding: 16px; margin: 24px 0;">
                                <tr>
                                    <td>
                                        <p style="margin: 0; color: #fca5a5; font-size: 14px; line-height: 1.6;">
                                            <strong>🔒 Security Recommendation:</strong> Change this temporary password after logging in. Go to Settings → Security → Change Password.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 24px 0 0 0; color: #9ca3af; font-size: 14px; line-height: 1.6;">
                                If you didn't request this password reset, please <a href="{$supportUrl}" style="color: #fbbf24; text-decoration: none;">contact support</a> immediately.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #0a0a0a; padding: 32px; text-align: center; border-top: 1px solid #2a2a2a;">
                            <p style="margin: 0 0 8px 0; color: #9ca3af; font-size: 12px;">
                                &copy; 2025 VeriBits. All rights reserved.
                            </p>
                            <p style="margin: 0; color: #6b7280; font-size: 12px;">
                                A service from <a href="https://www.afterdarksys.com/" style="color: #fbbf24; text-decoration: none;">After Dark Systems, LLC</a>
                            </p>
                            <p style="margin: 16px 0 0 0; color: #6b7280; font-size: 11px;">
                                <a href="https://veribits.com/privacy.php" style="color: #6b7280; text-decoration: none; margin: 0 8px;">Privacy</a>
                                <a href="https://veribits.com/terms.php" style="color: #6b7280; text-decoration: none; margin: 0 8px;">Terms</a>
                                <a href="https://veribits.com/support.php" style="color: #6b7280; text-decoration: none; margin: 0 8px;">Support</a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

/**
 * Get plain text email version
 */
function getPasswordResetEmailText(string $email, string $newPassword): string {
    return <<<TEXT
VeriBits Password Reset
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Your VeriBits password has been reset successfully.

YOUR LOGIN CREDENTIALS
----------------------
Email:    {$email}
Password: {$newPassword}

Log in at: https://veribits.com/login.php

SECURITY RECOMMENDATION
-----------------------
🔒 Change this temporary password after logging in.
   Go to Settings → Security → Change Password.

If you didn't request this password reset, please contact
support immediately at https://veribits.com/support.php

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
© 2025 VeriBits - A service from After Dark Systems, LLC
https://veribits.com
TEXT;
}
