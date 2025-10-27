<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JSON/YAML Validator - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .editor-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .editor-container {
                grid-template-columns: 1fr;
            }
        }

        .editor-panel {
            display: flex;
            flex-direction: column;
        }

        .editor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding: 0.5rem 0;
        }

        .editor-label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.1rem;
        }

        .editor-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--darker-bg);
            color: var(--text-primary);
        }

        .btn-small:hover {
            background: var(--primary-color);
            color: white;
        }

        .editor-wrapper {
            position: relative;
            flex: 1;
            min-height: 400px;
        }

        .line-numbers {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 45px;
            background: var(--darker-bg);
            color: var(--text-secondary);
            padding: 0.75rem 0.5rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
            text-align: right;
            border-radius: 8px 0 0 8px;
            user-select: none;
            overflow: hidden;
        }

        .editor-textarea {
            width: 100%;
            height: 100%;
            min-height: 400px;
            padding: 0.75rem 0.75rem 0.75rem 55px;
            background: var(--dark-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
            resize: vertical;
            overflow-wrap: break-word;
            white-space: pre;
            overflow-x: auto;
        }

        .editor-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .editor-textarea.error {
            border-color: var(--error-color);
        }

        .editor-textarea.success {
            border-color: var(--success-color);
        }

        .stats-bar {
            display: flex;
            gap: 1.5rem;
            padding: 0.75rem 1rem;
            background: var(--darker-bg);
            border-radius: 8px;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .stat-item {
            display: flex;
            gap: 0.5rem;
        }

        .stat-label {
            font-weight: 600;
        }

        .format-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .option-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .option-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .control-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .validation-status {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
        }

        .validation-status.success {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .validation-status.error {
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
        }

        .error-details {
            margin-top: 0.75rem;
            padding: 0.75rem;
            background: var(--darker-bg);
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
        }

        .examples-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 768px) {
            .examples-grid {
                grid-template-columns: 1fr;
            }
        }

        .example-card {
            background: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .example-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .example-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .example-code {
            background: var(--dark-bg);
            padding: 0.75rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            overflow-x: auto;
            white-space: pre;
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
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">JSON/YAML Validator</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Validate, format, and convert between JSON and YAML with real-time feedback
            </p>

            <div id="alert-container"></div>

            <!-- Main Editor Section -->
            <div class="feature-card">
                <div class="format-options">
                    <div class="option-group">
                        <label class="option-label">Input Format</label>
                        <select id="input-format" class="form-control">
                            <option value="json">JSON</option>
                            <option value="yaml">YAML</option>
                        </select>
                    </div>
                    <div class="option-group">
                        <label class="option-label">JSON Indentation</label>
                        <select id="indent-size" class="form-control">
                            <option value="2">2 Spaces</option>
                            <option value="4" selected>4 Spaces</option>
                            <option value="tab">Tabs</option>
                        </select>
                    </div>
                    <div class="option-group">
                        <label class="option-label">Show Line Numbers</label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                            <input type="checkbox" id="show-line-numbers" checked>
                            <span>Enabled</span>
                        </label>
                    </div>
                </div>

                <div class="editor-container">
                    <!-- Input Editor -->
                    <div class="editor-panel">
                        <div class="editor-header">
                            <span class="editor-label">Input</span>
                            <div class="editor-actions">
                                <button class="btn-small" onclick="clearInput()" title="Clear">Clear</button>
                                <button class="btn-small" onclick="copyInput()" title="Copy">Copy</button>
                            </div>
                        </div>
                        <div class="editor-wrapper">
                            <div class="line-numbers" id="input-line-numbers"></div>
                            <textarea
                                id="input-editor"
                                class="editor-textarea"
                                placeholder="Paste your JSON or YAML here..."
                                spellcheck="false"
                            ></textarea>
                        </div>
                        <div class="stats-bar">
                            <div class="stat-item">
                                <span class="stat-label">Lines:</span>
                                <span id="input-lines">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Characters:</span>
                                <span id="input-chars">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Size:</span>
                                <span id="input-size">0 B</span>
                            </div>
                        </div>
                    </div>

                    <!-- Output Editor -->
                    <div class="editor-panel">
                        <div class="editor-header">
                            <span class="editor-label">Output</span>
                            <div class="editor-actions">
                                <button class="btn-small" onclick="clearOutput()" title="Clear">Clear</button>
                                <button class="btn-small" onclick="copyOutput()" title="Copy">Copy</button>
                            </div>
                        </div>
                        <div class="editor-wrapper">
                            <div class="line-numbers" id="output-line-numbers"></div>
                            <textarea
                                id="output-editor"
                                class="editor-textarea"
                                placeholder="Formatted/converted output will appear here..."
                                spellcheck="false"
                                readonly
                            ></textarea>
                        </div>
                        <div class="stats-bar">
                            <div class="stat-item">
                                <span class="stat-label">Lines:</span>
                                <span id="output-lines">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Characters:</span>
                                <span id="output-chars">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Size:</span>
                                <span id="output-size">0 B</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Validation Status -->
                <div id="validation-status" class="validation-status"></div>

                <!-- Control Buttons -->
                <div class="control-buttons">
                    <button class="btn btn-primary" onclick="validateData()">
                        Validate
                    </button>
                    <button class="btn btn-primary" onclick="formatData()">
                        Format/Prettify
                    </button>
                    <button class="btn btn-primary" onclick="minifyData()">
                        Minify
                    </button>
                    <button class="btn btn-primary" onclick="convertData()">
                        Convert JSON ↔ YAML
                    </button>
                </div>
            </div>

            <!-- Examples Section -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Examples & Templates</h2>

                <div class="examples-grid">
                    <!-- JSON Examples -->
                    <div>
                        <h3 style="color: var(--accent-color); margin-bottom: 1rem;">JSON Examples</h3>

                        <div class="example-card" onclick="loadExample('json-simple')">
                            <div class="example-title">Simple Object</div>
                            <div class="example-code">{
  "name": "John Doe",
  "age": 30,
  "email": "john@example.com"
}</div>
                        </div>

                        <div class="example-card" onclick="loadExample('json-nested')" style="margin-top: 1rem;">
                            <div class="example-title">Nested Structure</div>
                            <div class="example-code">{
  "user": {
    "profile": {
      "name": "Jane",
      "settings": {
        "theme": "dark"
      }
    }
  }
}</div>
                        </div>

                        <div class="example-card" onclick="loadExample('json-array')" style="margin-top: 1rem;">
                            <div class="example-title">Array of Objects</div>
                            <div class="example-code">{
  "users": [
    {"id": 1, "name": "Alice"},
    {"id": 2, "name": "Bob"}
  ]
}</div>
                        </div>

                        <div class="example-card" onclick="loadExample('json-config')" style="margin-top: 1rem;">
                            <div class="example-title">Configuration File</div>
                            <div class="example-code">{
  "database": {
    "host": "localhost",
    "port": 5432,
    "credentials": {
      "username": "admin",
      "password": "secret"
    }
  }
}</div>
                        </div>
                    </div>

                    <!-- YAML Examples -->
                    <div>
                        <h3 style="color: var(--accent-color); margin-bottom: 1rem;">YAML Examples</h3>

                        <div class="example-card" onclick="loadExample('yaml-simple')">
                            <div class="example-title">Simple Document</div>
                            <div class="example-code">name: John Doe
age: 30
email: john@example.com</div>
                        </div>

                        <div class="example-card" onclick="loadExample('yaml-nested')" style="margin-top: 1rem;">
                            <div class="example-title">Nested Structure</div>
                            <div class="example-code">user:
  profile:
    name: Jane
    settings:
      theme: dark</div>
                        </div>

                        <div class="example-card" onclick="loadExample('yaml-array')" style="margin-top: 1rem;">
                            <div class="example-title">List/Array</div>
                            <div class="example-code">users:
  - id: 1
    name: Alice
  - id: 2
    name: Bob</div>
                        </div>

                        <div class="example-card" onclick="loadExample('yaml-docker')" style="margin-top: 1rem;">
                            <div class="example-title">Docker Compose</div>
                            <div class="example-code">version: '3.8'
services:
  web:
    image: nginx:latest
    ports:
      - "80:80"</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Features Info -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Features</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                    <div>
                        <h3 style="color: var(--primary-color); margin-bottom: 0.5rem;">Validation</h3>
                        <p style="color: var(--text-secondary);">Real-time syntax validation with detailed error messages and line numbers</p>
                    </div>
                    <div>
                        <h3 style="color: var(--primary-color); margin-bottom: 0.5rem;">Formatting</h3>
                        <p style="color: var(--text-secondary);">Beautify and prettify with customizable indentation options</p>
                    </div>
                    <div>
                        <h3 style="color: var(--primary-color); margin-bottom: 0.5rem;">Conversion</h3>
                        <p style="color: var(--text-secondary);">Seamlessly convert between JSON and YAML formats</p>
                    </div>
                    <div>
                        <h3 style="color: var(--primary-color); margin-bottom: 0.5rem;">Statistics</h3>
                        <p style="color: var(--text-secondary);">Track lines, characters, and file size in real-time</p>
                    </div>
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
        </div>
    </footer>

    <script src="/assets/js/main.js"></script>
    <script>
        const inputEditor = document.getElementById('input-editor');
        const outputEditor = document.getElementById('output-editor');
        const inputFormat = document.getElementById('input-format');
        const indentSize = document.getElementById('indent-size');
        const showLineNumbers = document.getElementById('show-line-numbers');
        const validationStatus = document.getElementById('validation-status');

        // Example templates
        const examples = {
            'json-simple': '{\n  "name": "John Doe",\n  "age": 30,\n  "email": "john@example.com",\n  "active": true\n}',
            'json-nested': '{\n  "user": {\n    "id": 123,\n    "profile": {\n      "name": "Jane Smith",\n      "settings": {\n        "theme": "dark",\n        "notifications": true\n      }\n    },\n    "roles": ["admin", "user"]\n  }\n}',
            'json-array': '{\n  "users": [\n    {\n      "id": 1,\n      "name": "Alice",\n      "role": "developer"\n    },\n    {\n      "id": 2,\n      "name": "Bob",\n      "role": "designer"\n    },\n    {\n      "id": 3,\n      "name": "Charlie",\n      "role": "manager"\n    }\n  ]\n}',
            'json-config': '{\n  "app_name": "VeriBits",\n  "version": "1.0.0",\n  "database": {\n    "host": "localhost",\n    "port": 5432,\n    "name": "veribits_db",\n    "credentials": {\n      "username": "admin",\n      "password": "secret123"\n    },\n    "pool": {\n      "min": 2,\n      "max": 10\n    }\n  },\n  "features": ["auth", "api", "dashboard"]\n}',
            'yaml-simple': 'name: John Doe\nage: 30\nemail: john@example.com\nactive: true',
            'yaml-nested': 'user:\n  id: 123\n  profile:\n    name: Jane Smith\n    settings:\n      theme: dark\n      notifications: true\n  roles:\n    - admin\n    - user',
            'yaml-array': 'users:\n  - id: 1\n    name: Alice\n    role: developer\n  - id: 2\n    name: Bob\n    role: designer\n  - id: 3\n    name: Charlie\n    role: manager',
            'yaml-docker': 'version: \'3.8\'\nservices:\n  web:\n    image: nginx:latest\n    ports:\n      - "80:80"\n    volumes:\n      - ./html:/usr/share/nginx/html\n    environment:\n      - NGINX_HOST=localhost\n      - NGINX_PORT=80\n  db:\n    image: postgres:14\n    environment:\n      - POSTGRES_PASSWORD=secret'
        };

        // Update line numbers
        function updateLineNumbers(editor, lineNumbersElement) {
            const lines = editor.value.split('\n').length;
            let lineNumbersHTML = '';
            for (let i = 1; i <= lines; i++) {
                lineNumbersHTML += i + '\n';
            }
            lineNumbersElement.textContent = lineNumbersHTML;
        }

        // Update statistics
        function updateStats(editor, statsPrefix) {
            const content = editor.value;
            const lines = content.split('\n').length;
            const chars = content.length;
            const bytes = new Blob([content]).size;

            document.getElementById(`${statsPrefix}-lines`).textContent = lines;
            document.getElementById(`${statsPrefix}-chars`).textContent = chars.toLocaleString();
            document.getElementById(`${statsPrefix}-size`).textContent = formatBytes(bytes);
        }

        // Format bytes
        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Toggle line numbers visibility
        showLineNumbers.addEventListener('change', () => {
            const lineNumbersElements = document.querySelectorAll('.line-numbers');
            const textareas = document.querySelectorAll('.editor-textarea');

            if (showLineNumbers.checked) {
                lineNumbersElements.forEach(el => el.style.display = 'block');
                textareas.forEach(ta => ta.style.paddingLeft = '55px');
            } else {
                lineNumbersElements.forEach(el => el.style.display = 'none');
                textareas.forEach(ta => ta.style.paddingLeft = '0.75rem');
            }
        });

        // Input editor events
        inputEditor.addEventListener('input', () => {
            updateLineNumbers(inputEditor, document.getElementById('input-line-numbers'));
            updateStats(inputEditor, 'input');
            hideValidationStatus();
        });

        inputEditor.addEventListener('scroll', () => {
            document.getElementById('input-line-numbers').scrollTop = inputEditor.scrollTop;
        });

        // Output editor events
        outputEditor.addEventListener('input', () => {
            updateLineNumbers(outputEditor, document.getElementById('output-line-numbers'));
            updateStats(outputEditor, 'output');
        });

        outputEditor.addEventListener('scroll', () => {
            document.getElementById('output-line-numbers').scrollTop = outputEditor.scrollTop;
        });

        // Show validation status
        function showValidationStatus(isValid, message, details = null) {
            validationStatus.className = 'validation-status ' + (isValid ? 'success' : 'error');

            let html = `<strong>${isValid ? '✓' : '✗'} ${message}</strong>`;

            if (details) {
                html += `<div class="error-details">${details}</div>`;
            }

            validationStatus.innerHTML = html;
            validationStatus.style.display = 'block';

            // Update editor borders
            inputEditor.className = 'editor-textarea ' + (isValid ? 'success' : 'error');
        }

        function hideValidationStatus() {
            validationStatus.style.display = 'none';
            inputEditor.className = 'editor-textarea';
        }

        // Get indent string
        function getIndentString() {
            const size = indentSize.value;
            if (size === 'tab') return '\t';
            return ' '.repeat(parseInt(size));
        }

        // Validate data
        async function validateData() {
            const data = inputEditor.value.trim();
            const type = inputFormat.value;

            if (!data) {
                showAlert('Please enter some data to validate', 'error');
                return;
            }

            try {
                const response = await apiRequest('/api/v1/tools/validate-data', {
                    method: 'POST',
                    body: JSON.stringify({ data, type, action: 'validate' })
                });

                const result = response.data;

                if (result.is_valid) {
                    showValidationStatus(true, `Valid ${type.toUpperCase()}`);
                    showAlert('Validation successful!', 'success');
                } else {
                    showValidationStatus(false, 'Validation failed', result.error || 'Unknown error');
                }
            } catch (error) {
                showValidationStatus(false, 'Validation failed', error.message);
                showAlert(error.message, 'error');
            }
        }

        // Format data
        async function formatData() {
            const data = inputEditor.value.trim();
            const type = inputFormat.value;

            if (!data) {
                showAlert('Please enter some data to format', 'error');
                return;
            }

            try {
                const response = await apiRequest('/api/v1/tools/validate-data', {
                    method: 'POST',
                    body: JSON.stringify({ data, type, action: 'format' })
                });

                const result = response.data;

                if (result.is_valid && result.formatted) {
                    // Apply custom indentation for JSON
                    let formatted = result.formatted;
                    if (type === 'json') {
                        const decoded = JSON.parse(data);
                        const indent = getIndentString();

                        if (indent === '\t') {
                            formatted = JSON.stringify(decoded, null, '\t');
                        } else {
                            formatted = JSON.stringify(decoded, null, indent);
                        }
                    }

                    outputEditor.value = formatted;
                    updateLineNumbers(outputEditor, document.getElementById('output-line-numbers'));
                    updateStats(outputEditor, 'output');
                    showValidationStatus(true, `Formatted successfully`);
                    showAlert('Data formatted successfully!', 'success');
                } else {
                    throw new Error(result.error || 'Invalid data format');
                }
            } catch (error) {
                showValidationStatus(false, 'Formatting failed', error.message);
                showAlert(error.message, 'error');
            }
        }

        // Minify data
        async function minifyData() {
            const data = inputEditor.value.trim();
            const type = inputFormat.value;

            if (!data) {
                showAlert('Please enter some data to minify', 'error');
                return;
            }

            if (type !== 'json') {
                showAlert('Minify is only available for JSON', 'warning');
                return;
            }

            try {
                const response = await apiRequest('/api/v1/tools/validate-data', {
                    method: 'POST',
                    body: JSON.stringify({ data, type, action: 'format' })
                });

                const result = response.data;

                if (result.is_valid && result.minified) {
                    outputEditor.value = result.minified;
                    updateLineNumbers(outputEditor, document.getElementById('output-line-numbers'));
                    updateStats(outputEditor, 'output');

                    const savings = ((1 - result.size_minified / result.size_original) * 100).toFixed(1);
                    showValidationStatus(true, `Minified successfully (${savings}% size reduction)`);
                    showAlert('Data minified successfully!', 'success');
                } else {
                    throw new Error(result.error || 'Invalid JSON format');
                }
            } catch (error) {
                showValidationStatus(false, 'Minification failed', error.message);
                showAlert(error.message, 'error');
            }
        }

        // Convert data
        async function convertData() {
            const data = inputEditor.value.trim();
            const type = inputFormat.value;

            if (!data) {
                showAlert('Please enter some data to convert', 'error');
                return;
            }

            try {
                const response = await apiRequest('/api/v1/tools/validate-data', {
                    method: 'POST',
                    body: JSON.stringify({ data, type, action: 'convert' })
                });

                const result = response.data;

                if (result.is_valid) {
                    const converted = type === 'json' ? result.converted_to_yaml : result.converted_to_json;

                    if (converted) {
                        outputEditor.value = converted;
                        updateLineNumbers(outputEditor, document.getElementById('output-line-numbers'));
                        updateStats(outputEditor, 'output');

                        const targetType = type === 'json' ? 'YAML' : 'JSON';
                        showValidationStatus(true, `Converted to ${targetType}`);
                        showAlert(`Successfully converted to ${targetType}!`, 'success');
                    } else {
                        throw new Error('Conversion result is empty');
                    }
                } else {
                    throw new Error(result.error || 'Invalid data format');
                }
            } catch (error) {
                showValidationStatus(false, 'Conversion failed', error.message);
                showAlert(error.message, 'error');
            }
        }

        // Load example
        function loadExample(exampleKey) {
            const exampleData = examples[exampleKey];
            if (exampleData) {
                inputEditor.value = exampleData;
                updateLineNumbers(inputEditor, document.getElementById('input-line-numbers'));
                updateStats(inputEditor, 'input');

                // Set appropriate input format
                if (exampleKey.startsWith('json')) {
                    inputFormat.value = 'json';
                } else {
                    inputFormat.value = 'yaml';
                }

                hideValidationStatus();
                outputEditor.value = '';
                updateLineNumbers(outputEditor, document.getElementById('output-line-numbers'));
                updateStats(outputEditor, 'output');

                showAlert('Example loaded!', 'success');
            }
        }

        // Clear input
        function clearInput() {
            inputEditor.value = '';
            updateLineNumbers(inputEditor, document.getElementById('input-line-numbers'));
            updateStats(inputEditor, 'input');
            hideValidationStatus();
        }

        // Clear output
        function clearOutput() {
            outputEditor.value = '';
            updateLineNumbers(outputEditor, document.getElementById('output-line-numbers'));
            updateStats(outputEditor, 'output');
        }

        // Copy input
        async function copyInput() {
            try {
                await navigator.clipboard.writeText(inputEditor.value);
                showAlert('Input copied to clipboard!', 'success');
            } catch (error) {
                showAlert('Failed to copy to clipboard', 'error');
            }
        }

        // Copy output
        async function copyOutput() {
            if (!outputEditor.value) {
                showAlert('Output is empty', 'warning');
                return;
            }

            try {
                await navigator.clipboard.writeText(outputEditor.value);
                showAlert('Output copied to clipboard!', 'success');
            } catch (error) {
                showAlert('Failed to copy to clipboard', 'error');
            }
        }

        // Initialize
        updateLineNumbers(inputEditor, document.getElementById('input-line-numbers'));
        updateLineNumbers(outputEditor, document.getElementById('output-line-numbers'));
        updateStats(inputEditor, 'input');
        updateStats(outputEditor, 'output');

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + Enter to validate
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                validateData();
            }
            // Ctrl/Cmd + Shift + F to format
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'F') {
                e.preventDefault();
                formatData();
            }
            // Ctrl/Cmd + Shift + M to minify
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'M') {
                e.preventDefault();
                minifyData();
            }
            // Ctrl/Cmd + Shift + C to convert
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'C') {
                e.preventDefault();
                convertData();
            }
        });
    </script>
</body>
</html>
