# SecretServer.io Integration - Complete Reference

## Overview

VeriBits now integrates with **secretserver.io** for centralized, secure secret management. This integration provides seamless secret access with automatic fallback to environment variables, ensuring high availability and graceful degradation.

## Quick Links

- **[Quick Start Guide](../SECRETSERVER_QUICKSTART.md)** - Get started in 5 minutes
- **[Full Documentation](../SECRETSERVER_INTEGRATION.md)** - Comprehensive integration guide
- **Migration Script** - `/scripts/migrate-secrets-to-secretserver.php`
- **Management CLI** - `/scripts/manage-secrets.php`
- **Test Suite** - `/scripts/test-secretserver-integration.php`

## Files Created

### Core Services

1. **`/app/src/Services/SecretServerClient.php`**
   - Low-level HTTP client for secretserver.io API
   - Handles GET, POST, DELETE operations for secrets
   - Local memory caching with configurable TTL
   - Health check and availability monitoring

2. **`/app/src/Services/SecretManager.php`**
   - High-level facade for unified secret access
   - Automatic fallback from SecretServer to environment variables
   - Singleton pattern for application-wide access
   - Secret rotation, prefetching, and cache management

### Updated Controllers

1. **`/app/src/Controllers/HaveIBeenPwnedController.php`**
   - Updated to use SecretManager for HIBP_API_KEY
   - Demonstrates integration pattern for existing controllers

2. **`/app/src/Services/StripeService.php`**
   - Updated to use SecretManager for STRIPE_SECRET_KEY
   - Shows payment service integration

### Configuration

1. **`/.env.production.example`**
   - Added SecretServer configuration variables
   - Documented fallback behavior

### Scripts

1. **`/scripts/migrate-secrets-to-secretserver.php`**
   - Migrates existing environment variables to secretserver.io
   - Supports dry-run and force modes
   - Handles common secrets (JWT, DB, API keys)

2. **`/scripts/manage-secrets.php`**
   - CLI tool for secret management
   - Commands: list, get, set, delete, rotate, health, clear-cache

3. **`/scripts/test-secretserver-integration.php`**
   - Comprehensive test suite for integration
   - Tests all major functionality
   - Validates both SecretServer and fallback modes

### Documentation

1. **`/SECRETSERVER_INTEGRATION.md`**
   - Complete integration guide
   - Usage examples and API reference
   - Security best practices
   - Troubleshooting guide

2. **`/SECRETSERVER_QUICKSTART.md`**
   - Quick start guide (5 minutes)
   - Essential configuration steps
   - Common usage patterns

3. **`/docs/secretserver-integration.md`** (this file)
   - Complete reference and file inventory

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Application Layer                         │
│  (Controllers, Services)                                     │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│              SecretManager (Facade)                          │
│  - Unified secret access                                     │
│  - Automatic fallback                                        │
│  - Cache management                                          │
└────────────┬───────────────────────────┬────────────────────┘
             │                           │
             ▼                           ▼
┌────────────────────────┐  ┌───────────────────────────────┐
│  SecretServerClient    │  │  Environment Variables        │
│  - API communication   │  │  - Fallback source            │
│  - HTTP requests       │  │  - Direct access via Config   │
│  - Local caching       │  │                               │
└────────────┬───────────┘  └───────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────┐
│              secretserver.io API                             │
│  - Centralized secret storage                                │
│  - REST API endpoints                                        │
│  - Authentication & authorization                            │
└─────────────────────────────────────────────────────────────┘
```

## Secret Flow

### 1. Normal Operation (SecretServer Available)

```
Application Request
    ↓
SecretManager.getSecret()
    ↓
Check memory cache → Hit? → Return cached value
    ↓ Miss
SecretServerClient.getSecret()
    ↓
HTTP GET /api/v1/secrets/:name
    ↓
secretserver.io → Return secret
    ↓
Cache result
    ↓
Return to application
```

### 2. Fallback Mode (SecretServer Unavailable)

```
Application Request
    ↓
SecretManager.getSecret()
    ↓
SecretServerClient.getSecret() → API Error
    ↓
Log warning
    ↓
Config.get() (Environment Variables)
    ↓
Return to application
```

## Configuration Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `SECRETSERVER_ENABLED` | `true` | Enable/disable SecretServer integration |
| `SECRETSERVER_API_URL` | `http://localhost:3000` | SecretServer API endpoint |
| `SECRETSERVER_API_KEY` | - | API key for authentication (required) |
| `SECRETSERVER_CACHE_ENABLED` | `true` | Enable local memory caching |
| `SECRETSERVER_CACHE_TTL` | `300` | Cache TTL in seconds (5 minutes) |

## API Endpoints

SecretServer.io provides these endpoints:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/secrets` | List all secrets |
| GET | `/api/v1/secrets/:name` | Get secret by name |
| POST | `/api/v1/secrets` | Create/update secret |
| DELETE | `/api/v1/secrets/:name` | Delete secret |
| GET | `/health` | Health check |

## Usage Examples

### Basic Secret Retrieval

```php
use VeriBits\Services\SecretManager;

$secretManager = SecretManager::getInstance();

// Get secret with fallback
$apiKey = $secretManager->getSecret('HIBP_API_KEY');

// Get required secret
$dbPassword = $secretManager->getRequiredSecret('DB_PASSWORD');

// Get with default
$timeout = $secretManager->getSecret('API_TIMEOUT', '30');
```

### Secret Management

```php
// Set a secret
$secretManager->setSecret('NEW_KEY', 'value', [
    'description' => 'Description here',
    'tags' => ['api', 'production']
]);

// Rotate a secret
$newValue = $secretManager->rotateSecret('JWT_SECRET');

// List secrets
$secrets = $secretManager->listSecrets();

// Delete a secret
$secretManager->deleteSecret('OLD_KEY');
```

### Controller Integration

```php
class MyController {
    private SecretManager $secretManager;
    private string $apiKey;

    public function __construct() {
        $this->secretManager = SecretManager::getInstance();
        $this->apiKey = $this->secretManager->getSecret('MY_API_KEY', '');

        if (empty($this->apiKey)) {
            Logger::error('MY_API_KEY not configured');
        }
    }
}
```

## CLI Commands

### Health Check

```bash
php scripts/manage-secrets.php health
```

### List Secrets

```bash
php scripts/manage-secrets.php list
```

### Get Secret

```bash
php scripts/manage-secrets.php get HIBP_API_KEY
```

### Set Secret

```bash
# Interactive mode
php scripts/manage-secrets.php set NEW_KEY

# Direct mode
php scripts/manage-secrets.php set NEW_KEY "value-here"
```

### Rotate Secret

```bash
php scripts/manage-secrets.php rotate JWT_SECRET
```

### Delete Secret

```bash
php scripts/manage-secrets.php delete OLD_KEY
```

### Migration

```bash
# Dry run
php scripts/migrate-secrets-to-secretserver.php --dry-run

# Perform migration
php scripts/migrate-secrets-to-secretserver.php

# Force overwrite
php scripts/migrate-secrets-to-secretserver.php --force
```

### Testing

```bash
php scripts/test-secretserver-integration.php
```

## Integration Checklist

- [x] Create SecretServerClient service
- [x] Create SecretManager facade
- [x] Update HaveIBeenPwnedController
- [x] Update StripeService
- [x] Add configuration to .env.example
- [x] Create migration script
- [x] Create management CLI
- [x] Create test suite
- [x] Write documentation
- [ ] Update remaining controllers
- [ ] Set up secret rotation schedule
- [ ] Configure monitoring
- [ ] Deploy to production

## Migration Path

### Phase 1: Setup (Completed)
- ✓ Install and configure secretserver.io
- ✓ Create integration code
- ✓ Update .env configuration

### Phase 2: Migration (In Progress)
- ✓ Migrate critical secrets (JWT, DB, API keys)
- [ ] Update all controllers to use SecretManager
- [ ] Test in staging environment

### Phase 3: Deployment
- [ ] Deploy to production
- [ ] Monitor for issues
- [ ] Remove secrets from .env files

### Phase 4: Optimization
- [ ] Set up secret rotation schedule
- [ ] Configure caching strategy
- [ ] Implement monitoring and alerts

## Security Considerations

1. **API Key Protection**
   - Store SECRETSERVER_API_KEY in secure vault
   - Rotate API keys regularly
   - Use different keys for dev/staging/prod

2. **Network Security**
   - Always use HTTPS in production
   - Configure firewall rules
   - Restrict API access by IP

3. **Secret Rotation**
   - Rotate secrets on schedule
   - Document rotation procedures
   - Test rotation in staging first

4. **Audit Logging**
   - All operations are logged
   - Review logs regularly
   - Set up alerts for failures

## Monitoring

### Key Metrics

- Secret retrieval latency
- Cache hit rate
- Fallback frequency
- API errors

### Log Patterns

```bash
# Secret retrieval
grep "SecretManager: Retrieved" /var/log/veribits/app.log

# Fallback events
grep "falling back to environment" /var/log/veribits/app.log

# Errors
grep "ERROR.*Secret" /var/log/veribits/app.log
```

### Health Checks

```bash
# Application health
php scripts/manage-secrets.php health

# API connectivity
curl -H "X-API-Key: $KEY" $URL/health

# Integration tests
php scripts/test-secretserver-integration.php
```

## Troubleshooting

### Common Issues

1. **SecretServer not available**
   - Check SECRETSERVER_API_URL
   - Verify API key
   - Test connectivity

2. **Secrets not found**
   - Run migration script
   - Check environment fallback
   - Verify secret names

3. **Performance degradation**
   - Enable caching
   - Increase cache TTL
   - Use prefetching

See [SECRETSERVER_INTEGRATION.md](../SECRETSERVER_INTEGRATION.md#troubleshooting) for detailed troubleshooting.

## Support

- **Documentation**: [SECRETSERVER_INTEGRATION.md](../SECRETSERVER_INTEGRATION.md)
- **Quick Start**: [SECRETSERVER_QUICKSTART.md](../SECRETSERVER_QUICKSTART.md)
- **SecretServer Docs**: https://secretserver.io/docs

## License

This integration is part of the VeriBits platform. See LICENSE file for details.
