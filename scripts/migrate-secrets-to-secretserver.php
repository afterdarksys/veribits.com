#!/usr/bin/env php
<?php
/**
 * Migrate Secrets to SecretServer.io
 *
 * This script migrates existing environment variables to secretserver.io
 * for centralized secret management.
 *
 * Usage:
 *   php scripts/migrate-secrets-to-secretserver.php [--dry-run] [--force]
 *
 * Options:
 *   --dry-run    Show what would be migrated without actually migrating
 *   --force      Overwrite existing secrets in secretserver.io
 */

require_once __DIR__ . '/../vendor/autoload.php';

use VeriBits\Services\SecretManager;
use VeriBits\Utils\Config;
use VeriBits\Utils\Logger;

// Parse command line arguments
$options = getopt('', ['dry-run', 'force', 'help']);
$dryRun = isset($options['dry-run']);
$force = isset($options['force']);
$showHelp = isset($options['help']);

if ($showHelp) {
    echo <<<HELP
Migrate Secrets to SecretServer.io

Usage:
  php scripts/migrate-secrets-to-secretserver.php [OPTIONS]

Options:
  --dry-run    Show what would be migrated without actually migrating
  --force      Overwrite existing secrets in secretserver.io
  --help       Show this help message

Examples:
  # Preview migration
  php scripts/migrate-secrets-to-secretserver.php --dry-run

  # Perform migration
  php scripts/migrate-secrets-to-secretserver.php

  # Force overwrite existing secrets
  php scripts/migrate-secrets-to-secretserver.php --force

HELP;
    exit(0);
}

echo "=== SecretServer.io Migration Tool ===\n\n";

// Load configuration
Config::load();

// Initialize SecretManager
try {
    $secretManager = SecretManager::getInstance();
} catch (Exception $e) {
    echo "ERROR: Failed to initialize SecretManager: {$e->getMessage()}\n";
    exit(1);
}

// Check if SecretServer is available
if (!$secretManager->isSecretServerEnabled()) {
    echo "ERROR: SecretServer is not enabled. Set SECRETSERVER_ENABLED=true in .env\n";
    exit(1);
}

if (!$secretManager->isSecretServerAvailable()) {
    echo "ERROR: SecretServer is not available. Check SECRETSERVER_API_URL and SECRETSERVER_API_KEY\n";
    echo "URL: " . Config::get('SECRETSERVER_API_URL') . "\n";
    exit(1);
}

echo "SecretServer is available and ready.\n\n";

// Define secrets to migrate
$secretsToMigrate = [
    'JWT_SECRET' => [
        'description' => 'JWT signing secret for authentication tokens',
        'tags' => ['auth', 'jwt']
    ],
    'DB_PASSWORD' => [
        'description' => 'PostgreSQL database password',
        'tags' => ['database', 'postgres']
    ],
    'HIBP_API_KEY' => [
        'description' => 'Have I Been Pwned API key',
        'tags' => ['api', 'hibp', 'security']
    ],
    'STRIPE_SECRET_KEY' => [
        'description' => 'Stripe secret key for payment processing',
        'tags' => ['payment', 'stripe']
    ],
    'STRIPE_WEBHOOK_SECRET' => [
        'description' => 'Stripe webhook signing secret',
        'tags' => ['payment', 'stripe', 'webhook']
    ],
    'ID_VERIFY_API_KEY' => [
        'description' => 'ID verification service API key',
        'tags' => ['api', 'verification']
    ],
    'AWS_ACCESS_KEY_ID' => [
        'description' => 'AWS access key for cloud services',
        'tags' => ['aws', 'cloud']
    ],
    'AWS_SECRET_ACCESS_KEY' => [
        'description' => 'AWS secret access key',
        'tags' => ['aws', 'cloud']
    ]
];

// Get existing secrets from SecretServer (if not forcing)
$existingSecrets = [];
if (!$force) {
    try {
        $existingSecrets = $secretManager->listSecrets();
        $existingSecretNames = array_column($existingSecrets, 'name');
    } catch (Exception $e) {
        echo "WARNING: Failed to list existing secrets: {$e->getMessage()}\n";
        $existingSecretNames = [];
    }
}

// Migration statistics
$stats = [
    'total' => 0,
    'migrated' => 0,
    'skipped' => 0,
    'failed' => 0,
    'missing' => 0
];

echo "Secrets to migrate: " . count($secretsToMigrate) . "\n";
if ($dryRun) {
    echo "DRY RUN MODE - No changes will be made\n";
}
echo "\n";

// Migrate each secret
foreach ($secretsToMigrate as $name => $metadata) {
    $stats['total']++;

    echo "[$stats[total]/" . count($secretsToMigrate) . "] $name... ";

    // Get value from environment
    $value = Config::get($name);

    if (empty($value)) {
        echo "SKIP (not set in environment)\n";
        $stats['missing']++;
        continue;
    }

    // Check if already exists in SecretServer
    if (!$force && in_array($name, $existingSecretNames ?? [])) {
        echo "SKIP (already exists, use --force to overwrite)\n";
        $stats['skipped']++;
        continue;
    }

    // Perform migration
    if ($dryRun) {
        echo "OK (would migrate)\n";
        echo "  Description: {$metadata['description']}\n";
        echo "  Tags: " . implode(', ', $metadata['tags']) . "\n";
        $stats['migrated']++;
    } else {
        try {
            $secretManager->setSecret($name, $value, $metadata);
            echo "OK (migrated)\n";
            $stats['migrated']++;
        } catch (Exception $e) {
            echo "FAIL ({$e->getMessage()})\n";
            $stats['failed']++;
        }
    }
}

// Print summary
echo "\n=== Migration Summary ===\n";
echo "Total secrets:    {$stats['total']}\n";
echo "Migrated:         {$stats['migrated']}\n";
echo "Skipped:          {$stats['skipped']}\n";
echo "Failed:           {$stats['failed']}\n";
echo "Missing in env:   {$stats['missing']}\n";

if ($dryRun) {
    echo "\nThis was a dry run. No changes were made.\n";
    echo "Run without --dry-run to perform the migration.\n";
} elseif ($stats['migrated'] > 0) {
    echo "\nMigration completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Verify secrets in secretserver.io\n";
    echo "2. Test application with secrets from secretserver.io\n";
    echo "3. Update controllers to use SecretManager\n";
    echo "4. Remove secrets from .env (keep in vault only)\n";
}

exit($stats['failed'] > 0 ? 1 : 0);
