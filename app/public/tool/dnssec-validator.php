<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DNSSEC Validator - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
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
        <div class="container" style="max-width: 900px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">DNSSEC Validator</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Validate DNSSEC signatures and check chain of trust for domains
            </p>

            <div id="alert-container"></div>

            <!-- Input Section -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Enter Domain Name</h2>

                <div class="form-group">
                    <label for="domain">Domain Name</label>
                    <input type="text" id="domain" placeholder="example.com"
                        style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                </div>

                <button class="btn btn-primary" id="validate-button" style="margin-top: 1rem; width: 100%;">
                    Validate DNSSEC
                </button>
            </div>

            <!-- Results Section -->
            <div class="feature-card" id="results-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">DNSSEC Validation Results</h2>
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
        const validateButton = document.getElementById('validate-button');
        const resultsSection = document.getElementById('results-section');

        validateButton.addEventListener('click', async () => {
            const domain = document.getElementById('domain').value.trim();

            if (!domain) {
                showAlert('Please enter a domain name', 'error');
                return;
            }

            resultsSection.style.display = 'block';
            document.getElementById('results-content').innerHTML = '<div class="spinner"></div>';

            try {
                const response = await fetch('/api/v1/tools/dnssec-validate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: JSON.stringify({ domain })
                });

                const data = await response.json();

                if (!response.ok) {
                    const errorMsg = data.error?.message || data.error || data.message || 'Validation failed';
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
            let html = '';

            // DNSSEC Status
            const dnssecEnabled = data.dnssec_enabled || false;
            const statusColor = dnssecEnabled ? 'var(--success-color)' : 'var(--error-color)';
            const statusIcon = dnssecEnabled ? '✓' : '✗';

            html += `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: ${statusColor}; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 1.5rem;">${statusIcon}</span>
                        DNSSEC ${dnssecEnabled ? 'Enabled' : 'Not Enabled'}
                    </h3>
                    <p style="color: var(--text-secondary);">${data.validation_message || ''}</p>
                </div>
            `;

            // DNSKEY Records
            if (data.dnskey_records && data.dnskey_records.length > 0) {
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">DNSKEY Records</h3>
                `;

                data.dnskey_records.forEach(key => {
                    html += `
                        <div style="margin-bottom: 1rem; padding: 1rem; background: var(--dark-bg); border-radius: 4px; border-left: 3px solid var(--primary-color);">
                            <p><strong>Flags:</strong> ${key.flags} ${key.flags === 257 ? '(Key-Signing Key)' : key.flags === 256 ? '(Zone-Signing Key)' : ''}</p>
                            <p><strong>Protocol:</strong> ${key.protocol}</p>
                            <p><strong>Algorithm:</strong> ${key.algorithm}</p>
                            <p><strong>Key Tag:</strong> ${key.key_tag || 'N/A'}</p>
                            <p style="word-break: break-all;"><strong>Public Key:</strong> <code style="font-size: 0.8rem;">${key.public_key ? key.public_key.substring(0, 80) + '...' : 'N/A'}</code></p>
                        </div>
                    `;
                });

                html += `</div>`;
            }

            // DS Records
            if (data.ds_records && data.ds_records.length > 0) {
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">DS Records (Delegation Signer)</h3>
                `;

                data.ds_records.forEach(ds => {
                    html += `
                        <div style="margin-bottom: 1rem; padding: 1rem; background: var(--dark-bg); border-radius: 4px; border-left: 3px solid var(--accent-color);">
                            <p><strong>Key Tag:</strong> ${ds.key_tag}</p>
                            <p><strong>Algorithm:</strong> ${ds.algorithm}</p>
                            <p><strong>Digest Type:</strong> ${ds.digest_type}</p>
                            <p style="word-break: break-all;"><strong>Digest:</strong> <code style="font-size: 0.8rem;">${ds.digest}</code></p>
                        </div>
                    `;
                });

                html += `</div>`;
            }

            // RRSIG Records
            if (data.rrsig_records && data.rrsig_records.length > 0) {
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">RRSIG Records (Signatures)</h3>
                `;

                data.rrsig_records.forEach(rrsig => {
                    html += `
                        <div style="margin-bottom: 1rem; padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                            <p><strong>Type Covered:</strong> ${rrsig.type_covered}</p>
                            <p><strong>Algorithm:</strong> ${rrsig.algorithm}</p>
                            <p><strong>Signer:</strong> ${rrsig.signer_name}</p>
                            <p><strong>Signature Expiration:</strong> ${rrsig.signature_expiration}</p>
                            <p><strong>Signature Inception:</strong> ${rrsig.signature_inception}</p>
                        </div>
                    `;
                });

                html += `</div>`;
            }

            // Chain of Trust
            if (data.chain_of_trust && data.chain_of_trust.length > 0) {
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Chain of Trust</h3>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                `;

                data.chain_of_trust.forEach((item, index) => {
                    const isLast = index === data.chain_of_trust.length - 1;
                    html += `
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="flex: 1; padding: 0.75rem; background: var(--dark-bg); border-radius: 4px;">
                                ${item}
                            </div>
                            ${!isLast ? '<div style="color: var(--primary-color);">↓</div>' : ''}
                        </div>
                    `;
                });

                html += `
                        </div>
                    </div>
                `;
            }

            html += `
                <div style="margin-top: 1.5rem; text-align: center;">
                    <button class="btn btn-primary" onclick="location.reload()">Validate Another Domain</button>
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
