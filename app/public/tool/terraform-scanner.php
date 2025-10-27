<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terraform Security Scanner - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .score-badge {
            display: inline-block;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 2.5rem;
            font-weight: bold;
            margin: 1rem 0;
        }
        .score-critical { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }
        .score-high { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: white; }
        .score-medium { background: linear-gradient(135deg, #eab308 0%, #ca8a04 100%); color: white; }
        .score-low { background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: white; }
        .score-excellent { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }

        .finding-card {
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid;
        }
        .finding-card.critical { border-left-color: #ef4444; }
        .finding-card.high { border-left-color: #f97316; }
        .finding-card.medium { border-left-color: #eab308; }
        .finding-card.low { border-left-color: #3b82f6; }

        .severity-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        .severity-badge.critical { background: #ef4444; color: white; }
        .severity-badge.high { background: #f97316; color: white; }
        .severity-badge.medium { background: #eab308; color: #000; }
        .severity-badge.low { background: #3b82f6; color: white; }

        .code-block {
            background: rgba(0,0,0,0.4);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 1rem;
            margin: 1rem 0;
            overflow-x: auto;
        }
        .code-block pre {
            margin: 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .code-block .code-header {
            background: rgba(255,255,255,0.05);
            padding: 0.5rem;
            margin: -1rem -1rem 1rem -1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        .stat-card {
            background: rgba(255,255,255,0.05);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--accent-color);
        }
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }

        .example-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .example-tab {
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .example-tab:hover, .example-tab.active {
            background: rgba(255,255,255,0.1);
            border-color: var(--accent-color);
        }

        .remediation-section {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid #10b981;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .remediation-section h4 {
            color: #10b981;
            margin-bottom: 0.5rem;
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
                <li><a href="/about.php">About</a></li>
                <li><a href="/login.php">Login</a></li>
                <li><a href="/signup.php" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <section style="padding: 8rem 2rem 4rem;">
        <div class="container" style="max-width: 1200px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem;">Terraform Security Scanner</h1>
            <p style="color: var(--text-secondary); margin-bottom: 3rem; font-size: 1.2rem;">
                Scan Terraform/HCL files for security vulnerabilities including public S3 buckets, open security groups,
                unencrypted resources, hardcoded credentials, and IAM misconfigurations.
            </p>

            <div id="alert-container"></div>

            <!-- Input Section -->
            <div class="feature-card" style="margin-bottom: 2rem;">
                <h2 style="margin-bottom: 1rem;">Upload Terraform Configuration</h2>

                <div class="example-tabs">
                    <button class="example-tab active" onclick="switchTab('manual')">Manual Input</button>
                    <button class="example-tab" onclick="switchTab('s3')">S3 Example</button>
                    <button class="example-tab" onclick="switchTab('ec2')">EC2 Example</button>
                    <button class="example-tab" onclick="switchTab('rds')">RDS Example</button>
                    <button class="example-tab" onclick="switchTab('iam')">IAM Example</button>
                </div>

                <form id="terraform-form">
                    <div class="form-group">
                        <label for="terraform-code">Terraform/HCL Code *</label>
                        <textarea
                            id="terraform-code"
                            name="terraform-code"
                            rows="20"
                            required
                            placeholder="Paste your Terraform configuration here..."
                            style="font-family: 'Courier New', monospace; font-size: 0.9rem;"
                        ></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" id="scan-btn">
                        Scan for Vulnerabilities
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearForm()" style="margin-left: 1rem;">
                        Clear
                    </button>
                </form>
            </div>

            <!-- Results Section -->
            <div id="results" style="display: none;">
                <!-- Security Score -->
                <div class="feature-card" style="text-align: center; margin-bottom: 2rem;">
                    <h2 style="margin-bottom: 1rem;">Security Score</h2>
                    <div id="score-badge"></div>
                    <p id="score-message" style="color: var(--text-secondary); margin-top: 1rem; font-size: 1.1rem;"></p>
                </div>

                <!-- Statistics -->
                <div class="feature-card" style="margin-bottom: 2rem;">
                    <h2 style="margin-bottom: 1rem;">Scan Summary</h2>
                    <div class="stat-grid" id="stats-grid"></div>
                </div>

                <!-- Findings -->
                <div class="feature-card" style="margin-bottom: 2rem;">
                    <h2 style="margin-bottom: 1.5rem;">Security Findings</h2>
                    <div id="findings-content"></div>
                </div>

                <!-- Best Practices -->
                <div class="feature-card">
                    <h2 style="margin-bottom: 1rem;">Recommendations</h2>
                    <div id="recommendations-content"></div>
                </div>
            </div>

            <!-- Information Section -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">What We Check</h2>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <div>
                        <h3 style="color: var(--accent-color); margin-bottom: 0.75rem;">AWS S3 Security</h3>
                        <ul style="margin-left: 1.5rem; line-height: 1.8;">
                            <li>Public access blocks disabled</li>
                            <li>Public read/write ACLs</li>
                            <li>Unencrypted buckets</li>
                            <li>Missing versioning</li>
                            <li>No logging enabled</li>
                        </ul>
                    </div>

                    <div>
                        <h3 style="color: var(--accent-color); margin-bottom: 0.75rem;">Network Security</h3>
                        <ul style="margin-left: 1.5rem; line-height: 1.8;">
                            <li>Open security groups (0.0.0.0/0)</li>
                            <li>Unrestricted ingress rules</li>
                            <li>Missing egress controls</li>
                            <li>Public subnet exposure</li>
                        </ul>
                    </div>

                    <div>
                        <h3 style="color: var(--accent-color); margin-bottom: 0.75rem;">Data Encryption</h3>
                        <ul style="margin-left: 1.5rem; line-height: 1.8;">
                            <li>Unencrypted RDS instances</li>
                            <li>Unencrypted EBS volumes</li>
                            <li>No KMS encryption</li>
                            <li>Storage encryption disabled</li>
                        </ul>
                    </div>

                    <div>
                        <h3 style="color: var(--accent-color); margin-bottom: 0.75rem;">IAM & Access</h3>
                        <ul style="margin-left: 1.5rem; line-height: 1.8;">
                            <li>Overly permissive policies</li>
                            <li>Wildcard actions/resources</li>
                            <li>Hardcoded credentials</li>
                            <li>Missing MFA requirements</li>
                        </ul>
                    </div>

                    <div>
                        <h3 style="color: var(--accent-color); margin-bottom: 0.75rem;">Monitoring & Backup</h3>
                        <ul style="margin-left: 1.5rem; line-height: 1.8;">
                            <li>Missing CloudWatch logs</li>
                            <li>No backup configurations</li>
                            <li>Disabled monitoring</li>
                            <li>Missing CloudTrail</li>
                        </ul>
                    </div>

                    <div>
                        <h3 style="color: var(--accent-color); margin-bottom: 0.75rem;">Best Practices</h3>
                        <ul style="margin-left: 1.5rem; line-height: 1.8;">
                            <li>Missing resource tags</li>
                            <li>No deletion protection</li>
                            <li>Default configurations</li>
                            <li>Lifecycle policies missing</li>
                        </ul>
                    </div>
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
        </div>
    </footer>

    <script src="/assets/js/main.js"></script>
    <script>
        const examples = {
            s3: `# Vulnerable S3 Bucket Configuration
resource "aws_s3_bucket" "example" {
  bucket = "my-public-bucket"
  acl    = "public-read"

  tags = {
    Environment = "dev"
  }
}

resource "aws_s3_bucket_public_access_block" "example" {
  bucket = aws_s3_bucket.example.id

  block_public_acls       = false
  block_public_policy     = false
  ignore_public_acls      = false
  restrict_public_buckets = false
}`,
            ec2: `# Vulnerable EC2 and Security Group
resource "aws_security_group" "allow_all" {
  name        = "allow_all"
  description = "Allow all inbound traffic"

  ingress {
    description = "Allow all traffic"
    from_port   = 0
    to_port     = 65535
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
}

resource "aws_instance" "web" {
  ami           = "ami-0c55b159cbfafe1f0"
  instance_type = "t2.micro"

  vpc_security_group_ids = [aws_security_group.allow_all.id]
  associate_public_ip_address = true

  # Hardcoded credentials - DANGEROUS!
  user_data = <<-EOF
              #!/bin/bash
              export AWS_ACCESS_KEY_ID="AKIAIOSFODNN7EXAMPLE"
              export AWS_SECRET_ACCESS_KEY="wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY"
              EOF
}`,
            rds: `# Vulnerable RDS Database
resource "aws_db_instance" "default" {
  identifier           = "mydb"
  engine               = "mysql"
  engine_version       = "5.7"
  instance_class       = "db.t3.micro"
  allocated_storage    = 20

  username = "admin"
  password = "password123"  # Hardcoded password!

  # Security Issues
  publicly_accessible    = true
  storage_encrypted      = false
  skip_final_snapshot    = true
  backup_retention_period = 0
  deletion_protection    = false

  enabled_cloudwatch_logs_exports = []

  vpc_security_group_ids = [aws_security_group.allow_all.id]
}`,
            iam: `# Overly Permissive IAM Policy
resource "aws_iam_policy" "admin_access" {
  name        = "admin-access"
  description = "Full admin access"

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect   = "Allow"
        Action   = "*"
        Resource = "*"
      }
    ]
  })
}

resource "aws_iam_user" "developer" {
  name = "developer"

  tags = {}  # Missing tags
}

resource "aws_iam_user_policy_attachment" "dev_attach" {
  user       = aws_iam_user.developer.name
  policy_arn = aws_iam_policy.admin_access.arn
}`
        };

        function switchTab(tab) {
            document.querySelectorAll('.example-tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');

            if (tab !== 'manual') {
                document.getElementById('terraform-code').value = examples[tab];
            }
        }

        function clearForm() {
            document.getElementById('terraform-code').value = '';
            document.getElementById('results').style.display = 'none';
        }

        document.getElementById('terraform-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const code = document.getElementById('terraform-code').value;
            const scanBtn = document.getElementById('scan-btn');

            scanBtn.textContent = 'Scanning...';
            scanBtn.disabled = true;

            try {
                // Simulate API call - in production, this would call a backend endpoint
                const results = scanTerraformCode(code);
                displayResults(results);
            } catch (error) {
                showAlert('Scan failed: ' + error.message, 'error');
            } finally {
                scanBtn.textContent = 'Scan for Vulnerabilities';
                scanBtn.disabled = false;
            }
        });

        function scanTerraformCode(code) {
            const findings = [];
            const lines = code.split('\n');

            // Check for public S3 buckets
            if (code.match(/acl\s*=\s*["']public-(read|read-write)["']/i)) {
                findings.push({
                    severity: 'critical',
                    category: 'S3 Security',
                    issue: 'Public S3 Bucket ACL Detected',
                    description: 'Bucket is configured with public-read or public-read-write ACL, making data accessible to anyone on the internet.',
                    line: getLineNumber(code, /acl\s*=\s*["']public-/i),
                    remediation: 'Remove public ACL and use bucket policies with specific principals instead.',
                    code: `# Secure configuration
resource "aws_s3_bucket" "example" {
  bucket = "my-secure-bucket"
  # Remove: acl = "public-read"
}

resource "aws_s3_bucket_public_access_block" "example" {
  bucket = aws_s3_bucket.example.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}`
                });
            }

            // Check for disabled public access blocks
            if (code.match(/block_public_acls\s*=\s*false/i) ||
                code.match(/block_public_policy\s*=\s*false/i)) {
                findings.push({
                    severity: 'critical',
                    category: 'S3 Security',
                    issue: 'S3 Public Access Block Disabled',
                    description: 'Public access block settings are disabled, allowing public access to S3 buckets.',
                    line: getLineNumber(code, /block_public_acls\s*=\s*false/i),
                    remediation: 'Enable all public access block settings to prevent accidental public exposure.',
                    code: `resource "aws_s3_bucket_public_access_block" "example" {
  bucket = aws_s3_bucket.example.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}`
                });
            }

            // Check for open security groups
            if (code.match(/cidr_blocks\s*=\s*\[["']0\.0\.0\.0\/0["']\]/i)) {
                const isIngress = code.match(/ingress\s*\{[^}]*cidr_blocks\s*=\s*\[["']0\.0\.0\.0\/0["']\]/is);
                if (isIngress) {
                    findings.push({
                        severity: 'critical',
                        category: 'Network Security',
                        issue: 'Security Group Open to Internet (0.0.0.0/0)',
                        description: 'Security group allows inbound traffic from any IP address (0.0.0.0/0), exposing resources to the entire internet.',
                        line: getLineNumber(code, /ingress\s*\{/i),
                        remediation: 'Restrict ingress rules to specific IP ranges or security groups.',
                        code: `resource "aws_security_group" "secure" {
  name        = "secure_access"
  description = "Restricted access"

  ingress {
    description = "HTTPS from specific IPs"
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["10.0.0.0/8"]  # Restrict to internal network
  }
}`
                    });
                }
            }

            // Check for unencrypted RDS
            if (code.match(/aws_db_instance/i) && code.match(/storage_encrypted\s*=\s*false/i)) {
                findings.push({
                    severity: 'high',
                    category: 'Data Encryption',
                    issue: 'Unencrypted RDS Database',
                    description: 'RDS instance is not encrypted at rest, potentially exposing sensitive data.',
                    line: getLineNumber(code, /storage_encrypted\s*=\s*false/i),
                    remediation: 'Enable storage encryption using AWS KMS.',
                    code: `resource "aws_db_instance" "default" {
  # ... other configuration ...

  storage_encrypted      = true
  kms_key_id            = aws_kms_key.rds.arn
}`
                });
            }

            // Check for publicly accessible RDS
            if (code.match(/publicly_accessible\s*=\s*true/i)) {
                findings.push({
                    severity: 'critical',
                    category: 'Network Security',
                    issue: 'Publicly Accessible RDS Instance',
                    description: 'Database is accessible from the public internet, increasing attack surface.',
                    line: getLineNumber(code, /publicly_accessible\s*=\s*true/i),
                    remediation: 'Set publicly_accessible to false and use private subnets.',
                    code: `resource "aws_db_instance" "default" {
  # ... other configuration ...

  publicly_accessible = false
  db_subnet_group_name = aws_db_subnet_group.private.name
}`
                });
            }

            // Check for hardcoded AWS credentials
            if (code.match(/AKIA[0-9A-Z]{16}/i)) {
                findings.push({
                    severity: 'critical',
                    category: 'Secrets Management',
                    issue: 'Hardcoded AWS Access Key',
                    description: 'AWS access key ID found in code. This is a critical security risk.',
                    line: getLineNumber(code, /AKIA[0-9A-Z]{16}/i),
                    remediation: 'Remove hardcoded credentials. Use IAM roles, AWS Secrets Manager, or environment variables.',
                    code: `# Use IAM instance profiles instead
resource "aws_iam_instance_profile" "app_profile" {
  name = "app_profile"
  role = aws_iam_role.app_role.name
}

resource "aws_instance" "app" {
  # ... other configuration ...
  iam_instance_profile = aws_iam_instance_profile.app_profile.name
  # No hardcoded credentials!
}`
                });
            }

            // Check for hardcoded passwords
            if (code.match(/password\s*=\s*["'][^"']+["']/i)) {
                findings.push({
                    severity: 'critical',
                    category: 'Secrets Management',
                    issue: 'Hardcoded Password in Configuration',
                    description: 'Password is hardcoded in Terraform configuration instead of using secure secret management.',
                    line: getLineNumber(code, /password\s*=\s*["']/i),
                    remediation: 'Use AWS Secrets Manager or SSM Parameter Store for password management.',
                    code: `# Retrieve password from Secrets Manager
data "aws_secretsmanager_secret_version" "db_password" {
  secret_id = "rds/db/password"
}

resource "aws_db_instance" "default" {
  # ... other configuration ...
  password = data.aws_secretsmanager_secret_version.db_password.secret_string
}`
                });
            }

            // Check for wildcard IAM permissions
            if (code.match(/Action\s*=\s*["']\*["']/i) || code.match(/Resource\s*=\s*["']\*["']/i)) {
                findings.push({
                    severity: 'high',
                    category: 'IAM Security',
                    issue: 'Overly Permissive IAM Policy (Wildcard)',
                    description: 'IAM policy uses wildcard (*) for actions or resources, granting excessive permissions.',
                    line: getLineNumber(code, /Action\s*=\s*["']\*["']/i),
                    remediation: 'Apply principle of least privilege with specific actions and resources.',
                    code: `resource "aws_iam_policy" "restricted" {
  name = "restricted-policy"

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "s3:GetObject",
          "s3:PutObject"
        ]
        Resource = "arn:aws:s3:::my-bucket/*"
      }
    ]
  })
}`
                });
            }

            // Check for no backup retention
            if (code.match(/backup_retention_period\s*=\s*0/i)) {
                findings.push({
                    severity: 'medium',
                    category: 'Backup & Recovery',
                    issue: 'No Backup Retention Configured',
                    description: 'Database backups are disabled, preventing recovery from data loss.',
                    line: getLineNumber(code, /backup_retention_period\s*=\s*0/i),
                    remediation: 'Enable automated backups with appropriate retention period.',
                    code: `resource "aws_db_instance" "default" {
  # ... other configuration ...

  backup_retention_period = 7
  backup_window          = "03:00-04:00"
  maintenance_window     = "mon:04:00-mon:05:00"
}`
                });
            }

            // Check for skip final snapshot
            if (code.match(/skip_final_snapshot\s*=\s*true/i)) {
                findings.push({
                    severity: 'medium',
                    category: 'Backup & Recovery',
                    issue: 'Final Snapshot Disabled',
                    description: 'RDS instance will not create a final snapshot before deletion, risking data loss.',
                    line: getLineNumber(code, /skip_final_snapshot\s*=\s*true/i),
                    remediation: 'Enable final snapshots before deletion.',
                    code: `resource "aws_db_instance" "default" {
  # ... other configuration ...

  skip_final_snapshot       = false
  final_snapshot_identifier = "\${var.identifier}-final-snapshot"
}`
                });
            }

            // Check for no deletion protection
            if (code.match(/deletion_protection\s*=\s*false/i)) {
                findings.push({
                    severity: 'medium',
                    category: 'Resource Protection',
                    issue: 'Deletion Protection Disabled',
                    description: 'Critical resource can be accidentally deleted without protection.',
                    line: getLineNumber(code, /deletion_protection\s*=\s*false/i),
                    remediation: 'Enable deletion protection for production resources.',
                    code: `resource "aws_db_instance" "default" {
  # ... other configuration ...

  deletion_protection = true
}`
                });
            }

            // Check for missing CloudWatch logs
            if (code.match(/aws_db_instance/i) && !code.match(/enabled_cloudwatch_logs_exports/i)) {
                findings.push({
                    severity: 'low',
                    category: 'Monitoring & Logging',
                    issue: 'CloudWatch Logs Not Enabled',
                    description: 'RDS instance is not configured to export logs to CloudWatch.',
                    line: getLineNumber(code, /aws_db_instance/i),
                    remediation: 'Enable CloudWatch log exports for monitoring and auditing.',
                    code: `resource "aws_db_instance" "default" {
  # ... other configuration ...

  enabled_cloudwatch_logs_exports = ["error", "general", "slowquery"]
}`
                });
            }

            // Check for missing tags
            if (code.match(/resource\s+"aws_/i) && !code.match(/tags\s*=\s*\{/i)) {
                findings.push({
                    severity: 'low',
                    category: 'Best Practices',
                    issue: 'Missing Resource Tags',
                    description: 'Resources lack tags for proper organization, cost tracking, and compliance.',
                    line: 1,
                    remediation: 'Add comprehensive tags to all resources.',
                    code: `resource "aws_instance" "example" {
  # ... other configuration ...

  tags = {
    Name        = "web-server"
    Environment = "production"
    Project     = "myapp"
    ManagedBy   = "terraform"
    Owner       = "team@example.com"
  }
}`
                });
            }

            // Check for unencrypted EBS volumes
            if (code.match(/aws_ebs_volume/i) && !code.match(/encrypted\s*=\s*true/i)) {
                findings.push({
                    severity: 'high',
                    category: 'Data Encryption',
                    issue: 'Unencrypted EBS Volume',
                    description: 'EBS volume is not encrypted, potentially exposing data at rest.',
                    line: getLineNumber(code, /aws_ebs_volume/i),
                    remediation: 'Enable EBS encryption with KMS.',
                    code: `resource "aws_ebs_volume" "example" {
  availability_zone = "us-west-2a"
  size             = 40

  encrypted = true
  kms_key_id = aws_kms_key.ebs.arn
}`
                });
            }

            // Check for S3 versioning
            if (code.match(/aws_s3_bucket\s+"[^"]+"/i) && !code.match(/versioning\s*\{/i)) {
                findings.push({
                    severity: 'low',
                    category: 'Best Practices',
                    issue: 'S3 Versioning Not Enabled',
                    description: 'S3 bucket versioning is not configured, preventing recovery from accidental deletions.',
                    line: getLineNumber(code, /aws_s3_bucket/i),
                    remediation: 'Enable versioning for data protection.',
                    code: `resource "aws_s3_bucket_versioning" "example" {
  bucket = aws_s3_bucket.example.id

  versioning_configuration {
    status = "Enabled"
  }
}`
                });
            }

            return calculateSecurityScore(findings);
        }

        function getLineNumber(code, pattern) {
            const lines = code.split('\n');
            for (let i = 0; i < lines.length; i++) {
                if (pattern.test(lines[i])) {
                    return i + 1;
                }
            }
            return null;
        }

        function calculateSecurityScore(findings) {
            let score = 100;
            const severityWeights = { critical: 20, high: 10, medium: 5, low: 2 };

            findings.forEach(finding => {
                score -= severityWeights[finding.severity] || 0;
            });

            score = Math.max(0, score);

            let riskLevel;
            if (score >= 90) riskLevel = 'excellent';
            else if (score >= 70) riskLevel = 'low';
            else if (score >= 50) riskLevel = 'medium';
            else if (score >= 30) riskLevel = 'high';
            else riskLevel = 'critical';

            const stats = {
                total: findings.length,
                critical: findings.filter(f => f.severity === 'critical').length,
                high: findings.filter(f => f.severity === 'high').length,
                medium: findings.filter(f => f.severity === 'medium').length,
                low: findings.filter(f => f.severity === 'low').length
            };

            return { score, riskLevel, findings, stats };
        }

        function displayResults(results) {
            // Display score
            const scoreBadge = document.getElementById('score-badge');
            scoreBadge.innerHTML = `<div class="score-badge score-${results.riskLevel}">${results.score}/100</div>`;

            const scoreMessage = document.getElementById('score-message');
            const messages = {
                excellent: 'Excellent! Your Terraform configuration follows security best practices.',
                low: 'Good security posture with minor improvements needed.',
                medium: 'Moderate risk. Several security issues should be addressed.',
                high: 'High risk. Critical security vulnerabilities detected.',
                critical: 'Critical risk! Immediate action required to secure your infrastructure.'
            };
            scoreMessage.textContent = messages[results.riskLevel];
            scoreMessage.style.color = {
                excellent: '#10b981',
                low: '#22c55e',
                medium: '#eab308',
                high: '#f97316',
                critical: '#ef4444'
            }[results.riskLevel];

            // Display statistics
            const statsGrid = document.getElementById('stats-grid');
            statsGrid.innerHTML = `
                <div class="stat-card">
                    <div class="stat-value">${results.stats.total}</div>
                    <div class="stat-label">Total Findings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #ef4444;">${results.stats.critical}</div>
                    <div class="stat-label">Critical</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #f97316;">${results.stats.high}</div>
                    <div class="stat-label">High</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #eab308;">${results.stats.medium}</div>
                    <div class="stat-label">Medium</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #3b82f6;">${results.stats.low}</div>
                    <div class="stat-label">Low</div>
                </div>
            `;

            // Display findings
            const findingsContent = document.getElementById('findings-content');
            if (results.findings.length === 0) {
                findingsContent.innerHTML = `
                    <p style="text-align: center; color: var(--success-color); font-size: 1.2rem; padding: 2rem;">
                        No security issues detected! Your Terraform configuration looks secure.
                    </p>
                `;
            } else {
                let html = '';
                results.findings.forEach((finding, index) => {
                    html += `
                        <div class="finding-card ${finding.severity}">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                <div>
                                    <h3 style="margin-bottom: 0.5rem;">${finding.issue}</h3>
                                    <span class="severity-badge ${finding.severity}">${finding.severity}</span>
                                    <span style="margin-left: 1rem; color: var(--text-secondary); font-size: 0.9rem;">
                                        ${finding.category}
                                    </span>
                                </div>
                                ${finding.line ? `<span style="color: var(--text-secondary); font-size: 0.85rem;">Line ${finding.line}</span>` : ''}
                            </div>

                            <p style="margin-bottom: 1rem; color: var(--text-secondary);">${finding.description}</p>

                            <div class="remediation-section">
                                <h4>Remediation</h4>
                                <p style="margin-bottom: 0.5rem;">${finding.remediation}</p>
                                ${finding.code ? `
                                    <div class="code-block">
                                        <div class="code-header">Secure Configuration Example</div>
                                        <pre><code>${escapeHtml(finding.code)}</code></pre>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                });
                findingsContent.innerHTML = html;
            }

            // Display recommendations
            const recommendationsContent = document.getElementById('recommendations-content');
            const recommendations = [
                'Always enable encryption at rest for data storage services (S3, RDS, EBS)',
                'Use IAM roles instead of hardcoded credentials',
                'Implement the principle of least privilege for all IAM policies',
                'Enable CloudWatch logging and monitoring for all critical resources',
                'Use AWS Secrets Manager or SSM Parameter Store for sensitive data',
                'Enable versioning and backup retention for data protection',
                'Restrict security group ingress to specific IP ranges',
                'Use private subnets for databases and internal services',
                'Enable deletion protection for production resources',
                'Implement comprehensive resource tagging for governance'
            ];

            let recsHtml = '<ul style="list-style: none; padding: 0;">';
            recommendations.forEach(rec => {
                recsHtml += `
                    <li style="padding: 0.75rem; margin-bottom: 0.5rem; background: rgba(255,255,255,0.05); border-left: 3px solid var(--accent-color); border-radius: 4px;">
                        ${rec}
                    </li>
                `;
            });
            recsHtml += '</ul>';
            recommendationsContent.innerHTML = recsHtml;

            // Show results section
            document.getElementById('results').style.display = 'block';
            document.getElementById('results').scrollIntoView({ behavior: 'smooth' });
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    </script>
</body>
</html>
