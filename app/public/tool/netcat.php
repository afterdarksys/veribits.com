<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Netcat - Network Swiss Army Knife - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
    <style>
        .mode-toggle {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            justify-content: center;
        }
        .mode-btn {
            padding: 0.75rem 2rem;
            background: var(--darker-bg);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-secondary);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .mode-btn.active {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border-color: var(--primary-color);
            color: white;
        }
        .advanced-options {
            display: none;
            padding: 1.5rem;
            background: rgba(255,255,255,0.02);
            border-radius: 8px;
            margin-top: 1rem;
        }
        .advanced-options.show {
            display: block;
        }
        .output-box {
            background: var(--darker-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 500px;
            overflow-y: auto;
            color: var(--text-primary);
        }
        .protocol-select {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .protocol-option {
            flex: 1;
            padding: 1rem;
            background: var(--darker-bg);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        .protocol-option.selected {
            border-color: var(--primary-color);
            background: rgba(251, 191, 36, 0.1);
        }
    </style>
</head>
<body>
    <nav>
        <div class="container">
            <a href="/" class="logo">VeriBits</a>
            <ul>
                <li><a href="/tools.php">Tools</a></li>
                <li><a href="/cli.php">CLI</a></li>
                <li><a href="/pricing.php">Pricing</a></li>
                <li><a href="/security.php">Security</a></li>
                <li><a href="/about.php">About</a></li>
                <li data-auth-item="true"><a href="/login.php">Login</a></li>
                <li data-auth-item="true"><a href="/signup.php" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <section style="padding: 8rem 2rem 4rem;">
        <div class="container" style="max-width: 1000px;">
            <div style="text-align: center;">
                <div style="display: inline-block; background: linear-gradient(135deg, #f093fb, #f5576c); color: white; padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.85rem; font-weight: bold; margin-bottom: 1rem; box-shadow: 0 4px 12px rgba(240, 147, 251, 0.3);">
                    ⭐ PRO FEATURE
                </div>
            </div>
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">🔌 Netcat</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Network Swiss Army Knife - TCP/UDP Connection Testing & Port Scanner
            </p>

            <div id="alert-container"></div>

            <!-- Mode Toggle -->
            <div class="mode-toggle">
                <button class="mode-btn active" onclick="setMode('simple')">
                    📝 Simple Mode
                </button>
                <button class="mode-btn" onclick="setMode('advanced')">
                    ⚙️ Advanced Mode
                </button>
            </div>

            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Network Connection Utility</h2>

                <!-- Protocol Selection -->
                <div class="form-group">
                    <label>Protocol</label>
                    <div class="protocol-select">
                        <div class="protocol-option selected" onclick="selectProtocol('tcp')" id="tcp-option">
                            <strong>TCP</strong>
                            <p style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 0.5rem;">
                                Reliable connection-oriented protocol
                            </p>
                        </div>
                        <div class="protocol-option" onclick="selectProtocol('udp')" id="udp-option">
                            <strong>UDP</strong>
                            <p style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 0.5rem;">
                                Fast connectionless protocol
                            </p>
                        </div>
                    </div>
                    <input type="hidden" id="protocol" value="tcp">
                </div>

                <!-- Basic Options -->
                <div class="form-group">
                    <label for="host">Target Host</label>
                    <input type="text" id="host" placeholder="example.com or 192.168.1.1" class="form-control">
                    <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">
                        Hostname or IP address
                    </small>
                </div>

                <div class="form-group">
                    <label for="port">Port</label>
                    <input type="number" id="port" placeholder="80" class="form-control" min="1" max="65535">
                    <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">
                        Port number (1-65535)
                    </small>
                </div>

                <div class="form-group">
                    <label for="data">Data to Send (Optional)</label>
                    <textarea id="data" rows="4" class="form-control" placeholder="GET / HTTP/1.1&#10;Host: example.com&#10;&#10;"></textarea>
                    <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">
                        Data to send after connection. Leave empty for connection test only.
                    </small>
                </div>

                <!-- Advanced Options -->
                <div class="advanced-options" id="advanced-options">
                    <h3 style="margin-bottom: 1rem; color: var(--accent-color);">Advanced Options</h3>

                    <div class="form-group">
                        <label for="timeout">Connection Timeout (seconds)</label>
                        <input type="number" id="timeout" value="5" class="form-control" min="1" max="60">
                    </div>

                    <div class="form-group">
                        <label for="wait-time">Wait Time for Response (seconds)</label>
                        <input type="number" id="wait-time" value="2" class="form-control" min="1" max="30">
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="verbose">
                            Verbose Output (show connection details)
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="zero-io">
                            Zero I/O Mode (scan only, no data transfer)
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="source-port">Source Port (Optional)</label>
                        <input type="number" id="source-port" placeholder="Auto" class="form-control" min="1" max="65535">
                        <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">
                            Specify source port for connection
                        </small>
                    </div>
                </div>

                <button class="btn btn-primary" onclick="executeNetcat()" style="width: 100%;">
                    ▶ Execute Netcat
                </button>

                <div id="results" style="margin-top: 2rem;"></div>
            </div>

            <!-- Quick Actions -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Quick Actions</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <button class="btn btn-secondary" onclick="quickAction('http')">
                        🌐 Test HTTP Server
                    </button>
                    <button class="btn btn-secondary" onclick="quickAction('https')">
                        🔒 Test HTTPS Server
                    </button>
                    <button class="btn btn-secondary" onclick="quickAction('ssh')">
                        🔑 Test SSH Server
                    </button>
                    <button class="btn btn-secondary" onclick="quickAction('smtp')">
                        📧 Test SMTP Server
                    </button>
                    <button class="btn btn-secondary" onclick="quickAction('dns')">
                        🌍 Test DNS Server
                    </button>
                    <button class="btn btn-secondary" onclick="quickAction('mysql')">
                        🗄️ Test MySQL Server
                    </button>
                </div>
            </div>

            <!-- Common Use Cases -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Common Use Cases</h2>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Port Scanning</h3>
                <p>Check if a port is open on a remote host:</p>
                <pre style="background: var(--darker-bg); padding: 1rem; border-radius: 4px; margin-bottom: 1rem;"><code>Host: example.com
Port: 80
Protocol: TCP
Zero I/O Mode: ✓</code></pre>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Banner Grabbing</h3>
                <p>Connect to a service and grab its banner:</p>
                <pre style="background: var(--darker-bg); padding: 1rem; border-radius: 4px; margin-bottom: 1rem;"><code>Host: example.com
Port: 22 (SSH) or 25 (SMTP)
Send Data: (leave empty)</code></pre>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">HTTP Request</h3>
                <p>Send a custom HTTP request:</p>
                <pre style="background: var(--darker-bg); padding: 1rem; border-radius: 4px; margin-bottom: 1rem;"><code>Host: example.com
Port: 80
Data to Send:
GET / HTTP/1.1
Host: example.com

(blank line at end is important)</code></pre>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">UDP Testing</h3>
                <p>Test UDP services like DNS:</p>
                <pre style="background: var(--darker-bg); padding: 1rem; border-radius: 4px;"><code>Host: 8.8.8.8
Port: 53
Protocol: UDP
Data: (DNS query packet)</code></pre>
            </div>

            <!-- Information -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">About Netcat</h2>
                <p style="margin-bottom: 1rem;">
                    Netcat is often referred to as the "Swiss Army knife" of networking tools. It's a versatile utility
                    that can read and write data across network connections using TCP or UDP protocols.
                </p>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Features</h3>
                <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                    <li>TCP and UDP connection testing</li>
                    <li>Port scanning and availability checks</li>
                    <li>Banner grabbing for service identification</li>
                    <li>Custom data transmission</li>
                    <li>Connection timeout configuration</li>
                    <li>Verbose debugging output</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Security Note</h3>
                <p style="padding: 1rem; background: rgba(239, 68, 68, 0.1); border-left: 3px solid #ef4444; border-radius: 4px;">
                    <strong>⚠️ Important:</strong> Only test connections to hosts and services you own or have explicit permission to test.
                    Unauthorized port scanning or connection attempts may be illegal in your jurisdiction.
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
        function setMode(mode) {
            // Update buttons
            document.querySelectorAll('.mode-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            // Show/hide advanced options
            const advancedDiv = document.getElementById('advanced-options');
            if (mode === 'advanced') {
                advancedDiv.classList.add('show');
            } else {
                advancedDiv.classList.remove('show');
            }
        }

        function selectProtocol(protocol) {
            document.getElementById('protocol').value = protocol;
            document.querySelectorAll('.protocol-option').forEach(opt => opt.classList.remove('selected'));
            document.getElementById(protocol + '-option').classList.add('selected');
        }

        async function executeNetcat() {
            const host = document.getElementById('host').value.trim();
            const port = parseInt(document.getElementById('port').value);
            const protocol = document.getElementById('protocol').value;
            const data = document.getElementById('data').value;

            if (!host) {
                showAlert('Please enter a target host', 'error');
                return;
            }

            if (!port || port < 1 || port > 65535) {
                showAlert('Please enter a valid port (1-65535)', 'error');
                return;
            }

            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '<div class="spinner"></div><p style="text-align: center; margin-top: 1rem;">Connecting to ' + host + ':' + port + '...</p>';

            const payload = {
                host: host,
                port: port,
                protocol: protocol,
                data: data || null,
                timeout: parseInt(document.getElementById('timeout')?.value || 5),
                wait_time: parseInt(document.getElementById('wait-time')?.value || 2),
                verbose: document.getElementById('verbose')?.checked || false,
                zero_io: document.getElementById('zero-io')?.checked || false,
                source_port: document.getElementById('source-port')?.value || null
            };

            try {
                const result = await apiRequest('/api/v1/tools/netcat', {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });

                displayResults(result.data);
            } catch (error) {
                resultsDiv.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        }

        function displayResults(data) {
            const resultsDiv = document.getElementById('results');

            let html = '<h3 style="margin-bottom: 1rem;">Results</h3>';

            // Connection Status
            html += '<div style="padding: 1rem; background: ' +
                (data.connected ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)') +
                '; border-left: 3px solid ' +
                (data.connected ? '#22c55e' : '#ef4444') +
                '; border-radius: 4px; margin-bottom: 1rem;">';

            html += '<strong>Status:</strong> ' + (data.connected ? '✓ Connected' : '✗ Connection Failed') + '<br>';
            html += '<strong>Host:</strong> ' + data.host + '<br>';
            html += '<strong>Port:</strong> ' + data.port + '<br>';
            html += '<strong>Protocol:</strong> ' + data.protocol.toUpperCase() + '<br>';

            if (data.connection_time) {
                html += '<strong>Connection Time:</strong> ' + data.connection_time + 'ms<br>';
            }

            html += '</div>';

            // Response Data
            if (data.response) {
                html += '<div style="margin-bottom: 1rem;">';
                html += '<strong>Response:</strong>';
                html += '<div class="output-box">' + escapeHtml(data.response) + '</div>';
                html += '<p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.5rem;">';
                html += 'Received ' + data.bytes_received + ' bytes';
                html += '</p>';
                html += '</div>';
            }

            // Banner (if detected)
            if (data.banner) {
                html += '<div style="margin-bottom: 1rem;">';
                html += '<strong>Banner:</strong>';
                html += '<div class="output-box">' + escapeHtml(data.banner) + '</div>';
                html += '</div>';
            }

            // Service Info
            if (data.service_name) {
                html += '<div style="padding: 1rem; background: rgba(59, 130, 246, 0.1); border-left: 3px solid #3b82f6; border-radius: 4px; margin-bottom: 1rem;">';
                html += '<strong>Detected Service:</strong> ' + data.service_name;
                if (data.service_description) {
                    html += '<br><span style="color: var(--text-secondary);">' + data.service_description + '</span>';
                }
                html += '</div>';
            }

            // Error
            if (data.error) {
                html += '<div class="alert alert-error">' + escapeHtml(data.error) + '</div>';
            }

            // Verbose Output
            if (data.verbose_output) {
                html += '<details style="margin-top: 1rem;">';
                html += '<summary style="cursor: pointer; color: var(--primary-color); font-weight: 600;">Verbose Output</summary>';
                html += '<div class="output-box" style="margin-top: 1rem;">' + escapeHtml(data.verbose_output) + '</div>';
                html += '</details>';
            }

            resultsDiv.innerHTML = html;
        }

        function quickAction(type) {
            const actions = {
                'http': { host: 'example.com', port: 80, data: 'GET / HTTP/1.1\nHost: example.com\n\n' },
                'https': { host: 'example.com', port: 443, data: '' },
                'ssh': { host: 'example.com', port: 22, data: '' },
                'smtp': { host: 'mail.example.com', port: 25, data: '' },
                'dns': { host: '8.8.8.8', port: 53, data: '', protocol: 'udp' },
                'mysql': { host: 'localhost', port: 3306, data: '' }
            };

            const action = actions[type];
            document.getElementById('host').value = action.host;
            document.getElementById('port').value = action.port;
            document.getElementById('data').value = action.data;

            if (action.protocol === 'udp') {
                selectProtocol('udp');
            } else {
                selectProtocol('tcp');
            }

            showAlert('Quick action loaded! Modify as needed and click Execute.', 'success');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
