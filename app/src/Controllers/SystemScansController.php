<?php
namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\Validator;
use VeriBits\Utils\Database;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Request;

class SystemScansController {
    /**
     * POST /api/v1/system-scans
     * Submit a new system scan with file hashes
     */
    public function create(): void {
        // Require API key authentication
        $keyData = Auth::requireApiKey();
        $userId = $keyData['user_id'];

        // Get request body
        $body = Request::getJsonBody();

        // Validate required fields
        $validator = new Validator($body);
        $validator->required('system_name')->string('system_name', 1, 255)
                  ->required('os_type')->string('os_type', 1, 50)
                  ->required('scan_date')->string('scan_date')
                  ->required('total_files')->integer('total_files', 0)
                  ->required('directories')->array('directories');

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        // Extract and sanitize data
        $systemName = $validator->sanitize('system_name');
        $systemIp = $validator->sanitize('system_ip') ?: null;
        $systemPublicIp = $validator->sanitize('system_public') ?: null;
        $osType = $validator->sanitize('os_type');
        $osVersion = $validator->sanitize('os_version') ?: null;
        $scanDate = $validator->sanitize('scan_date');
        $totalFiles = (int)$body['total_files'];
        $totalErrors = (int)($body['total_errors'] ?? 0);
        $directories = $body['directories'];
        $hashAlgorithms = $body['hash_algorithms'] ?? ['sha512'];

        // Validate scan_date format
        try {
            $scanDateTime = new \DateTime($scanDate);
        } catch (\Exception $e) {
            Response::error('Invalid scan_date format. Use ISO 8601 format.', 400);
            return;
        }

        // Begin transaction
        try {
            Database::beginTransaction();

            // Check for duplicate scan (same system, same scan date)
            $existing = Database::fetch(
                "SELECT id FROM system_scans
                 WHERE user_id = :user_id
                 AND system_name = :system_name
                 AND scan_date = :scan_date",
                [
                    'user_id' => $userId,
                    'system_name' => $systemName,
                    'scan_date' => $scanDateTime->format('Y-m-d H:i:s')
                ]
            );

            if ($existing) {
                Database::rollback();
                Response::error('Duplicate scan. A scan from this system at this time already exists.', 409);
                return;
            }

            // Insert system_scan record
            $scanId = Database::insert('system_scans', [
                'user_id' => $userId,
                'system_name' => $systemName,
                'system_ip' => $systemIp,
                'system_public_ip' => $systemPublicIp,
                'os_type' => $osType,
                'os_version' => $osVersion,
                'hash_algorithms' => '{' . implode(',', $hashAlgorithms) . '}',
                'scan_date' => $scanDateTime->format('Y-m-d H:i:s'),
                'total_files' => $totalFiles,
                'total_errors' => $totalErrors,
                'total_directories' => count($directories),
                'status' => 'processing'
            ]);

            // Insert file_hashes records (batch insert for performance)
            $fileCount = 0;
            $batchSize = 500;
            $batch = [];

            foreach ($directories as $dir) {
                if (!isset($dir['files']) || !is_array($dir['files'])) {
                    continue;
                }

                $dirName = $dir['dir_name'] ?? 'unknown';

                foreach ($dir['files'] as $file) {
                    if (!isset($file['file_name'])) {
                        continue;
                    }

                    $fileName = $file['file_name'];

                    // Extract hashes based on format
                    $sha256 = null;
                    $sha512 = null;

                    // Single hash format (backward compatibility)
                    if (isset($file['file_hash'])) {
                        // Determine algorithm by hash length
                        $hashLen = strlen($file['file_hash']);
                        if ($hashLen === 64) {
                            $sha256 = $file['file_hash'];
                        } elseif ($hashLen === 128) {
                            $sha512 = $file['file_hash'];
                        }
                    }

                    // Multi-hash format
                    if (isset($file['file_hash_sha256'])) {
                        $sha256 = $file['file_hash_sha256'];
                    }
                    if (isset($file['file_hash_sha512'])) {
                        $sha512 = $file['file_hash_sha512'];
                    }

                    // Skip if no valid hashes
                    if (!$sha256 && !$sha512) {
                        continue;
                    }

                    $batch[] = [
                        'scan_id' => $scanId,
                        'directory_name' => $dirName,
                        'file_name' => $fileName,
                        'file_hash_sha256' => $sha256,
                        'file_hash_sha512' => $sha512
                    ];

                    $fileCount++;

                    // Insert batch when it reaches size limit
                    if (count($batch) >= $batchSize) {
                        $this->insertFileBatch($batch);
                        $batch = [];
                    }
                }
            }

            // Insert remaining batch
            if (!empty($batch)) {
                $this->insertFileBatch($batch);
            }

            // Update scan status to completed
            Database::update('system_scans', ['status' => 'completed', 'processed_at' => date('Y-m-d H:i:s')], ['id' => $scanId]);

            Database::commit();

            Logger::info('System scan created', [
                'scan_id' => $scanId,
                'user_id' => $userId,
                'system_name' => $systemName,
                'total_files' => $fileCount,
                'total_directories' => count($directories)
            ]);

            Response::success([
                'scan_id' => $scanId,
                'system_name' => $systemName,
                'total_files_processed' => $fileCount,
                'total_directories' => count($directories),
                'status' => 'completed'
            ], 'System scan uploaded successfully', 201);

        } catch (\Exception $e) {
            Database::rollback();
            Logger::error('System scan creation failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            Response::error('Failed to process system scan', 500);
        }
    }

    /**
     * GET /api/v1/system-scans
     * List all scans for the authenticated user
     */
    public function list(): void {
        $keyData = Auth::requireApiKey();
        $userId = $keyData['user_id'];

        // Pagination
        $page = (int)($_GET['page'] ?? 1);
        $limit = min((int)($_GET['limit'] ?? 20), 100);
        $offset = ($page - 1) * $limit;

        // Filter by system name
        $systemName = $_GET['system_name'] ?? null;

        try {
            $params = ['user_id' => $userId];
            $whereClause = 'user_id = :user_id';

            if ($systemName) {
                $whereClause .= ' AND system_name = :system_name';
                $params['system_name'] = $systemName;
            }

            // Get total count
            $totalCount = Database::fetch(
                "SELECT COUNT(*) as count FROM system_scans WHERE $whereClause",
                $params
            )['count'] ?? 0;

            // Get scans
            $scans = Database::fetchAll(
                "SELECT id, system_name, system_ip, system_public_ip, os_type, os_version,
                        hash_algorithms, scan_date, total_files, total_errors, total_directories,
                        status, created_at, processed_at
                 FROM system_scans
                 WHERE $whereClause
                 ORDER BY scan_date DESC, created_at DESC
                 LIMIT :limit OFFSET :offset",
                array_merge($params, ['limit' => $limit, 'offset' => $offset])
            );

            $totalPages = ceil($totalCount / $limit);

            Response::success([
                'scans' => $scans,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$totalCount,
                    'total_pages' => (int)$totalPages
                ]
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to list system scans', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to retrieve system scans', 500);
        }
    }

    /**
     * GET /api/v1/system-scans/{id}
     * Get details of a specific scan including file hashes
     */
    public function get(): void {
        $keyData = Auth::requireApiKey();
        $userId = $keyData['user_id'];

        // Extract scan_id from URL
        $scanId = (int)($_GET['id'] ?? 0);

        if (!$scanId) {
            Response::error('Scan ID required', 400);
            return;
        }

        try {
            // Get scan metadata
            $scan = Database::fetch(
                "SELECT * FROM system_scans WHERE id = :id AND user_id = :user_id",
                ['id' => $scanId, 'user_id' => $userId]
            );

            if (!$scan) {
                Response::error('Scan not found', 404);
                return;
            }

            // Get file hashes (with pagination for large scans)
            $page = (int)($_GET['files_page'] ?? 1);
            $limit = min((int)($_GET['files_limit'] ?? 100), 1000);
            $offset = ($page - 1) * $limit;

            $includeFiles = ($_GET['include_files'] ?? 'true') === 'true';

            $response = ['scan' => $scan];

            if ($includeFiles) {
                $files = Database::fetchAll(
                    "SELECT directory_name, file_name, file_hash_sha256, file_hash_sha512, created_at
                     FROM file_hashes
                     WHERE scan_id = :scan_id
                     ORDER BY directory_name, file_name
                     LIMIT :limit OFFSET :offset",
                    ['scan_id' => $scanId, 'limit' => $limit, 'offset' => $offset]
                );

                $fileCount = Database::fetch(
                    "SELECT COUNT(*) as count FROM file_hashes WHERE scan_id = :scan_id",
                    ['scan_id' => $scanId]
                )['count'] ?? 0;

                $response['files'] = $files;
                $response['files_pagination'] = [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$fileCount,
                    'total_pages' => ceil($fileCount / $limit)
                ];
            }

            Response::success($response);

        } catch (\Exception $e) {
            Logger::error('Failed to get system scan', [
                'scan_id' => $scanId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to retrieve scan details', 500);
        }
    }

    /**
     * DELETE /api/v1/system-scans/{id}
     * Delete a scan and all associated file hashes
     */
    public function delete(): void {
        $keyData = Auth::requireApiKey();
        $userId = $keyData['user_id'];

        $scanId = (int)($_GET['id'] ?? 0);

        if (!$scanId) {
            Response::error('Scan ID required', 400);
            return;
        }

        try {
            // Verify ownership
            $scan = Database::fetch(
                "SELECT id FROM system_scans WHERE id = :id AND user_id = :user_id",
                ['id' => $scanId, 'user_id' => $userId]
            );

            if (!$scan) {
                Response::error('Scan not found', 404);
                return;
            }

            // Delete scan (cascades to file_hashes)
            Database::delete('system_scans', ['id' => $scanId]);

            Logger::info('System scan deleted', [
                'scan_id' => $scanId,
                'user_id' => $userId
            ]);

            Response::success([], 'Scan deleted successfully');

        } catch (\Exception $e) {
            Logger::error('Failed to delete system scan', [
                'scan_id' => $scanId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to delete scan', 500);
        }
    }

    /**
     * Helper: Insert batch of file hashes
     */
    private function insertFileBatch(array $batch): void {
        if (empty($batch)) {
            return;
        }

        $values = [];
        $params = [];
        $idx = 0;

        foreach ($batch as $file) {
            $values[] = sprintf(
                '($%d, $%d, $%d, $%d, $%d)',
                ++$idx, ++$idx, ++$idx, ++$idx, ++$idx
            );

            $params[] = $file['scan_id'];
            $params[] = $file['directory_name'];
            $params[] = $file['file_name'];
            $params[] = $file['file_hash_sha256'];
            $params[] = $file['file_hash_sha512'];
        }

        $sql = "INSERT INTO file_hashes (scan_id, directory_name, file_name, file_hash_sha256, file_hash_sha512)
                VALUES " . implode(', ', $values) . "
                ON CONFLICT (scan_id, file_name) DO NOTHING";

        Database::execute($sql, $params);
    }
}
