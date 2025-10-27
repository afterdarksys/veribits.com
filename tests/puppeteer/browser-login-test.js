const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const BASE_URL = 'https://www.veribits.com';
const TEST_EMAIL = 'straticus1@gmail.com';
const TEST_PASSWORD = 'TestPassword123!';

const results = {
  timestamp: new Date().toISOString(),
  status: 'PENDING',
  steps: [],
  screenshots: [],
  consoleErrors: [],
  networkErrors: [],
  finalUrl: null,
  error: null
};

async function runBrowserLoginTest() {
  let browser;
  const screenshotsDir = path.join(__dirname, '../screenshots');

  try {
    // Ensure screenshots directory exists
    if (!fs.existsSync(screenshotsDir)) {
      fs.mkdirSync(screenshotsDir, { recursive: true });
    }

    console.log('Launching browser...');
    browser = await puppeteer.launch({
      headless: 'new',
      args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();

    // Set viewport
    await page.setViewport({ width: 1920, height: 1080 });

    // Capture console messages
    page.on('console', msg => {
      if (msg.type() === 'error') {
        results.consoleErrors.push(msg.text());
        console.log('Console Error:', msg.text());
      }
    });

    // Capture network errors
    page.on('requestfailed', request => {
      results.networkErrors.push({
        url: request.url(),
        failure: request.failure().errorText
      });
      console.log('Network Error:', request.url(), request.failure().errorText);
    });

    // STEP 1: Navigate to homepage
    console.log('\nStep 1: Navigating to homepage...');
    await page.goto(BASE_URL, { waitUntil: 'networkidle2', timeout: 30000 });
    await page.screenshot({ path: path.join(screenshotsDir, 'browser-01-homepage.png'), fullPage: true });
    results.steps.push({
      step: 1,
      action: 'Navigate to homepage',
      status: 'PASS',
      url: page.url()
    });
    results.screenshots.push('browser-01-homepage.png');
    console.log('  ✓ Homepage loaded:', page.url());

    // STEP 2: Find and click Login button
    console.log('\nStep 2: Looking for Login button...');

    // Try multiple selectors
    const loginSelectors = [
      'a[href*="index.php"]',
      'a[href="/index.php"]',
      'a:has-text("Login")',
      'button:has-text("Login")',
      'a.login-btn',
      '[data-action="login"]'
    ];

    let loginButton = null;
    let usedSelector = null;

    for (const selector of loginSelectors) {
      try {
        loginButton = await page.$(selector);
        if (loginButton) {
          usedSelector = selector;
          console.log(`  Found login button with selector: ${selector}`);
          break;
        }
      } catch (e) {
        // Try next selector
      }
    }

    if (!loginButton) {
      // Try to find by text content
      const links = await page.$$('a');
      for (const link of links) {
        const text = await page.evaluate(el => el.textContent, link);
        if (text && (text.toLowerCase().includes('login') || text.toLowerCase().includes('sign in'))) {
          loginButton = link;
          usedSelector = 'text content match';
          console.log(`  Found login link by text: "${text.trim()}"`);
          break;
        }
      }
    }

    if (!loginButton) {
      throw new Error('Could not find login button/link on homepage');
    }

    await page.screenshot({ path: path.join(screenshotsDir, 'browser-02-before-login-click.png'), fullPage: true });
    results.screenshots.push('browser-02-before-login-click.png');

    await loginButton.click();
    await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 10000 });

    await page.screenshot({ path: path.join(screenshotsDir, 'browser-03-login-page.png'), fullPage: true });
    results.steps.push({
      step: 2,
      action: 'Click Login button',
      status: 'PASS',
      url: page.url(),
      selector: usedSelector
    });
    results.screenshots.push('browser-03-login-page.png');
    console.log('  ✓ Navigated to:', page.url());

    // STEP 3: Fill in email
    console.log('\nStep 3: Filling in email...');

    const emailSelectors = ['input[name="email"]', 'input[type="email"]', 'input#email'];
    let emailField = null;

    for (const selector of emailSelectors) {
      try {
        emailField = await page.$(selector);
        if (emailField) {
          console.log(`  Found email field: ${selector}`);
          break;
        }
      } catch (e) {
        // Try next selector
      }
    }

    if (!emailField) {
      throw new Error('Could not find email input field');
    }

    await emailField.type(TEST_EMAIL);
    results.steps.push({
      step: 3,
      action: 'Enter email',
      status: 'PASS',
      value: TEST_EMAIL
    });
    console.log('  ✓ Email entered');

    // STEP 4: Fill in password
    console.log('\nStep 4: Filling in password...');

    const passwordSelectors = ['input[name="password"]', 'input[type="password"]', 'input#password'];
    let passwordField = null;

    for (const selector of passwordSelectors) {
      try {
        passwordField = await page.$(selector);
        if (passwordField) {
          console.log(`  Found password field: ${selector}`);
          break;
        }
      } catch (e) {
        // Try next selector
      }
    }

    if (!passwordField) {
      throw new Error('Could not find password input field');
    }

    await passwordField.type(TEST_PASSWORD);
    await page.screenshot({ path: path.join(screenshotsDir, 'browser-04-credentials-filled.png'), fullPage: true });
    results.steps.push({
      step: 4,
      action: 'Enter password',
      status: 'PASS'
    });
    results.screenshots.push('browser-04-credentials-filled.png');
    console.log('  ✓ Password entered');

    // STEP 5: Submit form
    console.log('\nStep 5: Submitting login form...');

    const submitSelectors = [
      'button[type="submit"]',
      'input[type="submit"]',
      'button:has-text("Login")',
      'button:has-text("Sign In")',
      '.login-btn',
      '[data-action="submit"]'
    ];

    let submitButton = null;

    for (const selector of submitSelectors) {
      try {
        submitButton = await page.$(selector);
        if (submitButton) {
          console.log(`  Found submit button: ${selector}`);
          break;
        }
      } catch (e) {
        // Try next selector
      }
    }

    if (!submitButton) {
      // Try to find button by text
      const buttons = await page.$$('button');
      for (const btn of buttons) {
        const text = await page.evaluate(el => el.textContent, btn);
        if (text && (text.toLowerCase().includes('login') || text.toLowerCase().includes('sign in') || text.toLowerCase().includes('submit'))) {
          submitButton = btn;
          console.log(`  Found submit button by text: "${text.trim()}"`);
          break;
        }
      }
    }

    if (!submitButton) {
      throw new Error('Could not find submit button');
    }

    // Click and wait for navigation
    await Promise.all([
      submitButton.click(),
      page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 10000 }).catch(e => {
        console.log('  Navigation timeout (may be expected if no redirect)');
      })
    ]);

    await page.screenshot({ path: path.join(screenshotsDir, 'browser-05-after-submit.png'), fullPage: true });
    results.steps.push({
      step: 5,
      action: 'Submit form',
      status: 'PASS',
      url: page.url()
    });
    results.screenshots.push('browser-05-after-submit.png');
    console.log('  ✓ Form submitted');

    // STEP 6: Check final URL
    const finalUrl = page.url();
    results.finalUrl = finalUrl;
    console.log('\nStep 6: Checking final URL...');
    console.log(`  Current URL: ${finalUrl}`);

    if (finalUrl.includes('dashboard')) {
      results.steps.push({
        step: 6,
        action: 'Verify redirect to dashboard',
        status: 'PASS',
        url: finalUrl
      });
      console.log('  ✓ Successfully redirected to dashboard');
      await page.screenshot({ path: path.join(screenshotsDir, 'browser-06-dashboard.png'), fullPage: true });
      results.screenshots.push('browser-06-dashboard.png');
    } else {
      results.steps.push({
        step: 6,
        action: 'Check redirect to dashboard',
        status: 'FAIL',
        url: finalUrl,
        expected: 'URL should contain "dashboard"'
      });
      console.log('  ✗ Did NOT redirect to dashboard');

      // Check for error messages on page
      const bodyText = await page.evaluate(() => document.body.textContent);
      if (bodyText.includes('error') || bodyText.includes('Error') || bodyText.includes('invalid')) {
        console.log('  Error message detected on page');
        results.steps.push({
          step: 6.1,
          action: 'Page contains error message',
          status: 'INFO'
        });
      }
    }

    // STEP 7: Check if user is logged in
    console.log('\nStep 7: Verifying login state...');

    const userIndicators = [
      '.user-email',
      '.user-name',
      '[data-user-email]',
      '[data-user-name]',
      '.logged-in',
      '.user-info'
    ];

    let loggedIn = false;
    for (const selector of userIndicators) {
      const element = await page.$(selector);
      if (element) {
        loggedIn = true;
        const text = await page.evaluate(el => el.textContent, element);
        console.log(`  ✓ User indicator found (${selector}): ${text?.trim()}`);
        results.steps.push({
          step: 7,
          action: 'Verify user logged in',
          status: 'PASS',
          indicator: selector,
          value: text?.trim()
        });
        break;
      }
    }

    if (!loggedIn) {
      // Check cookies
      const cookies = await page.cookies();
      const sessionCookie = cookies.find(c =>
        c.name.toLowerCase().includes('session') ||
        c.name.toLowerCase().includes('auth') ||
        c.name === 'PHPSESSID'
      );

      if (sessionCookie) {
        console.log(`  ✓ Session cookie found: ${sessionCookie.name}`);
        results.steps.push({
          step: 7,
          action: 'Session cookie exists',
          status: 'PASS',
          cookie: sessionCookie.name
        });
        loggedIn = true;
      } else {
        console.log('  ⚠ No user indicator or session cookie found');
        results.steps.push({
          step: 7,
          action: 'Check if logged in',
          status: 'WARNING',
          message: 'No clear indicators of logged-in state'
        });
      }
    }

    // Determine overall status
    if (finalUrl.includes('dashboard') && loggedIn) {
      results.status = 'PASS';
      console.log('\n✓ LOGIN TEST PASSED');
    } else if (finalUrl.includes('dashboard')) {
      results.status = 'WARNING';
      console.log('\n⚠ LOGIN TEST WARNING: Redirected but no clear login indicators');
    } else {
      results.status = 'FAIL';
      console.log('\n✗ LOGIN TEST FAILED');
    }

  } catch (error) {
    results.status = 'FAIL';
    results.error = error.message;
    results.steps.push({
      step: 'ERROR',
      action: 'Exception caught',
      status: 'FAIL',
      error: error.message
    });
    console.error('\n✗ LOGIN TEST ERROR:', error.message);

    if (browser) {
      const pages = await browser.pages();
      if (pages.length > 0) {
        await pages[0].screenshot({
          path: path.join(screenshotsDir, 'browser-error.png'),
          fullPage: true
        });
        results.screenshots.push('browser-error.png');
      }
    }
  } finally {
    if (browser) {
      await browser.close();
    }

    // Save results
    const reportPath = path.join(__dirname, '../test-results/browser-login-test.json');
    const reportDir = path.dirname(reportPath);

    if (!fs.existsSync(reportDir)) {
      fs.mkdirSync(reportDir, { recursive: true });
    }

    fs.writeFileSync(reportPath, JSON.stringify(results, null, 2));
    console.log(`\nReport saved to: ${reportPath}`);
    console.log(`Screenshots saved to: ${screenshotsDir}`);

    return results;
  }
}

// Run the test
runBrowserLoginTest()
  .then(results => {
    console.log('\n=== FINAL RESULTS ===');
    console.log(`Status: ${results.status}`);
    console.log(`Steps completed: ${results.steps.length}`);
    console.log(`Console errors: ${results.consoleErrors.length}`);
    console.log(`Network errors: ${results.networkErrors.length}`);

    if (results.status === 'FAIL') {
      process.exit(1);
    }
  })
  .catch(error => {
    console.error('Test runner error:', error);
    process.exit(1);
  });
