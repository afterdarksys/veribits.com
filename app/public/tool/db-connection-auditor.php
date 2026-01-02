<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection String Auditor - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
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
        <div class="container" style="max-width: 1200px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem;">Database Connection String Auditor</h1>
            <p style="color: var(--text-secondary); margin-bottom: 3rem; font-size: 1.2rem;">
                Audit database connection strings for security issues: plaintext passwords, disabled SSL, public IPs, default ports.
            </p>

            <div id="alert-container"></div>

            <div class="feature-card" style="margin-bottom: 2rem;">
                <h2 style="margin-bottom: 1rem;">Audit Connection String</h2>
                <form id="db-audit-form">
                    <div class="form-group">
                        <label for="connection-string">Connection String *</label>
                        <input
                            type="text"
                            id="connection-string"
                            name="connection-string"
                            required
                            placeholder="postgresql://user:password@host:5432/database"
                            style="font-family: 'Courier New', monospace;"
                        >
                        <small style="color: var(--text-secondary); display: block; margin-top: 0.5rem;">
                            Supports: PostgreSQL, MySQL, MongoDB, Redis, MS SQL
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary" id="audit-btn">
                        Audit Connection String
                    </button>
                </form>
            </div>

            <div id="results" style="display: none;">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Audit Results</h2>
                    <div id="results-content"></div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2025 VeriBits. All rights reserved.</p>
        </div>
    </footer>

    <script src="/assets/js/main.js?v=<?= time() ?>"></script>
    <script>
        document.getElementById('db-audit-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const connString = document.getElementById('connection-string').value;
            const auditBtn = document.getElementById('audit-btn');

            auditBtn.textContent = 'Auditing...';
            auditBtn.disabled = true;

            try {
                const response = await fetch('/api/v1/security/db-connection/audit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        ...(getAuthToken() ? {'Authorization': `Bearer ${getAuthToken()}`} : {})
                    },
                    body: JSON.stringify({ connection_string: connString })
                });

                const result = await response.json();
                if (result.success) {
                    displayResults(result.data);
                } else {
                    showAlert(result.error?.message || 'Audit failed', 'error');
                }
            } catch (error) {
                showAlert('Network error: ' + error.message, 'error');
            } finally {
                auditBtn.textContent = 'Audit Connection String';
                auditBtn.disabled = false;
            }
        });

        function displayResults(data) {
            const resultsDiv = document.getElementById('results');
            const resultsContent = document.getElementById('results-content');

            let html = `
                <p><strong>Database Type:</strong> ${data.db_type}</p>
                <p><strong>Risk Score:</strong> <span style="color: ${data.risk_score > 50 ? '#ef4444' : '#22c55e'}">${data.risk_score}/100</span></p>
                <p><strong>Risk Level:</strong> <span style="text-transform: uppercase;">${data.risk_level}</span></p>
                <hr style="margin: 1.5rem 0;">
            `;

            if (data.issues.length === 0) {
                html += '<p style="color: var(--success-color);">✓ No security issues found!</p>';
            } else {
                html += '<h3>Issues Found</h3><div style="display: grid; gap: 1rem;">';
                data.issues.forEach(issue => {
                    const severityColors = {'critical': '#ef4444', 'high': '#f97316', 'medium': '#eab308', 'low': '#22c55e'};
                    html += `
                        <div style="padding: 1rem; background: rgba(255,255,255,0.05); border-left: 3px solid ${severityColors[issue.severity]}; border-radius: 4px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <strong>${issue.issue}</strong>
                                <span style="color: ${severityColors[issue.severity]}; font-size: 0.85rem; text-transform: uppercase;">${issue.severity}</span>
                            </div>
                            <p style="font-size: 0.9rem; color: var(--text-secondary);">💡 ${issue.recommendation}</p>
                        </div>
                    `;
                });
                html += '</div>';
            }

            if (data.recommendations && data.recommendations.length > 0) {
                html += '<h3 style="margin-top: 2rem;">Recommendations</h3><ul>';
                data.recommendations.forEach(rec => {
                    html += `<li style="padding: 0.5rem 0;">${rec}</li>`;
                });
                html += '</ul>';
            }

            if (data.secure_alternative) {
                html += `<h3 style="margin-top: 2rem;">Secure Alternative</h3>`;
                html += `<pre style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 4px; overflow-x: auto;">${data.secure_alternative}</pre>`;
            }

            resultsContent.innerHTML = html;
            resultsDiv.style.display = 'block';
            resultsDiv.scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>
