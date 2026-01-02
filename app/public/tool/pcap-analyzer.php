<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-Powered PCAP Analyzer - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .pcap-upload-zone {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 3rem;
            text-align: center;
            background: var(--darker-bg);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pcap-upload-zone:hover,
        .pcap-upload-zone.drag-over {
            border-color: var(--primary-color);
            background: rgba(0, 157, 255, 0.05);
        }

        .analysis-section {
            background: var(--darker-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .analysis-section h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.3rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0.5rem;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--dark-bg);
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }

        .stat-card.warning {
            border-left-color: #ffa500;
        }

        .stat-card.error {
            border-left-color: #ff4444;
        }

        .stat-card.success {
            border-left-color: #00ff00;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 0.3rem;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--text-primary);
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 1rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            background: var(--dark-bg);
            color: var(--primary-color);
            font-weight: 600;
        }

        .data-table tr:hover {
            background: var(--dark-bg);
        }

        .ai-insights {
            background: linear-gradient(135deg, rgba(0, 157, 255, 0.1), rgba(138, 43, 226, 0.1));
            border: 1px solid var(--primary-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .ai-insights h3 {
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .ai-insights h3::before {
            content: "🤖";
            font-size: 1.5rem;
        }

        .severity-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .severity-low {
            background: rgba(0, 255, 0, 0.2);
            color: #00ff00;
        }

        .severity-medium {
            background: rgba(255, 165, 0, 0.2);
            color: #ffa500;
        }

        .severity-high {
            background: rgba(255, 68, 68, 0.2);
            color: #ff4444;
        }

        .severity-critical {
            background: rgba(139, 0, 0, 0.3);
            color: #ff0000;
        }

        .recommendation-list {
            list-style: none;
            padding: 0;
        }

        .recommendation-list li {
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: var(--dark-bg);
            border-radius: 8px;
            border-left: 3px solid var(--accent-color);
        }

        .recommendation-list li::before {
            content: "✓";
            color: var(--accent-color);
            font-weight: bold;
            margin-right: 0.5rem;
        }

        .loading-spinner {
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 2rem auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--border-color);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 1rem;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            width: 0%;
            transition: width 0.3s ease;
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { background-position: -100% 0; }
            100% { background-position: 100% 0; }
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-danger {
            background: rgba(255, 68, 68, 0.2);
            color: #ff4444;
        }

        .badge-warning {
            background: rgba(255, 165, 0, 0.2);
            color: #ffa500;
        }

        .badge-success {
            background: rgba(0, 255, 0, 0.2);
            color: #00ff00;
        }

        .badge-info {
            background: rgba(0, 157, 255, 0.2);
            color: var(--primary-color);
        }

        .collapsible {
            cursor: pointer;
            user-select: none;
        }

        .collapsible::after {
            content: " ▼";
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .collapsible.collapsed::after {
            content: " ►";
        }

        .collapsible-content {
            max-height: 1000px;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .collapsible-content.hidden {
            max-height: 0;
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
        <div class="container" style="max-width: 1400px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">AI-Powered PCAP Analyzer</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Comprehensive network packet capture analysis with DNS troubleshooting, routing analysis, security detection, and AI-powered insights
            </p>

            <div id="alert-container"></div>

            <!-- Upload Section -->
            <div class="feature-card" id="upload-section">
                <h2 style="margin-bottom: 1.5rem;">Upload PCAP File</h2>

                <div class="pcap-upload-zone" id="upload-zone">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin: 0 auto 1rem; color: var(--primary-color);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    <h3 style="margin-bottom: 0.5rem;">Drag & Drop PCAP File Here</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 1rem;">or click to browse</p>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">
                        Supported formats: .pcap, .pcapng, .cap (Max 100MB)
                    </p>
                    <input type="file" id="file-input" accept=".pcap,.pcapng,.cap" style="display: none;">
                </div>

                <div style="margin-top: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" id="use-ai-checkbox" checked style="width: auto;">
                        <span>Enable AI-Powered Insights (Requires OpenAI API)</span>
                    </label>
                </div>

                <div id="file-info" style="display: none; margin-top: 1.5rem; padding: 1rem; background: var(--darker-bg); border-radius: 8px;">
                    <p><strong>Selected File:</strong> <span id="file-name"></span></p>
                    <p><strong>Size:</strong> <span id="file-size"></span></p>
                    <div class="progress-bar" id="upload-progress" style="display: none;">
                        <div class="progress-bar-fill" id="progress-fill"></div>
                    </div>
                </div>

                <button class="btn btn-primary" id="analyze-button" style="margin-top: 1rem; width: 100%; display: none;">
                    Analyze PCAP File
                </button>
            </div>

            <!-- Results Section -->
            <div id="results-section" style="display: none;">
                <!-- AI Insights Panel -->
                <div id="ai-insights-panel" style="display: none;"></div>

                <!-- Metadata -->
                <div id="metadata-section" class="analysis-section"></div>

                <!-- DNS Analysis -->
                <div id="dns-section" class="analysis-section"></div>

                <!-- Security Analysis -->
                <div id="security-section" class="analysis-section"></div>

                <!-- Routing Analysis -->
                <div id="routing-section" class="analysis-section"></div>

                <!-- ICMP Analysis -->
                <div id="icmp-section" class="analysis-section"></div>

                <!-- Traffic Analysis -->
                <div id="traffic-section" class="analysis-section"></div>

                <!-- Misbehaving Resources -->
                <div id="misbehaving-section" class="analysis-section"></div>

                <!-- Export Options -->
                <div class="analysis-section">
                    <h3>Export Results</h3>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <button class="btn btn-primary" id="export-json-btn">Export as JSON</button>
                        <button class="btn btn-primary" id="export-pdf-btn">Export as PDF Report</button>
                        <button class="btn btn-primary" onclick="location.reload()">Analyze Another File</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2025 VeriBits by After Dark Systems. All rights reserved.</p>
        </div>
    </footer>

    <script src="/assets/js/main.js?v=<?= time() ?>"></script>
    <script src="/assets/js/auth.js?v=<?= time() ?>"></script>
    <script>
        let analysisData = null;
        let charts = {};

        // File upload handling
        const uploadZone = document.getElementById('upload-zone');
        const fileInput = document.getElementById('file-input');
        const analyzeButton = document.getElementById('analyze-button');
        const fileInfo = document.getElementById('file-info');

        uploadZone.addEventListener('click', () => fileInput.click());

        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('drag-over');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('drag-over');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelect(files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        function handleFileSelect(file) {
            const allowedExtensions = ['pcap', 'pcapng', 'cap'];
            const extension = file.name.split('.').pop().toLowerCase();

            if (!allowedExtensions.includes(extension)) {
                showAlert('Invalid file type. Please upload a .pcap, .pcapng, or .cap file.', 'error');
                return;
            }

            if (file.size > 104857600) { // 100MB
                showAlert('File size exceeds 100MB limit.', 'error');
                return;
            }

            document.getElementById('file-name').textContent = file.name;
            document.getElementById('file-size').textContent = formatBytes(file.size);
            fileInfo.style.display = 'block';
            analyzeButton.style.display = 'block';
        }

        analyzeButton.addEventListener('click', async () => {
            const file = fileInput.files[0];
            if (!file) {
                showAlert('Please select a file first.', 'error');
                return;
            }

            const useAI = document.getElementById('use-ai-checkbox').checked;

            // Show progress
            document.getElementById('upload-progress').style.display = 'block';
            document.getElementById('progress-fill').style.width = '30%';
            analyzeButton.disabled = true;
            analyzeButton.textContent = 'Analyzing...';

            const formData = new FormData();
            formData.append('pcap_file', file);
            formData.append('use_ai', useAI.toString());

            try {
                const response = await fetch('/api/v1/tools/pcap-analyze', {
                    method: 'POST',
                    headers: {
                        'X-API-Key': localStorage.getItem('api_key') || ''
                    },
                    body: formData
                });

                document.getElementById('progress-fill').style.width = '100%';

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error?.message || data.error || 'Analysis failed');
                }

                analysisData = data.data;
                displayResults(analysisData);

            } catch (error) {
                showAlert(error.message || 'Analysis failed', 'error');
            } finally {
                analyzeButton.disabled = false;
                analyzeButton.textContent = 'Analyze PCAP File';
                document.getElementById('upload-progress').style.display = 'none';
            }
        });

        function displayResults(data) {
            document.getElementById('results-section').style.display = 'block';
            document.getElementById('upload-section').style.display = 'none';

            // Display AI Insights
            if (data.ai_insights && !data.ai_insights.error) {
                displayAIInsights(data.ai_insights);
            }

            // Display Metadata
            displayMetadata(data.analysis.metadata);

            // Display DNS Analysis
            displayDNSAnalysis(data.analysis.dns_analysis);

            // Display Security Analysis
            displaySecurityAnalysis(data.analysis.security_analysis);

            // Display Routing Analysis
            displayRoutingAnalysis(data.analysis.routing_analysis);

            // Display ICMP Analysis
            displayICMPAnalysis(data.analysis.icmp_analysis);

            // Display Traffic Analysis
            displayTrafficAnalysis(data.analysis);

            // Display Misbehaving Resources
            displayMisbehavingResources(data.analysis.misbehaving_resources);

            // Scroll to results
            document.getElementById('results-section').scrollIntoView({ behavior: 'smooth' });
        }

        function displayAIInsights(insights) {
            const panel = document.getElementById('ai-insights-panel');
            panel.style.display = 'block';

            const severityClass = `severity-${insights.severity || 'low'}`;

            let html = `
                <div class="ai-insights">
                    <h3>AI-Powered Insights <span class="severity-badge ${severityClass}">${insights.severity || 'info'}</span></h3>

                    ${insights.summary ? `
                        <div style="margin: 1.5rem 0;">
                            <h4 style="color: var(--accent-color); margin-bottom: 0.5rem;">Summary</h4>
                            <p>${insights.summary}</p>
                        </div>
                    ` : ''}

                    ${insights.root_cause ? `
                        <div style="margin: 1.5rem 0;">
                            <h4 style="color: var(--accent-color); margin-bottom: 0.5rem;">Root Cause Analysis</h4>
                            <p>${insights.root_cause}</p>
                        </div>
                    ` : ''}

                    ${insights.recommendations && insights.recommendations.length > 0 ? `
                        <div style="margin: 1.5rem 0;">
                            <h4 style="color: var(--accent-color); margin-bottom: 0.5rem;">Recommendations</h4>
                            <ul class="recommendation-list">
                                ${insights.recommendations.map(rec => `<li>${rec}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}

                    ${insights.troubleshooting_steps && insights.troubleshooting_steps.length > 0 ? `
                        <div style="margin: 1.5rem 0;">
                            <h4 style="color: var(--accent-color); margin-bottom: 0.5rem;">Troubleshooting Steps</h4>
                            <ol style="padding-left: 1.5rem;">
                                ${insights.troubleshooting_steps.map(step => `<li style="margin-bottom: 0.5rem;">${step}</li>`).join('')}
                            </ol>
                        </div>
                    ` : ''}
                </div>
            `;

            panel.innerHTML = html;
        }

        function displayMetadata(metadata) {
            const section = document.getElementById('metadata-section');

            const html = `
                <h3>Capture Metadata</h3>
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Packets</div>
                        <div class="stat-value">${metadata.total_packets?.toLocaleString() || 0}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Duration</div>
                        <div class="stat-value">${metadata.capture_duration?.toFixed(2) || 0}s</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Packets/Second</div>
                        <div class="stat-value">${metadata.packets_per_second?.toFixed(2) || 0}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">File Size</div>
                        <div class="stat-value">${formatBytes(metadata.file_size_bytes || 0)}</div>
                    </div>
                </div>
            `;

            section.innerHTML = html;
        }

        function displayDNSAnalysis(dns) {
            const section = document.getElementById('dns-section');

            const failureRate = dns.total_queries > 0 ? (dns.failed_query_count / dns.total_queries * 100) : 0;

            let html = `
                <h3 class="collapsible">DNS Troubleshooting</h3>
                <div class="collapsible-content">
                    <div class="stat-grid">
                        <div class="stat-card success">
                            <div class="stat-label">Total Queries</div>
                            <div class="stat-value">${dns.total_queries?.toLocaleString() || 0}</div>
                        </div>
                        <div class="stat-card ${dns.failed_query_count > 0 ? 'error' : 'success'}">
                            <div class="stat-label">Failed Queries</div>
                            <div class="stat-value">${dns.failed_query_count || 0}</div>
                        </div>
                        <div class="stat-card ${dns.average_response_time_ms > 100 ? 'warning' : 'success'}">
                            <div class="stat-label">Avg Response Time</div>
                            <div class="stat-value">${dns.average_response_time_ms?.toFixed(2) || 0}ms</div>
                        </div>
                        <div class="stat-card ${dns.queries_without_response > 0 ? 'warning' : 'success'}">
                            <div class="stat-label">No Response</div>
                            <div class="stat-value">${dns.queries_without_response || 0}</div>
                        </div>
                    </div>

                    ${dns.failed_queries && dns.failed_queries.length > 0 ? `
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">Failed Queries</h4>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Query</th>
                                    <th>Type</th>
                                    <th>Error</th>
                                    <th>DNS Server</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${dns.failed_queries.slice(0, 20).map(fail => `
                                    <tr>
                                        <td>${fail.query}</td>
                                        <td><span class="badge badge-info">${fail.query_type}</span></td>
                                        <td><span class="badge badge-danger">${fail.error_name}</span></td>
                                        <td>${fail.dns_server || 'N/A'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : ''}

                    ${dns.slow_queries && dns.slow_queries.length > 0 ? `
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">Slow Queries (>100ms)</h4>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Query</th>
                                    <th>Response Time</th>
                                    <th>DNS Server</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${dns.slow_queries.slice(0, 10).map(slow => `
                                    <tr>
                                        <td>${slow.query_name}</td>
                                        <td><span class="badge badge-warning">${(slow.response_time * 1000).toFixed(2)}ms</span></td>
                                        <td>${slow.dns_server || 'N/A'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : ''}

                    ${dns.dns_servers && dns.dns_servers.length > 0 ? `
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">DNS Server Performance</h4>
                        <div class="chart-container">
                            <canvas id="dns-servers-chart"></canvas>
                        </div>
                    ` : ''}
                </div>
            `;

            section.innerHTML = html;

            // Create DNS servers chart
            if (dns.dns_servers && dns.dns_servers.length > 0) {
                createDNSServersChart(dns.dns_servers);
            }

            addCollapsibleHandlers();
        }

        function displaySecurityAnalysis(security) {
            const section = document.getElementById('security-section');

            const hasIssues = security.port_scan_count > 0 || security.ddos_suspect_count > 0 ||
                              security.acl_block_count > 0 || security.syn_flood_count > 0;

            let html = `
                <h3 class="collapsible">Network Security Analysis</h3>
                <div class="collapsible-content">
                    <div class="stat-grid">
                        <div class="stat-card ${security.port_scan_count > 0 ? 'error' : 'success'}">
                            <div class="stat-label">Port Scans</div>
                            <div class="stat-value">${security.port_scan_count || 0}</div>
                        </div>
                        <div class="stat-card ${security.ddos_suspect_count > 0 ? 'error' : 'success'}">
                            <div class="stat-label">DDoS Suspects</div>
                            <div class="stat-value">${security.ddos_suspect_count || 0}</div>
                        </div>
                        <div class="stat-card ${security.acl_block_count > 0 ? 'warning' : 'success'}">
                            <div class="stat-label">Firewall Blocks</div>
                            <div class="stat-value">${security.acl_block_count || 0}</div>
                        </div>
                        <div class="stat-card ${security.syn_flood_count > 0 ? 'error' : 'success'}">
                            <div class="stat-label">SYN Floods</div>
                            <div class="stat-value">${security.syn_flood_count || 0}</div>
                        </div>
                    </div>

                    ${!hasIssues ? `
                        <div style="padding: 2rem; text-align: center; background: rgba(0, 255, 0, 0.1); border-radius: 8px; margin-top: 1rem;">
                            <h4 style="color: #00ff00;">✓ No Security Issues Detected</h4>
                            <p style="color: var(--text-secondary);">Your network traffic appears clean and secure.</p>
                        </div>
                    ` : ''}

                    ${security.port_scans_detected && security.port_scans_detected.length > 0 ? `
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">⚠ Port Scans Detected</h4>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Source IP</th>
                                    <th>Ports Scanned</th>
                                    <th>Sample Ports</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${security.port_scans_detected.slice(0, 10).map(scan => `
                                    <tr>
                                        <td><span class="badge badge-danger">${scan.source_ip}</span></td>
                                        <td>${scan.ports_scanned}</td>
                                        <td>${scan.port_list.slice(0, 10).join(', ')}...</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : ''}

                    ${security.ddos_suspects && security.ddos_suspects.length > 0 ? `
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">⚠ Potential DDoS Sources</h4>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Source IP</th>
                                    <th>Packet Count</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${security.ddos_suspects.slice(0, 10).map(suspect => `
                                    <tr>
                                        <td><span class="badge badge-danger">${suspect.source_ip}</span></td>
                                        <td>${suspect.packet_count?.toLocaleString()}</td>
                                        <td>${suspect.percentage?.toFixed(2)}%</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : ''}

                    ${security.acl_firewall_blocks && security.acl_firewall_blocks.length > 0 ? `
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">Firewall/ACL Blocks</h4>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>Destination</th>
                                    <th>Port</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${security.acl_firewall_blocks.slice(0, 20).map(block => `
                                    <tr>
                                        <td>${block.blocked_src}</td>
                                        <td>${block.blocked_dst}</td>
                                        <td>${block.blocked_port || 'N/A'}</td>
                                        <td><span class="badge badge-warning">${block.reason}</span></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : ''}
                </div>
            `;

            section.innerHTML = html;
            addCollapsibleHandlers();
        }

        function displayRoutingAnalysis(routing) {
            const section = document.getElementById('routing-section');

            let html = `
                <h3 class="collapsible">Routing Protocol Analysis</h3>
                <div class="collapsible-content">
                    <div class="stat-grid">
                        <div class="stat-card">
                            <div class="stat-label">OSPF Packets</div>
                            <div class="stat-value">${routing.ospf_packets_detected || 0}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">OSPF Neighbors</div>
                            <div class="stat-value">${routing.ospf_neighbors?.length || 0}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">BGP Packets</div>
                            <div class="stat-value">${routing.bgp_packets_detected || 0}</div>
                        </div>
                        <div class="stat-card ${routing.asymmetric_routing_detected ? 'warning' : 'success'}">
                            <div class="stat-label">Asymmetric Flows</div>
                            <div class="stat-value">${routing.asymmetric_flows?.length || 0}</div>
                        </div>
                    </div>

                    ${routing.ospf_neighbors && routing.ospf_neighbors.length > 0 ? `
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">OSPF Neighbors</h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            ${routing.ospf_neighbors.map(neighbor => `
                                <span class="badge badge-info">${neighbor}</span>
                            `).join('')}
                        </div>
                    ` : ''}

                    ${routing.bgp_peers && routing.bgp_peers.length > 0 ? `
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">BGP Peers</h4>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>Destination</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${routing.bgp_peers.slice(0, 10).map(peer => `
                                    <tr>
                                        <td>${peer.src}</td>
                                        <td>${peer.dst}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : ''}

                    ${routing.asymmetric_flows && routing.asymmetric_flows.length > 0 ? `
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">⚠ Asymmetric Routing Detected</h4>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Endpoints</th>
                                    <th>Direction 1</th>
                                    <th>Direction 2</th>
                                    <th>Imbalance</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${routing.asymmetric_flows.slice(0, 10).map(flow => `
                                    <tr>
                                        <td>${flow.endpoints.join(' ↔ ')}</td>
                                        <td>${flow.packets_direction_1}</td>
                                        <td>${flow.packets_direction_2}</td>
                                        <td><span class="badge badge-warning">${(flow.imbalance_ratio * 100).toFixed(1)}%</span></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : ''}
                </div>
            `;

            section.innerHTML = html;
            addCollapsibleHandlers();
        }

        function displayICMPAnalysis(icmp) {
            const section = document.getElementById('icmp-section');

            let html = `
                <h3 class="collapsible">ICMP & Traceroute Analysis</h3>
                <div class="collapsible-content">
                    <div class="stat-grid">
                        <div class="stat-card">
                            <div class="stat-label">ICMP Packets</div>
                            <div class="stat-value">${icmp.total_icmp_packets || 0}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Ping Requests</div>
                            <div class="stat-value">${icmp.ping_requests || 0}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Ping Replies</div>
                            <div class="stat-value">${icmp.ping_replies || 0}</div>
                        </div>
                        <div class="stat-card ${icmp.average_ping_latency_ms > 100 ? 'warning' : 'success'}">
                            <div class="stat-label">Avg Latency</div>
                            <div class="stat-value">${icmp.average_ping_latency_ms?.toFixed(2) || 0}ms</div>
                        </div>
                    </div>

                    ${icmp.unreachable_destinations && icmp.unreachable_destinations.length > 0 ? `
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">Unreachable Destinations</h4>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>Destination</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${icmp.unreachable_destinations.slice(0, 20).map(dest => `
                                    <tr>
                                        <td>${dest.src_ip || 'N/A'}</td>
                                        <td>${dest.dst_ip || 'N/A'}</td>
                                        <td><span class="badge badge-warning">${dest.unreachable_type}</span></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : ''}

                    ${icmp.traceroute_detected ? `
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">Traceroute Activity Detected</h4>
                        <p style="color: var(--text-secondary);">
                            The capture contains traceroute packets. ${Object.keys(icmp.traceroute_hops || {}).length} hop sources detected.
                        </p>
                    ` : ''}
                </div>
            `;

            section.innerHTML = html;
            addCollapsibleHandlers();
        }

        function displayTrafficAnalysis(analysis) {
            const section = document.getElementById('traffic-section');

            let html = `
                <h3 class="collapsible">Traffic Analysis & Protocol Distribution</h3>
                <div class="collapsible-content">
                    <div class="stat-grid">
                        <div class="stat-card">
                            <div class="stat-label">Unique IPs</div>
                            <div class="stat-value">${analysis.traffic_stats?.unique_ips || 0}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Top Conversations</div>
                            <div class="stat-value">${analysis.traffic_stats?.top_conversations?.length || 0}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Protocols</div>
                            <div class="stat-value">${Object.keys(analysis.protocol_distribution || {}).length}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Top Ports</div>
                            <div class="stat-value">${analysis.traffic_stats?.top_ports?.length || 0}</div>
                        </div>
                    </div>

                    ${analysis.protocol_distribution && Object.keys(analysis.protocol_distribution).length > 0 ? `
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">Protocol Distribution</h4>
                        <div class="chart-container">
                            <canvas id="protocol-chart"></canvas>
                        </div>
                    ` : ''}

                    ${analysis.traffic_stats?.top_ports && analysis.traffic_stats.top_ports.length > 0 ? `
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">Top Destination Ports</h4>
                        <div class="chart-container">
                            <canvas id="ports-chart"></canvas>
                        </div>
                    ` : ''}

                    ${analysis.traffic_stats?.top_conversations && analysis.traffic_stats.top_conversations.length > 0 ? `
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">Top Conversations</h4>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Endpoints</th>
                                    <th>Packets</th>
                                    <th>Bytes</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${analysis.traffic_stats.top_conversations.slice(0, 10).map(conv => `
                                    <tr>
                                        <td>${conv.endpoints.join(' ↔ ')}</td>
                                        <td>${conv.packets?.toLocaleString()}</td>
                                        <td>${formatBytes(conv.bytes)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : ''}
                </div>
            `;

            section.innerHTML = html;

            // Create charts
            if (analysis.protocol_distribution && Object.keys(analysis.protocol_distribution).length > 0) {
                createProtocolChart(analysis.protocol_distribution);
            }
            if (analysis.traffic_stats?.top_ports && analysis.traffic_stats.top_ports.length > 0) {
                createPortsChart(analysis.traffic_stats.top_ports);
            }

            addCollapsibleHandlers();
        }

        function displayMisbehavingResources(misbehaving) {
            const section = document.getElementById('misbehaving-section');

            let html = `
                <h3 class="collapsible">Misbehaving Resources</h3>
                <div class="collapsible-content">
                    <div class="stat-grid">
                        <div class="stat-card">
                            <div class="stat-label">Total Retransmissions</div>
                            <div class="stat-value">${misbehaving.total_retransmissions?.toLocaleString() || 0}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Top Talkers</div>
                            <div class="stat-value">${misbehaving.top_talkers?.length || 0}</div>
                        </div>
                    </div>

                    ${misbehaving.retransmissions && misbehaving.retransmissions.length > 0 ? `
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">Top Retransmitting Hosts</h4>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>IP Address</th>
                                    <th>Retransmissions</th>
                                    <th>Retransmission Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${misbehaving.retransmissions.slice(0, 10).map(host => `
                                    <tr>
                                        <td>${host.ip}</td>
                                        <td>${host.retransmission_count}</td>
                                        <td><span class="badge ${host.retransmission_rate > 5 ? 'badge-danger' : 'badge-warning'}">${host.retransmission_rate?.toFixed(2)}%</span></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : ''}

                    ${misbehaving.top_talkers && misbehaving.top_talkers.length > 0 ? `
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem;">Top Talkers by Packet Count</h4>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>IP Address</th>
                                    <th>Packet Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${misbehaving.top_talkers.slice(0, 20).map(talker => `
                                    <tr>
                                        <td>${talker.ip}</td>
                                        <td>${talker.packet_count?.toLocaleString()}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : ''}
                </div>
            `;

            section.innerHTML = html;
            addCollapsibleHandlers();
        }

        // Chart creation functions
        function createDNSServersChart(servers) {
            const ctx = document.getElementById('dns-servers-chart');
            if (!ctx) return;

            const data = servers.slice(0, 10);

            charts.dnsServers = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(s => s.ip),
                    datasets: [{
                        label: 'Query Count',
                        data: data.map(s => s.query_count),
                        backgroundColor: 'rgba(0, 157, 255, 0.7)',
                        borderColor: 'rgba(0, 157, 255, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: { color: '#ffffff' }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#ffffff' },
                            grid: { color: 'rgba(255, 255, 255, 0.1)' }
                        },
                        x: {
                            ticks: { color: '#ffffff' },
                            grid: { color: 'rgba(255, 255, 255, 0.1)' }
                        }
                    }
                }
            });
        }

        function createProtocolChart(protocolDist) {
            const ctx = document.getElementById('protocol-chart');
            if (!ctx) return;

            const labels = Object.keys(protocolDist);
            const data = Object.values(protocolDist);

            charts.protocol = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: [
                            'rgba(0, 157, 255, 0.7)',
                            'rgba(138, 43, 226, 0.7)',
                            'rgba(255, 165, 0, 0.7)',
                            'rgba(0, 255, 0, 0.7)',
                            'rgba(255, 68, 68, 0.7)',
                            'rgba(255, 215, 0, 0.7)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { color: '#ffffff' }
                        }
                    }
                }
            });
        }

        function createPortsChart(ports) {
            const ctx = document.getElementById('ports-chart');
            if (!ctx) return;

            const data = ports.slice(0, 15);

            charts.ports = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(p => p.port.toString()),
                    datasets: [{
                        label: 'Packet Count',
                        data: data.map(p => p.count),
                        backgroundColor: 'rgba(138, 43, 226, 0.7)',
                        borderColor: 'rgba(138, 43, 226, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: { color: '#ffffff' }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#ffffff' },
                            grid: { color: 'rgba(255, 255, 255, 0.1)' }
                        },
                        x: {
                            ticks: { color: '#ffffff' },
                            grid: { color: 'rgba(255, 255, 255, 0.1)' }
                        }
                    }
                }
            });
        }

        // Export functions
        document.getElementById('export-json-btn').addEventListener('click', () => {
            if (!analysisData) return;

            const dataStr = JSON.stringify(analysisData, null, 2);
            const blob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'pcap-analysis-results.json';
            a.click();
            URL.revokeObjectURL(url);
        });

        // Utility functions
        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            alertContainer.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        function addCollapsibleHandlers() {
            document.querySelectorAll('.collapsible').forEach(el => {
                el.addEventListener('click', function() {
                    this.classList.toggle('collapsed');
                    const content = this.nextElementSibling;
                    if (content && content.classList.contains('collapsible-content')) {
                        content.classList.toggle('hidden');
                    }
                });
            });
        }
    </script>
</body>
</html>
