<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Encoder/Decoder - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
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
        <div class="container" style="max-width: 900px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">URL Encoder/Decoder</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Encode and decode URLs with support for multiple encoding formats
            </p>

            <div id="alert-container"></div>

            <!-- Input Section -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Operation</h2>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                    <button class="btn btn-primary operation-btn" data-operation="encode" onclick="selectOperation('encode')" style="width: 100%;">
                        Encode
                    </button>
                    <button class="btn btn-secondary operation-btn" data-operation="decode" onclick="selectOperation('decode')" style="width: 100%;">
                        Decode
                    </button>
                </div>

                <div class="form-group">
                    <label for="input-text">Input Text</label>
                    <textarea id="input-text" rows="6" placeholder="Enter text or URL to encode/decode..." style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;"></textarea>
                </div>

                <button class="btn btn-primary" id="process-button" onclick="processURL()" style="width: 100%;">
                    Encode
                </button>
            </div>

            <!-- Results Section -->
            <div class="feature-card" id="results-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Results</h2>
                <div id="results-content"></div>
            </div>

            <!-- Examples Section -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Examples</h2>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">URL Encoding</h3>
                <p style="margin-bottom: 0.5rem;"><strong>Original:</strong></p>
                <code style="display: block; background: var(--darker-bg); padding: 0.5rem; border-radius: 4px; margin-bottom: 0.5rem;">https://example.com/search?q=hello world&lang=en</code>
                <p style="margin-bottom: 0.5rem;"><strong>Encoded:</strong></p>
                <code style="display: block; background: var(--darker-bg); padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem;">https%3A%2F%2Fexample.com%2Fsearch%3Fq%3Dhello%20world%26lang%3Den</code>

                <h3 style="color: var(--accent-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Special Characters</h3>
                <p style="margin-bottom: 0.5rem;"><strong>Original:</strong></p>
                <code style="display: block; background: var(--darker-bg); padding: 0.5rem; border-radius: 4px; margin-bottom: 0.5rem;">user@email.com?test=value&foo=bar</code>
                <p style="margin-bottom: 0.5rem;"><strong>Encoded:</strong></p>
                <code style="display: block; background: var(--darker-bg); padding: 0.5rem; border-radius: 4px;">user%40email.com%3Ftest%3Dvalue%26foo%3Dbar</code>
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

            // Update process button text
            document.getElementById('process-button').textContent = operation.charAt(0).toUpperCase() + operation.slice(1);
        }

        async function processURL() {
            const inputText = document.getElementById('input-text').value;

            if (!inputText.trim()) {
                showAlert('Please enter text to ' + selectedOperation, 'error');
                return;
            }

            const resultsSection = document.getElementById('results-section');
            const resultsContent = document.getElementById('results-content');

            resultsSection.style.display = 'block';
            resultsContent.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="spinner" style="margin: 0 auto;"></div></div>';

            try {
                const data = await apiRequest('/api/v1/tools/url-encoder', {
                    method: 'POST',
                    body: JSON.stringify({
                        text: inputText,
                        operation: selectedOperation
                    })
                });

                displayResults(data.data);
            } catch (error) {
                resultsContent.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        }

        function displayResults(result) {
            let html = `
                <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Result</h3>
                    <textarea readonly rows="6" style="width: 100%; padding: 0.75rem; background: var(--dark-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;">${result.result}</textarea>
                    <button class="btn btn-primary" onclick="copyToClipboard('${result.result.replace(/'/g, "\\'")}', this)" style="margin-top: 1rem; width: 100%;">
                        Copy to Clipboard
                    </button>
                </div>
            `;

            if (result.details) {
                html += `
                    <div style="padding: 1.5rem; background: var(--darker-bg); border-radius: 8px;">
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Details</h3>
                        <p><strong>Operation:</strong> ${result.details.operation}</p>
                        <p><strong>Input Length:</strong> ${result.details.input_length} characters</p>
                        <p><strong>Output Length:</strong> ${result.details.output_length} characters</p>
                    </div>
                `;
            }

            document.getElementById('results-content').innerHTML = html;
        }

        // Enhanced copy function with better UX
        function copyToClipboard(text, button) {
            const tempTextarea = document.createElement('textarea');
            tempTextarea.value = text;
            document.body.appendChild(tempTextarea);
            tempTextarea.select();
            document.execCommand('copy');
            document.body.removeChild(tempTextarea);

            const originalText = button.textContent;
            button.textContent = 'Copied!';
            button.classList.add('btn-success');

            setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove('btn-success');
            }, 2000);
        }
    </script>
</body>
</html>
