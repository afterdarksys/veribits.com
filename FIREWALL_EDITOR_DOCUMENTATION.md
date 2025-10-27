# VeriBits Firewall Configuration Editor

## Overview

The VeriBits Firewall Configuration Editor is an enterprise-grade visual tool for managing iptables (IPv4), ip6tables (IPv6), and ebtables (Layer 2 bridge) firewall configurations with version control, live preview, and automation capabilities.

## Features

### 🎨 Visual Editor
- **Drag-and-drop rule reordering** - Reorder firewall rules with simple drag-and-drop
- **Color-coded rule types** - Visual indicators for ACCEPT (green), DROP/REJECT (red), LOG (yellow)
- **Live command preview** - See generated iptables commands in real-time
- **Chain organization** - Rules organized by chain (INPUT, OUTPUT, FORWARD, etc.)
- **Expandable sections** - Collapse/expand chains for better organization

### 🔐 Multi-Protocol Support
- **iptables** - IPv4 packet filtering firewall
- **ip6tables** - IPv6 packet filtering firewall
- **ebtables** - Layer 2 Ethernet bridge firewall

### 📝 Rule Management
- **Upload existing configs** - Import iptables-save/ebtables-save output
- **Visual rule editor** - Add/edit rules with dropdown menus and form validation
- **Rule options** - Protocol, source/dest IP, ports, interfaces, connection state
- **Advanced options** - State tracking, logging, rate limiting, custom options
- **Bulk operations** - Delete multiple rules, reorder entire chains

### 📦 Version Control
- **Automatic versioning** - Each save creates a new version
- **Version history** - Browse and restore previous configurations
- **Diff viewer** - Compare any two versions side-by-side
- **Rollback support** - Restore previous configurations instantly
- **Device tracking** - Organize configs by device/server name

### 💾 Export & Download
- **iptables-save format** - Standard format for easy deployment
- **JSON export** - Structured data for automation
- **CLI integration** - Retrieve configs via API
- **Direct download** - Download configurations as files

### 🔄 Automation & CLI
- **REST API** - Full API for programmatic access
- **CLI script** - Bash script for automated retrieval and deployment
- **API key authentication** - Secure access via API keys
- **Webhook support** - Trigger deployments via webhooks

## Web Interface

### Accessing the Tool

Navigate to: `https://veribits.com/tool/firewall-editor.php`

### Getting Started

1. **Select Firewall Type**
   - Click on iptables (IPv4), ip6tables (IPv6), or ebtables (Layer 2)

2. **Upload Existing Configuration**
   - Click "Upload Config"
   - Drag & drop or browse for your iptables-save output file
   - Enter device name (optional, e.g., "web-server-01")
   - Click "Upload & Parse"

3. **Add Rules Manually**
   - Click "Add Rule"
   - Select chain (INPUT, OUTPUT, FORWARD, etc.)
   - Choose target (ACCEPT, DROP, REJECT, LOG)
   - Set protocol, IPs, ports, and options
   - Click "Add Rule"

4. **Edit Existing Rules**
   - Click "Edit" on any rule
   - Modify parameters
   - Save changes

5. **Reorder Rules**
   - Drag and drop rules within a chain
   - Order matters in firewall rules!

6. **Save to Account**
   - Click "Save to Account"
   - Enter version description
   - Configuration is saved with version control

7. **Download Configuration**
   - Click "Download" to get iptables-save format file
   - Apply on server: `iptables-restore < downloaded_file.rules`

## API Reference

### Base URL
```
https://veribits.com/api/v1/firewall
```

### Authentication
All API endpoints require authentication via session cookie or API key.

### Endpoints

#### Upload Configuration
```
POST /api/v1/firewall/upload
Content-Type: multipart/form-data

Parameters:
  - config_file: File (required) - iptables-save format file
  - firewall_type: String (required) - "iptables", "ip6tables", or "ebtables"
  - device_name: String (optional) - Device identifier

Response:
{
  "success": true,
  "data": {
    "type": "iptables",
    "chains": { ... },
    "deviceName": "web-server-01",
    "rawConfig": "...",
    "stats": {
      "total_chains": 3,
      "total_rules": 15
    }
  }
}
```

#### Save Configuration
```
POST /api/v1/firewall/save
Content-Type: application/json

Body:
{
  "config_type": "iptables",
  "config_data": "# iptables rules...",
  "device_name": "web-server-01",
  "description": "Updated SSH port to 2222"
}

Response:
{
  "success": true,
  "data": {
    "id": "uuid",
    "version": 5,
    "message": "Configuration saved successfully"
  }
}
```

#### List Configurations
```
GET /api/v1/firewall/list?device_name=web-server-01&config_type=iptables&limit=50

Response:
{
  "success": true,
  "data": {
    "configs": [
      {
        "id": "uuid",
        "device_name": "web-server-01",
        "config_type": "iptables",
        "version": 5,
        "description": "Updated SSH port",
        "created_at": "2025-10-27T10:30:00Z",
        "config_size": 4096
      }
    ],
    "total": 15,
    "limit": 50,
    "offset": 0
  }
}
```

#### Get Specific Configuration
```
GET /api/v1/firewall/get?id=uuid

Response:
{
  "success": true,
  "data": {
    "id": "uuid",
    "user_id": "uuid",
    "device_name": "web-server-01",
    "config_type": "iptables",
    "config_data": "# Full configuration...",
    "version": 5,
    "description": "Updated SSH port",
    "created_at": "2025-10-27T10:30:00Z"
  }
}
```

#### Compare Versions (Diff)
```
GET /api/v1/firewall/diff?old=uuid1&new=uuid2

Response:
{
  "success": true,
  "data": {
    "old": { "id": "uuid1", "version": 4, ... },
    "new": { "id": "uuid2", "version": 5, ... },
    "diff": [
      "  -A INPUT -p tcp --dport 22 -j ACCEPT",
      "- -A INPUT -p tcp --dport 80 -j ACCEPT",
      "+ -A INPUT -p tcp --dport 2222 -j ACCEPT",
      "+ -A INPUT -p tcp --dport 443 -j ACCEPT"
    ]
  }
}
```

#### Export Configuration
```
GET /api/v1/firewall/export?id=uuid&format=text

Response: Plain text iptables-save format file
```

## CLI Integration

### Public API Endpoint

Retrieve configurations without web login using API keys:

```bash
GET /get-iptables.php?key=YOUR_API_KEY&device=web-server-01&version=latest&output=text
```

#### Parameters
- `key` (required) - Your VeriBits API key
- `account` (optional) - Your account ID
- `device` (optional) - Device name to retrieve config for
- `version` (optional) - Version number or "latest" (default)
- `output` (optional) - "text" (default) or "json"

#### Example Responses

**Text Output:**
```bash
curl "https://veribits.com/get-iptables.php?key=YOUR_API_KEY&device=web-server-01"

# VeriBits Firewall Configuration
# Device: web-server-01
# Type: iptables
# Version: 5
# Description: Updated SSH port to 2222
# Retrieved: 2025-10-27 10:30:00
#
# Usage:
#   Apply: iptables-restore < this_file

*filter
:INPUT DROP [0:0]
:FORWARD DROP [0:0]
:OUTPUT ACCEPT [0:0]
-A INPUT -i lo -j ACCEPT
-A INPUT -p tcp --dport 2222 -j ACCEPT
-A INPUT -p tcp --dport 80 -j ACCEPT
-A INPUT -p tcp --dport 443 -j ACCEPT
-A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
COMMIT
```

**JSON Output:**
```bash
curl "https://veribits.com/get-iptables.php?key=YOUR_API_KEY&device=web-server-01&output=json"

{
  "success": true,
  "data": {
    "id": "uuid",
    "device_name": "web-server-01",
    "config_type": "iptables",
    "version": 5,
    "description": "Updated SSH port to 2222",
    "created_at": "2025-10-27T10:30:00Z",
    "config_data": "# Full config..."
  },
  "metadata": {
    "account_id": "uuid",
    "account_email": "user@example.com",
    "account_tier": "premium",
    "retrieved_at": "2025-10-27 10:35:00"
  }
}
```

### Automated Retrieval Script

Use the provided bash script for automated firewall management:

```bash
# Retrieve and save to file
./scripts/get-firewall-config.sh \
  --api-key YOUR_API_KEY \
  --device web-server-01 \
  --file /tmp/firewall.rules

# Retrieve specific version
./scripts/get-firewall-config.sh \
  --api-key YOUR_API_KEY \
  --device web-server-01 \
  --version 3 \
  --file /tmp/firewall-v3.rules

# Retrieve and apply (requires root)
sudo ./scripts/get-firewall-config.sh \
  --api-key YOUR_API_KEY \
  --device web-server-01 \
  --apply

# Using environment variables
export VERIBITS_API_KEY=your_key_here
export VERIBITS_DEVICE=web-server-01
./scripts/get-firewall-config.sh --apply
```

#### Script Options
```
-k, --api-key KEY       VeriBits API key
-a, --account ID        Account ID (optional)
-d, --device NAME       Device name
-v, --version NUM       Version number (default: latest)
-o, --output FORMAT     Output format: text or json
-f, --file PATH         Save to file
-A, --apply             Apply configuration (requires root)
-B, --no-backup         Skip backing up current rules
-u, --url URL           Custom API URL
-h, --help              Show help
```

## Database Schema

### firewall_configs Table
```sql
CREATE TABLE firewall_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id),
    device_name VARCHAR(255) NOT NULL,
    config_type VARCHAR(20) NOT NULL,  -- 'iptables', 'ip6tables', 'ebtables'
    config_data TEXT NOT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    description TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

### firewall_tags Table
```sql
CREATE TABLE firewall_tags (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    config_id UUID NOT NULL REFERENCES firewall_configs(id),
    tag_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

### firewall_deployments Table
```sql
CREATE TABLE firewall_deployments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    config_id UUID NOT NULL REFERENCES firewall_configs(id),
    server_hostname VARCHAR(255) NOT NULL,
    server_ip VARCHAR(45),
    deployed_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deployed_by UUID REFERENCES users(id),
    deployment_status VARCHAR(20) DEFAULT 'success',
    deployment_notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

## Use Cases

### 1. Development to Production Pipeline
```bash
# Save development firewall config
./get-firewall-config.sh -k API_KEY -d dev-server -f dev-firewall.rules

# Review and test
iptables-restore --test < dev-firewall.rules

# Apply to production
./get-firewall-config.sh -k API_KEY -d prod-server --apply
```

### 2. Disaster Recovery
```bash
# Backup current firewall
iptables-save > /root/firewall-backup.rules

# Restore from VeriBits
./get-firewall-config.sh -k API_KEY -d web-server-01 -v 10 --apply
```

### 3. Multi-Server Deployment
```bash
# Deploy same config to multiple servers
for server in web-{01..05}; do
  ssh $server "curl -s 'https://veribits.com/get-iptables.php?key=API_KEY&device=web-template' | iptables-restore"
done
```

### 4. Configuration Audit
```bash
# Get all versions in JSON
curl "https://veribits.com/api/v1/firewall/list?device_name=web-server-01" \
  -H "Authorization: Bearer TOKEN"

# Compare two versions
curl "https://veribits.com/api/v1/firewall/diff?old=uuid1&new=uuid2" \
  -H "Authorization: Bearer TOKEN"
```

## Best Practices

### Security
1. **Never commit API keys** - Use environment variables
2. **Backup before applying** - Script backs up by default
3. **Test configurations** - Use `iptables-restore --test` first
4. **Review changes** - Always review diff before applying
5. **Limit API key scope** - Use read-only keys where possible

### Version Control
1. **Descriptive version notes** - Document what changed and why
2. **Tag important versions** - Mark production-ready configs
3. **Regular backups** - Save configs after major changes
4. **Test before deploy** - Apply to test environment first

### Organization
1. **Consistent naming** - Use hostname or function as device name
2. **Group by role** - web-servers, db-servers, etc.
3. **Document rules** - Use comments in iptables rules
4. **Track deployments** - Record where configs are deployed

## Troubleshooting

### Upload Fails
- Ensure file is valid iptables-save format
- Check file size (max 10MB)
- Verify firewall type matches file content

### Apply Fails
- Run with sudo/root privileges
- Check current rules don't conflict
- Verify network connectivity won't be broken
- Review backup location

### API Errors
- Verify API key is active
- Check rate limits
- Ensure device name exists
- Validate version number

### Version Conflicts
- Each device/config_type has separate versioning
- Version numbers auto-increment
- Cannot have duplicate versions

## Migration Guide

### From Manual iptables Management

1. **Export current rules:**
   ```bash
   iptables-save > /tmp/current-firewall.rules
   ```

2. **Upload to VeriBits:**
   - Go to Firewall Editor
   - Click "Upload Config"
   - Select file and enter device name
   - Click "Upload & Parse"

3. **Review and save:**
   - Review parsed rules in visual editor
   - Click "Save to Account"
   - Enter description: "Initial import from server"

4. **Set up automation:**
   ```bash
   # Add to cron for daily backups
   0 2 * * * iptables-save | curl -F "config_file=@-" -F "firewall_type=iptables" \
     -F "device_name=$(hostname)" "https://veribits.com/api/v1/firewall/upload"
   ```

## Support

- **Documentation:** https://veribits.com/docs/firewall-editor
- **API Reference:** https://veribits.com/api/v1/docs
- **Support Email:** support@veribits.com
- **GitHub Issues:** https://github.com/afterdarksys/veribits/issues

## License

© 2025 After Dark Systems, LLC. All rights reserved.
