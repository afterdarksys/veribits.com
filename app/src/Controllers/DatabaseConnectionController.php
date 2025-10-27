<?php
namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Database;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\AuditLog;

class DatabaseConnectionController {

    public function audit(): void {
        $clientIp = RateLimit::getClientIp();
        $user = Auth::getUserFromToken(false);

        if (!$user) {
            if (!RateLimit::check("db_connection_audit:$clientIp", 10, 3600)) {
                Response::error('Rate limit exceeded', 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['connection_string'])) {
            Response::error('connection_string is required', 422);
            return;
        }

        $connString = trim($input['connection_string']);
        $analysis = $this->analyzeConnectionString($connString);

        if ($user) {
            $this->storeAnalysis($user['id'], $connString, $analysis);
            AuditLog::log($user['id'], 'db_connection_audit', 'Database Connection Audited', [
                'db_type' => $analysis['db_type'],
                'risk_score' => $analysis['risk_score']
            ]);
        }

        Response::success($analysis);
    }

    private function analyzeConnectionString(string $connString): array {
        $issues = [];
        $recommendations = [];
        $riskScore = 0;

        // Detect database type
        $dbType = $this->detectDatabaseType($connString);

        // Check for plaintext passwords
        $hasPlaintextPassword = false;
        if (preg_match('/(password|pwd)=([^;&\s]+)/i', $connString, $matches)) {
            $hasPlaintextPassword = true;
            $issues[] = [
                'severity' => 'critical',
                'issue' => 'Plaintext password detected in connection string',
                'recommendation' => 'Use environment variables or secret management (AWS Secrets Manager, HashiCorp Vault)'
            ];
            $riskScore += 40;
        }

        // Check for SSL/TLS
        $sslEnabled = false;
        $sslPatterns = ['ssl=true', 'sslmode=require', 'sslmode=verify', 'tls=true', 'ssl_ca='];
        foreach ($sslPatterns as $pattern) {
            if (stripos($connString, $pattern) !== false) {
                $sslEnabled = true;
                break;
            }
        }

        if (!$sslEnabled) {
            $issues[] = [
                'severity' => 'high',
                'issue' => 'SSL/TLS encryption not enabled',
                'recommendation' => 'Enable SSL/TLS to encrypt data in transit'
            ];
            $riskScore += 25;
        }

        // Check for public IP
        $publicIp = false;
        if (preg_match('/(?:@|\/\/)(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $connString, $matches)) {
            $ip = $matches[1];
            if (!$this->isPrivateIp($ip)) {
                $publicIp = true;
                $issues[] = [
                    'severity' => 'medium',
                    'issue' => "Public IP address detected: {$ip}",
                    'recommendation' => 'Use private IPs or VPC endpoints for better security'
                ];
                $riskScore += 15;
            }
        }

        // Check for default ports
        $usesDefaultPort = false;
        $defaultPorts = [
            'postgresql' => 5432,
            'mysql' => 3306,
            'mongodb' => 27017,
            'redis' => 6379,
            'mssql' => 1433
        ];

        if (isset($defaultPorts[$dbType])) {
            $defaultPort = $defaultPorts[$dbType];
            if (preg_match('/:' . $defaultPort . '(?:\/|;|$)/', $connString)) {
                $usesDefaultPort = true;
                $issues[] = [
                    'severity' => 'low',
                    'issue' => "Using default port {$defaultPort}",
                    'recommendation' => 'Consider using a non-standard port for security through obscurity'
                ];
                $riskScore += 5;
            }
        }

        // Check for default usernames
        $defaultUsers = ['root', 'admin', 'postgres', 'sa', 'mysql'];
        foreach ($defaultUsers as $defaultUser) {
            if (preg_match('/(?:user|uid)=' . $defaultUser . '(?:;|&|$)/i', $connString)) {
                $issues[] = [
                    'severity' => 'medium',
                    'issue' => "Default username '{$defaultUser}' detected",
                    'recommendation' => 'Use custom usernames instead of default accounts'
                ];
                $riskScore += 10;
                break;
            }
        }

        // Generate secure alternative
        $secureAlternative = $this->generateSecureAlternative($connString, $dbType);

        // Generate recommendations
        if ($hasPlaintextPassword) {
            $recommendations[] = "Use AWS Secrets Manager: aws secretsmanager get-secret-value --secret-id db-credentials";
            $recommendations[] = "Use environment variables: export DB_PASSWORD=\${SECRET}";
        }
        if (!$sslEnabled) {
            $recommendations[] = $this->getSSLRecommendation($dbType);
        }
        if ($publicIp) {
            $recommendations[] = "Use VPC endpoints or private IP ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)";
        }

        return [
            'db_type' => $dbType,
            'risk_score' => min(100, $riskScore),
            'risk_level' => $this->getRiskLevel($riskScore),
            'issues' => $issues,
            'has_plaintext_password' => $hasPlaintextPassword,
            'ssl_enabled' => $sslEnabled,
            'uses_default_port' => $usesDefaultPort,
            'public_ip' => $publicIp,
            'recommendations' => $recommendations,
            'secure_alternative' => $secureAlternative
        ];
    }

    private function detectDatabaseType(string $connString): string {
        if (stripos($connString, 'postgresql://') === 0 || stripos($connString, 'postgres://') === 0) {
            return 'postgresql';
        } elseif (stripos($connString, 'mysql://') === 0 || stripos($connString, 'Server=') !== false) {
            return 'mysql';
        } elseif (stripos($connString, 'mongodb://') === 0 || stripos($connString, 'mongodb+srv://') === 0) {
            return 'mongodb';
        } elseif (stripos($connString, 'redis://') === 0) {
            return 'redis';
        } elseif (stripos($connString, 'Data Source=') !== false) {
            return 'mssql';
        }
        return 'unknown';
    }

    private function isPrivateIp(string $ip): bool {
        $parts = explode('.', $ip);
        if (count($parts) !== 4) {
            return false;
        }

        // 10.0.0.0/8
        if ($parts[0] == 10) {
            return true;
        }
        // 172.16.0.0/12
        if ($parts[0] == 172 && $parts[1] >= 16 && $parts[1] <= 31) {
            return true;
        }
        // 192.168.0.0/16
        if ($parts[0] == 192 && $parts[1] == 168) {
            return true;
        }
        // 127.0.0.0/8 (localhost)
        if ($parts[0] == 127) {
            return true;
        }

        return false;
    }

    private function getSSLRecommendation(string $dbType): string {
        switch ($dbType) {
            case 'postgresql':
                return "Add sslmode=require or sslmode=verify-full to connection string";
            case 'mysql':
                return "Add useSSL=true&requireSSL=true to connection string";
            case 'mongodb':
                return "Add tls=true&tlsAllowInvalidCertificates=false to connection string";
            case 'redis':
                return "Use rediss:// (Redis with SSL) instead of redis://";
            default:
                return "Enable SSL/TLS encryption for database connections";
        }
    }

    private function generateSecureAlternative(string $connString, string $dbType): string {
        // Mask password
        $secure = preg_replace('/(password|pwd)=([^;&\s]+)/i', '$1=***MASKED***', $connString);

        // Add SSL if missing
        if (stripos($secure, 'ssl') === false) {
            switch ($dbType) {
                case 'postgresql':
                    $secure .= (strpos($secure, '?') === false ? '?' : '&') . 'sslmode=require';
                    break;
                case 'mysql':
                    $secure .= (strpos($secure, '?') === false ? '?' : '&') . 'useSSL=true';
                    break;
                case 'mongodb':
                    $secure .= (strpos($secure, '?') === false ? '?' : '&') . 'tls=true';
                    break;
            }
        }

        return $secure;
    }

    private function getRiskLevel(int $score): string {
        if ($score >= 75) return 'critical';
        if ($score >= 50) return 'high';
        if ($score >= 25) return 'medium';
        return 'low';
    }

    private function storeAnalysis(string $userId, string $connString, array $analysis): void {
        try {
            $conn = Database::getConnection();

            $stmt = $conn->prepare("
                INSERT INTO security_scans (user_id, scan_type, status, severity, score, findings_count, completed_at)
                VALUES ($1, $2, $3, $4, $5, $6, NOW())
                RETURNING id
            ");
            $stmt->execute([
                $userId,
                'db_connection',
                'completed',
                $analysis['risk_level'],
                $analysis['risk_score'],
                count($analysis['issues'])
            ]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $scanId = $result['id'];

            // Mask sensitive data before storing
            $maskedConnString = preg_replace('/(password|pwd)=([^;&\s]+)/i', '$1=***', $connString);

            $stmt = $conn->prepare("
                INSERT INTO db_connection_scans (
                    scan_id, user_id, db_type, connection_string, issues,
                    has_plaintext_password, ssl_enabled, uses_default_port, public_ip, recommendations
                ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)
            ");
            $stmt->execute([
                $scanId,
                $userId,
                $analysis['db_type'],
                $maskedConnString,
                json_encode($analysis['issues']),
                $analysis['has_plaintext_password'] ? 't' : 'f',
                $analysis['ssl_enabled'] ? 't' : 'f',
                $analysis['uses_default_port'] ? 't' : 'f',
                $analysis['public_ip'] ? 't' : 'f',
                json_encode($analysis['recommendations'])
            ]);
        } catch (\Exception $e) {
            error_log("Failed to store DB connection analysis: " . $e->getMessage());
        }
    }
}
