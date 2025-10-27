<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VeriBits - Advanced File Verification & Security Tools</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <style>
        .nav-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1rem;
        }
        .nav-logo {
            margin-right: 1rem;
            flex-shrink: 0;
        }
        .nav-search {
            flex: 1;
            max-width: 400px;
            position: relative;
        }
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 1.25rem;
            margin: 0;
            margin-left: auto;
            align-items: center;
            flex-shrink: 0;
        }
        @media (max-width: 1200px) {
            .nav-search {
                max-width: 300px;
            }
            .nav-menu {
                gap: 1rem;
            }
        }
        @media (max-width: 992px) {
            .nav-search {
                max-width: 200px;
            }
            .nav-menu {
                gap: 0.75rem;
            }
        }
        @media (max-width: 768px) {
            .nav-container {
                flex-wrap: wrap;
                gap: 0.75rem;
            }
            .nav-search {
                order: 3;
                flex: 1 1 100%;
                max-width: 100%;
                margin-top: 0.5rem;
            }
            .nav-menu {
                margin-left: auto;
                font-size: 0.9rem;
            }
        }
        @media (max-width: 576px) {
            .nav-menu {
                gap: 0.5rem;
                font-size: 0.85rem;
            }
            .nav-menu li a {
                padding: 0.4rem 0.6rem;
            }
        }
    </style>
    <nav>
        <div class="container nav-container">
            <a href="/" class="logo nav-logo">VeriBits</a>

            <div class="nav-search">
                <input
                    type="text"
                    id="tool-search"
                    placeholder="Search tools..."
                    autocomplete="off"
                    style="width: 100%; padding: 0.625rem 1rem; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 6px; color: white; font-size: 0.9rem;"
                />
                <button
                    id="search-go"
                    onclick="performToolSearch()"
                    style="position: absolute; right: 4px; top: 50%; transform: translateY(-50%); padding: 0.4rem 1rem; background: var(--primary-color); border: none; border-radius: 4px; color: white; cursor: pointer; font-weight: 600; font-size: 0.85rem; z-index: 10;"
                >
                    Go
                </button>
                <div id="search-autocomplete" style="position: absolute; top: 100%; left: 0; right: 0; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 6px; margin-top: 0.25rem; display: none; z-index: 1000; max-height: 400px; overflow-y: auto; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);"></div>
            </div>

            <ul class="nav-menu">
                <li><a href="/tools.php">Tools</a></li>
                <li><a href="/cli.php">CLI</a></li>
                <li><a href="/docs.php">Docs</a></li>
                <li><a href="/pricing.php">Pricing</a></li>
                <li><a href="/about.php">About</a></li>
                <li><a href="/login.php">Login</a></li>
                <li><a href="/signup.php" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-content">
            <h1>Verify. Validate. Trust.</h1>
            <p>Professional file verification and cryptographic validation tools for developers, security professionals, and businesses.</p>
            <div class="hero-buttons">
                <a href="/signup.php" class="btn btn-primary">Start Free Trial</a>
                <a href="/tools.php" class="btn btn-secondary">Explore Tools</a>
                <a href="#demo" class="btn btn-secondary" style="background: transparent; border: 2px solid var(--primary-color);">Try Live Demo</a>
            </div>
            <p style="margin-top: 2rem; color: var(--text-secondary); font-size: 0.95rem;">
                ✓ 5 Free Scans  ✓ No Credit Card Required  ✓ 50MB File Limit
            </p>
        </div>
    </section>

    <section class="features">
        <div class="container">
            <h2>Powerful Verification Tools</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🔍</div>
                    <h3>File Magic Detection</h3>
                    <p>Identify file types by analyzing magic numbers and headers. Detect 40+ file formats instantly.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">✍️</div>
                    <h3>Signature Verification</h3>
                    <p>Verify PGP, JAR, AIR, and macOS code signatures. Validate file authenticity and integrity.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔐</div>
                    <h3>Hash Validation</h3>
                    <p>Compare file hashes (MD5, SHA-1, SHA-256, SHA-512) to verify file integrity.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🎭</div>
                    <h3>Steganography Detection</h3>
                    <p>Detect hidden data in images and files. Identify potential steganographic content.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔑</div>
                    <h3>PGP Key Validation</h3>
                    <p>Validate PGP public keys, check expiration, and verify key fingerprints.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🌐</div>
                    <h3>DNS & IP Tools</h3>
                    <p>DNS validation, IP address calculations, and network security analysis.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📝</div>
                    <h3>Base64 Encoder/Decoder</h3>
                    <p>Encode and decode Base64 data for various applications and protocols.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <h3>Fast & Secure</h3>
                    <p>Enterprise-grade infrastructure with SSL encryption and instant results.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Live Demo Section -->
    <section id="demo" style="padding: 4rem 2rem; background: linear-gradient(180deg, rgba(30, 64, 175, 0.1) 0%, rgba(30, 64, 175, 0.05) 100%);">
        <div class="container">
            <h2 style="text-align: center; margin-bottom: 1rem;">Try It Live</h2>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem; font-size: 1.1rem;">
                No signup required - test our tools right now
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem; max-width: 1200px; margin: 0 auto;">
                <!-- JWT Demo -->
                <div class="feature-card">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">🔑 JWT Decoder</h3>
                    <textarea id="demo-jwt" rows="4" style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-primary); font-family: monospace; font-size: 0.9rem; resize: vertical;" placeholder="Paste JWT token here...">eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c</textarea>
                    <button onclick="decodeJWTDemo()" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Decode JWT</button>
                    <div id="jwt-result" style="margin-top: 1rem; padding: 1rem; background: var(--darker-bg); border-radius: 4px; display: none;">
                        <pre id="jwt-output" style="margin: 0; color: var(--accent-color); font-size: 0.85rem; white-space: pre-wrap;"></pre>
                    </div>
                </div>

                <!-- Crypto Validator Demo -->
                <div class="feature-card">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">₿ Crypto Validator</h3>
                    <input type="text" id="demo-crypto" style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-primary); font-family: monospace;" placeholder="Bitcoin or Ethereum address..." value="1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa">
                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                        <button onclick="validateCrypto('bitcoin')" class="btn btn-secondary" style="flex: 1;">Bitcoin</button>
                        <button onclick="validateCrypto('ethereum')" class="btn btn-secondary" style="flex: 1;">Ethereum</button>
                    </div>
                    <div id="crypto-result" style="margin-top: 1rem; padding: 1rem; background: var(--darker-bg); border-radius: 4px; display: none;">
                        <div id="crypto-output"></div>
                    </div>
                </div>

                <!-- Regex Tester Demo -->
                <div class="feature-card">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">🔤 Regex Tester</h3>
                    <input type="text" id="demo-regex" style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-primary); font-family: monospace; margin-bottom: 0.5rem;" placeholder="Regex pattern..." value="\d{3}-\d{3}-\d{4}">
                    <textarea id="demo-regex-text" rows="3" style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-primary); resize: vertical;" placeholder="Test text...">Call me at 555-123-4567 or 555-987-6543</textarea>
                    <button onclick="testRegexDemo()" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Test Regex</button>
                    <div id="regex-result" style="margin-top: 1rem; padding: 1rem; background: var(--darker-bg); border-radius: 4px; display: none;">
                        <div id="regex-output"></div>
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 3rem;">
                <p style="color: var(--text-secondary); margin-bottom: 1rem;">Want access to all 18+ tools?</p>
                <a href="/signup.php" class="btn btn-primary" style="padding: 0.75rem 2rem;">Start Free Trial</a>
            </div>
        </div>
    </section>

    <script>
        async function decodeJWTDemo() {
            const token = document.getElementById('demo-jwt').value.trim();
            const resultDiv = document.getElementById('jwt-result');
            const outputDiv = document.getElementById('jwt-output');

            if (!token) {
                alert('Please enter a JWT token');
                return;
            }

            try {
                const response = await fetch('/api/v1/jwt/decode', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token, verify_signature: false })
                });

                const result = await response.json();

                if (result.success) {
                    const data = result.data;
                    const output = {
                        header: data.header,
                        payload: data.payload,
                        claims: data.claims
                    };
                    outputDiv.textContent = JSON.stringify(output, null, 2);
                    resultDiv.style.display = 'block';
                } else {
                    outputDiv.textContent = '❌ Error: ' + result.error.message;
                    resultDiv.style.display = 'block';
                }
            } catch (error) {
                outputDiv.textContent = '❌ Error: ' + error.message;
                resultDiv.style.display = 'block';
            }
        }

        async function validateCrypto(type) {
            const address = document.getElementById('demo-crypto').value.trim();
            const resultDiv = document.getElementById('crypto-result');
            const outputDiv = document.getElementById('crypto-output');

            if (!address) {
                alert('Please enter a cryptocurrency address');
                return;
            }

            try {
                const response = await fetch(`/api/v1/crypto/validate/${type}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ value: address, type: 'address' })
                });

                const result = await response.json();

                if (result.success) {
                    const data = result.data;
                    const isValid = data.is_valid;
                    outputDiv.innerHTML = `
                        <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">${isValid ? '✅' : '❌'}</div>
                        <div style="color: ${isValid ? 'var(--accent-color)' : '#ef4444'}; font-weight: bold; margin-bottom: 0.5rem;">
                            ${isValid ? 'Valid' : 'Invalid'} ${type.charAt(0).toUpperCase() + type.slice(1)} Address
                        </div>
                        ${data.format ? `<div style="color: var(--text-secondary); font-size: 0.9rem;">Format: ${data.format}</div>` : ''}
                        ${data.network ? `<div style="color: var(--text-secondary); font-size: 0.9rem;">Network: ${data.network}</div>` : ''}
                    `;
                    resultDiv.style.display = 'block';
                } else {
                    outputDiv.innerHTML = '<div style="color: #ef4444;">❌ Error: ' + result.error.message + '</div>';
                    resultDiv.style.display = 'block';
                }
            } catch (error) {
                outputDiv.innerHTML = '<div style="color: #ef4444;">❌ Error: ' + error.message + '</div>';
                resultDiv.style.display = 'block';
            }
        }

        async function testRegexDemo() {
            const pattern = document.getElementById('demo-regex').value.trim();
            const text = document.getElementById('demo-regex-text').value;
            const resultDiv = document.getElementById('regex-result');
            const outputDiv = document.getElementById('regex-output');

            if (!pattern) {
                alert('Please enter a regex pattern');
                return;
            }

            try {
                const response = await fetch('/api/v1/tools/regex-test', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pattern, text, flags: 'g' })
                });

                const result = await response.json();

                if (result.success) {
                    const data = result.data;
                    let html = `
                        <div style="margin-bottom: 1rem;">
                            <strong>Matches Found:</strong> <span style="color: var(--primary-color);">${data.match_count}</span>
                        </div>
                    `;

                    if (data.matches && data.matches.length > 0) {
                        html += '<div style="font-size: 0.9rem;">';
                        data.matches.forEach((match, i) => {
                            html += `
                                <div style="padding: 0.5rem; background: rgba(251, 191, 36, 0.1); border-left: 3px solid var(--primary-color); margin-bottom: 0.5rem; border-radius: 3px;">
                                    <strong>Match ${i + 1}:</strong> <code style="color: var(--accent-color);">${match.match}</code>
                                    <span style="color: var(--text-secondary); margin-left: 0.5rem; font-size: 0.85rem;">@ position ${match.position}</span>
                                </div>
                            `;
                        });
                        html += '</div>';
                    } else {
                        html += '<div style="color: var(--text-secondary);">No matches found</div>';
                    }

                    outputDiv.innerHTML = html;
                    resultDiv.style.display = 'block';
                } else {
                    outputDiv.innerHTML = '<div style="color: #ef4444;">❌ Error: ' + result.error.message + '</div>';
                    resultDiv.style.display = 'block';
                }
            } catch (error) {
                outputDiv.innerHTML = '<div style="color: #ef4444;">❌ Error: ' + error.message + '</div>';
                resultDiv.style.display = 'block';
            }
        }
    </script>

    <section class="pricing">
        <div class="container">
            <h2>Simple, Transparent Pricing</h2>
            <div class="pricing-grid">
                <div class="pricing-card">
                    <h3>Free Trial</h3>
                    <div class="price">$0</div>
                    <div class="price-period">5 scans</div>
                    <ul class="pricing-features">
                        <li>5 free scans</li>
                        <li>50MB file limit</li>
                        <li>All verification tools</li>
                        <li>30-day window</li>
                        <li>No credit card required</li>
                    </ul>
                    <a href="/signup.php" class="btn btn-primary">Start Free</a>
                </div>

                <div class="pricing-card featured">
                    <h3>Monthly</h3>
                    <div class="price">$9.99<span class="price-period">/month</span></div>
                    <ul class="pricing-features">
                        <li>100 scans per month</li>
                        <li>200MB file limit</li>
                        <li>Priority processing</li>
                        <li>Email support</li>
                        <li>API access</li>
                        <li>Scan history</li>
                    </ul>
                    <a href="/signup.php" class="btn btn-primary">Get Started</a>
                </div>

                <div class="pricing-card">
                    <h3>Annual</h3>
                    <div class="price">$99<span class="price-period">/year</span></div>
                    <ul class="pricing-features">
                        <li>1,500 scans per year</li>
                        <li>500MB file limit</li>
                        <li>Priority processing</li>
                        <li>Premium support</li>
                        <li>API access</li>
                        <li>Save 17%</li>
                    </ul>
                    <a href="/signup.php" class="btn btn-primary">Save $21/year</a>
                </div>

                <div class="pricing-card">
                    <h3>Enterprise</h3>
                    <div class="price">Custom</div>
                    <div class="price-period">Contact us</div>
                    <ul class="pricing-features">
                        <li>Unlimited scans</li>
                        <li>Custom file limits</li>
                        <li>Dedicated support</li>
                        <li>SLA guarantee</li>
                        <li>Custom integrations</li>
                        <li>On-premise option</li>
                    </ul>
                    <a href="mailto:sales@veribits.com" class="btn btn-secondary">Contact Sales</a>
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

    <script>
        // Tool search autocomplete
        let searchTimeout;
        const searchInput = document.getElementById('tool-search');
        const autocompleteDiv = document.getElementById('search-autocomplete');

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length < 2) {
                autocompleteDiv.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(async () => {
                try {
                    const response = await fetch(`/api/v1/tools/search?q=${encodeURIComponent(query)}`);
                    const result = await response.json();

                    if (result.success && result.data.tools && result.data.tools.length > 0) {
                        displayAutocomplete(result.data.tools);
                    } else {
                        autocompleteDiv.style.display = 'none';
                    }
                } catch (error) {
                    console.error('Search error:', error);
                }
            }, 200);
        });

        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performToolSearch();
            }
        });

        // Close autocomplete when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-search')) {
                autocompleteDiv.style.display = 'none';
            }
        });

        function displayAutocomplete(tools) {
            let html = '';
            const maxResults = Math.min(tools.length, 8); // Limit to 8 results
            for (let i = 0; i < maxResults; i++) {
                const tool = tools[i];
                html += `
                    <div class="autocomplete-item" onclick="navigateToTool('${tool.url}')" style="padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid var(--border-color); transition: background 0.2s;" onmouseover="this.style.background='rgba(251, 191, 36, 0.1)'" onmouseout="this.style.background='transparent'">
                        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 0.25rem;">${tool.name}</div>
                        <div style="font-size: 0.85rem; color: var(--text-secondary);">${tool.description}</div>
                        <div style="margin-top: 0.25rem; font-size: 0.75rem; color: var(--primary-color);">${tool.category}</div>
                    </div>
                `;
            }
            if (tools.length > maxResults) {
                html += `<div style="padding: 0.75rem 1rem; text-align: center; color: var(--text-secondary); font-size: 0.85rem;">+${tools.length - maxResults} more results - click Go to see all</div>`;
            }
            autocompleteDiv.innerHTML = html;
            autocompleteDiv.style.display = 'block';
        }

        function navigateToTool(path) {
            window.location.href = path;
        }

        async function performToolSearch() {
            const query = searchInput.value.trim();

            if (!query) {
                return;
            }

            if (query.length < 2) {
                alert('Please enter at least 2 characters');
                return;
            }

            try {
                const response = await fetch(`/api/v1/tools/search?q=${encodeURIComponent(query)}`);
                const result = await response.json();

                if (result.success && result.data.tools && result.data.tools.length > 0) {
                    // If single exact match, go directly to that tool
                    if (result.data.tools.length === 1) {
                        window.location.href = result.data.tools[0].url;
                    } else {
                        // Multiple results - show search results page
                        window.location.href = `/search.php?q=${encodeURIComponent(query)}`;
                    }
                } else {
                    // No results - show search results page anyway
                    window.location.href = `/search.php?q=${encodeURIComponent(query)}`;
                }
            } catch (error) {
                console.error('Search error:', error);
                alert('Search failed. Please try again.');
            }
        }
    </script>

    <script src="/assets/js/main.js"></script>
</body>
</html>
<!-- version hash: 86db590587abafc7e6e5e85429d703db3c8280a29dc86a360909573de77186c3 -->
