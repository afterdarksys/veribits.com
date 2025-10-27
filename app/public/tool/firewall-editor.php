<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firewall Editor - iptables/ebtables - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .firewall-controls {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .firewall-type-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: var(--darker-bg);
            border-radius: 8px;
        }

        .firewall-type-btn {
            flex: 1;
            padding: 1rem;
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .firewall-type-btn.active {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }

        .firewall-type-btn:hover {
            transform: translateY(-2px);
        }

        .chain-section {
            margin-bottom: 2rem;
            background: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
        }

        .chain-header {
            background: var(--darker-bg);
            padding: 1rem 1.5rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid var(--primary-color);
        }

        .chain-header:hover {
            background: var(--border-color);
        }

        .chain-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .chain-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .chain-content {
            display: none;
            padding: 1.5rem;
        }

        .chain-content.expanded {
            display: block;
        }

        .rules-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }

        .rules-table th,
        .rules-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .rules-table th {
            background: var(--darker-bg);
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        .rules-table tbody tr {
            cursor: move;
            transition: background 0.2s;
        }

        .rules-table tbody tr:hover {
            background: var(--darker-bg);
        }

        .rules-table tbody tr.dragging {
            opacity: 0.5;
        }

        .rule-target {
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
        }

        .rule-target.ACCEPT {
            background: var(--success-color);
            color: white;
        }

        .rule-target.DROP, .rule-target.REJECT {
            background: var(--error-color);
            color: white;
        }

        .rule-target.LOG {
            background: var(--warning-color);
            color: white;
        }

        .rule-target.RETURN {
            background: var(--info-color);
            color: white;
        }

        .rule-actions {
            display: flex;
            gap: 0.5rem;
        }

        .rule-actions button {
            padding: 0.25rem 0.75rem;
            font-size: 0.9rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 8px;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            background: var(--darker-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
        }

        .command-preview {
            background: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-all;
            margin-top: 1rem;
            border-left: 4px solid var(--primary-color);
        }

        .version-history {
            margin-top: 2rem;
        }

        .version-item {
            background: var(--darker-bg);
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .version-item:hover {
            background: var(--border-color);
        }

        .diff-viewer {
            background: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.9rem;
            max-height: 400px;
            overflow-y: auto;
        }

        .diff-added {
            background: rgba(40, 167, 69, 0.2);
            color: var(--success-color);
        }

        .diff-removed {
            background: rgba(220, 53, 69, 0.2);
            color: var(--error-color);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state svg {
            width: 100px;
            height: 100px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--darker-bg);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }

        .stat-card h4 {
            margin: 0 0 0.5rem 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 400;
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
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
        <div class="container" style="max-width: 1400px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Firewall Configuration Editor</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Visual editor for iptables (IPv4/IPv6) and ebtables (Layer 2) with version control and live preview
            </p>

            <div id="alert-container"></div>

            <!-- Firewall Type Selector -->
            <div class="firewall-type-selector">
                <div class="firewall-type-btn active" data-type="iptables">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🛡️</div>
                    <strong>iptables</strong>
                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem;">IPv4 Firewall</div>
                </div>
                <div class="firewall-type-btn" data-type="ip6tables">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🌐</div>
                    <strong>ip6tables</strong>
                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem;">IPv6 Firewall</div>
                </div>
                <div class="firewall-type-btn" data-type="ebtables">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔗</div>
                    <strong>ebtables</strong>
                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem;">Layer 2 Bridge</div>
                </div>
            </div>

            <!-- Control Buttons -->
            <div class="firewall-controls">
                <button class="btn btn-primary" onclick="showUploadModal()">
                    📁 Upload Config
                </button>
                <button class="btn btn-secondary" onclick="showAddRuleModal()">
                    ➕ Add Rule
                </button>
                <button class="btn btn-secondary" onclick="showVersionHistory()">
                    📜 Version History
                </button>
                <button class="btn btn-secondary" onclick="downloadConfig()">
                    💾 Download
                </button>
            </div>

            <!-- Statistics -->
            <div class="stats-grid" id="stats-grid" style="display: none;">
                <div class="stat-card">
                    <h4>Total Rules</h4>
                    <div class="stat-value" id="stat-total-rules">0</div>
                </div>
                <div class="stat-card">
                    <h4>ACCEPT Rules</h4>
                    <div class="stat-value" style="color: var(--success-color);" id="stat-accept">0</div>
                </div>
                <div class="stat-card">
                    <h4>DROP/REJECT Rules</h4>
                    <div class="stat-value" style="color: var(--error-color);" id="stat-drop">0</div>
                </div>
                <div class="stat-card">
                    <h4>Active Chains</h4>
                    <div class="stat-value" id="stat-chains">0</div>
                </div>
            </div>

            <!-- Empty State -->
            <div class="empty-state" id="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3>No Firewall Configuration Loaded</h3>
                <p style="margin-top: 1rem;">Upload an iptables-save or ebtables-save file to get started, or create rules from scratch</p>
                <button class="btn btn-primary" onclick="showUploadModal()" style="margin-top: 1.5rem;">
                    Upload Configuration File
                </button>
            </div>

            <!-- Firewall Rules Display -->
            <div id="firewall-rules" style="display: none;"></div>

            <!-- Live Command Preview -->
            <div class="feature-card" id="command-preview-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1rem;">Live Command Preview</h2>
                <div class="command-preview" id="command-preview"></div>
                <div style="margin-top: 1rem; display: flex; gap: 1rem;">
                    <button class="btn btn-secondary" onclick="copyCommands()">
                        📋 Copy Commands
                    </button>
                    <button class="btn btn-primary" onclick="saveToAccount()">
                        💾 Save to Account
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Upload Modal -->
    <div class="modal" id="upload-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Upload Firewall Configuration</h2>
                <button class="modal-close" onclick="closeModal('upload-modal')">&times;</button>
            </div>

            <div class="upload-area" id="config-upload-area">
                <div class="upload-icon">📄</div>
                <p>Drag & drop your configuration file here or click to browse</p>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.5rem;">
                    Supported: iptables-save, ip6tables-save, ebtables-save output
                </p>
                <input type="file" id="config-file-input" accept=".txt,.conf,.rules" style="display: none;">
            </div>

            <div id="config-file-info" style="display: none; margin-top: 1.5rem;">
                <p><strong>Selected file:</strong> <span id="config-filename"></span></p>
                <div class="form-group">
                    <label for="device-name">Device/Server Name (optional)</label>
                    <input type="text" id="device-name" placeholder="web-server-01">
                </div>
                <button class="btn btn-primary" id="upload-config-button" style="margin-top: 1rem;">Upload & Parse</button>
            </div>
        </div>
    </div>

    <!-- Add/Edit Rule Modal -->
    <div class="modal" id="rule-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="rule-modal-title">Add Firewall Rule</h2>
                <button class="modal-close" onclick="closeModal('rule-modal')">&times;</button>
            </div>

            <form id="rule-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="rule-chain">Chain *</label>
                        <select id="rule-chain" required>
                            <option value="INPUT">INPUT</option>
                            <option value="OUTPUT">OUTPUT</option>
                            <option value="FORWARD">FORWARD</option>
                            <option value="PREROUTING">PREROUTING</option>
                            <option value="POSTROUTING">POSTROUTING</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="rule-target">Target *</label>
                        <select id="rule-target" required>
                            <option value="ACCEPT">ACCEPT</option>
                            <option value="DROP">DROP</option>
                            <option value="REJECT">REJECT</option>
                            <option value="LOG">LOG</option>
                            <option value="RETURN">RETURN</option>
                            <option value="DNAT">DNAT</option>
                            <option value="SNAT">SNAT</option>
                            <option value="MASQUERADE">MASQUERADE</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="rule-protocol">Protocol</label>
                        <select id="rule-protocol">
                            <option value="">All</option>
                            <option value="tcp">TCP</option>
                            <option value="udp">UDP</option>
                            <option value="icmp">ICMP</option>
                            <option value="icmpv6">ICMPv6</option>
                            <option value="esp">ESP</option>
                            <option value="ah">AH</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="rule-interface">Interface</label>
                        <input type="text" id="rule-interface" placeholder="eth0, wlan0, etc.">
                    </div>

                    <div class="form-group">
                        <label for="rule-source">Source IP/CIDR</label>
                        <input type="text" id="rule-source" placeholder="192.168.1.0/24 or any">
                    </div>

                    <div class="form-group">
                        <label for="rule-destination">Destination IP/CIDR</label>
                        <input type="text" id="rule-destination" placeholder="10.0.0.0/8 or any">
                    </div>

                    <div class="form-group">
                        <label for="rule-sport">Source Port(s)</label>
                        <input type="text" id="rule-sport" placeholder="80, 443, 1024:65535">
                    </div>

                    <div class="form-group">
                        <label for="rule-dport">Destination Port(s)</label>
                        <input type="text" id="rule-dport" placeholder="22, 80, 443">
                    </div>
                </div>

                <div class="form-group">
                    <label for="rule-state">Connection State</label>
                    <select id="rule-state" multiple size="4" style="height: auto;">
                        <option value="NEW">NEW</option>
                        <option value="ESTABLISHED">ESTABLISHED</option>
                        <option value="RELATED">RELATED</option>
                        <option value="INVALID">INVALID</option>
                    </select>
                    <small style="color: var(--text-secondary);">Hold Ctrl/Cmd to select multiple</small>
                </div>

                <div class="form-group">
                    <label for="rule-comment">Comment</label>
                    <input type="text" id="rule-comment" placeholder="Allow SSH from admin network">
                </div>

                <div class="form-group">
                    <label for="rule-extra">Additional Options</label>
                    <textarea id="rule-extra" rows="3" placeholder="--limit 5/min --limit-burst 10"></textarea>
                </div>

                <div class="command-preview" id="rule-preview"></div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    Add Rule
                </button>
            </form>
        </div>
    </div>

    <!-- Version History Modal -->
    <div class="modal" id="history-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Version History</h2>
                <button class="modal-close" onclick="closeModal('history-modal')">&times;</button>
            </div>
            <div id="history-content">
                <p style="text-align: center; color: var(--text-secondary);">Loading history...</p>
            </div>
        </div>
    </div>

    <!-- Diff Viewer Modal -->
    <div class="modal" id="diff-modal">
        <div class="modal-content" style="max-width: 1200px;">
            <div class="modal-header">
                <h2>Configuration Diff</h2>
                <button class="modal-close" onclick="closeModal('diff-modal')">&times;</button>
            </div>
            <div class="diff-viewer" id="diff-content"></div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; 2025 VeriBits. All rights reserved.</p>
            <p style="margin-top: 0.5rem;">
                A service from <a href="https://www.afterdarksys.com/" target="_blank" rel="noopener">After Dark Systems, LLC</a>
            </p>
        </div>
    </footer>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/firewall-editor.js"></script>
</body>
</html>
