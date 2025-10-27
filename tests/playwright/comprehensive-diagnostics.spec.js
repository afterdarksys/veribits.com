const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const BASE_URL = 'https://www.veribits.com';
const TEST_EMAIL = 'straticus1@gmail.com';
const TEST_PASSWORD = 'TestPassword123!';
const API_KEY = 'vb_f4837536eaae908c4cf38a47ac732e9c3cedf970951fcd45';

// Results collector
const results = {
  timestamp: new Date().toISOString(),
  summary: {
    totalTests: 0,
    passed: 0,
    failed: 0,
    warnings: 0
  },
  browserLogin: {},
  apiAuth: {},
  tools: [],
  navigation: {},
  errors: [],
  cloudfront: {},
  recommendations: []
};

test.describe('VeriBits Comprehensive Enterprise Diagnostics', () => {

  test.beforeAll(async () => {
    // Ensure screenshots directory exists
    const screenshotsDir = path.join(__dirname, '../screenshots');
    if (!fs.existsSync(screenshotsDir)) {
      fs.mkdirSync(screenshotsDir, { recursive: true });
    }
  });

  test('1. CRITICAL: Browser Login Flow Test', async ({ page }) => {
    const loginTest = {
      status: 'PENDING',
      steps: [],
      screenshots: [],
      errors: []
    };

    try {
      // Step 1: Navigate to homepage
      console.log('Step 1: Navigating to homepage...');
      await page.goto(BASE_URL, { waitUntil: 'networkidle', timeout: 30000 });
      await page.screenshot({ path: 'tests/screenshots/01-homepage.png', fullPage: true });
      loginTest.steps.push({ step: 1, action: 'Navigate to homepage', status: 'PASS' });
      loginTest.screenshots.push('01-homepage.png');

      // Check console errors
      const consoleErrors = [];
      page.on('console', msg => {
        if (msg.type() === 'error') {
          consoleErrors.push(msg.text());
        }
      });

      // Step 2: Click Login button
      console.log('Step 2: Looking for Login button...');
      const loginButton = await page.locator('a[href*="index.php"], a:has-text("Login"), button:has-text("Login")').first();
      await loginButton.waitFor({ timeout: 5000 });
      await page.screenshot({ path: 'tests/screenshots/02-before-login-click.png', fullPage: true });
      await loginButton.click();
      await page.waitForLoadState('networkidle');
      await page.screenshot({ path: 'tests/screenshots/03-login-page.png', fullPage: true });
      loginTest.steps.push({ step: 2, action: 'Click Login button', status: 'PASS' });
      loginTest.screenshots.push('02-before-login-click.png', '03-login-page.png');

      // Step 3: Check if we're on login page
      const currentUrl = page.url();
      console.log('Current URL after login click:', currentUrl);
      loginTest.steps.push({ step: 3, action: `URL is ${currentUrl}`, status: 'INFO' });

      // Step 4: Enter email
      console.log('Step 4: Entering email...');
      const emailField = await page.locator('input[name="email"], input[type="email"], input[id="email"]').first();
      await emailField.waitFor({ timeout: 5000 });
      await emailField.fill(TEST_EMAIL);
      loginTest.steps.push({ step: 4, action: 'Enter email', status: 'PASS' });

      // Step 5: Enter password
      console.log('Step 5: Entering password...');
      const passwordField = await page.locator('input[name="password"], input[type="password"], input[id="password"]').first();
      await passwordField.fill(TEST_PASSWORD);
      await page.screenshot({ path: 'tests/screenshots/04-credentials-entered.png', fullPage: true });
      loginTest.steps.push({ step: 5, action: 'Enter password', status: 'PASS' });
      loginTest.screenshots.push('04-credentials-entered.png');

      // Step 6: Submit form
      console.log('Step 6: Submitting form...');
      const submitButton = await page.locator('button[type="submit"], input[type="submit"], button:has-text("Login"), button:has-text("Sign In")').first();
      await submitButton.click();

      // Wait for navigation
      await page.waitForLoadState('networkidle', { timeout: 10000 });
      await page.screenshot({ path: 'tests/screenshots/05-after-submit.png', fullPage: true });
      loginTest.steps.push({ step: 6, action: 'Submit form', status: 'PASS' });
      loginTest.screenshots.push('05-after-submit.png');

      // Step 7: Verify redirect to dashboard
      const finalUrl = page.url();
      console.log('Final URL after login:', finalUrl);
      loginTest.steps.push({ step: 7, action: `Final URL: ${finalUrl}`, status: 'INFO' });

      if (finalUrl.includes('dashboard')) {
        loginTest.steps.push({ step: 7, action: 'Redirected to dashboard', status: 'PASS' });
        await page.screenshot({ path: 'tests/screenshots/06-dashboard.png', fullPage: true });
        loginTest.screenshots.push('06-dashboard.png');

        // Step 8: Verify user is logged in
        const userElement = await page.locator('[data-user-email], .user-email, .user-name, [class*="user"]').first();
        if (await userElement.count() > 0) {
          loginTest.steps.push({ step: 8, action: 'User element found - logged in', status: 'PASS' });
        } else {
          loginTest.steps.push({ step: 8, action: 'No user element found', status: 'WARNING' });
        }

        loginTest.status = 'PASS';
      } else {
        loginTest.steps.push({ step: 7, action: 'Did NOT redirect to dashboard', status: 'FAIL' });
        loginTest.status = 'FAIL';
        loginTest.errors.push(`Expected redirect to dashboard, got: ${finalUrl}`);
      }

      // Capture console errors
      if (consoleErrors.length > 0) {
        loginTest.errors.push(...consoleErrors);
        loginTest.status = 'FAIL';
      }

    } catch (error) {
      loginTest.status = 'FAIL';
      loginTest.errors.push(error.message);
      console.error('Login test failed:', error);
      await page.screenshot({ path: 'tests/screenshots/error-login.png', fullPage: true });
    }

    results.browserLogin = loginTest;
    results.summary.totalTests++;
    if (loginTest.status === 'PASS') results.summary.passed++;
    else results.summary.failed++;

    expect(loginTest.status).toBe('PASS');
  });

  test('2. API Authentication Endpoints', async ({ request }) => {
    const apiTest = {
      login: {},
      register: {},
      tokenValidation: {}
    };

    // Test Login API
    try {
      console.log('Testing POST /api/v1/auth/login...');
      const loginResponse = await request.post(`${BASE_URL}/api/v1/auth/login`, {
        data: {
          email: TEST_EMAIL,
          password: TEST_PASSWORD
        },
        headers: {
          'Content-Type': 'application/json'
        }
      });

      apiTest.login.status = loginResponse.status();
      apiTest.login.statusText = loginResponse.statusText();

      try {
        const loginData = await loginResponse.json();
        apiTest.login.response = loginData;
        if (loginData.token || loginData.jwt || loginData.access_token) {
          apiTest.login.tokenReceived = true;
        }
      } catch (e) {
        apiTest.login.response = await loginResponse.text();
      }

    } catch (error) {
      apiTest.login.error = error.message;
    }

    // Test Register API
    try {
      console.log('Testing POST /api/v1/auth/register...');
      const testUser = {
        email: `test-${Date.now()}@example.com`,
        password: 'TestPass123!',
        name: 'Test User'
      };

      const registerResponse = await request.post(`${BASE_URL}/api/v1/auth/register`, {
        data: testUser,
        headers: {
          'Content-Type': 'application/json'
        }
      });

      apiTest.register.status = registerResponse.status();
      apiTest.register.statusText = registerResponse.statusText();

      try {
        apiTest.register.response = await registerResponse.json();
      } catch (e) {
        apiTest.register.response = await registerResponse.text();
      }

    } catch (error) {
      apiTest.register.error = error.message;
    }

    // Test API Key validation
    try {
      console.log('Testing API Key authentication...');
      const apiKeyResponse = await request.get(`${BASE_URL}/api/v1/tools`, {
        headers: {
          'X-API-Key': API_KEY
        }
      });

      apiTest.tokenValidation.status = apiKeyResponse.status();
      apiTest.tokenValidation.statusText = apiKeyResponse.statusText();

      try {
        apiTest.tokenValidation.response = await apiKeyResponse.json();
      } catch (e) {
        apiTest.tokenValidation.response = await apiKeyResponse.text();
      }

    } catch (error) {
      apiTest.tokenValidation.error = error.message;
    }

    results.apiAuth = apiTest;
    results.summary.totalTests++;

    if (apiTest.login.status === 200 || apiTest.login.status === 201) {
      results.summary.passed++;
    } else {
      results.summary.failed++;
    }
  });

  test('3. All Tools Availability Check', async ({ page }) => {
    const tools = [
      // Developer Tools
      { name: 'JWT Debugger', path: '/tool/jwt-debugger.php', category: 'Developer' },
      { name: 'Regex Tester', path: '/tool/regex-tester.php', category: 'Developer' },
      { name: 'URL Encoder', path: '/tool/url-encoder.php', category: 'Developer' },
      { name: 'Hash Generator', path: '/tool/hash-generator.php', category: 'Developer' },
      { name: 'JSON/YAML Validator', path: '/tool/json-yaml-validator.php', category: 'Developer' },
      { name: 'Base64 Encoder', path: '/tool/base64-encoder.php', category: 'Developer' },

      // Network Tools
      { name: 'IP Calculator', path: '/tool/ip-calculator.php', category: 'Network' },
      { name: 'Visual Traceroute', path: '/tool/visual-traceroute.php', category: 'Network' },
      { name: 'BGP Intelligence', path: '/tool/bgp-intelligence.php', category: 'Network' },
      { name: 'PCAP Analyzer', path: '/tool/pcap-analyzer.php', category: 'Network' },

      // DNS Tools
      { name: 'DNS Validator', path: '/tool/dns-validator.php', category: 'DNS' },
      { name: 'Zone Validator', path: '/tool/zone-validator.php', category: 'DNS' },
      { name: 'DNSSEC Validator', path: '/tool/dnssec-validator.php', category: 'DNS' },
      { name: 'DNS Propagation Checker', path: '/tool/dns-propagation.php', category: 'DNS' },
      { name: 'Reverse DNS Lookup', path: '/tool/reverse-dns.php', category: 'DNS' },
      { name: 'DNS Migration Tools', path: '/tool/dns-converter.php', category: 'DNS' },

      // Security Tools
      { name: 'SSL Generator', path: '/tool/ssl-generator.php', category: 'Security' },
      { name: 'Code Signing', path: '/tool/code-signing.php', category: 'Security' },
      { name: 'Crypto Validator', path: '/tool/crypto-validator.php', category: 'Security' },
      { name: 'RBL Check', path: '/tool/rbl-check.php', category: 'Security' },
      { name: 'SMTP Relay Check', path: '/tool/smtp-relay-check.php', category: 'Security' },
      { name: 'Steganography', path: '/tool/steganography.php', category: 'Security' },
      { name: 'Security Headers', path: '/tool/security-headers.php', category: 'Security' },
      { name: 'Secrets Scanner', path: '/tool/secrets-scanner.php', category: 'Security' },
      { name: 'PGP Validator', path: '/tool/pgp-validator.php', category: 'Security' },
      { name: 'Hash Validator', path: '/tool/hash-validator.php', category: 'Security' },

      // DevOps Tools
      { name: 'Docker Scanner', path: '/tool/docker-scanner.php', category: 'DevOps' },
      { name: 'Terraform Scanner', path: '/tool/terraform-scanner.php', category: 'DevOps' },
      { name: 'Kubernetes Validator', path: '/tool/kubernetes-validator.php', category: 'DevOps' },
      { name: 'Firewall Editor', path: '/tool/firewall-editor.php', category: 'DevOps' },
      { name: 'IAM Policy Analyzer', path: '/tool/iam-policy-analyzer.php', category: 'DevOps' },
      { name: 'DB Connection Auditor', path: '/tool/db-connection-auditor.php', category: 'DevOps' },

      // File Tools
      { name: 'File Magic', path: '/tool/file-magic.php', category: 'File' },
      { name: 'Cert Converter', path: '/tool/cert-converter.php', category: 'File' },
    ];

    for (const tool of tools) {
      const toolResult = {
        name: tool.name,
        path: tool.path,
        category: tool.category,
        status: 'PENDING',
        httpStatus: null,
        loadTime: 0,
        errors: []
      };

      try {
        const startTime = Date.now();
        const response = await page.goto(`${BASE_URL}${tool.path}`, {
          waitUntil: 'networkidle',
          timeout: 15000
        });
        toolResult.loadTime = Date.now() - startTime;
        toolResult.httpStatus = response.status();

        if (response.status() === 200) {
          // Check if page contains error messages
          const bodyText = await page.textContent('body');
          if (bodyText.includes('Fatal error') || bodyText.includes('Warning:') || bodyText.includes('Parse error')) {
            toolResult.status = 'ERROR';
            toolResult.errors.push('PHP error detected on page');
          } else {
            toolResult.status = 'WORKING';
          }
        } else if (response.status() === 404) {
          toolResult.status = 'NOT_FOUND';
        } else if (response.status() >= 500) {
          toolResult.status = 'SERVER_ERROR';
        } else {
          toolResult.status = 'WARNING';
        }

      } catch (error) {
        toolResult.status = 'FAILED';
        toolResult.errors.push(error.message);
      }

      results.tools.push(toolResult);
      console.log(`${tool.name}: ${toolResult.status} (${toolResult.httpStatus})`);
    }

    results.summary.totalTests += tools.length;
    results.summary.passed += results.tools.filter(t => t.status === 'WORKING').length;
    results.summary.failed += results.tools.filter(t => t.status === 'FAILED' || t.status === 'SERVER_ERROR').length;
    results.summary.warnings += results.tools.filter(t => t.status === 'WARNING' || t.status === 'NOT_FOUND').length;
  });

  test('4. Navigation and Links Check', async ({ page }) => {
    const navTest = {
      mainNav: [],
      toolsPage: {},
      mobileNav: {},
      brokenLinks: []
    };

    try {
      // Check main navigation
      await page.goto(`${BASE_URL}/tools.php`, { waitUntil: 'networkidle' });

      const navLinks = await page.locator('nav a, header a').all();
      for (const link of navLinks) {
        const href = await link.getAttribute('href');
        const text = await link.textContent();
        navTest.mainNav.push({ text: text?.trim(), href });
      }

      // Check tool cards on tools.php
      const toolCards = await page.locator('.tool-card, [class*="tool"], a[href*="/tool/"]').all();
      navTest.toolsPage.cardCount = toolCards.length;
      navTest.toolsPage.status = toolCards.length > 0 ? 'PASS' : 'FAIL';

      // Test mobile navigation
      await page.setViewportSize({ width: 375, height: 667 });
      await page.screenshot({ path: 'tests/screenshots/mobile-navigation.png', fullPage: true });

      const mobileMenuButton = page.locator('button[aria-label*="menu"], .mobile-menu-toggle, [class*="hamburger"]');
      if (await mobileMenuButton.count() > 0) {
        navTest.mobileNav.hasToggle = true;
        await mobileMenuButton.click();
        await page.screenshot({ path: 'tests/screenshots/mobile-menu-open.png', fullPage: true });
        navTest.mobileNav.status = 'WORKING';
      } else {
        navTest.mobileNav.hasToggle = false;
        navTest.mobileNav.status = 'NOT_FOUND';
      }

    } catch (error) {
      navTest.errors = [error.message];
    }

    results.navigation = navTest;
    results.summary.totalTests++;
    if (navTest.toolsPage.status === 'PASS') results.summary.passed++;
    else results.summary.failed++;
  });

  test('5. CloudFront and Cache Analysis', async ({ request }) => {
    const cfTest = {
      enabled: false,
      distributionId: null,
      headers: {},
      cacheControl: null
    };

    try {
      const response = await request.get(BASE_URL);
      const headers = response.headers();

      cfTest.headers = headers;

      if (headers['x-amz-cf-id'] || headers['x-amzn-trace-id']) {
        cfTest.enabled = true;
        cfTest.distributionId = headers['x-amz-cf-id'];
      }

      cfTest.cacheControl = headers['cache-control'];
      cfTest.xCache = headers['x-cache'];
      cfTest.via = headers['via'];

    } catch (error) {
      cfTest.error = error.message;
    }

    results.cloudfront = cfTest;
  });

  test.afterAll(async () => {
    // Generate comprehensive report
    const reportPath = path.join(__dirname, '../test-results/comprehensive-diagnostics-report.json');
    const reportDir = path.dirname(reportPath);

    if (!fs.existsSync(reportDir)) {
      fs.mkdirSync(reportDir, { recursive: true });
    }

    // Categorize issues
    const issues = {
      CRITICAL: [],
      HIGH: [],
      MEDIUM: [],
      LOW: []
    };

    // Analyze browser login
    if (results.browserLogin.status === 'FAIL') {
      issues.CRITICAL.push({
        component: 'Browser Login',
        description: 'Login flow is broken',
        errors: results.browserLogin.errors,
        impact: 'Users cannot log in via browser'
      });
    }

    // Analyze tools
    const brokenTools = results.tools.filter(t => t.status === 'FAILED' || t.status === 'SERVER_ERROR');
    const notFoundTools = results.tools.filter(t => t.status === 'NOT_FOUND');

    if (brokenTools.length > 0) {
      issues.HIGH.push({
        component: 'Tools',
        description: `${brokenTools.length} tools are broken or returning server errors`,
        tools: brokenTools.map(t => t.name),
        impact: 'Core functionality unavailable'
      });
    }

    if (notFoundTools.length > 0) {
      issues.MEDIUM.push({
        component: 'Tools',
        description: `${notFoundTools.length} tools return 404`,
        tools: notFoundTools.map(t => t.name),
        impact: 'Tools not found or URLs incorrect'
      });
    }

    // Analyze API
    if (results.apiAuth.login.status !== 200 && results.apiAuth.login.status !== 201) {
      issues.HIGH.push({
        component: 'API Authentication',
        description: 'Login API is not working',
        status: results.apiAuth.login.status,
        response: results.apiAuth.login.response,
        impact: 'CLI and API integrations broken'
      });
    }

    results.issues = issues;

    // Generate recommendations
    const recommendations = [];

    if (issues.CRITICAL.length > 0) {
      recommendations.push({
        priority: 1,
        title: 'FIX CRITICAL: Restore browser login functionality',
        actions: [
          'Check session handling in AuthController.php',
          'Verify database connectivity for user authentication',
          'Check CSRF token generation and validation',
          'Review cookie settings and domain configuration'
        ]
      });
    }

    if (brokenTools.length > 5) {
      recommendations.push({
        priority: 2,
        title: 'FIX HIGH: Repair broken tools',
        actions: [
          'Review autoloader and class loading',
          'Check database connections in tool controllers',
          'Verify all required PHP extensions are installed',
          'Review error logs for specific PHP errors'
        ]
      });
    }

    if (!results.cloudfront.enabled) {
      recommendations.push({
        priority: 3,
        title: 'OPTIMIZE: Enable CloudFront caching',
        actions: [
          'Configure CloudFront distribution',
          'Set appropriate cache-control headers',
          'Implement cache busting for static assets',
          'Add WAF rules for security'
        ]
      });
    }

    results.recommendations = recommendations;

    fs.writeFileSync(reportPath, JSON.stringify(results, null, 2));
    console.log('\n=== DIAGNOSTIC REPORT SAVED ===');
    console.log(`Report: ${reportPath}`);
    console.log(`\nSummary:`);
    console.log(`  Total Tests: ${results.summary.totalTests}`);
    console.log(`  Passed: ${results.summary.passed}`);
    console.log(`  Failed: ${results.summary.failed}`);
    console.log(`  Warnings: ${results.summary.warnings}`);
    console.log(`\nIssues Found:`);
    console.log(`  CRITICAL: ${issues.CRITICAL.length}`);
    console.log(`  HIGH: ${issues.HIGH.length}`);
    console.log(`  MEDIUM: ${issues.MEDIUM.length}`);
    console.log(`  LOW: ${issues.LOW.length}`);
  });
});
