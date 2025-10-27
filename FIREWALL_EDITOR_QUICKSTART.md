# Firewall Editor - Quick Start Guide

## 🚀 Get Started in 5 Minutes

### Step 1: Run the Migration (30 seconds)

```bash
# Connect to your PostgreSQL database
psql -U veribits_user -d veribits

# Run the migration
\i db/migrations/012_firewall_configs.sql

# Verify tables were created
\dt firewall*

# Exit
\q
```

Expected output:
```
Migration 012 completed successfully!
Created tables: firewall_configs, firewall_tags, firewall_deployments
```

### Step 2: Access the Tool (1 minute)

1. Open your browser
2. Navigate to: `http://localhost:8080/tool/firewall-editor.php`
3. You should see the Firewall Configuration Editor

### Step 3: Upload Your First Config (2 minutes)

**Option A: Upload Existing Config**

```bash
# Get your current firewall rules
iptables-save > /tmp/current-firewall.rules

# Upload via web interface:
# 1. Click "Upload Config"
# 2. Drag & drop /tmp/current-firewall.rules
# 3. Enter device name: "my-server"
# 4. Click "Upload & Parse"
```

**Option B: Create from Scratch**

1. Click "Add Rule"
2. Select Chain: "INPUT"
3. Select Target: "ACCEPT"
4. Protocol: "tcp"
5. Destination Port: "22"
6. Comment: "Allow SSH"
7. Click "Add Rule"

### Step 4: Save Your Configuration (1 minute)

1. Review rules in the visual editor
2. Click "Save to Account"
3. Enter description: "Initial firewall configuration"
4. Click "Save"

### Step 5: Test the API (1 minute)

```bash
# Create an API key (if you haven't already)
curl -X POST http://localhost:8080/api/v1/api-keys \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_SESSION_TOKEN" \
  -d '{"description": "Firewall test key"}'

# Save the API key from the response

# Test retrieval
curl "http://localhost:8080/get-iptables.php?key=YOUR_API_KEY&device=my-server"
```

Expected output:
```
# VeriBits Firewall Configuration
# Device: my-server
# Type: iptables
# Version: 1
...
(your firewall rules)
```

## 🎯 Common Tasks

### Upload and Apply Firewall Rules

```bash
# 1. Save your current rules
iptables-save > /root/firewall-backup.rules

# 2. Retrieve from VeriBits and apply
curl "http://localhost:8080/get-iptables.php?key=YOUR_API_KEY&device=my-server" | iptables-restore

# Or use the helper script
sudo ./scripts/get-firewall-config.sh \
  --api-key YOUR_API_KEY \
  --device my-server \
  --apply
```

### Create Multiple Versions

```bash
# Version 1: Basic rules
# (Create via web interface)

# Version 2: Add HTTPS
# (Edit in web interface, add port 443 rule)

# Version 3: Add custom port
# (Edit in web interface, add port 8080 rule)

# Compare versions
curl "http://localhost:8080/api/v1/firewall/diff?old=UUID_V1&new=UUID_V2" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Organize by Device

```bash
# Save different configs for different servers
# Device: web-server-01
# Device: db-server-01
# Device: lb-server-01

# Retrieve specific device config
curl "http://localhost:8080/get-iptables.php?key=YOUR_API_KEY&device=web-server-01"
```

### Automated Backups

```bash
# Add to crontab for daily backups
0 2 * * * iptables-save | curl -F "config_file=@-" \
  -F "firewall_type=iptables" \
  -F "device_name=$(hostname)" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:8080/api/v1/firewall/upload
```

## 🧪 Quick Test

Run the test suite to verify everything works:

```bash
# Make executable
chmod +x tests/test-firewall-editor.sh

# Run tests (update API_URL if needed)
API_URL=http://localhost:8080 ./tests/test-firewall-editor.sh
```

Expected output:
```
======================================
  Firewall Editor Test Suite
======================================
[PASS] Test user created
[PASS] User logged in successfully
[PASS] API key created
[PASS] Configuration uploaded successfully
...
======================================
  Test Results
======================================
Passed: 20
Failed: 0
Total:  20
======================================
All tests passed! ✓
```

## 📋 Sample Configurations

### Basic Web Server

```bash
*filter
:INPUT DROP [0:0]
:FORWARD DROP [0:0]
:OUTPUT ACCEPT [0:0]

# Allow loopback
-A INPUT -i lo -j ACCEPT

# Allow established connections
-A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT

# Allow SSH (custom port for security)
-A INPUT -p tcp --dport 2222 -m comment --comment "SSH" -j ACCEPT

# Allow HTTP/HTTPS
-A INPUT -p tcp --dport 80 -m comment --comment "HTTP" -j ACCEPT
-A INPUT -p tcp --dport 443 -m comment --comment "HTTPS" -j ACCEPT

# Allow ping
-A INPUT -p icmp --icmp-type echo-request -j ACCEPT

COMMIT
```

### Database Server

```bash
*filter
:INPUT DROP [0:0]
:FORWARD DROP [0:0]
:OUTPUT ACCEPT [0:0]

# Allow loopback
-A INPUT -i lo -j ACCEPT

# Allow established connections
-A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT

# Allow SSH from admin network only
-A INPUT -s 10.0.1.0/24 -p tcp --dport 22 -m comment --comment "SSH from admin" -j ACCEPT

# Allow PostgreSQL from app servers only
-A INPUT -s 10.0.2.0/24 -p tcp --dport 5432 -m comment --comment "PostgreSQL from apps" -j ACCEPT

# Allow MySQL from app servers only
-A INPUT -s 10.0.2.0/24 -p tcp --dport 3306 -m comment --comment "MySQL from apps" -j ACCEPT

COMMIT
```

### Load Balancer

```bash
*filter
:INPUT DROP [0:0]
:FORWARD ACCEPT [0:0]
:OUTPUT ACCEPT [0:0]

# Allow loopback
-A INPUT -i lo -j ACCEPT

# Allow established connections
-A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT

# Allow SSH
-A INPUT -p tcp --dport 22 -j ACCEPT

# Allow HTTP/HTTPS with rate limiting
-A INPUT -p tcp --dport 80 -m limit --limit 100/sec --limit-burst 200 -j ACCEPT
-A INPUT -p tcp --dport 443 -m limit --limit 100/sec --limit-burst 200 -j ACCEPT

# Health check from monitoring
-A INPUT -s 10.0.0.100 -p tcp --dport 8080 -m comment --comment "Health check" -j ACCEPT

COMMIT
```

## 🐛 Troubleshooting

### Upload Fails

**Problem:** "Failed to upload configuration"

**Solution:**
```bash
# Check file format
head -5 your-firewall.rules
# Should start with: *filter or # Generated by iptables-save

# Verify file size
ls -lh your-firewall.rules
# Must be < 10MB

# Check firewall type matches
grep -q "ip6tables" your-firewall.rules && echo "IPv6" || echo "IPv4"
```

### Can't Apply Rules

**Problem:** "Permission denied" when applying rules

**Solution:**
```bash
# Must run as root
sudo ./scripts/get-firewall-config.sh --apply

# Check iptables is installed
which iptables
which ip6tables
which ebtables
```

### API Key Doesn't Work

**Problem:** "Invalid or inactive API key"

**Solution:**
```bash
# Check API key is active
curl http://localhost:8080/api/v1/api-keys \
  -H "Authorization: Bearer YOUR_SESSION_TOKEN"

# Create new API key if needed
curl -X POST http://localhost:8080/api/v1/api-keys \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_SESSION_TOKEN" \
  -d '{"description": "New firewall key"}'
```

### Version Not Found

**Problem:** "No firewall configuration found"

**Solution:**
```bash
# List all devices
curl "http://localhost:8080/api/v1/firewall/list" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Check device name spelling
# Device names are case-sensitive!
```

## 📚 Next Steps

1. **Read Full Documentation:** `FIREWALL_EDITOR_DOCUMENTATION.md`
2. **Review API Reference:** In the documentation
3. **Set Up Automation:** Use the CLI script in cron jobs
4. **Configure Alerts:** Set up monitoring for rule changes
5. **Plan Your Strategy:** Version control workflow

## 🆘 Get Help

- **Documentation:** `FIREWALL_EDITOR_DOCUMENTATION.md`
- **Examples:** Sample configs above
- **Tests:** Run `./tests/test-firewall-editor.sh`
- **Support:** support@veribits.com

## ✅ Checklist

- [ ] Migration run successfully
- [ ] Web interface accessible
- [ ] First configuration uploaded
- [ ] Rules displayed correctly
- [ ] Configuration saved to account
- [ ] API key created
- [ ] Public API tested
- [ ] CLI script tested
- [ ] Test suite passed
- [ ] Documentation reviewed

**You're ready to manage your firewalls with VeriBits! 🎉**
