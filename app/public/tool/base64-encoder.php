<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Base64 Encoder/Decoder - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
    <style>
        .image-preview {
            max-width: 100%;
            max-height: 400px;
            margin-top: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .stat-box {
            padding: 1rem;
            background: var(--darker-bg);
            border-radius: 8px;
            text-align: center;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
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
        <div class="container" style="max-width: 1000px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Base64 Encoder/Decoder</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Encode and decode Base64 data with support for text, files, and images
            </p>

            <div id="alert-container"></div>

            <!-- Operation Selection -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Select Operation</h2>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem;">
                    <button class="btn btn-primary operation-btn" data-operation="encode" onclick="selectOperation('encode')" style="width: 100%;">
                        Encode Text
                    </button>
                    <button class="btn btn-secondary operation-btn" data-operation="decode" onclick="selectOperation('decode')" style="width: 100%;">
                        Decode Text
                    </button>
                    <button class="btn btn-secondary operation-btn" data-operation="file" onclick="selectOperation('file')" style="width: 100%;">
                        Encode File
                    </button>
                </div>

                <!-- Encode Text Form -->
                <div id="encode-form" style="display: block;">
                    <div class="form-group">
                        <label for="encode-input">Input Text</label>
                        <textarea id="encode-input" rows="6" placeholder="Enter text to encode..." style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="url-safe-encode"> URL-Safe Encoding
                            </label>
                        </div>
                        <div class="form-group">
                            <label for="line-break-encode">Line Breaks</label>
                            <select id="line-break-encode" style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                                <option value="none">None</option>
                                <option value="64">Every 64 characters</option>
                                <option value="76" selected>Every 76 characters</option>
                            </select>
                        </div>
                    </div>

                    <button class="btn btn-primary" onclick="encodeText()" style="width: 100%;">
                        Encode to Base64
                    </button>
                </div>

                <!-- Decode Text Form -->
                <div id="decode-form" style="display: none;">
                    <div class="form-group">
                        <label for="decode-input">Base64 Input</label>
                        <textarea id="decode-input" rows="6" placeholder="Enter Base64 string to decode..." style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;"></textarea>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="url-safe-decode"> URL-Safe Decoding
                        </label>
                    </div>

                    <button class="btn btn-primary" onclick="decodeText()" style="width: 100%;">
                        Decode from Base64
                    </button>
                </div>

                <!-- File Upload Form -->
                <div id="file-form" style="display: none;">
                    <div class="upload-area" id="upload-area" style="border: 2px dashed var(--border-color); border-radius: 8px; padding: 3rem; text-align: center; cursor: pointer; transition: all 0.3s;">
                        <div class="upload-icon" style="font-size: 3rem; margin-bottom: 1rem;">📁</div>
                        <p>Drag & drop your file here or click to browse</p>
                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.5rem;">
                            Maximum file size: 10MB
                        </p>
                        <input type="file" id="file-input" style="display: none;">
                    </div>

                    <div id="file-info" style="display: none; margin-top: 1.5rem;">
                        <p><strong>Selected file:</strong> <span id="filename"></span></p>
                        <p><strong>Size:</strong> <span id="filesize"></span></p>

                        <div class="form-group" style="margin-top: 1rem;">
                            <label for="line-break-file">Line Breaks</label>
                            <select id="line-break-file" style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                                <option value="none">None</option>
                                <option value="64">Every 64 characters</option>
                                <option value="76" selected>Every 76 characters</option>
                            </select>
                        </div>

                        <button class="btn btn-primary" onclick="encodeFile()" style="width: 100%; margin-top: 1rem;">
                            Encode File to Base64
                        </button>
                    </div>
                </div>
            </div>

            <!-- Results Section -->
            <div class="feature-card" id="results-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Results</h2>
                <div id="results-content"></div>
            </div>

            <!-- Information Section -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">About Base64 Encoding</h2>

                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem;">
                    <div>
                        <h3 style="color: var(--accent-color); margin-bottom: 0.75rem;">What is Base64?</h3>
                        <p style="margin-bottom: 1rem;">
                            Base64 is a binary-to-text encoding scheme that represents binary data in ASCII string format.
                            It's commonly used to encode data that needs to be stored or transferred over media designed
                            to handle text.
                        </p>
                        <ul style="margin-left: 1.5rem;">
                            <li>Email attachments (MIME)</li>
                            <li>Data URLs in HTML/CSS</li>
                            <li>JSON/XML data transfer</li>
                            <li>Storing binary data in databases</li>
                        </ul>
                    </div>

                    <div>
                        <h3 style="color: var(--accent-color); margin-bottom: 0.75rem;">Encoding Information</h3>
                        <ul style="margin-left: 1.5rem;">
                            <li><strong>Character Set:</strong> A-Z, a-z, 0-9, +, /</li>
                            <li><strong>Padding:</strong> Uses = character</li>
                            <li><strong>URL-Safe:</strong> Replaces + with - and / with _</li>
                            <li><strong>Size Increase:</strong> ~33% larger than original</li>
                            <li><strong>Line Breaks:</strong> Optional (MIME standard: 76 chars)</li>
                        </ul>
                    </div>
                </div>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Examples</h3>
                <p style="margin-bottom: 0.5rem;"><strong>Plain Text:</strong></p>
                <code style="display: block; background: var(--darker-bg); padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem;">Hello, World!</code>

                <p style="margin-bottom: 0.5rem;"><strong>Base64 Encoded:</strong></p>
                <code style="display: block; background: var(--darker-bg); padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem;">SGVsbG8sIFdvcmxkIQ==</code>

                <p style="margin-bottom: 0.5rem;"><strong>Data URL (Image Example):</strong></p>
                <code style="display: block; background: var(--darker-bg); padding: 0.5rem; border-radius: 4px; word-break: break-all;">data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUA...</code>
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

    <script src="/assets/js/main.js?v=<?= time() ?>"></script>
    <script>
        let selectedOperation = 'encode';
        let selectedFile = null;

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
            document.getElementById('encode-form').style.display = operation === 'encode' ? 'block' : 'none';
            document.getElementById('decode-form').style.display = operation === 'decode' ? 'block' : 'none';
            document.getElementById('file-form').style.display = operation === 'file' ? 'block' : 'none';

            // Reset file selection
            if (operation !== 'file') {
                selectedFile = null;
                document.getElementById('file-info').style.display = 'none';
            }
        }

        // File upload handling
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('file-input');

        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = 'var(--primary-color)';
            uploadArea.style.backgroundColor = 'var(--darker-bg)';
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.style.borderColor = 'var(--border-color)';
            uploadArea.style.backgroundColor = 'transparent';
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = 'var(--border-color)';
            uploadArea.style.backgroundColor = 'transparent';
            handleFile(e.dataTransfer.files[0]);
        });

        fileInput.addEventListener('change', (e) => {
            handleFile(e.target.files[0]);
        });

        function handleFile(file) {
            if (!file) return;

            // Check file size (10MB limit)
            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                showAlert('File size exceeds 10MB limit', 'error');
                return;
            }

            selectedFile = file;
            document.getElementById('filename').textContent = file.name;
            document.getElementById('filesize').textContent = formatFileSize(file.size);
            document.getElementById('file-info').style.display = 'block';
        }

        async function encodeText() {
            const inputText = document.getElementById('encode-input').value;
            const urlSafe = document.getElementById('url-safe-encode').checked;
            const lineBreak = document.getElementById('line-break-encode').value;

            if (!inputText.trim()) {
                showAlert('Please enter text to encode', 'error');
                return;
            }

            const resultsSection = document.getElementById('results-section');
            const resultsContent = document.getElementById('results-content');

            resultsSection.style.display = 'block';
            resultsContent.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="spinner" style="margin: 0 auto;"></div><p style="margin-top: 1rem; color: var(--text-secondary);">Encoding...</p></div>';

            try {
                const data = await apiRequest('/api/v1/tools/base64-encoder', {
                    method: 'POST',
                    body: JSON.stringify({
                        operation: 'encode',
                        input: inputText,
                        url_safe: urlSafe,
                        line_break: lineBreak
                    })
                });

                displayEncodeResults(data.data);
            } catch (error) {
                resultsContent.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        }

        async function decodeText() {
            const inputText = document.getElementById('decode-input').value;
            const urlSafe = document.getElementById('url-safe-decode').checked;

            if (!inputText.trim()) {
                showAlert('Please enter Base64 data to decode', 'error');
                return;
            }

            const resultsSection = document.getElementById('results-section');
            const resultsContent = document.getElementById('results-content');

            resultsSection.style.display = 'block';
            resultsContent.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="spinner" style="margin: 0 auto;"></div><p style="margin-top: 1rem; color: var(--text-secondary);">Decoding...</p></div>';

            try {
                const data = await apiRequest('/api/v1/tools/base64-encoder', {
                    method: 'POST',
                    body: JSON.stringify({
                        operation: 'decode',
                        input: inputText,
                        url_safe: urlSafe
                    })
                });

                displayDecodeResults(data.data);
            } catch (error) {
                resultsContent.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        }

        async function encodeFile() {
            if (!selectedFile) {
                showAlert('Please select a file', 'error');
                return;
            }

            const lineBreak = document.getElementById('line-break-file').value;
            const resultsSection = document.getElementById('results-section');
            const resultsContent = document.getElementById('results-content');

            resultsSection.style.display = 'block';
            resultsContent.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="spinner" style="margin: 0 auto;"></div><p style="margin-top: 1rem; color: var(--text-secondary);">Encoding file...</p></div>';

            try {
                const reader = new FileReader();
                reader.onload = async function(e) {
                    const base64 = e.target.result.split(',')[1]; // Remove data URL prefix

                    try {
                        const data = await apiRequest('/api/v1/tools/base64-encoder', {
                            method: 'POST',
                            body: JSON.stringify({
                                operation: 'encode_file',
                                input: base64,
                                filename: selectedFile.name,
                                mime_type: selectedFile.type,
                                line_break: lineBreak
                            })
                        });

                        displayFileEncodeResults(data.data);
                    } catch (error) {
                        resultsContent.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
                    }
                };
                reader.readAsDataURL(selectedFile);
            } catch (error) {
                resultsContent.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        }

        function displayEncodeResults(result) {
            const html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Encoded Result</h3>
                    <textarea readonly rows="8" id="result-text" style="width: 100%; padding: 0.75rem; background: var(--dark-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;">${result.encoded}</textarea>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                        <button class="btn btn-primary" onclick="copyResult()">
                            Copy to Clipboard
                        </button>
                        <button class="btn btn-secondary" onclick="downloadResult()">
                            Download as File
                        </button>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value">${result.statistics.input_size}</div>
                        <div class="stat-label">Input Bytes</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${result.statistics.output_size}</div>
                        <div class="stat-label">Output Bytes</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${result.statistics.size_increase}</div>
                        <div class="stat-label">Size Increase</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${result.statistics.encoding_type}</div>
                        <div class="stat-label">Encoding Type</div>
                    </div>
                </div>
            `;

            document.getElementById('results-content').innerHTML = html;
        }

        function displayDecodeResults(result) {
            let html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Decoded Result</h3>
            `;

            // Check if it's an image
            if (result.is_image) {
                html += `
                    <div style="margin-bottom: 1rem;">
                        <p style="margin-bottom: 0.5rem;"><strong>Image Preview:</strong></p>
                        <img src="${result.data_url}" alt="Decoded Image" class="image-preview">
                    </div>
                    <div style="margin-top: 1rem;">
                        <p><strong>MIME Type:</strong> ${result.mime_type}</p>
                        <p><strong>Size:</strong> ${formatFileSize(result.statistics.output_size)}</p>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                        <button class="btn btn-primary" onclick="downloadDecodedFile('${result.data_url}', 'image')">
                            Download Image
                        </button>
                        <button class="btn btn-secondary" onclick="copyDataURL('${result.data_url}')">
                            Copy Data URL
                        </button>
                    </div>
                `;
            } else {
                html += `
                    <textarea readonly rows="8" id="result-text" style="width: 100%; padding: 0.75rem; background: var(--dark-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;">${escapeHtml(result.decoded)}</textarea>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                        <button class="btn btn-primary" onclick="copyResult()">
                            Copy to Clipboard
                        </button>
                        <button class="btn btn-secondary" onclick="downloadResult()">
                            Download as File
                        </button>
                    </div>
                `;
            }

            html += `</div>`;

            html += `
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value">${result.statistics.input_size}</div>
                        <div class="stat-label">Input Bytes</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${result.statistics.output_size}</div>
                        <div class="stat-label">Output Bytes</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${result.statistics.size_decrease}</div>
                        <div class="stat-label">Size Decrease</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${result.is_valid ? 'Valid' : 'Invalid'}</div>
                        <div class="stat-label">Base64 Format</div>
                    </div>
                </div>
            `;

            document.getElementById('results-content').innerHTML = html;
        }

        function displayFileEncodeResults(result) {
            const html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Encoded File Result</h3>
                    <p style="margin-bottom: 0.5rem;"><strong>Data URL:</strong></p>
                    <textarea readonly rows="3" id="data-url-text" style="width: 100%; padding: 0.75rem; background: var(--dark-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical; margin-bottom: 1rem;">${result.data_url}</textarea>

                    <p style="margin-bottom: 0.5rem;"><strong>Base64 Only:</strong></p>
                    <textarea readonly rows="8" id="result-text" style="width: 100%; padding: 0.75rem; background: var(--dark-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;">${result.encoded}</textarea>

                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 1rem;">
                        <button class="btn btn-primary" onclick="copyResult()">
                            Copy Base64
                        </button>
                        <button class="btn btn-secondary" onclick="copyDataURL('${result.data_url}')">
                            Copy Data URL
                        </button>
                        <button class="btn btn-secondary" onclick="downloadResult()">
                            Download
                        </button>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value">${result.statistics.input_size}</div>
                        <div class="stat-label">File Size (bytes)</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${result.statistics.output_size}</div>
                        <div class="stat-label">Base64 Size</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${result.statistics.size_increase}</div>
                        <div class="stat-label">Size Increase</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">${result.mime_type || 'N/A'}</div>
                        <div class="stat-label">MIME Type</div>
                    </div>
                </div>
            `;

            document.getElementById('results-content').innerHTML = html;
        }

        function copyResult() {
            const resultText = document.getElementById('result-text');
            if (resultText) {
                resultText.select();
                document.execCommand('copy');
                showAlert('Copied to clipboard!', 'success');
            }
        }

        function copyDataURL(dataUrl) {
            const tempInput = document.createElement('textarea');
            tempInput.value = dataUrl;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            showAlert('Data URL copied to clipboard!', 'success');
        }

        function downloadResult() {
            const resultText = document.getElementById('result-text');
            if (resultText) {
                const blob = new Blob([resultText.value], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = selectedOperation === 'encode' ? 'encoded.txt' : 'decoded.txt';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                showAlert('File downloaded!', 'success');
            }
        }

        function downloadDecodedFile(dataUrl, type) {
            const a = document.createElement('a');
            a.href = dataUrl;
            a.download = 'decoded_' + type + '_' + Date.now();
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            showAlert('File downloaded!', 'success');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
