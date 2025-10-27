# Authentication Fix - Visual Explanation

## The Problem: POST Body Lost During Apache Rewrite

### BEFORE FIX (Broken Flow)

```
┌─────────────────────────────────────────────────────────────────────┐
│ Client Request                                                      │
│ POST /api/v1/auth/login HTTP/1.1                                   │
│ Content-Type: application/json                                      │
│ Content-Length: 65                                                  │
│                                                                     │
│ {"email":"user@example.com","password":"SecurePass123!"}           │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│ AWS ALB (Load Balancer)                                            │
│ - Forwards request to ECS task                                      │
│ - Headers preserved via X-Forwarded-* headers                       │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Apache 2.4 (Container Port 80)                                      │
│                                                                     │
│ 1. Receives request: /api/v1/auth/login                            │
│ 2. .htaccess mod_rewrite rule matches:                             │
│    RewriteCond %{REQUEST_URI} ^/api/                               │
│    RewriteRule ^(.*)$ index.php [QSA,L]  ← MISSING [PT] FLAG!     │
│                                                                     │
│ 3. Internal redirect WITHOUT [PT]:                                 │
│    - Apache buffers/consumes php://input stream ❌                 │
│    - Prepares to send to PHP-FPM                                   │
│    - Body is CONSUMED during redirect                              │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│ PHP-FPM 8.3                                                         │
│                                                                     │
│ 4. index.php loads:                                                 │
│    - Autoloader initializes                                         │
│    - Routes request to AuthController->login()                      │
│                                                                     │
│ 5. AuthController->login() calls:                                   │
│    $body = Request::getJsonBody()                                   │
│                                                                     │
│ 6. Request::getBody() reads php://input:                            │
│    $body = file_get_contents('php://input')                         │
│    → Returns: "" (EMPTY!) ❌                                        │
│    → Stream already consumed by Apache                              │
│                                                                     │
│ 7. Request::getJsonBody() decodes:                                  │
│    json_decode("", true) → [] (empty array)                         │
│                                                                     │
│ 8. Validator checks:                                                │
│    $validator->required('email')                                    │
│    → FAILS! "email field is required" ❌                            │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Response (HTTP 422)                                                 │
│                                                                     │
│ {                                                                   │
│   "success": false,                                                 │
│   "errors": {                                                       │
│     "email": ["The email field is required"]                        │
│   }                                                                 │
│ }                                                                   │
└─────────────────────────────────────────────────────────────────────┘
```

---

## AFTER FIX (Working Flow)

```
┌─────────────────────────────────────────────────────────────────────┐
│ Client Request                                                      │
│ POST /api/v1/auth/login HTTP/1.1                                   │
│ Content-Type: application/json                                      │
│ Content-Length: 65                                                  │
│                                                                     │
│ {"email":"user@example.com","password":"SecurePass123!"}           │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│ AWS ALB (Load Balancer)                                            │
│ - Forwards request to ECS task                                      │
│ - Headers preserved via X-Forwarded-* headers                       │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Apache 2.4 (Container Port 80)                                      │
│                                                                     │
│ 1. Receives request: /api/v1/auth/login                            │
│ 2. .htaccess mod_rewrite rule matches:                             │
│    RewriteCond %{REQUEST_URI} ^/api/                               │
│    RewriteRule ^(.*)$ index.php [QSA,L,PT] ← [PT] FLAG ADDED! ✓   │
│                                                                     │
│ 3. Internal redirect WITH [PT]:                                    │
│    - Apache PRESERVES php://input stream ✓                         │
│    - Passes through to PHP-FPM without consuming                   │
│    - Body remains readable by PHP                                  │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│ PHP-FPM 8.3                                                         │
│                                                                     │
│ 4. index.php loads:                                                 │
│    - Autoloader initializes                                         │
│    - Routes request to AuthController->login()                      │
│                                                                     │
│ 5. AuthController->login() calls:                                   │
│    $body = Request::getJsonBody()                                   │
│                                                                     │
│ 6. Request::getBody() reads php://input:                            │
│    $body = file_get_contents('php://input')                         │
│    → Returns: '{"email":"user@example.com","password":"..."}'  ✓   │
│    → Stream is readable! Data preserved!                            │
│                                                                     │
│ 7. Request::getJsonBody() decodes:                                  │
│    json_decode($body, true)                                         │
│    → ['email' => 'user@example.com', 'password' => '...'] ✓       │
│                                                                     │
│ 8. Validator checks:                                                │
│    $validator->required('email') → PASS! ✓                          │
│    $validator->email('email') → PASS! ✓                             │
│    $validator->required('password') → PASS! ✓                       │
│                                                                     │
│ 9. Authentication proceeds:                                         │
│    - Verify credentials against database                            │
│    - Generate JWT token                                             │
│    - Return success response                                        │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Response (HTTP 200)                                                 │
│                                                                     │
│ {                                                                   │
│   "success": true,                                                  │
│   "data": {                                                         │
│     "access_token": "eyJ0eXAiOiJKV1QiLCJhbGci...",                  │
│     "token_type": "bearer",                                         │
│     "expires_in": 3600,                                             │
│     "user": {                                                       │
│       "id": 1,                                                      │
│       "email": "user@example.com"                                   │
│     }                                                               │
│   },                                                                │
│   "message": "Login successful"                                     │
│ }                                                                   │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Why Test Endpoints Worked (Mystery Explained)

### Test Endpoint Flow (Working)

```
┌─────────────────────────────────────────────────────────────────────┐
│ POST /api/v1/debug/request                                          │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Apache: Same rewrite rule (broken)                                  │
│ - Internal redirect consumes php://input                            │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│ index.php IMMEDIATELY reads php://input (line 101):                │
│                                                                     │
│ if ($uri === '/api/v1/debug/request' && $method === 'POST') {      │
│     $phpInput = @file_get_contents('php://input'); ← FIRST READ!   │
│     Response::json([...]);                                          │
│     exit;                                                           │
│ }                                                                   │
│                                                                     │
│ ✓ This happens BEFORE Request class is ever initialized            │
│ ✓ Gets the body before it's fully consumed                         │
│ ✓ Works by luck of execution order!                                │
└─────────────────────────────────────────────────────────────────────┘
```

### Login Endpoint Flow (Broken)

```
┌─────────────────────────────────────────────────────────────────────┐
│ POST /api/v1/auth/login                                             │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Apache: Same rewrite rule (broken)                                  │
│ - Internal redirect consumes php://input                            │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│ index.php routes to controller (line 189):                         │
│                                                                     │
│ if ($uri === '/api/v1/auth/login' && $method === 'POST') {         │
│     (new AuthController())->login(); ← Controller instantiation     │
│     exit;                                                           │
│ }                                                                   │
│                                                                     │
│ ▼ Calls AuthController->login()                                     │
│                                                                     │
│ AuthController->login() calls:                                      │
│     $body = Request::getJsonBody(); ← SECOND+ READ!                 │
│                                                                     │
│ ✗ Stream already consumed by Apache during rewrite                 │
│ ✗ Returns empty string                                              │
│ ✗ Validation fails!                                                 │
└─────────────────────────────────────────────────────────────────────┘
```

---

## The Fix: Apache [PT] Flag

### What is [PT] (Pass Through)?

The `[PT]` flag tells Apache's mod_rewrite:

> **"Pass this request through to the next phase of URI processing WITHOUT consuming or buffering the request body"**

### RewriteRule Flags Explained

```apache
RewriteRule ^(.*)$ index.php [QSA,L,PT,E=REQUEST_URI_ORIG:%{REQUEST_URI}]
                              ─┬─ ─┬ ─┬─ ─────────────┬──────────────────
                               │   │  │              │
    [QSA] Query String Append ─┘   │  │              │
    Preserves ?param=value         │  │              │
                                   │  │              │
    [L] Last ─────────────────────┘  │              │
    Stop processing rules             │              │
                                      │              │
    [PT] Pass Through ────────────────┘              │
    DON'T consume php://input                        │
                                                     │
    [E=VAR:value] Environment Variable ──────────────┘
    Preserve original URI for logging
```

### Without [PT]: Apache's Internal Behavior

```
1. Client sends POST with body
2. Apache receives request
3. mod_rewrite matches rule
4. Apache performs INTERNAL redirect
5. During redirect, Apache:
   - Reads php://input to buffer it
   - Prepares to forward to PHP
   - But stream is now consumed!
6. PHP receives empty stream
```

### With [PT]: Apache's Correct Behavior

```
1. Client sends POST with body
2. Apache receives request
3. mod_rewrite matches rule
4. Apache performs INTERNAL redirect WITH [PT]
5. During redirect, Apache:
   - DOES NOT read php://input
   - Passes stream through untouched
   - Stream remains readable
6. PHP receives full stream with body intact
```

---

## Comparison: php://input Stream State

### Without [PT] Flag

```
Timeline of php://input stream:

┌──────────────────────────┐
│ Client POST Body         │
│ {"email":"test@x.com"}   │ ← Original data
└───────────┬──────────────┘
            │
            ▼
┌──────────────────────────┐
│ Apache receives          │
│ Stream: READABLE         │ ← Stream is fresh
│ Position: 0              │
└───────────┬──────────────┘
            │
            ▼
┌──────────────────────────┐
│ mod_rewrite (no [PT])    │
│ Apache READS stream      │ ← Stream consumed
│ Position: END            │
└───────────┬──────────────┘
            │
            ▼
┌──────────────────────────┐
│ PHP-FPM receives         │
│ Stream: EXHAUSTED        │ ← Can't read anymore
│ Position: END            │
│ file_get_contents() = "" │ ← Returns empty!
└──────────────────────────┘
```

### With [PT] Flag

```
Timeline of php://input stream:

┌──────────────────────────┐
│ Client POST Body         │
│ {"email":"test@x.com"}   │ ← Original data
└───────────┬──────────────┘
            │
            ▼
┌──────────────────────────┐
│ Apache receives          │
│ Stream: READABLE         │ ← Stream is fresh
│ Position: 0              │
└───────────┬──────────────┘
            │
            ▼
┌──────────────────────────┐
│ mod_rewrite (with [PT])  │
│ Apache PRESERVES stream  │ ← Stream NOT consumed
│ Position: 0              │
└───────────┬──────────────┘
            │
            ▼
┌──────────────────────────┐
│ PHP-FPM receives         │
│ Stream: READABLE         │ ← Still readable!
│ Position: 0              │
│ file_get_contents() = OK │ ← Returns body!
└──────────────────────────┘
```

---

## Architecture Diagram: Full Stack

```
┌─────────────────────────────────────────────────────────────────────┐
│                          INTERNET                                   │
└───────────────────────────────┬─────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     AWS Application Load Balancer                   │
│                                                                     │
│  - HTTPS termination                                                │
│  - Health checks: /api/v1/health                                    │
│  - Target: veribits-target-group                                    │
└───────────────────────────────┬─────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      AWS ECS Fargate Service                        │
│                      (veribits-api-service)                         │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ ECS Task (1024 CPU, 2048 MB)                                 │  │
│  │                                                              │  │
│  │  ┌────────────────────────────────────────────────────────┐ │  │
│  │  │ Docker Container (veribits-app:latest)                 │ │  │
│  │  │                                                        │ │  │
│  │  │  ┌──────────────────────────────────────────────────┐ │ │  │
│  │  │  │ Apache 2.4 (Port 80)                             │ │ │  │
│  │  │  │                                                  │ │ │  │
│  │  │  │ - mod_rewrite enabled                            │ │ │  │
│  │  │  │ - .htaccess with [PT] flag ✓                    │ │ │  │
│  │  │  │ - SetEnvIf directives for headers               │ │ │  │
│  │  │  └────────────────┬─────────────────────────────────┘ │ │  │
│  │  │                   │ Unix socket                       │ │  │
│  │  │                   ▼                                   │ │  │
│  │  │  ┌──────────────────────────────────────────────────┐ │ │  │
│  │  │  │ PHP-FPM 8.3                                      │ │ │  │
│  │  │  │                                                  │ │ │  │
│  │  │  │ - Process manager: dynamic                       │ │ │  │
│  │  │  │ - Max children: 20                               │ │ │  │
│  │  │  │ - VeriBits application code                      │ │ │  │
│  │  │  │   - index.php (router)                           │ │ │  │
│  │  │  │   - AuthController                               │ │ │  │
│  │  │  │   - Request helper                               │ │ │  │
│  │  │  │   - Validator                                    │ │ │  │
│  │  │  └──────────────────────────────────────────────────┘ │ │  │
│  │  │                                                        │ │  │
│  │  └────────────────────────────────────────────────────────┘ │  │
│  │                                                              │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                │
                ┌───────────────┴───────────────┐
                │                               │
                ▼                               ▼
┌──────────────────────────────┐  ┌──────────────────────────────┐
│ AWS RDS PostgreSQL           │  │ AWS ElastiCache Redis        │
│ (nitetext-db)                │  │                              │
│                              │  │ - Rate limiting              │
│ - Users table                │  │ - Session storage            │
│ - API keys                   │  │ - Cache layer                │
│ - Quotas                     │  │                              │
└──────────────────────────────┘  └──────────────────────────────┘
```

---

## Key Takeaways

1. **The [PT] flag is CRITICAL for PHP-FPM with mod_rewrite**
   - Without it, POST bodies are consumed during internal redirects
   - This is a well-documented Apache best practice

2. **php://input is a one-time read stream**
   - Once consumed, returns empty string
   - Static caching helps within a request, but doesn't fix timing issues

3. **Timing matters in request processing**
   - Test endpoints worked due to execution order (read before rewrite impact)
   - Auth endpoints failed due to late execution (read after rewrite impact)

4. **Always use proper Apache flags for API endpoints**
   - `[QSA]` - Preserve query strings
   - `[L]` - Stop processing (last rule)
   - `[PT]` - Pass through (preserve body)
   - `[E=VAR:value]` - Environment variables for logging

5. **This is a common gotcha in containerized PHP applications**
   - Especially in AWS ECS, Docker, Kubernetes
   - Apache + PHP-FPM is a common stack that needs proper configuration

---

## References

- Apache mod_rewrite documentation: https://httpd.apache.org/docs/2.4/mod/mod_rewrite.html
- PHP php://input documentation: https://www.php.net/manual/en/wrappers.php.php
- PHP-FPM configuration: https://www.php.net/manual/en/install.fpm.configuration.php
- AWS ECS best practices: https://docs.aws.amazon.com/AmazonECS/latest/bestpracticesguide/

---

**This fix restores full authentication functionality to VeriBits!**
