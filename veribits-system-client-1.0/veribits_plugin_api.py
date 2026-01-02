"""
VeriBits Plugin API
Allows third-party developers to extend VeriBits functionality
"""

import os
import sys
import json
import importlib.util
from typing import Dict, List, Optional, Callable
from pathlib import Path
import inspect

PLUGIN_DIR = os.path.expanduser("~/.veribits/plugins")

class VeriBitsPluginAPI:
    """Base class for all VeriBits plugins"""

    # Plugin metadata
    name: str = "Unnamed Plugin"
    version: str = "1.0.0"
    author: str = "Unknown"
    description: str = ""

    def __init__(self, api_client):
        """
        Initialize plugin with VeriBits API client

        Args:
            api_client: VeriBits API client instance
        """
        self.api = api_client
        self.config = self.load_config()

    def load_config(self) -> Dict:
        """Load plugin configuration"""
        config_file = os.path.join(PLUGIN_DIR, self.name, "config.json")
        if os.path.exists(config_file):
            with open(config_file, 'r') as f:
                return json.load(f)
        return {}

    def save_config(self, config: Dict):
        """Save plugin configuration"""
        config_dir = os.path.join(PLUGIN_DIR, self.name)
        os.makedirs(config_dir, exist_ok=True)
        config_file = os.path.join(config_dir, "config.json")
        with open(config_file, 'w') as f:
            json.dump(config, f, indent=2)

    # ========== LIFECYCLE HOOKS ==========

    def on_install(self):
        """Called when plugin is installed"""
        pass

    def on_uninstall(self):
        """Called when plugin is uninstalled"""
        pass

    def on_enable(self):
        """Called when plugin is enabled"""
        pass

    def on_disable(self):
        """Called when plugin is disabled"""
        pass

    # ========== EVENT HOOKS ==========

    def on_hash_lookup(self, hash_value: str, result: Dict):
        """
        Called after hash lookup

        Args:
            hash_value: The hash that was looked up
            result: API response
        """
        pass

    def on_hash_found(self, hash_value: str, plaintext: str, hash_type: str):
        """
        Called when a hash is successfully cracked

        Args:
            hash_value: The hash
            plaintext: The plaintext value
            hash_type: Hash algorithm (md5, sha1, etc)
        """
        pass

    def on_malware_detected(self, file_path: str, threats: List[Dict]):
        """
        Called when malware is detected

        Args:
            file_path: Path to the scanned file
            threats: List of detected threats
        """
        pass

    def on_scan_complete(self, scan_type: str, results: Dict):
        """
        Called when any scan completes

        Args:
            scan_type: Type of scan (hash, malware, network, etc)
            results: Scan results
        """
        pass

    def on_api_error(self, endpoint: str, error: str):
        """
        Called when API request fails

        Args:
            endpoint: API endpoint that failed
            error: Error message
        """
        pass

    # ========== UTILITY METHODS ==========

    def log(self, message: str, level: str = "info"):
        """
        Log a message

        Args:
            message: Message to log
            level: Log level (info, warning, error)
        """
        prefix = {
            'info': 'ℹ️',
            'warning': '⚠️',
            'error': '❌',
            'success': '✓'
        }.get(level, 'ℹ️')

        print(f"[{self.name}] {prefix} {message}")

    def send_notification(self, title: str, message: str, **kwargs):
        """
        Send notification (to be implemented by notification plugins)

        Args:
            title: Notification title
            message: Notification message
            **kwargs: Additional parameters
        """
        self.log(f"{title}: {message}")


class PluginManager:
    """Manages plugin installation, loading, and execution"""

    def __init__(self, api_client):
        self.api_client = api_client
        self.plugins: Dict[str, VeriBitsPluginAPI] = {}
        self.load_all_plugins()

    def load_all_plugins(self):
        """Load all installed plugins"""
        if not os.path.exists(PLUGIN_DIR):
            os.makedirs(PLUGIN_DIR, exist_ok=True)
            return

        for plugin_name in os.listdir(PLUGIN_DIR):
            plugin_path = os.path.join(PLUGIN_DIR, plugin_name)
            if not os.path.isdir(plugin_path):
                continue

            # Look for main.py
            main_file = os.path.join(plugin_path, "main.py")
            if not os.path.exists(main_file):
                continue

            try:
                self.load_plugin(plugin_name, main_file)
            except Exception as e:
                print(f"Failed to load plugin {plugin_name}: {e}", file=sys.stderr)

    def load_plugin(self, name: str, file_path: str):
        """Load a single plugin"""
        spec = importlib.util.spec_from_file_location(name, file_path)
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)

        # Find the plugin class
        for item_name in dir(module):
            item = getattr(module, item_name)
            if (inspect.isclass(item) and
                issubclass(item, VeriBitsPluginAPI) and
                item is not VeriBitsPluginAPI):

                plugin_instance = item(self.api_client)
                self.plugins[name] = plugin_instance
                plugin_instance.on_enable()
                return

        raise Exception(f"No plugin class found in {file_path}")

    def trigger_event(self, event_name: str, *args, **kwargs):
        """
        Trigger an event for all plugins

        Args:
            event_name: Name of the event (e.g., 'on_hash_found')
            *args, **kwargs: Event parameters
        """
        for plugin_name, plugin in self.plugins.items():
            try:
                handler = getattr(plugin, event_name, None)
                if handler and callable(handler):
                    handler(*args, **kwargs)
            except Exception as e:
                print(f"Plugin {plugin_name} error in {event_name}: {e}", file=sys.stderr)

    def install_plugin(self, source: str):
        """
        Install a plugin from a source

        Args:
            source: Git URL, local path, or plugin registry name
        """
        # For now, just copy from local path
        # In production, you'd support git clone, npm-style registry, etc.

        if os.path.isdir(source):
            plugin_name = os.path.basename(source)
            dest = os.path.join(PLUGIN_DIR, plugin_name)

            if os.path.exists(dest):
                print(f"Plugin {plugin_name} already installed")
                return

            # Copy plugin
            import shutil
            shutil.copytree(source, dest)

            print(f"✓ Installed plugin: {plugin_name}")

            # Load it
            main_file = os.path.join(dest, "main.py")
            if os.path.exists(main_file):
                self.load_plugin(plugin_name, main_file)
                self.plugins[plugin_name].on_install()
        else:
            print(f"Error: Plugin source not found: {source}")

    def uninstall_plugin(self, name: str):
        """
        Uninstall a plugin

        Args:
            name: Plugin name
        """
        if name in self.plugins:
            self.plugins[name].on_uninstall()
            del self.plugins[name]

        plugin_path = os.path.join(PLUGIN_DIR, name)
        if os.path.exists(plugin_path):
            import shutil
            shutil.rmtree(plugin_path)
            print(f"✓ Uninstalled plugin: {name}")
        else:
            print(f"Plugin not found: {name}")

    def list_plugins(self):
        """List all installed plugins"""
        if not self.plugins:
            print("No plugins installed")
            return

        print(f"\nInstalled Plugins ({len(self.plugins)}):\n")
        for name, plugin in self.plugins.items():
            print(f"  • {plugin.name} v{plugin.version}")
            print(f"    {plugin.description}")
            print(f"    By: {plugin.author}")
            print()

    def get_plugin(self, name: str) -> Optional[VeriBitsPluginAPI]:
        """Get a plugin by name"""
        return self.plugins.get(name)


# Example plugin specification
class ExamplePlugin(VeriBitsPluginAPI):
    """Example plugin showing all features"""

    name = "example-plugin"
    version = "1.0.0"
    author = "VeriBits Team"
    description = "Example plugin demonstrating the plugin API"

    def on_install(self):
        self.log("Plugin installed!", "success")
        # Set default config
        self.save_config({
            'enabled': True,
            'notify_on_hash_found': True
        })

    def on_hash_found(self, hash_value: str, plaintext: str, hash_type: str):
        if self.config.get('notify_on_hash_found'):
            self.log(f"Hash cracked: {hash_value[:16]}... = {plaintext}", "success")

    def on_malware_detected(self, file_path: str, threats: List[Dict]):
        self.log(f"ALERT: Malware detected in {file_path}", "error")
        self.log(f"Threats: {len(threats)}", "warning")


if __name__ == '__main__':
    # Test the plugin system
    print("VeriBits Plugin API Test")
    print("=" * 50)

    # Create a mock API client
    class MockAPI:
        pass

    api = MockAPI()

    # Test plugin manager
    manager = PluginManager(api)
    manager.list_plugins()

    # Test event triggering
    manager.trigger_event('on_hash_found',
                         '5f4dcc3b5aa765d61d8327deb882cf99',
                         'password',
                         'md5')
