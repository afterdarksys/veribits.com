<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Config;

class URLScanController
{
    /**
     * Scan a URL using URLScan.io
     */
    public function scanURL(): void
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
        $url = trim($input['url'] ?? '');
        $visibility = $input['visibility'] ?? 'public'; // public or private

        if (empty($url)) {
            Response::error('URL is required', 400);
            return;
        }

        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Response::error('Invalid URL format', 400);
            return;
        }

        try {
            $apiKey = Config::get('URLSCAN_API_KEY');

            if (empty($apiKey)) {
                Response::error('URLScan.io API key not configured', 503);
                return;
            }

            // Submit URL for scanning
            $submitUrl = 'https://urlscan.io/api/v1/scan/';

            $ch = curl_init($submitUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'url' => $url,
                    'visibility' => $visibility
                ]),
                CURLOPT_HTTPHEADER => [
                    'API-Key: ' . $apiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new \Exception("URLScan.io API returned status code: $httpCode");
            }

            $submitData = json_decode($response, true);
            $uuid = $submitData['uuid'] ?? null;
            $resultUrl = $submitData['result'] ?? null;

            if (!$uuid) {
                throw new \Exception('Failed to get scan UUID from URLScan.io');
            }

            // Wait a few seconds for scan to complete
            sleep(10);

            // Fetch results
            $resultData = $this->fetchScanResults($uuid);

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Logger::info('URLScan completed', [
                'url' => $url,
                'uuid' => $uuid,
                'verdict' => $resultData['verdicts']['overall']['malicious'] ?? false
            ]);

            Response::success([
                'url' => $url,
                'uuid' => $uuid,
                'result_url' => $resultUrl,
                'screenshot' => $resultData['task']['screenshotURL'] ?? null,
                'verdicts' => $resultData['verdicts'] ?? [],
                'stats' => $resultData['stats'] ?? [],
                'page' => [
                    'domain' => $resultData['page']['domain'] ?? null,
                    'ip' => $resultData['page']['ip'] ?? null,
                    'country' => $resultData['page']['country'] ?? null,
                    'server' => $resultData['page']['server'] ?? null,
                    'title' => $resultData['page']['title'] ?? null
                ],
                'lists' => [
                    'ips' => $resultData['lists']['ips'] ?? [],
                    'countries' => $resultData['lists']['countries'] ?? [],
                    'urls' => array_slice($resultData['lists']['urls'] ?? [], 0, 10), // Limit to 10
                    'domains' => array_slice($resultData['lists']['domains'] ?? [], 0, 10)
                ],
                'scan_time' => $resultData['task']['time'] ?? null
            ], 'URL scan completed');

        } catch (\Exception $e) {
            Logger::error('URLScan failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            Response::error('URL scan failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get cached scan results for a URL
     */
    public function getResults(): void
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
        $uuid = trim($input['uuid'] ?? '');

        if (empty($uuid)) {
            Response::error('Scan UUID is required', 400);
            return;
        }

        try {
            $resultData = $this->fetchScanResults($uuid);

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success([
                'uuid' => $uuid,
                'screenshot' => $resultData['task']['screenshotURL'] ?? null,
                'verdicts' => $resultData['verdicts'] ?? [],
                'stats' => $resultData['stats'] ?? [],
                'page' => $resultData['page'] ?? [],
                'lists' => $resultData['lists'] ?? []
            ], 'Scan results retrieved');

        } catch (\Exception $e) {
            Response::error('Failed to retrieve results: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Fetch scan results from URLScan.io
     */
    private function fetchScanResults(string $uuid): array
    {
        $resultUrl = "https://urlscan.io/api/v1/result/{$uuid}/";

        $ch = curl_init($resultUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 404) {
            throw new \Exception('Scan results not found. Scan may still be processing.');
        }

        if ($httpCode !== 200) {
            throw new \Exception("Failed to fetch results. HTTP status: $httpCode");
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \Exception('Invalid response from URLScan.io');
        }

        return $data;
    }
}
