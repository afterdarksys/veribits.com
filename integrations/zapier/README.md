# VeriBits Zapier Integration

Connect VeriBits to 5,000+ apps with Zapier. Automate your security workflows without code.

## 🚀 Quick Start

### 1. Install the VeriBits App

Visit [Zapier's VeriBits page](https://zapier.com/apps/veribits/integrations) and click "Use this Zap"

### 2. Connect Your Account

1. Click "Connect an account"
2. You'll be redirected to VeriBits
3. Log in and authorize Zapier
4. You're ready to build workflows!

## 📋 Available Triggers

### Hash Found
Triggers when a hash is successfully cracked

**Sample Data:**
```json
{
  "hash": "5f4dcc3b5aa765d61d8327deb882cf99",
  "plaintext": "password",
  "hash_type": "md5",
  "timestamp": "2025-01-28T12:00:00Z"
}
```

**Example Zap:**
- Trigger: VeriBits - Hash Found
- Action: Slack - Send message to #security
- Result: Instant Slack alerts when hashes are cracked

### Malware Detected
Triggers when malware is detected in a file

**Sample Data:**
```json
{
  "file_name": "suspicious.exe",
  "threats": [{"name": "Trojan.Generic.12345", "severity": "high"}],
  "threat_count": 1
}
```

**Example Zap:**
- Trigger: VeriBits - Malware Detected
- Filter: Only if threat_count > 0
- Action: Email - Send security alert
- Action: Google Sheets - Log incident

### Scan Completed
Triggers when any security scan completes

**Sample Data:**
```json
{
  "scan_type": "malware",
  "items_scanned": 1500,
  "threats_found": 0,
  "duration_seconds": 120
}
```

## ⚡ Available Actions

### Lookup Hash
Lookup a hash in multiple databases

**Input Fields:**
- `hash` (required): The hash to lookup
- `hash_type` (optional): MD5, SHA1, SHA256, or auto-detect

**Example Zap:**
- Trigger: Gmail - New email with attachment
- Action: VeriBits - Scan File
- Filter: If threats > 0
- Action: VeriBits - Lookup Hash
- Action: Slack - Alert team

### Scan File for Malware
Upload and scan a file for malware

**Input Fields:**
- `file_url` (required): URL of file to scan

**Example Zap:**
- Trigger: Dropbox - New file
- Action: VeriBits - Scan File
- Filter: If clean = false
- Action: Dropbox - Delete file
- Action: Email - Notify admin

### Test Network Connection
Test network connectivity using netcat

**Input Fields:**
- `host` (required): Target hostname or IP
- `port` (required): Port number
- `protocol` (optional): TCP or UDP

**Example Zap:**
- Trigger: Schedule - Every hour
- Action: VeriBits - Network Test (example.com:443)
- Filter: If connected = false
- Action: PagerDuty - Create incident

## 📖 Example Workflows

### 1. Automated Malware Scanning Pipeline

```
Gmail: New Attachment
  ↓
VeriBits: Scan File
  ↓
Filter: If threats_found > 0
  ↓
Slack: Post to #security-alerts
  ↓
Jira: Create security ticket
  ↓
Google Sheets: Log incident
```

### 2. Hash Monitoring System

```
Schedule: Every day at 2 AM
  ↓
Google Sheets: Get new hashes
  ↓
Loop: For each hash
  ↓
VeriBits: Lookup Hash
  ↓
Filter: If found = true
  ↓
Google Sheets: Update row with plaintext
  ↓
Email: Send daily summary report
```

### 3. Network Monitoring

```
Schedule: Every 15 minutes
  ↓
VeriBits: Network Test (production server)
  ↓
Filter: If connected = false
  ↓
PagerDuty: Create urgent incident
  ↓
Twilio: Send SMS to on-call engineer
  ↓
Slack: Alert #infrastructure
```

### 4. Automated Threat Intelligence

```
RSS Feed: New malware IOCs
  ↓
Parse: Extract hash from feed
  ↓
VeriBits: Lookup Hash
  ↓
Filter: If found = true
  ↓
Airtable: Add to threat database
  ↓
Slack: Notify security team
```

### 5. Compliance Automation

```
Dropbox: New file in /uploads
  ↓
VeriBits: Scan File
  ↓
Google Sheets: Log scan result
  ↓
Filter: If threats_found = 0
  ↓
Dropbox: Move to /approved
  ↓ (else)
Dropbox: Move to /quarantine
  ↓
Email: Notify compliance team
```

## 🔐 Authentication

VeriBits uses OAuth 2.0 for secure authentication.

### What permissions does Zapier get?

- **Read**: Access your scan results, hash lookups, and reports
- **Write**: Submit scans, create webhooks, run tests

You can revoke access anytime at [veribits.com/settings](https://veribits.com/settings)

### Security Best Practices

1. **Use dedicated account**: Create a service account for Zapier
2. **Limit scope**: Only grant necessary permissions
3. **Monitor activity**: Check webhook logs regularly
4. **Rotate credentials**: Refresh OAuth tokens periodically

## 🛠️ Advanced Features

### Multi-Step Zaps

Combine multiple VeriBits actions:

```
Trigger: VeriBits - Hash Found
  ↓
Action: VeriBits - Network Test (associated C2 server)
  ↓
Action: VeriBits - Scan File (malware sample)
  ↓
Action: Create comprehensive threat report
```

### Filters & Logic

Use Zapier's filters for conditional workflows:

```python
# Only trigger if severity is HIGH
Filter: threat_severity = "high"

# Only on weekdays
Filter: current_time is between 9 AM - 5 PM

# Multiple conditions
Filter: (threats_found > 0) AND (file_size > 1MB)
```

### Delays & Scheduling

Add delays for rate limiting:

```
Action: VeriBits - Scan File
  ↓
Delay: 5 minutes
  ↓
Action: VeriBits - Check scan status
```

## 📊 Monitoring

### View Webhook Logs

Check delivery status in your VeriBits dashboard:

```bash
# Via API
curl https://veribits.com/api/v1/webhooks/123/deliveries \
  -H "Authorization: Bearer YOUR_TOKEN"

# Via Dashboard
https://veribits.com/dashboard/webhooks
```

### Webhook Delivery Details

- Timestamp
- Event type
- HTTP response code
- Response body
- Delivery status

## 🐛 Troubleshooting

### Zap not triggering?

1. **Check webhook status**:
   ```
   Dashboard → Integrations → Webhooks
   ```

2. **Verify events**:
   - Ensure webhook is subscribed to correct events
   - Check "Last Triggered" timestamp

3. **Test the trigger**:
   - Use Zapier's "Test Trigger" button
   - Check for sample data

### Action failing?

1. **Check API limits**:
   - Free tier: 50 requests/day
   - Pro tier: 10,000 requests/day

2. **Verify input data**:
   - Required fields must be filled
   - Correct data types (string, integer, etc.)

3. **Check logs**:
   - Zapier Task History
   - VeriBits API Logs

### Authentication issues?

1. **Reconnect account**:
   - Zapier → Connected Accounts
   - Remove and re-add VeriBits

2. **Check token expiration**:
   - OAuth tokens expire after 30 days
   - Zapier auto-refreshes, but may fail

3. **Verify permissions**:
   - Required scopes: `read write`

## 💰 Pricing

### VeriBits Tiers

- **Free**: 50 API calls/day (perfect for testing)
- **Pro** ($29/month): 10,000 calls/day
- **Enterprise** ($149/month): Unlimited + dedicated webhooks

### Zapier Tiers

VeriBits works with all Zapier plans:
- Free: 100 tasks/month
- Starter: 750 tasks/month ($19.99)
- Professional: 2,000 tasks/month ($49)
- Team: 50,000 tasks/month ($299)

## 📚 Resources

- **API Documentation**: https://veribits.com/api/docs
- **Zapier App Page**: https://zapier.com/apps/veribits
- **Community Forum**: https://community.veribits.com
- **Support**: support@veribits.com

## 🆘 Support

Need help?

- 📧 Email: support@veribits.com
- 💬 Live Chat: https://veribits.com/support
- 📖 Docs: https://docs.veribits.com
- 🎫 Tickets: https://veribits.com/support/tickets

## 🎯 Next Steps

1. **Try example Zaps**: Import pre-built templates
2. **Join community**: Share your workflows
3. **Build custom**: Create unique automations
4. **Scale up**: Upgrade for more capacity

Start automating your security workflows today! 🚀
