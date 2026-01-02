# Slack Notifier Plugin for VeriBits

Send real-time security alerts from VeriBits to your Slack channels.

## Features

✅ Real-time notifications for:
- Hash lookups and cracks
- Malware detections
- Scan completions
- API errors (optional)

✅ Customizable:
- Choose which events to notify
- Override default channel
- Custom bot name and icon
- Rich formatted messages with color coding

## Installation

```bash
veribits plugin install slack-notifier
```

## Setup

### 1. Create Slack Incoming Webhook

1. Go to https://api.slack.com/messaging/webhooks
2. Click "Create New App"
3. Choose "From scratch"
4. Name your app "VeriBits Security"
5. Select your workspace
6. Go to "Incoming Webhooks" and activate it
7. Click "Add New Webhook to Workspace"
8. Choose the channel (e.g., `#security`)
9. Copy the Webhook URL

### 2. Configure Plugin

```bash
# During installation, you'll be prompted for webhook URL
# Or configure manually:
veribits plugin configure slack-notifier
```

## Configuration

Edit `~/.veribits/plugins/slack-notifier/config.json`:

```json
{
  "webhook_url": "https://hooks.slack.com/services/YOUR/WEBHOOK/URL",
  "enabled": true,
  "notify_hash_found": true,
  "notify_malware_detected": true,
  "notify_scan_complete": true,
  "notify_api_error": false,
  "channel_override": "#security",
  "username": "VeriBits Security Bot",
  "icon_emoji": ":shield:"
}
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `webhook_url` | string | *required* | Slack Incoming Webhook URL |
| `enabled` | boolean | `true` | Enable/disable all notifications |
| `notify_hash_found` | boolean | `true` | Notify when hashes are cracked |
| `notify_malware_detected` | boolean | `true` | Alert on malware detection |
| `notify_scan_complete` | boolean | `true` | Notify on scan completion |
| `notify_api_error` | boolean | `false` | Notify on API errors |
| `channel_override` | string | `null` | Override default channel (e.g., `#security`) |
| `username` | string | `"VeriBits Security Bot"` | Bot display name |
| `icon_emoji` | string | `":shield:"` | Bot icon emoji |

## Usage

Once installed and configured, the plugin works automatically. Run any VeriBits command and receive notifications:

```bash
# Hash lookup - get notification when found
veribits hash lookup 5f4dcc3b5aa765d61d8327deb882cf99

# Malware scan - get alert if threats detected
veribits malware submit suspicious.exe

# Network scan - notification on completion
veribits netcat example.com 80
```

## Example Notifications

### Hash Found
```
🔓 Hash Cracked
Successfully cracked a MD5 hash

Hash: 5f4dcc3b5aa765d61d8327deb882cf99
Type: MD5
Plaintext: password
```

### Malware Detected
```
🚨 MALWARE DETECTED
File: suspicious.exe
Threats: 5

• Trojan.Generic.12345
• Backdoor.RemoteAccess
• Worm.Autorun
• ...and 2 more

Severity: HIGH
```

### Scan Complete
```
✅ System Scan Complete - CLEAN
Scan completed successfully

Scan Type: System
Items Scanned: 152,847
Threats Found: 0
Status: Clean
```

## Troubleshooting

### Not receiving notifications?

1. **Check webhook URL:**
   ```bash
   cat ~/.veribits/plugins/slack-notifier/config.json | grep webhook_url
   ```

2. **Test the webhook:**
   ```bash
   curl -X POST YOUR_WEBHOOK_URL \
     -H 'Content-Type: application/json' \
     -d '{"text":"Test message"}'
   ```

3. **Check plugin is enabled:**
   ```bash
   veribits plugin list
   ```

4. **View plugin logs:**
   ```bash
   veribits plugin logs slack-notifier
   ```

### Messages going to wrong channel?

Set `channel_override` in config to redirect:
```json
{
  "channel_override": "#your-channel"
}
```

### Too many notifications?

Disable specific event types:
```json
{
  "notify_hash_found": false,
  "notify_scan_complete": false
}
```

## Development

Want to modify this plugin?

1. Fork the plugin:
   ```bash
   cp -r ~/.veribits/plugins/slack-notifier ~/.veribits/plugins/my-slack-notifier
   ```

2. Edit `main.py`:
   ```python
   class MySlackNotifier(VeriBitsPluginAPI):
       name = "my-slack-notifier"
       # Your customizations here
   ```

3. Reinstall:
   ```bash
   veribits plugin install ~/.veribits/plugins/my-slack-notifier
   ```

## Support

- 📧 Email: support@veribits.com
- 💬 Slack: [VeriBits Community](https://veribits-community.slack.com)
- 🐛 Issues: [GitHub Issues](https://github.com/veribits/plugins/issues)

## License

MIT License - see LICENSE file

## Author

Created by VeriBits Team
https://veribits.com
