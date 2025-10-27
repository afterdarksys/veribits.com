<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PGP Validator - VeriBits</title>
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
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">PGP Validator</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Validate PGP/GPG public keys and verify signatures
            </p>

            <div id="alert-container"></div>

            <!-- Validation Type Selection -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Select Validation Type</h2>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                    <button class="btn btn-primary type-btn" data-type="key" onclick="selectType('key')" style="width: 100%;">
                        Validate Public Key
                    </button>
                    <button class="btn btn-secondary type-btn" data-type="signature" onclick="selectType('signature')" style="width: 100%;">
                        Verify Signature
                    </button>
                </div>

                <!-- Key Validation Form -->
                <div id="key-form" style="display: block;">
                    <div class="form-group">
                        <label for="public-key">PGP Public Key</label>
                        <textarea id="public-key" rows="12" placeholder="-----BEGIN PGP PUBLIC KEY BLOCK-----&#10;&#10;mQINBGJxY...&#10;&#10;-----END PGP PUBLIC KEY BLOCK-----" style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;"></textarea>
                    </div>

                    <button class="btn btn-primary" onclick="validateKey()" style="width: 100%;">
                        Validate Public Key
                    </button>
                </div>

                <!-- Signature Verification Form -->
                <div id="signature-form" style="display: none;">
                    <div class="form-group">
                        <label for="signed-message">Signed Message or Signature</label>
                        <textarea id="signed-message" rows="8" placeholder="-----BEGIN PGP SIGNED MESSAGE-----&#10;Hash: SHA512&#10;&#10;Message content here&#10;&#10;-----BEGIN PGP SIGNATURE-----&#10;&#10;iQIzBAEBCgAdFiEE...&#10;&#10;-----END PGP SIGNATURE-----" style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="verify-key">Public Key for Verification</label>
                        <textarea id="verify-key" rows="8" placeholder="-----BEGIN PGP PUBLIC KEY BLOCK-----&#10;&#10;mQINBGJxY...&#10;&#10;-----END PGP PUBLIC KEY BLOCK-----" style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;"></textarea>
                    </div>

                    <button class="btn btn-primary" onclick="verifySignature()" style="width: 100%;">
                        Verify Signature
                    </button>
                </div>
            </div>

            <!-- Results Section -->
            <div class="feature-card" id="results-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Validation Results</h2>
                <div id="results-content"></div>
            </div>

            <!-- Information Section -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">About PGP Validation</h2>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Public Key Validation</h3>
                <p style="margin-bottom: 1rem;">
                    Validates the format and structure of PGP public keys. This tool checks:
                </p>
                <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                    <li>Correct PGP armor format</li>
                    <li>Key fingerprint and ID</li>
                    <li>User IDs and email addresses</li>
                    <li>Key algorithm and bit length</li>
                    <li>Creation and expiration dates</li>
                    <li>Self-signatures</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Signature Verification</h3>
                <p style="margin-bottom: 1rem;">
                    Verifies PGP signatures to ensure message authenticity and integrity. This tool checks:
                </p>
                <ul style="margin-left: 1.5rem;">
                    <li>Signature validity</li>
                    <li>Signer's identity</li>
                    <li>Message integrity</li>
                    <li>Signature timestamp</li>
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

    <script src="/assets/js/main.js"></script>
    <script>
        let selectedType = 'key';

        function selectType(type) {
            selectedType = type;

            // Update button styles
            document.querySelectorAll('.type-btn').forEach(btn => {
                if (btn.dataset.type === type) {
                    btn.classList.remove('btn-secondary');
                    btn.classList.add('btn-primary');
                } else {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-secondary');
                }
            });

            // Show/hide appropriate form
            document.getElementById('key-form').style.display = type === 'key' ? 'block' : 'none';
            document.getElementById('signature-form').style.display = type === 'signature' ? 'block' : 'none';
        }

        async function validateKey() {
            const publicKey = document.getElementById('public-key').value.trim();

            if (!publicKey) {
                showAlert('Please enter a PGP public key', 'error');
                return;
            }

            const resultsSection = document.getElementById('results-section');
            const resultsContent = document.getElementById('results-content');

            resultsSection.style.display = 'block';
            resultsContent.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="spinner" style="margin: 0 auto;"></div><p style="margin-top: 1rem; color: var(--text-secondary);">Validating PGP key...</p></div>';

            try {
                const data = await apiRequest('/tools/pgp-validate', {
                    method: 'POST',
                    body: JSON.stringify({
                        public_key: publicKey,
                        operation: 'validate_key'
                    })
                });

                displayKeyResults(data.data);
            } catch (error) {
                resultsContent.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        }

        async function verifySignature() {
            const signedMessage = document.getElementById('signed-message').value.trim();
            const publicKey = document.getElementById('verify-key').value.trim();

            if (!signedMessage) {
                showAlert('Please enter a signed message or signature', 'error');
                return;
            }

            if (!publicKey) {
                showAlert('Please enter the public key for verification', 'error');
                return;
            }

            const resultsSection = document.getElementById('results-section');
            const resultsContent = document.getElementById('results-content');

            resultsSection.style.display = 'block';
            resultsContent.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="spinner" style="margin: 0 auto;"></div><p style="margin-top: 1rem; color: var(--text-secondary);">Verifying signature...</p></div>';

            try {
                const data = await apiRequest('/tools/pgp-validate', {
                    method: 'POST',
                    body: JSON.stringify({
                        signed_message: signedMessage,
                        public_key: publicKey,
                        operation: 'verify_signature'
                    })
                });

                displaySignatureResults(data.data);
            } catch (error) {
                resultsContent.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        }

        function displayKeyResults(result) {
            const statusClass = result.is_valid ? 'success-color' : 'error-color';
            const statusIcon = result.is_valid ? '✅' : '❌';
            const statusText = result.is_valid ? 'Valid PGP Public Key' : 'Invalid PGP Public Key';

            let html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid var(--${statusClass});">
                    <h3 style="color: var(--${statusClass}); margin-bottom: 1rem;">${statusIcon} ${statusText}</h3>
            `;

            if (result.is_valid && result.key_info) {
                const info = result.key_info;

                html += `
                    <div style="margin-top: 1rem;">
                        <p style="margin-bottom: 0.5rem;"><strong>Fingerprint:</strong></p>
                        <code style="display: block; background: var(--dark-bg); padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem; word-break: break-all;">${info.fingerprint || 'N/A'}</code>

                        <p><strong>Key ID:</strong> ${info.key_id || 'N/A'}</p>
                        <p><strong>Algorithm:</strong> ${info.algorithm || 'N/A'}</p>
                        <p><strong>Bit Length:</strong> ${info.bit_length || 'N/A'}</p>
                        <p><strong>Created:</strong> ${info.created_at || 'N/A'}</p>
                        ${info.expires_at ? `<p><strong>Expires:</strong> ${info.expires_at}</p>` : ''}
                `;

                if (info.user_ids && info.user_ids.length > 0) {
                    html += `<p style="margin-top: 1rem;"><strong>User IDs:</strong></p><ul style="margin-left: 1.5rem;">`;
                    info.user_ids.forEach(uid => {
                        html += `<li>${uid}</li>`;
                    });
                    html += `</ul>`;
                }

                html += `</div>`;
            } else if (result.error) {
                html += `<p style="color: var(--error-color); margin-top: 1rem;"><strong>Error:</strong> ${result.error}</p>`;
            }

            html += `</div>`;

            document.getElementById('results-content').innerHTML = html;
        }

        function displaySignatureResults(result) {
            const statusClass = result.is_valid ? 'success-color' : 'error-color';
            const statusIcon = result.is_valid ? '✅' : '❌';
            const statusText = result.is_valid ? 'Valid Signature' : 'Invalid Signature';

            let html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid var(--${statusClass});">
                    <h3 style="color: var(--${statusClass}); margin-bottom: 1rem;">${statusIcon} ${statusText}</h3>
            `;

            if (result.signature_info) {
                const info = result.signature_info;

                html += `
                    <div style="margin-top: 1rem;">
                        ${info.signer ? `<p><strong>Signer:</strong> ${info.signer}</p>` : ''}
                        ${info.key_id ? `<p><strong>Key ID:</strong> ${info.key_id}</p>` : ''}
                        ${info.timestamp ? `<p><strong>Signed:</strong> ${info.timestamp}</p>` : ''}
                        ${info.hash_algorithm ? `<p><strong>Hash Algorithm:</strong> ${info.hash_algorithm}</p>` : ''}
                `;

                if (result.message) {
                    html += `
                        <p style="margin-top: 1rem;"><strong>Message:</strong></p>
                        <pre style="background: var(--dark-bg); padding: 1rem; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word;">${result.message}</pre>
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
    </script>
</body>
</html>
