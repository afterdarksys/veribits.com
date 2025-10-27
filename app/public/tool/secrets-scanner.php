<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secrets Scanner - VeriBits</title>
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
            <h1 style="font-size: 3rem; margin-bottom: 1rem;">Secrets Scanner</h1>
            <p style="color: var(--text-secondary); margin-bottom: 3rem; font-size: 1.2rem;">
                Detect hardcoded secrets, API keys, passwords, AWS credentials, GitHub tokens, and private keys in your code.
            </p>

            <div id="alert-container"></div>

            <div class="feature-card" style="margin-bottom: 2rem;">
                <h2 style="margin-bottom: 1rem;">Scan for Secrets</h2>
                <form id="secrets-form">
                    <div class="form-group">
                        <label for="content">Code/Config Content *</label>
                        <textarea
                            id="content"
                            name="content"
                            rows="15"
                            required
                            placeholder="Paste your code, config files, or any text to scan for secrets..."
                            style="font-family: 'Courier New', monospace; font-size: 0.9rem;"
                        ></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" id="scan-btn">
                        Scan for Secrets
                    </button>
                </form>
            </div>

            <div id="results" style="display: none;">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Scan Results</h2>
                    <div id="results-content"></div>
                    <div id="recommendations" style="margin-top: 2rem;"></div>
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
        document.getElementById('secrets-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const content = document.getElementById('content').value;
            const scanBtn = document.getElementById('scan-btn');

            scanBtn.textContent = 'Scanning...';
            scanBtn.disabled = true;

            try {
                const response = await fetch('/api/v1/security/secrets/scan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        ...(getAuthToken() ? {'Authorization': `Bearer ${getAuthToken()}`} : {})
                    },
                    body: JSON.stringify({ content })
                });

                const result = await response.json();
                if (result.success) {
                    displayResults(result.data);
                } else {
                    showAlert(result.error?.message || 'Scan failed', 'error');
                }
            } catch (error) {
                showAlert('Network error: ' + error.message, 'error');
            } finally {
                scanBtn.textContent = 'Scan for Secrets';
                scanBtn.disabled = false;
            }
        });

        function displayResults(data) {
            const resultsDiv = document.getElementById('results');
            const resultsContent = document.getElementById('results-content');

            if (data.secrets_found === 0) {
                resultsContent.innerHTML = '<p style="color: var(--success-color); font-size: 1.2rem;">✓ No secrets detected!</p>';
            } else {
                let html = `<p style="color: var(--error-color); font-size: 1.2rem; margin-bottom: 1.5rem;">⚠️ Found ${data.secrets_found} secret(s)</p>`;
                html += '<div style="display: grid; gap: 1rem;">';
                
                data.secrets.forEach(secret => {
                    const severityColors = {'critical': '#ef4444', 'high': '#f97316', 'medium': '#eab308', 'low': '#22c55e'};
                    html += `
                        <div style="padding: 1rem; background: rgba(255,255,255,0.05); border-left: 3px solid ${severityColors[secret.severity]}; border-radius: 4px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <strong>${secret.name}</strong>
                                <span style="color: ${severityColors[secret.severity]}; font-size: 0.85rem; text-transform: uppercase;">${secret.severity}</span>
                            </div>
                            <p style="font-size: 0.9rem; color: var(--text-secondary);">Line ${secret.line} | Type: ${secret.type}</p>
                            <p style="font-size: 0.85rem; font-family: monospace; background: rgba(0,0,0,0.3); padding: 0.5rem; border-radius: 3px; margin-top: 0.5rem;">${secret.value}</p>
                        </div>
                    `;
                });
                html += '</div>';
                resultsContent.innerHTML = html;
            }

            if (data.recommendations) {
                let recsHtml = '<h3>Recommendations</h3><ul>';
                data.recommendations.forEach(rec => {
                    recsHtml += `<li style="padding: 0.5rem 0;">${rec}</li>`;
                });
                recsHtml += '</ul>';
                document.getElementById('recommendations').innerHTML = recsHtml;
            }

            resultsDiv.style.display = 'block';
            resultsDiv.scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>
