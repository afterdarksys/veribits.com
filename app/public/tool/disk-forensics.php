<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disk Forensics - The Sleuth Kit - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
    <style>
        .upload-zone {
            border: 3px dashed var(--border-color);
            border-radius: 8px;
            padding: 3rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-zone:hover, .upload-zone.drag-over {
            border-color: var(--primary-color);
            background: rgba(var(--primary-rgb), 0.1);
        }
        .file-tree {
            background: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            max-height: 500px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.9rem;
        }
        .file-item {
            padding: 0.5rem;
            margin: 0.25rem 0;
            border-radius: 4px;
            cursor: pointer;
        }
        .file-item:hover {
            background: rgba(255,255,255,0.05);
        }
        .file-item.deleted {
            color: var(--error-color);
        }
        .timeline-entry {
            padding: 0.75rem;
            margin: 0.5rem 0;
            background: var(--darker-bg);
            border-radius: 6px;
            border-left: 3px solid var(--primary-color);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        .stat-card {
            background: var(--darker-bg);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        .stat-label {
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: var(--darker-bg);
            border-radius: 15px;
            overflow: hidden;
            margin: 1rem 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        .warning-box {
            background: rgba(245, 158, 11, 0.1);
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
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
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Disk Forensics</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 1rem;">
                Powered by The Sleuth Kit (TSK) - Professional Digital Forensics
            </p>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem; font-size: 0.95rem;">
                Analyze disk images, recover deleted files, generate timelines, and extract evidence
            </p>

            <div class="warning-box">
                <strong>⚠️ Legal Notice:</strong> Only analyze disk images you own or have legal authorization to examine.
                Unauthorized computer forensics is illegal. This tool is for authorized investigations only.
            </div>

            <div class="warning-box" style="background: rgba(59, 130, 246, 0.1); border-left-color: #3b82f6;">
                <strong>🔐 Authentication Required:</strong> This tool requires a VeriBits account for security and compliance.
                <a href="/signup.php" style="color: var(--primary-color); text-decoration: underline;">Sign up free</a>
            </div>

            <div id="alert-container"></div>

            <!-- Upload Section -->
            <div class="feature-card" id="upload-section">
                <h2 style="margin-bottom: 1.5rem;">📤 Upload Disk Image</h2>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                    Upload a disk image for forensic analysis. Max 2GB for web upload.
                    For larger images, use the <a href="/cli.php" style="color: var(--primary-color);">System Client</a>.
                </p>

                <div class="upload-zone" id="upload-zone">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">💾</div>
                    <h3 style="margin-bottom: 0.5rem;">Drop disk image here or click to browse</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                        Supported: .dd, .raw, .img, .E01, .aff, .vhd, .vhdx, .vmdk
                    </p>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">
                        Maximum file size: 2GB
                    </p>
                    <input type="file" id="file-input" accept=".dd,.raw,.img,.e01,.aff,.vhd,.vhdx,.vmdk" style="display: none;">
                </div>

                <div id="upload-progress" style="display: none; margin-top: 2rem;">
                    <h3 style="margin-bottom: 1rem;">Uploading...</h3>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill">0%</div>
                    </div>
                    <p id="upload-status" style="text-align: center; color: var(--text-secondary);"></p>
                </div>
            </div>

            <!-- Analysis Options -->
            <div class="feature-card" id="analysis-options" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">🔍 Analysis Options</h2>

                <div style="display: grid; gap: 1rem;">
                    <label style="display: flex; align-items: center; padding: 1rem; background: var(--darker-bg); border-radius: 8px; cursor: pointer;">
                        <input type="checkbox" name="analysis" value="list_files" checked style="margin-right: 1rem; width: 20px; height: 20px;">
                        <div>
                            <strong>📁 List All Files</strong>
                            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.25rem;">Display all files and directories in the image</p>
                        </div>
                    </label>

                    <label style="display: flex; align-items: center; padding: 1rem; background: var(--darker-bg); border-radius: 8px; cursor: pointer;">
                        <input type="checkbox" name="analysis" value="recover_deleted" style="margin-right: 1rem; width: 20px; height: 20px;">
                        <div>
                            <strong>♻️ Recover Deleted Files</strong>
                            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.25rem;">Attempt to recover deleted files from unallocated space</p>
                        </div>
                    </label>

                    <label style="display: flex; align-items: center; padding: 1rem; background: var(--darker-bg); border-radius: 8px; cursor: pointer;">
                        <input type="checkbox" name="analysis" value="timeline" style="margin-right: 1rem; width: 20px; height: 20px;">
                        <div>
                            <strong>⏱️ Generate Timeline</strong>
                            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.25rem;">Create forensic timeline of file activities</p>
                        </div>
                    </label>

                    <label style="display: flex; align-items: center; padding: 1rem; background: var(--darker-bg); border-radius: 8px; cursor: pointer;">
                        <input type="checkbox" name="analysis" value="fsstat" checked style="margin-right: 1rem; width: 20px; height: 20px;">
                        <div>
                            <strong>📊 File System Statistics</strong>
                            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.25rem;">Get detailed file system information</p>
                        </div>
                    </label>

                    <label style="display: flex; align-items: center; padding: 1rem; background: var(--darker-bg); border-radius: 8px; cursor: pointer;">
                        <input type="checkbox" name="analysis" value="partitions" checked style="margin-right: 1rem; width: 20px; height: 20px;">
                        <div>
                            <strong>💽 Partition Layout</strong>
                            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.25rem;">Display partition table and layout</p>
                        </div>
                    </label>
                </div>

                <button class="btn btn-primary" id="analyze-btn" onclick="startAnalysis()" style="width: 100%; margin-top: 2rem;">
                    🔍 Start Analysis
                </button>
            </div>

            <!-- Results Section -->
            <div id="results-section" style="display: none; margin-top: 2rem;">
                <div class="feature-card">
                    <h2 style="margin-bottom: 1.5rem;">📊 Analysis Results</h2>
                    <div id="results-content"></div>
                </div>
            </div>

            <!-- Information Section -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">About Disk Forensics</h2>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">The Sleuth Kit (TSK)</h3>
                <p style="margin-bottom: 1rem;">
                    The Sleuth Kit is an industry-standard collection of command-line digital forensics tools used worldwide.
                    It enables analysis of disk images and file systems from various operating systems.
                </p>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Supported File Systems</h3>
                <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                    <li><strong>Windows:</strong> NTFS, FAT12/16/32, exFAT</li>
                    <li><strong>Linux:</strong> Ext2/3/4, XFS, BtrFS</li>
                    <li><strong>macOS:</strong> HFS+, APFS (limited)</li>
                    <li><strong>Other:</strong> ISO9660, UFS, YAFFS2</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Use Cases</h3>
                <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                    <li><strong>Law Enforcement:</strong> Analyze seized hard drives and extract evidence</li>
                    <li><strong>Incident Response:</strong> Investigate security breaches and data theft</li>
                    <li><strong>Data Recovery:</strong> Recover accidentally deleted files</li>
                    <li><strong>Compliance:</strong> Forensic audits and investigations</li>
                </ul>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">For Large Images</h3>
                <p>
                    Web upload is limited to 2GB. For larger disk images (10GB, 100GB+), use the
                    <a href="/cli.php" style="color: var(--primary-color); text-decoration: underline;">VeriBits System Client</a>
                    which can process images locally and send results to VeriBits.
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
        let currentAnalysisId = null;

        // Upload zone setup
        const uploadZone = document.getElementById('upload-zone');
        const fileInput = document.getElementById('file-input');

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

            if (e.dataTransfer.files.length > 0) {
                handleFileUpload(e.dataTransfer.files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileUpload(e.target.files[0]);
            }
        });

        async function handleFileUpload(file) {
            // Validate file size
            const maxSize = 2 * 1024 * 1024 * 1024; // 2GB
            if (file.size > maxSize) {
                showAlert('File too large. Maximum 2GB for web upload. Use system client for larger images.', 'error');
                return;
            }

            // Show progress
            document.getElementById('upload-progress').style.display = 'block';
            document.getElementById('upload-status').textContent = `Uploading ${file.name}...`;

            const formData = new FormData();
            formData.append('file', file);
            formData.append('name', file.name);

            try {
                const xhr = new XMLHttpRequest();

                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        document.getElementById('progress-fill').style.width = percent + '%';
                        document.getElementById('progress-fill').textContent = percent + '%';
                    }
                });

                xhr.addEventListener('load', () => {
                    const response = JSON.parse(xhr.responseText);

                    if (response.success) {
                        currentAnalysisId = response.data.analysis_id;
                        showAlert(`Upload successful! Analysis ID: ${currentAnalysisId}`, 'success');

                        // Show analysis options
                        document.getElementById('analysis-options').style.display = 'block';
                        document.getElementById('upload-progress').style.display = 'none';

                        // Display image info
                        displayImageInfo(response.data);
                    } else {
                        showAlert(response.error?.message || 'Upload failed', 'error');
                        document.getElementById('upload-progress').style.display = 'none';
                    }
                });

                xhr.addEventListener('error', () => {
                    showAlert('Upload failed. Please try again.', 'error');
                    document.getElementById('upload-progress').style.display = 'none';
                });

                xhr.open('POST', '/api/v1/forensics/disk/upload');
                const token = getAuthToken();
                if (token) {
                    xhr.setRequestHeader('Authorization', `Bearer ${token}`);
                }
                xhr.send(formData);

            } catch (error) {
                showAlert('Upload error: ' + error.message, 'error');
                document.getElementById('upload-progress').style.display = 'none';
            }
        }

        function displayImageInfo(data) {
            const info = `
                <div style="background: var(--darker-bg); padding: 1.5rem; border-radius: 8px; margin-top: 1rem;">
                    <h3 style="margin-bottom: 1rem;">Image Information</h3>
                    <div style="display: grid; gap: 0.5rem;">
                        <div><strong>Filename:</strong> ${data.filename}</div>
                        <div><strong>Size:</strong> ${formatFileSize(data.size)}</div>
                        <div><strong>Format:</strong> ${data.format.toUpperCase()}</div>
                        <div><strong>MD5:</strong> <code style="font-size: 0.85rem;">${data.md5}</code></div>
                        <div><strong>SHA-256:</strong> <code style="font-size: 0.85rem;">${data.sha256}</code></div>
                    </div>
                </div>
            `;

            document.getElementById('analysis-options').insertAdjacentHTML('afterbegin', info);
        }

        async function startAnalysis() {
            if (!currentAnalysisId) {
                showAlert('Please upload a disk image first', 'error');
                return;
            }

            const operations = Array.from(document.querySelectorAll('input[name="analysis"]:checked'))
                .map(cb => cb.value);

            if (operations.length === 0) {
                showAlert('Please select at least one analysis option', 'error');
                return;
            }

            const analyzeBtn = document.getElementById('analyze-btn');
            analyzeBtn.disabled = true;
            analyzeBtn.textContent = 'Analyzing...';

            document.getElementById('results-section').style.display = 'block';
            document.getElementById('results-content').innerHTML = '<div class="spinner"></div><p style="text-align: center; margin-top: 1rem;">Running forensic analysis... This may take several minutes.</p>';

            try {
                const data = await apiRequest('/api/v1/forensics/disk/analyze', {
                    method: 'POST',
                    body: JSON.stringify({
                        analysis_id: currentAnalysisId,
                        operations: operations
                    })
                });

                displayResults(data.data);
            } catch (error) {
                document.getElementById('results-content').innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            } finally {
                analyzeBtn.disabled = false;
                analyzeBtn.textContent = '🔍 Start Analysis';
            }
        }

        function displayResults(data) {
            let html = '<div class="stats-grid">';

            if (data.results.files) {
                html += `<div class="stat-card">
                    <div class="stat-value">${data.results.files.total.toLocaleString()}</div>
                    <div class="stat-label">Files Found</div>
                </div>`;
            }

            if (data.results.recovered) {
                html += `<div class="stat-card">
                    <div class="stat-value" style="color: var(--success-color);">${data.results.recovered.recovered_count}</div>
                    <div class="stat-label">Deleted Files Recovered</div>
                </div>`;
            }

            if (data.results.timeline) {
                html += `<div class="stat-card">
                    <div class="stat-value">${data.results.timeline.entries_count.toLocaleString()}</div>
                    <div class="stat-label">Timeline Entries</div>
                </div>`;
            }

            if (data.results.partitions) {
                html += `<div class="stat-card">
                    <div class="stat-value">${data.results.partitions.count}</div>
                    <div class="stat-label">Partitions</div>
                </div>`;
            }

            html += '</div>';

            // File listing
            if (data.results.files) {
                html += '<h3 style="margin-top: 2rem; margin-bottom: 1rem;">📁 File Listing</h3>';
                html += '<div class="file-tree">';
                data.results.files.files.forEach(file => {
                    const icon = file.type === 'directory' ? '📁' : '📄';
                    const deletedClass = file.deleted ? 'deleted' : '';
                    const deletedTag = file.deleted ? ' [DELETED]' : '';
                    html += `<div class="file-item ${deletedClass}">${icon} ${file.path}${deletedTag}</div>`;
                });
                if (data.results.files.truncated) {
                    html += '<p style="color: var(--text-secondary); margin-top: 1rem;">... and more (showing first 1000)</p>';
                }
                html += '</div>';
            }

            // File system stats
            if (data.results.filesystem) {
                html += '<h3 style="margin-top: 2rem; margin-bottom: 1rem;">📊 File System Information</h3>';
                html += '<div style="background: var(--darker-bg); padding: 1rem; border-radius: 8px;"><pre style="font-size: 0.85rem; overflow-x: auto;">';
                for (const [key, value] of Object.entries(data.results.filesystem)) {
                    html += `${key}: ${value}\n`;
                }
                html += '</pre></div>';
            }

            // Partitions
            if (data.results.partitions && data.results.partitions.partitions) {
                html += '<h3 style="margin-top: 2rem; margin-bottom: 1rem;">💽 Partition Layout</h3>';
                html += '<div style="overflow-x: auto;"><table style="width: 100%; border-collapse: collapse;">';
                html += '<thead><tr style="background: var(--darker-bg);"><th style="padding: 0.75rem; text-align: left;">Slot</th><th style="padding: 0.75rem; text-align: left;">Start</th><th style="padding: 0.75rem; text-align: left;">End</th><th style="padding: 0.75rem; text-align: left;">Length</th><th style="padding: 0.75rem; text-align: left;">Description</th></tr></thead><tbody>';
                data.results.partitions.partitions.forEach(p => {
                    html += `<tr style="border-bottom: 1px solid var(--border-color);"><td style="padding: 0.75rem;">${p.slot}</td><td style="padding: 0.75rem;">${p.start}</td><td style="padding: 0.75rem;">${p.end}</td><td style="padding: 0.75rem;">${p.length}</td><td style="padding: 0.75rem;">${p.description}</td></tr>`;
                });
                html += '</tbody></table></div>';
            }

            // Timeline
            if (data.results.timeline && data.results.timeline.entries) {
                html += '<h3 style="margin-top: 2rem; margin-bottom: 1rem;">⏱️ Forensic Timeline</h3>';
                data.results.timeline.entries.forEach(entry => {
                    html += `<div class="timeline-entry">
                        <div><strong>${entry.date}</strong></div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">${entry.file}</div>
                        <div style="margin-top: 0.5rem; font-size: 0.85rem;">Type: ${entry.type} | Size: ${entry.size}</div>
                    </div>`;
                });
            }

            document.getElementById('results-content').innerHTML = html;
        }
    </script>
</body>
</html>
