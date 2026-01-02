<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hash Lookup & Decryption - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
    <style>
        .tab-container {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border-color);
            overflow-x: auto;
        }
        .tab {
            padding: 1rem 1.5rem;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            white-space: nowrap;
        }
        .tab:hover {
            color: var(--text-primary);
        }
        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .source-result {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            margin: 0.5rem 0;
            background: var(--darker-bg);
            border-radius: 6px;
            border-left: 3px solid var(--border-color);
        }
        .source-result.found {
            border-left-color: var(--success-color);
            background: rgba(34, 197, 94, 0.1);
        }
        .source-result.not-found {
            border-left-color: var(--text-secondary);
        }
        .plaintext-result {
            background: var(--darker-bg);
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
            border: 2px solid var(--success-color);
        }
        .plaintext-value {
            font-family: monospace;
            font-size: 1.2rem;
            color: var(--success-color);
            word-break: break-all;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 4px;
            margin: 1rem 0;
        }
        .hash-type-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: var(--primary-color);
            color: white;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .warning-box {
            background: rgba(245, 158, 11, 0.1);
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        .stat-card {
            background: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        .email-list {
            background: var(--darker-bg);
            padding: 1rem;
            border-radius: 6px;
            max-height: 400px;
            overflow-y: auto;
        }
        .email-item {
            padding: 0.5rem;
            margin: 0.25rem 0;
            background: rgba(255,255,255,0.05);
            border-radius: 4px;
            font-family: monospace;
        }
        .spinner {
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 2rem auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
        <div class="container" style="max-width: 1200px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Hash Lookup & Decryption</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Lookup pre-computed hashes in multiple databases. Supports MD5, SHA1, SHA256 and more.
            </p>

            <div class="warning-box">
                <strong>⚠️ Legal Notice:</strong> Only use this tool for security research, password recovery, or systems you own.
                Unauthorized hash lookup for malicious purposes is illegal.
            </div>

            <div id="alert-container"></div>

            <!-- Tabs -->
            <div class="tab-container">
                <button class="tab active" onclick="switchTab('lookup')">🔍 Hash Lookup</button>
                <button class="tab" onclick="switchTab('batch')">📋 Batch Lookup</button>
                <button class="tab" onclick="switchTab('identify')">🔎 Identify Hash</button>
                <button class="tab" onclick="switchTab('email')">📧 Email Extractor</button>
            </div>

            <!-- Hash Lookup Tab -->
            <div id="lookup-tab" class="tab-content active">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Single Hash Lookup</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                        Enter a hash to search across multiple databases. Supports MD5, SHA1, SHA256, and more.
                    </p>

                    <form id="lookup-form">
                        <div class="form-group">
                            <label for="hash-input">Hash Value</label>
                            <input type="text" id="hash-input" placeholder="Enter hash (e.g., 5f4dcc3b5aa765d61d8327deb882cf99)"
                                   style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace;">
                            <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">
                                Example MD5: 5f4dcc3b5aa765d61d8327deb882cf99 (try this!)
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            🔍 Lookup Hash
                        </button>
                    </form>

                    <div id="lookup-results"></div>
                </div>
            </div>

            <!-- Batch Lookup Tab -->
            <div id="batch-tab" class="tab-content">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Batch Hash Lookup</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                        Lookup multiple hashes at once (up to 25). One hash per line.
                    </p>

                    <div class="warning-box" style="background: rgba(59, 130, 246, 0.1); border-left-color: #3b82f6;">
                        <strong>🔐 Authentication Required:</strong> Batch lookup requires a VeriBits account.
                        <a href="/signup.php" style="color: var(--primary-color); text-decoration: underline;">Sign up free</a>
                    </div>

                    <form id="batch-form">
                        <div class="form-group">
                            <label for="batch-input">Hash List (one per line)</label>
                            <textarea id="batch-input" rows="10" placeholder="5f4dcc3b5aa765d61d8327deb882cf99&#10;e10adc3949ba59abbe56e057f20f883e&#10;25f9e794323b453885f5181f1b624d0b"
                                      style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace;"></textarea>
                            <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">
                                Maximum 25 hashes per batch
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            📋 Lookup Batch
                        </button>
                    </form>

                    <div id="batch-results"></div>
                </div>
            </div>

            <!-- Identify Hash Tab -->
            <div id="identify-tab" class="tab-content">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Hash Type Identifier</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                        Not sure what type of hash you have? Enter it here to identify possible hash types.
                    </p>

                    <form id="identify-form">
                        <div class="form-group">
                            <label for="identify-input">Hash Value</label>
                            <input type="text" id="identify-input" placeholder="Enter hash to identify"
                                   style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace;">
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            🔎 Identify Hash Type
                        </button>
                    </form>

                    <div id="identify-results"></div>
                </div>
            </div>

            <!-- Email Extractor Tab -->
            <div id="email-tab" class="tab-content">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Email Extractor</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                        Extract and validate email addresses from any text. Useful for parsing logs, documents, and more.
                    </p>

                    <form id="email-form">
                        <div class="form-group">
                            <label for="email-input">Text to Extract From</label>
                            <textarea id="email-input" rows="10" placeholder="Paste any text containing email addresses here..."
                                      style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            📧 Extract Emails
                        </button>
                    </form>

                    <div id="email-results"></div>
                </div>
            </div>

            <!-- Information Section -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">About Hash Lookup</h2>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">How It Works</h3>
                <p style="margin-bottom: 1rem;">
                    This tool queries multiple online hash databases to find pre-computed plaintext values.
                    We aggregate results from several sources to maximize success rate.
                </p>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Supported Hash Types</h3>
                <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                    <li><strong>MD5</strong> (32 characters) - Most common</li>
                    <li><strong>SHA-1</strong> (40 characters)</li>
                    <li><strong>SHA-256</strong> (64 characters)</li>
                    <li><strong>SHA-384</strong> (96 characters)</li>
                    <li><strong>SHA-512</strong> (128 characters)</li>
                    <li><strong>NTLM, MySQL</strong> and others</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Success Rate</h3>
                <p style="margin-bottom: 1rem;">
                    Success depends on:
                </p>
                <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                    <li><strong>Common passwords</strong> - Very high success rate (90%+)</li>
                    <li><strong>Dictionary words</strong> - Moderate success rate (40-60%)</li>
                    <li><strong>Random passwords</strong> - Low success rate (< 10%)</li>
                    <li><strong>Salted hashes</strong> - Cannot be looked up</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Privacy & Security</h3>
                <p>
                    We query external databases but do not store your hashes or results.
                    All lookups are performed in real-time and not cached.
                </p>
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
        function switchTab(tab) {
            // Update tab buttons
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');

            // Update tab content
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(tab + '-tab').classList.add('active');
        }

        // Hash Lookup Form
        document.getElementById('lookup-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const hash = document.getElementById('hash-input').value.trim();
            if (!hash) {
                showAlert('Please enter a hash value', 'error');
                return;
            }

            const resultsDiv = document.getElementById('lookup-results');
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;

            submitBtn.disabled = true;
            submitBtn.textContent = 'Looking up hash...';
            resultsDiv.innerHTML = '<div class="spinner"></div><p style="text-align: center; color: var(--text-secondary); margin-top: 1rem;">Querying multiple databases...</p>';

            try {
                const data = await apiRequest('/api/v1/tools/hash-lookup', {
                    method: 'POST',
                    body: JSON.stringify({ hash })
                });

                displayLookupResults(data.data);
            } catch (error) {
                resultsDiv.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });

        // Batch Lookup Form
        document.getElementById('batch-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const batchInput = document.getElementById('batch-input').value.trim();
            if (!batchInput) {
                showAlert('Please enter hashes', 'error');
                return;
            }

            const hashes = batchInput.split('\n').map(h => h.trim()).filter(h => h);

            if (hashes.length > 25) {
                showAlert('Maximum 25 hashes allowed per batch', 'error');
                return;
            }

            const resultsDiv = document.getElementById('batch-results');
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;

            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing batch...';
            resultsDiv.innerHTML = '<div class="spinner"></div><p style="text-align: center; color: var(--text-secondary); margin-top: 1rem;">Processing ' + hashes.length + ' hashes...</p>';

            try {
                const data = await apiRequest('/api/v1/tools/hash-lookup/batch', {
                    method: 'POST',
                    body: JSON.stringify({ hashes })
                });

                displayBatchResults(data.data);
            } catch (error) {
                resultsDiv.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });

        // Identify Hash Form
        document.getElementById('identify-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const hash = document.getElementById('identify-input').value.trim();
            if (!hash) {
                showAlert('Please enter a hash value', 'error');
                return;
            }

            const resultsDiv = document.getElementById('identify-results');
            resultsDiv.innerHTML = '<div class="spinner"></div>';

            try {
                const data = await apiRequest('/api/v1/tools/hash-lookup/identify', {
                    method: 'POST',
                    body: JSON.stringify({ hash })
                });

                displayIdentifyResults(data.data);
            } catch (error) {
                resultsDiv.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        });

        // Email Extractor Form
        document.getElementById('email-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const text = document.getElementById('email-input').value.trim();
            if (!text) {
                showAlert('Please enter text to extract from', 'error');
                return;
            }

            const resultsDiv = document.getElementById('email-results');
            resultsDiv.innerHTML = '<div class="spinner"></div>';

            try {
                const data = await apiRequest('/api/v1/tools/email-extractor', {
                    method: 'POST',
                    body: JSON.stringify({ text })
                });

                displayEmailResults(data.data);
            } catch (error) {
                resultsDiv.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        });

        function displayLookupResults(data) {
            const resultsDiv = document.getElementById('lookup-results');

            if (data.found) {
                let html = `<div class="plaintext-result">
                    <h3 style="margin-bottom: 1rem; color: var(--success-color);">✓ Hash Found!</h3>
                    <div style="margin-bottom: 1rem;">
                        <strong>Hash Type:</strong> <span class="hash-type-badge">${data.hash_type.toUpperCase()}</span>
                    </div>
                    <div>
                        <strong>Plaintext:</strong>
                        <div class="plaintext-value">${data.plaintext}</div>
                    </div>
                    <button onclick="copyToClipboard('${data.plaintext}')" class="btn btn-secondary" style="margin-top: 1rem;">
                        📋 Copy to Clipboard
                    </button>
                </div>`;

                html += '<h3 style="margin-top: 2rem; margin-bottom: 1rem;">Sources Queried:</h3>';

                data.sources.forEach(source => {
                    const statusIcon = source.found ? '✓' : '✗';
                    const statusClass = source.found ? 'found' : 'not-found';

                    html += `<div class="source-result ${statusClass}">
                        <div>
                            <strong>${statusIcon} ${source.source}</strong>
                            ${source.note ? `<br><small style="color: var(--text-secondary);">${source.note}</small>` : ''}
                        </div>
                        <div>${source.found ? '<span style="color: var(--success-color);">Found</span>' : '<span style="color: var(--text-secondary);">Not found</span>'}</div>
                    </div>`;
                });

                resultsDiv.innerHTML = html;
            } else {
                let html = `<div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--error-color); padding: 1.5rem; border-radius: 8px; margin-top: 1.5rem;">
                    <h3 style="margin-bottom: 1rem;">❌ Hash Not Found</h3>
                    <p style="color: var(--text-secondary);">
                        The hash was not found in any of the queried databases. This could mean:
                    </p>
                    <ul style="margin: 1rem 0 1rem 1.5rem; color: var(--text-secondary);">
                        <li>The password is not in any database</li>
                        <li>The hash is salted or uses a unique algorithm</li>
                        <li>The hash may be incorrect or corrupted</li>
                    </ul>
                    <p style="color: var(--text-secondary);">
                        <strong>Queried ${data.sources_queried} sources</strong>
                    </p>
                </div>`;

                html += '<h3 style="margin-top: 2rem; margin-bottom: 1rem;">Sources Queried:</h3>';

                data.sources.forEach(source => {
                    html += `<div class="source-result not-found">
                        <div>
                            <strong>✗ ${source.source}</strong>
                            ${source.note ? `<br><small style="color: var(--text-secondary);">${source.note}</small>` : ''}
                        </div>
                        <div><span style="color: var(--text-secondary);">Not found</span></div>
                    </div>`;
                });

                resultsDiv.innerHTML = html;
            }
        }

        function displayBatchResults(data) {
            const resultsDiv = document.getElementById('batch-results');

            let html = `<div class="stats-grid" style="margin-top: 2rem;">
                <div class="stat-card">
                    <div class="stat-value">${data.total}</div>
                    <div class="stat-label">Total Hashes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--success-color);">${data.found}</div>
                    <div class="stat-label">Found</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--error-color);">${data.not_found}</div>
                    <div class="stat-label">Not Found</div>
                </div>
            </div>`;

            html += '<h3 style="margin-top: 2rem; margin-bottom: 1rem;">Results:</h3>';
            html += '<div style="overflow-x: auto;"><table style="width: 100%; border-collapse: collapse;">';
            html += `<thead><tr style="background: var(--darker-bg);">
                <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid var(--border-color);">Hash</th>
                <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid var(--border-color);">Type</th>
                <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid var(--border-color);">Plaintext</th>
            </tr></thead><tbody>`;

            data.results.forEach(result => {
                const statusColor = result.found ? 'var(--success-color)' : 'var(--text-secondary)';
                html += `<tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 0.75rem; font-family: monospace; font-size: 0.85rem;">${result.hash.substring(0, 16)}...</td>
                    <td style="padding: 0.75rem;"><span class="hash-type-badge">${result.hash_type.toUpperCase()}</span></td>
                    <td style="padding: 0.75rem; color: ${statusColor}; font-weight: 600;">${result.found ? result.plaintext : 'Not found'}</td>
                </tr>`;
            });

            html += '</tbody></table></div>';

            resultsDiv.innerHTML = html;
        }

        function displayIdentifyResults(data) {
            const resultsDiv = document.getElementById('identify-results');

            let html = `<div style="background: var(--darker-bg); padding: 1.5rem; border-radius: 8px; margin-top: 1.5rem;">
                <h3 style="margin-bottom: 1rem;">Hash Analysis</h3>
                <div style="display: grid; gap: 1rem;">
                    <div>
                        <strong>Hash:</strong> <code style="word-break: break-all;">${data.hash}</code>
                    </div>
                    <div>
                        <strong>Length:</strong> ${data.length} characters
                    </div>
                    <div>
                        <strong>Most Likely Type:</strong> <span class="hash-type-badge">${data.most_likely.toUpperCase()}</span>
                    </div>
                </div>
            </div>`;

            html += '<h3 style="margin-top: 2rem; margin-bottom: 1rem;">Possible Hash Types:</h3>';
            html += '<div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">';
            data.possible_types.forEach(type => {
                html += `<span class="hash-type-badge" style="background: var(--darker-bg); color: var(--text-primary);">${type}</span>`;
            });
            html += '</div>';

            resultsDiv.innerHTML = html;
        }

        function displayEmailResults(data) {
            const resultsDiv = document.getElementById('email-results');

            let html = `<div class="stats-grid" style="margin-top: 2rem;">
                <div class="stat-card">
                    <div class="stat-value">${data.total_found}</div>
                    <div class="stat-label">Total Found</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--success-color);">${data.valid_count}</div>
                    <div class="stat-label">Valid</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--error-color);">${data.invalid_count}</div>
                    <div class="stat-label">Invalid</div>
                </div>
            </div>`;

            if (data.valid_count > 0) {
                html += '<h3 style="margin-top: 2rem; margin-bottom: 1rem;">Valid Emails:</h3>';
                html += '<div class="email-list">';
                data.valid_emails.forEach(email => {
                    html += `<div class="email-item">${email}</div>`;
                });
                html += '</div>';

                const emailList = data.valid_emails.join('\n');
                html += `<button onclick="copyToClipboard('${emailList}')" class="btn btn-secondary" style="margin-top: 1rem; width: 100%;">
                    📋 Copy All Emails
                </button>`;
            }

            if (data.invalid_count > 0) {
                html += '<h3 style="margin-top: 2rem; margin-bottom: 1rem;">Invalid Emails:</h3>';
                html += '<div class="email-list">';
                data.invalid_emails.forEach(email => {
                    html += `<div class="email-item" style="color: var(--error-color);">${email}</div>`;
                });
                html += '</div>';
            }

            resultsDiv.innerHTML = html;
        }
    </script>
</body>
</html>
