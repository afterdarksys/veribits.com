const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

// Test configuration
const BASE_URL = process.env.BASE_URL || 'http://localhost';
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');
const RESULTS_FILE = path.join(__dirname, 'test-results.json');

// Test credentials (from create_test_accounts.sql)
const TEST_ACCOUNTS = {
    admin: { email: 'admin@veribits-test.com', password: 'Admin123!@#' },
    developer: { email: 'developer@veribits-test.com', password: 'Dev123!@#' },
    professional: { email: 'professional@veribits-test.com', password: 'Pro123!@#' },
    enterprise: { email: 'enterprise@veribits-test.com', password: 'Ent123!@#' },
    suspended: { email: 'suspended@veribits-test.com', password: 'Sus123!@#' }
};

const TEST_API_KEYS = {
    admin: 'vb_admin_test_key_000000000000000000000000000001',
    developer: 'vb_dev_test_key_000000000000000000000000000002',
    professional: 'vb_pro_test_key_000000000000000000000000000003',
    enterprise: 'vb_ent_test_key_000000000000000000000000000004'
};

// Results tracking
const results = {
    timestamp: new Date().toISOString(),
    baseUrl: BASE_URL,
    total: 0,
    passed: 0,
    failed: 0,
    tests: []
};

// Create screenshot directory
if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

// Helper function to save screenshot
async function saveScreenshot(page, testName) {
    const filename = `${testName.replace(/[^a-z0-9]/gi, '_')}_${Date.now()}.png`;
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, filename), fullPage: true });
    return filename;
}

// Helper function to record test result
function recordTest(name, passed, message, screenshot = null) {
    results.total++;
    if (passed) {
        results.passed++;
    } else {
        results.failed++;
    }
    results.tests.push({
        name,
        passed,
        message,
        screenshot,
        timestamp: new Date().toISOString()
    });
    console.log(`${passed ? '✓' : '✗'} ${name}: ${message}`);
}

// Test: Homepage loads
async function testHomepage(browser) {
    const page = await browser.newPage();
    try {
        const response = await page.goto(BASE_URL, { waitUntil: 'networkidle0', timeout: 30000 });
        const status = response.status();
        const title = await page.title();

        if (status === 200 && title.includes('VeriBits')) {
            recordTest('Homepage Load', true, `Status: ${status}, Title: ${title}`);
        } else {
            const screenshot = await saveScreenshot(page, 'homepage_error');
            recordTest('Homepage Load', false, `Status: ${status}, Title: ${title}`, screenshot);
        }
    } catch (error) {
        const screenshot = await saveScreenshot(page, 'homepage_error');
        recordTest('Homepage Load', false, error.message, screenshot);
    } finally {
        await page.close();
    }
}

// Test: Login page loads
async function testLoginPage(browser) {
    const page = await browser.newPage();
    try {
        const response = await page.goto(`${BASE_URL}/login.php`, { waitUntil: 'networkidle0', timeout: 30000 });
        const status = response.status();

        if (status === 200) {
            recordTest('Login Page Load', true, `Status: ${status}`);
        } else {
            const screenshot = await saveScreenshot(page, 'login_page_error');
            recordTest('Login Page Load', false, `Status: ${status}`, screenshot);
        }
    } catch (error) {
        const screenshot = await saveScreenshot(page, 'login_page_error');
        recordTest('Login Page Load', false, error.message, screenshot);
    } finally {
        await page.close();
    }
}

// Test: User login
async function testUserLogin(browser, accountType) {
    const page = await browser.newPage();
    const credentials = TEST_ACCOUNTS[accountType];

    try {
        await page.goto(`${BASE_URL}/login.php`, { waitUntil: 'networkidle0', timeout: 30000 });

        // Fill login form
        await page.type('#email', credentials.email);
        await page.type('#password', credentials.password);

        // Submit form
        await Promise.all([
            page.click('button[type="submit"]'),
            page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 })
        ]);

        // Check if redirected to dashboard
        const url = page.url();
        if (url.includes('/dashboard')) {
            recordTest(`Login (${accountType})`, true, `Successfully logged in as ${credentials.email}`);
            return page; // Return page for further testing
        } else {
            const screenshot = await saveScreenshot(page, `login_${accountType}_error`);
            recordTest(`Login (${accountType})`, false, `Unexpected redirect: ${url}`, screenshot);
            await page.close();
            return null;
        }
    } catch (error) {
        const screenshot = await saveScreenshot(page, `login_${accountType}_error`);
        recordTest(`Login (${accountType})`, false, error.message, screenshot);
        await page.close();
        return null;
    }
}

// Test: Dashboard access
async function testDashboard(page, accountType) {
    try {
        const response = await page.goto(`${BASE_URL}/dashboard.php`, { waitUntil: 'networkidle0', timeout: 30000 });
        const status = response.status();

        if (status === 200) {
            recordTest(`Dashboard Access (${accountType})`, true, `Status: ${status}`);
        } else {
            const screenshot = await saveScreenshot(page, `dashboard_${accountType}_error`);
            recordTest(`Dashboard Access (${accountType})`, false, `Status: ${status}`, screenshot);
        }
    } catch (error) {
        const screenshot = await saveScreenshot(page, `dashboard_${accountType}_error`);
        recordTest(`Dashboard Access (${accountType})`, false, error.message, screenshot);
    }
}

// Test: Tool pages load
async function testToolPages(browser) {
    const tools = [
        'hash-validator',
        'url-encoder',
        'pgp-validate',
        'security-headers',
        'base64-encoder',
        'jwt-decoder',
        'regex-tester',
        'json-validator'
    ];

    for (const tool of tools) {
        const page = await browser.newPage();
        try {
            const response = await page.goto(`${BASE_URL}/tool/${tool}.php`, { waitUntil: 'networkidle0', timeout: 30000 });
            const status = response.status();

            if (status === 200) {
                recordTest(`Tool Page: ${tool}`, true, `Status: ${status}`);
            } else {
                const screenshot = await saveScreenshot(page, `tool_${tool}_error`);
                recordTest(`Tool Page: ${tool}`, false, `Status: ${status}`, screenshot);
            }
        } catch (error) {
            const screenshot = await saveScreenshot(page, `tool_${tool}_error`);
            recordTest(`Tool Page: ${tool}`, false, error.message, screenshot);
        } finally {
            await page.close();
        }
    }
}

// Test: API health endpoint
async function testAPIHealth(browser) {
    const page = await browser.newPage();
    try {
        const response = await page.goto(`${BASE_URL}/api/v1/health`, { waitUntil: 'networkidle0', timeout: 30000 });
        const status = response.status();
        const json = await response.json();

        if (status === 200 && json.status === 'healthy') {
            recordTest('API Health Check', true, `Status: ${status}, Response: ${JSON.stringify(json)}`);
        } else {
            recordTest('API Health Check', false, `Status: ${status}, Response: ${JSON.stringify(json)}`);
        }
    } catch (error) {
        recordTest('API Health Check', false, error.message);
    } finally {
        await page.close();
    }
}

// Test: API authentication with key
async function testAPIKeyAuth(browser, accountType) {
    const page = await browser.newPage();
    const apiKey = TEST_API_KEYS[accountType];

    try {
        // Set API key header
        await page.setExtraHTTPHeaders({
            'X-API-Key': apiKey
        });

        const response = await page.goto(`${BASE_URL}/api/v1/auth/verify`, { waitUntil: 'networkidle0', timeout: 30000 });
        const status = response.status();
        const json = await response.json();

        if (status === 200 && json.success) {
            recordTest(`API Key Auth (${accountType})`, true, `Verified API key for ${accountType}`);
        } else {
            recordTest(`API Key Auth (${accountType})`, false, `Status: ${status}, Response: ${JSON.stringify(json)}`);
        }
    } catch (error) {
        recordTest(`API Key Auth (${accountType})`, false, error.message);
    } finally {
        await page.close();
    }
}

// Test: URL Encoder tool
async function testURLEncoderAPI(browser) {
    const page = await browser.newPage();
    try {
        const testData = { text: 'Hello World!', operation: 'encode' };

        const response = await page.evaluate(async (url, data) => {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            return {
                status: res.status,
                json: await res.json()
            };
        }, `${BASE_URL}/api/v1/tools/url/encode`, testData);

        if (response.status === 200 && response.json.success && response.json.data.result === 'Hello+World%21') {
            recordTest('URL Encoder API', true, `Encoded successfully: ${response.json.data.result}`);
        } else {
            recordTest('URL Encoder API', false, `Status: ${response.status}, Response: ${JSON.stringify(response.json)}`);
        }
    } catch (error) {
        recordTest('URL Encoder API', false, error.message);
    } finally {
        await page.close();
    }
}

// Test: Hash Validator API
async function testHashValidatorAPI(browser) {
    const page = await browser.newPage();
    try {
        const testData = { hash: '5d41402abc4b2a76b9719d911017c592', type: 'md5' };

        const response = await page.evaluate(async (url, data) => {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            return {
                status: res.status,
                json: await res.json()
            };
        }, `${BASE_URL}/api/v1/tools/hash/validate`, testData);

        if (response.status === 200 && response.json.success) {
            recordTest('Hash Validator API', true, `Hash validated: ${testData.type}`);
        } else {
            recordTest('Hash Validator API', false, `Status: ${response.status}, Response: ${JSON.stringify(response.json)}`);
        }
    } catch (error) {
        recordTest('Hash Validator API', false, error.message);
    } finally {
        await page.close();
    }
}

// Test: Security Headers Analyzer
async function testSecurityHeadersAPI(browser) {
    const page = await browser.newPage();
    try {
        const testData = { url: 'https://www.google.com' };

        const response = await page.evaluate(async (url, data) => {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            return {
                status: res.status,
                json: await res.json()
            };
        }, `${BASE_URL}/api/v1/tools/security-headers`, testData);

        if (response.status === 200 && response.json.success) {
            recordTest('Security Headers API', true, `Analyzed headers for ${testData.url}`);
        } else {
            recordTest('Security Headers API', false, `Status: ${response.status}, Response: ${JSON.stringify(response.json)}`);
        }
    } catch (error) {
        recordTest('Security Headers API', false, error.message);
    } finally {
        await page.close();
    }
}

// Test: Suspended user cannot access
async function testSuspendedUserAccess(browser) {
    const page = await browser.newPage();
    const credentials = TEST_ACCOUNTS.suspended;

    try {
        await page.goto(`${BASE_URL}/login.php`, { waitUntil: 'networkidle0', timeout: 30000 });

        await page.type('#email', credentials.email);
        await page.type('#password', credentials.password);

        await Promise.all([
            page.click('button[type="submit"]'),
            page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 })
        ]);

        // Should see error message about account suspension
        const content = await page.content();
        if (content.includes('suspended') || content.includes('inactive') || content.includes('disabled')) {
            recordTest('Suspended User Access Control', true, 'Suspended user correctly denied access');
        } else {
            const screenshot = await saveScreenshot(page, 'suspended_user_access');
            recordTest('Suspended User Access Control', false, 'Suspended user was able to login', screenshot);
        }
    } catch (error) {
        const screenshot = await saveScreenshot(page, 'suspended_user_error');
        recordTest('Suspended User Access Control', false, error.message, screenshot);
    } finally {
        await page.close();
    }
}

// Main test runner
async function runTests() {
    console.log('Starting comprehensive Puppeteer tests...');
    console.log(`Base URL: ${BASE_URL}`);
    console.log('='.repeat(80));

    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    try {
        // Unauthenticated tests
        console.log('\n📋 Running unauthenticated tests...');
        await testHomepage(browser);
        await testLoginPage(browser);
        await testAPIHealth(browser);
        await testToolPages(browser);

        // API tests (anonymous)
        console.log('\n📋 Running anonymous API tests...');
        await testURLEncoderAPI(browser);
        await testHashValidatorAPI(browser);
        await testSecurityHeadersAPI(browser);

        // Authenticated tests
        console.log('\n📋 Running authenticated tests...');

        // Test admin login and dashboard
        const adminPage = await testUserLogin(browser, 'admin');
        if (adminPage) {
            await testDashboard(adminPage, 'admin');
            await adminPage.close();
        }

        // Test developer login
        const devPage = await testUserLogin(browser, 'developer');
        if (devPage) {
            await testDashboard(devPage, 'developer');
            await devPage.close();
        }

        // Test professional login
        const proPage = await testUserLogin(browser, 'professional');
        if (proPage) {
            await testDashboard(proPage, 'professional');
            await proPage.close();
        }

        // Test enterprise login
        const entPage = await testUserLogin(browser, 'enterprise');
        if (entPage) {
            await testDashboard(entPage, 'enterprise');
            await entPage.close();
        }

        // Test suspended user
        await testSuspendedUserAccess(browser);

        // API Key tests
        console.log('\n📋 Running API key authentication tests...');
        await testAPIKeyAuth(browser, 'admin');
        await testAPIKeyAuth(browser, 'developer');
        await testAPIKeyAuth(browser, 'professional');
        await testAPIKeyAuth(browser, 'enterprise');

    } finally {
        await browser.close();
    }

    // Save results
    fs.writeFileSync(RESULTS_FILE, JSON.stringify(results, null, 2));

    // Print summary
    console.log('\n' + '='.repeat(80));
    console.log('TEST SUMMARY');
    console.log('='.repeat(80));
    console.log(`Total Tests: ${results.total}`);
    console.log(`Passed: ${results.passed} (${((results.passed / results.total) * 100).toFixed(1)}%)`);
    console.log(`Failed: ${results.failed} (${((results.failed / results.total) * 100).toFixed(1)}%)`);
    console.log(`\nResults saved to: ${RESULTS_FILE}`);
    console.log(`Screenshots saved to: ${SCREENSHOT_DIR}`);

    // Exit with error code if tests failed
    process.exit(results.failed > 0 ? 1 : 0);
}

// Run tests
runTests().catch(error => {
    console.error('Fatal error running tests:', error);
    process.exit(1);
});
