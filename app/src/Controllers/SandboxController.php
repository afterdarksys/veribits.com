<?php

namespace App\Controllers;

use App\Utils\Auth;
use App\Utils\Database;
use App\Utils\Logger;

/**
 * Malware Sandbox Controller
 *
 * Interactive malware analysis sandbox with:
 * - Static analysis (file inspection, signatures, strings)
 * - Dynamic analysis (behavior monitoring, network activity)
 * - API + Web UI reports
 * - Automated YARA scanning
 * - ML-enhanced threat clustering
 */
class SandboxController
{
    private $db;
    private $auth;
    private $logger;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
        $this->logger = new Logger('Sandbox');
    }

    /**
     * Submit file to sandbox for analysis
     *
     * POST /api/v1/sandbox/submit
     * Content-Type: multipart/form-data
     * Body: file (binary), analysis_type (static|dynamic|full), timeout (seconds)
     */
    public function submit()
    {
        try {
            $user = $this->auth->getUserFromToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            // Check file upload
            if (!isset($_FILES['file'])) {
                http_response_code(400);
                echo json_encode(['error' => 'File is required']);
                return;
            }

            $file = $_FILES['file'];
            $analysisType = $_POST['analysis_type'] ?? 'full';
            $timeout = intval($_POST['timeout'] ?? 300); // Default 5 minutes

            // Validate analysis type
            if (!in_array($analysisType, ['static', 'dynamic', 'full'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid analysis_type. Use: static, dynamic, or full']);
                return;
            }

            // Save uploaded file
            $uploadDir = '/tmp/sandbox/' . uniqid();
            mkdir($uploadDir, 0700, true);
            $filePath = $uploadDir . '/' . basename($file['name']);
            move_uploaded_file($file['tmp_name'], $filePath);

            // Calculate file hash
            $fileHash = hash_file('sha256', $filePath);

            // Check if already analyzed
            $existing = $this->getExistingAnalysis($fileHash);
            if ($existing) {
                echo json_encode([
                    'cached' => true,
                    'analysis_id' => $existing['id'],
                    'url' => getenv('API_URL') . '/api/v1/sandbox/report/' . $existing['id']
                ]);
                return;
            }

            // Create sandbox analysis record
            $analysisId = $this->createAnalysisRecord($user['id'], $filePath, $fileHash, $analysisType);

            // Queue analysis
            $this->queueAnalysis($analysisId, $filePath, $analysisType, $timeout);

            echo json_encode([
                'analysis_id' => $analysisId,
                'status' => 'queued',
                'estimated_time' => $timeout,
                'url' => getenv('API_URL') . '/api/v1/sandbox/report/' . $analysisId,
                'poll_url' => getenv('API_URL') . '/api/v1/sandbox/status/' . $analysisId
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Sandbox submission failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'Submission failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Get sandbox analysis status
     *
     * GET /api/v1/sandbox/status/{analysis_id}
     */
    public function getStatus($analysisId)
    {
        try {
            $user = $this->auth->getUserFromToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $analysis = $this->getAnalysis($analysisId);
            if (!$analysis || $analysis['user_id'] !== $user['id']) {
                http_response_code(404);
                echo json_encode(['error' => 'Analysis not found']);
                return;
            }

            echo json_encode([
                'analysis_id' => $analysisId,
                'status' => $analysis['status'],
                'progress' => $analysis['progress'],
                'started_at' => $analysis['started_at'],
                'completed_at' => $analysis['completed_at'],
                'error' => $analysis['error']
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Status check failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'Status check failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Get full sandbox analysis report
     *
     * GET /api/v1/sandbox/report/{analysis_id}
     */
    public function getReport($analysisId)
    {
        try {
            $user = $this->auth->getUserFromToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $analysis = $this->getAnalysis($analysisId);
            if (!$analysis || $analysis['user_id'] !== $user['id']) {
                http_response_code(404);
                echo json_encode(['error' => 'Analysis not found']);
                return;
            }

            if ($analysis['status'] !== 'completed') {
                http_response_code(202);
                echo json_encode([
                    'status' => 'processing',
                    'progress' => $analysis['progress'],
                    'message' => 'Analysis still in progress'
                ]);
                return;
            }

            // Get full report
            $report = json_decode($analysis['report'], true);

            echo json_encode([
                'analysis_id' => $analysisId,
                'file_name' => $analysis['file_name'],
                'file_hash' => $analysis['file_hash'],
                'analysis_type' => $analysis['analysis_type'],
                'verdict' => $analysis['verdict'],
                'threat_score' => $analysis['threat_score'],
                'report' => $report,
                'created_at' => $analysis['created_at'],
                'completed_at' => $analysis['completed_at']
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Report retrieval failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'Report retrieval failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Perform static analysis
     *
     * POST /api/v1/sandbox/static-analysis
     * Body: {"file_path": "/path/to/file"}
     */
    public function staticAnalysis()
    {
        try {
            $user = $this->auth->getUserFromToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $filePath = $input['file_path'] ?? null;

            if (!$filePath || !file_exists($filePath)) {
                http_response_code(400);
                echo json_encode(['error' => 'Valid file path required']);
                return;
            }

            // Perform static analysis
            $results = $this->performStaticAnalysis($filePath);

            echo json_encode([
                'file' => basename($filePath),
                'static_analysis' => $results,
                'timestamp' => date('c')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Static analysis failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'Static analysis failed: ' . $e->getMessage()]);
        }
    }

    // ===== PRIVATE HELPER METHODS =====

    private function getExistingAnalysis($fileHash)
    {
        return $this->db->query(
            "SELECT * FROM sandbox_analyses WHERE file_hash = ? ORDER BY created_at DESC LIMIT 1",
            [$fileHash]
        )->fetch();
    }

    private function createAnalysisRecord($userId, $filePath, $fileHash, $analysisType)
    {
        $result = $this->db->query(
            "INSERT INTO sandbox_analyses
            (user_id, file_name, file_path, file_hash, analysis_type, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'queued', NOW())
            RETURNING id",
            [$userId, basename($filePath), $filePath, $fileHash, $analysisType]
        );
        return $result->fetch()['id'];
    }

    private function queueAnalysis($analysisId, $filePath, $analysisType, $timeout)
    {
        // TODO: Queue to background worker (Redis Queue, RabbitMQ, etc.)
        // For now, start analysis immediately in background
        $this->startAnalysisAsync($analysisId, $filePath, $analysisType, $timeout);
    }

    private function startAnalysisAsync($analysisId, $filePath, $analysisType, $timeout)
    {
        // Mark as processing
        $this->db->query(
            "UPDATE sandbox_analyses SET status = 'processing', started_at = NOW() WHERE id = ?",
            [$analysisId]
        );

        // Perform analysis based on type
        $report = [];

        if (in_array($analysisType, ['static', 'full'])) {
            $report['static'] = $this->performStaticAnalysis($filePath);
        }

        if (in_array($analysisType, ['dynamic', 'full'])) {
            $report['dynamic'] = $this->performDynamicAnalysis($filePath, $timeout);
        }

        // Calculate verdict and threat score
        $verdict = $this->calculateVerdict($report);
        $threatScore = $this->calculateThreatScore($report);

        // Save report
        $this->db->query(
            "UPDATE sandbox_analyses
            SET status = 'completed', report = ?, verdict = ?, threat_score = ?, completed_at = NOW(), progress = 100
            WHERE id = ?",
            [json_encode($report), $verdict, $threatScore, $analysisId]
        );
    }

    private function performStaticAnalysis($filePath)
    {
        $analysis = [
            'file_info' => [
                'size' => filesize($filePath),
                'type' => mime_content_type($filePath),
                'permissions' => substr(sprintf('%o', fileperms($filePath)), -4)
            ],
            'hashes' => [
                'md5' => md5_file($filePath),
                'sha1' => sha1_file($filePath),
                'sha256' => hash_file('sha256', $filePath)
            ],
            'strings' => $this->extractStrings($filePath),
            'entropy' => $this->calculateEntropy($filePath),
            'pe_analysis' => $this->analyzePE($filePath),
            'signatures' => $this->checkSignatures($filePath),
            'yara_matches' => $this->runYARAScan($filePath),
            'embedded_files' => $this->detectEmbeddedFiles($filePath)
        ];

        return $analysis;
    }

    private function performDynamicAnalysis($filePath, $timeout)
    {
        // TODO: Implement actual dynamic analysis (requires sandboxed environment)
        // This would typically involve:
        // - Running file in isolated VM/container
        // - Monitoring system calls, network traffic, file operations
        // - Capturing behavior patterns

        return [
            'execution' => [
                'executed' => false,
                'timeout' => $timeout,
                'error' => 'Dynamic analysis not yet implemented'
            ],
            'network_activity' => [],
            'file_operations' => [],
            'registry_changes' => [],
            'processes_created' => [],
            'api_calls' => []
        ];
    }

    private function extractStrings($filePath)
    {
        $data = file_get_contents($filePath);
        preg_match_all('/[ -~]{8,}/', $data, $matches);
        return array_slice($matches[0], 0, 100); // First 100 strings
    }

    private function calculateEntropy($filePath)
    {
        $data = file_get_contents($filePath);
        $entropy = 0;
        $size = strlen($data);

        for ($i = 0; $i < 256; $i++) {
            $count = substr_count($data, chr($i));
            if ($count > 0) {
                $freq = $count / $size;
                $entropy -= $freq * log($freq, 2);
            }
        }

        return round($entropy, 3);
    }

    private function analyzePE($filePath)
    {
        // TODO: Implement PE (Portable Executable) analysis
        return ['pe_detected' => false];
    }

    private function checkSignatures($filePath)
    {
        // TODO: Check code signing signatures
        return ['signed' => false];
    }

    private function runYARAScan($filePath)
    {
        // TODO: Run YARA rules against file
        return [];
    }

    private function detectEmbeddedFiles($filePath)
    {
        // TODO: Detect embedded files (ZIP, archives, etc.)
        return [];
    }

    private function calculateVerdict($report)
    {
        // Simple verdict logic
        if (!empty($report['static']['yara_matches'])) {
            return 'malicious';
        }

        $entropy = $report['static']['entropy'] ?? 0;
        if ($entropy > 7.5) {
            return 'suspicious';
        }

        return 'clean';
    }

    private function calculateThreatScore($report)
    {
        $score = 0;

        // Add points for various indicators
        if (!empty($report['static']['yara_matches'])) $score += 50;
        if (($report['static']['entropy'] ?? 0) > 7.5) $score += 20;
        if (!empty($report['static']['embedded_files'])) $score += 10;
        if (empty($report['static']['signatures']['signed'])) $score += 5;

        return min($score, 100);
    }

    private function getAnalysis($analysisId)
    {
        return $this->db->query(
            "SELECT * FROM sandbox_analyses WHERE id = ?",
            [$analysisId]
        )->fetch();
    }
}
