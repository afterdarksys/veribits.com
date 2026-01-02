<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;

class PasswordRecoveryController
{
    /**
     * Remove password from file (when password is known)
     */
    public function removePassword(): void
    {
        $auth = Auth::optionalAuth();

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

        if (empty($password)) {
            Response::error('Password is required', 400);
            return;
        }

        // Validate file size (max 50MB)
        if ($file['size'] > 50 * 1024 * 1024) {
            Response::error('File too large (max 50MB)', 400);
            return;
        }

        $tmpFile = $file['tmp_name'];
        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        try {
            $result = null;

            switch ($extension) {
                case 'pdf':
                    $result = $this->removePdfPassword($tmpFile, $password);
                    break;

                case 'docx':
                case 'xlsx':
                case 'pptx':
                    $result = $this->removeOfficePassword($tmpFile, $password, $extension);
                    break;

                case 'zip':
                    $result = $this->removeZipPassword($tmpFile, $password);
                    break;

                default:
                    Response::error('Unsupported file type. Supported: PDF, DOCX, XLSX, PPTX, ZIP', 400);
                    return;
            }

            if (!$result['success']) {
                Response::error($result['message'], 400);
                return;
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            // Return the decrypted file
            $unlockedFile = $result['file'];
            $downloadName = 'unlocked_' . $originalName;

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $downloadName . '"');
            header('Content-Length: ' . filesize($unlockedFile));

            readfile($unlockedFile);
            @unlink($unlockedFile);
            exit;

        } catch (\Exception $e) {
            Logger::error('Password removal failed', [
                'error' => $e->getMessage(),
                'file' => $originalName
            ]);
            Response::error('Password removal failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Attempt to crack password (brute force/dictionary)
     */
    public function crackPassword(): void
    {
        $auth = Auth::optionalAuth();

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
        $method = $_POST['method'] ?? 'dictionary'; // dictionary or brute
        $maxAttempts = min((int)($_POST['max_attempts'] ?? 1000), 10000);
        $wordlistType = $_POST['wordlist'] ?? 'common'; // common, rockyou, custom

        // Validate file size (max 50MB)
        if ($file['size'] > 50 * 1024 * 1024) {
            Response::error('File too large (max 50MB)', 400);
            return;
        }

        $tmpFile = $file['tmp_name'];
        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        try {
            $result = null;

            switch ($extension) {
                case 'pdf':
                    $result = $this->crackPdfPassword($tmpFile, $method, $maxAttempts, $wordlistType);
                    break;

                case 'docx':
                case 'xlsx':
                case 'pptx':
                    $result = $this->crackOfficePassword($tmpFile, $method, $maxAttempts, $wordlistType, $extension);
                    break;

                case 'zip':
                    $result = $this->crackZipPassword($tmpFile, $method, $maxAttempts, $wordlistType);
                    break;

                default:
                    Response::error('Unsupported file type. Supported: PDF, DOCX, XLSX, PPTX, ZIP', 400);
                    return;
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('Password cracking completed', $result);

        } catch (\Exception $e) {
            Logger::error('Password cracking failed', [
                'error' => $e->getMessage(),
                'file' => $originalName
            ]);
            Response::error('Password cracking failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get file metadata and password protection info
     */
    public function analyzeFile(): void
    {
        $auth = Auth::optionalAuth();

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
        $tmpFile = $file['tmp_name'];
        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        try {
            $analysis = [
                'filename' => $originalName,
                'size' => $file['size'],
                'type' => $extension,
                'is_encrypted' => false,
                'encryption_type' => null,
                'metadata' => []
            ];

            switch ($extension) {
                case 'pdf':
                    $analysis = array_merge($analysis, $this->analyzePdf($tmpFile));
                    break;

                case 'docx':
                case 'xlsx':
                case 'pptx':
                    $analysis = array_merge($analysis, $this->analyzeOffice($tmpFile, $extension));
                    break;

                case 'zip':
                    $analysis = array_merge($analysis, $this->analyzeZip($tmpFile));
                    break;

                default:
                    Response::error('Unsupported file type', 400);
                    return;
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('File analysis completed', $analysis);

        } catch (\Exception $e) {
            Response::error('File analysis failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove password from PDF using pikepdf or qpdf
     */
    private function removePdfPassword(string $file, string $password): array
    {
        $output = tempnam(sys_get_temp_dir(), 'pdf_unlocked_');

        // Try pikepdf first (if Python is available)
        $pythonScript = <<<'PYTHON'
import sys
import pikepdf

try:
    pdf = pikepdf.open(sys.argv[1], password=sys.argv[2])
    pdf.save(sys.argv[3])
    print("SUCCESS")
except Exception as e:
    print(f"ERROR: {str(e)}")
    sys.exit(1)
PYTHON;

        $scriptFile = tempnam(sys_get_temp_dir(), 'pdf_script_') . '.py';
        file_put_contents($scriptFile, $pythonScript);

        $cmd = sprintf(
            'python3 %s %s %s %s 2>&1',
            escapeshellarg($scriptFile),
            escapeshellarg($file),
            escapeshellarg($password),
            escapeshellarg($output)
        );

        exec($cmd, $cmdOutput, $returnVar);
        @unlink($scriptFile);

        if ($returnVar === 0 && strpos(implode("\n", $cmdOutput), 'SUCCESS') !== false) {
            return ['success' => true, 'file' => $output];
        }

        // Fallback to qpdf if available
        $cmd = sprintf(
            'qpdf --decrypt --password=%s %s %s 2>&1',
            escapeshellarg($password),
            escapeshellarg($file),
            escapeshellarg($output)
        );

        exec($cmd, $cmdOutput, $returnVar);

        if ($returnVar === 0 && file_exists($output) && filesize($output) > 0) {
            return ['success' => true, 'file' => $output];
        }

        return ['success' => false, 'message' => 'Failed to remove PDF password. Incorrect password or unsupported encryption.'];
    }

    /**
     * Remove password from Office document using msoffcrypto-tool
     */
    private function removeOfficePassword(string $file, string $password, string $extension): array
    {
        $output = tempnam(sys_get_temp_dir(), 'office_unlocked_') . '.' . $extension;

        // Use msoffcrypto-tool
        $cmd = sprintf(
            'msoffcrypto-tool %s %s -p %s 2>&1',
            escapeshellarg($file),
            escapeshellarg($output),
            escapeshellarg($password)
        );

        exec($cmd, $cmdOutput, $returnVar);

        if ($returnVar === 0 && file_exists($output) && filesize($output) > 0) {
            return ['success' => true, 'file' => $output];
        }

        // Fallback: Try with Python script
        $pythonScript = <<<'PYTHON'
import sys
import msoffcrypto

try:
    with open(sys.argv[1], 'rb') as f:
        office_file = msoffcrypto.OfficeFile(f)
        office_file.load_key(password=sys.argv[2])

        with open(sys.argv[3], 'wb') as output:
            office_file.decrypt(output)

    print("SUCCESS")
except Exception as e:
    print(f"ERROR: {str(e)}")
    sys.exit(1)
PYTHON;

        $scriptFile = tempnam(sys_get_temp_dir(), 'office_script_') . '.py';
        file_put_contents($scriptFile, $pythonScript);

        $cmd = sprintf(
            'python3 %s %s %s %s 2>&1',
            escapeshellarg($scriptFile),
            escapeshellarg($file),
            escapeshellarg($password),
            escapeshellarg($output)
        );

        exec($cmd, $cmdOutput, $returnVar);
        @unlink($scriptFile);

        if ($returnVar === 0 && strpos(implode("\n", $cmdOutput), 'SUCCESS') !== false) {
            return ['success' => true, 'file' => $output];
        }

        return ['success' => false, 'message' => 'Failed to remove Office password. Incorrect password or unsupported encryption.'];
    }

    /**
     * Remove password from ZIP file
     */
    private function removeZipPassword(string $file, string $password): array
    {
        $output = tempnam(sys_get_temp_dir(), 'zip_unlocked_') . '.zip';

        // Extract and recompress without password
        $extractDir = sys_get_temp_dir() . '/zip_extract_' . uniqid();
        mkdir($extractDir);

        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            return ['success' => false, 'message' => 'Failed to open ZIP file'];
        }

        // Set password
        $zip->setPassword($password);

        // Extract all files
        $success = true;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $content = $zip->getFromIndex($i);

            if ($content === false) {
                $success = false;
                break;
            }

            $filepath = $extractDir . '/' . $stat['name'];
            $dir = dirname($filepath);

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (!str_ends_with($stat['name'], '/')) {
                file_put_contents($filepath, $content);
            }
        }

        $zip->close();

        if (!$success) {
            $this->rmdirRecursive($extractDir);
            return ['success' => false, 'message' => 'Incorrect password'];
        }

        // Create new ZIP without password
        $newZip = new \ZipArchive();
        if ($newZip->open($output, \ZipArchive::CREATE) !== true) {
            $this->rmdirRecursive($extractDir);
            return ['success' => false, 'message' => 'Failed to create new ZIP file'];
        }

        $this->addDirectoryToZip($newZip, $extractDir, '');
        $newZip->close();

        // Cleanup
        $this->rmdirRecursive($extractDir);

        return ['success' => true, 'file' => $output];
    }

    /**
     * Crack PDF password using dictionary or brute force
     */
    private function crackPdfPassword(string $file, string $method, int $maxAttempts, string $wordlistType): array
    {
        $passwords = $this->getPasswordList($wordlistType, $maxAttempts);
        $attempts = 0;
        $startTime = microtime(true);

        foreach ($passwords as $password) {
            $attempts++;

            // Try password
            $result = $this->removePdfPassword($file, $password);
            if ($result['success']) {
                @unlink($result['file']);
                return [
                    'found' => true,
                    'password' => $password,
                    'attempts' => $attempts,
                    'time_seconds' => round(microtime(true) - $startTime, 2)
                ];
            }

            if ($attempts >= $maxAttempts) {
                break;
            }
        }

        return [
            'found' => false,
            'attempts' => $attempts,
            'time_seconds' => round(microtime(true) - $startTime, 2),
            'message' => 'Password not found in ' . $attempts . ' attempts'
        ];
    }

    /**
     * Crack Office password
     */
    private function crackOfficePassword(string $file, string $method, int $maxAttempts, string $wordlistType, string $extension): array
    {
        $passwords = $this->getPasswordList($wordlistType, $maxAttempts);
        $attempts = 0;
        $startTime = microtime(true);

        foreach ($passwords as $password) {
            $attempts++;

            // Try password
            $result = $this->removeOfficePassword($file, $password, $extension);
            if ($result['success']) {
                @unlink($result['file']);
                return [
                    'found' => true,
                    'password' => $password,
                    'attempts' => $attempts,
                    'time_seconds' => round(microtime(true) - $startTime, 2)
                ];
            }

            if ($attempts >= $maxAttempts) {
                break;
            }
        }

        return [
            'found' => false,
            'attempts' => $attempts,
            'time_seconds' => round(microtime(true) - $startTime, 2),
            'message' => 'Password not found in ' . $attempts . ' attempts'
        ];
    }

    /**
     * Crack ZIP password
     */
    private function crackZipPassword(string $file, string $method, int $maxAttempts, string $wordlistType): array
    {
        $passwords = $this->getPasswordList($wordlistType, $maxAttempts);
        $attempts = 0;
        $startTime = microtime(true);

        foreach ($passwords as $password) {
            $attempts++;

            // Try password
            $result = $this->removeZipPassword($file, $password);
            if ($result['success']) {
                @unlink($result['file']);
                return [
                    'found' => true,
                    'password' => $password,
                    'attempts' => $attempts,
                    'time_seconds' => round(microtime(true) - $startTime, 2)
                ];
            }

            if ($attempts >= $maxAttempts) {
                break;
            }
        }

        return [
            'found' => false,
            'attempts' => $attempts,
            'time_seconds' => round(microtime(true) - $startTime, 2),
            'message' => 'Password not found in ' . $attempts . ' attempts'
        ];
    }

    /**
     * Get password list for dictionary attack
     */
    private function getPasswordList(string $type, int $limit): array
    {
        $passwords = [];

        switch ($type) {
            case 'common':
                // Top 100 most common passwords
                $passwords = [
                    '123456', 'password', '12345678', 'qwerty', '123456789',
                    '12345', '1234', '111111', '1234567', 'dragon',
                    '123123', 'baseball', 'iloveyou', 'trustno1', '1234567890',
                    'sunshine', 'master', 'welcome', 'shadow', 'ashley',
                    'football', 'jesus', 'michael', 'ninja', 'mustang',
                    'password1', 'admin', 'letmein', 'monkey', 'password123',
                    'abc123', 'batman', 'passw0rd', 'superman', 'qwerty123',
                    'administrator', 'root', 'test', 'guest', 'demo'
                ];
                break;

            case 'numeric':
                // Simple numeric passwords
                for ($i = 0; $i < min($limit, 10000); $i++) {
                    $passwords[] = str_pad((string)$i, 4, '0', STR_PAD_LEFT);
                }
                break;

            case 'alpha':
                // Simple alphabetic passwords (lowercase)
                $chars = 'abcdefghijklmnopqrstuvwxyz';
                for ($i = 0; $i < min($limit, 1000); $i++) {
                    $password = '';
                    for ($j = 0; $j < 4; $j++) {
                        $password .= $chars[rand(0, strlen($chars) - 1)];
                    }
                    $passwords[] = $password;
                }
                break;
        }

        return array_slice($passwords, 0, $limit);
    }

    /**
     * Analyze PDF file
     */
    private function analyzePdf(string $file): array
    {
        $analysis = [
            'is_encrypted' => false,
            'encryption_type' => null
        ];

        // Use pdfinfo or pikepdf to analyze
        $cmd = sprintf('pdfinfo %s 2>&1', escapeshellarg($file));
        exec($cmd, $output, $returnVar);

        $outputStr = implode("\n", $output);

        if (stripos($outputStr, 'encrypted: yes') !== false) {
            $analysis['is_encrypted'] = true;

            if (preg_match('/Encryption:\s+(.+)/i', $outputStr, $matches)) {
                $analysis['encryption_type'] = trim($matches[1]);
            }
        }

        return $analysis;
    }

    /**
     * Analyze Office file
     */
    private function analyzeOffice(string $file, string $extension): array
    {
        $analysis = [
            'is_encrypted' => false,
            'encryption_type' => 'Office'
        ];

        // Try to open as ZIP (Office files are ZIP archives)
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            // If can't open as ZIP, likely encrypted
            $analysis['is_encrypted'] = true;
            return $analysis;
        }

        // Check for EncryptionInfo or EncryptedPackage
        if ($zip->locateName('EncryptionInfo') !== false ||
            $zip->locateName('EncryptedPackage') !== false) {
            $analysis['is_encrypted'] = true;
        }

        $zip->close();
        return $analysis;
    }

    /**
     * Analyze ZIP file
     */
    private function analyzeZip(string $file): array
    {
        $analysis = [
            'is_encrypted' => false,
            'encryption_type' => null,
            'file_count' => 0
        ];

        $zip = new \ZipArchive();
        if ($zip->open($file) === true) {
            $analysis['file_count'] = $zip->numFiles;

            // Check if any file is encrypted
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat['encryption_method'] > 0) {
                    $analysis['is_encrypted'] = true;
                    $analysis['encryption_type'] = $this->getZipEncryptionType($stat['encryption_method']);
                    break;
                }
            }

            $zip->close();
        }

        return $analysis;
    }

    /**
     * Get ZIP encryption type name
     */
    private function getZipEncryptionType(int $method): string
    {
        $types = [
            0 => 'None',
            1 => 'ZipCrypto',
            99 => 'AES-256'
        ];

        return $types[$method] ?? "Unknown ($method)";
    }

    /**
     * Add directory to ZIP recursively
     */
    private function addDirectoryToZip(\ZipArchive $zip, string $dir, string $zipPath): void
    {
        $files = scandir($dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $dir . '/' . $file;
            $zipFilePath = $zipPath ? $zipPath . '/' . $file : $file;

            if (is_dir($fullPath)) {
                $zip->addEmptyDir($zipFilePath);
                $this->addDirectoryToZip($zip, $fullPath, $zipFilePath);
            } else {
                $zip->addFile($fullPath, $zipFilePath);
            }
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
