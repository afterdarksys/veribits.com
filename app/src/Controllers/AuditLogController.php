<?php
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Auth;
use VeriBits\Utils\Response;
use VeriBits\Utils\AuditLog;
use VeriBits\Utils\Logger;

class AuditLogController {
    /**
     * Get audit logs for the authenticated user
     */
    public function getLogs(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        if (!$userId) {
            Response::error('Invalid token', 401);
            return;
        }

        // Parse query parameters
        $limit = min((int)($_GET['limit'] ?? 100), 1000); // Max 1000
        $offset = (int)($_GET['offset'] ?? 0);
        $operation = $_GET['operation'] ?? null;
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        // Build filters
        $filters = [];
        if ($operation) {
            $filters['operation_type'] = $operation;
        }
        if ($startDate) {
            $filters['start_date'] = $startDate;
        }
        if ($endDate) {
            $filters['end_date'] = $endDate;
        }

        // Get logs and count
        $logs = AuditLog::getUserLogs($userId, $limit, $offset, $filters);
        $total = AuditLog::getUserLogsCount($userId, $filters);

        // Parse JSON fields
        foreach ($logs as &$log) {
            if (isset($log['request_data']) && is_string($log['request_data'])) {
                $log['request_data'] = json_decode($log['request_data'], true);
            }
            if (isset($log['response_data']) && is_string($log['response_data'])) {
                $log['response_data'] = json_decode($log['response_data'], true);
            }
            if (isset($log['files_metadata']) && is_string($log['files_metadata'])) {
                $log['files_metadata'] = json_decode($log['files_metadata'], true);
            }
        }

        Response::success([
            'logs' => $logs,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ]
        ]);
    }

    /**
     * Get audit log statistics for the authenticated user
     */
    public function getStats(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        if (!$userId) {
            Response::error('Invalid token', 401);
            return;
        }

        $period = $_GET['period'] ?? '30 days';

        // Validate period
        $allowedPeriods = ['7 days', '30 days', '90 days', '1 year'];
        if (!in_array($period, $allowedPeriods)) {
            $period = '30 days';
        }

        $stats = AuditLog::getUserStats($userId, $period);

        Response::success([
            'period' => $period,
            'stats' => $stats
        ]);
    }

    /**
     * Export audit logs as CSV
     */
    public function exportCsv(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        if (!$userId) {
            Response::error('Invalid token', 401);
            return;
        }

        // Get all logs (up to 10,000)
        $logs = AuditLog::getUserLogs($userId, 10000, 0);

        // Generate CSV
        $csv = "ID,Date/Time,Operation,Endpoint,Method,Status,Duration (ms),IP Address,Files\n";

        foreach ($logs as $log) {
            $files = '';
            if (!empty($log['files_metadata'])) {
                $metadata = json_decode($log['files_metadata'], true);
                if (is_array($metadata)) {
                    $fileNames = array_map(fn($f) => $f['name'] ?? 'unknown', $metadata);
                    $files = implode('; ', $fileNames);
                }
            }

            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $log['id'],
                $log['created_at'],
                $this->escapeCsv($log['operation_type']),
                $this->escapeCsv($log['endpoint']),
                $log['http_method'],
                $log['response_status'] ?? '',
                $log['duration_ms'] ?? '',
                $log['ip_address'],
                $this->escapeCsv($files)
            );
        }

        // Set headers for download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $csv;
        exit;
    }

    /**
     * Escape CSV field
     */
    private function escapeCsv(string $value): string {
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }

    /**
     * Get distinct operation types for filtering
     */
    public function getOperationTypes(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        if (!$userId) {
            Response::error('Invalid token', 401);
            return;
        }

        try {
            $sql = "SELECT DISTINCT operation_type
                    FROM audit_logs
                    WHERE user_id = :user_id
                    ORDER BY operation_type";

            $result = \VeriBits\Utils\Database::fetchAll($sql, ['user_id' => $userId]);
            $types = array_map(fn($row) => $row['operation_type'], $result);

            Response::success(['operation_types' => $types]);
        } catch (\Exception $e) {
            Logger::error('Failed to fetch operation types', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to fetch operation types', 500);
        }
    }
}
