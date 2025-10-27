<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Tools - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <nav>
        <div class="container">
            <a href="/" class="logo">VeriBits</a>
            <ul>
                <li><a href="/tools.php">Tools</a></li>
                <li><a href="/cli.php">CLI</a></li>
                <li><a href="/pricing.php">Pricing</a></li>
                <li><a href="/about.php">About</a></li>
                <li><a href="/login.php">Login</a></li>
                <li><a href="/signup.php" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <section style="padding: 8rem 2rem 4rem;">
        <div class="container">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Professional Security & Developer Tools</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem; font-size: 1.2rem;">
                22+ Professional Tools for Security, DevOps & Network Engineering
            </p>

            <h2 style="color: var(--primary-color); margin-bottom: 1.5rem; margin-top: 3rem;">🔐 Security & Cryptography</h2>
            <div class="tools-grid">
                <div class="tool-card" onclick="window.location='/tool/file-magic.php'">
                    <div class="feature-icon">🔍</div>
                    <h3>File Magic Detector</h3>
                    <p>Identify file types by analyzing magic numbers and binary headers. Supports 40+ file formats.</p>
                    <a href="/tool/file-magic.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/code-signing.php'">
                    <div class="feature-icon">✍️</div>
                    <h3>File Signature Verifier</h3>
                    <p>Verify PGP, JAR, AIR, and macOS code signatures. Validate digital signatures and certificates.</p>
                    <a href="/tool/code-signing.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/smtp-relay-check.php'">
                    <div class="feature-icon">📧</div>
                    <h3>Email Verification Suite</h3>
                    <p>Comprehensive email verification: SPF, DKIM, DMARC, MX records, deliverability scoring, and more.</p>
                    <a href="/tool/smtp-relay-check.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/cert-converter.php'">
                    <div class="feature-icon">🔐</div>
                    <h3>SSL Chain Resolver</h3>
                    <p>Automatically build and validate complete SSL/TLS certificate chains with verification.</p>
                    <a href="/tool/cert-converter.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/steganography.php'">
                    <div class="feature-icon">🎭</div>
                    <h3>Steganography Detector</h3>
                    <p>Detect hidden data in images and files. Identify potential steganographic content.</p>
                    <a href="/tool/steganography.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/pgp-validator.php'">
                    <div class="feature-icon">🔑</div>
                    <h3>PGP Key Validator</h3>
                    <p>Validate PGP public keys, check expiration dates, and verify key fingerprints.</p>
                    <a href="/tool/pgp-validator.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/dns-validator.php'">
                    <div class="feature-icon">🌐</div>
                    <h3>DNS Validator</h3>
                    <p>Validate DNS records, check DNSSEC, and analyze domain configurations.</p>
                    <a href="/tool/dns-validator.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/ip-calculator.php'">
                    <div class="feature-icon">🔢</div>
                    <h3>IP Calculator</h3>
                    <p>Calculate IP subnets, CIDR notation, and network ranges.</p>
                    <a href="/tool/ip-calculator.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/security-headers.php'">
                    <div class="feature-icon">🔍</div>
                    <h3>Data Breach Checker</h3>
                    <p>Check if your email or password has been compromised in known data breaches.</p>
                    <a href="/tool/security-headers.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/security-headers.php'">
                    <div class="feature-icon">☁️</div>
                    <h3>Cloud Storage Auditor</h3>
                    <p>Audit AWS S3 and Azure Blob storage for security misconfigurations and public access.</p>
                    <a href="/tool/security-headers.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/url-encoder.php'">
                    <div class="feature-icon">📝</div>
                    <h3>Base64 Encoder/Decoder</h3>
                    <p>Encode and decode Base64 data for various applications and protocols.</p>
                    <a href="/tool/url-encoder.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>
            </div>

            <h2 style="color: var(--primary-color); margin-bottom: 1.5rem; margin-top: 3rem;">🚀 Enterprise Security Tools</h2>
            <div class="tools-grid">
                <div class="tool-card" onclick="window.location='/tool/iam-policy-analyzer.php'">
                    <div class="feature-icon">🔐</div>
                    <h3>AWS IAM Policy Analyzer</h3>
                    <p>Analyze IAM policies for security risks, wildcards, and overpermissive access. Get 0-100 risk scores.</p>
                    <a href="/tool/iam-policy-analyzer.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/secrets-scanner.php'">
                    <div class="feature-icon">🔑</div>
                    <h3>Secrets Scanner</h3>
                    <p>Detect hardcoded API keys, passwords, AWS credentials, and private keys in your code.</p>
                    <a href="/tool/secrets-scanner.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/db-connection-auditor.php'">
                    <div class="feature-icon">🗄️</div>
                    <h3>Database Connection Auditor</h3>
                    <p>Audit connection strings for plaintext passwords, disabled SSL, and security issues.</p>
                    <a href="/tool/db-connection-auditor.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/docker-scanner.php'">
                    <div class="feature-icon">🐳</div>
                    <h3>Docker Image Scanner</h3>
                    <p>Scan Docker images for CVEs, secrets, and security misconfigurations.</p>
                    <a href="/tool/docker-scanner.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/terraform-scanner.php'">
                    <div class="feature-icon">☁️</div>
                    <h3>Terraform Scanner</h3>
                    <p>Scan IaC for misconfigurations: public S3 buckets, open security groups, unencrypted DBs.</p>
                    <a href="/tool/terraform-scanner.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/kubernetes-validator.php'">
                    <div class="feature-icon">☸️</div>
                    <h3>Kubernetes Validator</h3>
                    <p>Validate K8s manifests for privileged containers, host mounts, and RBAC issues.</p>
                    <a href="/tool/kubernetes-validator.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>
            </div>

            <h2 style="color: var(--primary-color); margin-bottom: 1.5rem; margin-top: 3rem;">🌐 Network & DNS Tools</h2>
            <div class="tools-grid">
                <div class="tool-card" onclick="window.location='/tool/firewall-editor.php'">
                    <div class="feature-icon">🛡️</div>
                    <h3>Firewall Editor</h3>
                    <p>Visual iptables/ebtables editor with drag-and-drop rules, version control, and live preview. Perfect for managing server firewalls.</p>
                    <a href="/tool/firewall-editor.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/pcap-analyzer.php'">
                    <div class="feature-icon">📊</div>
                    <h3>PCAP Analyzer (AI-Powered)</h3>
                    <p>Advanced PCAP analysis with AI insights. DNS troubleshooting, BGP/OSPF routing, attack detection, and more.</p>
                    <a href="/tool/pcap-analyzer.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/dnssec-validator.php'">
                    <div class="feature-icon">🔐</div>
                    <h3>DNSSEC Validator</h3>
                    <p>Validate DNSSEC configuration, check chain of trust, and verify DNSKEY, DS, and RRSIG records.</p>
                    <a href="/tool/dnssec-validator.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/dns-propagation.php'">
                    <div class="feature-icon">🌍</div>
                    <h3>DNS Propagation Checker</h3>
                    <p>Check DNS propagation across 16 global nameservers with real-time status and query times.</p>
                    <a href="/tool/dns-propagation.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/reverse-dns.php'">
                    <div class="feature-icon">🔄</div>
                    <h3>Reverse DNS Lookup</h3>
                    <p>Bulk PTR record lookup with forward DNS validation. Perfect for network troubleshooting.</p>
                    <a href="/tool/reverse-dns.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/visual-traceroute.php'">
                    <div class="feature-icon">🗺️</div>
                    <h3>Visual Traceroute</h3>
                    <p>Interactive traceroute with geographic visualization and hop latency analysis.</p>
                    <a href="/tool/visual-traceroute.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/bgp-intelligence.php'">
                    <div class="feature-icon">🔀</div>
                    <h3>BGP Intelligence</h3>
                    <p>BGP prefix lookup, ASN information, peer analysis, and routing intelligence.</p>
                    <a href="/tool/bgp-intelligence.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/rbl-check.php'">
                    <div class="feature-icon">🚫</div>
                    <h3>RBL/Blacklist Checker</h3>
                    <p>Check if your IP or domain is listed on major email blacklists and RBLs.</p>
                    <a href="/tool/rbl-check.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/zone-validator.php'">
                    <div class="feature-icon">📝</div>
                    <h3>DNS Zone File Validator</h3>
                    <p>Validate DNS zone files, check syntax, and verify record configurations.</p>
                    <a href="/tool/zone-validator.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/dns-converter.php'">
                    <div class="feature-icon">🔄</div>
                    <h3>DNS Server Migration Tool</h3>
                    <p>Convert djbdns/dnscache to Unbound, BIND to NSD. Upload configs, get production-ready output.</p>
                    <a href="/tool/dns-converter.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>
            </div>

            <h2 style="color: var(--primary-color); margin-bottom: 1.5rem; margin-top: 3rem;">🛠️ Developer Tools</h2>
            <div class="tools-grid">
                <div class="tool-card" onclick="window.location='/tool/jwt-debugger.php'">
                    <div class="feature-icon">🔑</div>
                    <h3>JWT Debugger</h3>
                    <p>Decode, verify, and generate JSON Web Tokens (JWT) with signature validation.</p>
                    <a href="/tool/jwt-debugger.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/regex-tester.php'">
                    <div class="feature-icon">🔤</div>
                    <h3>Regex Tester</h3>
                    <p>Test regular expressions with real-time matching, highlighting, and pattern explanation.</p>
                    <a href="/tool/regex-tester.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/json-yaml-validator.php'">
                    <div class="feature-icon">📋</div>
                    <h3>JSON/YAML Validator</h3>
                    <p>Validate, format, and convert between JSON and YAML formats with syntax highlighting.</p>
                    <a href="/tool/json-yaml-validator.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/url-encoder.php'">
                    <div class="feature-icon">🔗</div>
                    <h3>URL Encoder/Decoder</h3>
                    <p>Encode, decode, and parse URLs with component extraction and validation.</p>
                    <a href="/tool/url-encoder.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/hash-generator.php'">
                    <div class="feature-icon">🔐</div>
                    <h3>Hash Generator</h3>
                    <p>Generate MD5, SHA-1, SHA-256, SHA-512, and bcrypt hashes for any text or file.</p>
                    <a href="/tool/hash-generator.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>

                <div class="tool-card" onclick="window.location='/tool/base64-encoder.php'">
                    <div class="feature-icon">📋</div>
                    <h3>Base64 Encoder/Decoder</h3>
                    <p>Encode and decode Base64 strings with support for files and images.</p>
                    <a href="/tool/base64-encoder.php" class="btn btn-primary" style="margin-top: 1rem;">Launch Tool →</a>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2025 VeriBits. All rights reserved.</p>
            <p style="margin-top: 0.5rem;">
                A service from <a href="https://www.afterdarksys.com/" target="_blank" rel="noopener">After Dark Systems, LLC</a>
            </p>
            <p style="margin-top: 1rem;">
                <a href="/privacy.php" style="color: var(--text-secondary); margin: 0 1rem;">Privacy</a>
                <a href="/terms.php" style="color: var(--text-secondary); margin: 0 1rem;">Terms</a>
                <a href="/support.php" style="color: var(--text-secondary); margin: 0 1rem;">Support</a>
            </p>
        </div>
    </footer>
</body>
</html>
<!-- version hash: 86db590587abafc7e6e5e85429d703db3c8280a29dc86a360909573de77186c3 -->
