<?php
namespace VeriBits\Services;

use VeriBits\Utils\Config;
use VeriBits\Utils\Logger;

/**
 * SecretManager - Unified interface for secret management
 *
 * Provides a facade for accessing secrets with:
 * - Primary source: secretserver.io
 * - Fallback: Environment variables
 * - Secret rotation support
 * - Lazy loading for performance
 * - Graceful degradation when secretserver.io is unavailable
 */
class SecretManager {
    private static ?SecretManager $instance = null;
    private ?SecretServerClient $client = null;
    private array $secretCache = [];
    private bool $secretServerEnabled;
    private bool $secretServerAvailable = false;

    /**
     * Private constructor - use getInstance() instead
     */
    private function __construct() {
        $this->secretServerEnabled = Config::getBool('SECRETSERVER_ENABLED', true);

        if ($this->secretServerEnabled) {
            try {
                $this->client = new SecretServerClient();
                $this->secretServerAvailable = $this->client->isAvailable();

                if ($this->secretServerAvailable) {
                    Logger::info('SecretManager: SecretServer is available and enabled');
                } else {
                    Logger::warning('SecretManager: SecretServer is configured but not available, falling back to environment variables');
                }
            } catch (\Exception $e) {
                Logger::error('SecretManager: Failed to initialize SecretServer client', [
                    'error' => $e->getMessage()
                ]);
                $this->secretServerAvailable = false;
            }
        } else {
            Logger::info('SecretManager: SecretServer is disabled, using environment variables only');
        }
    }

    /**
     * Get singleton instance
     *
     * @return SecretManager
     */
    public static function getInstance(): SecretManager {
        if (self::$instance === null) {
            self::$instance = new SecretManager();
        }
        return self::$instance;
    }

    /**
     * Get a secret by name
     *
     * Attempts to retrieve from secretserver.io first, then falls back to environment variables.
     * Supports lazy loading and caching for performance.
     *
     * @param string $name Secret name
     * @param string|null $default Default value if secret not found
     * @param bool $required If true, throws exception when secret is missing
     * @return string|null Secret value
     * @throws \RuntimeException if required secret is missing
     */
    public function getSecret(string $name, ?string $default = null, bool $required = false): ?string {
        // Check memory cache first
        if (isset($this->secretCache[$name])) {
            return $this->secretCache[$name];
        }

        $value = null;

        // Try SecretServer if available
        if ($this->secretServerEnabled && $this->secretServerAvailable && $this->client !== null) {
            try {
                $value = $this->client->getSecret($name);
                if ($value !== null) {
                    Logger::debug('SecretManager: Retrieved from SecretServer', ['name' => $name]);
                    $this->secretCache[$name] = $value;
                    return $value;
                }
            } catch (\Exception $e) {
                Logger::warning('SecretManager: Failed to retrieve from SecretServer, falling back to environment', [
                    'name' => $name,
                    'error' => $e->getMessage()
                ]);
                // Mark as unavailable for this request
                $this->secretServerAvailable = false;
            }
        }

        // Fallback to environment variables
        $value = Config::get($name, '');
        if ($value !== '') {
            Logger::debug('SecretManager: Retrieved from environment', ['name' => $name]);
            $this->secretCache[$name] = $value;
            return $value;
        }

        // Use default if provided
        if ($default !== null) {
            Logger::debug('SecretManager: Using default value', ['name' => $name]);
            return $default;
        }

        // Throw exception if required
        if ($required) {
            throw new \RuntimeException("Required secret '$name' is not available from SecretServer or environment variables");
        }

        Logger::debug('SecretManager: Secret not found', ['name' => $name]);
        return null;
    }

    /**
     * Get a required secret
     *
     * Convenience method that throws exception if secret is missing
     *
     * @param string $name Secret name
     * @return string Secret value
     * @throws \RuntimeException if secret is missing
     */
    public function getRequiredSecret(string $name): string {
        $value = $this->getSecret($name, null, true);
        if ($value === null) {
            throw new \RuntimeException("Required secret '$name' is not available");
        }
        return $value;
    }

    /**
     * Set or update a secret in secretserver.io
     *
     * @param string $name Secret name
     * @param string $value Secret value
     * @param array $metadata Optional metadata
     * @return bool True on success
     * @throws \RuntimeException if SecretServer is not available
     */
    public function setSecret(string $name, string $value, array $metadata = []): bool {
        if (!$this->secretServerEnabled) {
            throw new \RuntimeException('SecretServer is disabled, cannot set secrets');
        }

        if (!$this->secretServerAvailable || $this->client === null) {
            throw new \RuntimeException('SecretServer is not available');
        }

        try {
            $result = $this->client->setSecret($name, $value, $metadata);

            // Clear from cache to force refresh on next get
            if (isset($this->secretCache[$name])) {
                unset($this->secretCache[$name]);
            }

            Logger::info('SecretManager: Secret set', ['name' => $name]);
            return $result;

        } catch (\Exception $e) {
            Logger::error('SecretManager: Failed to set secret', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Rotate a secret
     *
     * Generates a new value and updates the secret in secretserver.io
     *
     * @param string $name Secret name
     * @param callable|null $generator Custom generator function, or null for random string
     * @param int $length Length of generated secret (only used with default generator)
     * @return string New secret value
     * @throws \RuntimeException if SecretServer is not available
     */
    public function rotateSecret(string $name, ?callable $generator = null, int $length = 32): string {
        if (!$this->secretServerEnabled) {
            throw new \RuntimeException('SecretServer is disabled, cannot rotate secrets');
        }

        if (!$this->secretServerAvailable || $this->client === null) {
            throw new \RuntimeException('SecretServer is not available');
        }

        // Generate new secret value
        if ($generator !== null) {
            $newValue = $generator();
        } else {
            // Default: generate random string
            $newValue = bin2hex(random_bytes($length / 2));
        }

        // Update in SecretServer
        $this->setSecret($name, $newValue, [
            'description' => "Rotated on " . date('Y-m-d H:i:s')
        ]);

        Logger::info('SecretManager: Secret rotated', ['name' => $name]);
        return $newValue;
    }

    /**
     * List all secrets from secretserver.io
     *
     * @return array Array of secret names
     * @throws \RuntimeException if SecretServer is not available
     */
    public function listSecrets(): array {
        if (!$this->secretServerEnabled) {
            throw new \RuntimeException('SecretServer is disabled');
        }

        if (!$this->secretServerAvailable || $this->client === null) {
            throw new \RuntimeException('SecretServer is not available');
        }

        return $this->client->listSecrets();
    }

    /**
     * Delete a secret from secretserver.io
     *
     * @param string $name Secret name
     * @return bool True on success
     * @throws \RuntimeException if SecretServer is not available
     */
    public function deleteSecret(string $name): bool {
        if (!$this->secretServerEnabled) {
            throw new \RuntimeException('SecretServer is disabled');
        }

        if (!$this->secretServerAvailable || $this->client === null) {
            throw new \RuntimeException('SecretServer is not available');
        }

        $result = $this->client->deleteSecret($name);

        // Clear from cache
        if (isset($this->secretCache[$name])) {
            unset($this->secretCache[$name]);
        }

        Logger::info('SecretManager: Secret deleted', ['name' => $name]);
        return $result;
    }

    /**
     * Clear memory cache
     *
     * @param string|null $name Optional secret name to clear, or null to clear all
     */
    public function clearCache(?string $name = null): void {
        if ($name === null) {
            $this->secretCache = [];
            Logger::debug('SecretManager: All memory cache cleared');
        } elseif (isset($this->secretCache[$name])) {
            unset($this->secretCache[$name]);
            Logger::debug('SecretManager: Memory cache cleared', ['name' => $name]);
        }

        // Also clear SecretServerClient cache if available
        if ($this->client !== null) {
            $this->client->clearCache($name);
        }
    }

    /**
     * Check if SecretServer is available
     *
     * @return bool True if SecretServer is available
     */
    public function isSecretServerAvailable(): bool {
        return $this->secretServerAvailable;
    }

    /**
     * Check if SecretServer is enabled
     *
     * @return bool True if SecretServer is enabled
     */
    public function isSecretServerEnabled(): bool {
        return $this->secretServerEnabled;
    }

    /**
     * Get health status
     *
     * @return array Status information
     */
    public function getHealthStatus(): array {
        return [
            'secretserver_enabled' => $this->secretServerEnabled,
            'secretserver_available' => $this->secretServerAvailable,
            'cached_secrets' => count($this->secretCache),
            'fallback_mode' => $this->secretServerEnabled && !$this->secretServerAvailable
        ];
    }

    /**
     * Prefetch multiple secrets
     *
     * Useful for warming up the cache at application startup
     *
     * @param array $names Array of secret names
     * @return array Map of name => value for found secrets
     */
    public function prefetchSecrets(array $names): array {
        $results = [];

        foreach ($names as $name) {
            try {
                $value = $this->getSecret($name);
                if ($value !== null) {
                    $results[$name] = $value;
                }
            } catch (\Exception $e) {
                Logger::warning('SecretManager: Failed to prefetch secret', [
                    'name' => $name,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Logger::info('SecretManager: Prefetched secrets', [
            'requested' => count($names),
            'found' => count($results)
        ]);

        return $results;
    }
}
