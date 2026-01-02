# SecretServer.io Integration - Quick Start

This guide will get you up and running with secretserver.io integration in 5 minutes.

## Prerequisites

- secretserver.io running and accessible
- API key for secretserver.io
- PHP 8.1+
- VeriBits codebase

## Step 1: Configure Environment Variables (2 minutes)

Edit your `.env` file:

```bash
# Enable SecretServer integration
SECRETSERVER_ENABLED=true

# Set your secretserver.io endpoint
SECRETSERVER_API_URL=http://localhost:3000  # or https://secretserver.io for production

# Set your API key
SECRETSERVER_API_KEY=your-api-key-here

# Enable caching for performance
SECRETSERVER_CACHE_ENABLED=true
SECRETSERVER_CACHE_TTL=300
```

## Step 2: Verify Connection (30 seconds)

Test the connection to secretserver.io:

```bash
php scripts/manage-secrets.php health
```

Expected output:
```
Checking SecretServer health...

Status:
  Enabled:     Yes
  Available:   Yes
  Cached:      0 secret(s)
  Fallback:    No

SecretServer is healthy!
```

## Step 3: Migrate Existing Secrets (1 minute)

Preview what will be migrated:

```bash
php scripts/migrate-secrets-to-secretserver.php --dry-run
```

Perform the migration:

```bash
php scripts/migrate-secrets-to-secretserver.php
```

This will migrate these secrets from environment to secretserver.io:
- JWT_SECRET
- DB_PASSWORD
- HIBP_API_KEY
- STRIPE_SECRET_KEY
- STRIPE_WEBHOOK_SECRET
- ID_VERIFY_API_KEY
- AWS_ACCESS_KEY_ID
- AWS_SECRET_ACCESS_KEY

## Step 4: Verify Secrets (30 seconds)

List all secrets in secretserver.io:

```bash
php scripts/manage-secrets.php list
```

Get a specific secret:

```bash
php scripts/manage-secrets.php get HIBP_API_KEY
```

## Step 5: Test Your Application (1 minute)

The integration is now active! Test your application:

```bash
# Start your PHP server
php -S localhost:8000 -t app/public

# Test an endpoint that uses secrets (e.g., HIBP)
curl -X POST http://localhost:8000/api/hibp/check-email \
  -H "Content-Type: application/json" \
  -d '{"email": "test@example.com"}'
```

Check the logs to verify secrets are loaded from SecretServer:

```bash
grep "SecretManager" /var/log/veribits/app.log
```

You should see:
```
[INFO] SecretManager: SecretServer is available and enabled
[INFO] HIBP_API_KEY loaded successfully [source=SecretServer]
```

## Usage Examples

### Get a Secret in Your Code

```php
use VeriBits\Services\SecretManager;

$secretManager = SecretManager::getInstance();

// Get a secret (tries SecretServer, falls back to environment)
$apiKey = $secretManager->getSecret('HIBP_API_KEY');

// Get a required secret (throws exception if missing)
$dbPassword = $secretManager->getRequiredSecret('DB_PASSWORD');

// Get with default value
$timeout = $secretManager->getSecret('API_TIMEOUT', '30');
```

### Manage Secrets via CLI

```bash
# List all secrets
php scripts/manage-secrets.php list

# Set a new secret
php scripts/manage-secrets.php set NEW_API_KEY "sk-1234567890"

# Rotate a secret (generates new random value)
php scripts/manage-secrets.php rotate JWT_SECRET

# Delete a secret
php scripts/manage-secrets.php delete OLD_API_KEY

# Clear cache
php scripts/manage-secrets.php clear-cache
```

## Troubleshooting

### "SecretServer is not available"

**Check:**
1. Is secretserver.io running?
   ```bash
   curl http://localhost:3000/health
   ```

2. Is the API URL correct?
   ```bash
   echo $SECRETSERVER_API_URL
   ```

3. Is the API key valid?
   ```bash
   curl -H "X-API-Key: $SECRETSERVER_API_KEY" $SECRETSERVER_API_URL/api/v1/secrets
   ```

### "Secret not found"

The application will automatically fall back to environment variables if a secret is not in secretserver.io.

**To fix:**
```bash
# Add the secret to secretserver.io
php scripts/manage-secrets.php set MISSING_SECRET "value-here"

# Or migrate from environment
php scripts/migrate-secrets-to-secretserver.php
```

### Performance Issues

**Enable caching:**
```bash
SECRETSERVER_CACHE_ENABLED=true
SECRETSERVER_CACHE_TTL=600  # 10 minutes
```

**Prefetch secrets at startup:**
```php
$secretManager->prefetchSecrets([
    'HIBP_API_KEY',
    'STRIPE_SECRET_KEY',
    'JWT_SECRET'
]);
```

## What Controllers Use SecretManager?

Currently integrated:
- ✅ **HaveIBeenPwnedController** - HIBP_API_KEY
- ✅ **StripeService** - STRIPE_SECRET_KEY

To integrate more controllers, see: [SECRETSERVER_INTEGRATION.md](SECRETSERVER_INTEGRATION.md#updating-existing-controllers)

## Next Steps

1. **Update remaining controllers** to use SecretManager
2. **Set up secret rotation** schedule
3. **Remove secrets from .env** after confirming they work from secretserver.io
4. **Configure monitoring** for secret access failures
5. **Document your secret rotation policy**

## Resources

- **Full Documentation**: [SECRETSERVER_INTEGRATION.md](SECRETSERVER_INTEGRATION.md)
- **SecretServer.io Docs**: https://secretserver.io/docs
- **Migration Script**: [scripts/migrate-secrets-to-secretserver.php](scripts/migrate-secrets-to-secretserver.php)
- **Management CLI**: [scripts/manage-secrets.php](scripts/manage-secrets.php)

## Support

For issues or questions:
- Check logs: `grep "SecretManager" /var/log/veribits/app.log`
- Review documentation: [SECRETSERVER_INTEGRATION.md](SECRETSERVER_INTEGRATION.md)
- Test connection: `php scripts/manage-secrets.php health`
