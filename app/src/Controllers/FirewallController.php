<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\Database;
use VeriBits\Utils\Validator;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;
use VeriBits\Utils\AuditLog;

class FirewallController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Upload and parse firewall configuration file
     */
    public function upload(): void
    {
        try {
            // Check authentication
            $user = Auth::getUser();
            if (!$user) {
                Response::error('Authentication required', 401);
                return;
            }

            // Check rate limit
            $rateLimit = new RateLimit('firewall_upload', 20, 3600); // 20 uploads per hour
            if (!$rateLimit->check($user['id'])) {
                Response::error('Rate limit exceeded. Please try again later.', 429);
                return;
            }

            // Validate file upload
            if (!isset($_FILES['config_file'])) {
                Response::error('No configuration file uploaded', 400);
                return;
            }

            $file = $_FILES['config_file'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                Response::error('File upload failed', 400);
                return;
            }

            // Validate file size (max 10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                Response::error('File too large (max 10MB)', 400);
                return;
            }

            // Read file content
            $configContent = file_get_contents($file['tmp_name']);

            if ($configContent === false) {
                Response::error('Failed to read file', 500);
                return;
            }

            // Get firewall type
            $firewallType = $_POST['firewall_type'] ?? 'iptables';

            if (!in_array($firewallType, ['iptables', 'ip6tables', 'ebtables'])) {
                Response::error('Invalid firewall type', 400);
                return;
            }

            // Parse configuration
            $parsed = $this->parseFirewallConfig($configContent, $firewallType);

            // Get device name if provided
            $deviceName = $_POST['device_name'] ?? 'Unnamed Device';

            // Log the action
            AuditLog::log(
                $user['id'],
                'firewall_upload',
                'firewall_config',
                null,
                [
                    'firewall_type' => $firewallType,
                    'device_name' => $deviceName,
                    'file_size' => $file['size']
                ],
                $_SERVER['REMOTE_ADDR']
            );

            Response::success([
                'type' => $firewallType,
                'chains' => $parsed['chains'],
                'deviceName' => $deviceName,
                'rawConfig' => $configContent,
                'stats' => [
                    'total_chains' => count($parsed['chains']),
                    'total_rules' => $parsed['total_rules']
                ]
            ]);

        } catch (\Exception $e) {
            Logger::error('Firewall upload error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            Response::error('Failed to parse firewall configuration: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Save firewall configuration to database with version control
     */
    public function save(): void
    {
        try {
            // Check authentication
            $user = Auth::getUser();
            if (!$user) {
                Response::error('Authentication required', 401);
                return;
            }

            // Parse request body
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                Response::error('Invalid request body', 400);
                return;
            }

            // Validate required fields
            $required = ['config_type', 'config_data'];
            foreach ($required as $field) {
                if (!isset($input[$field])) {
                    Response::error("Missing required field: $field", 400);
                    return;
                }
            }

            // Validate firewall type
            if (!in_array($input['config_type'], ['iptables', 'ip6tables', 'ebtables'])) {
                Response::error('Invalid firewall type', 400);
                return;
            }

            $deviceName = $input['device_name'] ?? 'Unnamed Device';
            $description = $input['description'] ?? 'No description';

            // Get current version number for this device
            $query = "SELECT COALESCE(MAX(version), 0) + 1 as next_version
                      FROM firewall_configs
                      WHERE user_id = ? AND device_name = ? AND config_type = ?";

            $result = $this->db->query($query, [$user['id'], $deviceName, $input['config_type']]);
            $nextVersion = $result[0]['next_version'] ?? 1;

            // Insert new configuration version
            $query = "INSERT INTO firewall_configs
                      (user_id, device_name, config_type, config_data, version, description, created_at)
                      VALUES (?, ?, ?, ?, ?, ?, NOW())
                      RETURNING id";

            $result = $this->db->query($query, [
                $user['id'],
                $deviceName,
                $input['config_type'],
                $input['config_data'],
                $nextVersion,
                $description
            ]);

            $configId = $result[0]['id'];

            // Log the action
            AuditLog::log(
                $user['id'],
                'firewall_save',
                'firewall_config',
                $configId,
                [
                    'device_name' => $deviceName,
                    'config_type' => $input['config_type'],
                    'version' => $nextVersion,
                    'description' => $description
                ],
                $_SERVER['REMOTE_ADDR']
            );

            Response::success([
                'id' => $configId,
                'version' => $nextVersion,
                'message' => 'Configuration saved successfully'
            ]);

        } catch (\Exception $e) {
            Logger::error('Firewall save error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            Response::error('Failed to save firewall configuration: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List saved firewall configurations
     */
    public function list(): void
    {
        try {
            // Check authentication
            $user = Auth::getUser();
            if (!$user) {
                Response::error('Authentication required', 401);
                return;
            }

            // Get query parameters
            $deviceName = $_GET['device_name'] ?? null;
            $configType = $_GET['config_type'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

            // Build query
            $conditions = ['user_id = ?'];
            $params = [$user['id']];

            if ($deviceName) {
                $conditions[] = 'device_name = ?';
                $params[] = $deviceName;
            }

            if ($configType) {
                $conditions[] = 'config_type = ?';
                $params[] = $configType;
            }

            $whereClause = implode(' AND ', $conditions);

            $query = "SELECT id, device_name, config_type, version, description, created_at,
                             LENGTH(config_data) as config_size
                      FROM firewall_configs
                      WHERE $whereClause
                      ORDER BY created_at DESC
                      LIMIT ? OFFSET ?";

            $params[] = $limit;
            $params[] = $offset;

            $configs = $this->db->query($query, $params);

            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM firewall_configs WHERE $whereClause";
            $countResult = $this->db->query($countQuery, array_slice($params, 0, -2));
            $total = $countResult[0]['total'] ?? 0;

            Response::success([
                'configs' => $configs,
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset
            ]);

        } catch (\Exception $e) {
            Logger::error('Firewall list error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            Response::error('Failed to list firewall configurations: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get specific firewall configuration
     */
    public function get(): void
    {
        try {
            // Check authentication
            $user = Auth::getUser();
            if (!$user) {
                Response::error('Authentication required', 401);
                return;
            }

            $id = $_GET['id'] ?? null;

            if (!$id) {
                Response::error('Configuration ID required', 400);
                return;
            }

            // Get configuration
            $query = "SELECT * FROM firewall_configs WHERE id = ? AND user_id = ?";
            $result = $this->db->query($query, [$id, $user['id']]);

            if (empty($result)) {
                Response::error('Configuration not found', 404);
                return;
            }

            Response::success($result[0]);

        } catch (\Exception $e) {
            Logger::error('Firewall get error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            Response::error('Failed to get firewall configuration: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Compare two versions and generate diff
     */
    public function diff(): void
    {
        try {
            // Check authentication
            $user = Auth::getUser();
            if (!$user) {
                Response::error('Authentication required', 401);
                return;
            }

            $oldId = $_GET['old'] ?? null;
            $newId = $_GET['new'] ?? null;

            if (!$oldId || !$newId) {
                Response::error('Both old and new version IDs required', 400);
                return;
            }

            // Get both configurations
            $query = "SELECT id, config_data, version, device_name, created_at
                      FROM firewall_configs
                      WHERE id IN (?, ?) AND user_id = ?";

            $result = $this->db->query($query, [$oldId, $newId, $user['id']]);

            if (count($result) !== 2) {
                Response::error('One or both configurations not found', 404);
                return;
            }

            // Identify old and new
            $oldConfig = $result[0]['id'] === $oldId ? $result[0] : $result[1];
            $newConfig = $result[0]['id'] === $newId ? $result[0] : $result[1];

            // Generate diff
            $diff = $this->generateDiff(
                explode("\n", $oldConfig['config_data']),
                explode("\n", $newConfig['config_data'])
            );

            Response::success([
                'old' => [
                    'id' => $oldConfig['id'],
                    'version' => $oldConfig['version'],
                    'device_name' => $oldConfig['device_name'],
                    'created_at' => $oldConfig['created_at']
                ],
                'new' => [
                    'id' => $newConfig['id'],
                    'version' => $newConfig['version'],
                    'device_name' => $newConfig['device_name'],
                    'created_at' => $newConfig['created_at']
                ],
                'diff' => $diff
            ]);

        } catch (\Exception $e) {
            Logger::error('Firewall diff error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            Response::error('Failed to generate diff: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export configuration in various formats
     */
    public function export(): void
    {
        try {
            // Check authentication
            $user = Auth::getUser();
            if (!$user) {
                Response::error('Authentication required', 401);
                return;
            }

            $id = $_GET['id'] ?? null;
            $format = $_GET['format'] ?? 'text';

            if (!$id) {
                Response::error('Configuration ID required', 400);
                return;
            }

            // Get configuration
            $query = "SELECT * FROM firewall_configs WHERE id = ? AND user_id = ?";
            $result = $this->db->query($query, [$id, $user['id']]);

            if (empty($result)) {
                Response::error('Configuration not found', 404);
                return;
            }

            $config = $result[0];

            if ($format === 'json') {
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="firewall-config-v' . $config['version'] . '.json"');
                echo json_encode([
                    'device_name' => $config['device_name'],
                    'config_type' => $config['config_type'],
                    'version' => $config['version'],
                    'description' => $config['description'],
                    'created_at' => $config['created_at'],
                    'config_data' => $config['config_data']
                ], JSON_PRETTY_PRINT);
            } else {
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename="' . $config['config_type'] . '-v' . $config['version'] . '.rules"');
                echo $config['config_data'];
            }

        } catch (\Exception $e) {
            Logger::error('Firewall export error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            Response::error('Failed to export configuration: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Parse firewall configuration file
     */
    private function parseFirewallConfig(string $content, string $type): array
    {
        $chains = [];
        $totalRules = 0;

        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            // Parse table markers for iptables-save format
            if (preg_match('/^\*(\w+)$/', $line, $matches)) {
                $currentTable = $matches[1];
                continue;
            }

            // Parse chain policy
            if (preg_match('/^:(\w+)\s+(\w+)/', $line, $matches)) {
                $chainName = $matches[1];
                $policy = $matches[2];

                if (!isset($chains[$chainName])) {
                    $chains[$chainName] = [
                        'policy' => $policy,
                        'rules' => []
                    ];
                } else {
                    $chains[$chainName]['policy'] = $policy;
                }
                continue;
            }

            // Parse rule
            if (preg_match('/-A\s+(\w+)\s+(.+)/', $line, $matches)) {
                $chainName = $matches[1];
                $ruleSpec = $matches[2];

                if (!isset($chains[$chainName])) {
                    $chains[$chainName] = [
                        'policy' => 'ACCEPT',
                        'rules' => []
                    ];
                }

                $rule = $this->parseRule($ruleSpec);
                if ($rule) {
                    $chains[$chainName]['rules'][] = $rule;
                    $totalRules++;
                }
            }
        }

        return [
            'chains' => $chains,
            'total_rules' => $totalRules
        ];
    }

    /**
     * Parse individual rule
     */
    private function parseRule(string $ruleSpec): ?array
    {
        $rule = [
            'target' => '',
            'protocol' => '',
            'source' => '',
            'destination' => '',
            'interface' => '',
            'sport' => '',
            'dport' => '',
            'state' => [],
            'comment' => '',
            'extra' => ''
        ];

        // Extract target (-j)
        if (preg_match('/-j\s+(\w+)/', $ruleSpec, $matches)) {
            $rule['target'] = $matches[1];
        }

        // Extract protocol (-p)
        if (preg_match('/-p\s+(\w+)/', $ruleSpec, $matches)) {
            $rule['protocol'] = $matches[1];
        }

        // Extract source (-s)
        if (preg_match('/-s\s+([\d\.\/:a-fA-F]+)/', $ruleSpec, $matches)) {
            $rule['source'] = $matches[1];
        }

        // Extract destination (-d)
        if (preg_match('/-d\s+([\d\.\/:a-fA-F]+)/', $ruleSpec, $matches)) {
            $rule['destination'] = $matches[1];
        }

        // Extract input interface (-i)
        if (preg_match('/-i\s+(\S+)/', $ruleSpec, $matches)) {
            $rule['interface'] = $matches[1];
        }

        // Extract output interface (-o)
        if (preg_match('/-o\s+(\S+)/', $ruleSpec, $matches)) {
            $rule['out_interface'] = $matches[1];
        }

        // Extract source port
        if (preg_match('/--sport\s+([\d:,]+)/', $ruleSpec, $matches)) {
            $rule['sport'] = $matches[1];
        }

        // Extract destination port
        if (preg_match('/--dport\s+([\d:,]+)/', $ruleSpec, $matches)) {
            $rule['dport'] = $matches[1];
        }

        // Extract state
        if (preg_match('/--state\s+([\w,]+)/', $ruleSpec, $matches)) {
            $rule['state'] = explode(',', $matches[1]);
        }

        // Extract comment
        if (preg_match('/--comment\s+"([^"]+)"/', $ruleSpec, $matches)) {
            $rule['comment'] = $matches[1];
        } elseif (preg_match('/--comment\s+(\S+)/', $ruleSpec, $matches)) {
            $rule['comment'] = $matches[1];
        }

        return $rule;
    }

    /**
     * Generate diff between two configurations
     */
    private function generateDiff(array $old, array $new): array
    {
        $diff = [];
        $maxLines = max(count($old), count($new));

        $i = 0;
        $j = 0;

        while ($i < count($old) || $j < count($new)) {
            if ($i >= count($old)) {
                $diff[] = '+ ' . $new[$j];
                $j++;
            } elseif ($j >= count($new)) {
                $diff[] = '- ' . $old[$i];
                $i++;
            } elseif ($old[$i] === $new[$j]) {
                $diff[] = '  ' . $old[$i];
                $i++;
                $j++;
            } else {
                // Look ahead to find matching lines
                $foundMatch = false;
                for ($k = $j + 1; $k < min($j + 5, count($new)); $k++) {
                    if ($old[$i] === $new[$k]) {
                        // Lines were added
                        while ($j < $k) {
                            $diff[] = '+ ' . $new[$j];
                            $j++;
                        }
                        $foundMatch = true;
                        break;
                    }
                }

                if (!$foundMatch) {
                    for ($k = $i + 1; $k < min($i + 5, count($old)); $k++) {
                        if ($old[$k] === $new[$j]) {
                            // Lines were removed
                            while ($i < $k) {
                                $diff[] = '- ' . $old[$i];
                                $i++;
                            }
                            $foundMatch = true;
                            break;
                        }
                    }
                }

                if (!$foundMatch) {
                    $diff[] = '- ' . $old[$i];
                    $diff[] = '+ ' . $new[$j];
                    $i++;
                    $j++;
                }
            }
        }

        return $diff;
    }
}
