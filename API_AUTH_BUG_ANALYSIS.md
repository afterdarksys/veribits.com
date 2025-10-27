# API Authentication Bug - Root Cause Analysis

## Issue
`/api/v1/auth/login` returns 422 validation errors ("email field is required") even with valid JSON POST data.

## Root Cause
**Multiple reads of `php://input` stream without caching**

PHP's `php://input` stream can only be read **once** per request. When `AuthController::login()` calls:
1. `Request::getJsonBody()` at line 96 (reads php://input)
2. `Request::getBody()` at line 104 for debug logging (tries to read php://input again - RETURNS EMPTY)

The second call returns empty string, and since Validator is created with empty data, all validations fail.

## Evidence

### Working Endpoints (proof Request class is functional)
```bash
# This works - single call to getJsonBody()
curl -X POST https://www.veribits.com/api/v1/test/request-helper \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"pass"}'
# Returns: {"json_body":{"email":"test@example.com","password":"pass"}}

# This works - inline test without Logger
curl -X POST https://www.veribits.com/api/v1/test/login-inline \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"pass"}'
# Returns: {"success":true,"message":"Manual check passed!"}
```

### Failing Endpoint
```bash
curl -X POST https://www.veribits.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"straticus1@gmail.com","password":"TestPassword123!"}'
# Returns: {"validation_errors":{"email":["The email field is required"]}}
```

## The Fix

### Modified: `/app/src/Utils/Request.php`

Added static caching to allow multiple reads:

```php
class Request {
    /**
     * Cache for the request body
     * php://input can only be read ONCE per request, so we MUST cache it
     */
    private static ?string $cachedBody = null;

    public static function getBody(): string {
        // Return cached body if already read
        if (self::$cachedBody !== null) {
            return self::$cachedBody;
        }

        $body = '';

        // Read from php://input (only happens once now)
        if (!isset($_SERVER['CONTENT_TYPE']) || strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === false) {
            $body = @file_get_contents('php://input');

            // AWS ECS/ALB edge case: try stream if empty
            if (empty($body) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
                $stream = @fopen('php://input', 'r');
                if ($stream !== false) {
                    $body = @stream_get_contents($stream);
                    @fclose($stream);
                }
            }
        }

        // If empty, try to build from $_POST
        if (empty($body) && !empty($_POST)) {
            $body = json_encode($_POST);
        }

        // Cache the result for subsequent calls (THIS IS THE FIX)
        self::$cachedBody = $body ?: '';
        return self::$cachedBody;
    }
}
```

### Key Changes
1. Added `private static ?string $cachedBody = null;` property
2. Check cache first before reading php://input
3. Store result in cache after first read
4. All subsequent calls return cached value

## Deployment Status

### Issue Encountered
Docker build used cached layers from previous version, so the deployed image didn't have the fix!

**Evidence:**
- Local file has: `private static ?string $cachedBody = null;`
- Deployed image has: `private static $cachedBody = null;` (old version)

### Solution
Rebuild with `--no-cache` flag to force fresh build of all layers:

```bash
docker build --no-cache -t veribits:no-cache-fix -f docker/Dockerfile .
docker tag veribits:no-cache-fix 515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits:latest
docker push 515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits:latest
aws ecs update-service --cluster veribits-cluster --service veribits-api --force-new-deployment
```

## Why This Wasn't Obvious

1. **Debug endpoints worked** - they only called getJsonBody() once
2. **Test endpoints worked** - inline code without Logger.debug() calls
3. **Apache config was fine** - Content-Type headers were being passed correctly
4. **Docker caching masked the fix** - Layers were cached so new code wasn't deployed

## Prevention

1. Always use `Request::getJsonBody()` instead of reading php://input directly
2. Never remove caching from Request class (it's not a "bug", it's required!)
3. Use `docker build --no-cache` when troubleshooting deployment issues
4. Verify deployed code matches local code before claiming fix is deployed

## Timeline

- **Multiple attempts**: 11 different "fixes" deployed
- **Root cause**: php://input can only be read once
- **Real fix**: Re-enable caching in Request class (it was previously removed thinking it was causing issues)
- **Deployment issue**: Docker layer caching prevented fix from being deployed
- **Final solution**: Rebuild with --no-cache

## Test Accounts

After deployment completes:
- straticus1@gmail.com / TestPassword123!
- enterprise@veribits.com / EnterpriseDemo2025!
