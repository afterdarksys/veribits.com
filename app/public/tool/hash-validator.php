<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hash Validator - VeriBits</title>
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
        <div class="container" style="max-width: 1000px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Hash Validator</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Validate, identify, and compare cryptographic hashes
            </p>

            <div id="alert-container"></div>

            <!-- Operation Selection -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Select Operation</h2>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem;">
                    <button class="btn btn-primary operation-btn" data-operation="identify" onclick="selectOperation('identify')" style="width: 100%;">
                        Identify Hash
                    </button>
                    <button class="btn btn-secondary operation-btn" data-operation="validate" onclick="selectOperation('validate')" style="width: 100%;">
                        Validate Hash
                    </button>
                    <button class="btn btn-secondary operation-btn" data-operation="compare" onclick="selectOperation('compare')" style="width: 100%;">
                        Compare Hashes
                    </button>
                </div>

                <!-- Identify Hash Form -->
                <div id="identify-form" style="display: block;">
                    <div class="form-group">
                        <label for="hash-input">Hash Value</label>
                        <textarea id="hash-input" rows="4" placeholder="Enter hash value to identify..." style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;"></textarea>
                    </div>

                    <button class="btn btn-primary" onclick="identifyHash()" style="width: 100%;">
                        Identify Hash Type
                    </button>
                </div>

                <!-- Validate Hash Form -->
                <div id="validate-form" style="display: none;">
                    <div class="form-group">
                        <label for="validate-hash">Hash Value</label>
                        <textarea id="validate-hash" rows="3" placeholder="Enter hash to validate..." style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="hash-type">Hash Type</label>
                        <select id="hash-type" style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                            <option value="md5">MD5</option>
                            <option value="sha1">SHA-1</option>
                            <option value="sha256" selected>SHA-256</option>
                            <option value="sha384">SHA-384</option>
                            <option value="sha512">SHA-512</option>
                            <option value="sha3-256">SHA3-256</option>
                            <option value="sha3-512">SHA3-512</option>
                            <option value="blake2b">BLAKE2b</option>
                            <option value="blake2s">BLAKE2s</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="validate-text">Original Text (optional)</label>
                        <textarea id="validate-text" rows="3" placeholder="Enter original text to verify hash..." style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;"></textarea>
                    </div>

                    <button class="btn btn-primary" onclick="validateHash()" style="width: 100%;">
                        Validate Hash
                    </button>
                </div>

                <!-- Compare Hashes Form -->
                <div id="compare-form" style="display: none;">
                    <div class="form-group">
                        <label for="hash1">First Hash</label>
                        <textarea id="hash1" rows="3" placeholder="Enter first hash..." style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="hash2">Second Hash</label>
                        <textarea id="hash2" rows="3" placeholder="Enter second hash..." style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;"></textarea>
                    </div>

                    <button class="btn btn-primary" onclick="compareHashes()" style="width: 100%;">
                        Compare Hashes
                    </button>
                </div>
            </div>

            <!-- Results Section -->
            <div class="feature-card" id="results-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Results</h2>
                <div id="results-content"></div>
            </div>

            <!-- Information Section -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Supported Hash Algorithms</h2>

                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem;">
                    <div>
                        <h3 style="color: var(--accent-color); margin-bottom: 0.75rem;">Common Hashes</h3>
                        <ul style="margin-left: 1.5rem;">
                            <li><strong>MD5:</strong> 32 hex characters (128-bit)</li>
                            <li><strong>SHA-1:</strong> 40 hex characters (160-bit)</li>
                            <li><strong>SHA-256:</strong> 64 hex characters (256-bit)</li>
                            <li><strong>SHA-512:</strong> 128 hex characters (512-bit)</li>
                        </ul>
                    </div>

                    <div>
                        <h3 style="color: var(--accent-color); margin-bottom: 0.75rem;">Modern Hashes</h3>
                        <ul style="margin-left: 1.5rem;">
                            <li><strong>SHA3-256:</strong> 64 hex characters (256-bit)</li>
                            <li><strong>SHA3-512:</strong> 128 hex characters (512-bit)</li>
                            <li><strong>BLAKE2b:</strong> Up to 128 hex characters</li>
                            <li><strong>BLAKE2s:</strong> Up to 64 hex characters</li>
                        </ul>
                    </div>
                </div>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Examples</h3>
                <p style="margin-bottom: 0.5rem;"><strong>MD5:</strong></p>
                <code style="display: block; background: var(--darker-bg); padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem;">5d41402abc4b2a76b9719d911017c592</code>

                <p style="margin-bottom: 0.5rem;"><strong>SHA-256:</strong></p>
                <code style="display: block; background: var(--darker-bg); padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem;">e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855</code>

                <p style="margin-bottom: 0.5rem;"><strong>SHA-512:</strong></p>
                <code style="display: block; background: var(--darker-bg); padding: 0.5rem; border-radius: 4px; word-break: break-all;">cf83e1357eefb8bdf1542850d66d8007d620e4050b5715dc83f4a921d36ce9ce47d0d13c5d85f2b0ff8318d2877eec2f63b931bd47417a81a538327af927da3e</code>
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
        let selectedOperation = 'identify';

        function selectOperation(operation) {
            selectedOperation = operation;

            // Update button styles
            document.querySelectorAll('.operation-btn').forEach(btn => {
                if (btn.dataset.operation === operation) {
                    btn.classList.remove('btn-secondary');
                    btn.classList.add('btn-primary');
                } else {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-secondary');
                }
            });

            // Show/hide appropriate form
            document.getElementById('identify-form').style.display = operation === 'identify' ? 'block' : 'none';
            document.getElementById('validate-form').style.display = operation === 'validate' ? 'block' : 'none';
            document.getElementById('compare-form').style.display = operation === 'compare' ? 'block' : 'none';
        }

        async function identifyHash() {
            const hashValue = document.getElementById('hash-input').value.trim();

            if (!hashValue) {
                showAlert('Please enter a hash value', 'error');
                return;
            }

            const resultsSection = document.getElementById('results-section');
            const resultsContent = document.getElementById('results-content');

            resultsSection.style.display = 'block';
            resultsContent.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="spinner" style="margin: 0 auto;"></div><p style="margin-top: 1rem; color: var(--text-secondary);">Identifying hash type...</p></div>';

            try {
                const data = await apiRequest('/tools/hash-validator', {
                    method: 'POST',
                    body: JSON.stringify({
                        hash: hashValue,
                        operation: 'identify'
                    })
                });

                displayIdentifyResults(data.data);
            } catch (error) {
                resultsContent.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        }

        async function validateHash() {
            const hashValue = document.getElementById('validate-hash').value.trim();
            const hashType = document.getElementById('hash-type').value;
            const originalText = document.getElementById('validate-text').value;

            if (!hashValue) {
                showAlert('Please enter a hash value', 'error');
                return;
            }

            const resultsSection = document.getElementById('results-section');
            const resultsContent = document.getElementById('results-content');

            resultsSection.style.display = 'block';
            resultsContent.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="spinner" style="margin: 0 auto;"></div><p style="margin-top: 1rem; color: var(--text-secondary);">Validating hash...</p></div>';

            try {
                const data = await apiRequest('/tools/hash-validator', {
                    method: 'POST',
                    body: JSON.stringify({
                        hash: hashValue,
                        hash_type: hashType,
                        original_text: originalText || null,
                        operation: 'validate'
                    })
                });

                displayValidateResults(data.data);
            } catch (error) {
                resultsContent.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        }

        async function compareHashes() {
            const hash1 = document.getElementById('hash1').value.trim();
            const hash2 = document.getElementById('hash2').value.trim();

            if (!hash1 || !hash2) {
                showAlert('Please enter both hash values', 'error');
                return;
            }

            const resultsSection = document.getElementById('results-section');
            const resultsContent = document.getElementById('results-content');

            resultsSection.style.display = 'block';
            resultsContent.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="spinner" style="margin: 0 auto;"></div><p style="margin-top: 1rem; color: var(--text-secondary);">Comparing hashes...</p></div>';

            try {
                const data = await apiRequest('/tools/hash-validator', {
                    method: 'POST',
                    body: JSON.stringify({
                        hash1: hash1,
                        hash2: hash2,
                        operation: 'compare'
                    })
                });

                displayCompareResults(data.data);
            } catch (error) {
                resultsContent.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        }

        function displayIdentifyResults(result) {
            let html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Hash Analysis</h3>
                    <p><strong>Hash Value:</strong></p>
                    <code style="display: block; background: var(--dark-bg); padding: 0.75rem; border-radius: 4px; word-break: break-all; margin-bottom: 1rem;">${result.hash}</code>
                    <p><strong>Length:</strong> ${result.length} characters (${result.bits || 'N/A'} bits)</p>
            `;

            if (result.possible_types && result.possible_types.length > 0) {
                html += `
                    <p style="margin-top: 1rem;"><strong>Possible Hash Types:</strong></p>
                    <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                `;
                result.possible_types.forEach(type => {
                    html += `<li style="margin-bottom: 0.25rem;">${type}</li>`;
                });
                html += `</ul>`;
            } else {
                html += `<p style="color: var(--warning-color); margin-top: 1rem;">Could not identify hash type. The format may be invalid or unsupported.</p>`;
            }

            html += `</div>`;

            document.getElementById('results-content').innerHTML = html;
        }

        function displayValidateResults(result) {
            const statusClass = result.is_valid ? 'success-color' : 'error-color';
            const statusIcon = result.is_valid ? '✅' : '❌';
            const statusText = result.is_valid ? 'Valid Hash Format' : 'Invalid Hash Format';

            let html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid var(--${statusClass});">
                    <h3 style="color: var(--${statusClass}); margin-bottom: 1rem;">${statusIcon} ${statusText}</h3>
                    <p><strong>Hash Type:</strong> ${result.hash_type.toUpperCase()}</p>
                    <p><strong>Expected Length:</strong> ${result.expected_length} characters</p>
                    <p><strong>Actual Length:</strong> ${result.actual_length} characters</p>
            `;

            if (result.matches !== undefined) {
                const matchIcon = result.matches ? '✅' : '❌';
                const matchText = result.matches ? 'Hashes Match' : 'Hashes Do Not Match';
                html += `
                    <div style="margin-top: 1rem; padding: 1rem; background: var(--dark-bg); border-radius: 4px; border-left: 3px solid var(--${result.matches ? 'success-color' : 'error-color'});">
                        <strong style="color: var(--${result.matches ? 'success-color' : 'error-color'});">${matchIcon} ${matchText}</strong>
                `;
                if (result.computed_hash) {
                    html += `
                        <p style="margin-top: 0.5rem;"><strong>Computed Hash:</strong></p>
                        <code style="display: block; background: var(--darker-bg); padding: 0.5rem; border-radius: 4px; word-break: break-all; margin-top: 0.25rem;">${result.computed_hash}</code>
                    `;
                }
                html += `</div>`;
            }

            if (result.error) {
                html += `<p style="color: var(--error-color); margin-top: 1rem;"><strong>Error:</strong> ${result.error}</p>`;
            }

            html += `</div>`;

            document.getElementById('results-content').innerHTML = html;
        }

        function displayCompareResults(result) {
            const statusClass = result.match ? 'success-color' : 'error-color';
            const statusIcon = result.match ? '✅' : '❌';
            const statusText = result.match ? 'Hashes Match' : 'Hashes Do Not Match';

            let html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid var(--${statusClass});">
                    <h3 style="color: var(--${statusClass}); margin-bottom: 1rem;">${statusIcon} ${statusText}</h3>

                    <p style="margin-top: 1rem;"><strong>First Hash:</strong></p>
                    <code style="display: block; background: var(--dark-bg); padding: 0.75rem; border-radius: 4px; word-break: break-all; margin-bottom: 1rem;">${result.hash1}</code>

                    <p><strong>Second Hash:</strong></p>
                    <code style="display: block; background: var(--dark-bg); padding: 0.75rem; border-radius: 4px; word-break: break-all; margin-bottom: 1rem;">${result.hash2}</code>

                    <p><strong>Comparison:</strong> ${result.match ? 'Identical' : 'Different'}</p>
                    ${result.case_sensitive !== undefined ? `<p><strong>Case-Sensitive Match:</strong> ${result.case_sensitive ? 'Yes' : 'No'}</p>` : ''}
            `;

            if (result.differences && !result.match) {
                html += `<p style="margin-top: 1rem; color: var(--text-secondary);"><strong>Number of Differences:</strong> ${result.differences} character(s)</p>`;
            }

            html += `</div>`;

            document.getElementById('results-content').innerHTML = html;
        }
    </script>
</body>
</html>
