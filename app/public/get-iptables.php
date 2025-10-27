<?php
// © After Dark Systems
// Public API endpoint for retrieving firewall configurations via API key
// Usage: GET /get-iptables.php?key=$apiKey&account=$accountID&device=$deviceName&version=$version&output=text|json

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'VeriBits\\';
    $base_dir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use VeriBits\Utils\Database;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Config;
use VeriBits\Utils\AuditLog;

// Initialize configuration
Config::load();

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    $db = Database::getInstance();

    // Get API key from header ONLY (not URL parameters for security)
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;

    // Reject if API key passed via query parameter (security risk - logged in server logs, browser history, proxy logs)
    if (isset($_GET['key']) || isset($_GET['apikey'])) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Security: API keys must be sent via X-API-Key header, NOT URL parameters',
            'usage' => 'curl -H "X-API-Key: your_key_here" "https://www.veribits.com/get-iptables.php?account=...&device=...&output=text"',
            'reason' => 'API keys in URLs are logged in server logs, browser history, and proxy logs'
        ]);
        exit;
    }

    // Get other parameters
    $accountId = $_GET['account'] ?? $_GET['account_id'] ?? null;
    $deviceName = $_GET['device'] ?? $_GET['device_name'] ?? null;
    $version = isset($_GET['version']) ? (int)$_GET['version'] : null;
    $outputFormat = $_GET['output'] ?? 'text';

    // Validate API key
    if (!$apiKey) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'API key required',
            'usage' => 'Send API key via X-API-Key header: curl -H "X-API-Key: your_key_here" ...'
        ]);
        exit;
    }

    // Verify API key and get user
    $query = "SELECT k.user_id, k.is_active, u.id as account_id, u.email, u.tier
              FROM api_keys k
              JOIN users u ON k.user_id = u.id
              WHERE k.key_hash = ? AND k.is_active = true";

    $keyHash = hash('sha256', $apiKey);
    $result = $db->query($query, [$keyHash]);

    if (empty($result)) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid or inactive API key']);
        exit;
    }

    $user = $result[0];
    $userId = $user['user_id'];

    // Verify account ID matches if provided
    if ($accountId && $user['account_id'] !== $accountId) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Account ID mismatch']);
        exit;
    }

    // Build query to get firewall configuration
    $conditions = ['user_id = ?'];
    $params = [$userId];

    if ($deviceName) {
        $conditions[] = 'device_name = ?';
        $params[] = $deviceName;
    }

    if ($version !== null) {
        $conditions[] = 'version = ?';
        $params[] = $version;
    }

    $whereClause = implode(' AND ', $conditions);

    // Get the configuration
    if ($version !== null || $deviceName) {
        // Get specific version or latest for specific device
        $query = "SELECT id, device_name, config_type, config_data, version, description, created_at
                  FROM firewall_configs
                  WHERE $whereClause
                  ORDER BY version DESC
                  LIMIT 1";
    } else {
        // Get latest configuration across all devices
        $query = "SELECT id, device_name, config_type, config_data, version, description, created_at
                  FROM firewall_configs
                  WHERE user_id = ?
                  ORDER BY created_at DESC
                  LIMIT 1";
        $params = [$userId];
    }

    $result = $db->query($query, $params);

    if (empty($result)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'No firewall configuration found',
            'filters' => [
                'device' => $deviceName ?? 'any',
                'version' => $version ?? 'latest'
            ]
        ]);
        exit;
    }

    $config = $result[0];

    // Log the API access
    AuditLog::log(
        $userId,
        'firewall_api_access',
        'firewall_config',
        $config['id'],
        [
            'device_name' => $config['device_name'],
            'version' => $config['version'],
            'output_format' => $outputFormat,
            'api_key_used' => substr($apiKey, 0, 8) . '...'
        ],
        $_SERVER['REMOTE_ADDR']
    );

    // Return configuration in requested format
    if ($outputFormat === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $config['id'],
                'device_name' => $config['device_name'],
                'config_type' => $config['config_type'],
                'version' => (int)$config['version'],
                'description' => $config['description'],
                'created_at' => $config['created_at'],
                'config_data' => $config['config_data']
            ],
            'metadata' => [
                'account_id' => $user['account_id'],
                'account_email' => $user['email'],
                'account_tier' => $user['tier'],
                'retrieved_at' => date('Y-m-d H:i:s')
            ]
        ], JSON_PRETTY_PRINT);
    } else {
        // Return as plain text (default)
        header('Content-Type: text/plain');
        header('X-Device-Name: ' . $config['device_name']);
        header('X-Config-Type: ' . $config['config_type']);
        header('X-Config-Version: ' . $config['version']);
        header('X-Created-At: ' . $config['created_at']);

        // Add helpful comment header
        echo "# VeriBits Firewall Configuration\n";
        echo "# Device: " . $config['device_name'] . "\n";
        echo "# Type: " . $config['config_type'] . "\n";
        echo "# Version: " . $config['version'] . "\n";
        echo "# Description: " . $config['description'] . "\n";
        echo "# Retrieved: " . date('Y-m-d H:i:s') . "\n";
        echo "# Account: " . $user['email'] . " (Tier: " . $user['tier'] . ")\n";
        echo "#\n";
        echo "# Usage:\n";
        echo "#   Apply: " . $config['config_type'] . "-restore < this_file\n";
        echo "#   Or run each command individually\n";
        echo "#\n\n";

        echo $config['config_data'];
    }

} catch (\Exception $e) {
    Logger::error('Get iptables API error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'request' => $_GET
    ]);

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Internal server error',
        'message' => Config::isDevelopment() ? $e->getMessage() : 'An error occurred while retrieving the configuration'
    ]);
}
