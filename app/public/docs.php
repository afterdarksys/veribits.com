<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
    <style>
        .docs-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }
        .docs-nav {
            position: sticky;
            top: 80px;
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }
        .docs-nav h3 {
            margin-top: 0;
            color: var(--primary-color);
            font-size: 1.1rem;
        }
        .docs-nav ul {
            list-style: none;
            padding: 0;
        }
        .docs-nav li {
            margin: 0.5rem 0;
        }
        .docs-nav a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s;
        }
        .docs-nav a:hover {
            color: var(--primary-color);
        }
        .docs-section {
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
        }
        .docs-section:last-child {
            border-bottom: none;
        }
        .docs-section h2 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .docs-section h3 {
            color: var(--accent-color);
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        .code-block {
            background: var(--darker-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 1.5rem;
            margin: 1rem 0;
            overflow-x: auto;
        }
        .code-block code {
            color: var(--accent-color);
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        .feature-item {
            background: var(--card-bg);
            padding: 1rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }
        .feature-item strong {
            color: var(--primary-color);
        }
        .pricing-table {
            margin: 2rem 0;
            border-collapse: collapse;
            width: 100%;
        }
        .pricing-table th,
        .pricing-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .pricing-table th {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
        }
        .pricing-table tr:hover {
            background: rgba(59, 130, 246, 0.05);
        }
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
            border-left: 4px solid;
        }
        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            border-left-color: var(--primary-color);
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border-left-color: #22c55e;
        }
        .alert-warning {
            background: rgba(251, 191, 36, 0.1);
            border-left-color: #fbbf24;
        }
    </style>

    <!-- DNS Science Analytics -->
    <script src="https://www.dnsscience.io/static/js/analytics_track.js"
            data-token="dsa_live_d51afb46c704fe2594c15ed82717cb7703c8ab5c7185e481"
            data-endpoint="https://www.dnsscience.io/api/analytics/track"
            async></script>
</head>
<body>
    <nav>
        <div class="container" style="display: flex; align-items: center; gap: 1rem; padding: 0.75rem 1rem;">
            <a href="/" class="logo" style="margin-right: 1rem; flex-shrink: 0;">VeriBits</a>
            <ul style="display: flex; list-style: none; gap: 1.25rem; margin: 0; margin-left: auto; align-items: center; flex-shrink: 0;">
                <li><a href="/tools.php">Tools</a></li>
                <li><a href="/cli.php">CLI</a></li>
                <li><a href="/docs.php" class="active">Docs</a></li>
                <li><a href="/pricing.php">Pricing</a></li>
                <li><a href="/about.php">About</a></li>
                <li data-auth-item="true"><a href="/login.php">Login</a></li>
                <li data-auth-item="true"><a href="/signup.php" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <div class="docs-container">
        <h1 style="margin-bottom: 2rem;">📚 VeriBits Documentation</h1>

        <div class="docs-nav">
            <h3>Table of Contents</h3>
            <ul>
                <li><a href="#why">Why VeriBits?</a></li>
                <li><a href="#problem">What Problem Does It Solve?</a></li>
                <li><a href="#all-tools">All Tools (30+)</a></li>
                <li><a href="#cli-usage">How to Use the CLI</a></li>
                <li><a href="#common-operations">Most Common CLI Operations</a></li>
                <li><a href="#use-cases">Interview Use Cases</a></li>
                <li><a href="#licensing">How Licensing Works</a></li>
                <li><a href="#obtain-license">How to Obtain a License</a></li>
                <li><a href="#add-license">Adding Your License to the CLI</a></li>
                <li><a href="#api-endpoints">API Endpoints</a></li>
                <li><a href="#terraform">Terraform Provider</a></li>
                <li><a href="#ansible">Ansible Module</a></li>
            </ul>
        </div>

        <!-- Why VeriBits -->
        <section id="why" class="docs-section">
            <h2>🎯 Why We Made VeriBits</h2>
            <p>VeriBits was born from a simple observation: <strong>security tools are either too complex for developers or too simplistic for security professionals</strong>.</p>

            <p>We created VeriBits to bridge this gap by providing:</p>
            <div class="feature-list">
                <div class="feature-item">
                    <strong>🔐 Enterprise-Grade Security</strong>
                    <p>Professional-level tools without the enterprise complexity</p>
                </div>
                <div class="feature-item">
                    <strong>⚡ Developer-Friendly</strong>
                    <p>Simple APIs and CLI tools that integrate seamlessly into workflows</p>
                </div>
                <div class="feature-item">
                    <strong>🚀 Speed & Reliability</strong>
                    <p>Instant results with 99.9% uptime SLA</p>
                </div>
                <div class="feature-item">
                    <strong>🔄 Automation-Ready</strong>
                    <p>Built for CI/CD, IaC, and automated security workflows</p>
                </div>
            </div>

            <p>After working with dozens of security tools across multiple organizations, we realized that most tools fall into two categories:</p>
            <ul>
                <li><strong>Too Complex:</strong> Enterprise solutions with steep learning curves, requiring dedicated security teams</li>
                <li><strong>Too Limited:</strong> Simple tools that lack depth and can't scale to production needs</li>
            </ul>

            <p>VeriBits fills the gap in the middle—<strong>powerful enough for production, simple enough for any developer to use</strong>.</p>
        </section>

        <!-- What Problem It Solves -->
        <section id="problem" class="docs-section">
            <h2>💡 What Problem Does It Solve?</h2>

            <h3>The Security-Speed Paradox</h3>
            <p>Modern development moves fast, but security often slows things down. VeriBits solves this by making security checks:</p>
            <ul>
                <li><strong>Instant:</strong> Results in milliseconds, not minutes</li>
                <li><strong>Automated:</strong> Integrate into CI/CD pipelines</li>
                <li><strong>Comprehensive:</strong> 38+ tools in one platform</li>
                <li><strong>Accessible:</strong> Web UI, CLI, API, and IaC integrations</li>
            </ul>

            <h3>Specific Problems We Solve</h3>

            <div class="feature-item" style="margin: 1rem 0;">
                <strong>❌ Problem: Secrets in Code</strong>
                <p><strong>✅ Solution:</strong> Automated secrets scanning in CI/CD catches API keys, tokens, and passwords before they reach production</p>
                <div class="code-block"><code>vb secrets-scan app.js
# Finds: AWS keys, GitHub tokens, passwords, etc.</code></div>
            </div>

            <div class="feature-item" style="margin: 1rem 0;">
                <strong>❌ Problem: Overly Permissive IAM Policies</strong>
                <p><strong>✅ Solution:</strong> IAM policy analyzer identifies security risks before deployment</p>
                <div class="code-block"><code>vb iam-analyze policy.json
# Risk Score: 85/100 (HIGH)
# Finding: Allows s3:* on all resources</code></div>
            </div>

            <div class="feature-item" style="margin: 1rem 0;">
                <strong>❌ Problem: SSL Certificate Expiration</strong>
                <p><strong>✅ Solution:</strong> Automated SSL monitoring in Terraform/Ansible</p>
                <div class="code-block"><code>vb ssl-check example.com
# Expires in: 7 days ⚠️  (renew soon!)</code></div>
            </div>

            <div class="feature-item" style="margin: 1rem 0;">
                <strong>❌ Problem: Email Deliverability Issues</strong>
                <p><strong>✅ Solution:</strong> Comprehensive email verification (SPF, DMARC, DKIM, MX)</p>
                <div class="code-block"><code>vb email-score example.com
# Score: 42/100
# Missing: DMARC policy, SPF too permissive</code></div>
            </div>

            <div class="feature-item" style="margin: 1rem 0;">
                <strong>❌ Problem: Data Breaches</strong>
                <p><strong>✅ Solution:</strong> HIBP integration to check if emails/passwords are compromised</p>
                <div class="code-block"><code>vb hibp-email user@example.com
# Found in 3 breaches: LinkedIn (2012), Adobe (2013)...</code></div>
            </div>

            <div class="feature-item" style="margin: 1rem 0;">
                <strong>❌ Problem: Misconfigured Cloud Storage</strong>
                <p><strong>✅ Solution:</strong> S3/Azure/GCP bucket security scanning</p>
                <div class="code-block"><code>vb cloud-storage-scan my-bucket
# Risk: PUBLIC ACCESS ENABLED ⚠️
# Recommendation: Enable bucket encryption</code></div>
            </div>
        </section>

        <!-- All Tools -->
        <section id="all-tools" class="docs-section">
            <h2>🛠️ All Tools (30+ and Growing)</h2>
            <p>VeriBits provides a comprehensive suite of security, networking, and developer tools. All tools are available via Web UI, CLI, and API.</p>

            <h3>Developer Tools (8 tools)</h3>
            <div class="feature-list">
                <div class="feature-item">
                    <strong>Hash Generator/Validator</strong>
                    <p>Generate and validate MD5, SHA-1, SHA-256, SHA-512, bcrypt, Argon2 hashes with salt support</p>
                    <div class="code-block"><code># Node.js
vb hash-generate --algorithm sha256 --text "password"
vb hash-validate --hash abc123 --text "password"

# Python
veribits hash-generate sha256 "password"

# PHP
./veribits.php hash sha256 "password"</code></div>
                </div>
                <div class="feature-item">
                    <strong>JSON/YAML Validator</strong>
                    <p>Validate, format, minify, and convert between JSON and YAML</p>
                    <div class="code-block"><code>vb json-validate data.json
vb yaml-validate config.yaml
vb json-to-yaml data.json
vb yaml-to-json config.yaml</code></div>
                </div>
                <div class="feature-item">
                    <strong>Base64 Encoder/Decoder</strong>
                    <p>Encode/decode text and files, image preview, URL-safe encoding</p>
                    <div class="code-block"><code>vb base64-encode "secret text"
vb base64-decode "c2VjcmV0IHRleHQ="
vb base64-encode --file image.png</code></div>
                </div>
                <div class="feature-item">
                    <strong>JWT Debugger</strong>
                    <p>Decode, verify, and debug JSON Web Tokens</p>
                    <div class="code-block"><code>vb jwt-decode &lt;token&gt;
vb jwt-verify --token &lt;token&gt; --secret "key"</code></div>
                </div>
                <div class="feature-item">
                    <strong>Regex Tester</strong>
                    <p>Test regular expressions with match highlighting</p>
                    <div class="code-block"><code>vb regex "\d{3}-\d{3}-\d{4}" "555-123-4567"</code></div>
                </div>
                <div class="feature-item">
                    <strong>URL Encoder/Decoder</strong>
                    <p>URL encode and decode strings for web applications</p>
                    <div class="code-block"><code>vb url-encode "hello world"
vb url-decode "hello%20world"</code></div>
                </div>
                <div class="feature-item">
                    <strong>Code Signing Validator</strong>
                    <p>Verify digital signatures on executables and packages</p>
                    <div class="code-block"><code>vb code-sign-verify app.exe</code></div>
                </div>
                <div class="feature-item">
                    <strong>Crypto Validator</strong>
                    <p>Validate cryptocurrency addresses (BTC, ETH, etc.)</p>
                    <div class="code-block"><code>vb crypto-validate --address 1A1z... --type btc</code></div>
                </div>
            </div>

            <h3>Security Scanners (6 tools)</h3>
            <div class="feature-list">
                <div class="feature-item">
                    <strong>Docker Image Scanner (NEW)</strong>
                    <p>Dockerfile security analysis with 0-100 risk score, vulnerability detection</p>
                    <div class="code-block"><code># Node.js
vb docker-scan --file Dockerfile

# Python
veribits docker-scan Dockerfile

# Output: Security Score: 75/100
# Findings: Base image outdated, running as root</code></div>
                </div>
                <div class="feature-item">
                    <strong>Terraform Scanner (NEW)</strong>
                    <p>IaC security scanning for AWS/cloud misconfigurations</p>
                    <div class="code-block"><code>vb terraform-scan --file main.tf

# Checks: S3 public access, IAM wildcards,
# unencrypted resources, security groups</code></div>
                </div>
                <div class="feature-item">
                    <strong>Kubernetes Validator (NEW)</strong>
                    <p>K8s manifest security validation and best practices</p>
                    <div class="code-block"><code>vb k8s-validate deployment.yaml

# Checks: Privileged containers, resource limits,
# security contexts, network policies</code></div>
                </div>
                <div class="feature-item">
                    <strong>Secrets Scanner</strong>
                    <p>Detect hardcoded secrets, API keys, tokens, passwords in code</p>
                    <div class="code-block"><code>vb secrets-scan src/**/*.js
vb secrets-scan --directory ./app</code></div>
                </div>
                <div class="feature-item">
                    <strong>IAM Policy Analyzer</strong>
                    <p>Analyze AWS IAM policies for security risks and overly permissive access</p>
                    <div class="code-block"><code>vb iam-analyze policy.json

# Risk Score: 85/100
# Issues: Wildcard actions, missing conditions</code></div>
                </div>
                <div class="feature-item">
                    <strong>DB Connection Auditor</strong>
                    <p>Audit database connection strings for security issues</p>
                    <div class="code-block"><code>vb db-audit "postgres://user:pass@host/db"

# Checks: SSL enforcement, weak passwords,
# default credentials, exposed ports</code></div>
                </div>
            </div>

            <h3>Network & DNS Tools (9 tools)</h3>
            <div class="feature-list">
                <div class="feature-item">
                    <strong>PCAP Analyzer - AI-Powered (NEW)</strong>
                    <p>DNS troubleshooting, BGP/OSPF analysis, attack detection with OpenAI insights</p>
                    <div class="code-block"><code># Node.js
vb pcap-analyze --file capture.pcap

# Python
veribits pcap-analyze capture.pcap

# Features:
# - DNS query/response analysis
# - BGP route detection
# - TCP handshake analysis
# - AI-powered attack detection
# - Protocol distribution stats</code></div>
                </div>
                <div class="feature-item">
                    <strong>Firewall Editor (NEW)</strong>
                    <p>iptables/ebtables GUI editor with version control and CLI API</p>
                    <div class="code-block"><code># Node.js
vb firewall-editor --device server01 --version latest

# Python
veribits firewall-editor list
veribits firewall-editor export server01

# Features:
# - Edit iptables/ebtables rules via GUI
# - Version control for firewall configs
# - Rollback support
# - Rule validation before apply</code></div>
                </div>
                <div class="feature-item">
                    <strong>DNSSEC Validator (NEW)</strong>
                    <p>Validate DNSSEC chain of trust, DS records, RRSIG signatures</p>
                    <div class="code-block"><code>vb dnssec-validate example.com

# Checks: DNSKEY, DS records, RRSIG,
# chain of trust validation</code></div>
                </div>
                <div class="feature-item">
                    <strong>DNS Propagation Checker (NEW)</strong>
                    <p>Check DNS records across 16 global nameservers</p>
                    <div class="code-block"><code>vb dns-propagation --domain example.com --type A

# Servers: Google, Cloudflare, OpenDNS,
# Quad9, plus regional servers worldwide</code></div>
                </div>
                <div class="feature-item">
                    <strong>Reverse DNS Lookup (NEW)</strong>
                    <p>Bulk PTR record lookup with forward validation</p>
                    <div class="code-block"><code>vb reverse-dns 8.8.8.8
vb reverse-dns --file ips.txt

# Returns: PTR records with forward confirmation</code></div>
                </div>
                <div class="feature-item">
                    <strong>Visual Traceroute</strong>
                    <p>Interactive traceroute with geolocation and ASN data</p>
                    <div class="code-block"><code>vb traceroute google.com

# Shows: Hops, latency, ASN, geolocation</code></div>
                </div>
                <div class="feature-item">
                    <strong>BGP Intelligence</strong>
                    <p>BGP AS path analysis, route origin validation, prefix lookup</p>
                    <div class="code-block"><code>vb bgp-lookup AS15169
vb bgp-prefix 8.8.8.0/24</code></div>
                </div>
                <div class="feature-item">
                    <strong>RBL Checker</strong>
                    <p>Check IPs against 50+ spam blacklists</p>
                    <div class="code-block"><code>vb rbl-check 1.2.3.4</code></div>
                </div>
                <div class="feature-item">
                    <strong>DNS Zone Validator</strong>
                    <p>Validate DNS zone files for syntax and best practices</p>
                    <div class="code-block"><code>vb zone-validate zone.db</code></div>
                </div>
            </div>

            <h3>SSL/TLS & Certificates (5 tools)</h3>
            <div class="feature-list">
                <div class="feature-item">
                    <strong>SSL Certificate Checker</strong>
                    <p>Check certificate validity, expiration, chain of trust</p>
                    <div class="code-block"><code>vb ssl-check example.com</code></div>
                </div>
                <div class="feature-item">
                    <strong>Certificate Converter</strong>
                    <p>Convert between PEM, DER, PFX, P7B formats (OpenSSL-compatible)</p>
                    <div class="code-block"><code>vb ssl-convert -inform DER -outform PEM \
  -in cert.der -out cert.pem</code></div>
                </div>
                <div class="feature-item">
                    <strong>SSL Generator</strong>
                    <p>Generate self-signed certificates and CSRs</p>
                    <div class="code-block"><code>vb ssl-generate --domain example.com</code></div>
                </div>
                <div class="feature-item">
                    <strong>PGP Key Validator</strong>
                    <p>Validate PGP public keys and signatures</p>
                    <div class="code-block"><code>vb pgp-validate key.asc</code></div>
                </div>
                <div class="feature-item">
                    <strong>Security Headers Checker</strong>
                    <p>Analyze HTTP security headers (CSP, HSTS, X-Frame-Options, etc.)</p>
                    <div class="code-block"><code>vb security-headers https://example.com

# Checks: CSP, HSTS, X-Frame-Options,
# X-Content-Type-Options, Referrer-Policy</code></div>
                </div>
            </div>

            <h3>Email Verification (6 tools)</h3>
            <div class="feature-list">
                <div class="feature-item">
                    <strong>Email Validator</strong>
                    <p>Verify email addresses and domains</p>
                </div>
                <div class="feature-item">
                    <strong>SPF Checker</strong>
                    <p>Validate SPF records</p>
                </div>
                <div class="feature-item">
                    <strong>DMARC Checker</strong>
                    <p>Validate DMARC policies</p>
                </div>
                <div class="feature-item">
                    <strong>DKIM Checker</strong>
                    <p>Validate DKIM signatures</p>
                </div>
                <div class="feature-item">
                    <strong>MX Record Checker</strong>
                    <p>Check mail server records</p>
                </div>
                <div class="feature-item">
                    <strong>HIBP Integration</strong>
                    <p>Check if emails/passwords appear in data breaches</p>
                </div>
            </div>

            <h3>Utilities & File Analysis (4 tools)</h3>
            <div class="feature-list">
                <div class="feature-item">
                    <strong>IP Calculator</strong>
                    <p>Subnet calculator with CIDR notation support</p>
                </div>
                <div class="feature-item">
                    <strong>File Magic Analyzer</strong>
                    <p>Detect file types via magic bytes and metadata</p>
                </div>
                <div class="feature-item">
                    <strong>Steganography Detector</strong>
                    <p>Detect hidden data in images and files</p>
                </div>
                <div class="feature-item">
                    <strong>SMTP Relay Checker</strong>
                    <p>Test SMTP server relay configuration</p>
                </div>
            </div>

            <div class="alert alert-success">
                <strong>Total: 38 Tools</strong> - All available via Web UI, Node.js CLI, Python CLI, PHP CLI, and REST API
            </div>
        </section>

        <!-- CLI Usage -->
        <section id="cli-usage" class="docs-section">
            <h2>🖥️ How to Use the CLI</h2>

            <h3>Installation</h3>

            <p><strong>Node.js (npm):</strong></p>
            <div class="code-block"><code># Install globally
npm install -g veribits

# Verify installation
vb --version
# veribits 2.0.0</code></div>

            <p><strong>Python (pip):</strong></p>
            <div class="code-block"><code># Install globally
pip install veribits

# Verify installation
vb --version
# veribits 2.0.0</code></div>

            <p><strong>PHP (standalone):</strong></p>
            <div class="code-block"><code># Download
wget https://www.veribits.com/cli/veribits.php
chmod +x veribits.php

# Use directly
./veribits.php --version</code></div>

            <h3>Basic Usage</h3>
            <div class="code-block"><code># Get help
vb --help

# Run a command
vb &lt;command&gt; [arguments] [options]

# Examples
vb hash "hello world"
vb ssl-check google.com
vb email-verify user@example.com</code></div>

            <h3>Interactive Console Mode (NEW! 🎉)</h3>
            <p>Stay logged in and use TAB completion for faster workflows:</p>
            <div class="code-block"><code># Start console
vb console

╔════════════════════════════════════════════════════════════╗
║           VeriBits Interactive Console v2.0.0              ║
╚════════════════════════════════════════════════════════════╝

Type 'help' for available commands or 'exit' to quit

✓ Authenticated with API key

veribits&gt; email-spf google.com    # Press TAB for completion
veribits&gt; ssl-check github.com
veribits&gt; help                    # List all commands
veribits&gt; exit                    # Leave console</code></div>

            <div class="alert alert-success">
                <strong>💡 Pro Tip:</strong> Console mode is perfect for rapid testing and investigation. No need to type <code>vb</code> for every command, and you stay authenticated!
            </div>

            <h3>Authentication</h3>
            <div class="code-block"><code># Method 1: Environment variable (recommended)
export VERIBITS_API_KEY="your-api-key-here"
vb ssl-check example.com

# Method 2: Inline flag
vb --api-key your-key ssl-check example.com

# Method 3: Config file (coming soon)
# ~/.veribits/config</code></div>

            <h3>Available Commands (38+ total)</h3>
            <div class="code-block"><code># Security & IAM
vb iam-analyze policy.json
vb secrets-scan app.js
vb db-audit "postgresql://user:pass@host/db"
vb security-headers https://example.com

# Developer Tools
vb jwt-decode &lt;token&gt;
vb hash "text" --algorithm sha256
vb regex "\d+" "test 123"
vb url-encode "hello world"
vb base64 "secret data"

# Network Tools
vb dns-validate google.com
vb ip-calc 192.168.1.0/24
vb rbl-check 1.2.3.4
vb traceroute google.com
vb bgp-lookup AS15169

# Email Verification
vb email-verify user@example.com
vb email-spf example.com
vb email-dmarc example.com
vb email-dkim example.com
vb email-score example.com
vb hibp-email user@example.com

# SSL/TLS Tools
vb ssl-check example.com
vb ssl-convert -inform DER -outform PEM -in cert.der -out cert.pem
vb ssl-resolve-chain example.com
vb ssl-verify-keypair cert.pem key.pem
vb csr-validate request.csr

# Cloud Security
vb cloud-storage-scan my-bucket
vb cloud-storage-search "backup" --provider aws
vb malware-scan file.exe

# File Analysis
vb file-magic document.pdf
vb steg-detect image.png
vb inspect-archive backup.zip

# Utilities
vb tool-search "email"
vb tool-list
vb health
vb whois example.com

# Interactive Console
vb console</code></div>
        </section>

        <!-- Common Operations -->
        <section id="common-operations" class="docs-section">
            <h2>⭐ Most Common CLI Operations</h2>

            <h3>1. Pre-Deployment Security Scan</h3>
            <div class="code-block"><code># Scan for secrets before git commit
vb secrets-scan src/config.js

# Check IAM policy security
vb iam-analyze iam-policy.json

# Verify SSL certificate
vb ssl-check api.example.com</code></div>

            <h3>2. Email Domain Investigation</h3>
            <div class="code-block"><code># Complete email domain audit
vb email-spf example.com
vb email-dmarc example.com
vb email-dkim example.com --selector default
vb email-mx example.com
vb email-score example.com
vb whois example.com</code></div>

            <h3>3. SSL/TLS Certificate Management</h3>
            <div class="code-block"><code># Check certificate expiration
vb ssl-check example.com

# Convert certificate format (OpenSSL compatible)
vb ssl-convert -inform DER -outform PEM -in cert.der -out cert.pem

# Resolve certificate chain
vb ssl-resolve-chain example.com

# Verify certificate and key pair match
vb ssl-verify-keypair server.crt server.key</code></div>

            <h3>4. CI/CD Integration</h3>
            <div class="code-block"><code>#!/bin/bash
# .github/workflows/security-scan.sh

# Fail build if secrets found
if vb secrets-scan src/**/*.js | grep -q "Secrets Found: [1-9]"; then
  echo "❌ Secrets detected in code!"
  exit 1
fi

# Check IAM policies
vb iam-analyze terraform/iam-policy.json

echo "✅ Security checks passed"</code></div>

            <h3>5. Data Breach Checking</h3>
            <div class="code-block"><code># Check if email appears in breaches
vb hibp-email user@example.com

# Check if password is compromised (uses k-anonymity)
vb hibp-password "MyPassword123"</code></div>

            <h3>6. Network Investigation</h3>
            <div class="code-block"><code># IP subnet calculation
vb ip-calc 192.168.1.0/24

# Check if IP is blacklisted
vb rbl-check 1.2.3.4

# Trace route to host
vb traceroute google.com

# BGP AS lookup
vb bgp-lookup AS15169</code></div>

            <h3>7. Quick Development Tools</h3>
            <div class="code-block"><code># Generate hash
vb hash "secret" --algorithm sha256

# Test regex pattern
vb regex "\d{3}-\d{3}-\d{4}" "Call 555-123-4567"

# Decode JWT token
vb jwt-decode eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...

# URL encode/decode
vb url-encode "hello world"

# Base64 encode/decode
vb base64 "secret data"</code></div>
        </section>

        <!-- Interview Use Cases -->
        <section id="use-cases" class="docs-section">
            <h2>💼 Interview Use Cases - Show Off Your Skills</h2>
            <p>These real-world scenarios demonstrate how VeriBits solves complex problems that interviewers love to ask about.</p>

            <h3>Use Case 1: DNS Troubleshooting with PCAP Analysis</h3>
            <p><strong>Interview Question:</strong> "How would you troubleshoot DNS resolution failures in a production environment?"</p>

            <div class="alert alert-info">
                <strong>The VeriBits Answer:</strong> Use PCAP Analyzer with AI-powered insights
            </div>

            <div class="code-block"><code># Scenario: Customers reporting intermittent DNS failures
# Step 1: Capture network traffic
tcpdump -i eth0 -w dns-issue.pcap port 53

# Step 2: Analyze with VeriBits PCAP Analyzer
vb pcap-analyze --file dns-issue.pcap

# AI-Powered Output:
# ==================
# DNS Analysis:
# - 847 DNS queries detected
# - 156 queries with NXDOMAIN (18.4%)
# - 23 queries timing out (2.7%)
# - Top failing domain: api.example.com
#
# AI Insight: "High NXDOMAIN rate suggests DNS cache
# poisoning or misconfigured upstream resolver. The
# failed queries correlate with TTL expiration times,
# indicating a race condition in your DNS caching layer."
#
# Recommendation:
# 1. Check upstream resolver configuration
# 2. Increase negative caching TTL
# 3. Implement DNSSEC validation

# Step 3: Validate DNSSEC
vb dnssec-validate api.example.com

# Step 4: Check DNS propagation across global servers
vb dns-propagation --domain api.example.com --type A

# Result: Issue identified and resolved in minutes, not hours!</code></div>

            <h3>Use Case 2: Firewall Management Workflow</h3>
            <p><strong>Interview Question:</strong> "How do you manage firewall rules across multiple servers while maintaining version control?"</p>

            <div class="alert alert-info">
                <strong>The VeriBits Answer:</strong> Firewall Editor with built-in version control
            </div>

            <div class="code-block"><code># Scenario: Need to update firewall rules on 50 production servers

# Step 1: Export current firewall configuration
vb firewall-editor export server01 --output server01-fw.json

# Step 2: Edit rules via GUI (or programmatically)
vb firewall-editor --device server01 --version latest

# Step 3: Preview changes before applying
vb firewall-editor diff --device server01

# Output:
# + ACCEPT tcp -- 0.0.0.0/0 443 (HTTPS)
# - ACCEPT tcp -- 0.0.0.0/0 8080 (Old API)
# + REJECT tcp -- 192.168.1.0/24 22 (SSH restricted)

# Step 4: Apply changes with automatic rollback
vb firewall-editor apply --device server01 --rollback-timeout 5m

# Step 5: Version control snapshot
vb firewall-editor commit --device server01 --message "Add HTTPS, remove legacy API"

# Step 6: Rollback if needed
vb firewall-editor rollback --device server01 --version previous

# Benefits:
# - GUI editor eliminates iptables syntax errors
# - Automatic validation before apply
# - Built-in version control (no need for Git)
# - Rollback support prevents lockouts
# - CLI API for automation</code></div>

            <h3>Use Case 3: Security Scanning Pipeline (CI/CD)</h3>
            <p><strong>Interview Question:</strong> "How would you implement automated security scanning in your CI/CD pipeline?"</p>

            <div class="alert alert-info">
                <strong>The VeriBits Answer:</strong> Multi-layered security scanning with Docker, Terraform, K8s, and Secrets
            </div>

            <div class="code-block"><code># .github/workflows/security-pipeline.yml
name: Security Scan Pipeline
on: [push, pull_request]

jobs:
  security-scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Install VeriBits CLI
        run: npm install -g veribits

      - name: Scan for Secrets
        env:
          VERIBITS_API_KEY: ${{ secrets.VERIBITS_API_KEY }}
        run: |
          echo "🔍 Scanning for hardcoded secrets..."
          vb secrets-scan src/**/*.{js,ts,py,go}
          if [ $? -ne 0 ]; then
            echo "❌ Secrets found! Failing build."
            exit 1
          fi

      - name: Scan Dockerfile
        run: |
          echo "🐋 Scanning Dockerfile for vulnerabilities..."
          vb docker-scan --file Dockerfile --min-score 70
          # Fails if security score < 70/100

      - name: Scan Terraform
        run: |
          echo "🏗️  Scanning Terraform for misconfigurations..."
          vb terraform-scan --directory ./infrastructure
          # Checks: Public S3 buckets, overpermissive IAM,
          # unencrypted resources, open security groups

      - name: Validate Kubernetes Manifests
        run: |
          echo "☸️  Validating Kubernetes security..."
          vb k8s-validate k8s/*.yaml
          # Checks: Privileged containers, missing resource limits,
          # security contexts, network policies

      - name: Analyze IAM Policies
        run: |
          echo "🔐 Analyzing IAM policies..."
          vb iam-analyze terraform/iam-policy.json --max-risk 75
          # Fails if risk score > 75/100

      - name: Check SSL Certificates
        run: |
          echo "🔒 Checking SSL certificate expiration..."
          vb ssl-check api.example.com
          # Warns if cert expires in < 30 days

# Result: Comprehensive security scanning in < 2 minutes
# Catches issues BEFORE they reach production!</code></div>

            <h3>Use Case 4: Multi-Cloud Security Audit</h3>
            <p><strong>Interview Question:</strong> "How do you audit security across AWS, Azure, and GCP environments?"</p>

            <div class="code-block"><code># Scenario: Quarterly security audit across all cloud providers

# AWS Security Audit
vb iam-analyze aws-policies/*.json
vb cloud-storage-scan --provider aws --bucket prod-data
vb secrets-scan --directory ./aws-lambdas

# Azure Security Audit
vb cloud-storage-scan --provider azure --container backups
vb security-headers https://app.azure.example.com

# GCP Security Audit
vb cloud-storage-scan --provider gcp --bucket prod-gcs
vb k8s-validate gke-manifests/*.yaml

# Generate consolidated report
vb audit-report --output security-audit-Q1-2025.pdf

# Email to stakeholders
vb email-verify security-team@example.com</code></div>

            <h3>Use Case 5: Incident Response - Breach Detection</h3>
            <p><strong>Interview Question:</strong> "User credentials may be compromised. How do you investigate?"</p>

            <div class="code-block"><code># Scenario: Suspicious login activity detected

# Step 1: Check if emails appear in known breaches
vb hibp-email user1@example.com
vb hibp-email user2@example.com
vb hibp-email user3@example.com

# Output: user1@example.com found in:
# - LinkedIn (2012) - 167M accounts
# - Adobe (2013) - 153M accounts
# - Collection #1 (2019) - 773M accounts

# Step 2: Check password exposure (k-anonymity - secure)
vb hibp-password "CommonPassword123"
# Found 47,205 times in breaches

# Step 3: Force password reset for affected users
# (Your application logic here)

# Step 4: Scan codebase for exposed credentials
vb secrets-scan --directory ./backend

# Step 5: Audit database connection security
vb db-audit "postgres://user:pass@db.example.com/prod"

# Step 6: Check authentication endpoints security
vb security-headers https://auth.example.com

# Result: Comprehensive breach response in minutes!</code></div>

            <h3>Use Case 6: Network Diagnostics Deep Dive</h3>
            <p><strong>Interview Question:</strong> "Application is slow. How do you diagnose network issues?"</p>

            <div class="code-block"><code># Scenario: Users reporting slow API responses

# Step 1: Visual traceroute to API server
vb traceroute api.example.com
# Shows: Route, latency per hop, ASN, geolocation

# Step 2: Check if server IP is blacklisted
vb rbl-check 203.0.113.42
# Checks 50+ spam blacklists

# Step 3: BGP route analysis
vb bgp-lookup AS64496
vb bgp-prefix 203.0.113.0/24

# Step 4: DNS propagation check
vb dns-propagation --domain api.example.com --type A
# Checks 16 global DNS servers

# Step 5: Reverse DNS validation
vb reverse-dns 203.0.113.42
# Verifies PTR record matches forward lookup

# Step 6: PCAP analysis for deeper investigation
vb pcap-analyze --file api-traffic.pcap
# AI detects: "High TCP retransmission rate (12%)
# suggests network congestion or packet loss"

# Result: Network bottleneck identified quickly!</code></div>

            <div class="alert alert-success">
                <strong>💡 Interview Pro Tip:</strong> These use cases demonstrate:
                <ul style="margin-top: 0.5rem;">
                    <li>Problem-solving methodology</li>
                    <li>Tool expertise and automation skills</li>
                    <li>Security-first mindset</li>
                    <li>DevOps best practices</li>
                    <li>Real-world incident response experience</li>
                </ul>
                Mentioning VeriBits shows you stay current with modern security tools!
            </div>
        </section>

        <!-- Licensing -->
        <section id="licensing" class="docs-section">
            <h2>📜 How Licensing Works</h2>

            <h3>Tiers</h3>
            <table class="pricing-table">
                <thead>
                    <tr>
                        <th>Tier</th>
                        <th>Scans/Month</th>
                        <th>File Size Limit</th>
                        <th>Rate Limits</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Free</strong></td>
                        <td>5 total (lifetime)</td>
                        <td>50 MB</td>
                        <td>5 requests/hour</td>
                        <td>$0</td>
                    </tr>
                    <tr>
                        <td><strong>Starter</strong></td>
                        <td>1,000</td>
                        <td>100 MB</td>
                        <td>100 requests/hour</td>
                        <td>$29/month</td>
                    </tr>
                    <tr>
                        <td><strong>Professional</strong></td>
                        <td>10,000</td>
                        <td>500 MB</td>
                        <td>1,000 requests/hour</td>
                        <td>$99/month</td>
                    </tr>
                    <tr>
                        <td><strong>Enterprise</strong></td>
                        <td>Unlimited</td>
                        <td>10 GB</td>
                        <td>Custom</td>
                        <td>$499/month</td>
                    </tr>
                </tbody>
            </table>

            <h3>What's Included</h3>
            <ul>
                <li><strong>All Tiers:</strong> Access to all 38+ CLI commands and 100+ API endpoints</li>
                <li><strong>Paid Tiers:</strong> Priority support, SLA guarantees, audit logs</li>
                <li><strong>Enterprise:</strong> Dedicated support, custom integrations, on-premise deployment options</li>
            </ul>

            <div class="alert alert-info">
                <strong>📌 Note:</strong> The free tier is perfect for testing and personal projects. For production use, we recommend at least the Starter tier.
            </div>

            <h3>License Types</h3>
            <ul>
                <li><strong>API Key:</strong> Standard authentication for API and CLI</li>
                <li><strong>OAuth Token:</strong> For third-party integrations</li>
                <li><strong>Enterprise License:</strong> Custom authentication with SSO support</li>
            </ul>
        </section>

        <!-- Obtain License -->
        <section id="obtain-license" class="docs-section">
            <h2>🎫 How to Obtain a License</h2>

            <h3>Step 1: Create an Account</h3>
            <ol>
                <li>Visit <a href="/signup.php">veribits.com/signup.php</a></li>
                <li>Enter your email and create a password</li>
                <li>Verify your email address</li>
                <li>You'll automatically receive 5 free scans</li>
            </ol>

            <h3>Step 2: Choose Your Plan</h3>
            <ol>
                <li>Log in to your <a href="/dashboard.php">dashboard</a></li>
                <li>Click "Upgrade Plan" or visit <a href="/pricing.php">Pricing</a></li>
                <li>Select the plan that fits your needs</li>
                <li>Enter payment information (credit card or PayPal)</li>
            </ol>

            <h3>Step 3: Get Your API Key</h3>
            <ol>
                <li>After signup/upgrade, go to <a href="/settings.php">Settings</a></li>
                <li>Navigate to the "API Keys" section</li>
                <li>Click "Generate New API Key"</li>
                <li>Copy your key (it will only be shown once!)</li>
                <li>Store it securely (e.g., password manager)</li>
            </ol>

            <div class="alert alert-warning">
                <strong>⚠️ Security Warning:</strong> Never commit API keys to version control or share them publicly. Treat them like passwords!
            </div>

            <h3>Key Regeneration</h3>
            <p>If your key is compromised:</p>
            <ol>
                <li>Go to Settings → API Keys</li>
                <li>Click "Revoke" on the compromised key</li>
                <li>Generate a new key immediately</li>
                <li>Update all systems using the old key</li>
            </ol>
        </section>

        <!-- Add License to CLI -->
        <section id="add-license" class="docs-section">
            <h2>🔑 Adding Your License to the CLI</h2>

            <h3>Method 1: Environment Variable (Recommended)</h3>
            <p>This is the most secure and convenient method for local development:</p>

            <p><strong>Linux/macOS:</strong></p>
            <div class="code-block"><code># Add to ~/.bashrc or ~/.zshrc for permanent setup
export VERIBITS_API_KEY="vb_1234567890abcdef..."

# Or temporary for current session
export VERIBITS_API_KEY="vb_1234567890abcdef..."

# Verify it's set
echo $VERIBITS_API_KEY

# Use CLI (no --api-key needed)
vb ssl-check google.com</code></div>

            <p><strong>Windows (PowerShell):</strong></p>
            <div class="code-block"><code># Temporary (current session)
$env:VERIBITS_API_KEY="vb_1234567890abcdef..."

# Permanent (all sessions)
[System.Environment]::SetEnvironmentVariable('VERIBITS_API_KEY', 'vb_1234567890abcdef...', 'User')

# Verify
echo $env:VERIBITS_API_KEY</code></div>

            <p><strong>Windows (CMD):</strong></p>
            <div class="code-block"><code># Temporary
set VERIBITS_API_KEY=vb_1234567890abcdef...

# Permanent
setx VERIBITS_API_KEY "vb_1234567890abcdef..."</code></div>

            <h3>Method 2: Command-Line Flag</h3>
            <p>Pass the API key directly with each command:</p>
            <div class="code-block"><code>vb --api-key vb_1234567890abcdef... ssl-check google.com

# Works with all commands
vb --api-key vb_1234567890abcdef... email-verify user@example.com</code></div>

            <h3>Method 3: Console Mode</h3>
            <p>Use the interactive console to stay authenticated:</p>
            <div class="code-block"><code># Set environment variable first
export VERIBITS_API_KEY="vb_1234567890abcdef..."

# Start console (automatically uses env var)
vb console

# Or pass key inline
vb --api-key vb_1234567890abcdef... console

# Now you're authenticated for all commands
veribits&gt; ssl-check google.com
veribits&gt; email-verify user@example.com</code></div>

            <h3>Method 4: CI/CD Environment</h3>
            <p>For GitHub Actions, GitLab CI, Jenkins, etc.:</p>

            <p><strong>GitHub Actions:</strong></p>
            <div class="code-block"><code># .github/workflows/security-scan.yml
name: Security Scan
on: [push]

jobs:
  scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Install VeriBits CLI
        run: npm install -g veribits

      - name: Scan for secrets
        env:
          VERIBITS_API_KEY: ${{ secrets.VERIBITS_API_KEY }}
        run: vb secrets-scan src/**/*.js</code></div>

            <p><strong>GitLab CI:</strong></p>
            <div class="code-block"><code># .gitlab-ci.yml
security-scan:
  stage: test
  before_script:
    - npm install -g veribits
  script:
    - vb secrets-scan src/**/*.js
  variables:
    VERIBITS_API_KEY: $VERIBITS_API_KEY  # Set in GitLab CI/CD variables</code></div>

            <div class="alert alert-success">
                <strong>✅ Best Practice:</strong> Always use environment variables or secret management systems in CI/CD. Never hardcode API keys in your code or config files!
            </div>

            <h3>Verifying Authentication</h3>
            <div class="code-block"><code># Check API health with authentication
vb health

# Expected output:
# ✓ Authenticated with API key
# Status: HEALTHY
# Plan: Professional
# Scans Remaining: 9,847/10,000</code></div>
        </section>

        <!-- API Endpoints -->
        <section id="api-endpoints" class="docs-section">
            <h2>🔌 API Endpoints</h2>
            <p>All VeriBits tools are accessible via REST API. Here are examples for the new tools:</p>

            <h3>Base URL</h3>
            <div class="code-block"><code>https://www.veribits.com/api/v1</code></div>

            <h3>Authentication</h3>
            <div class="code-block"><code># Header-based authentication
curl -H "Authorization: Bearer vb_your_api_key" \
  https://www.veribits.com/api/v1/tools/hash-generate

# Query parameter (not recommended for production)
curl "https://www.veribits.com/api/v1/tools/hash-generate?api_key=vb_your_api_key"</code></div>

            <h3>Developer Tools API</h3>
            <div class="code-block"><code># Hash Generator
POST /api/v1/developer-tools/hash-generate
Content-Type: application/json
{
  "algorithm": "sha256",
  "text": "password",
  "salt": "optional_salt"
}

# JSON Validator
POST /api/v1/developer-tools/json-validate
Content-Type: application/json
{
  "json": "{\"key\": \"value\"}",
  "format": true
}

# Base64 Encoder
POST /api/v1/developer-tools/base64-encode
Content-Type: application/json
{
  "text": "secret data",
  "url_safe": false
}

# JWT Decoder
POST /api/v1/developer-tools/jwt-decode
Content-Type: application/json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}</code></div>

            <h3>Security Scanners API</h3>
            <div class="code-block"><code># Docker Image Scanner
POST /api/v1/security/docker-scan
Content-Type: application/json
{
  "dockerfile": "FROM ubuntu:20.04\nRUN apt-get update...",
  "filename": "Dockerfile"
}

# Response:
{
  "score": 75,
  "issues": [
    {
      "severity": "high",
      "message": "Running as root user",
      "line": 5
    }
  ]
}

# Terraform Scanner
POST /api/v1/security/terraform-scan
Content-Type: application/json
{
  "terraform_code": "resource \"aws_s3_bucket\" ...",
  "filename": "main.tf"
}

# Kubernetes Validator
POST /api/v1/security/k8s-validate
Content-Type: application/json
{
  "manifest": "apiVersion: v1\nkind: Pod...",
  "filename": "deployment.yaml"
}

# Secrets Scanner
POST /api/v1/security/secrets-scan
Content-Type: application/json
{
  "code": "const API_KEY = 'sk-1234567890';",
  "filename": "config.js"
}

# IAM Policy Analyzer
POST /api/v1/security/iam-analyze
Content-Type: application/json
{
  "policy": {
    "Version": "2012-10-17",
    "Statement": [...]
  }
}

# DB Connection Auditor
POST /api/v1/security/db-audit
Content-Type: application/json
{
  "connection_string": "postgresql://user:pass@host/db"
}</code></div>

            <h3>Network Tools API</h3>
            <div class="code-block"><code># PCAP Analyzer (file upload)
POST /api/v1/network-tools/pcap-analyze
Content-Type: multipart/form-data
{
  "file": &lt;pcap_file&gt;,
  "analyze_dns": true,
  "analyze_bgp": true,
  "ai_insights": true
}

# Response:
{
  "packets": 1247,
  "protocols": {
    "dns": 847,
    "tcp": 356,
    "udp": 44
  },
  "dns_analysis": {
    "queries": 847,
    "nxdomain": 156,
    "timeouts": 23
  },
  "ai_insight": "High NXDOMAIN rate suggests DNS cache poisoning..."
}

# DNSSEC Validator
GET /api/v1/network-tools/dnssec-validate?domain=example.com

# DNS Propagation Checker
GET /api/v1/network-tools/dns-propagation?domain=example.com&type=A

# Reverse DNS Lookup
GET /api/v1/network-tools/reverse-dns?ip=8.8.8.8

# Firewall Editor - List Rules
GET /api/v1/network-tools/firewall-editor/rules?device=server01

# Firewall Editor - Apply Rules
POST /api/v1/network-tools/firewall-editor/apply
Content-Type: application/json
{
  "device": "server01",
  "rules": [
    {
      "action": "ACCEPT",
      "protocol": "tcp",
      "port": 443
    }
  ]
}</code></div>

            <h3>Rate Limiting</h3>
            <div class="code-block"><code># Rate limit headers in response
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 847
X-RateLimit-Reset: 1640995200

# Rate limit exceeded response
HTTP/1.1 429 Too Many Requests
{
  "error": "Rate limit exceeded",
  "retry_after": 3600
}</code></div>

            <h3>Error Handling</h3>
            <div class="code-block"><code># Standard error response
{
  "error": "Invalid API key",
  "error_code": "AUTH_INVALID",
  "message": "The provided API key is invalid or expired",
  "docs_url": "https://www.veribits.com/docs#authentication"
}

# Validation error
{
  "error": "Validation failed",
  "error_code": "VALIDATION_ERROR",
  "errors": [
    {
      "field": "algorithm",
      "message": "Must be one of: md5, sha1, sha256, sha512"
    }
  ]
}</code></div>

            <div class="alert alert-info">
                <strong>📌 Full API Documentation:</strong> Visit <a href="https://www.veribits.com/api/docs">veribits.com/api/docs</a> for complete API reference with interactive examples.
            </div>
        </section>

        <!-- Terraform Provider -->
        <section id="terraform" class="docs-section">
            <h2>🏗️ Terraform Provider</h2>

            <h3>Installation</h3>
            <div class="code-block"><code># terraform.tf
terraform {
  required_providers {
    veribits = {
      source  = "afterdarksystems/veribits"
      version = "~> 2.0"
    }
  }
}

provider "veribits" {
  api_key = var.veribits_api_key  # From environment or Terraform variables
  api_url = "https://www.veribits.com/api/v1"
}</code></div>

            <h3>Configuration</h3>
            <div class="code-block"><code># variables.tf
variable "veribits_api_key" {
  description = "VeriBits API Key"
  type        = string
  sensitive   = true
}

# Set via environment variable
# export TF_VAR_veribits_api_key="vb_1234567890abcdef..."

# Or via terraform.tfvars (DO NOT commit this file!)
# veribits_api_key = "vb_1234567890abcdef..."</code></div>

            <h3>Example Usage</h3>
            <div class="code-block"><code># Validate IAM policy before deployment
resource "veribits_iam_policy_validation" "s3_policy" {
  policy_document = aws_iam_policy.s3_access.policy
  policy_name     = "s3-bucket-access"
  max_risk_level  = "medium"  # Fail if risk is high or critical
}

# Check SSL certificate before creating Route53 record
data "veribits_ssl_check" "api_cert" {
  host = "api.example.com"
  port = 443
}

output "cert_expires_in_days" {
  value = data.veribits_ssl_check.api_cert.expires_in_days
}

# Scan for secrets before deploying
resource "veribits_secrets_scan" "k8s_manifests" {
  source_file     = "${path.module}/k8s/deployment.yaml"
  fail_on_secrets = true
}

# Verify email domain before SES setup
data "veribits_email_spf" "domain" {
  domain = "example.com"
}

resource "aws_ses_domain_identity" "example" {
  # Only create if SPF is valid
  count  = data.veribits_email_spf.domain.valid ? 1 : 0
  domain = "example.com"
}</code></div>

            <h3>Available Resources & Data Sources</h3>
            <ul>
                <li><code>veribits_iam_policy_validation</code> - Validate IAM policies</li>
                <li><code>veribits_secrets_scan</code> - Scan for exposed secrets</li>
                <li><code>veribits_ssl_check</code> - Check SSL certificates</li>
                <li><code>veribits_email_spf</code> - Validate SPF records</li>
                <li><code>veribits_dns_validate</code> - Validate DNS records</li>
                <li><code>veribits_cloud_storage_scan</code> - Security scan buckets</li>
            </ul>

            <div class="alert alert-info">
                <strong>📌 Coming Soon:</strong> The Terraform provider is currently in development. Expected release: Q1 2026. <a href="/signup.php">Sign up</a> to be notified when it's available.
            </div>
        </section>

        <!-- Ansible Module -->
        <section id="ansible" class="docs-section">
            <h2>⚙️ Ansible Module</h2>

            <h3>Installation</h3>
            <div class="code-block"><code># Install the VeriBits collection from Ansible Galaxy
ansible-galaxy collection install afterdarksystems.veribits

# Verify installation
ansible-galaxy collection list | grep veribits</code></div>

            <h3>Configuration</h3>
            <div class="code-block"><code># group_vars/all.yml or inventory
veribits_api_key: "{{ lookup('env', 'VERIBITS_API_KEY') }}"

# Or use Ansible Vault for security
ansible-vault encrypt_string 'vb_1234567890abcdef...' --name 'veribits_api_key'</code></div>

            <h3>Example Playbook</h3>
            <div class="code-block"><code>---
- name: VeriBits Security Audit
  hosts: localhost
  collections:
    - afterdarksystems.veribits

  tasks:
    # Scan for secrets before deployment
    - name: Scan deployment files for secrets
      secrets_scan:
        source_file: /path/to/deployment.yaml
        api_key: "{{ veribits_api_key }}"
      register: secrets_result
      failed_when: secrets_result.secrets_found > 0

    # Validate IAM policies
    - name: Check IAM policy security
      iam_policy_validate:
        policy_file: /path/to/policy.json
        api_key: "{{ veribits_api_key }}"
        max_risk_level: medium
      register: iam_result

    - name: Display IAM findings
      debug:
        msg: "IAM Risk Score: {{ iam_result.risk_score }}/100"

    # Check SSL certificates for multiple domains
    - name: Verify SSL certificates
      ssl_check:
        host: "{{ item }}"
        port: 443
        api_key: "{{ veribits_api_key }}"
      loop:
        - api.example.com
        - www.example.com
        - admin.example.com
      register: ssl_results

    - name: Alert on expiring certificates
      debug:
        msg: "⚠️  Certificate for {{ item.host }} expires in {{ item.expires_in_days }} days"
      loop: "{{ ssl_results.results }}"
      when: item.expires_in_days < 30

    # Email domain verification
    - name: Verify email configuration
      email_verify:
        domain: example.com
        checks:
          - spf
          - dmarc
          - mx
        api_key: "{{ veribits_api_key }}"
      register: email_result

    - name: Fail if email score is low
      fail:
        msg: "Email deliverability score too low: {{ email_result.score }}/100"
      when: email_result.score < 70

    # Cloud storage security
    - name: Scan S3 buckets
      cloud_storage_scan:
        bucket: "{{ item }}"
        provider: aws
        api_key: "{{ veribits_api_key }}"
      loop: "{{ s3_buckets }}"
      register: bucket_results

    - name: Fail if bucket is public
      fail:
        msg: "Bucket {{ item.bucket }} has public access enabled!"
      loop: "{{ bucket_results.results }}"
      when: item.public_access == true</code></div>

            <h3>Available Modules</h3>
            <ul>
                <li><code>iam_policy_validate</code> - Validate AWS IAM policies</li>
                <li><code>secrets_scan</code> - Scan for exposed secrets</li>
                <li><code>ssl_check</code> - Check SSL certificate status</li>
                <li><code>email_verify</code> - Comprehensive email validation</li>
                <li><code>dns_validate</code> - Validate DNS records</li>
                <li><code>cloud_storage_scan</code> - Scan cloud storage security</li>
                <li><code>security_headers</code> - Check HTTP security headers</li>
            </ul>

            <h3>Integration with CI/CD</h3>
            <div class="code-block"><code># .gitlab-ci.yml
security-check:
  stage: test
  image: ansible:latest
  script:
    - ansible-galaxy collection install afterdarksystems.veribits
    - ansible-playbook security-audit.yml
  variables:
    VERIBITS_API_KEY: $VERIBITS_API_KEY</code></div>

            <div class="alert alert-info">
                <strong>📌 Coming Soon:</strong> The Ansible collection is currently in development. Expected release: Q1 2026. <a href="/signup.php">Sign up</a> to be notified when it's available.
            </div>
        </section>

        <!-- Footer -->
        <div style="text-align: center; margin: 3rem 0; padding: 2rem 0; border-top: 1px solid var(--border-color);">
            <p style="color: var(--text-secondary);">
                Need help? <a href="/support.php">Contact Support</a> or check the <a href="/cli.php">CLI Guide</a>
            </p>
            <p style="color: var(--text-secondary); margin-top: 1rem;">
                © <?= date('Y') ?> After Dark Systems, LLC. All rights reserved.
            </p>
        </div>
    </div>

    <script src="/assets/js/main.js?v=<?= time() ?>"></script>
</body>
</html>
<!-- version hash: 86db590587abafc7e6e5e85429d703db3c8280a29dc86a360909573de77186c3 -->
