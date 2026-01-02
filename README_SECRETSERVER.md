# SecretServer.io Integration for VeriBits

## Quick Overview

This integration enables **veribits.com** to manage secrets securely using **secretserver.io** with automatic fallback to environment variables.

## What's Included

### 🔧 Core Services
- **SecretServerClient** - HTTP client for secretserver.io API
- **SecretManager** - Unified facade with automatic fallback

### 📝 Scripts
- **migrate-secrets-to-secretserver.php** - Migrate existing secrets
- **manage-secrets.php** - CLI for secret management
- **test-secretserver-integration.php** - Integration test suite

### 📚 Documentation
- **SECRETSERVER_QUICKSTART.md** - 5-minute quick start
- **SECRETSERVER_INTEGRATION.md** - Complete guide
- **SECRETSERVER_INTEGRATION_SUMMARY.md** - Implementation summary
- **docs/secretserver-integration.md** - Complete reference
- **docs/examples/secretmanager-usage-examples.php** - Code examples

### ✅ Updated Controllers
- **HaveIBeenPwnedController** - Uses SecretManager for HIBP_API_KEY
- **StripeService** - Uses SecretManager for STRIPE_SECRET_KEY

## Quick Start (5 minutes)

### 1. Configure
```bash
# Edit .env
SECRETSERVER_ENABLED=true
SECRETSERVER_API_URL=http://localhost:3000
SECRETSERVER_API_KEY=your-api-key-here
```

### 2. Test Connection
```bash
php scripts/manage-secrets.php health
```

### 3. Migrate Secrets
```bash
php scripts/migrate-secrets-to-secretserver.php
```

### 4. Verify
```bash
php scripts/test-secretserver-integration.php
```

## Usage

### In Your Code
```php
use VeriBits\Services\SecretManager;

$secretManager = SecretManager::getInstance();
$apiKey = $secretManager->getSecret('MY_API_KEY');
```

### CLI Commands
```bash
# List secrets
php scripts/manage-secrets.php list

# Get secret
php scripts/manage-secrets.php get HIBP_API_KEY

# Set secret
php scripts/manage-secrets.php set NEW_KEY "value"

# Rotate secret
php scripts/manage-secrets.php rotate JWT_SECRET
```

## Features

- ✅ **Automatic Fallback** - Uses environment variables if SecretServer unavailable
- ✅ **Caching** - Local memory cache with configurable TTL
- ✅ **Lazy Loading** - Secrets loaded on-demand
- ✅ **Secret Rotation** - Built-in rotation support
- ✅ **Health Monitoring** - Status checks and diagnostics
- ✅ **Complete Testing** - 17 integration tests

## File Structure

```
veribits.com/
├── app/src/Services/
│   ├── SecretServerClient.php    # HTTP client
│   └── SecretManager.php          # Facade
├── scripts/
│   ├── migrate-secrets-to-secretserver.php
│   ├── manage-secrets.php
│   └── test-secretserver-integration.php
├── docs/
│   ├── secretserver-integration.md
│   └── examples/
│       └── secretmanager-usage-examples.php
├── SECRETSERVER_QUICKSTART.md
├── SECRETSERVER_INTEGRATION.md
├── SECRETSERVER_INTEGRATION_SUMMARY.md
└── README_SECRETSERVER.md (this file)
```

## Documentation Index

1. **[SECRETSERVER_QUICKSTART.md](SECRETSERVER_QUICKSTART.md)**
   - 5-minute setup guide
   - Quick examples
   - Basic troubleshooting

2. **[SECRETSERVER_INTEGRATION.md](SECRETSERVER_INTEGRATION.md)**
   - Complete integration guide
   - Architecture overview
   - API reference
   - Security best practices
   - Troubleshooting

3. **[SECRETSERVER_INTEGRATION_SUMMARY.md](SECRETSERVER_INTEGRATION_SUMMARY.md)**
   - Implementation summary
   - File inventory
   - Integration status

4. **[docs/secretserver-integration.md](docs/secretserver-integration.md)**
   - Complete reference
   - CLI commands
   - Configuration reference

5. **[docs/examples/secretmanager-usage-examples.php](docs/examples/secretmanager-usage-examples.php)**
   - 10 usage examples
   - Controller patterns
   - Service integration

## Next Steps

1. ✅ **Setup Complete** - Integration is ready
2. 📝 **Update Controllers** - Migrate remaining controllers to use SecretManager
3. 🔄 **Setup Rotation** - Configure secret rotation schedule
4. 📊 **Configure Monitoring** - Set up alerts and metrics
5. 🚀 **Deploy** - Deploy to production

## Support

### Health Check
```bash
php scripts/manage-secrets.php health
```

### Run Tests
```bash
php scripts/test-secretserver-integration.php
```

### Check Logs
```bash
grep "SecretManager" /var/log/veribits/app.log
```

## Links

- **SecretServer.io**: /Users/ryan/development/secretserver.io
- **VeriBits**: /Users/ryan/development/veribits.com
- **SecretServer Docs**: https://secretserver.io/docs

---

**Status**: ✅ Production Ready  
**Version**: 1.0  
**Last Updated**: January 2, 2026
