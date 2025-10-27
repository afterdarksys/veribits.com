/**
 * VeriBits Mobile Navigation Layout Test
 *
 * Tests the responsive navigation bar at multiple viewport sizes
 * to ensure no overlapping between logo, search box, and "Go" button.
 *
 * Test viewports:
 * - Desktop: 1920x1080
 * - Tablet: 768x1024
 * - Mobile: 375x667 (iPhone SE)
 * - Mobile: 390x844 (iPhone 12)
 */

const puppeteer = require('puppeteer');
const fs = require('fs').promises;
const path = require('path');

// Configuration
const BASE_URL = process.env.TEST_URL || 'http://localhost:8080';
const PAGE_PATH = '/home.php';
const SCREENSHOTS_DIR = path.join(__dirname, '..', 'screenshots');
const REPORT_FILE = path.join(__dirname, '..', 'mobile-navigation-test-report.json');

// Test viewports
const VIEWPORTS = [
    { name: 'Desktop', width: 1920, height: 1080 },
    { name: 'Tablet', width: 768, height: 1024 },
    { name: 'iPhone SE', width: 375, height: 667 },
    { name: 'iPhone 12', width: 390, height: 844 }
];

// Ensure screenshots directory exists
async function ensureDirectoryExists(dir) {
    try {
        await fs.mkdir(dir, { recursive: true });
        console.log(`✓ Screenshots directory ready: ${dir}`);
    } catch (error) {
        console.error(`Error creating directory ${dir}:`, error);
    }
}

// Helper function to check if two elements overlap
function checkOverlap(box1, box2) {
    return !(
        box1.right < box2.left ||
        box1.left > box2.right ||
        box1.bottom < box2.top ||
        box1.top > box2.bottom
    );
}

// Helper function to calculate overlap area
function calculateOverlapArea(box1, box2) {
    if (!checkOverlap(box1, box2)) {
        return 0;
    }

    const xOverlap = Math.min(box1.right, box2.right) - Math.max(box1.left, box2.left);
    const yOverlap = Math.min(box1.bottom, box2.bottom) - Math.max(box1.top, box2.top);

    return xOverlap * yOverlap;
}

// Get element bounding box
async function getElementBounds(page, selector) {
    try {
        const element = await page.$(selector);
        if (!element) {
            return null;
        }

        const box = await element.boundingBox();
        if (!box) {
            return null;
        }

        return {
            x: Math.round(box.x),
            y: Math.round(box.y),
            width: Math.round(box.width),
            height: Math.round(box.height),
            left: Math.round(box.x),
            right: Math.round(box.x + box.width),
            top: Math.round(box.y),
            bottom: Math.round(box.y + box.height)
        };
    } catch (error) {
        console.error(`Error getting bounds for ${selector}:`, error.message);
        return null;
    }
}

// Test navigation at specific viewport
async function testViewport(browser, viewport) {
    console.log(`\n${'='.repeat(60)}`);
    console.log(`Testing viewport: ${viewport.name} (${viewport.width}x${viewport.height})`);
    console.log('='.repeat(60));

    const page = await browser.newPage();

    // Set viewport
    await page.setViewport({
        width: viewport.width,
        height: viewport.height,
        deviceScaleFactor: 1
    });

    // Navigate to page
    console.log(`Navigating to ${BASE_URL}${PAGE_PATH}...`);
    await page.goto(`${BASE_URL}${PAGE_PATH}`, {
        waitUntil: 'networkidle2',
        timeout: 30000
    });

    // Wait for navigation to be visible
    await page.waitForSelector('.nav-container', { timeout: 5000 });

    // Take full page screenshot
    const screenshotPath = path.join(SCREENSHOTS_DIR, `${viewport.name.replace(/\s+/g, '-')}-${viewport.width}x${viewport.height}.png`);
    await page.screenshot({
        path: screenshotPath,
        fullPage: false
    });
    console.log(`✓ Screenshot saved: ${screenshotPath}`);

    // Get bounding boxes for critical elements
    console.log('\nGetting element bounding boxes...');

    const logoBounds = await getElementBounds(page, '.nav-logo');
    const searchContainerBounds = await getElementBounds(page, '.nav-search');
    const searchInputBounds = await getElementBounds(page, '#tool-search');
    const goButtonBounds = await getElementBounds(page, '#search-go');
    const navMenuBounds = await getElementBounds(page, '.nav-menu');

    // Get additional metrics
    const metrics = await page.evaluate(() => {
        const logo = document.querySelector('.nav-logo');
        const searchContainer = document.querySelector('.nav-search');
        const goButton = document.querySelector('#search-go');
        const navMenu = document.querySelector('.nav-menu');
        const navContainer = document.querySelector('.nav-container');

        return {
            logoVisible: logo ? window.getComputedStyle(logo).display !== 'none' : false,
            searchVisible: searchContainer ? window.getComputedStyle(searchContainer).display !== 'none' : false,
            goButtonVisible: goButton ? window.getComputedStyle(goButton).display !== 'none' : false,
            navMenuVisible: navMenu ? window.getComputedStyle(navMenu).display !== 'none' : false,
            containerFlexWrap: navContainer ? window.getComputedStyle(navContainer).flexWrap : 'unknown',
            searchOrder: searchContainer ? window.getComputedStyle(searchContainer).order : 'unknown',
            viewportWidth: window.innerWidth,
            viewportHeight: window.innerHeight
        };
    });

    console.log('\nElement Visibility:');
    console.log(`  Logo: ${metrics.logoVisible ? '✓' : '✗'}`);
    console.log(`  Search Container: ${metrics.searchVisible ? '✓' : '✗'}`);
    console.log(`  Go Button: ${metrics.goButtonVisible ? '✓' : '✗'}`);
    console.log(`  Nav Menu: ${metrics.navMenuVisible ? '✓' : '✗'}`);
    console.log(`  Container Flex Wrap: ${metrics.containerFlexWrap}`);
    console.log(`  Search Order: ${metrics.searchOrder}`);

    // Check for overlaps
    const issues = [];
    const overlaps = [];

    if (logoBounds && goButtonBounds) {
        const overlap = checkOverlap(logoBounds, goButtonBounds);
        const overlapArea = calculateOverlapArea(logoBounds, goButtonBounds);

        if (overlap) {
            issues.push(`CRITICAL: Logo and Go button overlap detected! (${overlapArea}px²)`);
            overlaps.push({
                elements: ['Logo', 'Go Button'],
                area: overlapArea,
                severity: 'CRITICAL'
            });
        } else {
            console.log('\n✓ PASS: No overlap between Logo and Go button');
        }
    }

    if (logoBounds && searchInputBounds) {
        const overlap = checkOverlap(logoBounds, searchInputBounds);
        const overlapArea = calculateOverlapArea(logoBounds, searchInputBounds);

        if (overlap) {
            issues.push(`WARNING: Logo and search input overlap detected! (${overlapArea}px²)`);
            overlaps.push({
                elements: ['Logo', 'Search Input'],
                area: overlapArea,
                severity: 'WARNING'
            });
        } else {
            console.log('✓ PASS: No overlap between Logo and search input');
        }
    }

    if (searchInputBounds && navMenuBounds) {
        const overlap = checkOverlap(searchInputBounds, navMenuBounds);
        const overlapArea = calculateOverlapArea(searchInputBounds, navMenuBounds);

        if (overlap) {
            issues.push(`INFO: Search input and nav menu overlap detected (${overlapArea}px²)`);
            overlaps.push({
                elements: ['Search Input', 'Nav Menu'],
                area: overlapArea,
                severity: 'INFO'
            });
        } else {
            console.log('✓ PASS: No overlap between search input and nav menu');
        }
    }

    // Check element visibility and positioning
    if (!metrics.logoVisible) {
        issues.push('ERROR: Logo is not visible');
    }

    if (!metrics.searchVisible) {
        issues.push('ERROR: Search container is not visible');
    }

    if (!metrics.goButtonVisible) {
        issues.push('ERROR: Go button is not visible');
    }

    if (!metrics.navMenuVisible) {
        issues.push('ERROR: Navigation menu is not visible');
    }

    // Check if elements are within viewport
    if (logoBounds && logoBounds.right > viewport.width) {
        issues.push('ERROR: Logo extends beyond viewport width');
    }

    if (goButtonBounds && goButtonBounds.right > viewport.width) {
        issues.push('ERROR: Go button extends beyond viewport width');
    }

    if (navMenuBounds && navMenuBounds.right > viewport.width) {
        issues.push('WARNING: Navigation menu extends beyond viewport width');
    }

    // Print element positions
    console.log('\nElement Bounding Boxes:');
    if (logoBounds) {
        console.log(`  Logo: x=${logoBounds.x}, y=${logoBounds.y}, width=${logoBounds.width}, height=${logoBounds.height}`);
        console.log(`        left=${logoBounds.left}, right=${logoBounds.right}, top=${logoBounds.top}, bottom=${logoBounds.bottom}`);
    } else {
        console.log('  Logo: NOT FOUND');
    }

    if (searchContainerBounds) {
        console.log(`  Search Container: x=${searchContainerBounds.x}, y=${searchContainerBounds.y}, width=${searchContainerBounds.width}, height=${searchContainerBounds.height}`);
        console.log(`                   left=${searchContainerBounds.left}, right=${searchContainerBounds.right}, top=${searchContainerBounds.top}, bottom=${searchContainerBounds.bottom}`);
    } else {
        console.log('  Search Container: NOT FOUND');
    }

    if (searchInputBounds) {
        console.log(`  Search Input: x=${searchInputBounds.x}, y=${searchInputBounds.y}, width=${searchInputBounds.width}, height=${searchInputBounds.height}`);
        console.log(`               left=${searchInputBounds.left}, right=${searchInputBounds.right}, top=${searchInputBounds.top}, bottom=${searchInputBounds.bottom}`);
    } else {
        console.log('  Search Input: NOT FOUND');
    }

    if (goButtonBounds) {
        console.log(`  Go Button: x=${goButtonBounds.x}, y=${goButtonBounds.y}, width=${goButtonBounds.width}, height=${goButtonBounds.height}`);
        console.log(`            left=${goButtonBounds.left}, right=${goButtonBounds.right}, top=${goButtonBounds.top}, bottom=${goButtonBounds.bottom}`);
    } else {
        console.log('  Go Button: NOT FOUND');
    }

    if (navMenuBounds) {
        console.log(`  Nav Menu: x=${navMenuBounds.x}, y=${navMenuBounds.y}, width=${navMenuBounds.width}, height=${navMenuBounds.height}`);
        console.log(`           left=${navMenuBounds.left}, right=${navMenuBounds.right}, top=${navMenuBounds.top}, bottom=${navMenuBounds.bottom}`);
    } else {
        console.log('  Nav Menu: NOT FOUND');
    }

    // Print issues
    if (issues.length > 0) {
        console.log('\n⚠ ISSUES DETECTED:');
        issues.forEach(issue => console.log(`  - ${issue}`));
    } else {
        console.log('\n✓ ALL CHECKS PASSED - No layout issues detected!');
    }

    await page.close();

    // Return test results
    return {
        viewport: {
            name: viewport.name,
            width: viewport.width,
            height: viewport.height
        },
        screenshot: screenshotPath,
        bounds: {
            logo: logoBounds,
            searchContainer: searchContainerBounds,
            searchInput: searchInputBounds,
            goButton: goButtonBounds,
            navMenu: navMenuBounds
        },
        metrics,
        overlaps,
        issues,
        passed: issues.length === 0 && overlaps.length === 0
    };
}

// Main test execution
async function runTests() {
    console.log('╔═══════════════════════════════════════════════════════════╗');
    console.log('║     VeriBits Mobile Navigation Layout Test Suite         ║');
    console.log('╚═══════════════════════════════════════════════════════════╝');
    console.log(`\nBase URL: ${BASE_URL}${PAGE_PATH}`);
    console.log(`Testing ${VIEWPORTS.length} different viewport sizes\n`);

    // Ensure screenshots directory exists
    await ensureDirectoryExists(SCREENSHOTS_DIR);

    // Launch browser
    console.log('Launching browser...');
    const browser = await puppeteer.launch({
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu'
        ]
    });

    console.log('✓ Browser launched\n');

    // Test each viewport
    const results = [];
    let totalTests = 0;
    let passedTests = 0;

    for (const viewport of VIEWPORTS) {
        try {
            const result = await testViewport(browser, viewport);
            results.push(result);
            totalTests++;
            if (result.passed) {
                passedTests++;
            }
        } catch (error) {
            console.error(`\n✗ Error testing ${viewport.name}:`, error.message);
            results.push({
                viewport,
                error: error.message,
                passed: false
            });
            totalTests++;
        }
    }

    // Close browser
    await browser.close();
    console.log('\n✓ Browser closed');

    // Generate summary report
    console.log('\n╔═══════════════════════════════════════════════════════════╗');
    console.log('║                     TEST SUMMARY                          ║');
    console.log('╚═══════════════════════════════════════════════════════════╝\n');

    console.log(`Total Tests: ${totalTests}`);
    console.log(`Passed: ${passedTests}`);
    console.log(`Failed: ${totalTests - passedTests}`);
    console.log(`Success Rate: ${((passedTests / totalTests) * 100).toFixed(1)}%\n`);

    // Detailed results
    results.forEach((result, index) => {
        const status = result.passed ? '✓ PASS' : '✗ FAIL';
        const viewport = result.viewport;
        console.log(`${status} - ${viewport.name} (${viewport.width}x${viewport.height})`);

        if (result.overlaps && result.overlaps.length > 0) {
            result.overlaps.forEach(overlap => {
                console.log(`       [${overlap.severity}] ${overlap.elements.join(' <-> ')}: ${overlap.area}px² overlap`);
            });
        }

        if (result.issues && result.issues.length > 0) {
            result.issues.forEach(issue => {
                console.log(`       ${issue}`);
            });
        }
    });

    // Save detailed JSON report
    const report = {
        timestamp: new Date().toISOString(),
        baseUrl: BASE_URL,
        pagePath: PAGE_PATH,
        summary: {
            totalTests,
            passedTests,
            failedTests: totalTests - passedTests,
            successRate: ((passedTests / totalTests) * 100).toFixed(1) + '%'
        },
        results
    };

    await fs.writeFile(REPORT_FILE, JSON.stringify(report, null, 2));
    console.log(`\n✓ Detailed report saved: ${REPORT_FILE}`);

    // Exit with appropriate code
    const exitCode = passedTests === totalTests ? 0 : 1;
    console.log(`\n${passedTests === totalTests ? '✓ ALL TESTS PASSED!' : '✗ SOME TESTS FAILED'}`);

    return exitCode;
}

// Run tests and handle errors
runTests()
    .then(exitCode => {
        process.exit(exitCode);
    })
    .catch(error => {
        console.error('\n✗ FATAL ERROR:', error);
        process.exit(1);
    });
