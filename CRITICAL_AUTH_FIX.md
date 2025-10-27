# CRITICAL AUTHENTICATION BUG FIX

## Problem Summary
The `/api/v1/auth/login` endpoint returns "email field is required" and "password field is required" errors even when valid JSON credentials are POSTed. This indicates that `Request::getJsonBody()` is returning an empty array instead of parsing the POST body.

## Root Cause Analysis
Based on testing:
1. `/api/v1/debug/request` endpoint CAN read php://input successfully
2. `/test-request-helper.php` standalone file CAN read php://input successfully
3. `/api/v1/test/request-helper` using `Request::getBody()` returns correct JSON string
4. `/api/v1/test/request-helper` using `Request::getJsonBody()` returns empty array

This indicates that:
- The body IS being read from php://input correctly
- The caching mechanism IS working
- The issue is in the `json_decode()` call within `Request::getJsonBody()`

Possible causes:
- BOM (Byte Order Mark) at the beginning of the string
- Hidden whitespace characters
- Character encoding issues
- JSON parsing failure due to malformed data

## Changes Made

### 1. /app/src/Utils/Request.php
**Added:**
- BOM stripping in `getJsonBody()` method
- Enhanced error logging with hex dump for debugging
- Better cache initialization tracking
- Explicit false-value checking for `file_get_contents()` and `stream_get_contents()`

**Key Fix:**
```php
// Strip BOM and whitespace that might interfere with JSON parsing
$body = ltrim($body, "\xEF\xBB\xBF"); // Remove UTF-8 BOM
$body = trim($body); // Remove leading/trailing whitespace
```

### 2. /app/src/Controllers/AuthController.php
**Added:**
- Debug logging when `Request::getJsonBody()` returns empty array
- Logs raw body, content-type, content-length for diagnosis

### 3. /app/public/index.php
**Added:**
- Enhanced test endpoints with hex dumps and detailed JSON error reporting
- `/api/v1/test/request-helper` now shows JSON decode errors
- `/api/v1/test/login-inline` provides full diagnostic output

## Deployment Instructions

### Step 1: Build and Push Docker Image
```bash
cd /Users/ryan/development/veribits.com

# Build the image
docker build -f docker/Dockerfile -t veribits:auth-fix .

# Tag for ECR
docker tag veribits:auth-fix 381491973729.dkr.ecr.us-east-1.amazonaws.com/veribits:auth-fix
docker tag veribits:auth-fix 381491973729.dkr.ecr.us-east-1.amazonaws.com/veribits:latest

# Login to ECR
aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin 381491973729.dkr.ecr.us-east-1.amazonaws.com

# Push
docker push 381491973729.dkr.ecr.us-east-1.amazonaws.com/veribits:auth-fix
docker push 381491973729.dkr.ecr.us-east-1.amazonaws.com/veribits:latest
```

### Step 2: Update ECS Service
```bash
# Force new deployment
aws ecs update-service \
  --cluster veribits-cluster \
  --service veribits-service \
  --force-new-deployment \
  --region us-east-1

# Monitor deployment
aws ecs wait services-stable \
  --cluster veribits-cluster \
  --services veribits-service \
  --region us-east-1
```

### Step 3: Test the Fix
```bash
# Test login with first account
curl -X POST https://veribits.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"straticus1@gmail.com","password":"TestPassword123!"}'

# Expected: JWT token and user info
# If still failing: Check CloudWatch logs for detailed error messages

# Test with second account
curl -X POST https://veribits.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"enterprise@veribits.com","password":"EnterpriseDemo2025!"}'
```

### Step 4: Check Logs if Still Failing
```bash
# Get logs from CloudWatch
aws logs tail /ecs/veribits --follow --region us-east-1 --filter-pattern "JSON decode failed"
```

## Test Endpoints for Debugging

If login still fails after deployment, use these test endpoints:

### 1. Raw php://input test
```bash
curl -X POST https://veribits.com/api/v1/debug/request \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"test123"}'
```
Expected: Should show `php_input` field with the JSON data

### 2. Request class test
```bash
curl -X POST https://veribits.com/api/v1/test/request-helper \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"test123"}'
```
Expected: Should show both `raw_body` and `json_body` with correct data
If `json_body` is empty but `raw_body` has data, check `json_error_msg` field

### 3. Login simulation test
```bash
curl -X POST https://veribits.com/api/v1/test/login-inline \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"test123"}'
```
Expected: Should show success or detailed failure information

## Fallback Solution

If the BOM stripping doesn't fix the issue, the logs will show the hex dump of the raw body. Use that to identify the actual problem. Common issues:

1. **Invalid JSON escaping**: Check if backslashes are being added (e.g., `\!` instead of `!`)
2. **Character encoding**: Ensure Content-Type header specifies UTF-8
3. **Apache/ALB transformation**: Check if AWS ALB is modifying the request body
4. **PHP configuration**: Check if magic_quotes or similar is enabled (shouldn't be in PHP 8.2)

## Timeline
- Issue reported: Before interview (URGENT)
- Fix developed: 2025-10-27
- Deployment target: Immediate

## Success Criteria
- Both test accounts can login successfully
- JWT tokens are returned
- No "field is required" errors when valid JSON is POSTed

## Files Modified
- /app/src/Utils/Request.php
- /app/src/Controllers/AuthController.php
- /app/public/index.php
