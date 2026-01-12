<?php

namespace App\Controllers;

use App\Utils\Auth;
use App\Utils\Database;
use App\Utils\RateLimit;
use App\Utils\Logger;

/**
 * Threat Intelligence Controller
 *
 * Provides threat/artifact intelligence APIs including:
 * - Malware & threat lookup (VirusTotal, MalwareBazaar, etc.)
 * - Threat ensemble fingerprints (packer detection, entropy, URLs)
 * - YARA rules integration
 * - IOC (Indicators of Compromise) feeds
 */
class ThreatIntelController
{
    private $db;
    private $auth;
    private $rateLimit;
    private $logger;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
        $this->rateLimit = new RateLimit();
        $this->logger = new Logger('ThreatIntel');
    }

    /**
     * Lookup file hash or artifact against threat intelligence databases
     *
     * POST /api/v1/threat-intel/lookup
     * Body: {
     *   "hash": "sha256_hash_here",
     *   "sources": ["virustotal", "malwarebazaar", "hybridanalysis"],
     *   "include_metadata": true
     * }
     */
    public function lookup()
    {
        try {
            // Auth check
            $user = $this->auth->getUserFromToken();
            if (!$user && !$this->rateLimit->checkAnonymous('threat_intel_lookup')) {
                http_response_code(429);
                echo json_encode(['error' => 'Rate limit exceeded']);
                return;
            }

            // Get input
            $input = json_decode(file_get_contents('php://input'), true);
            $hash = $input['hash'] ?? null;
            $sources = $input['sources'] ?? ['virustotal', 'malwarebazaar'];
            $includeMetadata = $input['include_metadata'] ?? true;

            if (!$hash) {
                http_response_code(400);
                echo json_encode(['error' => 'Hash is required']);
                return;
            }

            // Validate hash format
            if (!$this->validateHash($hash)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid hash format']);
                return;
            }

            // Check cache first
            $cached = $this->getCachedLookup($hash);
            if ($cached) {
                echo json_encode([
                    'hash' => $hash,
                    'cached' => true,
                    'results' => $cached
                ]);
                return;
            }

            // Query threat intelligence sources
            $results = [];
            foreach ($sources as $source) {
                $results[$source] = $this->queryThreatSource($source, $hash, $includeMetadata);
            }

            // Calculate threat score
            $threatScore = $this->calculateThreatScore($results);

            // Save to database
            $this->saveLookupResult($user['id'] ?? null, $hash, $results, $threatScore);

            // Cache results
            $this->cacheLookupResult($hash, $results);

            echo json_encode([
                'hash' => $hash,
                'threat_score' => $threatScore,
                'is_malicious' => $threatScore > 50,
                'confidence' => $this->calculateConfidence($results),
                'results' => $results,
                'timestamp' => date('c')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Threat lookup failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'Lookup failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Get threat ensemble fingerprints for a file
     *
     * POST /api/v1/threat-intel/fingerprint
     * Body: {
     *   "file_path": "/path/to/file",
     *   "include_entropy": true,
     *   "include_strings": true,
     *   "include_packers": true
     * }
     */
    public function fingerprint()
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
            $includeEntropy = $input['include_entropy'] ?? true;
            $includeStrings = $input['include_strings'] ?? true;
            $includePackers = $input['include_packers'] ?? true;

            if (!$filePath || !file_exists($filePath)) {
                http_response_code(400);
                echo json_encode(['error' => 'Valid file path required']);
                return;
            }

            $fingerprint = [];

            // Basic file info
            $fingerprint['file_size'] = filesize($filePath);
            $fingerprint['file_type'] = mime_content_type($filePath);
            $fingerprint['hashes'] = [
                'md5' => md5_file($filePath),
                'sha1' => sha1_file($filePath),
                'sha256' => hash_file('sha256', $filePath)
            ];

            // Entropy analysis
            if ($includeEntropy) {
                $fingerprint['entropy'] = $this->calculateFileEntropy($filePath);
                $fingerprint['entropy_anomaly'] = $fingerprint['entropy'] > 7.5; // High entropy = possibly encrypted/packed
            }

            // Packer detection
            if ($includePackers) {
                $fingerprint['packers'] = $this->detectPackers($filePath);
            }

            // Extract strings and URLs
            if ($includeStrings) {
                $fingerprint['suspicious_strings'] = $this->extractSuspiciousStrings($filePath);
                $fingerprint['embedded_urls'] = $this->extractURLs($filePath);
                $fingerprint['embedded_ips'] = $this->extractIPs($filePath);
            }

            // PE/ELF headers (if applicable)
            $fingerprint['pe_info'] = $this->extractPEInfo($filePath);

            echo json_encode([
                'fingerprint' => $fingerprint,
                'risk_indicators' => $this->assessRiskIndicators($fingerprint),
                'timestamp' => date('c')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Fingerprint generation failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'Fingerprinting failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Scan file with YARA rules
     *
     * POST /api/v1/threat-intel/yara-scan
     * Body: {
     *   "file_path": "/path/to/file",
     *   "rules": ["malware_signatures", "apt_detection"],
     *   "custom_rules": "optional custom yara rules text"
     * }
     */
    public function yaraScan()
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
            $rulesets = $input['rules'] ?? ['default'];
            $customRules = $input['custom_rules'] ?? null;

            if (!$filePath || !file_exists($filePath)) {
                http_response_code(400);
                echo json_encode(['error' => 'Valid file path required']);
                return;
            }

            // Execute YARA scan
            $matches = $this->executeYARAScan($filePath, $rulesets, $customRules);

            // Save scan results
            $this->saveScanResult($user['id'], $filePath, $matches);

            echo json_encode([
                'file' => basename($filePath),
                'matched_rules' => count($matches),
                'matches' => $matches,
                'is_malicious' => count($matches) > 0,
                'timestamp' => date('c')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('YARA scan failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'YARA scan failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Get IOC (Indicators of Compromise) feeds
     *
     * GET /api/v1/threat-intel/ioc-feed
     * Query params: ?type=ip|domain|hash&since=2026-01-01
     */
    public function iocFeed()
    {
        try {
            $user = $this->auth->getUserFromToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $type = $_GET['type'] ?? 'all';
            $since = $_GET['since'] ?? date('Y-m-d', strtotime('-7 days'));

            // Query IOC database
            $iocs = $this->getIOCs($type, $since);

            echo json_encode([
                'feed_type' => $type,
                'since' => $since,
                'total_iocs' => count($iocs),
                'iocs' => $iocs,
                'updated_at' => date('c')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('IOC feed failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'IOC feed failed: ' . $e->getMessage()]);
        }
    }

    // ===== PRIVATE HELPER METHODS =====

    private function validateHash($hash)
    {
        // MD5 (32), SHA1 (40), SHA256 (64)
        $length = strlen($hash);
        return in_array($length, [32, 40, 64]) && ctype_xdigit($hash);
    }

    private function queryThreatSource($source, $hash, $includeMetadata)
    {
        // Placeholder - integrate with real APIs
        switch ($source) {
            case 'virustotal':
                return $this->queryVirusTotal($hash, $includeMetadata);
            case 'malwarebazaar':
                return $this->queryMalwareBazaar($hash);
            case 'hybridanalysis':
                return $this->queryHybridAnalysis($hash);
            default:
                return ['error' => 'Unknown source: ' . $source];
        }
    }

    private function queryVirusTotal($hash, $includeMetadata)
    {
        // TODO: Implement actual VirusTotal API integration
        // For now, return simulated data
        return [
            'detected' => rand(0, 70),
            'total' => 70,
            'scan_date' => date('Y-m-d H:i:s'),
            'permalink' => 'https://virustotal.com/file/' . $hash
        ];
    }

    private function queryMalwareBazaar($hash)
    {
        // TODO: Implement MalwareBazaar API
        return [
            'found' => rand(0, 1) === 1,
            'family' => 'Unknown',
            'tags' => []
        ];
    }

    private function queryHybridAnalysis($hash)
    {
        // TODO: Implement Hybrid Analysis API
        return [
            'verdict' => 'unknown',
            'threat_score' => rand(0, 100)
        ];
    }

    private function calculateThreatScore($results)
    {
        $score = 0;
        $count = 0;

        foreach ($results as $source => $result) {
            if (isset($result['detected']) && isset($result['total'])) {
                $score += ($result['detected'] / $result['total']) * 100;
                $count++;
            }
            if (isset($result['threat_score'])) {
                $score += $result['threat_score'];
                $count++;
            }
        }

        return $count > 0 ? round($score / $count, 2) : 0;
    }

    private function calculateConfidence($results)
    {
        // Simple confidence based on number of sources
        return min(count($results) * 25, 100);
    }

    private function calculateFileEntropy($filePath)
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

    private function detectPackers($filePath)
    {
        // TODO: Implement real packer detection
        return ['UPX', 'Themida', 'VMProtect']; // Placeholder
    }

    private function extractSuspiciousStrings($filePath)
    {
        // TODO: Implement string extraction and filtering
        return [];
    }

    private function extractURLs($filePath)
    {
        $data = file_get_contents($filePath);
        preg_match_all('/https?:\/\/[^\s]+/', $data, $matches);
        return array_unique($matches[0]);
    }

    private function extractIPs($filePath)
    {
        $data = file_get_contents($filePath);
        preg_match_all('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', $data, $matches);
        return array_unique($matches[0]);
    }

    private function extractPEInfo($filePath)
    {
        // TODO: Implement PE header parsing
        return null;
    }

    private function assessRiskIndicators($fingerprint)
    {
        $risks = [];

        if (isset($fingerprint['entropy']) && $fingerprint['entropy'] > 7.5) {
            $risks[] = 'High entropy detected - possibly encrypted or packed';
        }

        if (isset($fingerprint['packers']) && count($fingerprint['packers']) > 0) {
            $risks[] = 'Packer detected: ' . implode(', ', $fingerprint['packers']);
        }

        if (isset($fingerprint['embedded_urls']) && count($fingerprint['embedded_urls']) > 0) {
            $risks[] = 'Embedded URLs found: ' . count($fingerprint['embedded_urls']);
        }

        return $risks;
    }

    private function executeYARAScan($filePath, $rulesets, $customRules)
    {
        // TODO: Implement actual YARA scanning
        // For now, return simulated matches
        return [];
    }

    private function getIOCs($type, $since)
    {
        // TODO: Query IOC database
        return [];
    }

    private function getCachedLookup($hash)
    {
        // TODO: Implement Redis cache lookup
        return null;
    }

    private function cacheLookupResult($hash, $results)
    {
        // TODO: Cache to Redis with 24h TTL
    }

    private function saveLookupResult($userId, $hash, $results, $threatScore)
    {
        $this->db->insert('threat_lookups', [
            'user_id' => $userId,
            'hash' => $hash,
            'results' => json_encode($results),
            'threat_score' => $threatScore,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function saveScanResult($userId, $filePath, $matches)
    {
        $this->db->insert('yara_scans', [
            'user_id' => $userId,
            'file_path' => $filePath,
            'matches' => json_encode($matches),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
