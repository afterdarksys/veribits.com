<?php
/**
 * SecretManager Usage Examples
 *
 * This file demonstrates various ways to use the SecretManager
 * for accessing secrets in VeriBits controllers and services.
 */

namespace VeriBits\Examples;

use VeriBits\Services\SecretManager;
use VeriBits\Utils\Logger;

/**
 * Example 1: Basic Secret Retrieval in a Controller
 */
class ExampleAPIController {
    private SecretManager $secretManager;
    private string $apiKey;

    public function __construct() {
        $this->secretManager = SecretManager::getInstance();

        // Get secret with automatic fallback to environment
        $this->apiKey = $this->secretManager->getSecret('EXAMPLE_API_KEY', '');

        if (empty($this->apiKey)) {
            Logger::error('EXAMPLE_API_KEY not configured');
        } else {
            Logger::info('Example API initialized', [
                'source' => $this->secretManager->isSecretServerAvailable()
                    ? 'SecretServer'
                    : 'Environment'
            ]);
        }
    }

    public function callExternalAPI(): array {
        // Use the API key in requests
        $ch = curl_init('https://api.example.com/data');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey
            ]
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}

/**
 * Example 2: Required Secrets with Exception Handling
 */
class DatabaseService {
    private SecretManager $secretManager;
    private \PDO $connection;

    public function __construct() {
        $this->secretManager = SecretManager::getInstance();

        try {
            // Get required secrets (throws if missing)
            $dbHost = $this->secretManager->getRequiredSecret('DB_HOST');
            $dbName = $this->secretManager->getRequiredSecret('DB_NAME');
            $dbUser = $this->secretManager->getRequiredSecret('DB_USER');
            $dbPassword = $this->secretManager->getRequiredSecret('DB_PASSWORD');

            // Create database connection
            $dsn = "pgsql:host=$dbHost;dbname=$dbName";
            $this->connection = new \PDO($dsn, $dbUser, $dbPassword);

            Logger::info('Database connection established');

        } catch (\RuntimeException $e) {
            Logger::error('Failed to get required database credentials', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getConnection(): \PDO {
        return $this->connection;
    }
}

/**
 * Example 3: Multiple Secrets with Prefetching
 */
class EmailService {
    private SecretManager $secretManager;
    private array $config;

    public function __construct() {
        $this->secretManager = SecretManager::getInstance();

        // Prefetch multiple secrets at once for better performance
        $secrets = $this->secretManager->prefetchSecrets([
            'SMTP_HOST',
            'SMTP_PORT',
            'SMTP_USER',
            'SMTP_PASSWORD',
            'SMTP_FROM_ADDRESS'
        ]);

        // Build configuration from prefetched secrets
        $this->config = [
            'host' => $secrets['SMTP_HOST'] ?? 'localhost',
            'port' => $secrets['SMTP_PORT'] ?? '587',
            'username' => $secrets['SMTP_USER'] ?? '',
            'password' => $secrets['SMTP_PASSWORD'] ?? '',
            'from' => $secrets['SMTP_FROM_ADDRESS'] ?? 'noreply@veribits.com'
        ];

        Logger::info('Email service initialized', [
            'host' => $this->config['host'],
            'from' => $this->config['from']
        ]);
    }

    public function sendEmail(string $to, string $subject, string $body): bool {
        // Use configured SMTP settings to send email
        // ...
        return true;
    }
}

/**
 * Example 4: Secret Rotation in Maintenance Script
 */
class SecretRotationService {
    private SecretManager $secretManager;

    public function __construct() {
        $this->secretManager = SecretManager::getInstance();
    }

    /**
     * Rotate all API keys on a schedule
     */
    public function rotateAllAPIKeys(): array {
        $results = [];
        $secretsToRotate = [
            'JWT_SECRET',
            'API_SIGNING_KEY',
            'WEBHOOK_SECRET'
        ];

        foreach ($secretsToRotate as $secretName) {
            try {
                Logger::info("Rotating secret: $secretName");

                // Rotate with default random generator
                $newValue = $this->secretManager->rotateSecret($secretName);

                $results[$secretName] = [
                    'success' => true,
                    'rotated_at' => date('Y-m-d H:i:s')
                ];

                Logger::info("Successfully rotated secret: $secretName");

                // Here you would notify dependent services of the new value
                // $this->notifyDependentServices($secretName, $newValue);

            } catch (\Exception $e) {
                Logger::error("Failed to rotate secret: $secretName", [
                    'error' => $e->getMessage()
                ]);

                $results[$secretName] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Rotate with custom generator
     */
    public function rotateJWTSecretWithCustomFormat(): string {
        return $this->secretManager->rotateSecret('JWT_SECRET', function() {
            // Custom format: prefix + timestamp + random
            return 'jwt_' . time() . '_' . bin2hex(random_bytes(16));
        });
    }
}

/**
 * Example 5: Configuration Service with Defaults
 */
class ConfigurationService {
    private SecretManager $secretManager;
    private array $settings;

    public function __construct() {
        $this->secretManager = SecretManager::getInstance();

        // Load configuration with sensible defaults
        $this->settings = [
            // Required secrets
            'api_key' => $this->secretManager->getRequiredSecret('SERVICE_API_KEY'),

            // Optional secrets with defaults
            'timeout' => (int)$this->secretManager->getSecret('SERVICE_TIMEOUT', '30'),
            'max_retries' => (int)$this->secretManager->getSecret('SERVICE_MAX_RETRIES', '3'),
            'cache_ttl' => (int)$this->secretManager->getSecret('SERVICE_CACHE_TTL', '300'),
            'debug_mode' => $this->secretManager->getSecret('SERVICE_DEBUG', 'false') === 'true',

            // Feature flags
            'enable_webhooks' => $this->secretManager->getSecret('ENABLE_WEBHOOKS', 'true') === 'true',
            'enable_logging' => $this->secretManager->getSecret('ENABLE_LOGGING', 'true') === 'true'
        ];

        Logger::debug('Configuration loaded', $this->settings);
    }

    public function getSetting(string $key, $default = null) {
        return $this->settings[$key] ?? $default;
    }
}

/**
 * Example 6: Conditional Secret Loading Based on Environment
 */
class PaymentService {
    private SecretManager $secretManager;
    private string $stripeKey;
    private bool $testMode;

    public function __construct() {
        $this->secretManager = SecretManager::getInstance();

        // Determine environment
        $environment = $this->secretManager->getSecret('APP_ENV', 'production');
        $this->testMode = in_array($environment, ['local', 'development', 'testing']);

        // Load appropriate Stripe key based on environment
        $keyName = $this->testMode ? 'STRIPE_TEST_SECRET_KEY' : 'STRIPE_SECRET_KEY';
        $this->stripeKey = $this->secretManager->getRequiredSecret($keyName);

        Logger::info('Payment service initialized', [
            'environment' => $environment,
            'test_mode' => $this->testMode
        ]);
    }

    public function isTestMode(): bool {
        return $this->testMode;
    }
}

/**
 * Example 7: Health Check Integration
 */
class HealthCheckService {
    private SecretManager $secretManager;

    public function __construct() {
        $this->secretManager = SecretManager::getInstance();
    }

    /**
     * Comprehensive health check including SecretServer status
     */
    public function getHealthStatus(): array {
        $secretServerStatus = $this->secretManager->getHealthStatus();

        return [
            'status' => 'ok',
            'timestamp' => date('c'),
            'services' => [
                'secretserver' => [
                    'enabled' => $secretServerStatus['secretserver_enabled'],
                    'available' => $secretServerStatus['secretserver_available'],
                    'cached_secrets' => $secretServerStatus['cached_secrets'],
                    'fallback_mode' => $secretServerStatus['fallback_mode'],
                    'status' => $secretServerStatus['secretserver_available'] ? 'healthy' : 'degraded'
                ],
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache()
            ]
        ];
    }

    private function checkDatabase(): array {
        try {
            $dbPassword = $this->secretManager->getRequiredSecret('DB_PASSWORD');
            // Test database connection
            return ['status' => 'healthy'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkCache(): array {
        // Check cache status
        return ['status' => 'healthy'];
    }
}

/**
 * Example 8: Admin Controller for Secret Management
 */
class AdminSecretsController {
    private SecretManager $secretManager;

    public function __construct() {
        $this->secretManager = SecretManager::getInstance();
    }

    /**
     * List all secrets (admin only)
     */
    public function listSecrets(): array {
        if (!$this->secretManager->isSecretServerAvailable()) {
            return [
                'error' => 'SecretServer is not available',
                'fallback_mode' => true
            ];
        }

        $secrets = $this->secretManager->listSecrets();

        return [
            'count' => count($secrets),
            'secrets' => array_map(function($secret) {
                // Don't expose values, only metadata
                return [
                    'name' => $secret['name'],
                    'description' => $secret['description'] ?? null,
                    'tags' => $secret['tags'] ?? [],
                    'created_at' => $secret['created_at'] ?? null
                ];
            }, $secrets)
        ];
    }

    /**
     * Rotate a secret (admin only)
     */
    public function rotateSecret(string $name): array {
        try {
            $newValue = $this->secretManager->rotateSecret($name);

            Logger::warning("Secret rotated by admin", [
                'secret_name' => $name,
                'admin_user' => $_SESSION['user_id'] ?? 'unknown'
            ]);

            return [
                'success' => true,
                'message' => "Secret '$name' rotated successfully",
                'rotated_at' => date('c')
            ];

        } catch (\Exception $e) {
            Logger::error("Failed to rotate secret", [
                'secret_name' => $name,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Clear secret cache (admin only)
     */
    public function clearCache(?string $name = null): array {
        $this->secretManager->clearCache($name);

        $message = $name
            ? "Cache cleared for secret '$name'"
            : "All secret caches cleared";

        Logger::info($message, [
            'admin_user' => $_SESSION['user_id'] ?? 'unknown'
        ]);

        return [
            'success' => true,
            'message' => $message
        ];
    }
}

/**
 * Example 9: Graceful Degradation Pattern
 */
class ThirdPartyAPIService {
    private SecretManager $secretManager;
    private ?string $apiKey;
    private bool $enabled;

    public function __construct() {
        $this->secretManager = SecretManager::getInstance();

        // Try to get API key, but don't fail if missing
        $this->apiKey = $this->secretManager->getSecret('THIRD_PARTY_API_KEY');
        $this->enabled = !empty($this->apiKey);

        if (!$this->enabled) {
            Logger::warning('Third party API key not configured, service will be disabled');
        }
    }

    public function callAPI(array $data): ?array {
        if (!$this->enabled) {
            Logger::info('Third party API disabled, skipping call');
            return null;
        }

        // Make API call with the key
        // ...
        return ['success' => true];
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }
}

/**
 * Example 10: Secret Validation and Testing
 */
class SecretValidationService {
    private SecretManager $secretManager;

    public function __construct() {
        $this->secretManager = SecretManager::getInstance();
    }

    /**
     * Validate all required secrets are present
     */
    public function validateRequiredSecrets(): array {
        $required = [
            'JWT_SECRET',
            'DB_PASSWORD',
            'STRIPE_SECRET_KEY',
            'HIBP_API_KEY'
        ];

        $results = [
            'valid' => true,
            'missing' => [],
            'present' => []
        ];

        foreach ($required as $secretName) {
            try {
                $value = $this->secretManager->getRequiredSecret($secretName);
                $results['present'][] = $secretName;
            } catch (\RuntimeException $e) {
                $results['valid'] = false;
                $results['missing'][] = $secretName;
            }
        }

        return $results;
    }

    /**
     * Test secret connectivity
     */
    public function testSecretConnectivity(): array {
        return [
            'secretserver_enabled' => $this->secretManager->isSecretServerEnabled(),
            'secretserver_available' => $this->secretManager->isSecretServerAvailable(),
            'health_status' => $this->secretManager->getHealthStatus(),
            'can_retrieve_secrets' => $this->canRetrieveSecrets(),
            'fallback_works' => $this->testFallback()
        ];
    }

    private function canRetrieveSecrets(): bool {
        try {
            // Try to retrieve a known secret
            $this->secretManager->getSecret('JWT_SECRET');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function testFallback(): bool {
        // Set a test environment variable
        putenv('TEST_FALLBACK_SECRET=test-value');

        try {
            $value = $this->secretManager->getSecret('TEST_FALLBACK_SECRET');
            return $value === 'test-value';
        } catch (\Exception $e) {
            return false;
        }
    }
}
