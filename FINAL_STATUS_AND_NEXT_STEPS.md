# API Authentication Issue - Final Status Report

## Executive Summary

After 4+ hours of intensive debugging and 15+ deployment attempts, the API authentication endpoint (`/api/v1/auth/login`) continues to return 422 validation errors. The root cause has been identified and the code fix has been implemented and deployed, but the issue persists in production.

## Status: UNRESOLVED ❌

### What We Know ✓

1. **Root Cause Identified**: PHP's `php://input` stream can only be read once per request
2. **Code Fix Applied**: Added static caching to `Request::getBody()` to allow multiple reads
3. **Fix Verified in Container**: The deployed Docker image contains the correct code
4. **Test Endpoints Work**: `/api/v1/test/request-helper` and `/api/v1/test/login-inline` both work perfectly
5. **Production Auth Endpoint Fails**: `/api/v1/auth/login` still returns validation errors

### What's Confusing ❓

The test endpoints use the EXACT SAME `Request::getJsonBody()` method as AuthController, and they work. This suggests:
- The Request class fix IS working
- Something specific to `/api/v1/auth/login` route or AuthController is different
- There may be hidden middleware, rate limiting, or other code consuming php://input before AuthController runs

## Files Modified

### 1. `/app/src/Utils/Request.php` ✅ DEPLOYED
```php
class Request {
    private static ?string $cachedBody = null;  // ← ADDED

    public static function getBody(): string {
        if (self::$cachedBody !== null) {  // ← ADDED
            return self::$cachedBody;
        }

        $body = @file_get_contents('php://input');

        // ... edge case handling ...

        self::$cachedBody = $body ?: '';  // ← ADDED
        return self::$cachedBody;
    }
}
```

**Status**: Deployed in image `sha256:ed6d38995a7dc3f3178c4fe342c9fa629cade68970af599a86ecd5de3e99cbec`

### 2. `/app/src/Controllers/AuthController.php` ✅ DEPLOYED
```php
public function login(): void {
    $clientIp = $this->getClientIp();

    if (!RateLimit::check("login:$clientIp", 10, 300)) {
        Response::error('Login rate limit exceeded', 429);
        return;
    }

    $body = Request::getJsonBody();  // Single call, properly cached

    $validator = new Validator($body);
    // ...
}
```

**Status**: Deployed, cleaned up (no debug code)

## Current Production State

- **ECS Cluster**: veribits-cluster
- **Service**: veribits-api
- **Task Definition**: veribits-api:12
- **Image**: `515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits-api@sha256:ed6d38995a7dc3f3178c4fe342c9fa629cade68970af599a86ecd5de3e99cbec`
- **Running Tasks**: 2/2
- **Deployment**: Complete and stable

## Test Results

### Working Endpoints ✅

```bash
# Test helper - WORKS
curl -X POST https://www.veribits.com/api/v1/test/request-helper \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"pass"}'

# Response: 200 OK
{
  "test": "index.php context",
  "raw_body": "{\"email\":\"test@example.com\",\"password\":\"pass\"}",
  "json_body": {"email":"test@example.com","password":"pass"},
  "has_email": true,
  "has_password": true
}
```

```bash
# Inline test - WORKS
curl -X POST https://www.veribits.com/api/v1/test/login-inline \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"pass"}'

# Response: 200 OK
{
  "success": true,
  "message": "Manual check passed!",
  "body": {"email":"test@example.com","password":"pass"}
}
```

### Broken Endpoint ❌

```bash
# Auth login - FAILS
curl -X POST https://www.veribits.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"straticus1@gmail.com","password":"TestPassword123!"}'

# Response: 422 Unprocessable Entity
{
  "success": false,
  "error": {
    "message": "Validation failed",
    "code": 422,
    "validation_errors": {
      "email": ["The email field is required"],
      "password": ["The password field is required"]
    }
  }
}
```

## Recommended Next Steps

### Option 1: Deep Debug with Exec Access (IMMEDIATE)

Connect directly to running container and add debug logging:

```bash
# Get running task
TASK_ARN=$(aws ecs list-tasks --cluster veribits-cluster --service-name veribits-api --query 'taskArns[0]' --output text)

# Enable ECS Exec if not already enabled
aws ecs update-service \
  --cluster veribits-cluster \
  --service veribits-api \
  --enable-execute-command

# Connect to container
aws ecs execute-command \
  --cluster veribits-cluster \
  --task $TASK_ARN \
  --container veribits-api \
  --interactive \
  --command "/bin/bash"

# Inside container, add debug logging
cat >> /var/www/src/Controllers/AuthController.php <<'EOF'
// After line 96: $body = Request::getJsonBody();
file_put_contents('/tmp/debug.log', json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'raw_input' => file_get_contents('php://input'),  // This will be empty since already read
    'cached_body' => Request::getBody(),
    'json_body' => $body,
    '_POST' => $_POST,
    '_SERVER' => array_filter($_SERVER, fn($k) => strpos($k, 'CONTENT') !== false, ARRAY_FILTER_USE_KEY)
], JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
EOF

# Restart Apache
apache2ctl restart

# Test and check log
curl -X POST https://www.veribits.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"pass"}'

cat /tmp/debug.log
```

### Option 2: Compare Working vs Broken Endpoints (ANALYTICAL)

Add identical debug logging to BOTH the working test endpoint and the broken auth endpoint to see the difference:

```php
// In index.php - test endpoint that WORKS
if ($uri === '/api/v1/test/request-helper' && $method === 'POST') {
    file_put_contents('/tmp/test-endpoint-debug.log', json_encode([
        'endpoint' => 'test-request-helper',
        'raw_body' => Request::getBody(),
        'json_body' => Request::getJsonBody(),
        '_SERVER' => $_SERVER
    ], JSON_PRETTY_PRINT), FILE_APPEND);

    // ... rest of code
}

// In AuthController.php - endpoint that FAILS
public function login(): void {
    file_put_contents('/tmp/auth-endpoint-debug.log', json_encode([
        'endpoint' => 'auth-login',
        'raw_body' => Request::getBody(),
        'json_body' => Request::getJsonBody(),
        '_SERVER' => $_SERVER
    ], JSON_PRETTY_PRINT), FILE_APPEND);

    // ... rest of code
}
```

Then compare the two log files side-by-side.

### Option 3: Nuclear Option - Bypass Validator (TEMPORARY FIX)

If you need this working immediately for the job interview, bypass the Validator temporarily:

```php
// In AuthController::login()
public function login(): void {
    $clientIp = $this->getClientIp();

    if (!RateLimit::check("login:$clientIp", 10, 300)) {
        Response::error('Login rate limit exceeded', 429);
        return;
    }

    $body = Request::getJsonBody();

    // TEMPORARY: Manual validation bypass
    if (empty($body['email']) || empty($body['password'])) {
        // This should never happen if Request is working
        Response::error('Invalid request data', 400, [
            'debug' => [
                'body_received' => $body,
                'raw_body' => Request::getBody(),
                'post' => $_POST,
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? null
            ]
        ]);
        return;
    }

    $email = filter_var($body['email'], FILTER_SANITIZE_EMAIL);
    $password = $body['password'];

    // Skip Validator entirely, proceed with login logic
    try {
        $user = Database::fetch(
            "SELECT id, email, password, status FROM users WHERE email = :email",
            ['email' => $email]
        );

        if (!$user || !Auth::verifyPassword($password, $user['password'])) {
            Response::error('Invalid credentials', 401);
            return;
        }

        // ... rest of login logic
    }
}
```

### Option 4: Check for Hidden Middleware or Interceptors

Search for ANY code that might be reading the request body before AuthController:

```bash
# Search for middleware patterns
grep -r "before.*login\|middleware\|interceptor" app/src/ app/public/

# Search for anything that reads php://input
grep -r "php://input\|file_get_contents.*php" app/src/ app/public/ | grep -v ".git"

# Check if there's a global request handler
grep -r "REQUEST_METHOD.*POST\|CONTENT_TYPE.*json" app/public/index.php
```

### Option 5: Enable Full PHP Error Logging

```bash
# In Docker container or via new deployment
echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/error-logging.ini
echo "display_errors = On" >> /usr/local/etc/php/conf.d/error-logging.ini
echo "log_errors = On" >> /usr/local/etc/php/conf.d/error-logging.ini
echo "error_log = /var/log/php_errors.log" >> /usr/local/etc/php/conf.d/error-logging.ini

apache2ctl restart

# Test and check errors
curl -X POST https://www.veribits.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"pass"}'

tail -f /var/log/php_errors.log
```

## Theory: Static Variable Persistence Across Requests

**Possible Issue**: PHP-FPM worker processes persist across multiple requests. If `Request::$cachedBody` is being set on request A, it might persist to request B if the same PHP-FPM worker handles both requests.

**Test**: Add logging to see if cache is "pre-populated":

```php
public static function getBody(): string {
    // Log if cache already exists (shouldn't happen on fresh request)
    if (self::$cachedBody !== null) {
        error_log("WARNING: Request body cache was already populated: " . self::$cachedBody);
        // RESET IT
        self::$cachedBody = null;
    }

    $body = @file_get_contents('php://input');
    self::$cachedBody = $body ?: '';
    return self::$cachedBody;
}
```

**Fix if this is the issue**: Reset cache at the START of each request:

```php
// In index.php, very first line after autoloading
Request::resetCache();  // Add this method to Request class
```

## Timeline

- **6:00 AM**: Issue reported
- **6:30 AM**: Root cause identified (php://input single read)
- **7:00 AM**: Code fix implemented
- **7:00-10:00 AM**: 15+ deployment attempts, various issues
  - Wrong ECR repository
  - Docker layer caching
  - Task definition pinned digest
  - ECS not pulling new images
- **10:00 AM**: Successfully deployed to production
- **10:20 AM**: STILL FAILING despite correct code in deployed image

## Conclusion

The issue is NOT with:
- ✅ Request class (fix is correct and deployed)
- ✅ Apache configuration (working for test endpoints)
- ✅ Docker/ECS deployment (image is running correctly)
- ✅ ALB/Load balancer (passing requests through)

The issue IS with:
- ❓ Something specific to the AuthController or `/api/v1/auth/login` route
- ❓ Possible middleware or interceptor reading body before AuthController
- ❓ Possible PHP-FPM static variable persistence (cache not resetting between requests)
- ❓ Possible difference in how index.php routes vs controller methods are called

**Recommendation**: Use Option 1 (ECS Exec debug) to add logging and see EXACTLY what's happening in the broken endpoint vs the working endpoints.

## Contact for Interview

**CRITICAL**: User has job interview at 7:00 AM EST and needs this working. Interview is already in progress or passed depending on timezone.

**Immediate workaround**: Use Option 3 (bypass Validator) to get auth working for interview demo, then debug properly after.
