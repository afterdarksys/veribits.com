#!/usr/bin/env php
<?php
/**
 * VeriBits Email Broadcast Utility
 *
 * Sends site-themed HTML emails to users, employees, or everyone
 *
 * Usage:
 *   php scripts/email-broadcast.php --subject "Subject Line" --file message.txt --to users
 *   php scripts/email-broadcast.php --subject "Subject Line" --file message.txt --to employees
 *   php scripts/email-broadcast.php --subject "Subject Line" --file message.txt --to all
 *   php scripts/email-broadcast.php --subject "Subject Line" --message "Direct message" --to users
 *
 * Options:
 *   --subject, -s    Email subject line (required)
 *   --file, -f       Path to text file containing message body
 *   --message, -m    Direct message text (alternative to --file)
 *   --to, -t         Recipient group: users|employees|all (required)
 *   --dry-run        Preview without sending
 *   --test           Send to test email only
 *   --test-email     Test email address (default: support@afterdarksys.com)
 */

require_once __DIR__ . '/../app/src/Services/EmailService.php';
require_once __DIR__ . '/../app/src/Utils/Database.php';

use VeriBits\Services\EmailService;
use VeriBits\Utils\Database;

// Colors for CLI output
const RED = "\033[0;31m";
const GREEN = "\033[0;32m";
const YELLOW = "\033[1;33m";
const BLUE = "\033[0;34m";
const NC = "\033[0m"; // No Color

// Parse command line arguments
$options = getopt("s:f:m:t:", [
    "subject:",
    "file:",
    "message:",
    "to:",
    "dry-run",
    "test",
    "test-email:",
    "help"
]);

// Show help
if (isset($options['help'])) {
    echo getHelpText();
    exit(0);
}

// Validate required parameters
if (!isset($options['subject']) && !isset($options['s'])) {
    echo RED . "Error: --subject is required" . NC . "\n";
    echo "Use --help for usage information\n";
    exit(1);
}

if (!isset($options['to']) && !isset($options['t'])) {
    echo RED . "Error: --to is required (users|employees|all)" . NC . "\n";
    exit(1);
}

if (!isset($options['file']) && !isset($options['f']) && !isset($options['message']) && !isset($options['m'])) {
    echo RED . "Error: --file or --message is required" . NC . "\n";
    exit(1);
}

// Extract values
$subject = $options['subject'] ?? $options['s'];
$recipientGroup = $options['to'] ?? $options['t'];
$dryRun = isset($options['dry-run']);
$testMode = isset($options['test']);
$testEmail = $options['test-email'] ?? 'support@afterdarksys.com';

// Get message content
if (isset($options['file']) || isset($options['f'])) {
    $filePath = $options['file'] ?? $options['f'];
    if (!file_exists($filePath)) {
        echo RED . "Error: File not found: $filePath" . NC . "\n";
        exit(1);
    }
    $messageContent = file_get_contents($filePath);
} else {
    $messageContent = $options['message'] ?? $options['m'];
}

// Convert plain text to HTML (preserve line breaks)
$messageContent = nl2br(htmlspecialchars($messageContent));

// Display configuration
echo BLUE . "========================================\n";
echo "VeriBits Email Broadcast Utility\n";
echo "========================================" . NC . "\n\n";

echo "Subject: " . YELLOW . $subject . NC . "\n";
echo "Recipient Group: " . YELLOW . $recipientGroup . NC . "\n";
echo "Message Length: " . strlen($messageContent) . " characters\n";
if ($dryRun) echo YELLOW . "Mode: DRY RUN (no emails will be sent)\n" . NC;
if ($testMode) echo YELLOW . "Mode: TEST (sending to $testEmail only)\n" . NC;
echo "\n";

// Get recipients from database
echo "Fetching recipients...\n";
$recipients = getRecipients($recipientGroup);

if (empty($recipients)) {
    echo RED . "Error: No recipients found for group: $recipientGroup" . NC . "\n";
    exit(1);
}

echo GREEN . "Found " . count($recipients) . " recipients" . NC . "\n\n";

// Preview
echo "Message Preview:\n";
echo "----------------\n";
echo substr(strip_tags($messageContent), 0, 200);
if (strlen($messageContent) > 200) echo "...";
echo "\n\n";

// Confirmation prompt (unless dry-run or test mode)
if (!$dryRun && !$testMode) {
    echo YELLOW . "Ready to send " . count($recipients) . " emails." . NC . "\n";
    echo "Type 'yes' to continue: ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);

    if ($line !== 'yes') {
        echo "Aborted.\n";
        exit(0);
    }
}

// Send emails
if ($dryRun) {
    echo BLUE . "DRY RUN - Would send to:\n" . NC;
    foreach (array_slice($recipients, 0, 10) as $recipient) {
        echo "  - {$recipient['email']} ({$recipient['name']})\n";
    }
    if (count($recipients) > 10) {
        echo "  ... and " . (count($recipients) - 10) . " more\n";
    }
    exit(0);
}

echo "\nSending emails...\n";
echo "Progress: ";

$emailService = new EmailService();

if ($testMode) {
    // Send single test email
    $result = $emailService->sendBroadcast(
        [['email' => $testEmail, 'name' => 'Test User']],
        $subject,
        $messageContent,
        $recipientGroup
    );
    echo "\n\n" . GREEN . "Test email sent to: $testEmail" . NC . "\n";
} else {
    // Send to all recipients
    $startTime = microtime(true);
    $result = $emailService->sendBroadcast($recipients, $subject, $messageContent, $recipientGroup);
    $duration = round(microtime(true) - $startTime, 2);

    echo "\n\n";
    echo "========================================\n";
    echo GREEN . "Broadcast Complete!" . NC . "\n";
    echo "========================================\n";
    echo "Total Recipients: " . $result['total'] . "\n";
    echo GREEN . "Sent Successfully: " . $result['sent'] . NC . "\n";
    if ($result['failed'] > 0) {
        echo RED . "Failed: " . $result['failed'] . NC . "\n";
    }
    echo "Duration: {$duration}s\n";
    echo "Rate: " . round($result['total'] / $duration, 2) . " emails/second\n";

    if (!empty($result['errors'])) {
        echo "\n" . YELLOW . "Errors:\n" . NC;
        foreach (array_slice($result['errors'], 0, 10) as $error) {
            echo "  - {$error['email']}: {$error['error']}\n";
        }
        if (count($result['errors']) > 10) {
            echo "  ... and " . (count($result['errors']) - 10) . " more errors\n";
        }
    }
}

echo "\n";

/**
 * Get recipients from database based on group
 */
function getRecipients(string $group): array {
    $db = Database::getInstance();
    $recipients = [];

    switch ($group) {
        case 'users':
            // All regular users
            $stmt = $db->prepare("
                SELECT email, COALESCE(username, email) as name
                FROM users
                WHERE role != 'employee' OR role IS NULL
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            $recipients = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            break;

        case 'employees':
            // Only employees
            $stmt = $db->prepare("
                SELECT email, COALESCE(username, email) as name
                FROM users
                WHERE role = 'employee' OR role = 'admin'
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            $recipients = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            break;

        case 'all':
        case 'everyone':
            // Everyone
            $stmt = $db->prepare("
                SELECT email, COALESCE(username, email) as name
                FROM users
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            $recipients = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            break;

        default:
            echo RED . "Error: Invalid recipient group. Must be: users, employees, or all" . NC . "\n";
            exit(1);
    }

    return $recipients;
}

/**
 * Get help text
 */
function getHelpText(): string {
    return GREEN . "
╔══════════════════════════════════════════════════════════════╗
║           VeriBits Email Broadcast Utility                   ║
╚══════════════════════════════════════════════════════════════╝
" . NC . "
Send site-themed HTML emails to users, employees, or everyone.

" . YELLOW . "USAGE:" . NC . "
  php scripts/email-broadcast.php [OPTIONS]

" . YELLOW . "REQUIRED OPTIONS:" . NC . "
  --subject, -s    Email subject line
  --to, -t         Recipient group (users|employees|all)
  --file, -f       Path to text file containing message
    OR
  --message, -m    Direct message text

" . YELLOW . "OPTIONAL:" . NC . "
  --dry-run        Preview recipients without sending
  --test           Send to test email only
  --test-email     Test email address (default: support@afterdarksys.com)
  --help           Show this help message

" . YELLOW . "RECIPIENT GROUPS:" . NC . "
  users            All regular users (excludes employees)
  employees        Only employees and admins
  all              Everyone in the database

" . YELLOW . "EXAMPLES:" . NC . "

  " . BLUE . "1. Broadcast from text file to all users:" . NC . "
     php scripts/email-broadcast.php \\
       --subject \"New Feature Release\" \\
       --file announcement.txt \\
       --to users

  " . BLUE . "2. Direct message to employees:" . NC . "
     php scripts/email-broadcast.php \\
       -s \"Team Update\" \\
       -m \"Please review the new policies.\" \\
       -t employees

  " . BLUE . "3. Dry run to preview:" . NC . "
     php scripts/email-broadcast.php \\
       -s \"Test\" -f message.txt -t all --dry-run

  " . BLUE . "4. Test email before full broadcast:" . NC . "
     php scripts/email-broadcast.php \\
       -s \"Test\" -f message.txt -t users --test

" . YELLOW . "MESSAGE FORMAT:" . NC . "
  - Plain text will be converted to HTML automatically
  - Line breaks are preserved
  - Email uses VeriBits branded template
  - Sent from: noreply@apps.afterdarksys.com

" . YELLOW . "RATE LIMITING:" . NC . "
  - Automatically throttled to ~13 emails/second (AWS SES limit)
  - Large broadcasts may take several minutes

" . GREEN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
For support, contact: support@afterdarksys.com
Documentation: https://veribits.com/docs.php
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
" . NC . "
";
}
