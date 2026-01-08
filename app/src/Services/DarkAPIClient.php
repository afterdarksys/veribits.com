<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Services;

use VeriBits\Utils\Logger;
use VeriBits\Utils\Config;

/**
 * DarkAPI.io Client
 *
 * Centralized threat intelligence client that queries darkapi.io
 * instead of calling external APIs directly. This saves query credits
 * and provides aggregated intelligence from 15+ threat feeds.
 *
 * DarkAPI.io aggregates:
 * - AbuseIPDB, Shodan, IPInfo, VirusTotal
 * - Abuse.ch (URLhaus, ThreatFox, Feodo, SSL Blacklist)
 * - CISA KEV, PhishTank, OpenPhish
 * - Emerging Threats, Blocklist.de, Tor exits
 * - And more...
 */
class DarkAPIClient
{
    private string $apiUrl;
    private string $apiKey;
    private int $timeout;
    private bool $enabled;

    public function __construct()
    {
        $this->apiUrl = Config::get('DARKAPI_URL', 'https://api.darkapi.io');
        $this->apiKey = Config::get('DARKAPI_KEY', '');
        $this->timeout = (int)Config::get('DARKAPI_TIMEOUT', 10);
        $this->enabled = !empty($this->apiKey);

        if (!$this->enabled) {
            Logger::warning('DarkAPI client disabled - no API key configured');
        }
    }

    /**
     * Check if DarkAPI is available
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Query IP reputation and threat intelligence
     *
     * Returns aggregated data from:
     * - AbuseIPDB abuse confidence score
     * - Shodan host information
     * - IPInfo geolocation
     * - Tor exit node check
     * - Blocklists and RBLs
     */
    public function queryIP(string $ip): array
    {
        if (!$this->enabled) {
            return $this->disabledResponse('IP lookup');
        }

        try {
            $response = $this->request("GET", "/v1/ip/{$ip}");

            if ($response['success']) {
                return [
                    'source' => 'DarkAPI.io',
                    'checked' => true,
                    'ip' => $ip,
                    'abuse_confidence_score' => $response['data']['abuse_confidence'] ?? 0,
                    'threat_level' => $response['data']['threat_level'] ?? 'unknown',
                    'is_tor' => $response['data']['is_tor'] ?? false,
                    'is_vpn' => $response['data']['is_vpn'] ?? false,
                    'is_proxy' => $response['data']['is_proxy'] ?? false,
                    'country_code' => $response['data']['country'] ?? null,
                    'city' => $response['data']['city'] ?? null,
                    'isp' => $response['data']['isp'] ?? null,
                    'asn' => $response['data']['asn'] ?? null,
                    'total_reports' => $response['data']['reports'] ?? 0,
                    'blacklists' => $response['data']['blacklists'] ?? [],
                    'shodan_ports' => $response['data']['ports'] ?? [],
                    'tags' => $response['data']['tags'] ?? []
                ];
            }

        } catch (\Exception $e) {
            Logger::error('DarkAPI IP lookup failed', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
        }

        return ['source' => 'DarkAPI.io', 'checked' => false, 'error' => 'Query failed'];
    }

    /**
     * Query domain reputation and phishing status
     *
     * Returns aggregated data from:
     * - PhishTank phishing database
     * - OpenPhish feeds
     * - URLhaus malware distribution
     * - Certificate transparency
     */
    public function queryDomain(string $domain): array
    {
        if (!$this->enabled) {
            return $this->disabledResponse('Domain lookup');
        }

        try {
            $response = $this->request("GET", "/v1/domain/{$domain}");

            if ($response['success']) {
                return [
                    'source' => 'DarkAPI.io',
                    'checked' => true,
                    'domain' => $domain,
                    'is_phishing' => $response['data']['is_phishing'] ?? false,
                    'is_malware' => $response['data']['is_malware'] ?? false,
                    'threat_score' => $response['data']['threat_score'] ?? 0,
                    'categories' => $response['data']['categories'] ?? [],
                    'first_seen' => $response['data']['first_seen'] ?? null,
                    'last_seen' => $response['data']['last_seen'] ?? null,
                    'reputation' => $response['data']['reputation'] ?? 'unknown'
                ];
            }

        } catch (\Exception $e) {
            Logger::error('DarkAPI domain lookup failed', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
        }

        return ['source' => 'DarkAPI.io', 'checked' => false, 'error' => 'Query failed'];
    }

    /**
     * Check URL for malware and phishing
     */
    public function checkURL(string $url): array
    {
        if (!$this->enabled) {
            return $this->disabledResponse('URL check');
        }

        try {
            $response = $this->request("POST", "/v1/url/check", [
                'url' => $url
            ]);

            if ($response['success']) {
                return [
                    'source' => 'DarkAPI.io',
                    'checked' => true,
                    'url' => $url,
                    'is_malicious' => $response['data']['is_malicious'] ?? false,
                    'threat_types' => $response['data']['threat_types'] ?? [],
                    'confidence' => $response['data']['confidence'] ?? 0,
                    'feeds_matched' => $response['data']['feeds_matched'] ?? []
                ];
            }

        } catch (\Exception $e) {
            Logger::error('DarkAPI URL check failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
        }

        return ['source' => 'DarkAPI.io', 'checked' => false, 'error' => 'Query failed'];
    }

    /**
     * Lookup file hash in malware databases
     *
     * Returns aggregated data from:
     * - VirusTotal scan results
     * - MalwareBazaar samples
     * - ThreatFox IoCs
     * - Feodo Tracker C2s
     */
    public function lookupHash(string $hash): array
    {
        if (!$this->enabled) {
            return $this->disabledResponse('Hash lookup');
        }

        try {
            $response = $this->request("GET", "/v1/hash/{$hash}");

            if ($response['success']) {
                return [
                    'source' => 'DarkAPI.io',
                    'found' => $response['data']['found'] ?? false,
                    'is_malware' => $response['data']['is_malware'] ?? false,
                    'malware_family' => $response['data']['malware_family'] ?? null,
                    'threat_name' => $response['data']['threat_name'] ?? null,
                    'file_type' => $response['data']['file_type'] ?? null,
                    'first_seen' => $response['data']['first_seen'] ?? null,
                    'vt_detections' => $response['data']['vt_detections'] ?? 0,
                    'vt_total' => $response['data']['vt_total'] ?? 0,
                    'tags' => $response['data']['tags'] ?? [],
                    'confidence' => $response['data']['confidence'] ?? 0
                ];
            }

        } catch (\Exception $e) {
            Logger::error('DarkAPI hash lookup failed', [
                'hash' => $hash,
                'error' => $e->getMessage()
            ]);
        }

        return ['source' => 'DarkAPI.io', 'found' => false, 'error' => 'Query failed'];
    }

    /**
     * Get CVE details with CISA KEV status
     */
    public function queryCVE(string $cveId): array
    {
        if (!$this->enabled) {
            return $this->disabledResponse('CVE lookup');
        }

        try {
            $response = $this->request("GET", "/v1/cve/{$cveId}");

            if ($response['success']) {
                return [
                    'source' => 'DarkAPI.io',
                    'found' => true,
                    'cve_id' => $cveId,
                    'description' => $response['data']['description'] ?? null,
                    'severity' => $response['data']['severity'] ?? null,
                    'cvss_score' => $response['data']['cvss_score'] ?? null,
                    'is_kev' => $response['data']['is_kev'] ?? false,
                    'kev_due_date' => $response['data']['kev_due_date'] ?? null,
                    'published' => $response['data']['published'] ?? null,
                    'references' => $response['data']['references'] ?? []
                ];
            }

        } catch (\Exception $e) {
            Logger::error('DarkAPI CVE lookup failed', [
                'cve' => $cveId,
                'error' => $e->getMessage()
            ]);
        }

        return ['source' => 'DarkAPI.io', 'found' => false, 'error' => 'Query failed'];
    }

    /**
     * Get available threat feeds
     */
    public function getFeeds(): array
    {
        if (!$this->enabled) {
            return [];
        }

        try {
            $response = $this->request("GET", "/v1/feeds");

            if ($response['success']) {
                return $response['data']['feeds'] ?? [];
            }

        } catch (\Exception $e) {
            Logger::error('DarkAPI feeds query failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Get platform statistics
     */
    public function getStats(): array
    {
        if (!$this->enabled) {
            return [];
        }

        try {
            $response = $this->request("GET", "/v1/stats");

            if ($response['success']) {
                return $response['data'] ?? [];
            }

        } catch (\Exception $e) {
            Logger::error('DarkAPI stats query failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Make HTTP request to DarkAPI
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->apiUrl . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $this->apiKey,
                'Content-Type: application/json',
                'User-Agent: VeriBits/1.0'
            ]
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL error: $error");
        }

        if ($httpCode === 404) {
            return ['success' => false, 'error' => 'Not found'];
        }

        if ($httpCode !== 200) {
            throw new \Exception("HTTP error: $httpCode");
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \Exception("Invalid JSON response");
        }

        return ['success' => true, 'data' => $decoded];
    }

    /**
     * Return response when DarkAPI is disabled
     */
    private function disabledResponse(string $operation): array
    {
        return [
            'source' => 'DarkAPI.io',
            'checked' => false,
            'note' => 'DarkAPI not configured - add DARKAPI_KEY to .env'
        ];
    }
}
