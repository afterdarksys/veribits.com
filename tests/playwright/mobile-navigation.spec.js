/**
 * VeriBits Mobile Navigation Layout Test - Playwright Version
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

const { test, expect } = require('@playwright/test');
const fs = require('fs').promises;
const path = require('path');

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

// Helper function to check if two elements overlap
function checkOverlap(box1, box2) {
    if (!box1 || !box2) return false;
    return !(
        box1.x + box1.width <= box2.x ||
        box2.x + box2.width <= box1.x ||
        box1.y + box1.height <= box2.y ||
        box2.y + box2.height <= box1.y
    );
}

// Helper function to calculate overlap area
function calculateOverlapArea(box1, box2) {
    if (!box1 || !box2 || !checkOverlap(box1, box2)) {
        return 0;
    }

    const xOverlap = Math.min(box1.x + box1.width, box2.x + box2.width) - Math.max(box1.x, box2.x);
    const yOverlap = Math.min(box1.y + box1.height, box2.y + box2.height) - Math.max(box1.y, box2.y);

    return xOverlap * yOverlap;
}

// Store results for final report
const testResults = [];

// Run tests for each viewport
for (const viewport of VIEWPORTS) {
    test(`Mobile Navigation - ${viewport.name} (${viewport.width}x${viewport.height})`, async ({ browser }) => {
        console.log(`\n${'='.repeat(60)}`);
        console.log(`Testing viewport: ${viewport.name} (${viewport.width}x${viewport.height})`);
        console.log('='.repeat(60));

        const context = await browser.newContext({
            viewport: { width: viewport.width, height: viewport.height },
            deviceScaleFactor: 1
        });

        const page = await context.newPage();

        // Navigate to page
        console.log(`Navigating to ${BASE_URL}${PAGE_PATH}...`);
        await page.goto(`${BASE_URL}${PAGE_PATH}`, {
            waitUntil: 'networkidle',
            timeout: 30000
        });

        // Wait for navigation to be visible
        await page.waitForSelector('.nav-container', { timeout: 5000 });

        // Take screenshot
        const screenshotPath = path.join(SCREENSHOTS_DIR, `${viewport.name.replace(/\s+/g, '-')}-${viewport.width}x${viewport.height}.png`);
        await page.screenshot({
            path: screenshotPath,
            fullPage: false
        });
        console.log(`✓ Screenshot saved: ${screenshotPath}`);

        // Get bounding boxes for critical elements
        console.log('\nGetting element bounding boxes...');

        const logoBounds = await page.locator('.nav-logo').boundingBox();
        const searchContainerBounds = await page.locator('.nav-search').boundingBox();
        const searchInputBounds = await page.locator('#tool-search').boundingBox();
        const goButtonBounds = await page.locator('#search-go').boundingBox();
        const navMenuBounds = await page.locator('.nav-menu').boundingBox();

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

        // Critical check: Logo and Go button
        if (logoBounds && goButtonBounds) {
            const overlap = checkOverlap(logoBounds, goButtonBounds);
            const overlapArea = calculateOverlapArea(logoBounds, goButtonBounds);

            if (overlap) {
                const msg = `Logo and Go button overlap detected! (${overlapArea.toFixed(0)}px²)`;
                issues.push(`CRITICAL: ${msg}`);
                overlaps.push({
                    elements: ['Logo', 'Go Button'],
                    area: overlapArea,
                    severity: 'CRITICAL'
                });
                console.log(`\n✗ FAIL: ${msg}`);
            } else {
                console.log('\n✓ PASS: No overlap between Logo and Go button');
            }
        }

        // Warning check: Logo and Search input
        if (logoBounds && searchInputBounds) {
            const overlap = checkOverlap(logoBounds, searchInputBounds);
            const overlapArea = calculateOverlapArea(logoBounds, searchInputBounds);

            if (overlap) {
                const msg = `Logo and search input overlap detected! (${overlapArea.toFixed(0)}px²)`;
                issues.push(`WARNING: ${msg}`);
                overlaps.push({
                    elements: ['Logo', 'Search Input'],
                    area: overlapArea,
                    severity: 'WARNING'
                });
                console.log(`✗ WARN: ${msg}`);
            } else {
                console.log('✓ PASS: No overlap between Logo and search input');
            }
        }

        // Info check: Search and Nav menu
        if (searchInputBounds && navMenuBounds) {
            const overlap = checkOverlap(searchInputBounds, navMenuBounds);
            const overlapArea = calculateOverlapArea(searchInputBounds, navMenuBounds);

            if (overlap) {
                const msg = `Search input and nav menu overlap detected (${overlapArea.toFixed(0)}px²)`;
                issues.push(`INFO: ${msg}`);
                overlaps.push({
                    elements: ['Search Input', 'Nav Menu'],
                    area: overlapArea,
                    severity: 'INFO'
                });
                console.log(`ℹ INFO: ${msg}`);
            } else {
                console.log('✓ PASS: No overlap between search input and nav menu');
            }
        }

        // Visibility checks
        expect(metrics.logoVisible, 'Logo should be visible').toBe(true);
        expect(metrics.searchVisible, 'Search container should be visible').toBe(true);
        expect(metrics.goButtonVisible, 'Go button should be visible').toBe(true);
        expect(metrics.navMenuVisible, 'Navigation menu should be visible').toBe(true);

        // Check if elements are within viewport
        if (logoBounds && logoBounds.x + logoBounds.width > viewport.width) {
            issues.push('ERROR: Logo extends beyond viewport width');
        }

        if (goButtonBounds && goButtonBounds.x + goButtonBounds.width > viewport.width) {
            issues.push('ERROR: Go button extends beyond viewport width');
        }

        if (navMenuBounds && navMenuBounds.x + navMenuBounds.width > viewport.width) {
            issues.push('WARNING: Navigation menu extends beyond viewport width');
        }

        // Print element positions
        console.log('\nElement Bounding Boxes:');
        if (logoBounds) {
            console.log(`  Logo: x=${Math.round(logoBounds.x)}, y=${Math.round(logoBounds.y)}, width=${Math.round(logoBounds.width)}, height=${Math.round(logoBounds.height)}`);
        } else {
            console.log('  Logo: NOT FOUND');
        }

        if (searchContainerBounds) {
            console.log(`  Search Container: x=${Math.round(searchContainerBounds.x)}, y=${Math.round(searchContainerBounds.y)}, width=${Math.round(searchContainerBounds.width)}, height=${Math.round(searchContainerBounds.height)}`);
        } else {
            console.log('  Search Container: NOT FOUND');
        }

        if (searchInputBounds) {
            console.log(`  Search Input: x=${Math.round(searchInputBounds.x)}, y=${Math.round(searchInputBounds.y)}, width=${Math.round(searchInputBounds.width)}, height=${Math.round(searchInputBounds.height)}`);
        } else {
            console.log('  Search Input: NOT FOUND');
        }

        if (goButtonBounds) {
            console.log(`  Go Button: x=${Math.round(goButtonBounds.x)}, y=${Math.round(goButtonBounds.y)}, width=${Math.round(goButtonBounds.width)}, height=${Math.round(goButtonBounds.height)}`);
        } else {
            console.log('  Go Button: NOT FOUND');
        }

        if (navMenuBounds) {
            console.log(`  Nav Menu: x=${Math.round(navMenuBounds.x)}, y=${Math.round(navMenuBounds.y)}, width=${Math.round(navMenuBounds.width)}, height=${Math.round(navMenuBounds.height)}`);
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

        // Store test result
        const result = {
            viewport: {
                name: viewport.name,
                width: viewport.width,
                height: viewport.height
            },
            screenshot: screenshotPath,
            bounds: {
                logo: logoBounds ? {
                    x: Math.round(logoBounds.x),
                    y: Math.round(logoBounds.y),
                    width: Math.round(logoBounds.width),
                    height: Math.round(logoBounds.height)
                } : null,
                searchContainer: searchContainerBounds ? {
                    x: Math.round(searchContainerBounds.x),
                    y: Math.round(searchContainerBounds.y),
                    width: Math.round(searchContainerBounds.width),
                    height: Math.round(searchContainerBounds.height)
                } : null,
                searchInput: searchInputBounds ? {
                    x: Math.round(searchInputBounds.x),
                    y: Math.round(searchInputBounds.y),
                    width: Math.round(searchInputBounds.width),
                    height: Math.round(searchInputBounds.height)
                } : null,
                goButton: goButtonBounds ? {
                    x: Math.round(goButtonBounds.x),
                    y: Math.round(goButtonBounds.y),
                    width: Math.round(goButtonBounds.width),
                    height: Math.round(goButtonBounds.height)
                } : null,
                navMenu: navMenuBounds ? {
                    x: Math.round(navMenuBounds.x),
                    y: Math.round(navMenuBounds.y),
                    width: Math.round(navMenuBounds.width),
                    height: Math.round(navMenuBounds.height)
                } : null
            },
            metrics,
            overlaps,
            issues,
            passed: issues.filter(i => i.startsWith('CRITICAL') || i.startsWith('ERROR')).length === 0
        };

        testResults.push(result);

        // Main assertion: No critical overlaps
        expect(overlaps.filter(o => o.severity === 'CRITICAL').length, 'No critical overlaps should exist').toBe(0);

        await context.close();
    });
}

// Generate report after all tests
test.afterAll(async () => {
    console.log('\n╔═══════════════════════════════════════════════════════════╗');
    console.log('║                     TEST SUMMARY                          ║');
    console.log('╚═══════════════════════════════════════════════════════════╝\n');

    const totalTests = testResults.length;
    const passedTests = testResults.filter(r => r.passed).length;

    console.log(`Total Tests: ${totalTests}`);
    console.log(`Passed: ${passedTests}`);
    console.log(`Failed: ${totalTests - passedTests}`);
    console.log(`Success Rate: ${((passedTests / totalTests) * 100).toFixed(1)}%\n`);

    // Detailed results
    testResults.forEach(result => {
        const status = result.passed ? '✓ PASS' : '✗ FAIL';
        const viewport = result.viewport;
        console.log(`${status} - ${viewport.name} (${viewport.width}x${viewport.height})`);

        if (result.overlaps && result.overlaps.length > 0) {
            result.overlaps.forEach(overlap => {
                console.log(`       [${overlap.severity}] ${overlap.elements.join(' <-> ')}: ${overlap.area.toFixed(0)}px² overlap`);
            });
        }

        if (result.issues && result.issues.length > 0) {
            result.issues.slice(0, 3).forEach(issue => {
                console.log(`       ${issue}`);
            });
            if (result.issues.length > 3) {
                console.log(`       ... and ${result.issues.length - 3} more issues`);
            }
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
        results: testResults
    };

    await fs.writeFile(REPORT_FILE, JSON.stringify(report, null, 2));
    console.log(`\n✓ Detailed report saved: ${REPORT_FILE}`);
    console.log(`\n${passedTests === totalTests ? '✓ ALL TESTS PASSED!' : '✗ SOME TESTS FAILED'}`);
});
