<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;

class DeveloperToolsController
{
    /**
     * Test regex pattern against text
     */
    public function regexTest(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $pattern = $input['pattern'] ?? '';
        $text = $input['text'] ?? '';
        $flags = $input['flags'] ?? 'g';

        if (empty($pattern)) {
            Response::error('Regex pattern is required', 400);
            return;
        }

        try {
            // Convert flags to PHP format
            $phpFlags = '';
            if (str_contains($flags, 'i')) $phpFlags .= 'i';
            if (str_contains($flags, 'm')) $phpFlags .= 'm';
            if (str_contains($flags, 's')) $phpFlags .= 's';

            // Build full pattern
            $fullPattern = '/' . str_replace('/', '\/', $pattern) . '/' . $phpFlags;

            // Test pattern
            $matches = [];
            $matchCount = @preg_match_all($fullPattern, $text, $matches, PREG_OFFSET_CAPTURE);

            if ($matchCount === false) {
                throw new \Exception(error_get_last()['message'] ?? 'Invalid regex pattern');
            }

            $result = [
                'is_valid' => true,
                'match_count' => $matchCount,
                'matches' => [],
                'pattern' => $fullPattern
            ];

            // Format matches
            if ($matchCount > 0) {
                foreach ($matches[0] as $index => $match) {
                    $result['matches'][] = [
                        'match' => $match[0],
                        'position' => $match[1],
                        'index' => $index
                    ];
                }
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success($result);

        } catch (\Exception $e) {
            Response::error('Regex error: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Validate and format JSON/YAML
     */
    public function validateData(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $data = $input['data'] ?? '';
        $type = $input['type'] ?? 'json'; // 'json' or 'yaml'
        $action = $input['action'] ?? 'validate'; // 'validate', 'format', 'convert'

        if (empty($data)) {
            Response::error('Data is required', 400);
            return;
        }

        try {
            $result = [
                'is_valid' => false,
                'type' => $type
            ];

            if ($type === 'json') {
                $decoded = json_decode($data, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON: ' . json_last_error_msg());
                }

                $result['is_valid'] = true;
                $result['formatted'] = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $result['minified'] = json_encode($decoded);
                $result['size_original'] = strlen($data);
                $result['size_formatted'] = strlen($result['formatted']);
                $result['size_minified'] = strlen($result['minified']);

                // Convert to YAML if requested
                if ($action === 'convert') {
                    $result['converted_to_yaml'] = $this->arrayToYaml($decoded);
                }

            } elseif ($type === 'yaml') {
                // Basic YAML parsing (simple implementation)
                $lines = explode("\n", $data);
                $parsed = $this->parseYaml($lines);

                $result['is_valid'] = true;
                $result['parsed'] = $parsed;
                $result['formatted'] = $this->arrayToYaml($parsed);

                // Convert to JSON if requested
                if ($action === 'convert') {
                    $result['converted_to_json'] = json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success($result);

        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * Scan text for secrets (API keys, tokens, passwords)
     */
    public function scanSecrets(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $text = $input['text'] ?? '';

        if (empty($text)) {
            Response::error('Text to scan is required', 400);
            return;
        }

        $secrets = [];

        // Patterns for common secrets
        $patterns = [
            'AWS Access Key' => '/AKIA[0-9A-Z]{16}/',
            'AWS Secret Key' => '/aws(.{0,20})?[\'"][0-9a-zA-Z\/+]{40}[\'"]/',
            'GitHub Token' => '/ghp_[0-9a-zA-Z]{36}/',
            'GitHub OAuth' => '/gho_[0-9a-zA-Z]{36}/',
            'Slack Token' => '/xox[baprs]-[0-9a-zA-Z-]{10,48}/',
            'Stripe API Key' => '/sk_live_[0-9a-zA-Z]{24}/',
            'Stripe Publishable' => '/pk_live_[0-9a-zA-Z]{24}/',
            'Google API Key' => '/AIza[0-9A-Za-z\\-_]{35}/',
            'Google OAuth' => '/[0-9]+-[0-9A-Za-z_]{32}\.apps\.googleusercontent\.com/',
            'Heroku API Key' => '/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/',
            'MailChimp API Key' => '/[0-9a-f]{32}-us[0-9]{1,2}/',
            'Mailgun API Key' => '/key-[0-9a-zA-Z]{32}/',
            'PayPal Braintree' => '/access_token\$production\$[0-9a-z]{16}\$[0-9a-f]{32}/',
            'Picatic API Key' => '/sk_live_[0-9a-z]{32}/',
            'SendGrid API Key' => '/SG\.[0-9A-Za-z\-_]{22}\.[0-9A-Za-z\-_]{43}/',
            'Twilio API Key' => '/SK[0-9a-fA-F]{32}/',
            'Twitter Access Token' => '/[1-9][0-9]+-[0-9a-zA-Z]{40}/',
            'Private SSH Key' => '/-----BEGIN (RSA|OPENSSH|DSA|EC) PRIVATE KEY-----/',
            'Generic API Key' => '/api[_-]?key[\'"]?\s*[:=]\s*[\'"]?[0-9a-zA-Z]{16,}/',
            'Generic Secret' => '/secret[\'"]?\s*[:=]\s*[\'"]?[0-9a-zA-Z]{16,}/',
            'Password in Code' => '/password[\'"]?\s*[:=]\s*[\'"][^\'"]{8,}/',
            'JWT Token' => '/eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}/',
            'Basic Auth' => '/Authorization:\s*Basic\s+[A-Za-z0-9+\/]+=*/',
            'Bearer Token' => '/Authorization:\s*Bearer\s+[A-Za-z0-9\-._~+\/]+=*/'
        ];

        foreach ($patterns as $name => $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $secrets[] = [
                        'type' => $name,
                        'value' => $this->maskSecret($match[0]),
                        'line' => substr_count(substr($text, 0, $match[1]), "\n") + 1,
                        'position' => $match[1],
                        'severity' => $this->getSeverity($name)
                    ];
                }
            }
        }

        if (!$auth['authenticated']) {
            RateLimit::incrementAnonymousScan($auth['ip_address']);
        }

        Response::success([
            'secrets_found' => count($secrets),
            'secrets' => $secrets,
            'risk_level' => count($secrets) === 0 ? 'low' : (count($secrets) < 5 ? 'medium' : 'high')
        ]);
    }

    /**
     * Generate hashes
     */
    public function generateHash(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $text = $input['text'] ?? '';
        $algorithms = $input['algorithms'] ?? ['md5', 'sha1', 'sha256', 'sha512'];

        if (empty($text)) {
            Response::error('Text is required', 400);
            return;
        }

        $hashes = [];

        foreach ($algorithms as $algo) {
            $algo = strtolower($algo);
            if ($algo === 'bcrypt') {
                $hashes[$algo] = password_hash($text, PASSWORD_BCRYPT);
            } elseif (in_array($algo, hash_algos())) {
                $hashes[$algo] = hash($algo, $text);
            }
        }

        if (!$auth['authenticated']) {
            RateLimit::incrementAnonymousScan($auth['ip_address']);
        }

        Response::success([
            'hashes' => $hashes,
            'input_length' => strlen($text)
        ]);
    }

    /**
     * URL encode/decode
     */
    public function urlEncode(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $text = trim($input['text'] ?? '');
        $operation = $input['operation'] ?? 'encode';

        if ($text === '') {
            Response::error('Text is required', 400);
            return;
        }

        $result = '';
        $inputLength = strlen($text);

        if ($operation === 'encode') {
            $result = urlencode($text);
        } else {
            $result = urldecode($text);
        }

        if (!$auth['authenticated']) {
            RateLimit::incrementAnonymousScan($auth['ip_address']);
        }

        Response::success([
            'result' => $result,
            'details' => [
                'operation' => $operation,
                'input_length' => $inputLength,
                'output_length' => strlen($result)
            ]
        ]);
    }

    /**
     * Simple YAML parser
     */
    private function parseYaml(array $lines): array
    {
        $result = [];
        $currentIndent = 0;

        foreach ($lines as $line) {
            $line = rtrim($line);
            if (empty($line) || $line[0] === '#') continue;

            preg_match('/^(\s*)(.+)$/', $line, $matches);
            $indent = strlen($matches[1] ?? '');
            $content = $matches[2] ?? '';

            if (str_contains($content, ':')) {
                [$key, $value] = array_map('trim', explode(':', $content, 2));
                $result[$key] = $value ?: [];
            }
        }

        return $result;
    }

    /**
     * Convert array to YAML
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $spaces = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $yaml .= $spaces . $key . ":\n";
                $yaml .= $this->arrayToYaml($value, $indent + 1);
            } else {
                $yaml .= $spaces . $key . ': ' . $value . "\n";
            }
        }

        return $yaml;
    }

    /**
     * Mask secret for display
     */
    private function maskSecret(string $secret): string
    {
        $len = strlen($secret);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        return substr($secret, 0, 4) . str_repeat('*', $len - 8) . substr($secret, -4);
    }

    /**
     * Get severity for secret type
     */
    private function getSeverity(string $type): string
    {
        $critical = ['AWS Secret Key', 'Private SSH Key', 'Password in Code'];
        $high = ['AWS Access Key', 'GitHub Token', 'Stripe API Key', 'Google API Key'];

        if (in_array($type, $critical)) return 'critical';
        if (in_array($type, $high)) return 'high';
        return 'medium';
    }

    /**
     * Validate PGP keys and signatures
     */
    public function validatePGP(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $publicKey = $input['public_key'] ?? '';
        $operation = $input['operation'] ?? 'validate_key';

        if (empty($publicKey)) {
            Response::error('PGP public key is required', 400);
            return;
        }

        // Basic PGP format validation
        $isValidFormat = preg_match('/-----BEGIN PGP PUBLIC KEY BLOCK-----.*-----END PGP PUBLIC KEY BLOCK-----/s', $publicKey);

        if (!$isValidFormat) {
            Response::error('Invalid PGP key format', 400, [
                'is_valid' => false,
                'error' => 'Key must be in PGP armor format'
            ]);
            return;
        }

        // For now, return basic validation
        // Full PGP validation would require GnuPG extension or external library
        $result = [
            'is_valid' => true,
            'format' => 'PGP Armor',
            'message' => 'Basic format validation passed. Full cryptographic validation requires GPG extension.'
        ];

        if (!$auth['authenticated']) {
            RateLimit::incrementAnonymousScan($auth['ip_address']);
        }

        Response::success($result);
    }

    /**
     * Validate and compare hashes
     */
    public function validateHash(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $hash = $input['hash'] ?? ($input['hash1'] ?? '');
        $hash2 = $input['hash2'] ?? '';
        $operation = $input['operation'] ?? 'identify';
        $hashType = $input['hash_type'] ?? '';
        $originalText = $input['original_text'] ?? '';

        if (empty($hash)) {
            Response::error('Hash value is required', 400);
            return;
        }

        // Identify hash type based on length
        $hashLength = strlen($hash);
        $possibleTypes = [];

        switch ($hashLength) {
            case 32:
                $possibleTypes[] = 'MD5';
                break;
            case 40:
                $possibleTypes[] = 'SHA-1';
                break;
            case 64:
                $possibleTypes[] = 'SHA-256';
                $possibleTypes[] = 'SHA3-256';
                $possibleTypes[] = 'BLAKE2s';
                break;
            case 96:
                $possibleTypes[] = 'SHA-384';
                break;
            case 128:
                $possibleTypes[] = 'SHA-512';
                $possibleTypes[] = 'SHA3-512';
                $possibleTypes[] = 'BLAKE2b';
                break;
        }

        $result = [];

        if ($operation === 'identify') {
            $result = [
                'hash' => $hash,
                'length' => $hashLength,
                'bits' => $hashLength * 4,
                'possible_types' => $possibleTypes
            ];
        } elseif ($operation === 'compare' && !empty($hash2)) {
            $result = [
                'hash1' => $hash,
                'hash2' => $hash2,
                'match' => $hash === $hash2,
                'case_sensitive' => $hash === $hash2,
                'case_insensitive' => strtolower($hash) === strtolower($hash2)
            ];
        } elseif ($operation === 'validate') {
            $isValid = preg_match('/^[a-f0-9]+$/i', $hash);
            $result = [
                'is_valid' => $isValid,
                'hash_type' => $hashType ?: ($possibleTypes[0] ?? 'unknown'),
                'expected_length' => $hashLength,
                'actual_length' => $hashLength,
                'format' => $isValid ? 'hexadecimal' : 'invalid'
            ];

            // If original text provided, compute and compare
            if (!empty($originalText) && !empty($hashType)) {
                $algo = strtolower(str_replace('-', '', $hashType));
                if (in_array($algo, hash_algos())) {
                    $computed = hash($algo, $originalText);
                    $result['computed_hash'] = $computed;
                    $result['matches'] = strtolower($computed) === strtolower($hash);
                }
            }
        }

        if (!$auth['authenticated']) {
            RateLimit::incrementAnonymousScan($auth['ip_address']);
        }

        Response::success($result);
    }

    /**
     * Base64 encode/decode
     */
    public function base64Encode(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $operation = $input['operation'] ?? 'encode';
        $inputData = $input['input'] ?? '';
        $urlSafe = $input['url_safe'] ?? false;
        $lineBreak = $input['line_break'] ?? 'none';

        if ($inputData === '') {
            Response::error('Input is required', 400);
            return;
        }

        try {
            $result = [];

            if ($operation === 'encode') {
                // Encode text to Base64
                $encoded = base64_encode($inputData);

                // URL-safe encoding
                if ($urlSafe) {
                    $encoded = strtr($encoded, '+/', '-_');
                    $encoded = rtrim($encoded, '=');
                }

                // Add line breaks
                if ($lineBreak !== 'none') {
                    $chunkSize = (int)$lineBreak;
                    $encoded = chunk_split($encoded, $chunkSize, "\n");
                    $encoded = rtrim($encoded);
                }

                $result = [
                    'encoded' => $encoded,
                    'statistics' => [
                        'input_size' => strlen($inputData),
                        'output_size' => strlen($encoded),
                        'size_increase' => $this->calculatePercentageIncrease(strlen($inputData), strlen($encoded)),
                        'encoding_type' => $urlSafe ? 'URL-Safe' : 'Standard'
                    ]
                ];

            } elseif ($operation === 'decode') {
                // Decode Base64 to text
                $toDecode = $inputData;

                // URL-safe decoding
                if ($urlSafe) {
                    $toDecode = strtr($toDecode, '-_', '+/');
                    // Add padding if needed
                    $remainder = strlen($toDecode) % 4;
                    if ($remainder) {
                        $toDecode .= str_repeat('=', 4 - $remainder);
                    }
                }

                // Remove any whitespace
                $toDecode = preg_replace('/\s+/', '', $toDecode);

                // Validate Base64
                $isValid = $this->isValidBase64($toDecode);

                if (!$isValid) {
                    Response::error('Invalid Base64 string', 400);
                    return;
                }

                $decoded = base64_decode($toDecode, true);

                if ($decoded === false) {
                    Response::error('Failed to decode Base64 string', 400);
                    return;
                }

                // Check if decoded data is an image
                $isImage = false;
                $mimeType = null;
                $dataUrl = null;

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mimeType = finfo_buffer($finfo, $decoded);
                    finfo_close($finfo);

                    if (strpos($mimeType, 'image/') === 0) {
                        $isImage = true;
                        $dataUrl = 'data:' . $mimeType . ';base64,' . base64_encode($decoded);
                    }
                }

                $result = [
                    'decoded' => $decoded,
                    'is_image' => $isImage,
                    'mime_type' => $mimeType,
                    'data_url' => $dataUrl,
                    'is_valid' => true,
                    'statistics' => [
                        'input_size' => strlen($toDecode),
                        'output_size' => strlen($decoded),
                        'size_decrease' => $this->calculatePercentageDecrease(strlen($toDecode), strlen($decoded))
                    ]
                ];

            } elseif ($operation === 'encode_file') {
                // Encode file (already in base64 from FileReader)
                $filename = $input['filename'] ?? 'file';
                $mimeType = $input['mime_type'] ?? 'application/octet-stream';

                // The input is already base64 from the frontend
                $encoded = $inputData;

                // Add line breaks
                if ($lineBreak !== 'none') {
                    $chunkSize = (int)$lineBreak;
                    $encoded = chunk_split($encoded, $chunkSize, "\n");
                    $encoded = rtrim($encoded);
                }

                $dataUrl = 'data:' . $mimeType . ';base64,' . $encoded;

                $result = [
                    'encoded' => $encoded,
                    'data_url' => $dataUrl,
                    'filename' => $filename,
                    'mime_type' => $mimeType,
                    'statistics' => [
                        'input_size' => strlen(base64_decode($inputData)),
                        'output_size' => strlen($encoded),
                        'size_increase' => $this->calculatePercentageIncrease(
                            strlen(base64_decode($inputData)),
                            strlen($encoded)
                        )
                    ]
                ];
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success($result);

        } catch (\Exception $e) {
            Response::error('Base64 processing error: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Validate Base64 string
     */
    private function isValidBase64(string $string): bool
    {
        // Check if string contains only valid Base64 characters
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string)) {
            return false;
        }

        // Try to decode
        $decoded = base64_decode($string, true);
        if ($decoded === false) {
            return false;
        }

        // Re-encode and compare
        if (base64_encode($decoded) !== preg_replace('/\s+/', '', $string)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate percentage increase
     */
    private function calculatePercentageIncrease(int $original, int $new): string
    {
        if ($original === 0) {
            return '0%';
        }
        $increase = (($new - $original) / $original) * 100;
        return round($increase, 2) . '%';
    }

    /**
     * Calculate percentage decrease
     */
    private function calculatePercentageDecrease(int $original, int $new): string
    {
        if ($original === 0) {
            return '0%';
        }
        $decrease = (($original - $new) / $original) * 100;
        return round($decrease, 2) . '%';
    }
}
