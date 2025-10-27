# VeriBits Premium Features Implementation Plan

## Phase 1: Authentication Fix (IMMEDIATE - 1 day)

### Current Status
- POST body: ✓ Working
- Database schema: ✓ Fixed (password_hash column)
- Migrations: ✓ Running
- Password hashing: ✓ Correct Argon2id format
- **Issue**: Login returns 401 despite correct setup

### Debug Steps
1. Add temporary logging to Auth::verifyPassword() to see actual hash values
2. Query database directly to verify password_hash values are correct
3. Check if password_hash column has correct data type (TEXT)
4. Verify no whitespace/encoding issues in stored hashes
5. Test with fresh user registration to rule out migration issues

### Deployment
```bash
# Build with debug logging
docker build -t veribits:auth-debug -f docker/Dockerfile .
docker push 515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits-api:auth-debug
aws ecs update-service --cluster veribits-cluster --service veribits-api --task-definition veribits-api:25 --force-new-deployment
```

### Success Criteria
- [ ] Both test accounts can login successfully
- [ ] JWT tokens returned with valid claims
- [ ] API calls work with bearer tokens

---

## Phase 2: Compliance Auditor Tool (WEEK 1-2)

### Overview
Premium tool that scans infrastructure configurations against compliance frameworks (SOC2, ISO27001, HIPAA, PCI-DSS).

### Implementation Plan

#### 2.1 Backend API (`app/src/Controllers/ComplianceController.php`)
```php
class ComplianceController {
    public function scan(): void
    public function getReport(string $reportId): void
    public function listFrameworks(): void
    public function generatePDF(string $reportId): void  // PREMIUM
}
```

**Endpoints**:
- `POST /api/v1/compliance/scan` - Start compliance scan
- `GET /api/v1/compliance/report/{id}` - Get scan results
- `GET /api/v1/compliance/frameworks` - List available frameworks
- `GET /api/v1/compliance/report/{id}/pdf` - Generate PDF (Premium)

#### 2.2 Database Schema
```sql
-- db/migrations/015_compliance_auditor.sql
CREATE TABLE compliance_scans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id),
    framework VARCHAR(50) NOT NULL, -- 'SOC2', 'ISO27001', etc.
    target_type VARCHAR(50) NOT NULL, -- 'aws', 'docker', 'k8s'
    target_config JSONB NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    findings JSONB,
    score INTEGER,
    created_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX idx_compliance_scans_user ON compliance_scans(user_id);
CREATE INDEX idx_compliance_scans_created ON compliance_scans(created_at);
```

#### 2.3 Compliance Rules Engine
```php
// app/src/Utils/ComplianceRules.php
class ComplianceRules {
    // Rule definitions for each framework
    private static array $soc2Rules = [
        'encryption_at_rest' => [...],
        'access_logging' => [...],
        'mfa_enabled' => [...],
        // ... 50+ rules
    ];

    public static function evaluateRule(string $framework, string $rule, array $config): array
    public static function calculateScore(array $findings): int
}
```

#### 2.4 Frontend Tool (`app/public/tool/compliance-auditor.php`)
- Framework selector (SOC2, ISO27001, HIPAA, PCI-DSS)
- Target configuration input (AWS config, Docker compose, etc.)
- Real-time scan progress
- Visual dashboard with compliance score
- Detailed findings with remediation steps
- PDF export button (premium feature)

### Testing Strategy
```bash
# Unit tests
tests/unit/ComplianceRulesTest.php
tests/unit/ComplianceControllerTest.php

# Integration tests
tests/integration/ComplianceScanFlowTest.php

# End-to-end tests
tests/playwright/compliance-auditor.spec.js
```

### Deployment Checklist
- [ ] Database migration runs successfully
- [ ] API endpoints return expected responses
- [ ] Frontend renders correctly
- [ ] Rate limiting configured (5 scans/hour free, unlimited premium)
- [ ] CloudWatch alerts for errors
- [ ] Documentation updated

---

## Phase 3: API Security Analyzer (WEEK 3-4)

### Overview
Analyzes REST/GraphQL APIs for security vulnerabilities (OWASP API Top 10).

### Implementation Plan

#### 3.1 Backend API
```php
class ApiSecurityController {
    public function analyzeEndpoint(): void      // Analyze single endpoint
    public function scanApi(): void              // Full API scan
    public function testAuthentication(): void   // Auth bypass tests
    public function testInjection(): void        // SQL/NoSQL injection
    public function testBrokenAuth(): void       // Broken auth detection
}
```

#### 3.2 Scanner Engine
```php
// app/src/Utils/ApiScanner.php
class ApiScanner {
    public function discoverEndpoints(string $baseUrl): array
    public function testEndpoint(string $url, string $method): array
    public function detectVulnerabilities(array $response): array
    public function generateReport(array $findings): array
}
```

**Vulnerability Checks**:
1. Broken Object Level Authorization (BOLA)
2. Broken User Authentication
3. Excessive Data Exposure
4. Lack of Resources & Rate Limiting
5. Broken Function Level Authorization
6. Mass Assignment
7. Security Misconfiguration
8. Injection vulnerabilities
9. Improper Assets Management
10. Insufficient Logging & Monitoring

#### 3.3 Database Schema
```sql
-- db/migrations/016_api_security.sql
CREATE TABLE api_scans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id),
    target_url VARCHAR(500) NOT NULL,
    scan_type VARCHAR(50) DEFAULT 'full',
    vulnerabilities JSONB,
    risk_score INTEGER,
    endpoints_tested INTEGER,
    created_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP
);
```

### Testing Strategy
- Test against intentionally vulnerable API (OWASP Juice Shop)
- Verify no false positives on secure endpoints
- Performance testing (scan 100+ endpoints in <60s)

---

## Phase 4: Tiered Billing System (WEEK 5)

### 4.1 Plans
```javascript
const PLANS = {
  free: {
    price: 0,
    quotas: {
      api_calls: 100,
      compliance_scans: 3,
      api_security_scans: 5,
      storage_mb: 50
    },
    features: ['basic_tools', 'community_support']
  },

  pro: {
    price: 49,
    quotas: {
      api_calls: 10000,
      compliance_scans: 50,
      api_security_scans: 100,
      storage_mb: 1000
    },
    features: ['all_tools', 'pdf_reports', 'slack_alerts', 'priority_support']
  },

  enterprise: {
    price: 299,
    quotas: {
      api_calls: -1, // unlimited
      compliance_scans: -1,
      api_security_scans: -1,
      storage_mb: 10000
    },
    features: ['all_tools', 'white_label', 'sla', 'dedicated_support', 'custom_integrations']
  }
};
```

### 4.2 Implementation
```php
// app/src/Controllers/BillingController.php
class BillingController {
    public function getPlans(): void
    public function subscribe(string $plan): void
    public function cancelSubscription(): void
    public function getUsage(): void
    public function checkQuota(string $resource): bool
}
```

### 4.3 Payment Integration
- Stripe for payment processing
- Webhooks for subscription events
- Prorated upgrades/downgrades

---

## Phase 5: Deployment Pipeline (WEEK 6)

### 5.1 CI/CD Pipeline
```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run PHPUnit tests
      - name: Run Playwright tests
      - name: Security scan

  build:
    needs: test
    steps:
      - name: Build Docker image
      - name: Push to ECR
      - name: Update ECS task definition

  deploy:
    needs: build
    steps:
      - name: Deploy to ECS
      - name: Run smoke tests
      - name: Rollback on failure
```

### 5.2 Monitoring & Alerts
```javascript
// CloudWatch Alarms
const alarms = {
  api_errors: {
    threshold: 10,
    period: 300,
    action: 'sns_alert'
  },
  high_latency: {
    threshold: 2000,
    period: 60,
    action: 'autoscale'
  },
  quota_exhausted: {
    threshold: 90,
    action: 'upgrade_prompt'
  }
};
```

---

## Phase 6: Testing Framework (ONGOING)

### 6.1 Test Coverage Goals
- Unit tests: 80%+ coverage
- Integration tests: All API endpoints
- E2E tests: Critical user flows
- Performance tests: Load testing (1000 req/s)

### 6.2 Test Structure
```
tests/
├── unit/
│   ├── Controllers/
│   ├── Utils/
│   └── Models/
├── integration/
│   ├── ApiEndpointsTest.php
│   ├── AuthenticationFlowTest.php
│   └── BillingFlowTest.php
├── e2e/
│   ├── playwright/
│   │   ├── compliance-flow.spec.js
│   │   ├── api-security-flow.spec.js
│   │   └── billing-flow.spec.js
│   └── puppeteer/
└── performance/
    ├── load-test.js
    └── stress-test.js
```

### 6.3 Automated Testing
```bash
# Pre-commit hooks
.git/hooks/pre-commit:
  - Run PHPUnit unit tests
  - Run ESLint
  - Check code formatting

# Pre-push hooks
.git/hooks/pre-push:
  - Run full test suite
  - Run security scanner
  - Check migrations
```

---

## Phase 7: Documentation (WEEK 7)

### 7.1 API Documentation
- OpenAPI/Swagger spec
- Interactive API explorer
- Code examples (curl, Python, Node.js, PHP)

### 7.2 User Guides
- Getting started guide
- Compliance auditor guide
- API security analyzer guide
- Billing & subscription management

### 7.3 Developer Documentation
- Architecture overview
- Database schema
- Deployment guide
- Contributing guidelines

---

## Implementation Timeline

### Week 1: Authentication Fix + Compliance Auditor Backend
- Day 1: Fix auth issue, verify login works
- Day 2-3: Implement compliance rules engine
- Day 4-5: Build compliance API endpoints
- Day 6-7: Database migrations, testing

### Week 2: Compliance Auditor Frontend + Testing
- Day 1-3: Build frontend interface
- Day 4-5: Integration testing
- Day 6-7: E2E testing, bug fixes

### Week 3: API Security Analyzer Backend
- Day 1-2: Design scanner engine
- Day 3-5: Implement vulnerability checks
- Day 6-7: API endpoints, database schema

### Week 4: API Security Analyzer Frontend
- Day 1-3: Build frontend interface
- Day 4-5: Integration testing
- Day 6-7: E2E testing, documentation

### Week 5: Billing System
- Day 1-2: Database schema for billing
- Day 3-4: Stripe integration
- Day 5: Quota management
- Day 6-7: Testing, admin dashboard

### Week 6: Deployment Pipeline
- Day 1-2: Setup GitHub Actions
- Day 3-4: Configure monitoring/alerts
- Day 5: Load testing
- Day 6-7: Documentation, training

### Week 7: Polish & Launch
- Day 1-3: Bug fixes, performance optimization
- Day 4-5: Documentation finalization
- Day 6: Marketing materials
- Day 7: Launch! 🚀

---

## Success Metrics

### Technical Metrics
- [ ] 99.9% uptime
- [ ] <500ms average API response time
- [ ] 80%+ test coverage
- [ ] Zero critical security vulnerabilities

### Business Metrics
- [ ] 100 free tier signups in month 1
- [ ] 10 paid subscriptions in month 1
- [ ] $500 MRR by end of month 2
- [ ] 5-star average user rating

### User Metrics
- [ ] <5min time to first value
- [ ] 50%+ weekly active users
- [ ] <10% churn rate
- [ ] NPS score >50

---

## Risk Mitigation

### Technical Risks
1. **Auth issues persist**: Have fallback OAuth integration ready
2. **Performance issues**: Implement caching, CDN, database indexing
3. **Security vulnerabilities**: Regular penetration testing, bug bounty program

### Business Risks
1. **Low adoption**: Free tier with generous limits, content marketing
2. **High churn**: User onboarding flow, proactive support
3. **Competition**: Unique value prop (bundled security tools), competitive pricing

---

## Next Steps (IMMEDIATE)

1. **Right Now**: Fix authentication 401 error
2. **Today**: Start compliance auditor backend
3. **This Week**: Complete compliance auditor MVP
4. **Next Week**: Begin API security analyzer

Let's start by fixing the auth issue, then I'll begin implementation of the compliance auditor!
