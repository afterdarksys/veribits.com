<?php
/**
 * Batch Controller
 *
 * Execute multiple API operations in a single request
 * Supports up to 100 operations per batch
 */
namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\Logger;
use VeriBits\Utils\RateLimit;

class BatchController {

    private const MAX_OPERATIONS = 100;

    /**
     * Allowed endpoints for batch operations
     * Only safe, read-heavy or idempotent operations allowed
     */
    private const ALLOWED_ENDPOINTS = [
        // Verification
        'POST /api/v1/verify/file',
        'POST /api/v1/verify/dns',
        'POST /api/v1/verify/ssl/website',

        // DNS Tools
        'POST /api/v1/dns/check',
        'POST /api/v1/tools/dns-validate',
        'POST /api/v1/tools/dnssec-validate',
        'POST /api/v1/tools/dns-propagation',
        'POST /api/v1/tools/reverse-dns',

        // SSL Tools
        'POST /api/v1/ssl/validate',
        'POST /api/v1/ssl/validate-csr',
        'POST /api/v1/ssl/resolve-chain',

        // Developer Tools
        'POST /api/v1/tools/generate-hash',
        'POST /api/v1/tools/regex-test',
        'POST /api/v1/tools/validate-data',
        'POST /api/v1/tools/url-encode',
        'POST /api/v1/tools/base64-encoder',
        'POST /api/v1/jwt/decode',

        // Network Tools
        'POST /api/v1/tools/ip-calculate',
        'POST /api/v1/tools/rbl-check',
        'POST /api/v1/tools/whois',

        // Security Tools
        'POST /api/v1/tools/scan-secrets',
        'POST /api/v1/security/iam-policy/analyze',
        'POST /api/v1/crypto/validate',

        // Hash Lookup
        'POST /api/v1/tools/hash-lookup',
        'POST /api/v1/tools/hash-lookup/identify',

        // Email Tools
        'POST /api/v1/email/check-disposable',
        'POST /api/v1/email/analyze-spf',
        'POST /api/v1/email/analyze-mx',

        // BGP Tools
        'POST /api/v1/bgp/prefix',
        'POST /api/v1/bgp/asn',
    ];

    /**
     * Execute batch operations
     * POST /api/v1/batch
     */
    public function execute(): void {
        // Require authentication
        $user = Auth::requireBearer();
        if (!$user) {
            return; // Auth already sent error response
        }

        $userId = $user['sub'] ?? null;

        // Rate limit batch requests more strictly
        if (!RateLimit::checkUserQuota($userId, 'batch')) {
            Response::error('Batch rate limit exceeded', 429);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $operations = $body['operations'] ?? [];

        if (empty($operations)) {
            Response::error('No operations provided', 400);
            return;
        }

        if (count($operations) > self::MAX_OPERATIONS) {
            Response::error('Maximum ' . self::MAX_OPERATIONS . ' operations per batch', 400);
            return;
        }

        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($operations as $index => $operation) {
            $opId = $operation['id'] ?? (string)$index;
            $method = strtoupper($operation['method'] ?? 'POST');
            $path = $operation['path'] ?? '';
            $opBody = $operation['body'] ?? [];

            // Validate operation
            $endpointKey = "$method $path";
            if (!in_array($endpointKey, self::ALLOWED_ENDPOINTS)) {
                $results[] = [
                    'id' => $opId,
                    'status' => 400,
                    'error' => 'Endpoint not allowed in batch: ' . $path
                ];
                $errorCount++;
                continue;
            }

            try {
                // Execute the operation
                $result = $this->executeOperation($method, $path, $opBody, $user);
                $results[] = [
                    'id' => $opId,
                    'status' => 200,
                    'body' => $result
                ];
                $successCount++;
            } catch (\Exception $e) {
                $results[] = [
                    'id' => $opId,
                    'status' => 500,
                    'error' => $e->getMessage()
                ];
                $errorCount++;

                Logger::error('Batch operation failed', [
                    'operation_id' => $opId,
                    'path' => $path,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Logger::info('Batch request completed', [
            'user_id' => $userId,
            'total' => count($operations),
            'success' => $successCount,
            'errors' => $errorCount
        ]);

        Response::success([
            'total' => count($operations),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results
        ]);
    }

    /**
     * Execute a single operation within the batch
     */
    private function executeOperation(string $method, string $path, array $body, array $user): array {
        // Map paths to controller methods
        $routes = [
            '/api/v1/dns/check' => ['VeriBits\\Controllers\\DNSCheckController', 'checkInternal'],
            '/api/v1/ssl/validate' => ['VeriBits\\Controllers\\SSLCheckController', 'validateInternal'],
            '/api/v1/tools/generate-hash' => ['VeriBits\\Controllers\\DeveloperToolsController', 'generateHashInternal'],
            '/api/v1/tools/regex-test' => ['VeriBits\\Controllers\\DeveloperToolsController', 'regexTestInternal'],
            '/api/v1/jwt/decode' => ['VeriBits\\Controllers\\JWTController', 'decodeInternal'],
            '/api/v1/tools/hash-lookup' => ['VeriBits\\Controllers\\HashLookupController', 'lookupInternal'],
            '/api/v1/tools/ip-calculate' => ['VeriBits\\Controllers\\NetworkToolsController', 'ipCalculateInternal'],
            '/api/v1/email/check-disposable' => ['VeriBits\\Controllers\\EmailVerificationController', 'checkDisposableInternal'],
            '/api/v1/crypto/validate' => ['VeriBits\\Controllers\\CryptoValidationController', 'validateInternal'],
        ];

        // For now, use a simplified internal execution
        // In production, this would call actual controller methods

        // DNS check example
        if ($path === '/api/v1/dns/check') {
            $domain = $body['domain'] ?? '';
            $type = $body['record_type'] ?? 'A';

            if (empty($domain)) {
                throw new \InvalidArgumentException('Domain required');
            }

            $records = dns_get_record($domain, $this->getDnsType($type));
            return [
                'domain' => $domain,
                'record_type' => $type,
                'records' => $records ?: []
            ];
        }

        // Hash generation
        if ($path === '/api/v1/tools/generate-hash') {
            $input = $body['input'] ?? '';
            $algorithm = $body['algorithm'] ?? 'sha256';

            if (empty($input)) {
                throw new \InvalidArgumentException('Input required');
            }

            return [
                'hash' => hash($algorithm, $input),
                'algorithm' => $algorithm
            ];
        }

        // IP calculation
        if ($path === '/api/v1/tools/ip-calculate') {
            $ip = $body['ip'] ?? $body['cidr'] ?? '';

            if (empty($ip)) {
                throw new \InvalidArgumentException('IP or CIDR required');
            }

            // Simple IP info
            $longIp = ip2long($ip);
            return [
                'ip' => $ip,
                'long' => $longIp,
                'binary' => decbin($longIp)
            ];
        }

        // Regex test
        if ($path === '/api/v1/tools/regex-test') {
            $pattern = $body['pattern'] ?? '';
            $input = $body['input'] ?? '';

            if (empty($pattern)) {
                throw new \InvalidArgumentException('Pattern required');
            }

            $matches = [];
            $result = @preg_match('/' . $pattern . '/', $input, $matches);

            return [
                'matches' => $result === 1,
                'groups' => $matches
            ];
        }

        // Default: endpoint not implemented for batch
        throw new \RuntimeException('Batch execution not implemented for: ' . $path);
    }

    /**
     * Convert record type string to PHP constant
     */
    private function getDnsType(string $type): int {
        $types = [
            'A' => DNS_A,
            'AAAA' => DNS_AAAA,
            'MX' => DNS_MX,
            'TXT' => DNS_TXT,
            'NS' => DNS_NS,
            'CNAME' => DNS_CNAME,
            'SOA' => DNS_SOA,
            'PTR' => DNS_PTR,
        ];

        return $types[strtoupper($type)] ?? DNS_A;
    }
}
