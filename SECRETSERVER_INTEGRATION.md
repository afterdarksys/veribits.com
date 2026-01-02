# SecretServer.io Integration Guide

## Overview

VeriBits now integrates with **secretserver.io** for centralized, secure secret management. This integration provides:

- **Centralized Secret Storage**: Store all API keys, passwords, and sensitive credentials in one secure location
- **Automatic Fallback**: Seamlessly falls back to environment variables if secretserver.io is unavailable
- **Secret Rotation**: Built-in support for rotating secrets without application restarts
- **Caching**: Local caching for performance with configurable TTL
- **Lazy Loading**: Secrets are loaded on-demand, reducing startup overhead

## Architecture

The integration consists of two main components:

### 1. SecretServerClient (`app/src/Services/SecretServerClient.php`)

Low-level HTTP client for communicating with the secretserver.io API:

- **GET /api/v1/secrets/:name** - Retrieve a secret by name
- **POST /api/v1/secrets** - Create or update a secret
- **GET /api/v1/secrets** - List all secrets
- **DELETE /api/v1/secrets/:name** - Delete a secret

Features:
- Local memory caching with configurable TTL
- Comprehensive error handling
- Health check endpoint support
- SSL/TLS verification

### 2. SecretManager (`app/src/Services/SecretManager.php`)

High-level facade that provides unified secret access:

- **Primary source**: secretserver.io
- **Fallback source**: Environment variables
- **Graceful degradation**: Automatically falls back when secretserver.io is unavailable
- **Singleton pattern**: One instance shared across the application

## Configuration

### Environment Variables

Add these to your `.env` or `.env.production` file:

```bash
# Enable/disable secretserver.io integration
SECRETSERVER_ENABLED=true

# SecretServer.io API endpoint
SECRETSERVER_API_URL=http://localhost:3000

# API key for authentication
SECRETSERVER_API_KEY=your-api-key-here

# Enable local caching
SECRETSERVER_CACHE_ENABLED=true

# Cache TTL in seconds (default: 300 = 5 minutes)
SECRETSERVER_CACHE_TTL=300
```

### Development vs Production

**Development** (local):
```bash
SECRETSERVER_ENABLED=true
SECRETSERVER_API_URL=http://localhost:3000
```

**Production**:
```bash
SECRETSERVER_ENABLED=true
SECRETSERVER_API_URL=https://secretserver.io
```

## Usage Examples

### Basic Secret Retrieval

```php
use VeriBits\Services\SecretManager;

// Get singleton instance
$secretManager = SecretManager::getInstance();

// Get a secret with fallback to environment
$apiKey = $secretManager->getSecret('HIBP_API_KEY');

// Get a required secret (throws exception if missing)
$dbPassword = $secretManager->getRequiredSecret('DB_PASSWORD');

// Get a secret with default value
$timeout = $secretManager->getSecret('API_TIMEOUT', '30');
```

### Setting Secrets

```php
// Set a secret in secretserver.io
$secretManager->setSecret('NEW_API_KEY', 'sk-1234567890abcdef', [
    'description' => 'New API key for external service',
    'tags' => ['api', 'production']
]);
```

### Secret Rotation

```php
// Rotate a secret with default random generator
$newValue = $secretManager->rotateSecret('JWT_SECRET');

// Rotate with custom generator
$newValue = $secretManager->rotateSecret('API_KEY', function() {
    return 'custom-' . bin2hex(random_bytes(16));
});
```

### Listing and Managing Secrets

```php
// List all secrets
$secrets = $secretManager->listSecrets();
foreach ($secrets as $secret) {
    echo "Secret: {$secret['name']}\n";
}

// Delete a secret
$secretManager->deleteSecret('OLD_API_KEY');

// Clear cache
$secretManager->clearCache(); // Clear all
$secretManager->clearCache('SPECIFIC_KEY'); // Clear specific
```

### Checking Health Status

```php
$status = $secretManager->getHealthStatus();
/*
Array (
    [secretserver_enabled] => true
    [secretserver_available] => true
    [cached_secrets] => 5
    [fallback_mode] => false
)
*/
```

### Prefetching Secrets

Warm up the cache at application startup:

```php
// Prefetch commonly used secrets
$secrets = $secretManager->prefetchSecrets([
    'HIBP_API_KEY',
    'STRIPE_SECRET_KEY',
    'JWT_SECRET',
    'DB_PASSWORD'
]);
```

## Updating Existing Controllers

### Before (Direct Environment Access)

```php
class HaveIBeenPwnedController {
    private string $hibpApiKey;

    public function __construct() {
        $this->hibpApiKey = $_ENV['HIBP_API_KEY'] ?? '';
    }
}
```

### After (SecretManager Integration)

```php
use VeriBits\Services\SecretManager;

class HaveIBeenPwnedController {
    private string $hibpApiKey;
    private SecretManager $secretManager;

    public function __construct() {
        $this->secretManager = SecretManager::getInstance();
        $this->hibpApiKey = $this->secretManager->getSecret('HIBP_API_KEY', '') ?? '';
    }
}
```

## Migration Guide

### Step 1: Update Configuration

Add secretserver.io configuration to your `.env` file:

```bash
cp .env.production.example .env
# Edit .env and set SECRETSERVER_* variables
```

### Step 2: Store Secrets in SecretServer

Use the SecretManager to migrate existing secrets:

```php
$secretManager = SecretManager::getInstance();

// Migrate secrets from environment to secretserver.io
$secrets = [
    'HIBP_API_KEY' => getenv('HIBP_API_KEY'),
    'STRIPE_SECRET_KEY' => getenv('STRIPE_SECRET_KEY'),
    'JWT_SECRET' => getenv('JWT_SECRET'),
    'DB_PASSWORD' => getenv('DB_PASSWORD')
];

foreach ($secrets as $name => $value) {
    if (!empty($value)) {
        $secretManager->setSecret($name, $value, [
            'description' => "Migrated from environment on " . date('Y-m-d')
        ]);
    }
}
```

### Step 3: Update Controllers

Update each controller to use SecretManager instead of direct environment access.

### Step 4: Test Fallback Behavior

1. **Test with SecretServer available**:
   - Verify secrets are loaded from secretserver.io
   - Check logs for "Retrieved from SecretServer" messages

2. **Test with SecretServer unavailable**:
   - Stop secretserver.io
   - Verify application continues to work using environment variables
   - Check logs for "falling back to environment" warnings

3. **Test with missing secrets**:
   - Remove a required secret from both sources
   - Verify appropriate error handling

## Security Best Practices

### 1. API Key Management

- Store `SECRETSERVER_API_KEY` in a secure vault (AWS Secrets Manager, OCI Vault, etc.)
- Rotate API keys regularly using the rotation feature
- Use different API keys for development and production

### 2. Network Security

- **Production**: Always use HTTPS for `SECRETSERVER_API_URL`
- **Development**: HTTP is acceptable for localhost only
- Configure firewall rules to restrict access to secretserver.io

### 3. Secret Rotation

Implement a rotation schedule:

```php
// Rotate secrets on a schedule (e.g., monthly cron job)
$secretsToRotate = ['JWT_SECRET', 'API_KEY'];

foreach ($secretsToRotate as $secretName) {
    try {
        $newValue = $secretManager->rotateSecret($secretName);
        Logger::info("Rotated secret: $secretName");

        // Update dependent services
        // notifyDependentServices($secretName, $newValue);
    } catch (\Exception $e) {
        Logger::error("Failed to rotate secret: $secretName", [
            'error' => $e->getMessage()
        ]);
    }
}
```

### 4. Audit Logging

All secret operations are automatically logged:

- Secret retrieval (debug level)
- Secret creation/update (info level)
- Secret rotation (info level)
- Secret deletion (info level)
- Errors and fallbacks (warning/error level)

Review logs regularly:

```bash
grep "SecretManager" /var/log/veribits/app.log
```

## Troubleshooting

### Issue: Secrets not loading from SecretServer

**Symptoms**: Application uses environment variables instead of secretserver.io

**Solutions**:
1. Check `SECRETSERVER_ENABLED=true` in `.env`
2. Verify `SECRETSERVER_API_URL` is correct
3. Confirm `SECRETSERVER_API_KEY` is valid
4. Test connectivity: `curl -H "X-API-Key: YOUR_KEY" $SECRETSERVER_API_URL/health`
5. Check logs for connection errors

### Issue: Performance degradation

**Symptoms**: Slow API responses

**Solutions**:
1. Enable caching: `SECRETSERVER_CACHE_ENABLED=true`
2. Increase cache TTL: `SECRETSERVER_CACHE_TTL=600` (10 minutes)
3. Use `prefetchSecrets()` to warm cache at startup
4. Consider deploying secretserver.io closer to your application

### Issue: Authentication failures

**Symptoms**: "SecretServer authentication failed" errors

**Solutions**:
1. Verify API key is correct
2. Check API key has not expired
3. Confirm API key has appropriate permissions
4. Review secretserver.io logs for authentication attempts

### Issue: Secrets out of sync

**Symptoms**: Application using stale secret values

**Solutions**:
1. Clear cache: `$secretManager->clearCache()`
2. Reduce cache TTL for frequently rotated secrets
3. Implement cache invalidation on secret updates
4. Restart application after critical secret changes

## API Reference

### SecretManager Methods

#### `getInstance(): SecretManager`
Get singleton instance.

#### `getSecret(string $name, ?string $default = null, bool $required = false): ?string`
Retrieve a secret by name.

#### `getRequiredSecret(string $name): string`
Retrieve a required secret (throws if missing).

#### `setSecret(string $name, string $value, array $metadata = []): bool`
Create or update a secret.

#### `rotateSecret(string $name, ?callable $generator = null, int $length = 32): string`
Rotate a secret with optional custom generator.

#### `listSecrets(): array`
List all secrets.

#### `deleteSecret(string $name): bool`
Delete a secret.

#### `clearCache(?string $name = null): void`
Clear cache for one or all secrets.

#### `isSecretServerAvailable(): bool`
Check if secretserver.io is reachable.

#### `getHealthStatus(): array`
Get detailed health status.

#### `prefetchSecrets(array $names): array`
Prefetch multiple secrets.

## Performance Considerations

### Caching Strategy

- **Memory cache**: Fast, but lost on application restart
- **TTL-based**: Balances freshness and performance
- **Lazy loading**: Secrets loaded only when needed

### Recommended Settings

**High-traffic production**:
```bash
SECRETSERVER_CACHE_ENABLED=true
SECRETSERVER_CACHE_TTL=600  # 10 minutes
```

**Development**:
```bash
SECRETSERVER_CACHE_ENABLED=true
SECRETSERVER_CACHE_TTL=60   # 1 minute
```

**Secret rotation workflows**:
```bash
SECRETSERVER_CACHE_ENABLED=false  # Disable during rotation
```

## Integration with Other Services

### Stripe Integration

```php
use VeriBits\Services\SecretManager;
use VeriBits\Services\StripeService;

$secretManager = SecretManager::getInstance();
$stripeKey = $secretManager->getRequiredSecret('STRIPE_SECRET_KEY');

// Use in StripeService
$stripe = new StripeService();
```

### Database Connections

```php
$dbPassword = $secretManager->getRequiredSecret('DB_PASSWORD');
$dsn = "pgsql:host=localhost;dbname=veribits";
$pdo = new PDO($dsn, 'dbuser', $dbPassword);
```

### External APIs

```php
$apiKey = $secretManager->getSecret('EXTERNAL_API_KEY');
$client = new ExternalApiClient($apiKey);
```

## Deployment Checklist

- [ ] Update `.env` with secretserver.io configuration
- [ ] Set `SECRETSERVER_API_URL` to production URL
- [ ] Set `SECRETSERVER_API_KEY` to production API key
- [ ] Migrate all secrets to secretserver.io
- [ ] Update controllers to use SecretManager
- [ ] Test secret retrieval in staging environment
- [ ] Test fallback behavior (disable secretserver.io temporarily)
- [ ] Configure monitoring and alerts for secret access failures
- [ ] Document secret rotation schedule
- [ ] Set up automated secret rotation (if applicable)
- [ ] Review and update security policies
- [ ] Train team on new secret management workflow

## Support and Resources

- **SecretServer.io Documentation**: https://secretserver.io/docs
- **VeriBits Repository**: /Users/ryan/development/veribits.com
- **SecretServer.io Repository**: /Users/ryan/development/secretserver.io

## License

This integration is part of the VeriBits platform. See LICENSE file for details.
