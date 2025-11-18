<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Scans - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
    <style>
        .scans-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .scan-card {
            background: white;
            border: 1px solid #e1e4e8;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: box-shadow 0.2s;
        }

        .scan-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .scan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .scan-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #24292e;
        }

        .scan-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 0.75rem;
            color: #586069;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .meta-value {
            font-size: 0.95rem;
            color: #24292e;
            font-weight: 500;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-processing {
            background: #fff3cd;
            color: #856404;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .status-pending {
            background: #d1ecf1;
            color: #0c5460;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #0366d6;
            color: white;
        }

        .btn-primary:hover {
            background: #0256c7;
        }

        .btn-secondary {
            background: #e1e4e8;
            color: #24292e;
        }

        .btn-secondary:hover {
            background: #d1d5da;
        }

        .btn-danger {
            background: #d73a49;
            color: white;
        }

        .btn-danger:hover {
            background: #cb2431;
        }

        .file-details {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e1e4e8;
        }

        .file-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .file-table th,
        .file-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e1e4e8;
        }

        .file-table th {
            background: #f6f8fa;
            font-weight: 600;
            color: #24292e;
            font-size: 0.875rem;
        }

        .file-table td {
            font-size: 0.875rem;
            color: #586069;
        }

        .hash-value {
            font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
            font-size: 0.75rem;
            word-break: break-all;
        }

        .loading {
            text-align: center;
            padding: 3rem;
            color: #586069;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .page-btn {
            padding: 0.5rem 0.75rem;
            border: 1px solid #e1e4e8;
            background: white;
            border-radius: 4px;
            cursor: pointer;
        }

        .page-btn.active {
            background: #0366d6;
            color: white;
            border-color: #0366d6;
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #586069;
        }

        .empty-state h3 {
            margin-bottom: 1rem;
            color: #24292e;
        }

        .filter-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #e1e4e8;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .filter-bar input,
        .filter-bar select {
            padding: 0.5rem;
            border: 1px solid #d1d5da;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .filter-bar input {
            flex: 1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border: 1px solid #e1e4e8;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #0366d6;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #586069;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/assets/includes/header.php'; ?>

    <div class="scans-container">
        <div class="page-header">
            <h1>System Scans</h1>
            <p>View and manage file hash scans from the VeriBits system client</p>
        </div>

        <!-- Statistics -->
        <div id="stats" class="stats-grid" style="display: none;">
            <div class="stat-card">
                <div class="stat-value" id="stat-scans">0</div>
                <div class="stat-label">Total Scans</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-systems">0</div>
                <div class="stat-label">Systems</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-files">0</div>
                <div class="stat-label">Total Files</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-latest">-</div>
                <div class="stat-label">Latest Scan</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
            <input type="text" id="filter-system" placeholder="Filter by system name...">
            <button class="btn btn-secondary" onclick="loadScans()">Refresh</button>
        </div>

        <!-- Loading State -->
        <div id="loading" class="loading">
            <p>Loading system scans...</p>
        </div>

        <!-- Error State -->
        <div id="error" class="error-message" style="display: none;"></div>

        <!-- Empty State -->
        <div id="empty-state" class="empty-state" style="display: none;">
            <h3>No Scans Found</h3>
            <p>Upload your first system scan using the VeriBits system client.</p>
            <p style="margin-top: 1rem;">
                <a href="https://github.com/afterdarksys/veribits-system-client" target="_blank" class="btn btn-primary">
                    Download System Client
                </a>
            </p>
        </div>

        <!-- Scans List -->
        <div id="scans-list"></div>

        <!-- Pagination -->
        <div id="pagination" class="pagination" style="display: none;"></div>
    </div>

    <?php include __DIR__ . '/assets/includes/footer.php'; ?>

    <script src="/assets/js/auth.js?v=<?= time() ?>"></script>
    <script>
        let currentPage = 1;
        let apiKey = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', async () => {
            // Check authentication
            apiKey = localStorage.getItem('veribits_api_key');
            if (!apiKey) {
                window.location.href = '/login.php?redirect=' + encodeURIComponent(window.location.pathname);
                return;
            }

            await loadScans();
        });

        async function loadScans(page = 1) {
            currentPage = page;
            const systemFilter = document.getElementById('filter-system').value;

            document.getElementById('loading').style.display = 'block';
            document.getElementById('error').style.display = 'none';
            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('scans-list').innerHTML = '';

            try {
                let url = `/api/v1/system-scans?page=${page}&limit=20`;
                if (systemFilter) {
                    url += `&system_name=${encodeURIComponent(systemFilter)}`;
                }

                const response = await fetch(url, {
                    headers: {
                        'X-API-Key': apiKey
                    }
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Failed to load scans');
                }

                document.getElementById('loading').style.display = 'none';

                if (!data.data.scans || data.data.scans.length === 0) {
                    document.getElementById('empty-state').style.display = 'block';
                    return;
                }

                // Update statistics
                updateStats(data.data.scans);

                // Render scans
                renderScans(data.data.scans);

                // Render pagination
                if (data.data.pagination.total_pages > 1) {
                    renderPagination(data.data.pagination);
                }

            } catch (error) {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('error').textContent = error.message;
                document.getElementById('error').style.display = 'block';
                console.error('Error loading scans:', error);
            }
        }

        function updateStats(scans) {
            const statsDiv = document.getElementById('stats');
            statsDiv.style.display = 'grid';

            document.getElementById('stat-scans').textContent = scans.length;

            const uniqueSystems = new Set(scans.map(s => s.system_name));
            document.getElementById('stat-systems').textContent = uniqueSystems.size;

            const totalFiles = scans.reduce((sum, s) => sum + (s.total_files || 0), 0);
            document.getElementById('stat-files').textContent = totalFiles.toLocaleString();

            if (scans.length > 0) {
                const latestDate = new Date(scans[0].scan_date);
                document.getElementById('stat-latest').textContent = latestDate.toLocaleDateString();
            }
        }

        function renderScans(scans) {
            const container = document.getElementById('scans-list');

            scans.forEach(scan => {
                const card = document.createElement('div');
                card.className = 'scan-card';
                card.innerHTML = `
                    <div class="scan-header">
                        <div class="scan-title">${escapeHtml(scan.system_name)}</div>
                        <div>
                            <span class="status-badge status-${scan.status}">${scan.status}</span>
                        </div>
                    </div>
                    <div class="scan-meta">
                        <div class="meta-item">
                            <div class="meta-label">Scan Date</div>
                            <div class="meta-value">${formatDate(scan.scan_date)}</div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Operating System</div>
                            <div class="meta-value">${escapeHtml(scan.os_type)} ${escapeHtml(scan.os_version || '')}</div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Total Files</div>
                            <div class="meta-value">${(scan.total_files || 0).toLocaleString()}</div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Directories</div>
                            <div class="meta-value">${scan.total_directories || 0}</div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Errors</div>
                            <div class="meta-value">${scan.total_errors || 0}</div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">System IP</div>
                            <div class="meta-value">${escapeHtml(scan.system_ip || 'N/A')}</div>
                        </div>
                    </div>
                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                        <button class="btn btn-primary" onclick="viewScanDetails(${scan.id})">View Files</button>
                        <button class="btn btn-danger" onclick="deleteScan(${scan.id})">Delete</button>
                    </div>
                    <div id="scan-details-${scan.id}" class="file-details" style="display: none;"></div>
                `;
                container.appendChild(card);
            });
        }

        async function viewScanDetails(scanId) {
            const detailsDiv = document.getElementById(`scan-details-${scanId}`);

            if (detailsDiv.style.display === 'block') {
                detailsDiv.style.display = 'none';
                return;
            }

            detailsDiv.innerHTML = '<div class="loading">Loading file details...</div>';
            detailsDiv.style.display = 'block';

            try {
                const response = await fetch(`/api/v1/system-scans/${scanId}?files_limit=100`, {
                    headers: {
                        'X-API-Key': apiKey
                    }
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Failed to load scan details');
                }

                const files = data.data.files || [];

                if (files.length === 0) {
                    detailsDiv.innerHTML = '<p>No files found in this scan.</p>';
                    return;
                }

                let html = `
                    <h3>File Hashes (showing ${files.length} of ${data.data.files_pagination.total})</h3>
                    <table class="file-table">
                        <thead>
                            <tr>
                                <th>Directory</th>
                                <th>File Name</th>
                                <th>SHA256</th>
                                <th>SHA512</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                files.forEach(file => {
                    html += `
                        <tr>
                            <td>${escapeHtml(file.directory_name)}</td>
                            <td>${escapeHtml(file.file_name.split('/').pop())}</td>
                            <td class="hash-value">${file.file_hash_sha256 || '-'}</td>
                            <td class="hash-value">${file.file_hash_sha512 || '-'}</td>
                        </tr>
                    `;
                });

                html += `
                        </tbody>
                    </table>
                `;

                if (data.data.files_pagination.total_pages > 1) {
                    html += `<p style="margin-top: 1rem; color: #586069;">Showing page 1 of ${data.data.files_pagination.total_pages}</p>`;
                }

                detailsDiv.innerHTML = html;

            } catch (error) {
                detailsDiv.innerHTML = `<div class="error-message">${error.message}</div>`;
                console.error('Error loading scan details:', error);
            }
        }

        async function deleteScan(scanId) {
            if (!confirm('Are you sure you want to delete this scan? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch(`/api/v1/system-scans/${scanId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-API-Key': apiKey
                    }
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Failed to delete scan');
                }

                alert('Scan deleted successfully');
                await loadScans(currentPage);

            } catch (error) {
                alert('Error: ' + error.message);
                console.error('Error deleting scan:', error);
            }
        }

        function renderPagination(pagination) {
            const container = document.getElementById('pagination');
            container.style.display = 'flex';
            container.innerHTML = '';

            // Previous button
            const prevBtn = document.createElement('button');
            prevBtn.className = 'page-btn';
            prevBtn.textContent = 'Previous';
            prevBtn.disabled = pagination.page === 1;
            prevBtn.onclick = () => loadScans(pagination.page - 1);
            container.appendChild(prevBtn);

            // Page numbers (show max 5)
            const startPage = Math.max(1, pagination.page - 2);
            const endPage = Math.min(pagination.total_pages, startPage + 4);

            for (let i = startPage; i <= endPage; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.className = 'page-btn' + (i === pagination.page ? ' active' : '');
                pageBtn.textContent = i;
                pageBtn.onclick = () => loadScans(i);
                container.appendChild(pageBtn);
            }

            // Next button
            const nextBtn = document.createElement('button');
            nextBtn.className = 'page-btn';
            nextBtn.textContent = 'Next';
            nextBtn.disabled = pagination.page === pagination.total_pages;
            nextBtn.onclick = () => loadScans(pagination.page + 1);
            container.appendChild(nextBtn);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Filter on Enter key
        document.getElementById('filter-system').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                loadScans();
            }
        });
    </script>
</body>
</html>
