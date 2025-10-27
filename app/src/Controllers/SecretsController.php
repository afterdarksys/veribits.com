<?php
namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Database;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\AuditLog;

class SecretsController {

    private array $secretPatterns = [
        'aws_access_key' => [
            'pattern' => '/AKIA[0-9A-Z]{16}/',
            'name' => 'AWS Access Key ID',
            'severity' => 'critical'
        ],
        'aws_secret_key' => [
            'pattern' => '/aws(.{0,20})?[\'"][0-9a-zA-Z\/+]{40}[\'"]/',
            'name' => 'AWS Secret Access Key',
            'severity' => 'critical'
        ],
        'github_token' => [
            'pattern' => '/gh[pousr]_[A-Za-z0-9_]{36,255}/',
            'name' => 'GitHub Token',
            'severity' => 'critical'
        ],
        'slack_token' => [
            'pattern' => '/xox[baprs]-[0-9]{10,13}-[0-9]{10,13}-[a-zA-Z0-9]{24,32}/',
            'name' => 'Slack Token',
            'severity' => 'high'
        ],
        'private_key' => [
            'pattern' => '/-----BEGIN (RSA|DSA|EC|OPENSSH) PRIVATE KEY-----/',
            'name' => 'Private Key',
            'severity' => 'critical'
        ],
        'jwt' => [
            'pattern' => '/eyJ[A-Za-z0-9_-]*\.eyJ[A-Za-z0-9_-]*\.[A-Za-z0-9_.-]*/',
            'name' => 'JSON Web Token',
            'severity' => 'medium'
        ],
        'api_key' => [
            'pattern' => '/api[_-]?key[\'"]?\s*[:=]\s*[\'"]?[a-zA-Z0-9]{32,}[\'"]?/i',
            'name' => 'Generic API Key',
            'severity' => 'high'
        ],
        'password' => [
            'pattern' => '/password[\'"]?\s*[:=]\s*[\'"][^\'"\s]{8,}[\'"]/',
            'name' => 'Hardcoded Password',
            'severity' => 'high'
        ],
        'database_url' => [
            'pattern' => '/(postgres|mysql|mongodb):\/\/[^:\s]+:[^@\s]+@/',
            'name' => 'Database URL with Credentials',
            'severity' => 'critical'
        ]
    ];

    public function scan(): void {
        $clientIp = RateLimit::getClientIp();
        $user = Auth::getUserFromToken(false);

        if (!$user) {
            if (!RateLimit::check("secrets_scan:$clientIp", 5, 3600)) {
                Response::error('Rate limit exceeded', 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['content'])) {
            Response::error('content is required', 422);
            return;
        }

        $content = $input['content'];
        $sourceType = $input['source_type'] ?? 'text';
        $sourceName = $input['source_name'] ?? 'Unknown';

        $secrets = $this->scanContent($content);

        if ($user) {
            $this->storeScan($user['id'], $sourceType, $sourceName, $secrets);
            AuditLog::log($user['id'], 'secrets_scan', 'Secrets Scan Performed', [
                'source_type' => $sourceType,
                'secrets_found' => count($secrets)
            ]);
        }

        Response::success([
            'secrets_found' => count($secrets),
            'secrets' => $secrets,
            'risk_level' => $this->calculateRiskLevel($secrets),
            'recommendations' => $this->generateRecommendations($secrets)
        ]);
    }

    private function scanContent(string $content): array {
        $secrets = [];

        foreach ($this->secretPatterns as $type => $config) {
            preg_match_all($config['pattern'], $content, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as $match) {
                $value = $match[0];
                $offset = $match[1];

                // Calculate line number
                $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;

                // Get context (surrounding lines)
                $lines = explode("\n", $content);
                $contextStart = max(0, $lineNumber - 2);
                $contextEnd = min(count($lines), $lineNumber + 1);
                $context = implode("\n", array_slice($lines, $contextStart, $contextEnd - $contextStart));

                $secrets[] = [
                    'type' => $type,
                    'name' => $config['name'],
                    'severity' => $config['severity'],
                    'value' => $this->maskSecret($value),
                    'line' => $lineNumber,
                    'context' => $context,
                    'entropy' => $this->calculateEntropy($value)
                ];
            }
        }

        return $secrets;
    }

    private function maskSecret(string $secret): string {
        $len = strlen($secret);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        return substr($secret, 0, 4) . str_repeat('*', $len - 8) . substr($secret, -4);
    }

    private function calculateEntropy(string $str): float {
        $len = strlen($str);
        if ($len == 0) {
            return 0.0;
        }

        $frequencies = [];
        for ($i = 0; $i < $len; $i++) {
            $char = $str[$i];
            $frequencies[$char] = ($frequencies[$char] ?? 0) + 1;
        }

        $entropy = 0.0;
        foreach ($frequencies as $count) {
            $p = $count / $len;
            $entropy -= $p * log($p, 2);
        }

        return round($entropy, 2);
    }

    private function calculateRiskLevel(array $secrets): string {
        if (empty($secrets)) {
            return 'none';
        }

        $criticalCount = count(array_filter($secrets, fn($s) => $s['severity'] === 'critical'));
        $highCount = count(array_filter($secrets, fn($s) => $s['severity'] === 'high'));

        if ($criticalCount > 0) {
            return 'critical';
        } elseif ($highCount > 2) {
            return 'high';
        } elseif ($highCount > 0) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    private function generateRecommendations(array $secrets): array {
        $recommendations = [];

        if (empty($secrets)) {
            $recommendations[] = "No secrets detected. Continue following security best practices.";
            return $recommendations;
        }

        $recommendations[] = "Remove ALL detected secrets from your codebase immediately";
        $recommendations[] = "Rotate compromised credentials (generate new keys/tokens)";
        $recommendations[] = "Use environment variables or secret management systems (AWS Secrets Manager, HashiCorp Vault)";
        $recommendations[] = "Add secrets to .gitignore to prevent future commits";
        $recommendations[] = "Use git-secrets or similar tools in your pre-commit hooks";
        $recommendations[] = "Scan your Git history with: git log -p | grep -i 'password\\|api_key\\|secret'";

        return $recommendations;
    }

    private function storeScan(string $userId, string $sourceType, string $sourceName, array $secrets): void {
        try {
            $conn = Database::getConnection();

            $stmt = $conn->prepare("
                INSERT INTO security_scans (user_id, scan_type, status, severity, score, findings_count, completed_at)
                VALUES ($1, $2, $3, $4, $5, $6, NOW())
                RETURNING id
            ");

            $riskLevel = $this->calculateRiskLevel($secrets);
            $riskScore = count($secrets) * 20; // Simple scoring

            $stmt->execute([
                $userId,
                'secrets',
                'completed',
                $riskLevel,
                min(100, $riskScore),
                count($secrets)
            ]);

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $scanId = $result['id'];

            $stmt = $conn->prepare("
                INSERT INTO secret_scans (
                    scan_id, user_id, source_type, source_name, secrets_found, secrets
                ) VALUES ($1, $2, $3, $4, $5, $6)
            ");

            $stmt->execute([
                $scanId,
                $userId,
                $sourceType,
                $sourceName,
                count($secrets),
                json_encode($secrets)
            ]);
        } catch (\Exception $e) {
            error_log("Failed to store secrets scan: " . $e->getMessage());
        }
    }
}
