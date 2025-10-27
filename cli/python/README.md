# VeriBits CLI (Python)

Professional security and developer tools for your terminal.

[![PyPI version](https://badge.fury.io/py/veribits.svg)](https://badge.fury.io/py/veribits)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Installation

```bash
pip install veribits
```

## Quick Start

```bash
# Analyze AWS IAM policy
veribits iam-analyze policy.json

# Scan for secrets
veribits secrets-scan config.js

# Audit database connection
veribits db-audit "postgresql://user:pass@host/db"

# Check security headers
veribits security-headers https://example.com

# Interactive mode
veribits console
```

## Available Commands

### Security Tools

#### IAM Policy Analyzer
Analyze AWS IAM policies for security vulnerabilities and best practices.
```bash
veribits iam-analyze policy.json
```

#### Secrets Scanner
Detect hardcoded API keys, passwords, tokens, and other sensitive credentials in source code.
```bash
veribits secrets-scan app.js
veribits secrets-scan config.yaml
```

#### Database Connection Auditor
Audit database connection strings for security issues and provide secure alternatives.
```bash
veribits db-audit "postgresql://user:pass@localhost:5432/db"
veribits db-audit "mysql://root:password@localhost/database"
```

#### Security Headers Analyzer
Analyze HTTP security headers and get recommendations for improvement.
```bash
veribits security-headers https://example.com
```

#### Hash Validator
Validate and identify hash types (MD5, SHA1, SHA256, SHA512, etc.).
```bash
veribits hash-validate 5d41402abc4b2a76b9719d911017c592
```

#### PGP Key Validator
Validate PGP/GPG public keys and extract key information.
```bash
veribits pgp-validate publickey.asc
veribits pgp-validate "-----BEGIN PGP PUBLIC KEY BLOCK-----..."
```

### SSL/TLS Tools

#### SSL Certificate Check
Check SSL/TLS certificates for validity, expiration, and security issues.
```bash
veribits ssl-check example.com
veribits ssl-check example.com --port 8443
```

#### Certificate Converter
Convert between certificate formats (PEM, DER, P12, JKS).
```bash
veribits cert-convert certificate.pem --format DER
veribits ssl-convert -in cert.der -inform DER -outform PEM -out cert.pem
```

#### CSR Validator
Validate Certificate Signing Requests and extract information.
```bash
veribits csr-validate request.csr
```

#### SSL Chain Resolution
Resolve and validate SSL certificate chains.
```bash
veribits ssl-resolve-chain example.com
veribits ssl-resolve-chain certificate.pem --file
```

#### SSL Key Pair Verification
Verify that a certificate and private key match.
```bash
veribits ssl-verify-keypair certificate.pem privatekey.pem
```

### Email Security Tools

#### Comprehensive Email Verification
Full email validation including SPF, DKIM, DMARC, and MX records.
```bash
veribits email-verify user@example.com
```

#### SPF Record Analysis
Analyze SPF (Sender Policy Framework) records for a domain.
```bash
veribits email-spf example.com
```

#### DKIM Analysis
Validate DKIM (DomainKeys Identified Mail) configuration.
```bash
veribits email-dkim example.com
veribits email-dkim example.com --selector google
```

#### DMARC Analysis
Check DMARC (Domain-based Message Authentication) policy.
```bash
veribits email-dmarc example.com
```

#### MX Record Check
Verify mail server (MX) records for a domain.
```bash
veribits email-mx example.com
```

#### Disposable Email Check
Detect if an email address is from a disposable email provider.
```bash
veribits email-disposable temp@guerrillamail.com
```

#### Email Blacklist Check
Check if a domain is listed on email blacklists.
```bash
veribits email-blacklist example.com
```

#### Email Deliverability Score
Calculate comprehensive deliverability score for a domain.
```bash
veribits email-score example.com
```

### Network Tools

#### IP Calculator
Calculate subnet information from IP address or CIDR notation.
```bash
veribits ip-calc 192.168.1.0/24
veribits ip-calc 10.0.0.0/8
```

#### DNS Validator
Validate DNS records for a domain.
```bash
veribits dns-validate example.com
veribits dns-validate example.com --type MX
veribits dns-validate example.com --type TXT
```

#### Zone File Validator
Validate DNS zone files for syntax errors.
```bash
veribits zone-validate zone.db
```

#### RBL/DNSBL Check
Check if an IP address is listed in spam blacklists.
```bash
veribits rbl-check 8.8.8.8
```

#### Traceroute
Perform visual traceroute with geographic information.
```bash
veribits traceroute example.com
```

#### BGP AS Lookup
Lookup BGP Autonomous System information.
```bash
veribits bgp-lookup AS15169
```

### Developer Tools

#### JWT Decoder
Decode and validate JSON Web Tokens.
```bash
veribits jwt-decode eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
veribits jwt-decode <token> --secret mysecret --verify
```

#### Hash Generator
Generate cryptographic hashes using various algorithms.
```bash
veribits hash "hello world"
veribits hash "sensitive data" --algorithm sha512
```

#### Regex Tester
Test regular expressions against text.
```bash
veribits regex "\d+" "test 123 numbers"
veribits regex "^[a-z]+$" "lowercase" --flags i
```

#### URL Encoder/Decoder
Encode or decode URL strings.
```bash
veribits url-encode "hello world"
veribits url-encode "hello%20world" --decode
```

#### Base64 Encoder/Decoder
Encode or decode Base64 strings.
```bash
veribits base64 "hello world"
veribits base64 "aGVsbG8gd29ybGQ=" --decode
```

#### File Magic Detector
Detect file types by analyzing magic numbers and signatures.
```bash
veribits file-magic document.pdf
veribits file-magic image.jpg
```

### Blockchain & Crypto Tools

#### Cryptocurrency Address Validator
Validate Bitcoin and Ethereum addresses.
```bash
veribits crypto-validate 1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa
veribits crypto-validate 0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb --type ethereum
```

#### Transaction Verification
Verify blockchain transactions.
```bash
veribits verify-tx <transaction-hash>
veribits verify-tx <transaction-hash> --chain ethereum
```

### Breach Detection

#### Have I Been Pwned - Email
Check if an email has been involved in known data breaches.
```bash
veribits hibp-email user@example.com
```

#### Have I Been Pwned - Password
Check if a password has been compromised in known breaches.
```bash
veribits hibp-password mypassword123
```

### Cloud Security Tools

#### Cloud Storage Search
Search for publicly exposed cloud storage buckets.
```bash
veribits cloud-storage-search "company-name"
veribits cloud-storage-search "backup" --provider aws
```

#### Cloud Storage Security Scan
Analyze cloud storage bucket security configuration.
```bash
veribits cloud-storage-scan my-bucket
veribits cloud-storage-scan my-container --provider azure
```

#### Cloud Storage Bucket List
List accessible cloud storage buckets.
```bash
veribits cloud-storage-buckets
veribits cloud-storage-buckets --provider gcp
```

### File Analysis Tools

#### Steganography Detection
Detect hidden data in images using steganography analysis.
```bash
veribits steg-detect image.png
```

#### File Integrity Verification
Verify file integrity and authenticity using hashes and signatures.
```bash
veribits verify-file document.pdf
veribits verify-file file.zip --hash abc123...
veribits verify-file binary --signature signature.sig
```

#### Malware Scanner
Scan files for malware and suspicious patterns.
```bash
veribits malware-scan suspicious.exe
```

#### Archive Inspector
Inspect and analyze archive file contents.
```bash
veribits inspect-archive backup.zip
veribits inspect-archive data.tar.gz
```

### Utility Commands

#### Tool Search
Search available tools by keyword or category.
```bash
veribits tool-search "email"
veribits tool-search "ssl" --category security
```

#### Tool List
List all available tools organized by category.
```bash
veribits tool-list
veribits tool-list --category network
```

#### Health Check
Check API health and connectivity.
```bash
veribits health
```

#### WHOIS Lookup
Perform WHOIS domain lookups.
```bash
veribits whois example.com
```

### Interactive Console

Start an interactive console with command completion and history.

```bash
veribits console
```

Features:
- Command auto-completion
- Command history
- Persistent session
- Colored output
- Easy command chaining

Example console session:
```
veribits> ssl-check example.com
veribits> dns-validate example.com
veribits> email-verify admin@example.com
veribits> exit
```

## Authentication

Many commands work without authentication, but some require an API key for full functionality:

```bash
# Set API key via command line
veribits --api-key YOUR_API_KEY iam-analyze policy.json

# Or set as environment variable
export VERIBITS_API_KEY=your_api_key_here
veribits iam-analyze policy.json
```

Get your free API key at: https://www.veribits.com/register

## Command Aliases

For faster usage, you can use the short alias `vb`:

```bash
vb ssl-check example.com
vb hash "hello world"
vb console
```

## Examples

### Security Audit Workflow
```bash
# Check website security
veribits security-headers https://example.com
veribits ssl-check example.com
veribits dns-validate example.com

# Verify email configuration
veribits email-verify admin@example.com
veribits email-spf example.com
veribits email-dmarc example.com

# Check for breaches
veribits hibp-email admin@example.com
```

### Code Security Review
```bash
# Scan for secrets
veribits secrets-scan app.js
veribits secrets-scan config.yaml
veribits secrets-scan .env

# Analyze IAM policies
veribits iam-analyze policy.json

# Check database connections
veribits db-audit "postgresql://user:pass@host/db"
```

### Certificate Management
```bash
# Check certificate
veribits ssl-check api.example.com

# Validate CSR
veribits csr-validate request.csr

# Convert formats
veribits cert-convert cert.pem --format DER

# Verify key pair
veribits ssl-verify-keypair cert.pem key.pem
```

### Network Diagnostics
```bash
# IP analysis
veribits ip-calc 192.168.1.0/24

# DNS checks
veribits dns-validate example.com --type A
veribits dns-validate example.com --type MX

# Traceroute
veribits traceroute example.com

# RBL check
veribits rbl-check 8.8.8.8
```

## Output Formats

All commands provide human-readable output with:
- Color-coded results
- Clear success/failure indicators
- Detailed explanations
- Actionable recommendations

## Platform Support

- Linux (all distributions)
- macOS
- Windows
- Docker/Containers
- CI/CD Pipelines

## Python Version Requirements

- Python 3.8+
- Python 3.9+
- Python 3.10+
- Python 3.11+

## Contributing

We welcome contributions! Please see our contributing guidelines at:
https://github.com/afterdarksystems/veribits-cli

## Support

- Documentation: https://www.veribits.com/cli.php
- API Docs: https://www.veribits.com/api-docs
- Support: support@afterdarksys.com
- Issues: https://github.com/afterdarksystems/veribits-cli/issues

## License

MIT License - Copyright (c) 2025 After Dark Systems, LLC

See LICENSE file for details.

## Changelog

### Version 3.0.0
- Added all new security tools from Node.js CLI
- Enhanced IAM policy analyzer
- Added secrets scanner
- Added database connection auditor
- Added hash validator
- Added comprehensive email security tools
- Added cloud storage security tools
- Added file analysis tools
- Improved interactive console mode
- Better error handling and reporting
- Performance improvements

### Version 2.0.0
- Initial stable release
- Core security tools
- SSL/TLS tools
- Network diagnostics
- Developer utilities
- Interactive console mode

## About VeriBits

VeriBits is a comprehensive security and developer tools platform created by After Dark Systems, LLC. Our mission is to provide professional-grade security tools that are accessible, reliable, and easy to use.

Visit us at: https://www.veribits.com
