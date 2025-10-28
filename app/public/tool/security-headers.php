<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Headers Analyzer - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
    <style>
        .score-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 2rem;
            font-weight: bold;
            margin: 1rem 0;
        }
        .grade-a { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .grade-b { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .grade-c { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #333; }
        .grade-d { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); color: #333; }
        .grade-f { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; }

        .header-card {
            background: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--border-color);
        }
        .header-card.good { border-left-color: var(--success-color); }
        .header-card.warning { border-left-color: #f59e0b; }
        .header-card.missing { border-left-color: var(--error-color); }

        .severity-high { color: var(--error-color); }
        .severity-medium { color: #f59e0b; }
        .severity-low { color: #3b82f6; }
    </style>
</head>
<body>
    <nav>
        <div class="container">
            <a href="/" class="logo">VeriBits</a>
            <ul>
                <li><a href="/tools.php">Tools</a></li>
                <li><a href="/pricing.php">Pricing</a></li>
                <li><a href="/about.php">About</a></li>
                <li data-auth-item="true"><a href="/login.php">Login</a></li>
                <li data-auth-item="true"><a href="/signup.php" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <section style="padding: 8rem 2rem 4rem;">
        <div class="container" style="max-width: 1200px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Security Headers Analyzer</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Analyze HTTP security headers to identify vulnerabilities and improve web security
            </p>

            <div id="alert-container"></div>

            <!-- Input Section -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Enter URL to Analyze</h2>

                <div class="form-group">
                    <label for="url">Website URL</label>
                    <input type="url" id="url" placeholder="https://example.com" style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                </div>

                <button class="btn btn-primary" id="analyze-button" onclick="analyzeHeaders()" style="width: 100%;">
                    Analyze Security Headers
                </button>
            </div>

            <!-- Score Section -->
            <div class="feature-card" id="score-section" style="display: none; margin-top: 2rem; text-align: center;">
                <h2 style="margin-bottom: 1rem;">Security Score</h2>
                <div id="score-badge"></div>
                <p id="score-message" style="color: var(--text-secondary); margin-top: 1rem;"></p>
            </div>

            <!-- Headers Analysis -->
            <div class="feature-card" id="analysis-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Header Analysis</h2>
                <div id="headers-content"></div>
            </div>

            <!-- Recommendations -->
            <div class="feature-card" id="recommendations-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Recommendations</h2>
                <div id="recommendations-content"></div>
            </div>

            <!-- Information Section -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">About Security Headers</h2>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Critical Headers</h3>
                <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                    <li><strong>Strict-Transport-Security (HSTS):</strong> Forces HTTPS connections</li>
                    <li><strong>Content-Security-Policy (CSP):</strong> Prevents XSS and injection attacks</li>
                    <li><strong>X-Frame-Options:</strong> Protects against clickjacking</li>
                    <li><strong>X-Content-Type-Options:</strong> Prevents MIME-type sniffing</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Recommended Headers</h3>
                <ul style="margin-left: 1.5rem;">
                    <li><strong>Referrer-Policy:</strong> Controls referrer information</li>
                    <li><strong>Permissions-Policy:</strong> Controls browser features</li>
                    <li><strong>Cross-Origin headers:</strong> Isolates browsing contexts</li>
                </ul>
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
        async function analyzeHeaders() {
            const url = document.getElementById('url').value.trim();

            if (!url) {
                showAlert('Please enter a URL to analyze', 'error');
                return;
            }

            const analyzeButton = document.getElementById('analyze-button');
            analyzeButton.disabled = true;
            analyzeButton.textContent = 'Analyzing...';

            const scoreSection = document.getElementById('score-section');
            const analysisSection = document.getElementById('analysis-section');
            const recommendationsSection = document.getElementById('recommendations-section');

            scoreSection.style.display = 'block';
            analysisSection.style.display = 'block';
            recommendationsSection.style.display = 'block';

            document.getElementById('score-badge').innerHTML = '<div class="spinner" style="margin: 0 auto;"></div>';
            document.getElementById('headers-content').innerHTML = '<div class="spinner"></div>';
            document.getElementById('recommendations-content').innerHTML = '<div class="spinner"></div>';

            try {
                const data = await apiRequest('/api/v1/tools/security-headers', {
                    method: 'POST',
                    body: JSON.stringify({ url })
                });

                displayResults(data.data);
            } catch (error) {
                document.getElementById('headers-content').innerHTML = `<div class="alert alert-error">${error.message}</div>`;
                scoreSection.style.display = 'none';
                recommendationsSection.style.display = 'none';
            } finally {
                analyzeButton.disabled = false;
                analyzeButton.textContent = 'Analyze Security Headers';
            }
        }

        function displayResults(result) {
            // Display score
            const scoreBadge = document.getElementById('score-badge');
            const gradeClass = `grade-${result.grade.toLowerCase()}`;
            scoreBadge.innerHTML = `
                <div class="score-badge ${gradeClass}">
                    ${result.score}/100 (Grade ${result.grade})
                </div>
            `;

            const scoreMessage = document.getElementById('score-message');
            if (result.score >= 90) {
                scoreMessage.textContent = 'Excellent! Your security headers are well configured.';
            } else if (result.score >= 70) {
                scoreMessage.textContent = 'Good, but there is room for improvement.';
            } else if (result.score >= 50) {
                scoreMessage.textContent = 'Fair. Several security improvements needed.';
            } else {
                scoreMessage.textContent = 'Poor. Critical security headers are missing.';
            }

            // Display header analysis
            let headersHtml = '';
            const headerNames = {
                'hsts': 'Strict-Transport-Security',
                'csp': 'Content-Security-Policy',
                'x_frame_options': 'X-Frame-Options',
                'x_content_type_options': 'X-Content-Type-Options',
                'x_xss_protection': 'X-XSS-Protection',
                'referrer_policy': 'Referrer-Policy',
                'permissions_policy': 'Permissions-Policy',
                'cross_origin_embedder_policy': 'Cross-Origin-Embedder-Policy',
                'cross_origin_opener_policy': 'Cross-Origin-Opener-Policy',
                'cross_origin_resource_policy': 'Cross-Origin-Resource-Policy'
            };

            for (const [key, name] of Object.entries(headerNames)) {
                const header = result.analysis[key];
                if (!header) continue;

                const statusClass = header.status || 'missing';
                const icon = statusClass === 'good' ? '✅' : statusClass === 'warning' ? '⚠️' : '❌';

                headersHtml += `
                    <div class="header-card ${statusClass}">
                        <h3 style="margin-bottom: 0.5rem;">${icon} ${name}</h3>
                        ${header.present ? `
                            <p><strong>Value:</strong> <code style="word-break: break-all;">${header.value}</code></p>
                        ` : `
                            <p style="color: var(--error-color);">Header is missing</p>
                        `}
                        ${header.message ? `<p style="margin-top: 0.5rem;">${header.message}</p>` : ''}
                        ${header.issues && header.issues.length > 0 ? `
                            <ul style="margin-top: 0.5rem; margin-left: 1.5rem;">
                                ${header.issues.map(issue => `<li>${issue}</li>`).join('')}
                            </ul>
                        ` : ''}
                    </div>
                `;
            }

            document.getElementById('headers-content').innerHTML = headersHtml;

            // Display recommendations
            if (result.recommendations && result.recommendations.length > 0) {
                let recommendationsHtml = '<div style="display: grid; gap: 1rem;">';

                result.recommendations.forEach(rec => {
                    const severityClass = `severity-${rec.severity}`;
                    recommendationsHtml += `
                        <div style="padding: 1rem; background: var(--darker-bg); border-radius: 8px; border-left: 3px solid var(--border-color);">
                            <h4 class="${severityClass}" style="margin-bottom: 0.5rem;">
                                ${rec.severity === 'high' ? '🔴' : rec.severity === 'medium' ? '🟡' : '🔵'}
                                ${rec.header.replace(/_/g, '-')}
                            </h4>
                            <p>${rec.message}</p>
                            ${rec.example ? `
                                <p style="margin-top: 0.5rem;"><strong>Example:</strong></p>
                                <code style="display: block; background: var(--dark-bg); padding: 0.5rem; border-radius: 4px; margin-top: 0.25rem;">${rec.example}</code>
                            ` : ''}
                        </div>
                    `;
                });

                recommendationsHtml += '</div>';
                document.getElementById('recommendations-content').innerHTML = recommendationsHtml;
            } else {
                document.getElementById('recommendations-content').innerHTML = `
                    <p style="text-align: center; color: var(--success-color); padding: 2rem;">
                        ✅ No recommendations! All security headers are properly configured.
                    </p>
                `;
            }
        }

        // Allow Enter key to submit
        document.getElementById('url').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                analyzeHeaders();
            }
        });
    </script>
</body>
</html>
