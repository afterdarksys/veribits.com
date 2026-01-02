<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Docker Image Scanner - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
    <style>
        .severity-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .severity-critical {
            background: #dc2626;
            color: white;
        }
        .severity-high {
            background: #ea580c;
            color: white;
        }
        .severity-medium {
            background: #ca8a04;
            color: white;
        }
        .severity-low {
            background: #16a34a;
            color: white;
        }
        .severity-info {
            background: #0284c7;
            color: white;
        }
        .issue-card {
            background: rgba(255, 255, 255, 0.03);
            border-left: 4px solid var(--border-color);
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        .issue-card.critical {
            border-left-color: #dc2626;
        }
        .issue-card.high {
            border-left-color: #ea580c;
        }
        .issue-card.medium {
            border-left-color: #ca8a04;
        }
        .issue-card.low {
            border-left-color: #16a34a;
        }
        .issue-card.info {
            border-left-color: #0284c7;
        }
        .code-snippet {
            background: var(--darker-bg);
            padding: 0.75rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            margin: 0.5rem 0;
        }
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            margin: 0 auto;
        }
        .score-excellent {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .score-good {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }
        .score-fair {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        .score-poor {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        .example-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .example-btn {
            padding: 0.75rem;
            background: var(--darker-bg);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        .example-btn:hover {
            border-color: var(--primary-color);
            background: rgba(0, 123, 255, 0.1);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        .stat-box {
            background: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
                <li data-auth-item="true"><a href="/login.php">Login</a></li>
                <li data-auth-item="true"><a href="/signup.php" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <section style="padding: 8rem 2rem 4rem;">
        <div class="container" style="max-width: 1400px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Docker Image Scanner</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem; font-size: 1.2rem;">
                Analyze Dockerfiles for security vulnerabilities, best practices, and optimization opportunities
            </p>

            <div id="alert-container"></div>

            <div class="feature-card" style="margin-bottom: 2rem;">
                <h2 style="margin-bottom: 1rem;">Dockerfile Analysis</h2>

                <div class="example-selector">
                    <button class="example-btn" onclick="loadExample('vulnerable')">
                        <div style="font-weight: bold; margin-bottom: 0.25rem;">Vulnerable</div>
                        <div style="font-size: 0.85rem; color: var(--text-secondary);">Common Issues</div>
                    </button>
                    <button class="example-btn" onclick="loadExample('secure')">
                        <div style="font-weight: bold; margin-bottom: 0.25rem;">Secure</div>
                        <div style="font-size: 0.85rem; color: var(--text-secondary);">Best Practices</div>
                    </button>
                    <button class="example-btn" onclick="loadExample('nodejs')">
                        <div style="font-weight: bold; margin-bottom: 0.25rem;">Node.js</div>
                        <div style="font-size: 0.85rem; color: var(--text-secondary);">Web Application</div>
                    </button>
                    <button class="example-btn" onclick="loadExample('python')">
                        <div style="font-weight: bold; margin-bottom: 0.25rem;">Python</div>
                        <div style="font-size: 0.85rem; color: var(--text-secondary);">Data Science</div>
                    </button>
                </div>

                <form id="scanner-form">
                    <div class="form-group">
                        <label for="dockerfile">Dockerfile Content *</label>
                        <textarea
                            id="dockerfile"
                            name="dockerfile"
                            rows="20"
                            required
                            placeholder="FROM ubuntu:latest&#10;RUN apt-get update && apt-get install -y python3&#10;COPY . /app&#10;WORKDIR /app&#10;CMD [&quot;python3&quot;, &quot;app.py&quot;]"
                            style="font-family: 'Courier New', monospace; font-size: 0.9rem;"
                        ></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" id="scan-btn">
                        Scan Dockerfile
                    </button>
                </form>
            </div>

            <div id="results" style="display: none;">
                <!-- Security Score -->
                <div class="feature-card" style="margin-bottom: 2rem;">
                    <h2 style="margin-bottom: 1.5rem; text-align: center;">Security Score</h2>
                    <div id="score-display"></div>
                    <div id="stats-display" class="stats-grid"></div>
                </div>

                <!-- Issues by Severity -->
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Security Issues</h2>
                    <div id="issues-display"></div>
                </div>

                <!-- Best Practices -->
                <div class="feature-card" style="margin-top: 2rem;">
                    <h2 style="margin-bottom: 1.5rem;">Recommendations</h2>
                    <div id="recommendations-display"></div>
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

    <script src="/assets/js/main.js?v=<?= time() ?>"></script>
    <script>
        const examples = {
            vulnerable: `# Vulnerable Dockerfile Example
FROM ubuntu:latest
RUN apt-get update && apt-get install -y python3 curl
COPY . /app
WORKDIR /app
ENV API_KEY=sk_live_1234567890abcdef
ENV DB_PASSWORD=SuperSecret123!
RUN curl -o /tmp/install.sh https://example.com/install.sh && bash /tmp/install.sh
EXPOSE 22
EXPOSE 3000
CMD python3 app.py`,

            secure: `# Secure Dockerfile Example
FROM python:3.11-slim-bookworm AS builder
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir --user -r requirements.txt

FROM python:3.11-slim-bookworm
WORKDIR /app
RUN groupadd -r appuser && useradd -r -g appuser appuser && \\
    chown -R appuser:appuser /app
COPY --from=builder --chown=appuser:appuser /root/.local /home/appuser/.local
COPY --chown=appuser:appuser . .
USER appuser
EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \\
  CMD python -c "import requests; requests.get('http://localhost:8000/health')"
CMD ["python", "-m", "uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]`,

            nodejs: `# Node.js Application
FROM node:18-alpine
WORKDIR /usr/src/app
COPY package*.json ./
RUN npm ci --only=production
COPY . .
EXPOSE 3000
USER node
CMD ["node", "server.js"]`,

            python: `# Python Data Science
FROM python:3.11
WORKDIR /workspace
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY notebooks/ ./notebooks/
COPY data/ ./data/
EXPOSE 8888
CMD ["jupyter", "notebook", "--ip=0.0.0.0", "--no-browser", "--allow-root"]`
        };

        function loadExample(type) {
            document.getElementById('dockerfile').value = examples[type];
        }

        document.getElementById('scanner-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const dockerfile = document.getElementById('dockerfile').value;
            const scanBtn = document.getElementById('scan-btn');

            scanBtn.textContent = 'Scanning...';
            scanBtn.disabled = true;

            try {
                const result = await scanDockerfile(dockerfile);
                displayResults(result);
            } catch (error) {
                showAlert('Scan error: ' + error.message, 'error');
            } finally {
                scanBtn.textContent = 'Scan Dockerfile';
                scanBtn.disabled = false;
            }
        });

        function scanDockerfile(content) {
            const lines = content.split('\n');
            const issues = [];
            let score = 100;

            // Track what we find
            let hasUser = false;
            let hasHealthcheck = false;
            let hasMultiStage = false;
            let baseImages = [];

            lines.forEach((line, index) => {
                const lineNum = index + 1;
                const trimmed = line.trim();

                // Skip comments and empty lines
                if (trimmed.startsWith('#') || !trimmed) return;

                // Check for FROM instruction
                if (trimmed.toUpperCase().startsWith('FROM')) {
                    const parts = trimmed.split(/\s+/);
                    if (parts.length >= 2) {
                        const image = parts[1];
                        baseImages.push(image);

                        if (parts.length > 2 && parts[2].toUpperCase() === 'AS') {
                            hasMultiStage = true;
                        }

                        // Check for :latest tag
                        if (image.includes(':latest') || !image.includes(':')) {
                            issues.push({
                                severity: 'high',
                                title: 'Using :latest Tag',
                                line: lineNum,
                                code: line,
                                description: 'Base image uses :latest tag or no tag, which can lead to unpredictable builds.',
                                remediation: 'Pin to a specific version: FROM python:3.11-slim instead of FROM python:latest'
                            });
                            score -= 10;
                        }

                        // Check for non-slim/alpine variants
                        if (!image.includes('alpine') && !image.includes('slim') && !image.includes('distroless')) {
                            issues.push({
                                severity: 'medium',
                                title: 'Large Base Image',
                                line: lineNum,
                                code: line,
                                description: 'Using a full-sized base image increases attack surface and image size.',
                                remediation: 'Consider using slim or alpine variants: FROM python:3.11-slim'
                            });
                            score -= 5;
                        }
                    }
                }

                // Check for hardcoded secrets
                const secretPatterns = [
                    { regex: /API_KEY\s*=\s*['"]?[a-zA-Z0-9_-]{20,}/, name: 'API Key' },
                    { regex: /PASSWORD\s*=\s*['"]?[^'"$\s]{8,}/, name: 'Password' },
                    { regex: /SECRET\s*=\s*['"]?[^'"$\s]{10,}/, name: 'Secret' },
                    { regex: /TOKEN\s*=\s*['"]?[^'"$\s]{20,}/, name: 'Token' },
                    { regex: /AKIA[0-9A-Z]{16}/, name: 'AWS Access Key' },
                    { regex: /sk_live_[a-zA-Z0-9]{24,}/, name: 'Stripe API Key' },
                    { regex: /gh[pousr]_[A-Za-z0-9]{36,}/, name: 'GitHub Token' }
                ];

                secretPatterns.forEach(pattern => {
                    if (pattern.regex.test(line)) {
                        issues.push({
                            severity: 'critical',
                            title: `Hardcoded ${pattern.name}`,
                            line: lineNum,
                            code: line,
                            description: `Detected hardcoded ${pattern.name} in Dockerfile.`,
                            remediation: 'Use build arguments (ARG) or runtime environment variables, never commit secrets.'
                        });
                        score -= 20;
                    }
                });

                // Check for USER instruction
                if (trimmed.toUpperCase().startsWith('USER')) {
                    hasUser = true;
                    if (trimmed.includes('root')) {
                        issues.push({
                            severity: 'high',
                            title: 'Running as Root User',
                            line: lineNum,
                            code: line,
                            description: 'Explicitly setting USER to root is a security risk.',
                            remediation: 'Create and use a non-root user instead.'
                        });
                        score -= 15;
                        hasUser = false;
                    }
                }

                // Check for HEALTHCHECK
                if (trimmed.toUpperCase().startsWith('HEALTHCHECK')) {
                    hasHealthcheck = true;
                }

                // Check for dangerous ports
                if (trimmed.toUpperCase().startsWith('EXPOSE')) {
                    const dangerousPorts = ['22', '23', '21', '3389', '445', '139'];
                    dangerousPorts.forEach(port => {
                        if (trimmed.includes(port)) {
                            issues.push({
                                severity: 'high',
                                title: `Dangerous Port Exposed: ${port}`,
                                line: lineNum,
                                code: line,
                                description: `Port ${port} is commonly associated with administrative or insecure protocols.`,
                                remediation: 'Remove EXPOSE for administrative ports. Use SSH tunneling if remote access is needed.'
                            });
                            score -= 15;
                        }
                    });
                }

                // Check for COPY without --chown
                if (trimmed.toUpperCase().startsWith('COPY')) {
                    if (!trimmed.includes('--chown') && hasUser) {
                        issues.push({
                            severity: 'low',
                            title: 'COPY Without --chown',
                            line: lineNum,
                            code: line,
                            description: 'Files copied will be owned by root, requiring additional RUN chown commands.',
                            remediation: 'Use COPY --chown=user:group to set ownership during copy.'
                        });
                        score -= 3;
                    }
                }

                // Check for RUN with dangerous commands
                if (trimmed.toUpperCase().startsWith('RUN')) {
                    // Check for curl | bash pattern
                    if (/curl.*\|.*bash|wget.*\|.*sh/i.test(trimmed)) {
                        issues.push({
                            severity: 'critical',
                            title: 'Piping to Shell',
                            line: lineNum,
                            code: line,
                            description: 'Downloading and executing scripts directly is extremely dangerous.',
                            remediation: 'Download script first, verify integrity (checksum), then execute.'
                        });
                        score -= 20;
                    }

                    // Check for apt-get without -y or --no-install-recommends
                    if (trimmed.includes('apt-get install') && !trimmed.includes('-y')) {
                        issues.push({
                            severity: 'low',
                            title: 'apt-get Without -y Flag',
                            line: lineNum,
                            code: line,
                            description: 'Interactive prompts will cause build failures.',
                            remediation: 'Add -y flag: apt-get install -y package-name'
                        });
                        score -= 2;
                    }

                    // Check for missing cleanup
                    if (trimmed.includes('apt-get') && !trimmed.includes('rm -rf /var/lib/apt/lists')) {
                        issues.push({
                            severity: 'low',
                            title: 'Missing apt-get Cleanup',
                            line: lineNum,
                            code: line,
                            description: 'apt cache not cleaned, unnecessarily increasing image size.',
                            remediation: 'Add cleanup: && rm -rf /var/lib/apt/lists/*'
                        });
                        score -= 2;
                    }

                    // Check for multiple RUN commands that should be combined
                    if (index > 0 && lines[index - 1].trim().toUpperCase().startsWith('RUN')) {
                        issues.push({
                            severity: 'info',
                            title: 'Multiple RUN Commands',
                            line: lineNum,
                            code: line,
                            description: 'Consecutive RUN commands create additional layers.',
                            remediation: 'Combine RUN commands with && to reduce layers and image size.'
                        });
                        score -= 1;
                    }
                }

                // Check for ADD vs COPY
                if (trimmed.toUpperCase().startsWith('ADD')) {
                    if (!trimmed.match(/\.(tar|tar\.gz|tgz|tar\.bz2)$/)) {
                        issues.push({
                            severity: 'low',
                            title: 'Using ADD Instead of COPY',
                            line: lineNum,
                            code: line,
                            description: 'ADD has implicit behavior (auto-extraction). COPY is more explicit.',
                            remediation: 'Use COPY unless you need ADD\'s auto-extraction feature.'
                        });
                        score -= 2;
                    }
                }

                // Check for WORKDIR
                if (trimmed.toUpperCase().startsWith('WORKDIR') && trimmed.includes('cd ')) {
                    issues.push({
                        severity: 'low',
                        title: 'Using cd in WORKDIR',
                        line: lineNum,
                        code: line,
                        description: 'cd commands don\'t persist between RUN instructions.',
                        remediation: 'Use WORKDIR /path instead of RUN cd /path'
                    });
                    score -= 2;
                }
            });

            // Check for missing USER instruction
            if (!hasUser) {
                issues.push({
                    severity: 'high',
                    title: 'No Non-Root User Specified',
                    line: 0,
                    code: '',
                    description: 'Container will run as root by default, which is a security risk.',
                    remediation: 'Create a non-root user:\nRUN groupadd -r appuser && useradd -r -g appuser appuser\nUSER appuser'
                });
                score -= 15;
            }

            // Check for missing HEALTHCHECK
            if (!hasHealthcheck) {
                issues.push({
                    severity: 'medium',
                    title: 'No HEALTHCHECK Defined',
                    line: 0,
                    code: '',
                    description: 'Container health cannot be monitored by orchestration systems.',
                    remediation: 'Add HEALTHCHECK:\nHEALTHCHECK --interval=30s --timeout=3s \\\n  CMD curl -f http://localhost:8000/health || exit 1'
                });
                score -= 5;
            }

            // Check for multi-stage build
            if (baseImages.length === 1 && content.includes('build') && !hasMultiStage) {
                issues.push({
                    severity: 'info',
                    title: 'Consider Multi-Stage Build',
                    line: 0,
                    code: '',
                    description: 'Multi-stage builds can significantly reduce final image size.',
                    remediation: 'Use multi-stage builds to separate build and runtime dependencies.'
                });
                score -= 2;
            }

            // Ensure score doesn't go below 0
            score = Math.max(0, score);

            // Sort issues by severity
            const severityOrder = { critical: 0, high: 1, medium: 2, low: 3, info: 4 };
            issues.sort((a, b) => severityOrder[a.severity] - severityOrder[b.severity]);

            return {
                score,
                issues,
                stats: {
                    total: issues.length,
                    critical: issues.filter(i => i.severity === 'critical').length,
                    high: issues.filter(i => i.severity === 'high').length,
                    medium: issues.filter(i => i.severity === 'medium').length,
                    low: issues.filter(i => i.severity === 'low').length,
                    info: issues.filter(i => i.severity === 'info').length
                },
                metadata: {
                    lines: lines.length,
                    baseImages,
                    hasMultiStage,
                    hasHealthcheck,
                    hasUser
                }
            };
        }

        function displayResults(data) {
            // Display score
            let scoreClass = 'score-poor';
            let scoreLabel = 'Poor';
            if (data.score >= 90) {
                scoreClass = 'score-excellent';
                scoreLabel = 'Excellent';
            } else if (data.score >= 75) {
                scoreClass = 'score-good';
                scoreLabel = 'Good';
            } else if (data.score >= 50) {
                scoreClass = 'score-fair';
                scoreLabel = 'Fair';
            }

            document.getElementById('score-display').innerHTML = `
                <div class="score-circle ${scoreClass}">
                    ${data.score}
                </div>
                <p style="text-align: center; margin-top: 1rem; font-size: 1.2rem; color: var(--text-secondary);">
                    ${scoreLabel} Security Score
                </p>
            `;

            // Display stats
            const statsHtml = `
                <div class="stat-box">
                    <span class="stat-number" style="color: var(--error-color);">${data.stats.critical}</span>
                    <span class="stat-label">Critical</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number" style="color: #ea580c;">${data.stats.high}</span>
                    <span class="stat-label">High</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number" style="color: #ca8a04;">${data.stats.medium}</span>
                    <span class="stat-label">Medium</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number" style="color: var(--success-color);">${data.stats.low}</span>
                    <span class="stat-label">Low</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number" style="color: #0284c7;">${data.stats.info}</span>
                    <span class="stat-label">Info</span>
                </div>
            `;
            document.getElementById('stats-display').innerHTML = statsHtml;

            // Display issues
            if (data.issues.length === 0) {
                document.getElementById('issues-display').innerHTML = `
                    <div style="text-align: center; padding: 2rem; color: var(--success-color);">
                        <div style="font-size: 4rem;">✓</div>
                        <h3 style="margin-top: 1rem;">No Issues Found!</h3>
                        <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                            Your Dockerfile follows security best practices.
                        </p>
                    </div>
                `;
            } else {
                let issuesHtml = '';
                data.issues.forEach(issue => {
                    issuesHtml += `
                        <div class="issue-card ${issue.severity}">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                                <h3 style="margin: 0; color: var(--text-primary);">${issue.title}</h3>
                                <span class="severity-badge severity-${issue.severity}">${issue.severity}</span>
                            </div>
                            ${issue.line > 0 ? `<p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Line ${issue.line}</p>` : ''}
                            ${issue.code ? `<div class="code-snippet">${escapeHtml(issue.code)}</div>` : ''}
                            <p style="margin: 0.75rem 0; color: var(--text-secondary);">${issue.description}</p>
                            <div style="background: rgba(0, 123, 255, 0.1); padding: 0.75rem; border-radius: 4px; border-left: 3px solid var(--primary-color);">
                                <strong style="color: var(--primary-color);">Remediation:</strong>
                                <p style="margin: 0.5rem 0 0 0; font-family: 'Courier New', monospace; font-size: 0.9rem; white-space: pre-wrap;">${escapeHtml(issue.remediation)}</p>
                            </div>
                        </div>
                    `;
                });
                document.getElementById('issues-display').innerHTML = issuesHtml;
            }

            // Display recommendations
            const recommendations = generateRecommendations(data);
            let recsHtml = '<ul style="line-height: 1.8;">';
            recommendations.forEach(rec => {
                recsHtml += `<li style="margin-bottom: 0.5rem;">${rec}</li>`;
            });
            recsHtml += '</ul>';
            document.getElementById('recommendations-display').innerHTML = recsHtml;

            // Show results section
            document.getElementById('results').style.display = 'block';
            document.getElementById('results').scrollIntoView({ behavior: 'smooth' });
        }

        function generateRecommendations(data) {
            const recs = [];

            if (data.stats.critical > 0) {
                recs.push('<strong>Critical issues detected!</strong> Address hardcoded secrets and dangerous practices immediately.');
            }

            if (!data.metadata.hasMultiStage) {
                recs.push('Consider using <strong>multi-stage builds</strong> to reduce final image size and improve security.');
            }

            if (!data.metadata.hasHealthcheck) {
                recs.push('Add a <strong>HEALTHCHECK</strong> instruction for better container monitoring and orchestration.');
            }

            if (!data.metadata.hasUser) {
                recs.push('Always run containers as a <strong>non-root user</strong> to minimize security risks.');
            }

            recs.push('Pin base image versions to specific tags (e.g., <code>python:3.11-slim</code>) for reproducible builds.');
            recs.push('Use <code>.dockerignore</code> files to exclude unnecessary files from the build context.');
            recs.push('Minimize layers by combining RUN commands with <code>&&</code>.');
            recs.push('Scan images regularly with tools like <strong>Trivy</strong>, <strong>Snyk</strong>, or <strong>Grype</strong>.');
            recs.push('Consider using <strong>distroless</strong> base images for minimal attack surface.');
            recs.push('Implement <strong>least privilege</strong> by only installing required packages.');

            return recs;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
