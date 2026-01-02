<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Recovery Tool - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
    <style>
        .tab-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border-color);
        }
        .tab {
            padding: 1rem 2rem;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
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
        .file-info {
            background: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .file-info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        .file-info-row:last-child {
            border-bottom: none;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-encrypted {
            background: var(--error-color);
            color: white;
        }
        .status-unlocked {
            background: var(--success-color);
            color: white;
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
        .warning-box {
            background: rgba(245, 158, 11, 0.1);
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
        }
        .result-box {
            background: var(--darker-bg);
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1.5rem;
        }
        .success-box {
            background: rgba(34, 197, 94, 0.1);
            border-left: 4px solid var(--success-color);
        }
        .error-box {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid var(--error-color);
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
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Password Recovery Tool</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Remove or recover passwords from PDF, Office documents, and ZIP files
            </p>

            <div class="warning-box">
                <strong>⚠️ Legal Notice:</strong> Only use this tool on files you own or have explicit permission to access.
                Unauthorized password cracking is illegal.
            </div>

            <div id="alert-container"></div>

            <!-- Tabs -->
            <div class="tab-container">
                <button class="tab active" onclick="switchTab('analyze')">📊 Analyze File</button>
                <button class="tab" onclick="switchTab('remove')">🔓 Remove Password</button>
                <button class="tab" onclick="switchTab('crack')">🔨 Crack Password</button>
            </div>

            <!-- Analyze Tab -->
            <div id="analyze-tab" class="tab-content active">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Analyze Password-Protected File</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                        Upload a file to check if it's password-protected and view encryption details.
                    </p>

                    <form id="analyze-form">
                        <div class="form-group">
                            <label for="analyze-file">Select File</label>
                            <input type="file" id="analyze-file" name="file" required accept=".pdf,.docx,.xlsx,.pptx,.zip">
                            <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">
                                Supported: PDF, DOCX, XLSX, PPTX, ZIP (max 50MB)
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            Analyze File
                        </button>
                    </form>

                    <div id="analyze-results"></div>
                </div>
            </div>

            <!-- Remove Password Tab -->
            <div id="remove-tab" class="tab-content">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Remove Password from File</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                        Remove password protection when you know the password. The unlocked file will be downloaded automatically.
                    </p>

                    <form id="remove-form">
                        <div class="form-group">
                            <label for="remove-file">Select File</label>
                            <input type="file" id="remove-file" name="file" required accept=".pdf,.docx,.xlsx,.pptx,.zip">
                            <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">
                                Supported: PDF, DOCX, XLSX, PPTX, ZIP (max 50MB)
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="remove-password">Password</label>
                            <input type="password" id="remove-password" name="password" required placeholder="Enter the file password">
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            Remove Password & Download
                        </button>
                    </form>

                    <div id="remove-results"></div>
                </div>
            </div>

            <!-- Crack Password Tab -->
            <div id="crack-tab" class="tab-content">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Crack Password (Dictionary Attack)</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                        Attempt to recover the password using common password lists. This may take several minutes.
                    </p>

                    <form id="crack-form">
                        <div class="form-group">
                            <label for="crack-file">Select File</label>
                            <input type="file" id="crack-file" name="file" required accept=".pdf,.docx,.xlsx,.pptx,.zip">
                            <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">
                                Supported: PDF, DOCX, XLSX, PPTX, ZIP (max 50MB)
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="wordlist">Password List</label>
                            <select id="wordlist" name="wordlist">
                                <option value="common">Common Passwords (Fast - ~100 attempts)</option>
                                <option value="numeric">Numeric 0000-9999 (Medium - ~10,000 attempts)</option>
                                <option value="alpha">Random Lowercase (Slow - customizable)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="max-attempts">Maximum Attempts</label>
                            <input type="number" id="max-attempts" name="max_attempts" value="1000" min="100" max="10000" step="100">
                            <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">
                                More attempts = longer processing time. Maximum: 10,000
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            Start Password Cracking
                        </button>
                    </form>

                    <div id="crack-results"></div>
                </div>
            </div>

            <!-- Information Section -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">How It Works</h2>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Password Removal</h3>
                <p style="margin-bottom: 1rem;">
                    When you know the password, this tool decrypts the file and creates a new version without password protection.
                    The original file is not modified.
                </p>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Password Cracking</h3>
                <p style="margin-bottom: 1rem;">
                    Uses dictionary attacks to try common passwords. Success depends on password complexity:
                </p>
                <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                    <li><strong>Simple passwords</strong> (123456, password, etc.) - Usually found quickly</li>
                    <li><strong>Numeric passwords</strong> (PINs, dates) - Can be found with numeric wordlist</li>
                    <li><strong>Complex passwords</strong> (random characters, long) - Very unlikely to crack</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Supported File Types</h3>
                <ul style="margin-left: 1.5rem;">
                    <li><strong>PDF</strong> - Uses qpdf and pikepdf</li>
                    <li><strong>Office Documents</strong> - DOCX, XLSX, PPTX (uses msoffcrypto-tool)</li>
                    <li><strong>ZIP Archives</strong> - Traditional ZIP encryption (not AES-256)</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Privacy & Security</h3>
                <p>
                    Files are processed server-side and immediately deleted after processing.
                    We do not store uploaded files or recovered passwords.
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

        // Analyze form
        document.getElementById('analyze-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData();
            const fileInput = document.getElementById('analyze-file');
            formData.append('file', fileInput.files[0]);

            const resultsDiv = document.getElementById('analyze-results');
            resultsDiv.innerHTML = '<div class="spinner"></div>';

            try {
                const response = await fetch('/api/v1/tools/password-recovery/analyze', {
                    method: 'POST',
                    headers: getAuthToken() ? {'Authorization': `Bearer ${getAuthToken()}`} : {},
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    displayAnalysisResults(result.data);
                } else {
                    resultsDiv.innerHTML = `<div class="result-box error-box">
                        <strong>Error:</strong> ${result.error?.message || 'Analysis failed'}
                    </div>`;
                }
            } catch (error) {
                resultsDiv.innerHTML = `<div class="result-box error-box">
                    <strong>Error:</strong> ${error.message}
                </div>`;
            }
        });

        // Remove password form
        document.getElementById('remove-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData();
            const fileInput = document.getElementById('remove-file');
            const password = document.getElementById('remove-password').value;

            formData.append('file', fileInput.files[0]);
            formData.append('password', password);

            const resultsDiv = document.getElementById('remove-results');
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;

            submitBtn.disabled = true;
            submitBtn.textContent = 'Removing password...';
            resultsDiv.innerHTML = '<div class="spinner"></div>';

            try {
                const response = await fetch('/api/v1/tools/password-recovery/remove', {
                    method: 'POST',
                    headers: getAuthToken() ? {'Authorization': `Bearer ${getAuthToken()}`} : {},
                    body: formData
                });

                if (response.ok && response.headers.get('content-type') === 'application/octet-stream') {
                    // File download successful
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'unlocked_' + fileInput.files[0].name;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);

                    resultsDiv.innerHTML = `<div class="result-box success-box">
                        <strong>✓ Success!</strong> Password removed. Your unlocked file has been downloaded.
                    </div>`;
                } else {
                    const result = await response.json();
                    resultsDiv.innerHTML = `<div class="result-box error-box">
                        <strong>Error:</strong> ${result.error?.message || 'Password removal failed. Check if password is correct.'}
                    </div>`;
                }
            } catch (error) {
                resultsDiv.innerHTML = `<div class="result-box error-box">
                    <strong>Error:</strong> ${error.message}
                </div>`;
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });

        // Crack password form
        document.getElementById('crack-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData();
            const fileInput = document.getElementById('crack-file');
            const wordlist = document.getElementById('wordlist').value;
            const maxAttempts = document.getElementById('max-attempts').value;

            formData.append('file', fileInput.files[0]);
            formData.append('wordlist', wordlist);
            formData.append('max_attempts', maxAttempts);
            formData.append('method', 'dictionary');

            const resultsDiv = document.getElementById('crack-results');
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;

            submitBtn.disabled = true;
            submitBtn.textContent = 'Cracking password...';
            resultsDiv.innerHTML = `<div class="spinner"></div><p style="text-align: center; color: var(--text-secondary); margin-top: 1rem;">This may take several minutes...</p>`;

            try {
                const response = await fetch('/api/v1/tools/password-recovery/crack', {
                    method: 'POST',
                    headers: getAuthToken() ? {'Authorization': `Bearer ${getAuthToken()}`} : {},
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    displayCrackResults(result.data);
                } else {
                    resultsDiv.innerHTML = `<div class="result-box error-box">
                        <strong>Error:</strong> ${result.error?.message || 'Password cracking failed'}
                    </div>`;
                }
            } catch (error) {
                resultsDiv.innerHTML = `<div class="result-box error-box">
                    <strong>Error:</strong> ${error.message}
                </div>`;
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });

        function displayAnalysisResults(data) {
            const resultsDiv = document.getElementById('analyze-results');

            const statusBadge = data.is_encrypted
                ? '<span class="status-badge status-encrypted">🔒 Encrypted</span>'
                : '<span class="status-badge status-unlocked">🔓 Not Encrypted</span>';

            let html = `<div class="file-info" style="margin-top: 1.5rem;">
                <div class="file-info-row">
                    <strong>Filename:</strong>
                    <span>${data.filename}</span>
                </div>
                <div class="file-info-row">
                    <strong>File Type:</strong>
                    <span>${data.type.toUpperCase()}</span>
                </div>
                <div class="file-info-row">
                    <strong>File Size:</strong>
                    <span>${formatFileSize(data.size)}</span>
                </div>
                <div class="file-info-row">
                    <strong>Status:</strong>
                    ${statusBadge}
                </div>`;

            if (data.is_encrypted && data.encryption_type) {
                html += `<div class="file-info-row">
                    <strong>Encryption Type:</strong>
                    <span>${data.encryption_type}</span>
                </div>`;
            }

            if (data.file_count !== undefined) {
                html += `<div class="file-info-row">
                    <strong>Files in Archive:</strong>
                    <span>${data.file_count}</span>
                </div>`;
            }

            html += `</div>`;

            if (data.is_encrypted) {
                html += `<div style="margin-top: 1rem; padding: 1rem; background: var(--darker-bg); border-radius: 8px;">
                    <p style="color: var(--text-secondary);">
                        💡 This file is password-protected. Use the "Remove Password" tab if you know the password,
                        or try "Crack Password" for weak passwords.
                    </p>
                </div>`;
            }

            resultsDiv.innerHTML = html;
        }

        function displayCrackResults(data) {
            const resultsDiv = document.getElementById('crack-results');

            if (data.found) {
                resultsDiv.innerHTML = `<div class="result-box success-box">
                    <h3 style="margin-bottom: 1rem;">✓ Password Found!</h3>
                    <div class="file-info-row">
                        <strong>Password:</strong>
                        <code style="font-size: 1.1rem; background: rgba(0,0,0,0.3); padding: 0.5rem 1rem; border-radius: 4px;">${data.password}</code>
                    </div>
                    <div class="file-info-row">
                        <strong>Attempts:</strong>
                        <span>${data.attempts.toLocaleString()}</span>
                    </div>
                    <div class="file-info-row">
                        <strong>Time:</strong>
                        <span>${data.time_seconds} seconds</span>
                    </div>
                    <p style="margin-top: 1rem; color: var(--text-secondary);">
                        💡 You can now use this password in the "Remove Password" tab to unlock your file.
                    </p>
                </div>`;
            } else {
                resultsDiv.innerHTML = `<div class="result-box error-box">
                    <h3 style="margin-bottom: 1rem;">❌ Password Not Found</h3>
                    <div class="file-info-row">
                        <strong>Attempts:</strong>
                        <span>${data.attempts.toLocaleString()}</span>
                    </div>
                    <div class="file-info-row">
                        <strong>Time:</strong>
                        <span>${data.time_seconds} seconds</span>
                    </div>
                    <p style="margin-top: 1rem; color: var(--text-secondary);">
                        ${data.message || 'The password was not found in the selected wordlist. Try a different wordlist or increase max attempts.'}
                    </p>
                </div>`;
            }
        }
    </script>
</body>
</html>
