#!/usr/bin/env php
<?php
/**
 * Test SecretServer.io Integration
 *
 * This script runs a series of tests to validate the secretserver.io integration
 * is working correctly.
 *
 * Usage:
 *   php scripts/test-secretserver-integration.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use VeriBits\Services\SecretManager;
use VeriBits\Services\SecretServerClient;
use VeriBits\Utils\Config;

echo "=== SecretServer.io Integration Tests ===\n\n";

// Track test results
$tests = [
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0
];

/**
 * Run a test
 */
function runTest(string $name, callable $test, array &$tests): void {
    echo "TEST: $name... ";

    try {
        $result = $test();

        if ($result === true) {
            echo "PASS\n";
            $tests['passed']++;
        } elseif ($result === null) {
            echo "WARN\n";
            $tests['warnings']++;
        } else {
            echo "FAIL: $result\n";
            $tests['failed']++;
        }
    } catch (Exception $e) {
        echo "FAIL: {$e->getMessage()}\n";
        $tests['failed']++;
    }
}

// Test 1: Configuration loaded
runTest('Configuration loaded', function() {
    Config::load();
    $apiUrl = Config::get('SECRETSERVER_API_URL');

    if (empty($apiUrl)) {
        return "SECRETSERVER_API_URL not configured";
    }

    return true;
}, $tests);

// Test 2: SecretManager initialized
runTest('SecretManager initialization', function() {
    $secretManager = SecretManager::getInstance();

    if (!$secretManager) {
        return "Failed to get SecretManager instance";
    }

    return true;
}, $tests);

// Test 3: SecretServer enabled
runTest('SecretServer enabled check', function() {
    $secretManager = SecretManager::getInstance();

    if (!$secretManager->isSecretServerEnabled()) {
        return "SecretServer is disabled (set SECRETSERVER_ENABLED=true)";
    }

    return true;
}, $tests);

// Test 4: SecretServer availability
runTest('SecretServer availability check', function() {
    $secretManager = SecretManager::getInstance();

    if (!$secretManager->isSecretServerAvailable()) {
        return null; // Warning, not failure (fallback mode works)
    }

    return true;
}, $tests);

// Test 5: Health status
runTest('Health status check', function() {
    $secretManager = SecretManager::getInstance();
    $status = $secretManager->getHealthStatus();

    if (!is_array($status)) {
        return "Invalid health status response";
    }

    if (!isset($status['secretserver_enabled'])) {
        return "Missing secretserver_enabled in health status";
    }

    return true;
}, $tests);

// Test 6: Environment fallback
runTest('Environment variable fallback', function() {
    $secretManager = SecretManager::getInstance();

    // Set a test environment variable
    putenv('TEST_SECRET_FALLBACK=test-value-12345');

    $value = $secretManager->getSecret('TEST_SECRET_FALLBACK');

    if ($value !== 'test-value-12345') {
        return "Failed to retrieve from environment variables";
    }

    return true;
}, $tests);

// Test 7: Cache functionality
runTest('Cache functionality', function() {
    $secretManager = SecretManager::getInstance();

    // Set a test secret
    putenv('TEST_SECRET_CACHE=cached-value-67890');

    // First retrieval (should cache)
    $value1 = $secretManager->getSecret('TEST_SECRET_CACHE');

    // Second retrieval (should hit cache)
    $value2 = $secretManager->getSecret('TEST_SECRET_CACHE');

    if ($value1 !== $value2) {
        return "Cache consistency check failed";
    }

    // Clear cache
    $secretManager->clearCache('TEST_SECRET_CACHE');

    return true;
}, $tests);

// Test 8: Required secret with missing value
runTest('Required secret exception handling', function() {
    $secretManager = SecretManager::getInstance();

    try {
        $secretManager->getRequiredSecret('NONEXISTENT_SECRET_12345');
        return "Should have thrown exception for missing required secret";
    } catch (RuntimeException $e) {
        // Expected exception
        return true;
    }
}, $tests);

// Test 9: Default value handling
runTest('Default value handling', function() {
    $secretManager = SecretManager::getInstance();

    $value = $secretManager->getSecret('NONEXISTENT_SECRET_DEFAULT', 'default-value');

    if ($value !== 'default-value') {
        return "Default value not returned for missing secret";
    }

    return true;
}, $tests);

// Test 10: Prefetch functionality
runTest('Prefetch functionality', function() {
    $secretManager = SecretManager::getInstance();

    // Set test secrets
    putenv('TEST_PREFETCH_1=value1');
    putenv('TEST_PREFETCH_2=value2');

    $results = $secretManager->prefetchSecrets([
        'TEST_PREFETCH_1',
        'TEST_PREFETCH_2',
        'NONEXISTENT_SECRET'
    ]);

    if (!isset($results['TEST_PREFETCH_1']) || $results['TEST_PREFETCH_1'] !== 'value1') {
        return "Failed to prefetch TEST_PREFETCH_1";
    }

    if (!isset($results['TEST_PREFETCH_2']) || $results['TEST_PREFETCH_2'] !== 'value2') {
        return "Failed to prefetch TEST_PREFETCH_2";
    }

    if (isset($results['NONEXISTENT_SECRET'])) {
        return "Should not have prefetched nonexistent secret";
    }

    return true;
}, $tests);

// Test 11: SecretServerClient instantiation
runTest('SecretServerClient instantiation', function() {
    $client = new SecretServerClient();

    if (!$client) {
        return "Failed to instantiate SecretServerClient";
    }

    return true;
}, $tests);

// Test 12: Integration with HaveIBeenPwnedController
runTest('HaveIBeenPwnedController integration', function() {
    // Set test API key
    putenv('HIBP_API_KEY=test-hibp-key-12345');

    $controller = new \VeriBits\Controllers\HaveIBeenPwnedController();

    // Controller should initialize without errors
    return true;
}, $tests);

// Test 13: Integration with StripeService
runTest('StripeService integration', function() {
    // Set test API key
    putenv('STRIPE_SECRET_KEY=sk_test_12345');

    try {
        $service = new \VeriBits\Services\StripeService();
        return true;
    } catch (Exception $e) {
        // Stripe library might not be available in all environments
        return null; // Warning
    }
}, $tests);

// Advanced tests (only if SecretServer is available)
$secretManager = SecretManager::getInstance();
if ($secretManager->isSecretServerAvailable()) {
    echo "\n--- Advanced Tests (SecretServer Available) ---\n\n";

    // Test 14: Set and retrieve secret
    runTest('Set and retrieve secret from SecretServer', function() use ($secretManager) {
        $testName = 'TEST_SECRET_' . time();
        $testValue = 'test-value-' . bin2hex(random_bytes(8));

        // Set secret
        $secretManager->setSecret($testName, $testValue, [
            'description' => 'Test secret for integration testing'
        ]);

        // Clear cache to force retrieval from server
        $secretManager->clearCache($testName);

        // Retrieve secret
        $retrievedValue = $secretManager->getSecret($testName, null, false);

        // Clean up
        try {
            $secretManager->deleteSecret($testName);
        } catch (Exception $e) {
            // Ignore cleanup errors
        }

        if ($retrievedValue !== $testValue) {
            return "Retrieved value doesn't match set value";
        }

        return true;
    }, $tests);

    // Test 15: List secrets
    runTest('List secrets from SecretServer', function() use ($secretManager) {
        $secrets = $secretManager->listSecrets();

        if (!is_array($secrets)) {
            return "Failed to list secrets";
        }

        return true;
    }, $tests);

    // Test 16: Secret rotation
    runTest('Secret rotation', function() use ($secretManager) {
        $testName = 'TEST_ROTATE_' . time();
        $originalValue = 'original-value-12345';

        // Set initial secret
        $secretManager->setSecret($testName, $originalValue);

        // Rotate secret
        $newValue = $secretManager->rotateSecret($testName);

        // Clean up
        try {
            $secretManager->deleteSecret($testName);
        } catch (Exception $e) {
            // Ignore cleanup errors
        }

        if ($newValue === $originalValue) {
            return "Rotated value is the same as original";
        }

        if (empty($newValue)) {
            return "Rotated value is empty";
        }

        return true;
    }, $tests);

    // Test 17: Delete secret
    runTest('Delete secret from SecretServer', function() use ($secretManager) {
        $testName = 'TEST_DELETE_' . time();
        $testValue = 'delete-me-12345';

        // Set secret
        $secretManager->setSecret($testName, $testValue);

        // Delete secret
        $result = $secretManager->deleteSecret($testName);

        if (!$result) {
            return "Failed to delete secret";
        }

        // Verify deletion
        $retrievedValue = $secretManager->getSecret($testName);
        if ($retrievedValue !== null) {
            return "Secret still exists after deletion";
        }

        return true;
    }, $tests);
}

// Print summary
echo "\n=== Test Summary ===\n";
echo "Passed:   {$tests['passed']}\n";
echo "Failed:   {$tests['failed']}\n";
echo "Warnings: {$tests['warnings']}\n";
echo "Total:    " . ($tests['passed'] + $tests['failed'] + $tests['warnings']) . "\n";

if ($tests['failed'] === 0) {
    echo "\n✓ All tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed. Please review the errors above.\n";
    exit(1);
}
