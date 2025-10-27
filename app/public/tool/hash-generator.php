<?php
/**
 * Hash Generator Tool
 * Generates various cryptographic hashes from text input or file uploads
 */

// Handle file upload and hashing
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input');
        }

        $algorithm = $data['algorithm'] ?? 'sha256';
        $inputText = $data['text'] ?? '';
        $salt = $data['salt'] ?? '';
        $cost = (int)($data['cost'] ?? 10);

        // Validate algorithm
        $validAlgorithms = ['md5', 'sha1', 'sha256', 'sha512', 'bcrypt', 'argon2', 'sha384', 'sha3-256', 'sha3-512'];
        if (!in_array($algorithm, $validAlgorithms)) {
            throw new Exception('Invalid hash algorithm');
        }

        // Validate input
        if (empty($inputText)) {
            throw new Exception('Input text is required');
        }

        // Generate hash based on algorithm
        $hash = '';
        $hashLength = 0;
        $bits = 0;
        $executionTime = 0;

        $startTime = microtime(true);

        switch ($algorithm) {
            case 'md5':
                $hash = md5($inputText);
                $hashLength = 32;
                $bits = 128;
                break;

            case 'sha1':
                $hash = sha1($inputText);
                $hashLength = 40;
                $bits = 160;
                break;

            case 'sha256':
                $hash = hash('sha256', $inputText);
                $hashLength = 64;
                $bits = 256;
                break;

            case 'sha384':
                $hash = hash('sha384', $inputText);
                $hashLength = 96;
                $bits = 384;
                break;

            case 'sha512':
                $hash = hash('sha512', $inputText);
                $hashLength = 128;
                $bits = 512;
                break;

            case 'sha3-256':
                if (!in_array('sha3-256', hash_algos())) {
                    throw new Exception('SHA3-256 is not supported on this server');
                }
                $hash = hash('sha3-256', $inputText);
                $hashLength = 64;
                $bits = 256;
                break;

            case 'sha3-512':
                if (!in_array('sha3-512', hash_algos())) {
                    throw new Exception('SHA3-512 is not supported on this server');
                }
                $hash = hash('sha3-512', $inputText);
                $hashLength = 128;
                $bits = 512;
                break;

            case 'bcrypt':
                // Validate cost parameter (4-31)
                if ($cost < 4 || $cost > 31) {
                    $cost = 10;
                }

                // Add salt if provided
                $options = ['cost' => $cost];
                if (!empty($salt)) {
                    // Note: password_hash generates its own salt, this is for display purposes
                    $inputText = $salt . $inputText;
                }

                $hash = password_hash($inputText, PASSWORD_BCRYPT, $options);
                $hashLength = 60;
                $bits = 184; // Bcrypt uses 184 bits (23 bytes) for the hash
                break;

            case 'argon2':
                // Check if Argon2 is available
                if (!defined('PASSWORD_ARGON2ID') && !defined('PASSWORD_ARGON2I')) {
                    throw new Exception('Argon2 is not supported on this server (requires PHP 7.2+)');
                }

                $options = [
                    'memory_cost' => 65536, // 64 MB
                    'time_cost' => 4,
                    'threads' => 1
                ];

                // Add salt if provided
                if (!empty($salt)) {
                    $inputText = $salt . $inputText;
                }

                // Use Argon2id if available, otherwise fall back to Argon2i
                $algoType = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_ARGON2I;
                $hash = password_hash($inputText, $algoType, $options);
                $hashLength = strlen($hash);
                $bits = 256; // Argon2 produces 256-bit hashes by default
                break;

            default:
                throw new Exception('Unsupported algorithm');
        }

        $executionTime = round((microtime(true) - $startTime) * 1000, 2); // Convert to milliseconds

        // Get algorithm info
        $algorithmInfo = getAlgorithmInfo($algorithm);

        echo json_encode([
            'success' => true,
            'data' => [
                'hash' => $hash,
                'algorithm' => $algorithm,
                'length' => strlen($hash),
                'bits' => $bits,
                'execution_time' => $executionTime,
                'input_length' => strlen($inputText),
                'algorithm_info' => $algorithmInfo,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        exit;

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

function getAlgorithmInfo($algorithm) {
    $info = [
        'md5' => [
            'name' => 'MD5',
            'full_name' => 'Message Digest Algorithm 5',
            'security' => 'weak',
            'warning' => 'MD5 is cryptographically broken and should not be used for security purposes. Use for checksums only.',
            'use_cases' => ['File integrity checks', 'Non-security checksums', 'Legacy systems'],
            'speed' => 'Very Fast',
            'recommended' => false
        ],
        'sha1' => [
            'name' => 'SHA-1',
            'full_name' => 'Secure Hash Algorithm 1',
            'security' => 'weak',
            'warning' => 'SHA-1 is deprecated due to collision vulnerabilities. Not recommended for security purposes.',
            'use_cases' => ['Git commits', 'Legacy systems', 'Non-security checksums'],
            'speed' => 'Very Fast',
            'recommended' => false
        ],
        'sha256' => [
            'name' => 'SHA-256',
            'full_name' => 'Secure Hash Algorithm 256-bit',
            'security' => 'strong',
            'warning' => null,
            'use_cases' => ['Digital signatures', 'SSL certificates', 'Blockchain', 'File integrity'],
            'speed' => 'Fast',
            'recommended' => true
        ],
        'sha384' => [
            'name' => 'SHA-384',
            'full_name' => 'Secure Hash Algorithm 384-bit',
            'security' => 'strong',
            'warning' => null,
            'use_cases' => ['Digital signatures', 'High-security applications', 'File integrity'],
            'speed' => 'Fast',
            'recommended' => true
        ],
        'sha512' => [
            'name' => 'SHA-512',
            'full_name' => 'Secure Hash Algorithm 512-bit',
            'security' => 'strong',
            'warning' => null,
            'use_cases' => ['Digital signatures', 'Password hashing (with salt)', 'High-security applications'],
            'speed' => 'Fast',
            'recommended' => true
        ],
        'sha3-256' => [
            'name' => 'SHA3-256',
            'full_name' => 'Secure Hash Algorithm 3 256-bit',
            'security' => 'strong',
            'warning' => null,
            'use_cases' => ['Modern cryptographic applications', 'Digital signatures', 'High-security systems'],
            'speed' => 'Moderate',
            'recommended' => true
        ],
        'sha3-512' => [
            'name' => 'SHA3-512',
            'full_name' => 'Secure Hash Algorithm 3 512-bit',
            'security' => 'strong',
            'warning' => null,
            'use_cases' => ['Modern cryptographic applications', 'High-security systems', 'Future-proof hashing'],
            'speed' => 'Moderate',
            'recommended' => true
        ],
        'bcrypt' => [
            'name' => 'bcrypt',
            'full_name' => 'Blowfish Crypt',
            'security' => 'very strong',
            'warning' => null,
            'use_cases' => ['Password hashing', 'User authentication', 'Secure credential storage'],
            'speed' => 'Slow (by design)',
            'recommended' => true
        ],
        'argon2' => [
            'name' => 'Argon2',
            'full_name' => 'Argon2 (Password Hash Competition Winner 2015)',
            'security' => 'very strong',
            'warning' => null,
            'use_cases' => ['Password hashing', 'Key derivation', 'Modern authentication systems'],
            'speed' => 'Slow (by design)',
            'recommended' => true
        ]
    ];

    return $info[$algorithm] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hash Generator - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .hash-output {
            word-break: break-all;
            font-family: 'Courier New', monospace;
            background: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin: 1rem 0;
        }

        .warning-box {
            background: var(--darker-bg);
            border-left: 4px solid var(--warning-color);
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }

        .success-box {
            background: var(--darker-bg);
            border-left: 4px solid var(--success-color);
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }

        .info-box {
            background: var(--darker-bg);
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }

        .algorithm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .algorithm-card {
            background: var(--darker-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .algorithm-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .algorithm-card.selected {
            border-color: var(--primary-color);
            background: var(--dark-bg);
        }

        .algorithm-card.weak {
            border-left: 4px solid var(--error-color);
        }

        .algorithm-card.strong {
            border-left: 4px solid var(--success-color);
        }

        .copy-btn-wrapper {
            position: relative;
            display: inline-block;
        }

        .copy-success {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--success-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.85rem;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .copy-success.show {
            opacity: 1;
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
        <div class="container" style="max-width: 1200px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Hash Generator</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Generate cryptographic hashes using various algorithms including MD5, SHA-256, SHA-512, bcrypt, and Argon2
            </p>

            <div id="alert-container"></div>

            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Select Hash Algorithm</h2>

                <div class="algorithm-grid">
                    <div class="algorithm-card weak" data-algorithm="md5" onclick="selectAlgorithm('md5')">
                        <h3 style="margin-bottom: 0.5rem;">MD5</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">128-bit (32 chars)</p>
                        <p style="color: var(--error-color); font-size: 0.85rem; margin-top: 0.5rem;">Not secure</p>
                    </div>

                    <div class="algorithm-card weak" data-algorithm="sha1" onclick="selectAlgorithm('sha1')">
                        <h3 style="margin-bottom: 0.5rem;">SHA-1</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">160-bit (40 chars)</p>
                        <p style="color: var(--error-color); font-size: 0.85rem; margin-top: 0.5rem;">Deprecated</p>
                    </div>

                    <div class="algorithm-card strong selected" data-algorithm="sha256" onclick="selectAlgorithm('sha256')">
                        <h3 style="margin-bottom: 0.5rem;">SHA-256</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">256-bit (64 chars)</p>
                        <p style="color: var(--success-color); font-size: 0.85rem; margin-top: 0.5rem;">Recommended</p>
                    </div>

                    <div class="algorithm-card strong" data-algorithm="sha384" onclick="selectAlgorithm('sha384')">
                        <h3 style="margin-bottom: 0.5rem;">SHA-384</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">384-bit (96 chars)</p>
                        <p style="color: var(--success-color); font-size: 0.85rem; margin-top: 0.5rem;">Strong</p>
                    </div>

                    <div class="algorithm-card strong" data-algorithm="sha512" onclick="selectAlgorithm('sha512')">
                        <h3 style="margin-bottom: 0.5rem;">SHA-512</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">512-bit (128 chars)</p>
                        <p style="color: var(--success-color); font-size: 0.85rem; margin-top: 0.5rem;">Very Strong</p>
                    </div>

                    <div class="algorithm-card strong" data-algorithm="sha3-256" onclick="selectAlgorithm('sha3-256')">
                        <h3 style="margin-bottom: 0.5rem;">SHA3-256</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">256-bit (64 chars)</p>
                        <p style="color: var(--success-color); font-size: 0.85rem; margin-top: 0.5rem;">Modern</p>
                    </div>

                    <div class="algorithm-card strong" data-algorithm="sha3-512" onclick="selectAlgorithm('sha3-512')">
                        <h3 style="margin-bottom: 0.5rem;">SHA3-512</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">512-bit (128 chars)</p>
                        <p style="color: var(--success-color); font-size: 0.85rem; margin-top: 0.5rem;">Modern</p>
                    </div>

                    <div class="algorithm-card strong" data-algorithm="bcrypt" onclick="selectAlgorithm('bcrypt')">
                        <h3 style="margin-bottom: 0.5rem;">bcrypt</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">Password Hashing</p>
                        <p style="color: var(--success-color); font-size: 0.85rem; margin-top: 0.5rem;">Passwords</p>
                    </div>

                    <div class="algorithm-card strong" data-algorithm="argon2" onclick="selectAlgorithm('argon2')">
                        <h3 style="margin-bottom: 0.5rem;">Argon2</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">Modern Password Hash</p>
                        <p style="color: var(--success-color); font-size: 0.85rem; margin-top: 0.5rem;">Best for Passwords</p>
                    </div>
                </div>
            </div>

            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Input</h2>

                <div class="form-group">
                    <label for="input-text">Text to Hash *</label>
                    <textarea id="input-text" rows="6" placeholder="Enter text to generate hash..." style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace; resize: vertical;"></textarea>
                </div>

                <!-- Additional options for bcrypt and Argon2 -->
                <div id="advanced-options" style="display: none;">
                    <div class="form-group">
                        <label for="salt-input">Salt (Optional)</label>
                        <input type="text" id="salt-input" placeholder="Optional salt for additional security..." style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: monospace;">
                        <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 0.5rem;">
                            Note: bcrypt and Argon2 automatically generate salts. This is for additional custom salting.
                        </p>
                    </div>

                    <div class="form-group" id="cost-option" style="display: none;">
                        <label for="cost-input">bcrypt Cost (4-31, default: 10)</label>
                        <input type="number" id="cost-input" min="4" max="31" value="10" style="width: 100%; padding: 0.75rem; background: var(--darker-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                        <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 0.5rem;">
                            Higher cost = more secure but slower. Each increment doubles the computation time.
                        </p>
                    </div>
                </div>

                <button class="btn btn-primary" onclick="generateHash()" style="width: 100%; margin-top: 1rem;">
                    Generate Hash
                </button>
            </div>

            <!-- Results Section -->
            <div class="feature-card" id="results-section" style="display: none; margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Generated Hash</h2>
                <div id="results-content"></div>
            </div>

            <!-- Information Section -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Hash Algorithm Information</h2>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <h3 style="color: var(--accent-color); margin-bottom: 0.75rem;">General Purpose Hashing</h3>
                        <ul style="margin-left: 1.5rem; line-height: 1.8;">
                            <li><strong>SHA-256:</strong> Best for digital signatures, SSL, file integrity</li>
                            <li><strong>SHA-512:</strong> Higher security variant of SHA-2</li>
                            <li><strong>SHA3-256/512:</strong> Modern alternative to SHA-2</li>
                        </ul>

                        <h3 style="color: var(--error-color); margin-top: 1.5rem; margin-bottom: 0.75rem;">Deprecated Algorithms</h3>
                        <ul style="margin-left: 1.5rem; line-height: 1.8;">
                            <li><strong>MD5:</strong> Use only for non-security checksums</li>
                            <li><strong>SHA-1:</strong> Being phased out, avoid for new applications</li>
                        </ul>
                    </div>

                    <div>
                        <h3 style="color: var(--success-color); margin-bottom: 0.75rem;">Password Hashing</h3>
                        <ul style="margin-left: 1.5rem; line-height: 1.8;">
                            <li><strong>Argon2:</strong> Winner of Password Hashing Competition 2015</li>
                            <li><strong>bcrypt:</strong> Industry standard for password storage</li>
                        </ul>

                        <div class="info-box" style="margin-top: 1rem;">
                            <p style="color: var(--primary-color); font-weight: bold; margin-bottom: 0.5rem;">Security Best Practices</p>
                            <ul style="margin-left: 1.5rem; font-size: 0.9rem; line-height: 1.6;">
                                <li>Never use MD5 or SHA-1 for security</li>
                                <li>Always use bcrypt or Argon2 for passwords</li>
                                <li>Add salt to hashes when possible</li>
                                <li>Use SHA-256 or higher for file integrity</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <h3 style="color: var(--accent-color); margin-top: 2rem; margin-bottom: 0.75rem;">Common Use Cases</h3>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <div class="info-box">
                        <p style="font-weight: bold; margin-bottom: 0.5rem;">File Integrity Verification</p>
                        <p style="font-size: 0.9rem; color: var(--text-secondary);">
                            Use SHA-256 or SHA-512 to verify downloaded files haven't been tampered with
                        </p>
                    </div>

                    <div class="info-box">
                        <p style="font-weight: bold; margin-bottom: 0.5rem;">Password Storage</p>
                        <p style="font-size: 0.9rem; color: var(--text-secondary);">
                            Use Argon2 or bcrypt with automatic salt generation for secure password storage
                        </p>
                    </div>

                    <div class="info-box">
                        <p style="font-weight: bold; margin-bottom: 0.5rem;">Digital Signatures</p>
                        <p style="font-size: 0.9rem; color: var(--text-secondary);">
                            Use SHA-256 or SHA-512 for signing documents and verifying authenticity
                        </p>
                    </div>

                    <div class="info-box">
                        <p style="font-weight: bold; margin-bottom: 0.5rem;">Blockchain & Cryptocurrencies</p>
                        <p style="font-size: 0.9rem; color: var(--text-secondary);">
                            SHA-256 is widely used in Bitcoin and other blockchain technologies
                        </p>
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
        let selectedAlgorithm = 'sha256';

        function selectAlgorithm(algorithm) {
            selectedAlgorithm = algorithm;

            // Update card selection
            document.querySelectorAll('.algorithm-card').forEach(card => {
                if (card.dataset.algorithm === algorithm) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });

            // Show/hide advanced options for bcrypt and Argon2
            const advancedOptions = document.getElementById('advanced-options');
            const costOption = document.getElementById('cost-option');

            if (algorithm === 'bcrypt' || algorithm === 'argon2') {
                advancedOptions.style.display = 'block';
                if (algorithm === 'bcrypt') {
                    costOption.style.display = 'block';
                } else {
                    costOption.style.display = 'none';
                }
            } else {
                advancedOptions.style.display = 'none';
            }
        }

        async function generateHash() {
            const inputText = document.getElementById('input-text').value;
            const salt = document.getElementById('salt-input').value;
            const cost = document.getElementById('cost-input').value;

            if (!inputText) {
                showAlert('Please enter text to hash', 'error');
                return;
            }

            const resultsSection = document.getElementById('results-section');
            const resultsContent = document.getElementById('results-content');

            resultsSection.style.display = 'block';
            resultsContent.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="spinner" style="margin: 0 auto;"></div><p style="margin-top: 1rem; color: var(--text-secondary);">Generating hash...</p></div>';

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        algorithm: selectedAlgorithm,
                        text: inputText,
                        salt: salt,
                        cost: parseInt(cost) || 10
                    })
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error || 'Failed to generate hash');
                }

                displayResults(data.data);
            } catch (error) {
                resultsContent.innerHTML = `<div class="alert alert-error">${error.message}</div>`;
            }
        }

        function displayResults(result) {
            const info = result.algorithm_info;
            const securityClass = info.security === 'weak' ? 'error-color' : info.security === 'very strong' ? 'success-color' : 'primary-color';

            let html = '';

            // Security warning for weak algorithms
            if (info.warning) {
                html += `
                    <div class="warning-box">
                        <p style="color: var(--warning-color); font-weight: bold; margin-bottom: 0.5rem;">Security Warning</p>
                        <p style="color: var(--text-secondary);">${info.warning}</p>
                    </div>
                `;
            }

            // Hash output
            html += `
                <div style="margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <label style="font-weight: bold;">Generated Hash:</label>
                        <div class="copy-btn-wrapper">
                            <button class="btn btn-secondary" onclick="copyHash('${result.hash.replace(/'/g, "\\'")}', this)" style="padding: 0.5rem 1rem;">
                                Copy Hash
                            </button>
                            <div class="copy-success">Copied!</div>
                        </div>
                    </div>
                    <div class="hash-output">${result.hash}</div>
                </div>
            `;

            // Algorithm details
            html += `
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <p><strong>Algorithm:</strong> ${info.full_name}</p>
                        <p><strong>Hash Length:</strong> ${result.length} characters</p>
                        <p><strong>Bit Strength:</strong> ${result.bits} bits</p>
                    </div>
                    <div>
                        <p><strong>Security Level:</strong> <span style="color: var(--${securityClass}); text-transform: capitalize;">${info.security}</span></p>
                        <p><strong>Speed:</strong> ${info.speed}</p>
                        <p><strong>Execution Time:</strong> ${result.execution_time} ms</p>
                    </div>
                </div>
            `;

            // Use cases
            html += `
                <div class="info-box">
                    <p style="font-weight: bold; margin-bottom: 0.5rem;">Recommended Use Cases:</p>
                    <ul style="margin-left: 1.5rem; font-size: 0.9rem; line-height: 1.6;">
            `;

            info.use_cases.forEach(useCase => {
                html += `<li>${useCase}</li>`;
            });

            html += `
                    </ul>
                </div>
            `;

            // Additional info
            html += `
                <div style="margin-top: 1.5rem; padding: 1rem; background: var(--darker-bg); border-radius: 4px;">
                    <p style="font-size: 0.9rem; color: var(--text-secondary);">
                        <strong>Input Length:</strong> ${result.input_length} characters<br>
                        <strong>Generated:</strong> ${result.timestamp}
                    </p>
                </div>
            `;

            document.getElementById('results-content').innerHTML = html;
        }

        function copyHash(hash, button) {
            // Create temporary textarea
            const textarea = document.createElement('textarea');
            textarea.value = hash;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');

                // Show success message
                const wrapper = button.parentElement;
                const successMsg = wrapper.querySelector('.copy-success');
                successMsg.classList.add('show');

                setTimeout(() => {
                    successMsg.classList.remove('show');
                }, 2000);
            } catch (err) {
                console.error('Failed to copy:', err);
                showAlert('Failed to copy to clipboard', 'error');
            }

            document.body.removeChild(textarea);
        }

        // Initialize with SHA-256 selected
        selectAlgorithm('sha256');
    </script>
</body>
</html>
