<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DNS Propagation Checker - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
    <style>
        .server-result {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--dark-bg);
            border-radius: 4px;
            margin-bottom: 0.75rem;
        }
        .server-flag {
            font-size: 2rem;
            min-width: 3rem;
            text-align: center;
        }
        .server-info {
            flex: 1;
        }
        .server-status {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        .status-success { background: var(--success-color); }
        .status-error { background: var(--error-color); }
        .status-warning { background: var(--warning-color); }
        .world-map {
            width: 100%;
            height: 300px;
            background: var(--darker-bg);
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
        }
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
        <div class="container" style="max-width: 1100px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">DNS Propagation Checker</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Check DNS propagation across global nameservers in real-time
            </p>

            <div id="alert-container"></div>

            <!-- Input Section -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">DNS Lookup Configuration</h2>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label for="domain">Domain Name</label>
                        <input type="text" id="domain" placeholder="example.com"
                            style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                    </div>

                    <div class="form-group">
                        <label for="record-type">Record Type</label>
                        <select id="record-type"
                            style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                            <option value="A">A</option>
                            <option value="AAAA">AAAA</option>
                            <option value="CNAME">CNAME</option>
                            <option value="MX">MX</option>
                            <option value="TXT">TXT</option>
                            <option value="NS">NS</option>
                        </select>
                    </div>
                </div>

                <button class="btn btn-primary" id="check-button" style="margin-top: 1rem; width: 100%;">
                    Check Propagation
                </button>
            </div>

            <!-- Results Section -->
            <div class="feature-card" id="results-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Propagation Results</h2>

                <div class="world-map">
                    <span>Checking servers worldwide...</span>
                </div>

                <div id="summary-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;"></div>

                <div id="results-content"></div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2025 VeriBits by After Dark Systems. All rights reserved.</p>
        </div>
    </footer>

    <script src="/assets/js/main.js?v=<?= time() ?>"></script>
    <script>
        const checkButton = document.getElementById('check-button');
        const resultsSection = document.getElementById('results-section');

        checkButton.addEventListener('click', async () => {
            const domain = document.getElementById('domain').value.trim();
            const recordType = document.getElementById('record-type').value;

            if (!domain) {
                showAlert('Please enter a domain name', 'error');
                return;
            }

            resultsSection.style.display = 'block';
            document.getElementById('results-content').innerHTML = '<div class="spinner"></div>';

            try {
                const response = await fetch('/api/v1/tools/dns-propagation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: JSON.stringify({ domain, record_type: recordType })
                });

                const data = await response.json();

                if (!response.ok) {
                    const errorMsg = data.error?.message || data.error || data.message || 'Check failed';
                    throw new Error(errorMsg);
                }

                displayResults(data.data);
            } catch (error) {
                const errorMessage = error.message || 'An unknown error occurred';
                document.getElementById('results-content').innerHTML =
                    `<div class="alert alert-error">${errorMessage}</div>`;
            }
        });

        function displayResults(data) {
            const servers = data.servers || [];
            const successCount = servers.filter(s => s.status === 'success').length;
            const failCount = servers.filter(s => s.status === 'error').length;
            const totalCount = servers.length;

            // Summary stats
            const summaryHtml = `
                <div style="padding: 1rem; background: var(--darker-bg); border-radius: 8px; text-align: center;">
                    <div style="font-size: 2rem; color: var(--success-color); font-weight: bold;">${successCount}</div>
                    <div style="color: var(--text-secondary);">Successful</div>
                </div>
                <div style="padding: 1rem; background: var(--darker-bg); border-radius: 8px; text-align: center;">
                    <div style="font-size: 2rem; color: var(--error-color); font-weight: bold;">${failCount}</div>
                    <div style="color: var(--text-secondary);">Failed</div>
                </div>
                <div style="padding: 1rem; background: var(--darker-bg); border-radius: 8px; text-align: center;">
                    <div style="font-size: 2rem; color: var(--primary-color); font-weight: bold;">${Math.round((successCount/totalCount) * 100)}%</div>
                    <div style="color: var(--text-secondary);">Propagation</div>
                </div>
            `;
            document.getElementById('summary-stats').innerHTML = summaryHtml;

            // Server results
            let html = '<div style="display: grid; gap: 0.75rem;">';

            servers.forEach(server => {
                const statusClass = server.status === 'success' ? 'status-success' : 'status-error';
                const resultValue = server.result ? (Array.isArray(server.result) ? server.result.join(', ') : server.result) : 'No records found';

                html += `
                    <div class="server-result">
                        <div class="server-flag">${server.flag || '🌐'}</div>
                        <div class="server-info">
                            <div style="display: flex; align-items: center; margin-bottom: 0.25rem;">
                                <span class="server-status ${statusClass}"></span>
                                <strong>${server.location}</strong>
                                <span style="margin-left: auto; color: var(--text-secondary); font-size: 0.9rem;">${server.query_time || 'N/A'}</span>
                            </div>
                            <div style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.25rem;">${server.server}</div>
                            ${server.status === 'success'
                                ? `<div style="color: var(--success-color); font-size: 0.9rem;"><code>${resultValue}</code></div>`
                                : `<div style="color: var(--error-color); font-size: 0.9rem;">${server.error || 'Query failed'}</div>`
                            }
                        </div>
                    </div>
                `;
            });

            html += '</div>';

            html += `
                <div style="margin-top: 1.5rem; text-align: center;">
                    <button class="btn btn-primary" onclick="location.reload()">Check Another Domain</button>
                </div>
            `;

            document.getElementById('results-content').innerHTML = html;
        }

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            alertContainer.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }
    </script>
</body>
</html>
