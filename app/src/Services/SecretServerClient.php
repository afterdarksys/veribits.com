<?php
namespace VeriBits\Services;

use VeriBits\Utils\Config;
use VeriBits\Utils\Logger;

/**
 * SecretServerClient - Client for secretserver.io API
 *
 * Provides secure access to secrets stored in secretserver.io with:
 * - HTTP/HTTPS API communication
 * - Local caching for performance
 * - Error handling and fallbacks
 * - Secret rotation support
 */
class SecretServerClient {
    private string $apiUrl;
    private string $apiKey;
    private array $cache = [];
    private int $cacheTtl;
    private bool $cacheEnabled;

    /**
     * Constructor - Initialize client with configuration
     */
    public function __construct() {
        $this->apiUrl = rtrim(Config::get('SECRETSERVER_API_URL', 'http://localhost:3000'), '/');
        $this->apiKey = Config::get('SECRETSERVER_API_KEY', '');
        $this->cacheTtl = Config::getInt('SECRETSERVER_CACHE_TTL', 300); // 5 minutes default
        $this->cacheEnabled = Config::getBool('SECRETSERVER_CACHE_ENABLED', true);

        if (empty($this->apiKey)) {
            Logger::warning('SecretServerClient: SECRETSERVER_API_KEY not configured');
        }
    }

    /**
     * Get a secret by name
     *
     * @param string $name Secret name
     * @param bool $useCache Whether to use cached value
     * @return string|null Secret value or null if not found
     * @throws \RuntimeException on API errors
     */
    public function getSecret(string $name, bool $useCache = true): ?string {
        // Check cache first
        if ($useCache && $this->cacheEnabled && isset($this->cache[$name])) {
            $cached = $this->cache[$name];
            if (time() < $cached['expires']) {
                Logger::debug('SecretServerClient: Cache hit', ['name' => $name]);
                return $cached['value'];
            }
            // Expired, remove from cache
            unset($this->cache[$name]);
        }

        try {
            $url = $this->apiUrl . '/api/v1/secrets/' . urlencode($name);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $this->apiKey,
                    'Content-Type: application/json',
                    'User-Agent: VeriBits/1.0'
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException("SecretServer API request failed: $curlError");
            }

            if ($httpCode === 404) {
                Logger::debug('SecretServerClient: Secret not found', ['name' => $name]);
                return null;
            }

            if ($httpCode === 401 || $httpCode === 403) {
                throw new \RuntimeException('SecretServer authentication failed. Check API key.');
            }

            if ($httpCode !== 200) {
                throw new \RuntimeException("SecretServer API returned status code: $httpCode");
            }

            $data = json_decode($response, true);
            if (!is_array($data) || !isset($data['value'])) {
                throw new \RuntimeException('Invalid response from SecretServer API');
            }

            $secretValue = $data['value'];

            // Cache the result
            if ($this->cacheEnabled) {
                $this->cache[$name] = [
                    'value' => $secretValue,
                    'expires' => time() + $this->cacheTtl
                ];
            }

            Logger::info('SecretServerClient: Secret retrieved', ['name' => $name]);
            return $secretValue;

        } catch (\Exception $e) {
            Logger::error('SecretServerClient: Failed to get secret', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Set or update a secret
     *
     * @param string $name Secret name
     * @param string $value Secret value
     * @param array $metadata Optional metadata (description, tags, etc.)
     * @return bool True on success
     * @throws \RuntimeException on API errors
     */
    public function setSecret(string $name, string $value, array $metadata = []): bool {
        try {
            $url = $this->apiUrl . '/api/v1/secrets';

            $payload = [
                'name' => $name,
                'value' => $value
            ];

            // Add optional metadata
            if (!empty($metadata['description'])) {
                $payload['description'] = $metadata['description'];
            }
            if (!empty($metadata['tags'])) {
                $payload['tags'] = $metadata['tags'];
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $this->apiKey,
                    'Content-Type: application/json',
                    'User-Agent: VeriBits/1.0'
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException("SecretServer API request failed: $curlError");
            }

            if ($httpCode === 401 || $httpCode === 403) {
                throw new \RuntimeException('SecretServer authentication failed. Check API key.');
            }

            if ($httpCode !== 200 && $httpCode !== 201) {
                throw new \RuntimeException("SecretServer API returned status code: $httpCode");
            }

            // Invalidate cache for this secret
            if (isset($this->cache[$name])) {
                unset($this->cache[$name]);
            }

            Logger::info('SecretServerClient: Secret set', ['name' => $name]);
            return true;

        } catch (\Exception $e) {
            Logger::error('SecretServerClient: Failed to set secret', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * List all secrets
     *
     * @return array Array of secret names
     * @throws \RuntimeException on API errors
     */
    public function listSecrets(): array {
        try {
            $url = $this->apiUrl . '/api/v1/secrets';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $this->apiKey,
                    'Content-Type: application/json',
                    'User-Agent: VeriBits/1.0'
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException("SecretServer API request failed: $curlError");
            }

            if ($httpCode === 401 || $httpCode === 403) {
                throw new \RuntimeException('SecretServer authentication failed. Check API key.');
            }

            if ($httpCode !== 200) {
                throw new \RuntimeException("SecretServer API returned status code: $httpCode");
            }

            $data = json_decode($response, true);
            if (!is_array($data) || !isset($data['secrets'])) {
                throw new \RuntimeException('Invalid response from SecretServer API');
            }

            Logger::info('SecretServerClient: Secrets listed', ['count' => count($data['secrets'])]);
            return $data['secrets'];

        } catch (\Exception $e) {
            Logger::error('SecretServerClient: Failed to list secrets', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete a secret
     *
     * @param string $name Secret name
     * @return bool True on success
     * @throws \RuntimeException on API errors
     */
    public function deleteSecret(string $name): bool {
        try {
            $url = $this->apiUrl . '/api/v1/secrets/' . urlencode($name);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $this->apiKey,
                    'Content-Type: application/json',
                    'User-Agent: VeriBits/1.0'
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException("SecretServer API request failed: $curlError");
            }

            if ($httpCode === 401 || $httpCode === 403) {
                throw new \RuntimeException('SecretServer authentication failed. Check API key.');
            }

            if ($httpCode !== 200 && $httpCode !== 204) {
                throw new \RuntimeException("SecretServer API returned status code: $httpCode");
            }

            // Invalidate cache for this secret
            if (isset($this->cache[$name])) {
                unset($this->cache[$name]);
            }

            Logger::info('SecretServerClient: Secret deleted', ['name' => $name]);
            return true;

        } catch (\Exception $e) {
            Logger::error('SecretServerClient: Failed to delete secret', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Clear local cache
     *
     * @param string|null $name Optional secret name to clear, or null to clear all
     */
    public function clearCache(?string $name = null): void {
        if ($name === null) {
            $this->cache = [];
            Logger::debug('SecretServerClient: All cache cleared');
        } elseif (isset($this->cache[$name])) {
            unset($this->cache[$name]);
            Logger::debug('SecretServerClient: Cache cleared', ['name' => $name]);
        }
    }

    /**
     * Check if SecretServer is available
     *
     * @return bool True if SecretServer is reachable
     */
    public function isAvailable(): bool {
        try {
            $url = $this->apiUrl . '/health';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200;

        } catch (\Exception $e) {
            Logger::debug('SecretServerClient: Availability check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
