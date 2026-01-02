# VeriBits Email System Guide

Complete email framework with AWS SES integration for site-themed HTML emails.

## Features

✅ **Site-Themed HTML Emails** - Professional branded templates with VeriBits styling
✅ **AWS SES Integration** - Sends via apps.afterdarksys.com
✅ **Welcome Emails** - Automatically sent on user registration
✅ **Broadcast Utility** - CLI tool for mass emails
✅ **Admin API** - RESTful endpoints for email operations
✅ **Rate Limiting** - Built-in protection against abuse

---

## Components

### 1. EmailService Class
**Location:** `app/src/Services/EmailService.php`

Core service for sending emails via AWS SES.

**Methods:**
- `send(to, subject, htmlBody, textBody)` - Send single email
- `sendWelcomeEmail(email, username)` - Send welcome email
- `sendPasswordResetEmail(email, resetToken)` - Send password reset
- `sendBroadcast(recipients, subject, content, type)` - Broadcast to multiple
- `getSendingStats()` - Get AWS SES quota and usage

### 2. Email Broadcast CLI
**Location:** `scripts/email-broadcast.php`

Command-line utility for mass emails.

### 3. EmailController
**Location:** `app/src/Controllers/EmailController.php`

REST API for email operations (admin only).

---

## Quick Start

### Send Test Email

```bash
php scripts/email-broadcast.php \
  --subject "Test Email" \
  --message "This is a test message" \
  --to users \
  --test \
  --test-email "your@email.com"
```

### Broadcast from Text File

```bash
# 1. Create message file
cat > announcement.txt << 'EOF'
Hello VeriBits Users!

We're excited to announce new features:

• Malware Detonation Sandbox - Analyze suspicious files
• Netcat Network Tool - Advanced TCP/UDP testing
• OAuth2 & Webhooks - Integrate with Zapier and n8n
• Enhanced Security Documentation

Try them out at https://veribits.com/tools.php

Best regards,
The VeriBits Team
EOF

# 2. Send to all users
php scripts/email-broadcast.php \
  --subject "New Enterprise Features Released!" \
  --file announcement.txt \
  --to all
```

---

## CLI Usage

### Syntax

```bash
php scripts/email-broadcast.php [OPTIONS]
```

### Required Options

- `--subject, -s` - Email subject line
- `--to, -t` - Recipient group (`users` | `employees` | `all`)
- `--file, -f` - Path to message file **OR**
- `--message, -m` - Direct message text

### Optional Flags

- `--dry-run` - Preview without sending
- `--test` - Send to test email only
- `--test-email` - Test email address (default: support@afterdarksys.com)
- `--help` - Show help

### Recipient Groups

| Group | Description |
|-------|-------------|
| `users` | All regular users (excludes employees) |
| `employees` | Only employees and admins |
| `all` | Everyone in database |

### Examples

**1. Dry run to preview recipients:**
```bash
php scripts/email-broadcast.php \
  -s "Test Subject" \
  -f message.txt \
  -t users \
  --dry-run
```

**2. Send to employees only:**
```bash
php scripts/email-broadcast.php \
  -s "Internal Update" \
  -m "Team meeting tomorrow at 10 AM" \
  -t employees
```

**3. Broadcast to everyone:**
```bash
php scripts/email-broadcast.php \
  --subject "System Maintenance Notice" \
  --file maintenance.txt \
  --to all
```

---

## REST API Endpoints

All email API endpoints require authentication.

### Send Test Email
```http
POST /api/v1/email/test
Authorization: Bearer {API_KEY}
Content-Type: application/json

{
  "email": "test@example.com"
}
```

**Response:**
```json
{
  "success": true,
  "messageId": "...",
  "to": "test@example.com"
}
```

### Send Welcome Email (Admin)
```http
POST /api/v1/email/welcome
Authorization: Bearer {API_KEY}
Content-Type: application/json

{
  "email": "newuser@example.com"
}
```

### Get Sending Statistics (Admin)
```http
GET /api/v1/email/stats
Authorization: Bearer {API_KEY}
```

**Response:**
```json
{
  "max24HourSend": 50000,
  "maxSendRate": 14,
  "sentLast24Hours": 127,
  "remaining24Hour": 49873
}
```

### Broadcast Email (Admin)
```http
POST /api/v1/email/broadcast
Authorization: Bearer {API_KEY}
Content-Type: application/json

{
  "subject": "Important Update",
  "content": "Your message here...",
  "recipients": "users",
  "preview": false
}
```

**Preview mode** (set `preview: true`):
```json
{
  "success": true,
  "data": {
    "recipient_count": 1523,
    "sample_recipients": [...]
  }
}
```

---

## Email Templates

### Base Template

All emails use a professionally designed HTML template with:
- VeriBits branding and logo
- Purple gradient header matching website
- Responsive design
- Mobile-friendly layout
- Footer with links to tools, docs, support

### Template Variables

Templates support `{{variable}}` syntax:

```php
$emailService->renderTemplate('welcome', [
    'username' => 'john_doe',
    'loginUrl' => 'https://veribits.com/login.php',
    'toolsUrl' => 'https://veribits.com/tools.php'
]);
```

### Available Templates

1. **welcome** - New user welcome email
2. **password-reset** - Password reset with secure token
3. **broadcast** - General announcement/update emails

### Custom Templates

Create custom templates in: `app/src/Templates/Email/{name}.html`

Use variables with double curly braces: `{{username}}`

---

## Automatic Welcome Emails

Welcome emails are **automatically sent** when users register via:
- Web signup form
- API registration endpoint
- CLI user creation

**Location:** `AuthController.php:86-98`

**Customization:**
Edit the welcome template in `EmailService::getInlineTemplate()`

---

## AWS SES Configuration

### Sender Details
- **From:** noreply@apps.afterdarksys.com
- **From Name:** VeriBits
- **Reply-To:** support@afterdarksys.com
- **Region:** us-east-1

### Verified Identities
✅ apps.afterdarksys.com
✅ afterdarksys.com
✅ nitetext.com
✅ support@afterdarksys.com

### Rate Limits
- **Maximum Send Rate:** 14 emails/second
- **Daily Quota:** 50,000 emails/day
- **Built-in Throttling:** ~13 emails/second (safe buffer)

### Monitoring

```bash
# Check current quota and usage
php -r "require 'app/src/Services/EmailService.php';
use VeriBits\Services\EmailService;
$e = new EmailService();
print_r($e->getSendingStats());"
```

Or via API:
```bash
curl -H "Authorization: Bearer {API_KEY}" \
  https://veribits.com/api/v1/email/stats
```

---

## Message Format

### Plain Text to HTML Conversion

Plain text messages are automatically converted to HTML:
- Line breaks preserved (`\n` → `<br>`)
- HTML characters escaped
- Wrapped in themed template

### Best Practices

✅ **DO:**
- Keep subject lines under 50 characters
- Use clear, action-oriented language
- Include call-to-action links
- Test with `--test` flag first
- Use `--dry-run` for large broadcasts

❌ **DON'T:**
- Send from personal email addresses
- Include sensitive information
- Use ALL CAPS in subject
- Send more than 1 broadcast per day
- Forget to proofread

---

## Troubleshooting

### Email not sending

1. **Check AWS SES status:**
   ```bash
   aws ses get-account-sending-enabled --region us-east-1
   ```

2. **Verify sender identity:**
   ```bash
   aws ses list-identities --region us-east-1 | grep apps.afterdarksys.com
   ```

3. **Check daily quota:**
   ```bash
   curl -H "Authorization: Bearer {API_KEY}" \
     https://veribits.com/api/v1/email/stats
   ```

### Emails going to spam

- Ensure SPF/DKIM/DMARC are configured for afterdarksys.com
- Avoid spam trigger words
- Don't send too frequently
- Keep bounce rate < 5%

### Rate limit exceeded

SES enforces 14 emails/second. The broadcast utility automatically throttles to ~13/sec.

For large broadcasts (>10,000), expect:
- 10,000 emails = ~12 minutes
- 25,000 emails = ~30 minutes

---

## Security

### Authentication
- All API endpoints require valid API key or JWT token
- Broadcast endpoint requires admin role
- Rate limiting on all endpoints

### Email Validation
- Disposable email detection
- Format validation
- MX record verification (optional)

### Logging
All email operations logged to:
- Application logs: `logs/app.log`
- AWS CloudWatch (SES delivery logs)

---

## Examples

### Weekly Newsletter
```bash
cat > newsletter.txt << 'EOF'
This week's security tips:

1. Always enable 2FA on critical accounts
2. Use unique passwords for each service
3. Keep software up to date
4. Review app permissions regularly

New tools this week:
• Malware Detonation - Analyze suspicious files safely
• Enhanced PCAP Analysis - Deep packet inspection

Happy hacking (ethically)!
EOF

php scripts/email-broadcast.php \
  -s "VeriBits Weekly - Security Tips" \
  -f newsletter.txt \
  -t users
```

### System Maintenance Notice
```bash
php scripts/email-broadcast.php \
  -s "Scheduled Maintenance - Jan 15, 2025" \
  -m "VeriBits will undergo scheduled maintenance on January 15 from 2-4 AM EST. All services will be temporarily unavailable. Thank you for your patience!" \
  -t all
```

### Employee Announcement
```bash
php scripts/email-broadcast.php \
  -s "Team Meeting Tomorrow" \
  -m "Reminder: All-hands meeting tomorrow at 10 AM. Agenda: Q4 review, 2025 roadmap, new hires. See you there!" \
  -t employees
```

---

## Integration with Other Systems

### Zapier Webhook Trigger
When user registers → Send to Zapier → Add to mailing list

### Slack Notifications
Broadcast completion → Post to #marketing Slack channel

### Monitoring
Email stats → CloudWatch → Alert on high bounce rate

---

## Support

**Email:** support@afterdarksys.com
**Documentation:** https://veribits.com/docs.php
**CLI Help:** `php scripts/email-broadcast.php --help`

**AWS SES Console:** https://console.aws.amazon.com/ses/
**CloudWatch Logs:** https://console.aws.amazon.com/cloudwatch/

---

## Changelog

### Version 1.0 (October 2025)
- ✅ Initial email system implementation
- ✅ AWS SES integration
- ✅ Welcome email automation
- ✅ Broadcast CLI utility
- ✅ Admin API endpoints
- ✅ Themed HTML templates
- ✅ Rate limiting and throttling
