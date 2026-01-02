<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>osquery - SQL Query Interface - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
    <style>
        .query-editor {
            width: 100%;
            min-height: 200px;
            padding: 1rem;
            background: var(--darker-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
            resize: vertical;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            overflow-x: auto;
            display: block;
        }
        .results-table th {
            background: var(--darker-bg);
            padding: 0.75rem;
            text-align: left;
            border-bottom: 2px solid var(--border-color);
            position: sticky;
            top: 0;
        }
        .results-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            font-family: monospace;
            font-size: 0.85rem;
        }
        .query-template {
            padding: 1rem;
            background: var(--darker-bg);
            border-radius: 8px;
            margin: 0.5rem 0;
            cursor: pointer;
            transition: all 0.3s;
        }
        .query-template:hover {
            background: rgba(255,255,255,0.05);
            border-left: 3px solid var(--primary-color);
        }
        .ip-display {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            margin: 2rem 0;
        }
        .ip-value {
            font-size: 2.5rem;
            font-weight: bold;
            font-family: monospace;
            color: white;
            margin: 1rem 0;
        }
        .tab-container {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border-color);
        }
        .tab {
            padding: 1rem 1.5rem;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
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
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">osquery</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                SQL-Powered Operating System Instrumentation & Monitoring
            </p>

            <!-- User IP Display -->
            <div class="ip-display">
                <h2 style="color: white; margin-bottom: 0.5rem;">Your IP Address</h2>
                <div class="ip-value" id="user-ip">Loading...</div>
                <p style="color: rgba(255,255,255,0.9); margin-top: 0.5rem;">
                    Scan your IP or subnet for security analysis
                </p>
                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
                    <button class="btn btn-secondary" onclick="scanIP()">🔍 Scan This IP</button>
                    <button class="btn btn-secondary" onclick="showSubnetScan()">🌐 Scan Subnet</button>
                </div>
            </div>

            <div id="alert-container"></div>

            <!-- Tabs -->
            <div class="tab-container">
                <button class="tab active" onclick="switchTab('query')">📊 SQL Query</button>
                <button class="tab" onclick="switchTab('templates')">📋 Templates</button>
                <button class="tab" onclick="switchTab('tables')">📁 Tables</button>
            </div>

            <!-- Query Tab -->
            <div id="query-tab" class="tab-content active">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Execute SQL Query</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                        Query your operating system using SQL. Access processes, users, network connections, and more.
                    </p>

                    <div class="form-group">
                        <label for="query-input">SQL Query</label>
                        <textarea id="query-input" class="query-editor" placeholder="SELECT * FROM processes WHERE name LIKE '%ssh%';">SELECT * FROM processes LIMIT 10;</textarea>
                        <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">
                            Only SELECT queries are allowed. Timeout: 30 seconds
                        </small>
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <button class="btn btn-primary" onclick="executeQuery()" style="flex: 1;">
                            ▶ Execute Query
                        </button>
                        <button class="btn btn-secondary" onclick="clearQuery()">
                            🗑️ Clear
                        </button>
                    </div>

                    <div id="query-results" style="margin-top: 2rem;"></div>
                </div>
            </div>

            <!-- Templates Tab -->
            <div id="templates-tab" class="tab-content">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Query Templates</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                        Pre-built queries for common security and system analysis tasks
                    </p>

                    <div id="templates-list">Loading templates...</div>
                </div>
            </div>

            <!-- Tables Tab -->
            <div id="tables-tab" class="tab-content">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">Available Tables</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                        Browse available osquery tables and their schemas
                    </p>

                    <div id="tables-list">Loading tables...</div>
                </div>
            </div>

            <!-- Information Section -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">About osquery</h2>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">What is osquery?</h3>
                <p style="margin-bottom: 1rem;">
                    osquery is an operating system instrumentation framework that exposes an operating system as a high-performance relational database.
                    This allows you to write SQL queries to explore operating system data.
                </p>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Example Queries</h3>
                <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                    <li><strong>List processes:</strong> <code>SELECT * FROM processes;</code></li>
                    <li><strong>Network connections:</strong> <code>SELECT * FROM process_open_sockets;</code></li>
                    <li><strong>User accounts:</strong> <code>SELECT * FROM users;</code></li>
                    <li><strong>Listening ports:</strong> <code>SELECT * FROM listening_ports;</code></li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Security Features</h3>
                <ul style="margin-left: 1.5rem;">
                    <li>Read-only queries (no system modification)</li>
                    <li>Query timeout limits</li>
                    <li>Table access whitelist</li>
                    <li>Full audit logging</li>
                </ul>
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
        // Get and display user IP
        fetch('/api/v1/auth/status')
            .then(r => r.json())
            .then(data => {
                if (data.data && data.data.ip_address) {
                    document.getElementById('user-ip').textContent = data.data.ip_address;
                }
            })
            .catch(() => {
                document.getElementById('user-ip').textContent = 'Unknown';
            });

        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');

            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(tab + '-tab').classList.add('active');

            // Load data when switching tabs
            if (tab === 'templates' && document.getElementById('templates-list').innerHTML === 'Loading templates...') {
                loadTemplates();
            }
            if (tab === 'tables' && document.getElementById('tables-list').innerHTML === 'Loading tables...') {
                loadTables();
            }
        }

        async function executeQuery() {
            const query = document.getElementById('query-input').value.trim();

            if (!query) {
                showAlert('Please enter a SQL query', 'error');
                return;
            }

            const resultsDiv = document.getElementById('query-results');
            resultsDiv.innerHTML = '<div class="spinner"></div><p style="text-align: center; margin-top: 1rem;">Executing query...</p>';

            try {
                const data = await apiRequest('/api/v1/osquery/execute', {
                    method: 'POST',
                    body: JSON.stringify({ query })
                });

                displayResults(data.data);
            } catch (error) {
                resultsDiv.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        }

        function displayResults(data) {
            const resultsDiv = document.getElementById('query-results');

            if (data.row_count === 0) {
                resultsDiv.innerHTML = '<div class="alert alert-info">Query returned no results</div>';
                return;
            }

            let html = `<div style="margin-bottom: 1rem; color: var(--text-secondary);">
                <strong>${data.row_count.toLocaleString()}</strong> rows returned in <strong>${data.execution_time}s</strong>
                ${data.truncated ? ' (truncated to 10,000 rows)' : ''}
            </div>`;

            html += '<div style="overflow-x: auto; max-height: 600px;"><table class="results-table">';
            html += '<thead><tr>';

            data.columns.forEach(col => {
                html += `<th>${col}</th>`;
            });

            html += '</tr></thead><tbody>';

            data.rows.forEach(row => {
                html += '<tr>';
                data.columns.forEach(col => {
                    const value = row[col] || '';
                    html += `<td>${value}</td>`;
                });
                html += '</tr>';
            });

            html += '</tbody></table></div>';

            html += `<button class="btn btn-secondary" onclick="exportResults()" style="margin-top: 1rem;">
                💾 Export as JSON
            </button>`;

            resultsDiv.innerHTML = html;
        }

        async function loadTemplates() {
            try {
                const data = await apiRequest('/api/v1/osquery/templates', {
                    method: 'GET'
                });

                displayTemplates(data.data.templates);
            } catch (error) {
                document.getElementById('templates-list').innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        }

        function displayTemplates(templates) {
            let html = '';

            templates.forEach(category => {
                html += `<h3 style="margin-top: 2rem; margin-bottom: 1rem;">${category.category}</h3>`;

                category.queries.forEach(query => {
                    html += `<div class="query-template" onclick='useTemplate(\`${query.query.replace(/`/g, '\\`')}\`)'>
                        <h4 style="margin-bottom: 0.5rem;">${query.name}</h4>
                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.5rem;">${query.description}</p>
                        <code style="font-size: 0.85rem; color: var(--primary-color);">${query.query.substring(0, 100)}...</code>
                    </div>`;
                });
            });

            document.getElementById('templates-list').innerHTML = html;
        }

        function useTemplate(query) {
            document.getElementById('query-input').value = query;
            switchTab('query');
            document.querySelectorAll('.tab')[0].classList.add('active');
            showAlert('Template loaded! Click "Execute Query" to run it.', 'success');
        }

        async function loadTables() {
            try {
                const data = await apiRequest('/api/v1/osquery/tables', {
                    method: 'GET'
                });

                displayTables(data.data.tables);
            } catch (error) {
                document.getElementById('tables-list').innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        }

        function displayTables(tables) {
            let html = '<div style="display: grid; gap: 1rem;">';

            tables.forEach(table => {
                html += `<div style="padding: 1rem; background: var(--darker-bg); border-radius: 8px;">
                    <h4 style="margin-bottom: 0.5rem; font-family: monospace;">${table.name}</h4>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">${table.description}</p>
                    <button class="btn btn-secondary" onclick='useTable("${table.name}")' style="margin-top: 0.5rem; font-size: 0.9rem;">
                        Use in Query
                    </button>
                </div>`;
            });

            html += '</div>';

            document.getElementById('tables-list').innerHTML = html;
        }

        function useTable(tableName) {
            document.getElementById('query-input').value = `SELECT * FROM ${tableName} LIMIT 10;`;
            switchTab('query');
            document.querySelectorAll('.tab')[0].classList.add('active');
        }

        function clearQuery() {
            document.getElementById('query-input').value = '';
            document.getElementById('query-results').innerHTML = '';
        }

        function exportResults() {
            const query = document.getElementById('query-input').value;
            showAlert('Export feature coming soon!', 'info');
        }

        function scanIP() {
            const ip = document.getElementById('user-ip').textContent;
            window.location.href = `/tool/ip-calculator.php?ip=${ip}`;
        }

        function showSubnetScan() {
            const ip = document.getElementById('user-ip').textContent;
            const subnet = prompt(`Enter subnet to scan (e.g., ${ip}/24):`);
            if (subnet) {
                showAlert('Subnet scanning feature coming soon!', 'info');
            }
        }
    </script>
</body>
</html>
