<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DNS Migration Converter - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .tabs {
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
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
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

        .config-preview {
            background: var(--darker-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            max-height: 400px;
            overflow-y: auto;
        }

        .config-preview pre {
            margin: 0;
            color: var(--text-primary);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .download-section {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .info-box {
            background: var(--darker-bg);
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }

        .info-box h4 {
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 0.5rem;
        }

        .info-box ul {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }

        .info-box li {
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
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
                <li><a href="/login.php">Login</a></li>
                <li><a href="/signup.php" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <section style="padding: 8rem 2rem 4rem;">
        <div class="container" style="max-width: 1000px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">DNS Migration Converter</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Convert djbdns/dnscache to Unbound, or BIND to NSD with ease
            </p>

            <div id="alert-container"></div>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" data-tab="dnscache">djbdns → Unbound</button>
                <button class="tab" data-tab="bind">BIND → NSD</button>
            </div>

            <!-- djbdns to Unbound Tab -->
            <div class="tab-content active" id="dnscache-tab">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Convert djbdns/dnscache to Unbound</h2>

                    <div class="info-box">
                        <h4>What this tool does:</h4>
                        <ul>
                            <li>Parses djbdns directory structure from tar.gz archive</li>
                            <li>Extracts upstream DNS servers from <code>root/servers/*</code></li>
                            <li>Converts allowed client IPs from <code>root/ip/*</code></li>
                            <li>Reads environment variables from <code>env/</code></li>
                            <li>Generates complete <code>unbound.conf</code> with DNSSEC validation</li>
                            <li>Optionally converts tinydns <code>data</code> file to NSD zones</li>
                        </ul>
                    </div>

                    <div class="form-group">
                        <label for="dnscache-file">Upload djbdns Configuration Archive</label>
                        <div class="upload-area" id="dnscache-upload-area">
                            <div class="upload-icon">📦</div>
                            <p>Drag & drop tar.gz of /var/dnscache/ or /service/dnscache/</p>
                            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.5rem;">
                                Supports: .tar.gz, .tgz, .zip
                            </p>
                            <input type="file" id="dnscache-file" accept=".tar.gz,.tgz,.zip" style="display: none;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" id="convert-tinydns" style="width: auto;">
                            <span>Also convert tinydns data file to NSD zones (if found)</span>
                        </label>
                    </div>

                    <div id="dnscache-file-info" style="display: none; margin-top: 1.5rem;">
                        <p><strong>Selected file:</strong> <span id="dnscache-filename"></span></p>
                        <p><strong>Size:</strong> <span id="dnscache-filesize"></span></p>
                        <button class="btn btn-primary" id="dnscache-convert-button" style="margin-top: 1rem;">
                            Convert to Unbound
                        </button>
                    </div>
                </div>

                <!-- dnscache Results -->
                <div class="feature-card" id="dnscache-results" style="display: none; margin-top: 2rem;">
                    <h2 style="margin-bottom: 1.5rem;">Conversion Results</h2>
                    <div id="dnscache-results-content"></div>
                </div>
            </div>

            <!-- BIND to NSD Tab -->
            <div class="tab-content" id="bind-tab">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Convert BIND to NSD</h2>

                    <div class="info-box">
                        <h4>What this tool does:</h4>
                        <ul>
                            <li>Parses <code>named.conf</code> and zone configurations</li>
                            <li>Extracts zone definitions (master/slave)</li>
                            <li>Converts TSIG keys for secure zone transfers</li>
                            <li>Generates <code>nsd.conf</code> with modern best practices</li>
                            <li>Validates and converts zone files to NSD format</li>
                            <li>Preserves all DNS records and zone data</li>
                        </ul>
                    </div>

                    <div class="form-group">
                        <label for="bind-file">Upload BIND Configuration Archive</label>
                        <div class="upload-area" id="bind-upload-area">
                            <div class="upload-icon">📦</div>
                            <p>Drag & drop tar.gz containing named.conf and zone files</p>
                            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.5rem;">
                                Supports: .tar.gz, .tgz, .zip
                            </p>
                            <input type="file" id="bind-file" accept=".tar.gz,.tgz,.zip" style="display: none;">
                        </div>
                    </div>

                    <div id="bind-file-info" style="display: none; margin-top: 1.5rem;">
                        <p><strong>Selected file:</strong> <span id="bind-filename"></span></p>
                        <p><strong>Size:</strong> <span id="bind-filesize"></span></p>
                        <button class="btn btn-primary" id="bind-convert-button" style="margin-top: 1rem;">
                            Convert to NSD
                        </button>
                    </div>
                </div>

                <!-- BIND Results -->
                <div class="feature-card" id="bind-results" style="display: none; margin-top: 2rem;">
                    <h2 style="margin-bottom: 1.5rem;">Conversion Results</h2>
                    <div id="bind-results-content"></div>
                </div>
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
        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const tabName = tab.dataset.tab;

                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                tab.classList.add('active');
                document.getElementById(`${tabName}-tab`).classList.add('active');
            });
        });

        // dnscache to Unbound converter
        let dnscacheFile = null;

        const dnscacheUploadArea = document.getElementById('dnscache-upload-area');
        const dnscacheFileInput = document.getElementById('dnscache-file');
        const dnscacheFileInfo = document.getElementById('dnscache-file-info');
        const dnscacheConvertButton = document.getElementById('dnscache-convert-button');
        const dnscacheResults = document.getElementById('dnscache-results');

        dnscacheUploadArea.addEventListener('click', () => dnscacheFileInput.click());

        dnscacheUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            dnscacheUploadArea.classList.add('drag-over');
        });

        dnscacheUploadArea.addEventListener('dragleave', () => {
            dnscacheUploadArea.classList.remove('drag-over');
        });

        dnscacheUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            dnscacheUploadArea.classList.remove('drag-over');
            handleDnscacheFile(e.dataTransfer.files[0]);
        });

        dnscacheFileInput.addEventListener('change', (e) => {
            handleDnscacheFile(e.target.files[0]);
        });

        function handleDnscacheFile(file) {
            if (!file) return;

            dnscacheFile = file;
            document.getElementById('dnscache-filename').textContent = file.name;
            document.getElementById('dnscache-filesize').textContent = formatFileSize(file.size);
            dnscacheFileInfo.style.display = 'block';
        }

        dnscacheConvertButton.addEventListener('click', async () => {
            if (!dnscacheFile) return;

            dnscacheResults.style.display = 'block';
            document.getElementById('dnscache-results-content').innerHTML = '<div class="spinner"></div>';

            const formData = new FormData();
            formData.append('archive', dnscacheFile);
            formData.append('convert_tinydns', document.getElementById('convert-tinydns').checked ? 'true' : 'false');

            try {
                const data = await uploadFile('/api/v1/dns-converter/dnscache-to-unbound', formData);
                displayDnscacheResults(data);
            } catch (error) {
                document.getElementById('dnscache-results-content').innerHTML =
                    `<div class="alert alert-error">${error.message}</div>`;
            }
        });

        function displayDnscacheResults(response) {
            const data = response.data || response;

            let html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: var(--success-color); margin-bottom: 1rem;">Conversion Successful</h3>
                    <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                        <p><strong>Upstream Servers:</strong> ${data.config_details.upstream_servers ? Object.keys(data.config_details.upstream_servers).length : 0}</p>
                        <p><strong>Client IPs:</strong> ${data.config_details.client_ips ? data.config_details.client_ips.length : 0}</p>
                        <p><strong>Cache Size:</strong> ${data.config_details.cache_size}</p>
                        <p><strong>Threads:</strong> ${data.config_details.num_threads}</p>
                    </div>
                </div>
            `;

            // Unbound config
            if (data.unbound_conf) {
                window.unboundConf = data.unbound_conf;
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Unbound Configuration</h3>
                        <div class="config-preview">
                            <pre>${escapeHtml(data.unbound_conf)}</pre>
                        </div>
                        <div class="download-section">
                            <button class="btn btn-primary" onclick="downloadConfigByRef('unboundConf', 'unbound.conf')">
                                Download unbound.conf
                            </button>
                        </div>
                    </div>
                `;
            }

            // NSD config (if tinydns was converted)
            if (data.nsd_conf) {
                window.nsdConf = data.nsd_conf;
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">NSD Configuration (from tinydns)</h3>
                        <div class="config-preview">
                            <pre>${escapeHtml(data.nsd_conf)}</pre>
                        </div>
                        <div class="download-section">
                            <button class="btn btn-primary" onclick="downloadConfigByRef('nsdConf', 'nsd.conf')">
                                Download nsd.conf
                            </button>
                        </div>
                    </div>
                `;
            }

            // Zone files
            if (data.zone_files && Object.keys(data.zone_files).length > 0) {
                window.zoneFiles = data.zone_files;
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Zone Files (${Object.keys(data.zone_files).length})</h3>
                `;

                Object.keys(data.zone_files).forEach((zoneName, index) => {
                    html += `
                        <div style="margin-bottom: 1.5rem;">
                            <h4 style="color: var(--accent-color);">${zoneName}</h4>
                            <div class="config-preview">
                                <pre>${escapeHtml(data.zone_files[zoneName])}</pre>
                            </div>
                            <button class="btn btn-secondary" onclick="downloadZoneFile('${zoneName}')" style="margin-top: 0.5rem;">
                                Download ${zoneName}.zone
                            </button>
                        </div>
                    `;
                });

                html += `</div>`;
            }

            html += `
                <div style="margin-top: 1.5rem; text-align: center;">
                    <button class="btn btn-primary" onclick="location.reload()">Convert Another Configuration</button>
                </div>
            `;

            document.getElementById('dnscache-results-content').innerHTML = html;
        }

        // BIND to NSD converter
        let bindFile = null;

        const bindUploadArea = document.getElementById('bind-upload-area');
        const bindFileInput = document.getElementById('bind-file');
        const bindFileInfo = document.getElementById('bind-file-info');
        const bindConvertButton = document.getElementById('bind-convert-button');
        const bindResults = document.getElementById('bind-results');

        bindUploadArea.addEventListener('click', () => bindFileInput.click());

        bindUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            bindUploadArea.classList.add('drag-over');
        });

        bindUploadArea.addEventListener('dragleave', () => {
            bindUploadArea.classList.remove('drag-over');
        });

        bindUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            bindUploadArea.classList.remove('drag-over');
            handleBindFile(e.dataTransfer.files[0]);
        });

        bindFileInput.addEventListener('change', (e) => {
            handleBindFile(e.target.files[0]);
        });

        function handleBindFile(file) {
            if (!file) return;

            bindFile = file;
            document.getElementById('bind-filename').textContent = file.name;
            document.getElementById('bind-filesize').textContent = formatFileSize(file.size);
            bindFileInfo.style.display = 'block';
        }

        bindConvertButton.addEventListener('click', async () => {
            if (!bindFile) return;

            bindResults.style.display = 'block';
            document.getElementById('bind-results-content').innerHTML = '<div class="spinner"></div>';

            const formData = new FormData();
            formData.append('archive', bindFile);

            try {
                const data = await uploadFile('/api/v1/dns-converter/bind-to-nsd', formData);
                displayBindResults(data);
            } catch (error) {
                document.getElementById('bind-results-content').innerHTML =
                    `<div class="alert alert-error">${error.message}</div>`;
            }
        });

        function displayBindResults(response) {
            const data = response.data || response;

            let html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: var(--success-color); margin-bottom: 1rem;">Conversion Successful</h3>
                    <div style="padding: 1rem; background: var(--dark-bg); border-radius: 4px;">
                        <p><strong>Total Zones:</strong> ${data.config_details.total_zones}</p>
                        <p><strong>Master Zones:</strong> ${data.config_details.master_zones}</p>
                        <p><strong>Slave Zones:</strong> ${data.config_details.slave_zones}</p>
                        <p><strong>TSIG Keys:</strong> ${data.config_details.tsig_keys}</p>
                    </div>
                </div>
            `;

            // NSD config
            if (data.nsd_conf) {
                window.bindNsdConf = data.nsd_conf;
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">NSD Configuration</h3>
                        <div class="config-preview">
                            <pre>${escapeHtml(data.nsd_conf)}</pre>
                        </div>
                        <div class="download-section">
                            <button class="btn btn-primary" onclick="downloadConfigByRef('bindNsdConf', 'nsd.conf')">
                                Download nsd.conf
                            </button>
                        </div>
                    </div>
                `;
            }

            // Zone files
            if (data.zones && Object.keys(data.zones).length > 0) {
                window.bindZones = data.zones;
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Zone Files (${Object.keys(data.zones).length})</h3>
                `;

                Object.keys(data.zones).forEach((zoneName) => {
                    const zoneData = data.zones[zoneName];
                    html += `
                        <div style="margin-bottom: 1.5rem;">
                            <h4 style="color: var(--accent-color);">${zoneName} (${zoneData.type})</h4>
                            <div class="config-preview">
                                <pre>${escapeHtml(zoneData.content)}</pre>
                            </div>
                            <button class="btn btn-secondary" onclick="downloadBindZone('${zoneName}')" style="margin-top: 0.5rem;">
                                Download ${zoneName}.zone
                            </button>
                        </div>
                    `;
                });

                html += `</div>`;
            }

            html += `
                <div style="margin-top: 1.5rem; text-align: center;">
                    <button class="btn btn-primary" onclick="location.reload()">Convert Another Configuration</button>
                </div>
            `;

            document.getElementById('bind-results-content').innerHTML = html;
        }

        // Download configuration file by reference
        function downloadConfigByRef(refName, filename) {
            const content = window[refName];
            if (!content) {
                showAlert('Configuration content not found', 'error');
                return;
            }

            const blob = new Blob([content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // Download zone file
        function downloadZoneFile(zoneName) {
            const content = window.zoneFiles[zoneName];
            if (!content) {
                showAlert('Zone file content not found', 'error');
                return;
            }

            const blob = new Blob([content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = zoneName + '.zone';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // Download BIND zone file
        function downloadBindZone(zoneName) {
            const zoneData = window.bindZones[zoneName];
            if (!zoneData || !zoneData.content) {
                showAlert('Zone file content not found', 'error');
                return;
            }

            const blob = new Blob([zoneData.content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = zoneName + '.zone';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Show alert message
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
