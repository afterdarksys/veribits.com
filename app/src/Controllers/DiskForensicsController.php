<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Config;

class DiskForensicsController
{
    private const MAX_UPLOAD_SIZE = 2 * 1024 * 1024 * 1024; // 2GB
    private const UPLOAD_DIR = '/tmp/veribits_forensics/';
    private const RESULTS_DIR = '/tmp/veribits_forensics_results/';

    /**
     * Upload disk image for analysis
     */
    public function upload(): void
    {
        $auth = Auth::requireAuth();

        if (!isset($_FILES['file'])) {
            Response::error('No file uploaded', 400);
            return;
        }

        $file = $_FILES['file'];
        $name = $_POST['name'] ?? pathinfo($file['name'], PATHINFO_FILENAME);

        // Validate file size
        if ($file['size'] > self::MAX_UPLOAD_SIZE) {
            Response::error('File too large. Maximum 2GB for web upload. Use system client for larger images.', 400);
            return;
        }

        // Validate file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['dd', 'raw', 'img', 'e01', 'aff', 'vhd', 'vhdx', 'vmdk'];

        if (!in_array($extension, $allowedExtensions)) {
            Response::error('Unsupported file format. Allowed: ' . implode(', ', $allowedExtensions), 400);
            return;
        }

        try {
            // Create upload directory if needed
            if (!is_dir(self::UPLOAD_DIR)) {
                mkdir(self::UPLOAD_DIR, 0755, true);
            }

            // Generate unique ID for this analysis
            $analysisId = uniqid('dsk_', true);
            $uploadPath = self::UPLOAD_DIR . $analysisId . '.' . $extension;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                Response::error('Failed to save uploaded file', 500);
                return;
            }

            // Calculate hash for integrity
            $md5 = md5_file($uploadPath);
            $sha256 = hash_file('sha256', $uploadPath);

            // Get basic image info
            $imageInfo = $this->getImageInfo($uploadPath);

            Response::success('Disk image uploaded successfully', [
                'analysis_id' => $analysisId,
                'filename' => $name,
                'size' => $file['size'],
                'format' => $extension,
                'md5' => $md5,
                'sha256' => $sha256,
                'image_info' => $imageInfo,
                'status' => 'ready',
                'message' => 'Image uploaded and ready for analysis'
            ]);

        } catch (\Exception $e) {
            Logger::error('Disk image upload failed', [
                'error' => $e->getMessage(),
                'user_id' => $auth['user_id']
            ]);
            Response::error('Upload failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Analyze disk image
     */
    public function analyze(): void
    {
        $auth = Auth::requireAuth();

        $input = json_decode(file_get_contents('php://input'), true);
        $analysisId = $input['analysis_id'] ?? '';
        $operations = $input['operations'] ?? ['list_files'];

        if (empty($analysisId)) {
            Response::error('Analysis ID is required', 400);
            return;
        }

        try {
            // Find the image file
            $imagePath = $this->findImageFile($analysisId);

            if (!$imagePath || !file_exists($imagePath)) {
                Response::error('Image not found', 404);
                return;
            }

            $results = [];

            foreach ($operations as $operation) {
                switch ($operation) {
                    case 'list_files':
                        $results['files'] = $this->listFiles($imagePath);
                        break;

                    case 'recover_deleted':
                        $results['recovered'] = $this->recoverDeleted($imagePath, $analysisId);
                        break;

                    case 'timeline':
                        $results['timeline'] = $this->generateTimeline($imagePath);
                        break;

                    case 'fsstat':
                        $results['filesystem'] = $this->getFileSystemStats($imagePath);
                        break;

                    case 'partitions':
                        $results['partitions'] = $this->getPartitions($imagePath);
                        break;

                    default:
                        $results['warnings'][] = "Unknown operation: $operation";
                }
            }

            Response::success('Analysis completed', [
                'analysis_id' => $analysisId,
                'operations_completed' => count($operations),
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Logger::error('Disk analysis failed', [
                'error' => $e->getMessage(),
                'analysis_id' => $analysisId
            ]);
            Response::error('Analysis failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Extract specific file from disk image
     */
    public function extractFile(): void
    {
        $auth = Auth::requireAuth();

        $input = json_decode(file_get_contents('php://input'), true);
        $analysisId = $input['analysis_id'] ?? '';
        $inode = $input['inode'] ?? '';
        $filename = $input['filename'] ?? 'extracted_file';

        if (empty($analysisId) || empty($inode)) {
            Response::error('Analysis ID and inode are required', 400);
            return;
        }

        try {
            $imagePath = $this->findImageFile($analysisId);

            if (!$imagePath) {
                Response::error('Image not found', 404);
                return;
            }

            // Extract file using icat
            $outputPath = self::RESULTS_DIR . $analysisId . '/' . $filename;

            if (!is_dir(dirname($outputPath))) {
                mkdir(dirname($outputPath), 0755, true);
            }

            $cmd = sprintf(
                'icat %s %s > %s 2>&1',
                escapeshellarg($imagePath),
                escapeshellarg($inode),
                escapeshellarg($outputPath)
            );

            exec($cmd, $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($outputPath)) {
                Response::error('Failed to extract file: ' . implode("\n", $output), 500);
                return;
            }

            // Return file as download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
            header('Content-Length: ' . filesize($outputPath));

            readfile($outputPath);
            @unlink($outputPath);
            exit;

        } catch (\Exception $e) {
            Response::error('Extraction failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get basic image information
     */
    private function getImageInfo(string $imagePath): array
    {
        $info = [
            'size' => filesize($imagePath),
            'format' => pathinfo($imagePath, PATHINFO_EXTENSION)
        ];

        // Try to get image stats
        $cmd = sprintf('img_stat %s 2>&1', escapeshellarg($imagePath));
        exec($cmd, $output, $returnCode);

        if ($returnCode === 0) {
            $info['img_stat'] = implode("\n", $output);
        }

        return $info;
    }

    /**
     * List all files in disk image
     */
    private function listFiles(string $imagePath): array
    {
        $cmd = sprintf(
            'fls -r -p %s 2>&1',
            escapeshellarg($imagePath)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Failed to list files: ' . implode("\n", $output));
        }

        // Parse fls output
        $files = [];
        foreach ($output as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Parse fls format: r/r 123: path/to/file
            if (preg_match('/^([rd\/\-]+)\s+(\d+):\s+(.+)$/', $line, $matches)) {
                $files[] = [
                    'type' => str_contains($matches[1], 'd') ? 'directory' : 'file',
                    'deleted' => str_contains($matches[1], 'r') && !str_contains($matches[1], '/'),
                    'inode' => $matches[2],
                    'path' => trim($matches[3])
                ];
            }
        }

        return [
            'total' => count($files),
            'files' => array_slice($files, 0, 1000), // Limit to first 1000 for web display
            'truncated' => count($files) > 1000
        ];
    }

    /**
     * Recover deleted files
     */
    private function recoverDeleted(string $imagePath, string $analysisId): array
    {
        $outputDir = self::RESULTS_DIR . $analysisId . '/recovered/';

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $cmd = sprintf(
            'tsk_recover %s %s 2>&1',
            escapeshellarg($imagePath),
            escapeshellarg($outputDir)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Recovery failed: ' . implode("\n", $output));
        }

        // Count recovered files
        $recoveredFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($outputDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $recoveredFiles[] = [
                    'name' => $file->getFilename(),
                    'path' => str_replace($outputDir, '', $file->getPathname()),
                    'size' => $file->getSize()
                ];
            }
        }

        return [
            'recovered_count' => count($recoveredFiles),
            'output_directory' => $outputDir,
            'files' => array_slice($recoveredFiles, 0, 100) // First 100 for display
        ];
    }

    /**
     * Generate forensic timeline
     */
    private function generateTimeline(string $imagePath): array
    {
        // Generate body file first
        $bodyFile = self::RESULTS_DIR . uniqid('body_') . '.txt';

        $cmd = sprintf(
            'fls -m / -r %s > %s 2>&1',
            escapeshellarg($imagePath),
            escapeshellarg($bodyFile)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($bodyFile)) {
            throw new \Exception('Timeline generation failed');
        }

        // Generate timeline using mactime
        $timelineFile = self::RESULTS_DIR . uniqid('timeline_') . '.csv';

        $cmd = sprintf(
            'mactime -b %s -d > %s 2>&1',
            escapeshellarg($bodyFile),
            escapeshellarg($timelineFile)
        );

        exec($cmd, $output, $returnCode);

        // Parse timeline
        $timeline = [];
        if (file_exists($timelineFile)) {
            $lines = file($timelineFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach (array_slice($lines, 0, 500) as $line) { // First 500 entries
                $parts = str_getcsv($line);
                if (count($parts) >= 4) {
                    $timeline[] = [
                        'date' => $parts[0] ?? '',
                        'size' => $parts[1] ?? '',
                        'type' => $parts[2] ?? '',
                        'mode' => $parts[3] ?? '',
                        'file' => $parts[8] ?? ''
                    ];
                }
            }

            @unlink($timelineFile);
        }

        @unlink($bodyFile);

        return [
            'entries_count' => count($timeline),
            'entries' => $timeline
        ];
    }

    /**
     * Get file system statistics
     */
    private function getFileSystemStats(string $imagePath): array
    {
        $cmd = sprintf('fsstat %s 2>&1', escapeshellarg($imagePath));
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            return ['error' => 'Failed to get file system stats'];
        }

        $stats = [];
        $currentSection = '';

        foreach ($output as $line) {
            if (empty(trim($line))) {
                continue;
            }

            if (str_starts_with($line, 'FILE SYSTEM INFORMATION')) {
                $currentSection = 'general';
                continue;
            }

            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $stats[trim($key)] = trim($value);
            }
        }

        return $stats;
    }

    /**
     * Get partition layout
     */
    private function getPartitions(string $imagePath): array
    {
        $cmd = sprintf('mmls %s 2>&1', escapeshellarg($imagePath));
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            return ['error' => 'No partition table found or unsupported format'];
        }

        $partitions = [];

        foreach ($output as $line) {
            // Parse mmls output: slot, start, end, length, description
            if (preg_match('/^\s*(\d+):\s+(\d+)\s+(\d+)\s+(\d+)\s+(.+)$/', $line, $matches)) {
                $partitions[] = [
                    'slot' => $matches[1],
                    'start' => $matches[2],
                    'end' => $matches[3],
                    'length' => $matches[4],
                    'description' => trim($matches[5])
                ];
            }
        }

        return [
            'count' => count($partitions),
            'partitions' => $partitions
        ];
    }

    /**
     * Find image file by analysis ID
     */
    private function findImageFile(string $analysisId): ?string
    {
        $pattern = self::UPLOAD_DIR . $analysisId . '.*';
        $files = glob($pattern);

        return $files[0] ?? null;
    }

    /**
     * Clean up old analysis files
     */
    public function cleanup(): void
    {
        $auth = Auth::requireAuth();

        if (!$auth['is_admin']) {
            Response::error('Admin access required', 403);
            return;
        }

        try {
            $cleaned = 0;
            $maxAge = 7 * 24 * 60 * 60; // 7 days

            // Clean uploads
            if (is_dir(self::UPLOAD_DIR)) {
                $files = glob(self::UPLOAD_DIR . '*');
                foreach ($files as $file) {
                    if (is_file($file) && (time() - filemtime($file)) > $maxAge) {
                        unlink($file);
                        $cleaned++;
                    }
                }
            }

            // Clean results
            if (is_dir(self::RESULTS_DIR)) {
                $dirs = glob(self::RESULTS_DIR . '*', GLOB_ONLYDIR);
                foreach ($dirs as $dir) {
                    if ((time() - filemtime($dir)) > $maxAge) {
                        $this->rmdirRecursive($dir);
                        $cleaned++;
                    }
                }
            }

            Response::success('Cleanup completed', [
                'files_cleaned' => $cleaned
            ]);

        } catch (\Exception $e) {
            Response::error('Cleanup failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove directory recursively
     */
    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                $this->rmdirRecursive($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
