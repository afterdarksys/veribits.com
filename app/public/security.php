<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security & Trust - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
    <style>
        .trust-badge {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border-radius: 12px;
            color: white;
            font-weight: bold;
            margin: 0.5rem;
            text-align: center;
        }
        .compliance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .compliance-card {
            padding: 1.5rem;
            background: var(--darker-bg);
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }
        .security-metric {
            text-align: center;
            padding: 2rem;
        }
        .security-metric .number {
            font-size: 3rem;
            font-weight: bold;
            color: var(--primary-color);
            display: block;
        }
        .security-metric .label {
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <nav>
        <div class="container">
            <a href="/" class="logo">VeriBits</a>
            <ul>
                <li><a href="/tools.php">Tools</a></li>
                <li><a href="/cli.php">CLI</a></li>
                <li><a href="/pricing.php">Pricing</a></li>
                <li><a href="/security.php">Security</a></li>
                <li><a href="/about.php">About</a></li>
                <li data-auth-item="true"><a href="/login.php">Login</a></li>
                <li data-auth-item="true"><a href="/signup.php" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <section style="padding: 8rem 2rem 4rem;">
        <div class="container" style="max-width: 1200px;">
            <h1 style="font-size: 3.5rem; margin-bottom: 1rem; text-align: center;">🔒 Security & Trust</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem; font-size: 1.2rem;">
                Enterprise-grade security for your most sensitive data
            </p>

            <!-- Trust Badges -->
            <div style="text-align: center; margin: 3rem 0;">
                <div class="trust-badge">🔐 256-bit Encryption</div>
                <div class="trust-badge">🌐 TLS 1.3</div>
                <div class="trust-badge">✅ SOC 2 Type II</div>
                <div class="trust-badge">🇪🇺 GDPR Compliant</div>
                <div class="trust-badge">🛡️ ISO 27001</div>
                <div class="trust-badge">📊 99.9% SLA</div>
            </div>

            <!-- Security Metrics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; margin: 4rem 0;">
                <div class="security-metric">
                    <span class="number">99.9%</span>
                    <span class="label">Uptime SLA</span>
                </div>
                <div class="security-metric">
                    <span class="number">24/7</span>
                    <span class="label">Security Monitoring</span>
                </div>
                <div class="security-metric">
                    <span class="number">&lt;15min</span>
                    <span class="label">Incident Response</span>
                </div>
                <div class="security-metric">
                    <span class="number">Zero</span>
                    <span class="label">Breaches (Ever)</span>
                </div>
            </div>

            <!-- Data Security -->
            <div class="feature-card" style="margin-bottom: 3rem;">
                <h2 style="margin-bottom: 1.5rem;">🔐 Data Security</h2>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Encryption</h3>
                <ul style="margin-left: 1.5rem; margin-bottom: 1.5rem;">
                    <li><strong>At Rest:</strong> AES-256 encryption for all stored data</li>
                    <li><strong>In Transit:</strong> TLS 1.3 for all API and web traffic</li>
                    <li><strong>End-to-End:</strong> Client-side encryption available for sensitive data</li>
                    <li><strong>Key Management:</strong> Hardware Security Modules (HSM) for key storage</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Zero-Knowledge Architecture</h3>
                <p style="margin-bottom: 1rem;">
                    Optional zero-knowledge encryption means we never see your plaintext data. You control the keys.
                </p>
                <ul style="margin-left: 1.5rem;">
                    <li>Client-side encryption before upload</li>
                    <li>Server-side encrypted data processing</li>
                    <li>Encrypted results storage</li>
                    <li>You hold the decryption keys</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Data Retention</h3>
                <ul style="margin-left: 1.5rem;">
                    <li><strong>Analysis Results:</strong> 90 days (configurable up to 1 year for Enterprise)</li>
                    <li><strong>Uploaded Files:</strong> 30 days (immediately deletable by you)</li>
                    <li><strong>Audit Logs:</strong> 1 year (7 years for Enterprise)</li>
                    <li><strong>Account Data:</strong> Deleted within 30 days of account closure</li>
                </ul>
            </div>

            <!-- Compliance & Certifications -->
            <div class="feature-card" style="margin-bottom: 3rem;">
                <h2 style="margin-bottom: 1.5rem;">📜 Compliance & Certifications</h2>

                <div class="compliance-grid">
                    <div class="compliance-card">
                        <h3 style="margin-bottom: 0.5rem;">🛡️ SOC 2 Type II</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">
                            Independently audited security controls for service organizations
                        </p>
                        <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                            <strong>Last Audit:</strong> January 2025
                        </p>
                    </div>

                    <div class="compliance-card">
                        <h3 style="margin-bottom: 0.5rem;">🔐 ISO 27001</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">
                            International standard for information security management
                        </p>
                        <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                            <strong>Certified:</strong> 2024
                        </p>
                    </div>

                    <div class="compliance-card">
                        <h3 style="margin-bottom: 0.5rem;">🇪🇺 GDPR Compliant</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">
                            Full compliance with EU General Data Protection Regulation
                        </p>
                        <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                            <strong>DPA Available:</strong> Yes
                        </p>
                    </div>

                    <div class="compliance-card">
                        <h3 style="margin-bottom: 0.5rem;">🇺🇸 CCPA Compliant</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">
                            California Consumer Privacy Act compliance
                        </p>
                        <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                            <strong>Privacy Rights:</strong> Fully Supported
                        </p>
                    </div>

                    <div class="compliance-card">
                        <h3 style="margin-bottom: 0.5rem;">🏥 HIPAA Ready</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">
                            HIPAA-compliant infrastructure available for healthcare
                        </p>
                        <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                            <strong>BAA Available:</strong> Enterprise Plan
                        </p>
                    </div>

                    <div class="compliance-card">
                        <h3 style="margin-bottom: 0.5rem;">💳 PCI DSS</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">
                            Payment Card Industry Data Security Standard
                        </p>
                        <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                            <strong>Level:</strong> Service Provider Level 1
                        </p>
                    </div>
                </div>
            </div>

            <!-- Infrastructure Security -->
            <div class="feature-card" style="margin-bottom: 3rem;">
                <h2 style="margin-bottom: 1.5rem;">🏗️ Infrastructure Security</h2>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Cloud Infrastructure</h3>
                <ul style="margin-left: 1.5rem; margin-bottom: 1.5rem;">
                    <li>Multi-region deployment (US-East, US-West, EU, APAC)</li>
                    <li>Automated failover and disaster recovery</li>
                    <li>Daily encrypted backups with 30-day retention</li>
                    <li>Geo-redundant storage</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Network Security</h3>
                <ul style="margin-left: 1.5rem; margin-bottom: 1.5rem;">
                    <li>WAF (Web Application Firewall) protection</li>
                    <li>DDoS mitigation and traffic filtering</li>
                    <li>Network segmentation and isolation</li>
                    <li>Intrusion Detection System (IDS)</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Application Security</h3>
                <ul style="margin-left: 1.5rem;">
                    <li>OWASP Top 10 protection</li>
                    <li>Regular security audits and penetration testing</li>
                    <li>Automated vulnerability scanning</li>
                    <li>Secure Software Development Lifecycle (SSDLC)</li>
                    <li>Dependency vulnerability monitoring</li>
                </ul>
            </div>

            <!-- Access Control -->
            <div class="feature-card" style="margin-bottom: 3rem;">
                <h2 style="margin-bottom: 1.5rem;">👤 Access Control & Authentication</h2>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Authentication</h3>
                <ul style="margin-left: 1.5rem; margin-bottom: 1.5rem;">
                    <li><strong>Multi-Factor Authentication (MFA):</strong> TOTP, SMS, Hardware keys</li>
                    <li><strong>Single Sign-On (SSO):</strong> SAML 2.0, OAuth 2.0, OpenID Connect</li>
                    <li><strong>API Keys:</strong> Scoped permissions, automatic rotation</li>
                    <li><strong>Session Management:</strong> Secure, short-lived tokens</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Authorization</h3>
                <ul style="margin-left: 1.5rem; margin-bottom: 1.5rem;">
                    <li>Role-Based Access Control (RBAC)</li>
                    <li>Least-privilege principle enforcement</li>
                    <li>Fine-grained API permissions</li>
                    <li>Team and organization isolation</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Audit Logging</h3>
                <ul style="margin-left: 1.5rem;">
                    <li>Comprehensive audit trail for all actions</li>
                    <li>Real-time security event monitoring</li>
                    <li>Tamper-proof log storage</li>
                    <li>Export logs to your SIEM</li>
                </ul>
            </div>

            <!-- Incident Response -->
            <div class="feature-card" style="margin-bottom: 3rem;">
                <h2 style="margin-bottom: 1.5rem;">🚨 Incident Response</h2>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">24/7 Security Operations Center</h3>
                <p style="margin-bottom: 1rem;">
                    Our dedicated security team monitors all systems around the clock.
                </p>
                <ul style="margin-left: 1.5rem; margin-bottom: 1.5rem;">
                    <li>Real-time threat detection</li>
                    <li>Automated incident response workflows</li>
                    <li>15-minute response time SLA for critical incidents</li>
                    <li>Quarterly incident response drills</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Vulnerability Management</h3>
                <ul style="margin-left: 1.5rem; margin-bottom: 1.5rem;">
                    <li>Continuous vulnerability scanning</li>
                    <li>Responsible disclosure program</li>
                    <li>Bug bounty program ($500 - $10,000 rewards)</li>
                    <li>Patch SLA: Critical (24h), High (7 days), Medium (30 days)</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Communication</h3>
                <ul style="margin-left: 1.5rem;">
                    <li>Transparent security notifications</li>
                    <li>Status page: <a href="https://status.veribits.com" style="color: var(--primary-color);">status.veribits.com</a></li>
                    <li>Security mailing list for critical updates</li>
                    <li>Annual security report published</li>
                </ul>
            </div>

            <!-- Privacy -->
            <div class="feature-card" style="margin-bottom: 3rem;">
                <h2 style="margin-bottom: 1.5rem;">🔒 Privacy Commitment</h2>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Data Ownership</h3>
                <p style="margin-bottom: 1.5rem;">
                    <strong>Your data is YOUR data.</strong> We never sell, rent, or share your data with third parties.
                </p>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Data Processing</h3>
                <ul style="margin-left: 1.5rem; margin-bottom: 1.5rem;">
                    <li>We only process data necessary to provide the service</li>
                    <li>No data mining or profiling for advertising</li>
                    <li>Data segregation between customers</li>
                    <li>Right to export all your data (portable format)</li>
                    <li>Right to delete your data permanently</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Transparency</h3>
                <ul style="margin-left: 1.5rem;">
                    <li>Clear, readable privacy policy (no legalese)</li>
                    <li>Data Processing Agreement (DPA) available</li>
                    <li>Subprocessor list publicly available</li>
                    <li>Annual transparency report</li>
                </ul>
            </div>

            <!-- SLA & Availability -->
            <div class="feature-card" style="margin-bottom: 3rem;">
                <h2 style="margin-bottom: 1.5rem;">📊 Service Level Agreement</h2>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Uptime Guarantee</h3>
                <table style="width: 100%; border-collapse: collapse; margin: 1rem 0;">
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th style="text-align: left; padding: 0.75rem;">Plan</th>
                        <th style="text-align: left; padding: 0.75rem;">Monthly Uptime</th>
                        <th style="text-align: left; padding: 0.75rem;">Credits</th>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.75rem;">Free</td>
                        <td style="padding: 0.75rem;">Best effort</td>
                        <td style="padding: 0.75rem;">N/A</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.75rem;">Pro</td>
                        <td style="padding: 0.75rem;">99.5%</td>
                        <td style="padding: 0.75rem;">10% credit per 0.5% below</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.75rem;">Enterprise</td>
                        <td style="padding: 0.75rem;">99.9%</td>
                        <td style="padding: 0.75rem;">25% credit per 0.1% below</td>
                    </tr>
                </table>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Support Response Times</h3>
                <table style="width: 100%; border-collapse: collapse; margin: 1rem 0;">
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th style="text-align: left; padding: 0.75rem;">Severity</th>
                        <th style="text-align: left; padding: 0.75rem;">Pro</th>
                        <th style="text-align: left; padding: 0.75rem;">Enterprise</th>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.75rem;">Critical</td>
                        <td style="padding: 0.75rem;">4 hours</td>
                        <td style="padding: 0.75rem;">1 hour</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.75rem;">High</td>
                        <td style="padding: 0.75rem;">1 business day</td>
                        <td style="padding: 0.75rem;">4 hours</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.75rem;">Normal</td>
                        <td style="padding: 0.75rem;">2 business days</td>
                        <td style="padding: 0.75rem;">1 business day</td>
                    </tr>
                </table>
            </div>

            <!-- Contact & Resources -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">📞 Security Resources</h2>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Report Security Issue</h3>
                <p style="margin-bottom: 0.5rem;">
                    Found a vulnerability? We value responsible disclosure.
                </p>
                <p style="margin-bottom: 1.5rem;">
                    📧 Email: <a href="mailto:security@veribits.com" style="color: var(--primary-color);">security@veribits.com</a><br>
                    🔐 PGP Key: <a href="/security-pgp.asc" style="color: var(--primary-color);">Download</a><br>
                    💰 Bug Bounty: <a href="/bug-bounty" style="color: var(--primary-color);">Learn More</a>
                </p>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Security Documentation</h3>
                <ul style="margin-left: 1.5rem;">
                    <li><a href="/docs/security-whitepaper.pdf" style="color: var(--primary-color);">Security Whitepaper</a></li>
                    <li><a href="/docs/soc2-report.pdf" style="color: var(--primary-color);">SOC 2 Report (NDA Required)</a></li>
                    <li><a href="/docs/dpa.pdf" style="color: var(--primary-color);">Data Processing Agreement</a></li>
                    <li><a href="/docs/subprocessors.pdf" style="color: var(--primary-color);">Subprocessor List</a></li>
                    <li><a href="https://status.veribits.com" style="color: var(--primary-color);">System Status Page</a></li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Contact Security Team</h3>
                <p>
                    For security inquiries, compliance questions, or enterprise security requirements:<br>
                    📧 <a href="mailto:security@veribits.com" style="color: var(--primary-color);">security@veribits.com</a>
                </p>
            </div>

            <!-- Last Updated -->
            <p style="text-align: center; color: var(--text-secondary); margin-top: 3rem; font-size: 0.9rem;">
                Last Updated: January 28, 2025 • Version 2.0
            </p>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2025 VeriBits by After Dark Systems. All rights reserved.</p>
            <p style="margin-top: 0.5rem;">
                <a href="/privacy.php" style="color: var(--text-secondary); margin: 0 1rem;">Privacy</a>
                <a href="/terms.php" style="color: var(--text-secondary); margin: 0 1rem;">Terms</a>
                <a href="/security.php" style="color: var(--text-secondary); margin: 0 1rem;">Security</a>
                <a href="/support.php" style="color: var(--text-secondary); margin: 0 1rem;">Support</a>
            </p>
        </div>
    </footer>

    <script src="/assets/js/main.js?v=<?= time() ?>"></script>
</body>
</html>
