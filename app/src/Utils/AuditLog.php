<?php
namespace VeriBits\Utils;

class AuditLog {
    // Sensitive keys to remove from request/response data
    private static array $sensitiveKeys = [
        'password', 'password_hash', 'token', 'secret', 'api_key', 'private_key',
        'jwt', 'bearer', 'authorization', 'cookie', 'session', 'csrf_token',
        'credit_card', 'cvv', 'ssn', 'social_security'
    ];

    /**
     * Log an API operation
     */
    public static function log(array $data): void {
        try {
            // Ensure required fields are present
            $logEntry = [
                'ip_address' => $data['ip_address'] ?? RateLimit::getClientIp(),
                'user_agent' => $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
                'operation_type' => $data['operation_type'] ?? 'unknown',
                'endpoint' => $data['endpoint'] ?? ($_SERVER['REQUEST_URI'] ?? '/'),
                'http_method' => $data['http_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
                'created_at' => date('Y-m-d H:i:s')
            ];

            // Optional fields
            if (isset($data['user_id'])) {
                $logEntry['user_id'] = $data['user_id'];
            }
            if (isset($data['api_key_id'])) {
                $logEntry['api_key_id'] = $data['api_key_id'];
            }
            if (isset($data['session_id'])) {
                $logEntry['session_id'] = $data['session_id'];
            }

            // Sanitize and store request data
            if (isset($data['request_data'])) {
                $logEntry['request_data'] = json_encode(self::sanitizeData($data['request_data']));
            }

            // Store response info
            if (isset($data['response_status'])) {
                $logEntry['response_status'] = $data['response_status'];
            }
            if (isset($data['response_data'])) {
                $sanitized = self::sanitizeData($data['response_data']);
                // Truncate large responses (keep first 10KB)
                $json = json_encode($sanitized);
                if (strlen($json) > 10240) {
                    $logEntry['response_data'] = substr($json, 0, 10240);
                } else {
                    $logEntry['response_data'] = $json;
                }
            }

            // File metadata
            if (isset($data['files_metadata'])) {
                $logEntry['files_metadata'] = json_encode($data['files_metadata']);
            }

            // Performance metrics
            if (isset($data['duration_ms'])) {
                $logEntry['duration_ms'] = $data['duration_ms'];
            }

            // Error tracking
            if (isset($data['error_message'])) {
                $logEntry['error_message'] = $data['error_message'];
            }
            if (isset($data['error_code'])) {
                $logEntry['error_code'] = $data['error_code'];
            }
            if (isset($data['stack_trace'])) {
                $logEntry['stack_trace'] = $data['stack_trace'];
            }

            // Rate limiting info
            if (isset($data['rate_limit_hit'])) {
                $logEntry['rate_limit_hit'] = $data['rate_limit_hit'] ? 'true' : 'false';
            }
            if (isset($data['quota_remaining'])) {
                $logEntry['quota_remaining'] = $data['quota_remaining'];
            }

            // Insert into database
            Database::insert('audit_logs', $logEntry);

        } catch (\Exception $e) {
            // Don't throw - we don't want audit logging to break the application
            Logger::error('Failed to write audit log', [
                'error' => $e->getMessage(),
                'operation' => $data['operation_type'] ?? 'unknown'
            ]);
        }
    }

    /**
     * Remove sensitive data from arrays recursively
     */
    private static function sanitizeData($data): mixed {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                // Check if key is sensitive
                $lowerKey = strtolower($key);
                $isSensitive = false;
                foreach (self::$sensitiveKeys as $sensitiveKey) {
                    if (strpos($lowerKey, $sensitiveKey) !== false) {
                        $isSensitive = true;
                        break;
                    }
                }

                if ($isSensitive) {
                    $sanitized[$key] = '[REDACTED]';
                } else {
                    $sanitized[$key] = self::sanitizeData($value);
                }
            }
            return $sanitized;
        } elseif (is_object($data)) {
            return self::sanitizeData((array)$data);
        } else {
            return $data;
        }
    }

    /**
     * Get audit logs for a user
     */
    public static function getUserLogs(int $userId, int $limit = 100, int $offset = 0, array $filters = []): array {
        try {
            $where = ['user_id' => $userId];

            // Apply filters
            if (isset($filters['operation_type'])) {
                $where['operation_type'] = $filters['operation_type'];
            }

            $sql = "SELECT * FROM audit_logs WHERE user_id = :user_id";
            $params = ['user_id' => $userId];

            if (isset($filters['operation_type'])) {
                $sql .= " AND operation_type = :operation_type";
                $params['operation_type'] = $filters['operation_type'];
            }

            if (isset($filters['start_date'])) {
                $sql .= " AND created_at >= :start_date";
                $params['start_date'] = $filters['start_date'];
            }

            if (isset($filters['end_date'])) {
                $sql .= " AND created_at <= :end_date";
                $params['end_date'] = $filters['end_date'];
            }

            $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $params['limit'] = $limit;
            $params['offset'] = $offset;

            return Database::fetchAll($sql, $params);
        } catch (\Exception $e) {
            Logger::error('Failed to fetch user audit logs', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get audit logs count for a user
     */
    public static function getUserLogsCount(int $userId, array $filters = []): int {
        try {
            $sql = "SELECT COUNT(*) as count FROM audit_logs WHERE user_id = :user_id";
            $params = ['user_id' => $userId];

            if (isset($filters['operation_type'])) {
                $sql .= " AND operation_type = :operation_type";
                $params['operation_type'] = $filters['operation_type'];
            }

            if (isset($filters['start_date'])) {
                $sql .= " AND created_at >= :start_date";
                $params['start_date'] = $filters['start_date'];
            }

            if (isset($filters['end_date'])) {
                $sql .= " AND created_at <= :end_date";
                $params['end_date'] = $filters['end_date'];
            }

            $result = Database::fetch($sql, $params);
            return (int)($result['count'] ?? 0);
        } catch (\Exception $e) {
            Logger::error('Failed to count user audit logs', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get audit logs for anonymous user by IP
     */
    public static function getAnonymousLogs(string $ipAddress, int $limit = 100): array {
        try {
            $sql = "SELECT * FROM audit_logs
                    WHERE ip_address = :ip_address AND user_id IS NULL
                    ORDER BY created_at DESC LIMIT :limit";

            return Database::fetchAll($sql, [
                'ip_address' => $ipAddress,
                'limit' => $limit
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to fetch anonymous audit logs', [
                'ip' => $ipAddress,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get operation statistics for a user
     */
    public static function getUserStats(int $userId, string $period = '30 days'): array {
        try {
            // Validate period to prevent SQL injection
            $allowedPeriods = [
                '7 days' => '7 days',
                '30 days' => '30 days',
                '90 days' => '90 days',
                '1 year' => '1 year'
            ];

            if (!isset($allowedPeriods[$period])) {
                Logger::warning('Invalid period requested for stats', [
                    'period' => $period,
                    'user_id' => $userId
                ]);
                $period = '30 days';
            }

            $sql = "SELECT
                        operation_type,
                        COUNT(*) as count,
                        AVG(duration_ms) as avg_duration_ms,
                        SUM(CASE WHEN response_status >= 400 THEN 1 ELSE 0 END) as error_count
                    FROM audit_logs
                    WHERE user_id = :user_id
                    AND created_at >= NOW() - INTERVAL :period
                    GROUP BY operation_type
                    ORDER BY count DESC";

            return Database::fetchAll($sql, [
                'user_id' => $userId,
                'period' => $period
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to fetch user stats', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Log file metadata
     */
    public static function logFileMetadata(string $filename, int $filesize, string $hash, string $mimeType = 'application/octet-stream'): array {
        return [
            'name' => basename($filename),
            'size' => $filesize,
            'hash' => $hash,
            'mime_type' => $mimeType
        ];
    }
}
