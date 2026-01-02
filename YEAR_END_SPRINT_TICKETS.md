# VeriBits Year-End Sprint - Change Tickets

**Created:** 2025-12-29
**Updated:** 2025-12-29 (Sprint Day 1 - Continued)
**Sprint Goal:** Maximum value delivery before year-end management cutoff
**Status:** IN PROGRESS - 12 TICKETS COMPLETED

---

## EXECUTIVE SUMMARY

Based on comprehensive platform review by AI staff, we have identified:
- **35+ improvement opportunities** (code quality, security, performance)
- **20+ API/tooling enhancements**
- **24 new feature opportunities** ($3M+ ARR potential)

This document prioritizes tickets for immediate execution.

---

## CRITICAL - EXECUTE IMMEDIATELY

### TICKET-001: Deploy VeriBits Application (Replace Migration Page)
**Priority:** P0 - BLOCKER
**Status:** READY TO DEPLOY - Requires SSH Access
**Effort:** 2-4 hours
**Owner:** DevOps

**Problem:** veribits.com shows "Service migrating to new infrastructure" placeholder instead of actual application.

**Current State:**
- DNS: veribits.com -> 129.80.158.147 (Oracle Cloud)
- API: api.veribits.com -> 129.153.158.177 (separate IP)
- Migration page served from OCI

**Deployment Script Created:** `./scripts/deploy-to-oci.sh`

**To Deploy:**
```bash
# Set SSH credentials for OCI
export OCI_SSH_USER=opc
export OCI_SSH_KEY=~/.ssh/your-oci-key.pem

# Deploy to production
./scripts/deploy-to-oci.sh

# Or dry-run first
./scripts/deploy-to-oci.sh --dry-run
```

**Acceptance Criteria:**
- [ ] veribits.com serves actual PHP application
- [ ] All 40+ tools accessible
- [ ] Authentication working
- [ ] API endpoints responding
- [ ] API docs at /api/docs

---

### TICKET-002: After Dark Systems Central Auth Integration (OIDC)
**Priority:** P0 - Critical
**Status:** COMPLETED
**Effort:** 4-6 hours
**Owner:** Backend

**Problem:** VeriBits has standalone auth, not integrated with After Dark Systems Central.

**Central Auth Details:**
- Issuer: `https://login.afterdarksys.com`
- Authorization: `https://login.afterdarksys.com/oauth/authorize`
- Token: `https://login.afterdarksys.com/oauth/token`
- UserInfo: `https://login.afterdarksys.com/oauth/userinfo`
- JWKS: `https://login.afterdarksys.com/.well-known/jwks.json`
- Scopes: `openid profile email platforms`

**Implementation Plan:**
1. Create `CentralAuthController.php` for OIDC flow
2. Add OIDC client configuration to `.env.production`
3. Create "Login with After Dark Systems" button
4. Implement token exchange and session management
5. Link Central accounts to existing VeriBits accounts

**Files to Create/Modify:**
- `app/src/Controllers/CentralAuthController.php` (NEW)
- `app/src/Utils/OIDCClient.php` (NEW)
- `app/config/.env.production` (ADD OIDC config)
- `app/public/login.php` (ADD Central auth button)
- `app/public/assets/js/auth.js` (ADD Central auth handler)

**Acceptance Criteria:**
- [ ] "Login with After Dark Systems" button visible
- [ ] OIDC authorization flow working
- [ ] Users authenticated via Central get VeriBits session
- [ ] Existing local auth still works as fallback

---

### TICKET-003: Fix Critical Security Vulnerability - AdminController No Auth
**Priority:** P0 - CRITICAL SECURITY
**Status:** COMPLETED
**Effort:** 15 minutes
**Owner:** Backend

**Problem:** `AdminController::resetPassword()` and `testRegister()` have NO authentication - anyone can reset passwords!

**File:** `app/src/Controllers/AdminController.php`

**Fix:**
```php
public function resetPassword(): void {
    // ADD THIS LINE:
    $claims = Auth::requireBearer();
    // Verify admin role
    if (!in_array('admin', $claims['roles'] ?? [])) {
        Response::error('Admin access required', 403);
        return;
    }
    // ... rest of code
}
```

**Acceptance Criteria:**
- [ ] All admin endpoints require authentication
- [ ] Only admin role can access admin functions

---

## HIGH PRIORITY - This Sprint

### TICKET-004: Remove Sensitive Data from Production Logs
**Priority:** P1
**Status:** COMPLETED
**Effort:** 1 hour
**Owner:** Backend

**Problem:** `Auth.php` lines 81-98 log password hashes in hex to error_log()

**Fix:** Replaced all `error_log()` calls with `Logger::warning()` without sensitive data.

---

### TICKET-005: Unify Password Hashing Algorithm
**Priority:** P1
**Status:** COMPLETED
**Effort:** 30 minutes
**Owner:** Backend

**Problem:** Auth.php uses BCrypt cost=10, AdminController uses ARGON2ID - causes login failures.

**Fix:** Standardized on BCrypt cost=10 in AdminController to match Auth.php.

---

### TICKET-006: Add Missing Database Indexes
**Priority:** P1
**Status:** COMPLETED (via migration 025)
**Effort:** 15 minutes
**Owner:** Database

**Migration to add:**
```sql
CREATE INDEX idx_rate_limits_identifier_ts ON rate_limits(identifier, timestamp);
CREATE INDEX idx_audit_logs_user_id_created ON audit_logs(user_id, created_at);
CREATE INDEX idx_api_keys_key ON api_keys(key);
CREATE INDEX idx_api_keys_user_revoked ON api_keys(user_id, revoked);
```

---

### TICKET-007: OpenAPI/Swagger Documentation
**Priority:** P1
**Status:** COMPLETED
**Effort:** 2-3 days
**Owner:** Backend

**Deliverables:**
- OpenAPI 3.0 spec at `/api/v1/openapi.json` - CREATED
- Swagger UI at `/api/docs` - CREATED (docs.php)
- Full API documentation with schemas

---

### TICKET-008: Implement Request Caching with ETags
**Priority:** P2
**Status:** COMPLETED
**Effort:** 1 day
**Owner:** Backend

**Benefit:** Reduce server load, improve client performance for GET endpoints.

**Changes:**
- Added `Response::cached()` method with ETag support and 304 Not Modified responses
- Added `Response::noCache()` method for sensitive endpoints
- Supports configurable max-age and private/public cache control
- Returns 304 Not Modified when client ETag matches

---

### TICKET-009: Complete Hash Lookup Local Caching (TODO in code)
**Priority:** P2
**Status:** COMPLETED
**Effort:** 1 day
**Owner:** Backend

**Changes:**
- Created migration 026_hash_lookup_cache.sql for cache table
- Implemented queryLocalCache() to check cache first
- Implemented cacheHashResult() to store successful lookups
- Cache includes hit count and LRU eviction support

---

### TICKET-010: Complete Code Signing Session Management (TODO in code)
**Priority:** P2
**Status:** COMPLETED
**Effort:** 1 day
**Owner:** Backend

**Changes:**
- Integrated Auth::optionalAuth() to get user_id from session
- Updated sign() and getQuota() methods
- Anonymous users still tracked by IP, authenticated users by user_id

---

## MEDIUM PRIORITY - If Time Permits

### TICKET-011: n8n Integration Package
**Priority:** P2
**Status:** COMPLETED
**Effort:** 2-3 days
**Description:** Create n8n-compatible nodes for major VeriBits tools

**Deliverables:**
- integrations/n8n/nodes/VeriBits.node.ts - Full n8n node implementation
- integrations/n8n/credentials/VeriBitsApi.credentials.ts
- integrations/n8n/package.json with proper n8n node registration
- integrations/n8n/README.md with installation instructions

---

### TICKET-012: Shell Completion Scripts (bash/zsh/fish)
**Priority:** P2
**Status:** COMPLETED
**Effort:** 1 day
**Description:** Add CLI shell completion for better UX

**Deliverables:**
- veribits-system-client-1.0/completions/veribits.bash
- veribits-system-client-1.0/completions/veribits.zsh
- veribits-system-client-1.0/completions/veribits.fish

---

### TICKET-013: Batch Operations API Endpoint
**Priority:** P2
**Status:** COMPLETED
**Effort:** 1-2 days
**Description:** Create `/api/v1/batch` for multiple operations in one request

**Deliverables:**
- app/src/Controllers/BatchController.php
- Route added to index.php
- Supports up to 100 operations per batch
- Whitelist-based endpoint security

---

### TICKET-014: Security Dashboard MVP
**Priority:** P2
**Effort:** 3-4 days
**Description:** Executive-level security posture dashboard

---

### TICKET-015: CI/CD Pipeline Templates (GitHub Actions)
**Priority:** P2
**Effort:** 2-3 days
**Description:** Pre-built workflow templates for GitHub Actions

---

## NEW FEATURES - Backlog (Post Year-End)

### TICKET-016: AI-Powered Threat Recommendation Engine
**Priority:** P3
**Effort:** 2-3 weeks
**Revenue Impact:** $200K+ ARR
**Description:** Claude API integration for plain-English findings and remediation

---

### TICKET-017: White-Label Platform for MSPs
**Priority:** P3
**Effort:** 6-8 weeks
**Revenue Impact:** $1M+ ARR
**Description:** Custom branding, multi-tenant, sub-tenant management

---

### TICKET-018: Compliance Automation (SOC2/HIPAA)
**Priority:** P3
**Effort:** 8-10 weeks
**Revenue Impact:** $800K+ ARR
**Description:** Automated evidence collection for audits

---

### TICKET-019: Zero-Trust Architecture Validator
**Priority:** P3
**Effort:** 3-4 weeks
**Revenue Impact:** $400K+ ARR
**Description:** 6-pillar zero-trust maturity assessment

---

### TICKET-020: Team Management & RBAC
**Priority:** P3
**Effort:** 3-4 weeks
**Revenue Impact:** $200K+ ARR
**Description:** Multi-user accounts, roles, permissions

---

## TECHNICAL DEBT - Quick Fixes

| # | Issue | File | Line | Effort |
|---|-------|------|------|--------|
| TD-001 | Replace `error_log()` with Logger | Auth.php | 81-98 | 30 min |
| TD-002 | Remove @ suppression operator | Multiple | 168 instances | 2 hours |
| TD-003 | Replace exit() with exceptions | Auth.php | 12,19,28,35,45 | 1 hour |
| TD-004 | Use Request::getJsonBody() | All controllers | 78 instances | 1 hour |
| TD-005 | Replace SELECT * with columns | Multiple | 20+ instances | 2 hours |
| TD-006 | Add allowedTables entries | Database.php | 11-16 | 15 min |
| TD-007 | Fix CIDR validation | Validator.php | 288-297 | 20 min |
| TD-008 | Add query execution logging | Database.php | N/A | 1 hour |

---

## SPRINT EXECUTION PLAN

### Day 1 (Today - Dec 29) - COMPLETED
- [x] Platform review complete
- [x] TICKET-003: Fix AdminController auth (15 min)
- [x] TICKET-002: Central Auth integration (4-6 hours)
- [x] TICKET-004: Remove sensitive logs (1 hour)
- [x] TICKET-005: Unify password hashing (30 min)
- [x] TICKET-006: Add database indexes (via migration 025)
- [x] TICKET-007: OpenAPI documentation
- [x] TICKET-009: Hash lookup caching
- [x] TICKET-010: Code signing session
- [x] TICKET-011: n8n integration package
- [x] TICKET-012: Shell completion scripts
- [x] TICKET-013: Batch operations API

### Day 2 (Dec 30) - REMAINING
- [ ] TICKET-001: Deploy application to OCI
- [ ] Register VeriBits as OAuth client at login.afterdarksys.com
- [ ] Run database migrations (024, 025, 026)
- [ ] TICKET-008: Request caching with ETags
- [ ] TICKET-014: Security Dashboard MVP (if time permits)

### Day 3 (Dec 31)
- [ ] Final testing and verification
- [ ] Technical debt quick fixes
- [ ] Documentation cleanup

---

## METRICS & SUCCESS CRITERIA

**Sprint Success:**
- [ ] veribits.com serving actual application
- [ ] Central auth integration working
- [ ] 0 critical security vulnerabilities
- [ ] 5+ technical debt items resolved
- [ ] OpenAPI documentation started

**Post-Sprint Tracking:**
- API response time < 200ms p95
- Error rate < 0.1%
- User registrations increasing
- Tool usage metrics improving

---

## NOTES

1. **Deployment Blocker:** Site is on Oracle Cloud (129.80.158.147), not AWS ECS. Need to either:
   - Deploy to OCI instance
   - Update DNS to AWS ALB
   - Investigate why ECS isn't being used

2. **Central Auth:** Full OIDC support available at login.afterdarksys.com - straightforward integration.

3. **Quick Wins:** Many code quality fixes can be done in < 1 hour each.

4. **Revenue Potential:** New features identified have $3M+ ARR potential - prioritize for Q1 2026.
