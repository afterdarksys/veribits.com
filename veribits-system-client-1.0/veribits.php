#!/usr/bin/env php
<?php
/**
 * VeriBits CLI - Professional Security & Forensics Toolkit (PHP Edition)
 * Unified command-line interface for all VeriBits tools
 */

define('VERSION', '1.0.0');
define('DEFAULT_CONFIG', $_SERVER['HOME'] . '/.veribits/config.json');
define('DEFAULT_API_URL', 'https://veribits.com');

class VeriBitsCLI {
    private $config;
    private $apiUrl;
    private $apiKey;

    public function __construct() {
        $this->config = $this->loadConfig();
        $this->apiUrl = $this->config['api_url'] ?? DEFAULT_API_URL;
        $this->apiKey = $this->config['api_key'] ?? '';
    }

    private function loadConfig(): array {
        if (file_exists(DEFAULT_CONFIG)) {
            $json = file_get_contents(DEFAULT_CONFIG);
            return json_decode($json, true) ?? [];
        }
        return [];
    }

    private function saveConfig(array $config): void {
        $dir = dirname(DEFAULT_CONFIG);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(DEFAULT_CONFIG, json_encode($config, JSON_PRETTY_PRINT));
    }

    private function apiRequest(string $endpoint, string $method = 'GET', $data = null, $files = null): array {
        $url = $this->apiUrl . '/api/v1' . $endpoint;
        $ch = curl_init();

        $headers = [];
        if ($this->apiKey) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($files) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $files);
            } elseif ($data) {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->error("API Error: HTTP $httpCode");
        }

        return json_decode($response, true);
    }

    // ========== HASH COMMANDS ==========

    public function hashLookup(array $args): void {
        $hash = $args['hash'] ?? null;
        $type = $args['type'] ?? 'auto';
        $verbose = isset($args['verbose']);

        if (!$hash) {
            $this->error("Hash required");
        }

        echo "🔍 Looking up hash: $hash\n";

        $result = $this->apiRequest('/tools/hash-lookup', 'POST', [
            'hash' => $hash,
            'hash_type' => $type
        ]);

        if ($result['success']) {
            $data = $result['data'];
            if ($data['found']) {
                echo "\n✓ Hash Found!\n";
                echo "Hash Type: " . strtoupper($data['hash_type']) . "\n";
                echo "Plaintext: {$data['plaintext']}\n";
                echo "\nSources checked: {$data['sources_queried']}\n";
                echo "Sources found: {$data['sources_found']}\n";

                if ($verbose) {
                    echo "\nSource Details:\n";
                    foreach ($data['sources'] as $source) {
                        $status = $source['found'] ? "✓" : "✗";
                        echo "  $status {$source['source']}\n";
                    }
                }
            } else {
                echo "\n✗ Hash not found in any database\n";
                echo "Checked {$data['sources_queried']} sources\n";
            }
        }
    }

    public function hashBatch(array $args): void {
        $file = $args['file'] ?? null;
        $output = $args['output'] ?? null;

        if (!$file || !file_exists($file)) {
            $this->error("File not found: $file");
        }

        echo "📋 Loading hashes from: $file\n";
        $hashes = array_filter(array_map('trim', file($file)));
        echo "Found " . count($hashes) . " hashes to lookup\n\n";

        $result = $this->apiRequest('/tools/hash-lookup/batch', 'POST', [
            'hashes' => $hashes
        ]);

        if ($result['success']) {
            $data = $result['data'];
            echo "Total: {$data['total']}\n";
            echo "Found: {$data['found']}\n";
            echo "Not Found: {$data['not_found']}\n\n";

            foreach ($data['results'] as $r) {
                $status = $r['found'] ? "✓" : "✗";
                $plaintext = $r['found'] ? $r['plaintext'] : 'Not found';
                $hashShort = substr($r['hash'], 0, 16);
                echo "$status $hashShort... → $plaintext\n";
            }

            if ($output) {
                file_put_contents($output, json_encode($data['results'], JSON_PRETTY_PRINT));
                echo "\n💾 Results saved to: $output\n";
            }
        }
    }

    public function hashIdentify(array $args): void {
        $hash = $args['hash'] ?? null;
        if (!$hash) {
            $this->error("Hash required");
        }

        echo "🔎 Identifying hash: $hash\n";

        $result = $this->apiRequest('/tools/hash-lookup/identify', 'POST', [
            'hash' => $hash
        ]);

        if ($result['success']) {
            $data = $result['data'];
            echo "\nLength: {$data['length']} characters\n";
            echo "Most Likely: " . strtoupper($data['most_likely']) . "\n";
            echo "\nPossible Types:\n";
            foreach ($data['possible_types'] as $type) {
                echo "  • $type\n";
            }
        }
    }

    // ========== PASSWORD COMMANDS ==========

    public function passwordAnalyze(array $args): void {
        $file = $args['file'] ?? null;
        if (!$file || !file_exists($file)) {
            $this->error("File not found: $file");
        }

        echo "📊 Analyzing file: $file\n";

        $cfile = new CURLFile($file);
        $result = $this->apiRequest('/tools/password-recovery/analyze', 'POST', null, ['file' => $cfile]);

        if ($result['success']) {
            $data = $result['data'];
            echo "\nFilename: {$data['filename']}\n";
            echo "Type: " . strtoupper($data['type']) . "\n";
            echo "Size: " . $this->formatSize($data['size']) . "\n";

            if ($data['is_encrypted']) {
                echo "Status: 🔒 Encrypted\n";
                if (isset($data['encryption_type'])) {
                    echo "Encryption: {$data['encryption_type']}\n";
                }
            } else {
                echo "Status: 🔓 Not Encrypted\n";
            }
        }
    }

    public function passwordRemove(array $args): void {
        $file = $args['file'] ?? null;
        $password = $args['password'] ?? null;
        $output = $args['output'] ?? "unlocked_" . basename($file);

        if (!$file || !file_exists($file)) {
            $this->error("File not found: $file");
        }
        if (!$password) {
            $this->error("Password required");
        }

        echo "🔓 Removing password from: $file\n";

        $url = $this->apiUrl . '/api/v1/tools/password-recovery/remove';
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($this->apiKey) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $this->apiKey]);
        }

        $cfile = new CURLFile($file);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => $cfile,
            'password' => $password
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            file_put_contents($output, $response);
            echo "✓ Password removed successfully!\n";
            echo "💾 Saved to: $output\n";
        } else {
            echo "Error: Failed to remove password\n";
        }
    }

    public function passwordCrack(array $args): void {
        $file = $args['file'] ?? null;
        $wordlist = $args['wordlist'] ?? 'common';
        $maxAttempts = $args['max-attempts'] ?? 1000;

        if (!$file || !file_exists($file)) {
            $this->error("File not found: $file");
        }

        echo "🔨 Attempting to crack password: $file\n";
        echo "Wordlist: $wordlist\n";
        echo "Max attempts: $maxAttempts\n\n";
        echo "This may take several minutes...\n\n";

        $cfile = new CURLFile($file);
        $result = $this->apiRequest('/tools/password-recovery/crack', 'POST', null, [
            'file' => $cfile,
            'wordlist' => $wordlist,
            'max_attempts' => $maxAttempts,
            'method' => 'dictionary'
        ]);

        if ($result['success']) {
            $data = $result['data'];
            if ($data['found']) {
                echo "✓ Password Found!\n";
                echo "Password: {$data['password']}\n";
                echo "Attempts: {$data['attempts']}\n";
                echo "Time: {$data['time_seconds']}s\n";
            } else {
                echo "✗ Password not found\n";
                echo "Attempts: {$data['attempts']}\n";
                echo "Time: {$data['time_seconds']}s\n";
                echo "\nTry a different wordlist or increase max attempts\n";
            }
        }
    }

    // ========== DISK FORENSICS COMMANDS ==========

    public function diskAnalyze(array $args): void {
        $image = $args['image'] ?? null;
        $operations = $args['operations'] ?? 'list_files,fsstat';
        $output = $args['output'] ?? null;

        if (!$image || !file_exists($image)) {
            $this->error("Image file not found: $image");
        }

        echo "💾 Analyzing disk image: $image\n";
        echo "Uploading image (this may take a while)...\n";

        $cfile = new CURLFile($image);
        $uploadResult = $this->apiRequest('/forensics/disk/upload', 'POST', null, ['file' => $cfile]);

        if (!$uploadResult['success']) {
            $this->error("Upload failed!");
        }

        $analysisId = $uploadResult['data']['analysis_id'];
        echo "Upload complete! Analysis ID: $analysisId\n\n";

        $ops = explode(',', $operations);
        echo "Running analysis operations: " . implode(', ', $ops) . "\n";

        $result = $this->apiRequest('/forensics/disk/analyze', 'POST', [
            'analysis_id' => $analysisId,
            'operations' => $ops
        ]);

        if ($result['success']) {
            $data = $result['data']['results'];

            if (isset($data['files'])) {
                echo "\n📁 Files found: {$data['files']['total']}\n";
            }

            if (isset($data['filesystem'])) {
                echo "\n📊 File System Information:\n";
                foreach ($data['filesystem'] as $key => $value) {
                    echo "  $key: $value\n";
                }
            }

            if (isset($data['partitions'])) {
                echo "\n💽 Partitions: {$data['partitions']['count']}\n";
            }

            if ($output) {
                file_put_contents($output, json_encode($data, JSON_PRETTY_PRINT));
                echo "\n💾 Full results saved to: $output\n";
            }
        }
    }

    // ========== NETCAT COMMANDS ==========

    public function netcat(array $args): void {
        $host = $args['host'] ?? null;
        $port = $args['port'] ?? null;

        if (!$host || !$port) {
            $this->error("Host and port required");
        }

        echo "🔌 Connecting to $host:$port...\n";

        $result = $this->apiRequest('/tools/netcat', 'POST', [
            'host' => $host,
            'port' => $port,
            'protocol' => $args['protocol'] ?? 'tcp',
            'data' => $args['data'] ?? null,
            'timeout' => $args['timeout'] ?? 5,
            'wait_time' => $args['wait-time'] ?? 2,
            'verbose' => isset($args['verbose']),
            'zero_io' => isset($args['zero-io']),
            'source_port' => $args['source-port'] ?? null
        ]);

        if ($result['success']) {
            $data = $result['data'];

            echo "\nStatus: " . ($data['connected'] ? '✓ Connected' : '✗ Connection Failed') . "\n";
            echo "Host: {$data['host']}\n";
            echo "Port: {$data['port']}\n";
            echo "Protocol: " . strtoupper($data['protocol']) . "\n";

            if (isset($data['connection_time'])) {
                echo "Connection Time: {$data['connection_time']}ms\n";
            }

            if (isset($data['response'])) {
                echo "\nResponse:\n";
                echo $data['response'] . "\n";
                echo "\nReceived {$data['bytes_received']} bytes\n";
            }

            if (isset($data['banner'])) {
                echo "\nBanner: {$data['banner']}\n";
            }

            if (isset($data['service_name'])) {
                echo "\nDetected Service: {$data['service_name']}\n";
                if (isset($data['service_description'])) {
                    echo "Description: {$data['service_description']}\n";
                }
            }

            if (isset($data['error'])) {
                echo "\nError: {$data['error']}\n";
            }

            if (isset($data['verbose_output']) && isset($args['verbose'])) {
                echo "\nVerbose Output:\n";
                echo $data['verbose_output'] . "\n";
            }
        }
    }

    // ========== OSQUERY COMMANDS ==========

    public function osqueryRun(array $args): void {
        $query = $args['query'] ?? null;
        $timeout = $args['timeout'] ?? 30;
        $output = $args['output'] ?? null;

        if (!$query) {
            $this->error("Query required");
        }

        $queryShort = substr($query, 0, 50);
        echo "📊 Executing query: $queryShort...\n\n";

        $result = $this->apiRequest('/osquery/execute', 'POST', [
            'query' => $query,
            'timeout' => $timeout
        ]);

        if ($result['success']) {
            $data = $result['data'];
            echo "Rows: {$data['row_count']}\n";
            echo "Time: {$data['execution_time']}s\n\n";

            if ($data['row_count'] > 0) {
                $columns = $data['columns'];
                $rows = $data['rows'];

                // Print header
                echo implode(" | ", $columns) . "\n";
                echo str_repeat("-", count($columns) * 20) . "\n";

                // Print rows (limit to 20)
                foreach (array_slice($rows, 0, 20) as $row) {
                    $values = [];
                    foreach ($columns as $col) {
                        $values[] = $row[$col] ?? '';
                    }
                    echo implode(" | ", $values) . "\n";
                }

                if (count($rows) > 20) {
                    echo "\n... and " . (count($rows) - 20) . " more rows\n";
                }
            }

            if ($output) {
                file_put_contents($output, json_encode($data['rows'], JSON_PRETTY_PRINT));
                echo "\n💾 Results saved to: $output\n";
            }
        }
    }

    public function osqueryTables(array $args): void {
        $verbose = isset($args['verbose']);

        $result = $this->apiRequest('/osquery/tables', 'GET');

        if ($result['success']) {
            $tables = $result['data']['tables'];
            echo "Available osquery tables (" . count($tables) . "):\n\n";
            foreach ($tables as $table) {
                echo "  • {$table['name']}\n";
                if ($verbose && isset($table['description'])) {
                    echo "    {$table['description']}\n";
                }
            }
        }
    }

    // ========== CONFIG COMMANDS ==========

    public function configSet(array $args): void {
        $key = $args['key'] ?? null;
        $value = $args['value'] ?? null;

        if (!$key || !$value) {
            $this->error("Key and value required");
        }

        $config = $this->loadConfig();
        $config[$key] = $value;
        $this->saveConfig($config);
        echo "✓ Set $key = $value\n";
    }

    public function configShow(array $args): void {
        $config = $this->loadConfig();
        echo "Current configuration:\n";
        foreach ($config as $key => $value) {
            // Hide sensitive values
            if (stripos($key, 'key') !== false || stripos($key, 'password') !== false) {
                $value = '***' . substr($value, -4);
            }
            echo "  $key: $value\n";
        }
    }

    // ========== HELPER METHODS ==========

    private function formatSize(int $size): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        foreach ($units as $unit) {
            if ($size < 1024) {
                return number_format($size, 2) . " $unit";
            }
            $size /= 1024;
        }
        return number_format($size, 2) . " TB";
    }

    private function error(string $message): void {
        fwrite(STDERR, "Error: $message\n");
        exit(1);
    }
}

// ========== MAIN CLI ROUTER ==========

function main() {
    global $argv;

    if (count($argv) < 2) {
        showHelp();
        exit(0);
    }

    $command = $argv[1];
    $args = parseArgs(array_slice($argv, 2));

    $cli = new VeriBitsCLI();

    try {
        switch ($command) {
            case 'hash':
                $subcommand = $args['_'][0] ?? null;
                switch ($subcommand) {
                    case 'lookup':
                        $args['hash'] = $args['_'][1] ?? null;
                        $cli->hashLookup($args);
                        break;
                    case 'batch':
                        $args['file'] = $args['_'][1] ?? null;
                        $cli->hashBatch($args);
                        break;
                    case 'identify':
                        $args['hash'] = $args['_'][1] ?? null;
                        $cli->hashIdentify($args);
                        break;
                    default:
                        echo "Unknown hash command: $subcommand\n";
                        exit(1);
                }
                break;

            case 'password':
                $subcommand = $args['_'][0] ?? null;
                switch ($subcommand) {
                    case 'analyze':
                        $args['file'] = $args['_'][1] ?? null;
                        $cli->passwordAnalyze($args);
                        break;
                    case 'remove':
                        $args['file'] = $args['_'][1] ?? null;
                        $cli->passwordRemove($args);
                        break;
                    case 'crack':
                        $args['file'] = $args['_'][1] ?? null;
                        $cli->passwordCrack($args);
                        break;
                    default:
                        echo "Unknown password command: $subcommand\n";
                        exit(1);
                }
                break;

            case 'disk':
                $subcommand = $args['_'][0] ?? null;
                switch ($subcommand) {
                    case 'analyze':
                        $args['image'] = $args['_'][1] ?? null;
                        $cli->diskAnalyze($args);
                        break;
                    default:
                        echo "Unknown disk command: $subcommand\n";
                        exit(1);
                }
                break;

            case 'osquery':
                $subcommand = $args['_'][0] ?? null;
                switch ($subcommand) {
                    case 'run':
                        $args['query'] = $args['_'][1] ?? null;
                        $cli->osqueryRun($args);
                        break;
                    case 'tables':
                        $cli->osqueryTables($args);
                        break;
                    default:
                        echo "Unknown osquery command: $subcommand\n";
                        exit(1);
                }
                break;

            case 'netcat':
                $args['host'] = $args['_'][0] ?? null;
                $args['port'] = $args['_'][1] ?? null;
                $cli->netcat($args);
                break;

            case 'config':
                $subcommand = $args['_'][0] ?? null;
                switch ($subcommand) {
                    case 'set':
                        $args['key'] = $args['_'][1] ?? null;
                        $args['value'] = $args['_'][2] ?? null;
                        $cli->configSet($args);
                        break;
                    case 'show':
                        $cli->configShow($args);
                        break;
                    default:
                        echo "Unknown config command: $subcommand\n";
                        exit(1);
                }
                break;

            case '--version':
                echo "VeriBits CLI (PHP) v" . VERSION . "\n";
                break;

            case '--help':
            case 'help':
                showHelp();
                break;

            default:
                echo "Unknown command: $command\n";
                showHelp();
                exit(1);
        }
    } catch (Exception $e) {
        fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
        exit(1);
    }
}

function parseArgs(array $argv): array {
    $args = ['_' => []];
    for ($i = 0; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if (str_starts_with($arg, '--')) {
            $key = substr($arg, 2);
            if ($i + 1 < count($argv) && !str_starts_with($argv[$i + 1], '-')) {
                $args[$key] = $argv[++$i];
            } else {
                $args[$key] = true;
            }
        } elseif (str_starts_with($arg, '-')) {
            $key = substr($arg, 1);
            if ($i + 1 < count($argv) && !str_starts_with($argv[$i + 1], '-')) {
                $args[$key] = $argv[++$i];
            } else {
                $args[$key] = true;
            }
        } else {
            $args['_'][] = $arg;
        }
    }
    return $args;
}

function showHelp(): void {
    echo <<<HELP
VeriBits CLI (PHP Edition) v1.0.0
Professional Security & Forensics Toolkit

Usage:
  veribits.php <command> [subcommand] [options]

Commands:
  hash               Hash lookup and analysis
    lookup <hash>      Lookup hash in databases
    batch <file>       Batch lookup from file
    identify <hash>    Identify hash type

  password           Password recovery and cracking
    analyze <file>     Analyze password-protected file
    remove <file>      Remove password from file
      -p, --password   Password to remove
      -o, --output     Output file
    crack <file>       Crack password
      -w, --wordlist   Wordlist (common, numeric, alpha)
      -m, --max-attempts  Max attempts (default: 1000)

  disk               Disk forensics (TSK)
    analyze <image>    Analyze disk image
      --operations     Operations (comma-separated)
      -o, --output     Output file (JSON)

  osquery            osquery SQL interface
    run <query>        Execute SQL query
      -t, --timeout    Timeout in seconds (default: 30)
      -o, --output     Output file (JSON)
    tables             List available tables
      -v, --verbose    Show descriptions

  netcat <host> <port>  Network connection utility
    --protocol         Protocol (tcp or udp, default: tcp)
    --data             Data to send
    --timeout          Connection timeout (default: 5)
    --wait-time        Wait time for response (default: 2)
    -v, --verbose      Verbose output
    --zero-io          Zero I/O mode (scan only)
    --source-port      Source port

  config             Configuration
    set <key> <value>  Set configuration value
    show               Show current configuration

Options:
  --version          Show version
  --help             Show this help

Examples:
  veribits.php hash lookup 5f4dcc3b5aa765d61d8327deb882cf99
  veribits.php password remove file.pdf -p mypassword
  veribits.php netcat example.com 80 --data "GET / HTTP/1.1\nHost: example.com\n\n"
  veribits.php osquery run "SELECT * FROM processes LIMIT 10"

HELP;
}

main();
