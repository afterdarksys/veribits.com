<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;

class HashLookupController
{
    /**
     * Lookup/decrypt a single hash
     */
    public function lookup(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $hash = trim($input['hash'] ?? '');
        $hashType = $input['hash_type'] ?? 'auto';

        if (empty($hash)) {
            Response::error('Hash value is required', 400);
            return;
        }

        // Validate hash format
        if (!preg_match('/^[a-fA-F0-9]+$/', $hash)) {
            Response::error('Invalid hash format. Must be hexadecimal.', 400);
            return;
        }

        try {
            // Auto-detect hash type if not specified
            if ($hashType === 'auto') {
                $hashType = $this->identifyHashType($hash);
            }

            // Query multiple sources
            $results = $this->queryHashDatabases($hash, $hashType);

            // Count successful results
            $found = false;
            $plaintext = null;
            $sources = [];

            foreach ($results as $result) {
                if ($result['found']) {
                    $found = true;
                    $plaintext = $result['plaintext'];

                    // Cache successful lookups (skip if already from cache)
                    if ($result['source'] !== 'Local Cache') {
                        $this->cacheHashResult($hash, $hashType, $plaintext, $result['source']);
                    }
                }
                $sources[] = $result;
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('Hash lookup completed', [
                'hash' => $hash,
                'hash_type' => $hashType,
                'found' => $found,
                'plaintext' => $plaintext,
                'sources' => $sources,
                'sources_queried' => count($sources),
                'sources_found' => count(array_filter($sources, fn($s) => $s['found']))
            ]);

        } catch (\Exception $e) {
            Logger::error('Hash lookup failed', [
                'error' => $e->getMessage(),
                'hash' => substr($hash, 0, 16) . '...'
            ]);
            Response::error('Hash lookup failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Batch lookup multiple hashes
     */
    public function batchLookup(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            Response::error('Authentication required for batch lookups', 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $hashes = $input['hashes'] ?? [];

        if (empty($hashes) || !is_array($hashes)) {
            Response::error('Hashes array is required', 400);
            return;
        }

        // Limit batch size
        $maxBatchSize = 25;
        if (count($hashes) > $maxBatchSize) {
            Response::error("Maximum $maxBatchSize hashes allowed per batch", 400);
            return;
        }

        try {
            $results = [];

            foreach ($hashes as $hash) {
                $hash = trim($hash);

                if (empty($hash)) {
                    continue;
                }

                $hashType = $this->identifyHashType($hash);
                $lookupResults = $this->queryHashDatabases($hash, $hashType);

                $found = false;
                $plaintext = null;

                foreach ($lookupResults as $result) {
                    if ($result['found']) {
                        $found = true;
                        $plaintext = $result['plaintext'];
                        break;
                    }
                }

                $results[] = [
                    'hash' => $hash,
                    'hash_type' => $hashType,
                    'found' => $found,
                    'plaintext' => $plaintext
                ];
            }

            Response::success('Batch lookup completed', [
                'total' => count($results),
                'found' => count(array_filter($results, fn($r) => $r['found'])),
                'not_found' => count(array_filter($results, fn($r) => !$r['found'])),
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Response::error('Batch lookup failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Identify hash type by length and pattern
     */
    public function identifyHash(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $hash = trim($input['hash'] ?? '');

        if (empty($hash)) {
            Response::error('Hash value is required', 400);
            return;
        }

        $hashType = $this->identifyHashType($hash);
        $length = strlen($hash);

        $possibleTypes = $this->getPossibleHashTypes($hash);

        Response::success('Hash identified', [
            'hash' => $hash,
            'length' => $length,
            'most_likely' => $hashType,
            'possible_types' => $possibleTypes
        ]);
    }

    /**
     * Query multiple hash databases and aggregate results
     */
    private function queryHashDatabases(string $hash, string $hashType): array
    {
        $results = [];

        // Query md5decrypt.net (if MD5)
        if ($hashType === 'md5') {
            $results[] = $this->queryMD5Decrypt($hash);
        }

        // Query hashkiller.io
        $results[] = $this->queryHashKiller($hash, $hashType);

        // Query local cache (future enhancement)
        $results[] = $this->queryLocalCache($hash);

        // Query md5online.org (if MD5)
        if ($hashType === 'md5') {
            $results[] = $this->queryMD5Online($hash);
        }

        // Query sha1decrypt (if SHA1)
        if ($hashType === 'sha1') {
            $results[] = $this->querySHA1Decrypt($hash);
        }

        return $results;
    }

    /**
     * Query md5decrypt.net API
     */
    private function queryMD5Decrypt(string $hash): array
    {
        $source = 'md5decrypt.net';

        try {
            $url = "https://md5decrypt.net/Api/api.asp?hash={$hash}&hash_type=md5&email=noreply@veribits.com&code=YOUR_API_KEY";

            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'user_agent' => 'VeriBits/1.0'
                ]
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response && $response !== '' && !str_contains($response, 'NOT FOUND')) {
                return [
                    'source' => $source,
                    'found' => true,
                    'plaintext' => trim($response),
                    'response_time' => 'N/A'
                ];
            }

        } catch (\Exception $e) {
            // Silent fail
        }

        return [
            'source' => $source,
            'found' => false,
            'plaintext' => null,
            'error' => 'Not found or API error'
        ];
    }

    /**
     * Query hashkiller.io database
     */
    private function queryHashKiller(string $hash, string $hashType): array
    {
        $source = 'hashkiller.io';

        try {
            // Note: HashKiller doesn't have a free API, this is a placeholder
            // You would need to implement web scraping or use their API if available

            return [
                'source' => $source,
                'found' => false,
                'plaintext' => null,
                'note' => 'API not available'
            ];

        } catch (\Exception $e) {
            return [
                'source' => $source,
                'found' => false,
                'plaintext' => null,
                'error' => 'API error'
            ];
        }
    }

    /**
     * Query local cache database
     */
    private function queryLocalCache(string $hash): array
    {
        $source = 'Local Cache';

        try {
            $pdo = \VeriBits\Utils\Database::getConnection();

            // Query the cache
            $stmt = $pdo->prepare("
                SELECT plaintext, source, hit_count
                FROM hash_lookup_cache
                WHERE hash = :hash
                LIMIT 1
            ");
            $stmt->execute(['hash' => strtolower($hash)]);
            $cached = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($cached) {
                // Update hit count and last accessed time
                $updateStmt = $pdo->prepare("
                    UPDATE hash_lookup_cache
                    SET hit_count = hit_count + 1,
                        last_accessed_at = NOW()
                    WHERE hash = :hash
                ");
                $updateStmt->execute(['hash' => strtolower($hash)]);

                return [
                    'source' => $source,
                    'found' => true,
                    'plaintext' => $cached['plaintext'],
                    'original_source' => $cached['source'],
                    'cache_hits' => $cached['hit_count'] + 1
                ];
            }

        } catch (\Exception $e) {
            Logger::warning('Hash cache query failed', ['error' => $e->getMessage()]);
        }

        return [
            'source' => $source,
            'found' => false,
            'plaintext' => null
        ];
    }

    /**
     * Store a successful lookup in the cache
     */
    private function cacheHashResult(string $hash, string $hashType, string $plaintext, string $source): void
    {
        try {
            $pdo = \VeriBits\Utils\Database::getConnection();

            // Use INSERT ... ON CONFLICT to handle duplicates
            $stmt = $pdo->prepare("
                INSERT INTO hash_lookup_cache (hash, hash_type, plaintext, source)
                VALUES (:hash, :hash_type, :plaintext, :source)
                ON CONFLICT (hash, hash_type)
                DO UPDATE SET
                    hit_count = hash_lookup_cache.hit_count + 1,
                    last_accessed_at = NOW()
            ");
            $stmt->execute([
                'hash' => strtolower($hash),
                'hash_type' => $hashType,
                'plaintext' => $plaintext,
                'source' => $source
            ]);

            Logger::debug('Hash cached successfully', [
                'hash_prefix' => substr($hash, 0, 8) . '...',
                'type' => $hashType
            ]);

        } catch (\Exception $e) {
            // Silently fail - caching is non-critical
            Logger::warning('Failed to cache hash', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Query md5online.org
     */
    private function queryMD5Online(string $hash): array
    {
        $source = 'md5online.org';

        try {
            $url = "https://www.md5online.org/md5-decrypt.html";

            // Note: This site requires POST request and may have CAPTCHA
            // This is a simplified implementation

            return [
                'source' => $source,
                'found' => false,
                'plaintext' => null,
                'note' => 'Requires CAPTCHA verification'
            ];

        } catch (\Exception $e) {
            return [
                'source' => $source,
                'found' => false,
                'plaintext' => null,
                'error' => 'API error'
            ];
        }
    }

    /**
     * Query sha1decrypt.com
     */
    private function querySHA1Decrypt(string $hash): array
    {
        $source = 'sha1decrypt.com';

        try {
            // Similar implementation to MD5Decrypt
            // Note: May require API key

            return [
                'source' => $source,
                'found' => false,
                'plaintext' => null,
                'note' => 'API implementation pending'
            ];

        } catch (\Exception $e) {
            return [
                'source' => $source,
                'found' => false,
                'plaintext' => null,
                'error' => 'API error'
            ];
        }
    }

    /**
     * Identify hash type by length
     */
    private function identifyHashType(string $hash): string
    {
        $length = strlen($hash);

        $typeMap = [
            32 => 'md5',
            40 => 'sha1',
            64 => 'sha256',
            96 => 'sha384',
            128 => 'sha512',
            16 => 'mysql323',
            56 => 'sha224'
        ];

        return $typeMap[$length] ?? 'unknown';
    }

    /**
     * Get all possible hash types for a given hash
     */
    private function getPossibleHashTypes(string $hash): array
    {
        $length = strlen($hash);
        $types = [];

        switch ($length) {
            case 32:
                $types = ['MD5', 'MD4', 'MD2', 'NTLM', 'LM', 'RAdmin v2.x'];
                break;
            case 40:
                $types = ['SHA-1', 'MySQL5.x', 'Tiger-160', 'RIPEMD-160', 'HAS-160'];
                break;
            case 64:
                $types = ['SHA-256', 'SHA3-256', 'BLAKE2s-256', 'GOST R 34.11-94', 'RIPEMD-256'];
                break;
            case 96:
                $types = ['SHA-384', 'SHA3-384'];
                break;
            case 128:
                $types = ['SHA-512', 'SHA3-512', 'BLAKE2b-512', 'Whirlpool'];
                break;
            case 16:
                $types = ['MySQL 3.2.3', 'CRC-64'];
                break;
            case 56:
                $types = ['SHA-224', 'SHA3-224'];
                break;
            case 8:
                $types = ['CRC-32', 'Adler-32'];
                break;
            default:
                $types = ['Unknown'];
        }

        return $types;
    }

    /**
     * Extract emails from text
     */
    public function extractEmails(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $text = $input['text'] ?? '';

        if (empty($text)) {
            Response::error('Text is required', 400);
            return;
        }

        // Extract emails using regex
        $pattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
        preg_match_all($pattern, $text, $matches);

        $emails = array_unique($matches[0]);
        $emails = array_values($emails); // Re-index array

        // Validate emails
        $validEmails = [];
        $invalidEmails = [];

        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validEmails[] = $email;
            } else {
                $invalidEmails[] = $email;
            }
        }

        if (!$auth['authenticated']) {
            RateLimit::incrementAnonymousScan($auth['ip_address']);
        }

        Response::success('Email extraction completed', [
            'total_found' => count($emails),
            'valid_emails' => $validEmails,
            'invalid_emails' => $invalidEmails,
            'valid_count' => count($validEmails),
            'invalid_count' => count($invalidEmails)
        ]);
    }
}
