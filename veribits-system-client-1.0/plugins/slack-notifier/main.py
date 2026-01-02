"""
VeriBits Slack Notifier Plugin
Sends notifications to Slack when security events occur
"""

import sys
import os
import json
import requests
from typing import Dict, List

# Import the plugin API
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', '..'))
from veribits_plugin_api import VeriBitsPluginAPI


class SlackNotifierPlugin(VeriBitsPluginAPI):
    """Send VeriBits security alerts to Slack"""

    name = "slack-notifier"
    version = "1.0.0"
    author = "VeriBits Team"
    description = "Send security alerts and scan results to Slack channels"

    def on_install(self):
        """Setup plugin on installation"""
        self.log("Installing Slack Notifier plugin...", "info")

        # Prompt for Slack webhook URL
        print("\n🔔 Slack Notifier Setup")
        print("=" * 50)
        print("To use this plugin, you need a Slack Incoming Webhook URL")
        print("Get one at: https://api.slack.com/messaging/webhooks\n")

        webhook_url = input("Enter your Slack Webhook URL: ").strip()

        if webhook_url:
            self.save_config({
                'webhook_url': webhook_url,
                'enabled': True,
                'notify_hash_found': True,
                'notify_malware_detected': True,
                'notify_scan_complete': True,
                'notify_api_error': False,
                'channel_override': None,  # Set to override default channel
                'username': 'VeriBits Security Bot',
                'icon_emoji': ':shield:'
            })
            self.log("Configuration saved!", "success")
            self.send_test_notification()
        else:
            self.log("Skipped configuration. Run 'veribits plugin configure slack-notifier' to set up later.", "warning")

    def send_test_notification(self):
        """Send a test notification to verify setup"""
        try:
            self.send_slack_message(
                "VeriBits Slack Integration Active",
                "Successfully connected to Slack! You'll receive security alerts here.",
                color="good"
            )
            self.log("Test notification sent!", "success")
        except Exception as e:
            self.log(f"Failed to send test notification: {e}", "error")

    def send_slack_message(self, title: str, message: str, color: str = "#FBB024", fields: List[Dict] = None):
        """
        Send a message to Slack

        Args:
            title: Message title
            message: Message text
            color: Attachment color (good, warning, danger, or hex)
            fields: Additional fields to display
        """
        webhook_url = self.config.get('webhook_url')
        if not webhook_url:
            self.log("Slack webhook URL not configured", "error")
            return

        if not self.config.get('enabled', True):
            return

        # Build Slack message
        attachment = {
            'color': color,
            'title': title,
            'text': message,
            'footer': 'VeriBits Security Platform',
            'footer_icon': 'https://veribits.com/assets/img/logo.png',
            'ts': int(__import__('time').time())
        }

        if fields:
            attachment['fields'] = fields

        payload = {
            'username': self.config.get('username', 'VeriBits Bot'),
            'icon_emoji': self.config.get('icon_emoji', ':shield:'),
            'attachments': [attachment]
        }

        # Add channel override if configured
        channel = self.config.get('channel_override')
        if channel:
            payload['channel'] = channel

        # Send to Slack
        try:
            response = requests.post(webhook_url, json=payload, timeout=10)
            response.raise_for_status()
        except Exception as e:
            self.log(f"Failed to send Slack notification: {e}", "error")

    def on_hash_found(self, hash_value: str, plaintext: str, hash_type: str):
        """Notify when a hash is cracked"""
        if not self.config.get('notify_hash_found', True):
            return

        self.send_slack_message(
            "🔓 Hash Cracked",
            f"Successfully cracked a {hash_type.upper()} hash",
            color="good",
            fields=[
                {'title': 'Hash', 'value': f"`{hash_value[:32]}...`", 'short': True},
                {'title': 'Type', 'value': hash_type.upper(), 'short': True},
                {'title': 'Plaintext', 'value': f"`{plaintext}`", 'short': False}
            ]
        )

    def on_malware_detected(self, file_path: str, threats: List[Dict]):
        """Notify when malware is detected"""
        if not self.config.get('notify_malware_detected', True):
            return

        threat_list = "\n".join([f"• {t.get('name', 'Unknown threat')}" for t in threats[:5]])
        if len(threats) > 5:
            threat_list += f"\n• ...and {len(threats) - 5} more"

        self.send_slack_message(
            "🚨 MALWARE DETECTED",
            f"*File:* `{os.path.basename(file_path)}`\n*Threats:* {len(threats)}\n\n{threat_list}",
            color="danger",
            fields=[
                {'title': 'File Path', 'value': file_path, 'short': False},
                {'title': 'Threat Count', 'value': str(len(threats)), 'short': True},
                {'title': 'Severity', 'value': 'HIGH', 'short': True}
            ]
        )

    def on_scan_complete(self, scan_type: str, results: Dict):
        """Notify when scans complete"""
        if not self.config.get('notify_scan_complete', True):
            return

        # Only notify for important scans
        if scan_type not in ['malware', 'network', 'system']:
            return

        threat_count = results.get('threats_found', 0)
        color = "danger" if threat_count > 0 else "good"
        status = "⚠️ THREATS FOUND" if threat_count > 0 else "✅ CLEAN"

        self.send_slack_message(
            f"{scan_type.title()} Scan Complete - {status}",
            f"Scan completed successfully",
            color=color,
            fields=[
                {'title': 'Scan Type', 'value': scan_type.title(), 'short': True},
                {'title': 'Items Scanned', 'value': str(results.get('scanned_items', 'N/A')), 'short': True},
                {'title': 'Threats Found', 'value': str(threat_count), 'short': True},
                {'title': 'Status', 'value': 'Clean' if threat_count == 0 else 'Action Required', 'short': True}
            ]
        )

    def on_api_error(self, endpoint: str, error: str):
        """Notify on API errors (if enabled)"""
        if not self.config.get('notify_api_error', False):
            return

        self.send_slack_message(
            "⚠️ API Error",
            f"An API request failed",
            color="warning",
            fields=[
                {'title': 'Endpoint', 'value': endpoint, 'short': True},
                {'title': 'Error', 'value': error, 'short': False}
            ]
        )

    def send_notification(self, title: str, message: str, **kwargs):
        """
        Generic notification method

        Args:
            title: Notification title
            message: Notification message
            **kwargs: color, fields, etc.
        """
        self.send_slack_message(
            title,
            message,
            color=kwargs.get('color', '#FBB024'),
            fields=kwargs.get('fields')
        )


# Export the plugin class
Plugin = SlackNotifierPlugin


if __name__ == '__main__':
    # Test the plugin
    print("Testing Slack Notifier Plugin")
    print("=" * 50)

    class MockAPI:
        pass

    plugin = SlackNotifierPlugin(MockAPI())

    # Test notification
    plugin.on_hash_found(
        '5f4dcc3b5aa765d61d8327deb882cf99',
        'password',
        'md5'
    )
