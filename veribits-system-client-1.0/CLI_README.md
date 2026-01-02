# VeriBits CLI - Professional Security & Forensics Toolkit

The VeriBits CLI provides a powerful command-line interface to all VeriBits tools. Available in **three languages** for maximum compatibility:

- **Python** (`veribits`) - Recommended for most users
- **PHP** (`veribits.php`) - For PHP-centric environments
- **Node.js** (`veribits.js`) - For JavaScript/Node.js users

All three versions provide identical functionality and API compatibility.

## 📦 Installation

### Python CLI (Recommended)

```bash
# Make executable
chmod +x veribits

# Add to PATH (optional)
sudo cp veribits /usr/local/bin/

# Verify installation
veribits --version
```

### PHP CLI

```bash
# Requirements: PHP 7.4+ with curl extension
php -v  # Check PHP version

# Make executable
chmod +x veribits.php

# Add to PATH (optional)
sudo cp veribits.php /usr/local/bin/veribits-php

# Verify installation
./veribits.php --version
```

### Node.js CLI

```bash
# Requirements: Node.js 14+
node --version  # Check Node version

# Make executable
chmod +x veribits.js

# Add to PATH (optional)
sudo cp veribits.js /usr/local/bin/veribits-node

# Verify installation
./veribits.js --version
```

## 🔑 Configuration

Set your API key for unlimited access:

```bash
# Python
./veribits config set api_key YOUR_API_KEY_HERE

# PHP
./veribits.php config set api_key YOUR_API_KEY_HERE

# Node.js
./veribits.js config set api_key YOUR_API_KEY_HERE
```

Get your API key from: https://veribits.com/dashboard

## 🛠️ Available Commands

All three CLI versions support the same commands:

### Hash Lookup & Analysis

**Lookup a single hash:**
```bash
veribits hash lookup 5f4dcc3b5aa765d61d8327deb882cf99
veribits hash lookup <hash> --type md5 --verbose
```

**Batch lookup from file:**
```bash
veribits hash batch hashes.txt
veribits hash batch hashes.txt --output results.json
```

**Identify hash type:**
```bash
veribits hash identify 5f4dcc3b5aa765d61d8327deb882cf99
```

### Password Recovery

**Analyze password-protected file:**
```bash
veribits password analyze document.pdf
```

**Remove password (when known):**
```bash
veribits password remove document.pdf -p mypassword
veribits password remove document.pdf -p mypassword -o unlocked.pdf
```

**Crack password (dictionary attack):**
```bash
veribits password crack document.pdf -w common
veribits password crack document.pdf -w common -m 10000
```

Supported file types: PDF, DOCX, XLSX, PPTX, ZIP

### Disk Forensics (The Sleuth Kit)

**Analyze disk image:**
```bash
veribits disk analyze image.dd
veribits disk analyze image.dd --operations list_files,recover_deleted,timeline
veribits disk analyze image.dd -o results.json
```

**Recover deleted files:**
```bash
veribits disk recover image.dd
veribits disk recover image.dd -o recovered_files/
```

**Generate forensic timeline:**
```bash
veribits disk timeline image.dd
veribits disk timeline image.dd -o timeline.csv
```

Supported formats: DD, E01, AFF, VHD, VMDK

### osquery SQL Interface

**Execute SQL query:**
```bash
veribits osquery run "SELECT * FROM processes LIMIT 10"
veribits osquery run "SELECT * FROM listening_ports" -o output.json
veribits osquery run "SELECT * FROM users" --timeout 60
```

**Run query pack:**
```bash
veribits osquery pack security-audit
```

**List available tables:**
```bash
veribits osquery tables
veribits osquery tables --verbose
```

### Netcat - Network Swiss Army Knife

**Test TCP connection:**
```bash
veribits netcat example.com 80
veribits netcat 192.168.1.1 22 --verbose
```

**Test UDP connection:**
```bash
veribits netcat 8.8.8.8 53 --protocol udp
```

**Send data and receive response:**
```bash
veribits netcat example.com 80 --data "GET / HTTP/1.1\nHost: example.com\n\n"
```

**Port scanning (zero I/O mode):**
```bash
veribits netcat example.com 443 --zero-io
```

**Advanced options:**
```bash
veribits netcat example.com 80 \
  --protocol tcp \
  --data "GET / HTTP/1.1\nHost: example.com\n\n" \
  --timeout 10 \
  --wait-time 5 \
  --verbose \
  --source-port 12345
```

### System Scan

**Run full system file hash scan:**
```bash
veribits scan
```

This will use the `file_hasher.py` script to scan your system.

### Configuration

**Set configuration value:**
```bash
veribits config set api_key YOUR_KEY
veribits config set api_url https://veribits.com
```

**Show current configuration:**
```bash
veribits config show
```

## 📚 Usage Examples

### Example 1: Hash Lookup Workflow

```bash
# Single hash lookup
veribits hash lookup 5f4dcc3b5aa765d61d8327deb882cf99

# Create file with multiple hashes
cat > hashes.txt <<EOF
5f4dcc3b5aa765d61d8327deb882cf99
098f6bcd4621d373cade4e832627b4f6
5d41402abc4b2a76b9719d911017c592
EOF

# Batch lookup
veribits hash batch hashes.txt --output results.json

# View results
cat results.json
```

### Example 2: Network Diagnostics

```bash
# Test HTTP server
veribits netcat example.com 80 --data "GET / HTTP/1.1\nHost: example.com\n\n"

# Test HTTPS (connection only)
veribits netcat example.com 443

# Test SSH server (grab banner)
veribits netcat example.com 22 --verbose

# Test SMTP server
veribits netcat mail.example.com 25

# Scan common ports
for port in 20 21 22 23 25 80 443 3306 5432 6379; do
    echo "Testing port $port..."
    veribits netcat localhost $port --zero-io --timeout 2
done
```

### Example 3: osquery System Monitoring

```bash
# List all running processes
veribits osquery run "SELECT pid, name, path FROM processes"

# Find processes listening on network ports
veribits osquery run "SELECT * FROM listening_ports WHERE port < 1024"

# Check user accounts
veribits osquery run "SELECT username, uid, shell FROM users"

# Find SUID binaries (security audit)
veribits osquery run "SELECT * FROM suid_bin"

# Export results for analysis
veribits osquery run "SELECT * FROM processes" -o processes.json
```

### Example 4: Password Recovery

```bash
# Analyze a protected PDF
veribits password analyze report.pdf

# Remove password if you know it
veribits password remove report.pdf -p SecretPass123 -o unlocked_report.pdf

# Attempt to crack password
veribits password crack report.pdf -w common -m 5000

# Try with different wordlist
veribits password crack report.pdf -w numeric -m 10000
```

### Example 5: Disk Forensics

```bash
# Quick analysis
veribits disk analyze evidence.dd --operations list_files,fsstat

# Full forensic analysis
veribits disk analyze evidence.dd \
  --operations list_files,recover_deleted,timeline,fsstat,partitions \
  -o full_analysis.json

# Generate timeline
veribits disk timeline evidence.dd -o timeline.csv

# Analyze the timeline
cat timeline.csv | grep "2024-01-15"
```

## 🔧 Advanced Usage

### Using with Bash Scripts

```bash
#!/bin/bash
# scan-network.sh - Scan common ports on a host

HOST="$1"
if [ -z "$HOST" ]; then
    echo "Usage: $0 <host>"
    exit 1
fi

echo "Scanning $HOST for common services..."

PORTS="21 22 23 25 80 110 143 443 3306 5432 6379 8080"

for PORT in $PORTS; do
    echo -n "Port $PORT: "
    if veribits netcat "$HOST" "$PORT" --zero-io --timeout 2 2>&1 | grep -q "Connected"; then
        SERVICE=$(veribits netcat "$HOST" "$PORT" --timeout 2 2>&1 | grep "Detected Service" | cut -d: -f2)
        echo "OPEN $SERVICE"
    else
        echo "CLOSED"
    fi
done
```

### Using with Python Scripts

```python
#!/usr/bin/env python3
import subprocess
import json

def lookup_hash(hash_value):
    """Lookup a hash using VeriBits CLI"""
    result = subprocess.run(
        ['./veribits', 'hash', 'lookup', hash_value],
        capture_output=True,
        text=True
    )
    return result.stdout

# Batch processing
hashes = [
    '5f4dcc3b5aa765d61d8327deb882cf99',
    '098f6bcd4621d373cade4e832627b4f6'
]

for h in hashes:
    print(f"Looking up: {h}")
    result = lookup_hash(h)
    print(result)
```

### CI/CD Integration

**GitHub Actions:**
```yaml
name: Security Scan
on: [push]
jobs:
  scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup VeriBits CLI
        run: |
          chmod +x veribits
          ./veribits config set api_key ${{ secrets.VERIBITS_API_KEY }}
      - name: Scan for exposed secrets
        run: ./veribits secrets ./src
```

## 📊 Output Formats

### JSON Output

Many commands support `--output file.json` for machine-readable results:

```bash
# Hash batch lookup
veribits hash batch hashes.txt --output results.json

# osquery results
veribits osquery run "SELECT * FROM processes" --output processes.json

# Disk forensics analysis
veribits disk analyze image.dd -o analysis.json
```

### Processing JSON with jq

```bash
# Extract only found hashes
veribits hash batch hashes.txt -o results.json
cat results.json | jq '.[] | select(.found == true)'

# Get process count
veribits osquery run "SELECT * FROM processes" -o proc.json
cat proc.json | jq 'length'
```

## 🆘 Troubleshooting

### "Permission denied" errors

```bash
chmod +x veribits veribits.php veribits.js
```

### "API Error: 429 Rate limit exceeded"

Set your API key for unlimited access:
```bash
veribits config set api_key YOUR_API_KEY
```

### Python "ModuleNotFoundError"

Install required dependencies:
```bash
pip install requests
```

### PHP "Call to undefined function curl_init"

Install PHP curl extension:
```bash
# Ubuntu/Debian
sudo apt-get install php-curl

# macOS
brew install php
```

### Node.js "MODULE_NOT_FOUND"

Verify Node.js version:
```bash
node --version  # Should be 14+
```

## 🔒 Security Best Practices

1. **Store API keys securely** - Never commit API keys to version control
2. **Use environment variables** - Export `VERIBITS_API_KEY` in your shell profile
3. **Limit scope** - Only scan networks/hosts you own or have permission to test
4. **Review output** - Always review scan results before taking action
5. **Rotate keys** - Periodically rotate your API keys

## 📖 Documentation

- **Web Interface:** https://veribits.com/tools.php
- **API Documentation:** https://veribits.com/api/docs
- **Support:** https://veribits.com/support.php
- **CLI Documentation:** https://veribits.com/cli.php

## 💡 Tips & Tricks

### Create Aliases

Add to your `~/.bashrc` or `~/.zshrc`:

```bash
alias vb='./veribits'
alias vb-hash='./veribits hash lookup'
alias vb-nc='./veribits netcat'
alias vb-osq='./veribits osquery run'
```

Then use:
```bash
vb-hash 5f4dcc3b5aa765d61d8327deb882cf99
vb-nc example.com 80
vb-osq "SELECT * FROM processes"
```

### Quick Port Scanner

```bash
function portscan() {
    for port in {1..1024}; do
        ./veribits netcat "$1" "$port" --zero-io --timeout 1 2>&1 | grep -q "Connected" && echo "Port $port: OPEN"
    done
}

portscan localhost
```

### System Monitoring Script

```bash
#!/bin/bash
# monitor.sh - Continuous system monitoring

while true; do
    echo "=== System Check: $(date) ==="

    # Check listening ports
    ./veribits osquery run "SELECT * FROM listening_ports WHERE port < 1024" \
        | tee ports.log

    # Check running processes
    ./veribits osquery run "SELECT pid, name FROM processes ORDER BY pid" \
        | tee processes.log

    sleep 300  # Run every 5 minutes
done
```

## 🎯 Version Comparison

| Feature | Python | PHP | Node.js |
|---------|--------|-----|---------|
| Hash Lookup | ✅ | ✅ | ✅ |
| Password Recovery | ✅ | ✅ | ⚠️ Limited |
| Disk Forensics | ✅ | ✅ | ⚠️ Limited |
| osquery | ✅ | ✅ | ✅ |
| Netcat | ✅ | ✅ | ✅ |
| File Upload | ✅ | ✅ | ⚠️ Basic |
| Config Management | ✅ | ✅ | ✅ |
| JSON Output | ✅ | ✅ | ✅ |

⚠️ = Limited functionality due to language constraints. Use Python CLI for full features.

## 📝 License

© 2025 VeriBits by After Dark Systems. All rights reserved.

## 🆘 Support

For issues or questions:
- Email: support@afterdarksys.com
- Web: https://veribits.com/support.php
- GitHub: https://github.com/afterdarksystems/veribits

---

**Version:** 1.0.0
**Last Updated:** 2025-01-28
