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

---

## COMPLETE IMPLEMENTATION ROADMAP

### All 10 Premium Features

#### Category A: Security Tools (5 Tools)
1. **Compliance Auditor** - SOC2/ISO27001/HIPAA configuration scanners
2. **Threat Intelligence Feed** - Real-time CVE/IOC integration
3. **Security Posture Dashboard** - Aggregate score across all tools
4. **Container Security Suite** - Deep Docker/K8s vulnerability scanning
5. **API Security Analyzer** - GraphQL/REST security testing

#### Category B: Monetization Strategies (5 Strategies)
1. **Tiered API Limits** - Free (100/mo) → Pro ($49, 10K/mo) → Enterprise (unlimited)
2. **Premium Features** - Advanced analysis, PDF reports, Slack/webhook alerts
3. **White-label Option** - $299/mo for MSPs/consultancies
4. **Batch Processing** - Charge per bulk scan ($0.01/scan for 1000+ scans)
5. **Training/Certification** - Security tool mastery courses

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

---

## Phase 2.5: Threat Intelligence Feed (WEEK 2-3)

### Overview
Real-time threat intelligence integration with CVE, IOC, and security advisories. Provides alerts for vulnerabilities affecting user infrastructure.

### Implementation Plan

#### 2.5.1 Backend API
```php
class ThreatIntelController {
    public function searchCVE(string $query): void
    public function getIOCDetails(string $ioc): void
    public function checkVulnerabilities(array $packages): void
    public function subscribeToFeeds(): void  // PREMIUM
    public function getAlerts(): void         // PREMIUM
}
```

**Endpoints**:
- `GET /api/v1/threat-intel/cve/search?q={keyword}` - Search CVE database
- `GET /api/v1/threat-intel/ioc/{hash}` - Get IOC details (IP, domain, file hash)
- `POST /api/v1/threat-intel/scan` - Scan packages/dependencies for vulnerabilities
- `POST /api/v1/threat-intel/subscribe` - Subscribe to threat feeds (Premium)
- `GET /api/v1/threat-intel/alerts` - Get personalized alerts (Premium)

#### 2.5.2 Data Sources Integration
```php
// app/src/Utils/ThreatIntel.php
class ThreatIntel {
    // Free tier sources
    private static array $freeSources = [
        'nvd' => 'https://services.nvd.nist.gov/rest/json/cves/2.0',
        'cisa' => 'https://www.cisa.gov/sites/default/files/feeds/known_exploited_vulnerabilities.json',
        'github_advisories' => 'https://api.github.com/advisories'
    ];

    // Premium sources
    private static array $premiumSources = [
        'otx' => 'AlienVault OTX',
        'abuse_ch' => 'abuse.ch feeds',
        'threatfox' => 'ThreatFox IOC database',
        'virustotal' => 'VirusTotal API'
    ];

    public static function fetchCVEs(string $vendor, string $product): array
    public static function checkIOC(string $indicator): array
    public static function scanDependencies(array $manifest): array
}
```

#### 2.5.3 Database Schema
```sql
-- db/migrations/017_threat_intelligence.sql
CREATE TABLE threat_intel_cache (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    source VARCHAR(50) NOT NULL,
    indicator_type VARCHAR(50) NOT NULL, -- 'cve', 'ioc', 'domain', 'ip', 'hash'
    indicator_value TEXT NOT NULL,
    severity VARCHAR(20),
    data JSONB NOT NULL,
    first_seen TIMESTAMP DEFAULT NOW(),
    last_updated TIMESTAMP DEFAULT NOW(),
    UNIQUE(source, indicator_type, indicator_value)
);

CREATE TABLE threat_subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id),
    feed_type VARCHAR(50) NOT NULL,
    filters JSONB,
    notification_method VARCHAR(20) DEFAULT 'email', -- email, slack, webhook
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE threat_alerts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id),
    threat_id UUID REFERENCES threat_intel_cache(id),
    severity VARCHAR(20),
    status VARCHAR(20) DEFAULT 'new', -- new, acknowledged, resolved
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_threat_cache_indicator ON threat_intel_cache(indicator_type, indicator_value);
CREATE INDEX idx_threat_alerts_user ON threat_alerts(user_id, status);
```

#### 2.5.4 Frontend Tool
```html
<!-- app/public/tool/threat-intelligence.php -->
<div class="threat-intel-dashboard">
    <!-- CVE Search -->
    <section class="cve-search">
        <h2>CVE Database Search</h2>
        <input type="text" placeholder="Search CVEs (e.g., Apache, Log4j)">
        <div class="results">
            <!-- CVE cards with severity badges -->
        </div>
    </section>

    <!-- IOC Lookup -->
    <section class="ioc-lookup">
        <h2>Indicator of Compromise Lookup</h2>
        <input type="text" placeholder="IP, domain, or file hash">
        <div class="threat-score">
            <!-- Threat score visualization -->
        </div>
    </section>

    <!-- Vulnerability Scanner (Premium) -->
    <section class="vuln-scanner premium-feature">
        <h2>Dependency Scanner <span class="badge">PRO</span></h2>
        <textarea placeholder="Paste package.json, requirements.txt, or pom.xml"></textarea>
        <button>Scan for Vulnerabilities</button>
    </section>

    <!-- Alert Subscriptions (Premium) -->
    <section class="alert-subs premium-feature">
        <h2>Alert Subscriptions <span class="badge">PRO</span></h2>
        <form>
            <label>Notify me about:</label>
            <input type="checkbox"> Critical CVEs
            <input type="checkbox"> Zero-days
            <input type="checkbox"> Exploited vulnerabilities
            <select name="notification">
                <option>Email</option>
                <option>Slack</option>
                <option>Webhook</option>
            </select>
        </form>
    </section>
</div>
```

### Monetization Strategy
- **Free**: 50 CVE searches/day, 20 IOC lookups/day, basic severity data
- **Pro**: Unlimited searches, advanced threat scoring, dependency scanning, email alerts
- **Enterprise**: Real-time feeds, Slack/webhook integration, custom IOC lists

---

## Phase 3.5: Security Posture Dashboard (WEEK 4)

### Overview
Centralized dashboard showing aggregate security score across all tools. Provides at-a-glance view of overall security posture.

### Implementation Plan

#### 3.5.1 Backend API
```php
class SecurityPostureController {
    public function getDashboard(): void
    public function calculateScore(): void
    public function getRecommendations(): void
    public function getTrends(): void  // PREMIUM
    public function exportReport(): void  // PREMIUM
}
```

#### 3.5.2 Scoring Algorithm
```php
// app/src/Utils/SecurityScore.php
class SecurityScore {
    public static function calculate(int $userId): array {
        $scores = [
            'compliance' => self::getComplianceScore($userId),
            'vulnerabilities' => self::getVulnerabilityScore($userId),
            'api_security' => self::getApiSecurityScore($userId),
            'container_security' => self::getContainerScore($userId),
            'configurations' => self::getConfigScore($userId)
        ];

        $weights = [
            'compliance' => 0.25,
            'vulnerabilities' => 0.30,
            'api_security' => 0.20,
            'container_security' => 0.15,
            'configurations' => 0.10
        ];

        $totalScore = 0;
        foreach ($scores as $category => $score) {
            $totalScore += $score * $weights[$category];
        }

        return [
            'overall_score' => round($totalScore),
            'grade' => self::getGrade($totalScore),
            'category_scores' => $scores,
            'recommendations' => self::getRecommendations($scores)
        ];
    }

    private static function getGrade(float $score): string {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }
}
```

#### 3.5.3 Database Schema
```sql
-- db/migrations/018_security_posture.sql
CREATE TABLE security_scores (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id),
    overall_score INTEGER NOT NULL,
    grade VARCHAR(2),
    category_scores JSONB,
    recommendations JSONB,
    calculated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_security_scores_user ON security_scores(user_id, calculated_at);
```

#### 3.5.4 Dashboard UI
```javascript
// Real-time security score gauge
const scoreGauge = {
    value: 78,
    grade: 'B',
    trend: '+5 from last week',
    categories: {
        compliance: { score: 85, status: 'good' },
        vulnerabilities: { score: 65, status: 'warning' },
        api_security: { score: 90, status: 'excellent' },
        container_security: { score: 70, status: 'fair' },
        configurations: { score: 80, status: 'good' }
    }
};

// Interactive chart showing trends over time (Premium)
// Top recommendations widget
// Recent scans timeline
```

---

## Phase 4.5: Container Security Suite (WEEK 5-6)

### Overview
Deep security analysis for Docker containers and Kubernetes clusters. Scans images, configurations, and runtime security.

### Implementation Plan

#### 4.5.1 Backend API
```php
class ContainerSecurityController {
    public function scanImage(): void
    public function analyzeDockerfile(): void
    public function auditK8sConfig(): void
    public function checkRuntime(): void  // PREMIUM
    public function scanRegistry(): void  // PREMIUM
}
```

**Endpoints**:
- `POST /api/v1/container/scan-image` - Scan Docker image for vulnerabilities
- `POST /api/v1/container/analyze-dockerfile` - Analyze Dockerfile best practices
- `POST /api/v1/container/k8s-audit` - Audit Kubernetes manifests
- `POST /api/v1/container/runtime-security` - Runtime security monitoring (Premium)
- `POST /api/v1/container/registry-scan` - Scan entire registry (Premium)

#### 4.5.2 Scanner Implementation
```php
// app/src/Utils/ContainerScanner.php
class ContainerScanner {
    public static function scanImage(string $imageName): array {
        $vulnerabilities = [];

        // 1. Scan base image
        $baseImage = self::detectBaseImage($imageName);
        $vulns = ThreatIntel::checkPackages($baseImage);

        // 2. Check for secrets in layers
        $secrets = self::scanForSecrets($imageName);

        // 3. Analyze layer composition
        $layers = self::analyzeLayers($imageName);

        // 4. Check for rootless configuration
        $rootlessScore = self::checkRootless($imageName);

        // 5. Verify image signatures
        $signed = self::verifySignature($imageName);

        return [
            'vulnerabilities' => $vulns,
            'secrets_found' => count($secrets),
            'layers' => count($layers),
            'size' => self::getImageSize($imageName),
            'rootless' => $rootlessScore,
            'signed' => $signed,
            'risk_score' => self::calculateRisk($vulns, $secrets, $rootlessScore)
        ];
    }

    public static function analyzeDockerfile(string $content): array {
        $issues = [];

        // Check for anti-patterns
        if (strpos($content, 'FROM latest') !== false) {
            $issues[] = ['severity' => 'high', 'message' => 'Using :latest tag'];
        }

        if (strpos($content, 'USER root') !== false) {
            $issues[] = ['severity' => 'medium', 'message' => 'Running as root user'];
        }

        // Check for security best practices
        $hasHealthcheck = strpos($content, 'HEALTHCHECK') !== false;
        $hasNonRoot = strpos($content, 'USER ') !== false;
        $usesMultiStage = substr_count($content, 'FROM ') > 1;

        return [
            'issues' => $issues,
            'best_practices' => [
                'healthcheck' => $hasHealthcheck,
                'non_root_user' => $hasNonRoot,
                'multi_stage_build' => $usesMultiStage
            ],
            'score' => self::calculateDockerfileScore($issues, $hasHealthcheck, $hasNonRoot)
        ];
    }
}
```

#### 4.5.3 Kubernetes Security
```php
public static function auditK8sManifest(array $manifest): array {
    $findings = [];

    // Check security contexts
    if (!isset($manifest['spec']['securityContext'])) {
        $findings[] = ['severity' => 'high', 'issue' => 'Missing securityContext'];
    }

    // Check for privileged containers
    foreach ($manifest['spec']['containers'] as $container) {
        if ($container['securityContext']['privileged'] ?? false) {
            $findings[] = ['severity' => 'critical', 'issue' => 'Privileged container detected'];
        }
    }

    // Check network policies
    // Check resource limits
    // Check RBAC configurations

    return $findings;
}
```

---

## Phase 5.5: White-Label Option (WEEK 6-7)

### Overview
Allow MSPs and consultancies to rebrand VeriBits with their own logo, domain, and branding. Full white-label solution at $299/mo.

### Implementation Plan

#### 5.5.1 Multi-Tenancy Architecture
```php
// app/src/Utils/Tenant.php
class Tenant {
    public static function getCurrentTenant(): ?array {
        $domain = $_SERVER['HTTP_HOST'];

        // Check if custom domain
        $tenant = Database::fetch(
            "SELECT * FROM tenants WHERE custom_domain = :domain OR subdomain = :domain",
            ['domain' => $domain]
        );

        return $tenant ?? self::getDefaultTenant();
    }

    public static function getConfig(string $key): mixed {
        $tenant = self::getCurrentTenant();
        return $tenant['config'][$key] ?? Config::get($key);
    }
}
```

#### 5.5.2 Database Schema
```sql
-- db/migrations/019_white_label.sql
CREATE TABLE tenants (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    owner_user_id UUID NOT NULL REFERENCES users(id),
    name VARCHAR(255) NOT NULL,
    subdomain VARCHAR(100) UNIQUE NOT NULL,
    custom_domain VARCHAR(255) UNIQUE,
    config JSONB NOT NULL,
    -- Config includes: logo_url, primary_color, secondary_color, company_name, etc.
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE tenant_users (
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    user_id UUID NOT NULL REFERENCES users(id),
    role VARCHAR(50) DEFAULT 'member',
    PRIMARY KEY (tenant_id, user_id)
);
```

#### 5.5.3 White-Label Features
- Custom logo and branding
- Custom domain (e.g., security.clientcompany.com)
- Custom email templates
- Custom PDF report headers
- Subdomain provisioning (e.g., acmeconsulting.veribits.com)
- SSO integration
- Usage reporting per client

### Monetization
- $299/mo base fee
- Unlimited sub-users
- All Pro features included
- API access for automation
- Priority support

---

## Phase 6.5: Batch Processing (WEEK 7)

### Overview
Bulk scanning capability for enterprises needing to process thousands of assets. Pay-per-scan pricing model.

### Implementation Plan

#### 6.5.1 Batch API
```php
class BatchProcessingController {
    public function createBatch(): void
    public function uploadAssets(): void
    public function startProcessing(): void
    public function getStatus(string $batchId): void
    public function downloadResults(string $batchId): void
}
```

**Endpoints**:
- `POST /api/v1/batch/create` - Create batch job
- `POST /api/v1/batch/{id}/upload` - Upload CSV/JSON of assets
- `POST /api/v1/batch/{id}/start` - Start processing
- `GET /api/v1/batch/{id}/status` - Get progress
- `GET /api/v1/batch/{id}/download` - Download results (CSV/PDF)

#### 6.5.2 Queue System
```php
// app/src/Utils/BatchQueue.php
class BatchQueue {
    public static function enqueue(string $batchId, array $items): void {
        foreach ($items as $item) {
            Redis::lpush("batch:$batchId:queue", json_encode($item));
        }

        // Start workers
        for ($i = 0; $i < 10; $i++) {
            self::startWorker($batchId);
        }
    }

    private static function startWorker(string $batchId): void {
        // Process items from queue
        while ($item = Redis::rpop("batch:$batchId:queue")) {
            $data = json_decode($item, true);
            $result = self::processItem($data);
            Redis::lpush("batch:$batchId:results", json_encode($result));

            // Update progress
            Redis::incr("batch:$batchId:completed");
        }
    }
}
```

#### 6.5.3 Pricing Calculator
```php
public static function calculateBatchCost(int $itemCount): array {
    $tiers = [
        ['min' => 1, 'max' => 100, 'price' => 0.10],
        ['min' => 101, 'max' => 1000, 'price' => 0.05],
        ['min' => 1001, 'max' => 10000, 'price' => 0.02],
        ['min' => 10001, 'max' => PHP_INT_MAX, 'price' => 0.01]
    ];

    $cost = 0;
    $remaining = $itemCount;

    foreach ($tiers as $tier) {
        $tierItems = min($remaining, $tier['max'] - $tier['min'] + 1);
        $cost += $tierItems * $tier['price'];
        $remaining -= $tierItems;

        if ($remaining <= 0) break;
    }

    return [
        'items' => $itemCount,
        'cost' => round($cost, 2),
        'estimated_time' => ceil($itemCount / 100) . ' minutes'
    ];
}
```

---

## Phase 7.5: Training & Certification (WEEK 8-10)

### Overview
Security tool mastery courses with certification. Monetize expertise through education.

### Implementation Plan

#### 7.5.1 Course Structure
```javascript
const courses = {
    'veribits-fundamentals': {
        title: 'VeriBits Security Fundamentals',
        duration: '4 hours',
        price: 49,
        modules: [
            'Introduction to Security Verification',
            'Using the Compliance Auditor',
            'API Security Best Practices',
            'Container Security Essentials',
            'Threat Intelligence Basics'
        ],
        certification: 'VeriBits Certified Associate'
    },

    'veribits-professional': {
        title: 'VeriBits Security Professional',
        duration: '8 hours',
        price: 149,
        modules: [
            'Advanced Compliance Auditing',
            'Threat Hunting with VeriBits',
            'Kubernetes Security Mastery',
            'Building Security Pipelines',
            'Incident Response with VeriBits'
        ],
        certification: 'VeriBits Certified Professional'
    },

    'veribits-expert': {
        title: 'VeriBits Security Expert',
        duration: '16 hours',
        price: 299,
        modules: [
            'Enterprise Architecture Security',
            'Custom Integration Development',
            'Security Automation at Scale',
            'Advanced Threat Intelligence',
            'Consultancy Best Practices'
        ],
        certification: 'VeriBits Certified Expert'
    }
};
```

#### 7.5.2 Learning Management System
```php
class TrainingController {
    public function enrollCourse(string $courseId): void
    public function getProgress(string $courseId): void
    public function submitQuiz(string $moduleId, array $answers): void
    public function takeCertificationExam(string $courseId): void
    public function issueCertificate(string $userId, string $courseId): void
}
```

#### 7.5.3 Database Schema
```sql
-- db/migrations/020_training.sql
CREATE TABLE courses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    duration_hours INTEGER,
    price DECIMAL(10,2),
    modules JSONB,
    certification_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE enrollments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id),
    course_id UUID NOT NULL REFERENCES courses(id),
    progress INTEGER DEFAULT 0,
    completed_modules JSONB,
    enrolled_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP
);

CREATE TABLE certifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id),
    course_id UUID NOT NULL REFERENCES courses(id),
    certificate_number VARCHAR(50) UNIQUE NOT NULL,
    issued_at TIMESTAMP DEFAULT NOW(),
    expires_at TIMESTAMP,
    verification_url TEXT
);
```

#### 7.5.4 Certification Verification
```php
// Public endpoint for certificate verification
public function verifyCertificate(string $certNumber): void {
    $cert = Database::fetch(
        "SELECT c.*, u.email, co.title, co.certification_name
         FROM certifications c
         JOIN users u ON c.user_id = u.id
         JOIN courses co ON c.course_id = co.id
         WHERE c.certificate_number = :cert",
        ['cert' => $certNumber]
    );

    if ($cert && strtotime($cert['expires_at']) > time()) {
        Response::success([
            'valid' => true,
            'holder' => $cert['email'],
            'certification' => $cert['certification_name'],
            'issued' => $cert['issued_at'],
            'expires' => $cert['expires_at']
        ]);
    } else {
        Response::error('Certificate not found or expired', 404);
    }
}
```

### Monetization Strategy
- Individual courses: $49-$299
- Enterprise licensing: $2,999/year (unlimited enrollments)
- Recertification: $99/year
- Custom training: $5,000/day on-site

