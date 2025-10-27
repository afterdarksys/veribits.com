<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AWS IAM Policy Analyzer - VeriBits</title>
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
        <div class="container" style="max-width: 1200px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem;">AWS IAM Policy Analyzer</h1>
            <p style="color: var(--text-secondary); margin-bottom: 3rem; font-size: 1.2rem;">
                Analyze AWS IAM policies for security risks, overpermissive wildcards, and public access. Get actionable recommendations with 0-100 risk scoring.
            </p>

            <div id="alert-container"></div>

            <div class="feature-card" style="margin-bottom: 2rem;">
                <h2 style="margin-bottom: 1rem;">Analyze IAM Policy</h2>
                <form id="iam-policy-form">
                    <div class="form-group">
                        <label for="policy-name">Policy Name (Optional)</label>
                        <input type="text" id="policy-name" name="policy-name" placeholder="My-Custom-Policy">
                    </div>

                    <div class="form-group">
                        <label for="policy-document">IAM Policy Document (JSON) *</label>
                        <textarea
                            id="policy-document"
                            name="policy-document"
                            rows="15"
                            required
                            placeholder='{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": "*",
      "Resource": "*"
    }
  ]
}'
                            style="font-family: 'Courier New', monospace; font-size: 0.9rem;"
                        ></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" id="analyze-btn">
                        Analyze Policy
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="loadExample()" style="margin-left: 1rem;">
                        Load Example
                    </button>
                </form>
            </div>

            <div id="results" style="display: none;">
                <div class="feature-card" style="margin-bottom: 2rem;">
                    <h2 style="margin-bottom: 1.5rem;">Analysis Results</h2>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                        <div class="stat-card">
                            <div class="stat-value" id="risk-score">--</div>
                            <div class="stat-label">Risk Score</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" id="risk-level">--</div>
                            <div class="stat-label">Risk Level</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" id="findings-count">--</div>
                            <div class="stat-label">Findings</div>
                        </div>
                    </div>

                    <div id="findings-list"></div>
                    <div id="recommendations-list" style="margin-top: 2rem;"></div>
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

    <script src="/assets/js/main.js"></script>
    <script>
        const form = document.getElementById('iam-policy-form');
        const analyzeBtn = document.getElementById('analyze-btn');
        const resultsDiv = document.getElementById('results');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const policyName = document.getElementById('policy-name').value || 'Unnamed Policy';
            const policyDocument = document.getElementById('policy-document').value.trim();

            if (!policyDocument) {
                showAlert('Please enter an IAM policy document', 'error');
                return;
            }

            // Validate JSON
            try {
                JSON.parse(policyDocument);
            } catch (err) {
                showAlert('Invalid JSON format: ' + err.message, 'error');
                return;
            }

            analyzeBtn.textContent = 'Analyzing...';
            analyzeBtn.disabled = true;

            try {
                const response = await fetch('/api/v1/security/iam-policy/analyze', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        ...(getAuthToken() ? {'Authorization': `Bearer ${getAuthToken()}`} : {})
                    },
                    body: JSON.stringify({
                        policy_name: policyName,
                        policy_document: policyDocument
                    })
                });

                const result = await response.json();

                if (result.success) {
                    displayResults(result.data);
                } else {
                    showAlert(result.error?.message || 'Analysis failed', 'error');
                }
            } catch (error) {
                showAlert('Network error: ' + error.message, 'error');
            } finally {
                analyzeBtn.textContent = 'Analyze Policy';
                analyzeBtn.disabled = false;
            }
        });

        function displayResults(data) {
            // Update stats
            document.getElementById('risk-score').textContent = data.risk_score;
            document.getElementById('risk-level').textContent = data.risk_level.toUpperCase();
            document.getElementById('findings-count').textContent = data.findings.length;

            // Color code risk level
            const riskLevelEl = document.getElementById('risk-level');
            const colors = {
                'critical': '#ef4444',
                'high': '#f97316',
                'medium': '#eab308',
                'low': '#22c55e'
            };
            riskLevelEl.style.color = colors[data.risk_level] || '#9ca3af';

            // Display findings
            const findingsList = document.getElementById('findings-list');
            if (data.findings.length === 0) {
                findingsList.innerHTML = '<p style="color: var(--success-color);">✓ No security issues found!</p>';
            } else {
                let html = '<h3 style="margin-bottom: 1rem;">Security Findings</h3>';
                html += '<div style="display: grid; gap: 1rem;">';

                data.findings.forEach((finding, index) => {
                    const severityColors = {
                        'critical': '#ef4444',
                        'high': '#f97316',
                        'medium': '#eab308',
                        'low': '#22c55e'
                    };

                    html += `
                        <div style="padding: 1rem; background: rgba(255,255,255,0.05); border-left: 3px solid ${severityColors[finding.severity]}; border-radius: 4px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <strong>${finding.issue}</strong>
                                <span style="color: ${severityColors[finding.severity]}; font-size: 0.85rem; text-transform: uppercase;">${finding.severity}</span>
                            </div>
                            ${finding.statement_index !== undefined ? `<p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Statement #${finding.statement_index}</p>` : ''}
                            <p style="font-size: 0.9rem; color: var(--text-secondary);">💡 ${finding.recommendation}</p>
                        </div>
                    `;
                });

                html += '</div>';
                findingsList.innerHTML = html;
            }

            // Display recommendations
            const recommendationsList = document.getElementById('recommendations-list');
            if (data.recommendations && data.recommendations.length > 0) {
                let html = '<h3 style="margin-bottom: 1rem;">Recommendations</h3>';
                html += '<ul style="list-style: none; padding: 0;">';
                data.recommendations.forEach(rec => {
                    html += `<li style="padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">✓ ${rec}</li>`;
                });
                html += '</ul>';
                recommendationsList.innerHTML = html;
            }

            resultsDiv.style.display = 'block';
            resultsDiv.scrollIntoView({ behavior: 'smooth' });
        }

        function loadExample() {
            const examplePolicy = {
                "Version": "2012-10-17",
                "Statement": [
                    {
                        "Effect": "Allow",
                        "Action": "*",
                        "Resource": "*"
                    }
                ]
            };
            document.getElementById('policy-name').value = 'Admin Access Example';
            document.getElementById('policy-document').value = JSON.stringify(examplePolicy, null, 2);
        }
    </script>
</body>
</html>
