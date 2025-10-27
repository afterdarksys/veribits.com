<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reverse DNS Lookup - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <nav>
        <div class="container">
            <a href="/" class="logo">VeriBits</a>
            <ul>
                <li><a href="/tools.php">Tools</a></li>
                <li><a href="/pricing.php">Pricing</a></li>
                <li><a href="/about.php">About</a></li>
                <li><a href="/login.php">Login</a></li>
                <li><a href="/signup.php" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <section style="padding: 8rem 2rem 4rem;">
        <div class="container" style="max-width: 900px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Reverse DNS Lookup</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Perform PTR record lookups and validate forward DNS resolution
            </p>

            <div id="alert-container"></div>

            <!-- Input Section -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">IP Address Lookup</h2>

                <div class="form-group">
                    <label for="ip-address">IP Address (single or bulk)</label>
                    <textarea id="ip-address" placeholder="8.8.8.8&#10;1.1.1.1&#10;208.67.222.222" rows="5"
                        style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;"></textarea>
                    <small style="color: var(--text-secondary); display: block; margin-top: 0.5rem;">
                        Enter one or more IP addresses (one per line) for bulk lookup
                    </small>
                </div>

                <div style="display: flex; gap: 1rem; align-items: center; margin-top: 1rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" id="validate-forward" checked style="width: auto;">
                        <span>Validate forward DNS (A/AAAA records)</span>
                    </label>
                </div>

                <button class="btn btn-primary" id="lookup-button" style="margin-top: 1rem; width: 100%;">
                    Perform Reverse DNS Lookup
                </button>
            </div>

            <!-- Results Section -->
            <div class="feature-card" id="results-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Lookup Results</h2>
                <div id="results-content"></div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2025 VeriBits by After Dark Systems. All rights reserved.</p>
        </div>
    </footer>

    <script src="/assets/js/main.js"></script>
    <script>
        const lookupButton = document.getElementById('lookup-button');
        const resultsSection = document.getElementById('results-section');

        lookupButton.addEventListener('click', async () => {
            const ipInput = document.getElementById('ip-address').value.trim();
            const validateForward = document.getElementById('validate-forward').checked;

            if (!ipInput) {
                showAlert('Please enter at least one IP address', 'error');
                return;
            }

            // Split by newlines and filter empty lines
            const ipAddresses = ipInput.split('\n')
                .map(ip => ip.trim())
                .filter(ip => ip.length > 0);

            if (ipAddresses.length === 0) {
                showAlert('Please enter valid IP addresses', 'error');
                return;
            }

            resultsSection.style.display = 'block';
            document.getElementById('results-content').innerHTML = '<div class="spinner"></div>';

            try {
                const response = await fetch('/api/v1/tools/reverse-dns', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: JSON.stringify({
                        ip_addresses: ipAddresses,
                        validate_forward: validateForward
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    const errorMsg = data.error?.message || data.error || data.message || 'Lookup failed';
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
            const results = data.results || [];
            const successCount = results.filter(r => r.ptr_record).length;
            const totalCount = results.length;

            let html = `
                <div style="padding: 1rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1.5rem; display: flex; justify-content: space-around; text-align: center;">
                    <div>
                        <div style="font-size: 1.5rem; color: var(--primary-color); font-weight: bold;">${totalCount}</div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">Total Lookups</div>
                    </div>
                    <div>
                        <div style="font-size: 1.5rem; color: var(--success-color); font-weight: bold;">${successCount}</div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">PTR Found</div>
                    </div>
                    <div>
                        <div style="font-size: 1.5rem; color: var(--error-color); font-weight: bold;">${totalCount - successCount}</div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">No PTR</div>
                    </div>
                </div>
            `;

            results.forEach(result => {
                const hasPTR = result.ptr_record && result.ptr_record !== 'No PTR record found';
                const statusColor = hasPTR ? 'var(--success-color)' : 'var(--error-color)';
                const statusIcon = hasPTR ? '✓' : '✗';

                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid ${statusColor};">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                            <span style="font-size: 1.5rem; color: ${statusColor};">${statusIcon}</span>
                            <h3 style="margin: 0; color: var(--text-primary);">${result.ip_address}</h3>
                        </div>

                        <div style="margin-bottom: 0.75rem;">
                            <strong>PTR Record:</strong>
                            ${hasPTR
                                ? `<code style="color: var(--accent-color);">${result.ptr_record}</code>`
                                : `<span style="color: var(--error-color);">No PTR record found</span>`
                            }
                        </div>

                        ${result.forward_dns ? `
                            <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px; margin-top: 1rem;">
                                <strong>Forward DNS Validation:</strong>
                                ${result.forward_dns.matches
                                    ? `<span style="color: var(--success-color); margin-left: 0.5rem;">✓ Matches</span>`
                                    : `<span style="color: var(--warning-color); margin-left: 0.5rem;">⚠ Does not match</span>`
                                }
                                ${result.forward_dns.records && result.forward_dns.records.length > 0 ? `
                                    <div style="margin-top: 0.5rem;">
                                        <div style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.25rem;">A/AAAA Records:</div>
                                        ${result.forward_dns.records.map(rec =>
                                            `<div style="margin-left: 1rem;"><code style="color: var(--accent-color); font-size: 0.9rem;">${rec}</code></div>`
                                        ).join('')}
                                    </div>
                                ` : ''}
                            </div>
                        ` : ''}

                        ${result.error ? `
                            <div style="color: var(--error-color); margin-top: 0.5rem; font-size: 0.9rem;">
                                Error: ${result.error}
                            </div>
                        ` : ''}
                    </div>
                `;
            });

            html += `
                <div style="margin-top: 1.5rem; text-align: center;">
                    <button class="btn btn-primary" onclick="location.reload()">Lookup More IPs</button>
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
