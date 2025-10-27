<?php
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Auth;
use VeriBits\Utils\Response;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Database;
use VeriBits\Utils\CommandExecutor;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\AuditLog;

class KeystoreController {
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_TYPES = ['application/x-java-keystore', 'application/x-pkcs12', 'application/pkcs7-mime', 'application/octet-stream'];

    /**
     * Convert JKS to PKCS12
     */
    public function jksToPkcs12(): void {
        $auth = Auth::optionalAuth();
        $startTime = microtime(true);

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        // Validate file upload
        if (!isset($_FILES['file'])) {
            Response::error('No file uploaded', 400);
            return;
        }

        $file = $_FILES['file'];
        $password = $_POST['password'] ?? '';
        $newPassword = $_POST['new_password'] ?? $password;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('File upload failed', 400);
            return;
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            Response::error('File too large (max 10MB)', 400);
            return;
        }

        try {
            $tmpInput = $file['tmp_name'];
            $tmpOutput = tempnam(sys_get_temp_dir(), 'pkcs12_');
            $sourceHash = hash_file('sha256', $tmpInput);

            // Convert using keytool
            $result = CommandExecutor::execute('keytool', [
                '-importkeystore',
                '-srckeystore', $tmpInput,
                '-srcstoretype', 'JKS',
                '-srcstorepass', $password,
                '-destkeystore', $tmpOutput,
                '-deststoretype', 'PKCS12',
                '-deststorepass', $newPassword
            ]);

            if ($result['exit_code'] !== 0) {
                throw new \Exception('Conversion failed: ' . ($result['stderr'] ?? 'Unknown error'));
            }

            // Read output file
            $outputData = file_get_contents($tmpOutput);
            $outputHash = hash('sha256', $outputData);
            $outputSize = strlen($outputData);

            // Log operation (NOT the files)
            $this->logConversion([
                'user_id' => $auth['user_id'] ?? null,
                'ip_address' => $auth['ip_address'] ?? RateLimit::getClientIp(),
                'operation_type' => 'jks_to_pkcs12',
                'source_filename' => basename($file['name']),
                'source_filesize' => $file['size'],
                'source_hash' => $sourceHash,
                'output_filename' => str_replace('.jks', '.p12', basename($file['name'])),
                'output_filesize' => $outputSize,
                'output_hash' => $outputHash,
                'status' => 'success'
            ]);

            // Cleanup
            @unlink($tmpOutput);

            // Audit log
            $duration = (int)((microtime(true) - $startTime) * 1000);
            AuditLog::log([
                'user_id' => $auth['user_id'] ?? null,
                'operation_type' => 'tool:keystore-jks-to-pkcs12',
                'endpoint' => '/api/v1/tools/keystore/jks-to-pkcs12',
                'http_method' => 'POST',
                'request_data' => ['filename' => basename($file['name'])],
                'response_status' => 200,
                'files_metadata' => [AuditLog::logFileMetadata($file['name'], $file['size'], $sourceHash)],
                'duration_ms' => $duration
            ]);

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            // Return file for download
            header('Content-Type: application/x-pkcs12');
            header('Content-Disposition: attachment; filename="' . str_replace('.jks', '.p12', basename($file['name'])) . '"');
            header('Content-Length: ' . $outputSize);
            echo $outputData;
            exit;

        } catch (\Exception $e) {
            Logger::error('JKS to PKCS12 conversion failed', [
                'error' => $e->getMessage(),
                'filename' => $file['name']
            ]);

            $this->logConversion([
                'user_id' => $auth['user_id'] ?? null,
                'ip_address' => $auth['ip_address'] ?? RateLimit::getClientIp(),
                'operation_type' => 'jks_to_pkcs12',
                'source_filename' => basename($file['name']),
                'source_filesize' => $file['size'],
                'source_hash' => $sourceHash ?? 'unknown',
                'status' => 'error',
                'error_message' => $e->getMessage()
            ]);

            Response::error('Conversion failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Convert PKCS12 to JKS
     */
    public function pkcs12ToJks(): void {
        $auth = Auth::optionalAuth();
        $startTime = microtime(true);

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        if (!isset($_FILES['file'])) {
            Response::error('No file uploaded', 400);
            return;
        }

        $file = $_FILES['file'];
        $password = $_POST['password'] ?? '';
        $newPassword = $_POST['new_password'] ?? $password;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('File upload failed', 400);
            return;
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            Response::error('File too large (max 10MB)', 400);
            return;
        }

        try {
            $tmpInput = $file['tmp_name'];
            $tmpOutput = tempnam(sys_get_temp_dir(), 'jks_');
            $sourceHash = hash_file('sha256', $tmpInput);

            // Convert using keytool
            $result = CommandExecutor::execute('keytool', [
                '-importkeystore',
                '-srckeystore', $tmpInput,
                '-srcstoretype', 'PKCS12',
                '-srcstorepass', $password,
                '-destkeystore', $tmpOutput,
                '-deststoretype', 'JKS',
                '-deststorepass', $newPassword
            ]);

            if ($result['exit_code'] !== 0) {
                throw new \Exception('Conversion failed: ' . ($result['stderr'] ?? 'Unknown error'));
            }

            $outputData = file_get_contents($tmpOutput);
            $outputHash = hash('sha256', $outputData);
            $outputSize = strlen($outputData);

            $this->logConversion([
                'user_id' => $auth['user_id'] ?? null,
                'ip_address' => $auth['ip_address'] ?? RateLimit::getClientIp(),
                'operation_type' => 'pkcs12_to_jks',
                'source_filename' => basename($file['name']),
                'source_filesize' => $file['size'],
                'source_hash' => $sourceHash,
                'output_filename' => str_replace('.p12', '.jks', basename($file['name'])),
                'output_filesize' => $outputSize,
                'output_hash' => $outputHash,
                'status' => 'success'
            ]);

            @unlink($tmpOutput);

            $duration = (int)((microtime(true) - $startTime) * 1000);
            AuditLog::log([
                'user_id' => $auth['user_id'] ?? null,
                'operation_type' => 'tool:keystore-pkcs12-to-jks',
                'endpoint' => '/api/v1/tools/keystore/pkcs12-to-jks',
                'http_method' => 'POST',
                'request_data' => ['filename' => basename($file['name'])],
                'response_status' => 200,
                'files_metadata' => [AuditLog::logFileMetadata($file['name'], $file['size'], $sourceHash)],
                'duration_ms' => $duration
            ]);

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            header('Content-Type: application/x-java-keystore');
            header('Content-Disposition: attachment; filename="' . str_replace('.p12', '.jks', basename($file['name'])) . '"');
            header('Content-Length: ' . $outputSize);
            echo $outputData;
            exit;

        } catch (\Exception $e) {
            Logger::error('PKCS12 to JKS conversion failed', [
                'error' => $e->getMessage(),
                'filename' => $file['name']
            ]);

            $this->logConversion([
                'user_id' => $auth['user_id'] ?? null,
                'ip_address' => $auth['ip_address'] ?? RateLimit::getClientIp(),
                'operation_type' => 'pkcs12_to_jks',
                'source_filename' => basename($file['name']),
                'source_filesize' => $file['size'],
                'source_hash' => $sourceHash ?? 'unknown',
                'status' => 'error',
                'error_message' => $e->getMessage()
            ]);

            Response::error('Conversion failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Extract certificates and keys from PKCS12/PKCS7
     */
    public function extractPkcs(): void {
        $auth = Auth::optionalAuth();
        $startTime = microtime(true);

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        if (!isset($_FILES['file'])) {
            Response::error('No file uploaded', 400);
            return;
        }

        $file = $_FILES['file'];
        $password = $_POST['password'] ?? '';
        $format = $_POST['format'] ?? 'pkcs12'; // 'pkcs12' or 'pkcs7'

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('File upload failed', 400);
            return;
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            Response::error('File too large (max 10MB)', 400);
            return;
        }

        try {
            $tmpInput = $file['tmp_name'];
            $sourceHash = hash_file('sha256', $tmpInput);
            $extracted = [];

            if ($format === 'pkcs12') {
                $extracted = $this->extractPkcs12($tmpInput, $password);
            } else {
                $extracted = $this->extractPkcs7($tmpInput);
            }

            $this->logConversion([
                'user_id' => $auth['user_id'] ?? null,
                'ip_address' => $auth['ip_address'] ?? RateLimit::getClientIp(),
                'operation_type' => 'extract_' . $format,
                'source_filename' => basename($file['name']),
                'source_filesize' => $file['size'],
                'source_hash' => $sourceHash,
                'extracted_items' => json_encode($extracted['metadata']),
                'status' => 'success'
            ]);

            $duration = (int)((microtime(true) - $startTime) * 1000);
            AuditLog::log([
                'user_id' => $auth['user_id'] ?? null,
                'operation_type' => 'tool:keystore-extract-' . $format,
                'endpoint' => '/api/v1/tools/keystore/extract',
                'http_method' => 'POST',
                'request_data' => ['filename' => basename($file['name']), 'format' => $format],
                'response_status' => 200,
                'files_metadata' => [AuditLog::logFileMetadata($file['name'], $file['size'], $sourceHash)],
                'duration_ms' => $duration
            ]);

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success($extracted);

        } catch (\Exception $e) {
            Logger::error('PKCS extraction failed', [
                'error' => $e->getMessage(),
                'filename' => $file['name'],
                'format' => $format
            ]);

            $this->logConversion([
                'user_id' => $auth['user_id'] ?? null,
                'ip_address' => $auth['ip_address'] ?? RateLimit::getClientIp(),
                'operation_type' => 'extract_' . $format,
                'source_filename' => basename($file['name']),
                'source_filesize' => $file['size'],
                'source_hash' => $sourceHash ?? 'unknown',
                'status' => 'error',
                'error_message' => $e->getMessage()
            ]);

            Response::error('Extraction failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Extract from PKCS12 using openssl
     */
    private function extractPkcs12(string $filepath, string $password): array {
        $items = [];
        $metadata = [];

        // Extract certificate
        $certResult = CommandExecutor::execute('openssl', [
            'pkcs12', '-in', $filepath, '-clcerts', '-nokeys',
            '-passin', 'pass:' . $password
        ]);

        if ($certResult['exit_code'] === 0 && !empty($certResult['stdout'])) {
            $items['certificate'] = $certResult['stdout'];

            // Parse certificate details
            $textResult = CommandExecutor::execute('openssl', [
                'x509', '-text', '-noout'
            ], $certResult['stdout']);

            if (preg_match('/Subject:(.+)/i', $textResult['stdout'], $m)) {
                $metadata[] = ['type' => 'certificate', 'subject' => trim($m[1])];
            }
        }

        // Extract private key
        $keyResult = CommandExecutor::execute('openssl', [
            'pkcs12', '-in', $filepath, '-nocerts', '-nodes',
            '-passin', 'pass:' . $password
        ]);

        if ($keyResult['exit_code'] === 0 && !empty($keyResult['stdout'])) {
            $items['private_key'] = $keyResult['stdout'];
            $metadata[] = ['type' => 'private_key'];
        }

        // Extract CA certificates
        $caResult = CommandExecutor::execute('openssl', [
            'pkcs12', '-in', $filepath, '-cacerts', '-nokeys',
            '-passin', 'pass:' . $password
        ]);

        if ($caResult['exit_code'] === 0 && !empty($caResult['stdout'])) {
            $items['ca_certificates'] = $caResult['stdout'];
            $metadata[] = ['type' => 'ca_certificates'];
        }

        return ['items' => $items, 'metadata' => $metadata];
    }

    /**
     * Extract from PKCS7
     */
    private function extractPkcs7(string $filepath): array {
        $items = [];
        $metadata = [];

        // Extract certificates from PKCS7
        $result = CommandExecutor::execute('openssl', [
            'pkcs7', '-in', $filepath, '-print_certs'
        ]);

        if ($result['exit_code'] === 0 && !empty($result['stdout'])) {
            $items['certificates'] = $result['stdout'];

            // Count certificates
            $count = substr_count($result['stdout'], '-----BEGIN CERTIFICATE-----');
            $metadata[] = ['type' => 'certificates', 'count' => $count];
        }

        return ['items' => $items, 'metadata' => $metadata];
    }

    /**
     * Log conversion operation
     */
    private function logConversion(array $data): void {
        try {
            Database::insert('keystore_conversions', $data);
        } catch (\Exception $e) {
            Logger::error('Failed to log keystore conversion', [
                'error' => $e->getMessage(),
                'operation' => $data['operation_type'] ?? 'unknown'
            ]);
        }
    }
}
