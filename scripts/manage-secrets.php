#!/usr/bin/env php
<?php
/**
 * Manage Secrets in SecretServer.io
 *
 * Command-line tool for managing secrets stored in secretserver.io
 *
 * Usage:
 *   php scripts/manage-secrets.php <command> [options]
 *
 * Commands:
 *   list                    List all secrets
 *   get <name>              Get a secret value
 *   set <name> <value>      Set a secret
 *   delete <name>           Delete a secret
 *   rotate <name>           Rotate a secret (generate new random value)
 *   health                  Check SecretServer health status
 *   clear-cache [name]      Clear cache for secret or all secrets
 */

require_once __DIR__ . '/../vendor/autoload.php';

use VeriBits\Services\SecretManager;
use VeriBits\Utils\Config;

// Parse command line arguments
$command = $argv[1] ?? 'help';
$arg1 = $argv[2] ?? null;
$arg2 = $argv[3] ?? null;

// Load configuration
Config::load();

// Initialize SecretManager
try {
    $secretManager = SecretManager::getInstance();
} catch (Exception $e) {
    echo "ERROR: Failed to initialize SecretManager: {$e->getMessage()}\n";
    exit(1);
}

// Execute command
switch ($command) {
    case 'list':
        handleList($secretManager);
        break;

    case 'get':
        handleGet($secretManager, $arg1);
        break;

    case 'set':
        handleSet($secretManager, $arg1, $arg2);
        break;

    case 'delete':
        handleDelete($secretManager, $arg1);
        break;

    case 'rotate':
        handleRotate($secretManager, $arg1);
        break;

    case 'health':
        handleHealth($secretManager);
        break;

    case 'clear-cache':
        handleClearCache($secretManager, $arg1);
        break;

    case 'help':
    default:
        showHelp();
        break;
}

/**
 * List all secrets
 */
function handleList(SecretManager $secretManager): void {
    checkSecretServerAvailable($secretManager);

    try {
        echo "Listing secrets from SecretServer...\n\n";
        $secrets = $secretManager->listSecrets();

        if (empty($secrets)) {
            echo "No secrets found.\n";
            return;
        }

        echo "Found " . count($secrets) . " secret(s):\n\n";

        foreach ($secrets as $index => $secret) {
            $num = $index + 1;
            echo "[$num] {$secret['name']}\n";
            if (!empty($secret['description'])) {
                echo "    Description: {$secret['description']}\n";
            }
            if (!empty($secret['tags'])) {
                echo "    Tags: " . implode(', ', $secret['tags']) . "\n";
            }
            if (!empty($secret['created_at'])) {
                echo "    Created: {$secret['created_at']}\n";
            }
            echo "\n";
        }

    } catch (Exception $e) {
        echo "ERROR: {$e->getMessage()}\n";
        exit(1);
    }
}

/**
 * Get a secret value
 */
function handleGet(SecretManager $secretManager, ?string $name): void {
    if (empty($name)) {
        echo "ERROR: Secret name is required\n";
        echo "Usage: php manage-secrets.php get <name>\n";
        exit(1);
    }

    try {
        echo "Retrieving secret '$name'...\n";
        $value = $secretManager->getSecret($name);

        if ($value === null) {
            echo "Secret not found.\n";
            exit(1);
        }

        echo "\nSecret value:\n";
        echo "$value\n";

    } catch (Exception $e) {
        echo "ERROR: {$e->getMessage()}\n";
        exit(1);
    }
}

/**
 * Set a secret
 */
function handleSet(SecretManager $secretManager, ?string $name, ?string $value): void {
    checkSecretServerAvailable($secretManager);

    if (empty($name)) {
        echo "ERROR: Secret name is required\n";
        echo "Usage: php manage-secrets.php set <name> <value>\n";
        exit(1);
    }

    if ($value === null) {
        // Read from stdin if value not provided
        echo "Enter secret value: ";
        $value = trim(fgets(STDIN));

        if (empty($value)) {
            echo "ERROR: Secret value cannot be empty\n";
            exit(1);
        }
    }

    try {
        echo "Setting secret '$name'...\n";

        // Prompt for optional metadata
        echo "Enter description (optional): ";
        $description = trim(fgets(STDIN));

        echo "Enter tags (comma-separated, optional): ";
        $tagsInput = trim(fgets(STDIN));
        $tags = !empty($tagsInput) ? array_map('trim', explode(',', $tagsInput)) : [];

        $metadata = [];
        if (!empty($description)) {
            $metadata['description'] = $description;
        }
        if (!empty($tags)) {
            $metadata['tags'] = $tags;
        }

        $secretManager->setSecret($name, $value, $metadata);
        echo "Secret '$name' has been set successfully.\n";

    } catch (Exception $e) {
        echo "ERROR: {$e->getMessage()}\n";
        exit(1);
    }
}

/**
 * Delete a secret
 */
function handleDelete(SecretManager $secretManager, ?string $name): void {
    checkSecretServerAvailable($secretManager);

    if (empty($name)) {
        echo "ERROR: Secret name is required\n";
        echo "Usage: php manage-secrets.php delete <name>\n";
        exit(1);
    }

    try {
        echo "Are you sure you want to delete secret '$name'? (yes/no): ";
        $confirm = trim(fgets(STDIN));

        if (strtolower($confirm) !== 'yes') {
            echo "Aborted.\n";
            exit(0);
        }

        echo "Deleting secret '$name'...\n";
        $secretManager->deleteSecret($name);
        echo "Secret '$name' has been deleted successfully.\n";

    } catch (Exception $e) {
        echo "ERROR: {$e->getMessage()}\n";
        exit(1);
    }
}

/**
 * Rotate a secret
 */
function handleRotate(SecretManager $secretManager, ?string $name): void {
    checkSecretServerAvailable($secretManager);

    if (empty($name)) {
        echo "ERROR: Secret name is required\n";
        echo "Usage: php manage-secrets.php rotate <name>\n";
        exit(1);
    }

    try {
        echo "Rotating secret '$name'...\n";
        echo "This will generate a new random value. Continue? (yes/no): ";
        $confirm = trim(fgets(STDIN));

        if (strtolower($confirm) !== 'yes') {
            echo "Aborted.\n";
            exit(0);
        }

        $newValue = $secretManager->rotateSecret($name);
        echo "Secret '$name' has been rotated successfully.\n";
        echo "\nNew value:\n";
        echo "$newValue\n";
        echo "\nIMPORTANT: Update any services using this secret!\n";

    } catch (Exception $e) {
        echo "ERROR: {$e->getMessage()}\n";
        exit(1);
    }
}

/**
 * Check health status
 */
function handleHealth(SecretManager $secretManager): void {
    echo "Checking SecretServer health...\n\n";

    $status = $secretManager->getHealthStatus();

    echo "Status:\n";
    echo "  Enabled:     " . ($status['secretserver_enabled'] ? 'Yes' : 'No') . "\n";
    echo "  Available:   " . ($status['secretserver_available'] ? 'Yes' : 'No') . "\n";
    echo "  Cached:      {$status['cached_secrets']} secret(s)\n";
    echo "  Fallback:    " . ($status['fallback_mode'] ? 'Yes (using environment variables)' : 'No') . "\n";

    if (!$status['secretserver_enabled']) {
        echo "\nWARNING: SecretServer is disabled. Set SECRETSERVER_ENABLED=true in .env\n";
    }

    if ($status['secretserver_enabled'] && !$status['secretserver_available']) {
        echo "\nERROR: SecretServer is not available. Check:\n";
        echo "  - SECRETSERVER_API_URL: " . Config::get('SECRETSERVER_API_URL') . "\n";
        echo "  - SECRETSERVER_API_KEY is set correctly\n";
        echo "  - SecretServer is running and accessible\n";
        exit(1);
    }

    echo "\nSecretServer is healthy!\n";
}

/**
 * Clear cache
 */
function handleClearCache(SecretManager $secretManager, ?string $name): void {
    if ($name === null) {
        echo "Clearing all cached secrets...\n";
        $secretManager->clearCache();
        echo "All cached secrets have been cleared.\n";
    } else {
        echo "Clearing cached secret '$name'...\n";
        $secretManager->clearCache($name);
        echo "Cached secret '$name' has been cleared.\n";
    }
}

/**
 * Check if SecretServer is available
 */
function checkSecretServerAvailable(SecretManager $secretManager): void {
    if (!$secretManager->isSecretServerEnabled()) {
        echo "ERROR: SecretServer is not enabled. Set SECRETSERVER_ENABLED=true in .env\n";
        exit(1);
    }

    if (!$secretManager->isSecretServerAvailable()) {
        echo "ERROR: SecretServer is not available. Check SECRETSERVER_API_URL and SECRETSERVER_API_KEY\n";
        exit(1);
    }
}

/**
 * Show help
 */
function showHelp(): void {
    echo <<<HELP
Manage Secrets in SecretServer.io

Usage:
  php scripts/manage-secrets.php <command> [options]

Commands:
  list                    List all secrets
  get <name>              Get a secret value
  set <name> [value]      Set a secret (prompts for value if not provided)
  delete <name>           Delete a secret
  rotate <name>           Rotate a secret (generate new random value)
  health                  Check SecretServer health status
  clear-cache [name]      Clear cache for secret or all secrets
  help                    Show this help message

Examples:
  # List all secrets
  php scripts/manage-secrets.php list

  # Get a secret
  php scripts/manage-secrets.php get HIBP_API_KEY

  # Set a secret (interactive)
  php scripts/manage-secrets.php set NEW_API_KEY

  # Set a secret (with value)
  php scripts/manage-secrets.php set NEW_API_KEY "sk-1234567890"

  # Rotate a secret
  php scripts/manage-secrets.php rotate JWT_SECRET

  # Delete a secret
  php scripts/manage-secrets.php delete OLD_API_KEY

  # Check health
  php scripts/manage-secrets.php health

  # Clear cache
  php scripts/manage-secrets.php clear-cache
  php scripts/manage-secrets.php clear-cache HIBP_API_KEY

HELP;
}
