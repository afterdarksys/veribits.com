<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Config;

class CertificateController
{
    /**
     * Search certificate transparency logs using crt.sh (FREE)
     */
    public function searchCertificates(): void
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
        $domain = trim($input['domain'] ?? '');
        $includeSubdomains = $input['include_subdomains'] ?? true;

        if (empty($domain)) {
            Response::error('Domain is required', 400);
            return;
        }

        // Basic domain validation
        if (!preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $domain)) {
            Response::error('Invalid domain name', 400);
            return;
        }

        try {
            $searchQuery = $includeSubdomains ? "%.{$domain}" : $domain;
            $url = 'https://crt.sh/?' . http_build_query([
                'q' => $searchQuery,
                'output' => 'json'
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: VeriBits/1.0'
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new \Exception("crt.sh API returned status code: $httpCode");
            }

            $certificates = json_decode($response, true);
            if (!is_array($certificates)) {
                $certificates = [];
            }

            // Extract unique subdomains
            $subdomains = [];
            $certSummary = [];

            foreach ($certificates as $cert) {
                $commonName = $cert['common_name'] ?? '';
                $nameValue = $cert['name_value'] ?? '';

                // Parse name_value which may contain multiple domains
                $domains = array_filter(
                    array_map('trim', explode("\n", $nameValue)),
                    fn($d) => !empty($d)
                );

                foreach ($domains as $d) {
                    if (str_contains($d, $domain)) {
                        $subdomains[$d] = true;
                    }
                }

                // Add to certificate summary (limit to avoid huge responses)
                if (count($certSummary) < 50) {
                    $certSummary[] = [
                        'issuer_name' => $cert['issuer_name'] ?? null,
                        'common_name' => $commonName,
                        'not_before' => $cert['not_before'] ?? null,
                        'not_after' => $cert['not_after'] ?? null,
                        'entry_timestamp' => $cert['entry_timestamp'] ?? null,
                        'serial_number' => $cert['serial_number'] ?? null
                    ];
                }
            }

            $subdomains = array_keys($subdomains);
            sort($subdomains);

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Logger::info('Certificate search completed', [
                'domain' => $domain,
                'total_certs' => count($certificates),
                'unique_subdomains' => count($subdomains)
            ]);

            Response::success([
                'domain' => $domain,
                'total_certificates' => count($certificates),
                'unique_subdomains' => count($subdomains),
                'subdomains' => $subdomains,
                'certificates' => $certSummary,
                'search_type' => $includeSubdomains ? 'with_subdomains' : 'exact_match'
            ], 'Certificate search completed');

        } catch (\Exception $e) {
            Logger::error('Certificate search failed', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            Response::error('Certificate search failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get certificate details by ID from crt.sh
     */
    public function getCertificate(): void
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
        $certId = trim($input['cert_id'] ?? '');

        if (empty($certId)) {
            Response::error('Certificate ID is required', 400);
            return;
        }

        try {
            $url = "https://crt.sh/?id={$certId}&output=json";

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new \Exception("Failed to fetch certificate details");
            }

            $certData = json_decode($response, true);

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success([
                'certificate' => $certData
            ], 'Certificate retrieved');

        } catch (\Exception $e) {
            Response::error('Failed to retrieve certificate: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Query Censys for certificate intelligence (requires API key)
     */
    public function searchCensys(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            Response::error('Authentication required for Censys searches', 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $query = trim($input['query'] ?? '');

        if (empty($query)) {
            Response::error('Search query is required', 400);
            return;
        }

        try {
            $apiId = Config::get('CENSYS_API_ID');
            $apiSecret = Config::get('CENSYS_API_SECRET');

            if (empty($apiId) || empty($apiSecret)) {
                Response::error('Censys API credentials not configured', 503);
                return;
            }

            $url = 'https://search.censys.io/api/v2/certificates/search';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'q' => $query,
                    'per_page' => 20
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Basic ' . base64_encode("{$apiId}:{$apiSecret}")
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new \Exception("Censys API returned status code: $httpCode");
            }

            $data = json_decode($response, true);

            Response::success([
                'results' => $data['result']['hits'] ?? [],
                'total' => $data['result']['total'] ?? 0,
                'query' => $query
            ], 'Censys search completed');

        } catch (\Exception $e) {
            Logger::error('Censys search failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            Response::error('Censys search failed: ' . $e->getMessage(), 500);
        }
    }
}
