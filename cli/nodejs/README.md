# VeriBits CLI (Node.js)

Professional security and developer tools in your terminal.

[![npm version](https://badge.fury.io/js/veribits.svg)](https://badge.fury.io/js/veribits)
[![Node.js 14+](https://img.shields.io/badge/node-%3E%3D14.0.0-brightgreen.svg)](https://nodejs.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Installation

```bash
# Install globally via npm
npm install -g veribits

# Or install globally via yarn
yarn global add veribits

# Or use npx (no installation required)
npx veribits --help
```

## Quick Start

```bash
# Check version
veribits --version

# Decode a JWT token
veribits jwt-decode "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."

# Test a regex pattern
veribits regex "\\d{3}-\\d{3}-\\d{4}" "Call me at 555-123-4567"

# Scan for secrets in a file
veribits secrets-scan ./config.yaml

# Analyze IAM policy
veribits iam-analyze ./policy.json

# Audit database connection
veribits db-audit "postgresql://user:pass@host/db"

# Generate hashes
veribits hash "password123" -a sha256
veribits hash-generate "password123" -a bcrypt

# Check security headers
veribits security-headers https://example.com

# Validate PGP key
veribits pgp-validate ./key.asc

# Detect file type
veribits file-magic ./unknown-file.bin

# Analyze PCAP files
veribits pcap-analyze ./capture.pcap

# Manage firewall configs
veribits firewall-upload ./iptables.rules -t iptables
veribits firewall-list

# Validate JSON/YAML
veribits json-validate ./config.json

# Base64 encoding/decoding
veribits base64-encode "Hello World"
veribits base64-decode "SGVsbG8gV29ybGQ="

# Scan Docker/Terraform
veribits docker-scan ./Dockerfile
veribits terraform-scan ./main.tf

# Kubernetes validation
veribits k8s-validate ./deployment.yaml

# DNS tools
veribits dnssec-validate example.com
veribits dns-propagation example.com -t A
veribits reverse-dns 8.8.8.8
```

## Commands

### Security Analysis

#### IAM Policy Analyzer
Analyze AWS IAM policies for security risks and compliance issues.

```bash
veribits iam-analyze <policy-file>

# Example
veribits iam-analyze ./my-policy.json
```

#### Secrets Scanner
Scan files for exposed secrets, API keys, credentials, and sensitive data.

```bash
veribits secrets-scan <file>

# Examples
veribits secrets-scan ./app.js
veribits secrets-scan ./config.yaml
veribits secrets-scan ./.env
```

#### Database Connection Auditor
Audit database connection strings for security vulnerabilities.

```bash
veribits db-audit <connection-string>

# Examples
veribits db-audit "postgresql://user:pass@localhost/mydb"
veribits db-audit "mongodb://admin:password@host:27017/db"
```

#### Security Headers Analyzer
Analyze HTTP security headers for a website.

```bash
veribits security-headers <url>

# Example
veribits security-headers https://veribits.com
```

### Developer Tools

#### JWT Debugger
Decode and optionally verify JWT tokens.

```bash
veribits jwt-decode <token> [options]

# Options:
#   -s, --secret <secret>  Secret for verification
#   -v, --verify           Verify token signature

# Examples
veribits jwt-decode "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
veribits jwt-decode "token..." --secret "my-secret" --verify
```

#### Hash Generator
Generate cryptographic hashes.

```bash
veribits hash <text> [options]

# Options:
#   -a, --algorithm <algo>  Hash algorithm (md5, sha1, sha256, sha512)
#                           Default: sha256

# Examples
veribits hash "password123"
veribits hash "mydata" -a md5
veribits hash "secret" -a sha512
```

#### Regex Tester
Test regular expression patterns.

```bash
veribits regex <pattern> <text> [options]

# Options:
#   -f, --flags <flags>  Regex flags (g, i, m, s)

# Examples
veribits regex "\\d+" "There are 123 numbers"
veribits regex "[A-Z]+" "HELLO world" -f "g"
veribits regex "email.*@.*\\.com" "Contact: user@example.com"
```

#### PGP Key Validator
Validate PGP/GPG keys and extract key information.

```bash
veribits pgp-validate <key-or-file>

# Examples
veribits pgp-validate ./public-key.asc
veribits pgp-validate "-----BEGIN PGP PUBLIC KEY BLOCK-----..."
```

#### File Magic Detector
Detect file type by analyzing magic numbers.

```bash
veribits file-magic <file>

# Examples
veribits file-magic ./unknown-file
veribits file-magic ./document.bin
```

### Network & Infrastructure Tools

#### PCAP Analyzer
Upload and analyze PCAP network capture files.

```bash
veribits pcap-analyze <file>

# Examples
veribits pcap-analyze ./capture.pcap
veribits pcap-analyze ./network-traffic.pcapng
```

Analyzes:
- Total packets and protocols
- Top talkers (most active IPs)
- Suspicious network activity
- Traffic patterns

#### Firewall Configuration Manager
Manage firewall configurations with version control.

```bash
# Upload firewall config
veribits firewall-upload <file> [options]

# Options:
#   -t, --type <type>    Firewall type (iptables, ip6tables, ebtables)
#   -n, --name <name>    Device name for tracking

# List saved configurations
veribits firewall-list [options]

# Options:
#   -d, --device <name>  Filter by device name
#   -t, --type <type>    Filter by firewall type

# Get specific configuration
veribits firewall-get <id>

# Examples
veribits firewall-upload ./iptables.rules -t iptables -n "web-server-01"
veribits firewall-list --device "web-server-01"
veribits firewall-get 123
```

Features:
- Parse iptables/ip6tables/ebtables rules
- Version control for all changes
- Compare configurations (diff)
- Export in multiple formats

#### DNSSEC Validator
Validate DNSSEC configuration and keys.

```bash
veribits dnssec-validate <domain>

# Examples
veribits dnssec-validate example.com
veribits dnssec-validate cloudflare.com
```

Checks:
- DNSSEC enabled status
- DS and DNSKEY records
- Chain of trust validation
- Algorithm compatibility

#### DNS Propagation Checker
Check DNS propagation across global nameservers.

```bash
veribits dns-propagation <domain> [options]

# Options:
#   -t, --type <type>  Record type (A, AAAA, MX, TXT, etc.)

# Examples
veribits dns-propagation example.com
veribits dns-propagation example.com -t MX
veribits dns-propagation example.com -t AAAA
```

Features:
- Tests 15+ global locations
- Shows consistency across servers
- Identifies propagation delays
- Displays all resolved values

#### Reverse DNS Lookup
Perform PTR (reverse DNS) lookups.

```bash
veribits reverse-dns <ip>

# Examples
veribits reverse-dns 8.8.8.8
veribits reverse-dns 1.1.1.1
veribits reverse-dns 2001:4860:4860::8888
```

Validates:
- PTR record existence
- Forward/reverse DNS match
- Multiple PTR records
- IPv4 and IPv6 support

### Infrastructure as Code Security

#### Hash Generator (Advanced)
Generate various cryptographic hashes including bcrypt, argon2.

```bash
veribits hash-generate <text> [options]

# Options:
#   -a, --algorithm <algo>  Hash algorithm
#                           (sha256, md5, sha512, bcrypt, argon2, etc.)

# Examples
veribits hash-generate "password123"
veribits hash-generate "password123" -a bcrypt
veribits hash-generate "password123" -a argon2
veribits hash-generate "password123" -a sha512
```

Supported algorithms:
- SHA family (sha1, sha256, sha512)
- MD5
- Bcrypt (with salts)
- Argon2 (recommended for passwords)
- Blake2b

#### JSON/YAML Validator
Validate and format JSON or YAML files.

```bash
veribits json-validate <input> [options]

# Options:
#   -f, --format <format>  Format type (json, yaml)
#   --pretty               Pretty print output

# Examples
veribits json-validate ./config.json
veribits json-validate ./deployment.yaml -f yaml
veribits json-validate '{"key":"value"}'
```

Features:
- Syntax validation
- Schema validation
- Pretty printing
- Format conversion
- Detailed error messages

#### Base64 Encoder/Decoder
Encode and decode Base64 data.

```bash
# Encode
veribits base64-encode <input> [options]

# Options:
#   -f, --file  Input is a file path

# Decode
veribits base64-decode <input> [options]

# Options:
#   -o, --output <file>  Output file path

# Examples
veribits base64-encode "Hello World"
veribits base64-encode ./file.bin -f
veribits base64-decode "SGVsbG8gV29ybGQ="
veribits base64-decode "..." -o output.bin
```

#### Docker Security Scanner
Scan Dockerfiles for security vulnerabilities and best practices.

```bash
veribits docker-scan <file>

# Examples
veribits docker-scan ./Dockerfile
veribits docker-scan ./docker/Dockerfile.prod
```

Detects:
- Running as root user
- Exposed secrets
- Outdated base images
- Security best practices
- COPY vs ADD usage
- Unnecessary packages

#### Terraform Security Scanner
Scan Terraform configurations for security issues.

```bash
veribits terraform-scan <file>

# Examples
veribits terraform-scan ./main.tf
veribits terraform-scan ./infrastructure/aws.tf
```

Checks:
- Public S3 buckets
- Open security groups
- Unencrypted resources
- Missing tags
- IAM overpermissions
- Compliance violations

#### Kubernetes Manifest Validator
Validate Kubernetes YAML manifests.

```bash
veribits k8s-validate <file>

# Examples
veribits k8s-validate ./deployment.yaml
veribits k8s-validate ./k8s/service.yaml
```

Validates:
- Syntax and structure
- Resource specifications
- Security contexts
- Resource limits
- Label selectors
- Best practices

## Authentication

For unlimited usage and advanced features, set your API key:

```bash
# Set environment variable
export VERIBITS_API_KEY="your-api-key-here"

# Or pass with each command
veribits --api-key "your-key" secrets-scan file.js
```

Get your API key at: https://veribits.com/dashboard

## Environment Variables

- `VERIBITS_API_URL` - Override API endpoint (default: https://veribits.com/api/v1)
- `VERIBITS_API_KEY` - Your API key for authenticated requests

You can also use a `.env` file in your project:

```env
VERIBITS_API_KEY=your-api-key-here
VERIBITS_API_URL=https://veribits.com/api/v1
```

## Usage Limits

**Anonymous (No API Key):**
- 5 free scans per 30-day period
- 50MB max file size
- All tools available

**Authenticated:**
- 50+ scans per month (Free tier)
- 200MB max file size
- Priority processing
- API access

See pricing: https://veribits.com/pricing

## Advanced Usage

### CI/CD Integration

#### GitHub Actions

```yaml
name: Security Scan
on: [push, pull_request]

jobs:
  scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: actions/setup-node@v2
        with:
          node-version: '14'
      - name: Install VeriBits CLI
        run: npm install -g veribits
      - name: Scan for secrets
        env:
          VERIBITS_API_KEY: ${{ secrets.VERIBITS_API_KEY }}
        run: |
          veribits secrets-scan ./src/config.js
          veribits secrets-scan ./.env.example
```

#### GitLab CI

```yaml
security-scan:
  image: node:14
  script:
    - npm install -g veribits
    - veribits secrets-scan ./src
  only:
    - merge_requests
  variables:
    VERIBITS_API_KEY: $VERIBITS_API_KEY
```

### Batch Processing

```bash
# Scan all JavaScript files for secrets
for file in $(find ./src -name "*.js"); do
  echo "Scanning $file..."
  veribits secrets-scan "$file"
done

# Analyze all IAM policies
for policy in $(find ./policies -name "*.json"); do
  echo "Analyzing $policy..."
  veribits iam-analyze "$policy"
done

# Detect file types in uploads directory
for file in ./uploads/*; do
  veribits file-magic "$file"
done
```

### Programmatic Usage

You can also use VeriBits as a Node.js module:

```javascript
const VeriBitsCLI = require('veribits');

const cli = new VeriBitsCLI('your-api-key');

// Scan for secrets
await cli.secretsScan('./config.js');

// Analyze IAM policy
await cli.iamAnalyze('./policy.json');

// Decode JWT
await cli.jwtDecode('token...', { secret: 'my-secret', verify: true });
```

## Available Tools

### Security & Cryptography
- 🔍 File Magic Detector
- 🔑 PGP Key Validator
- 🔐 Secrets Scanner
- 🛡️ Security Headers Analyzer
- 🗄️ Database Connection Auditor
- 🔐 IAM Policy Analyzer
- 🔐 Hash Validator
- 🔐 Hash Generator (Advanced with bcrypt/argon2)
- 💰 Cryptocurrency Address Validator
- 🎭 Steganography Detector
- 🦠 Malware Scanner
- 📦 Archive Inspector

### Developer Tools
- 🔑 JWT Debugger
- 🔤 Regex Tester
- 📝 JSON/YAML Validator
- 🔗 URL Encoder/Decoder
- 📦 Base64 Encoder/Decoder
- 🔨 Code Signing Tools

### Network & Infrastructure
- 📊 PCAP Analyzer
- 🔥 Firewall Configuration Manager (iptables/ip6tables/ebtables)
- 🌐 DNS Validator
- 🔐 DNSSEC Validator
- 🌍 DNS Propagation Checker
- 🔄 Reverse DNS Lookup
- 📝 Zone File Validator
- 🔢 IP Calculator
- 🛡️ RBL/DNSBL Checker
- 🗺️ Visual Traceroute
- 🌐 BGP Intelligence & AS Lookup
- 🔍 WHOIS Lookup

### Email Security
- 📧 Email Verification (Comprehensive)
- 📮 SPF Analyzer
- 🔏 DKIM Analyzer
- 🛡️ DMARC Analyzer
- 📬 MX Record Checker
- 🚫 Disposable Email Detector
- ⚫ Email Blacklist Checker
- 📊 Email Deliverability Score
- 🔓 Have I Been Pwned Integration

### SSL/TLS Tools
- 🔐 SSL Certificate Checker
- 🔄 Certificate Converter (PEM/DER/P12/JKS)
- 🔗 SSL Chain Resolver
- 🔑 SSL Key Pair Verifier
- 📝 CSR Validator
- 🔐 SSL Certificate Generator

### Infrastructure as Code
- 🐳 Docker Security Scanner
- 🏗️ Terraform Security Scanner
- ☸️ Kubernetes Manifest Validator

### Cloud Security
- ☁️ Cloud Storage Search
- 🔒 Cloud Storage Security Scanner
- 📦 Bucket Enumeration

## Alias Command

The CLI also supports a short alias `vb`:

```bash
# These are equivalent
veribits secrets-scan file.js
vb secrets-scan file.js
```

## Troubleshooting

### Command not found

If you get "command not found" after installation:

```bash
# Reinstall globally
npm install -g veribits

# Or use npx
npx veribits --help
```

### Permission denied

On Unix systems, you might need to use sudo:

```bash
sudo npm install -g veribits
```

Or configure npm to install packages globally without sudo:

```bash
mkdir ~/.npm-global
npm config set prefix '~/.npm-global'
echo 'export PATH=~/.npm-global/bin:$PATH' >> ~/.bashrc
source ~/.bashrc
```

### API errors

If you're getting API errors:
1. Check your internet connection
2. Verify your API key is correct
3. Check usage limits at https://veribits.com/dashboard

## Contributing

Contributions welcome! Please read our [Contributing Guide](CONTRIBUTING.md).

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Support

- 📧 Email: support@veribits.com
- 🌐 Website: https://veribits.com
- 📚 Documentation: https://docs.veribits.com
- 🐛 Issues: https://github.com/afterdarksystems/veribits-cli/issues

## About

VeriBits is a service from [After Dark Systems, LLC](https://www.afterdarksys.com/)

---

Made with ❤️ by developers, for developers.
