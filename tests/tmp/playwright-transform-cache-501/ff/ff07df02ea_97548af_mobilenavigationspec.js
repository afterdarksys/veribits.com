"use strict";

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

const {
  test,
  expect
} = require('@playwright/test');
const fs = require('fs').promises;
const path = require('path');
const BASE_URL = process.env.TEST_URL || 'http://localhost:8080';
const PAGE_PATH = '/home.php';
const SCREENSHOTS_DIR = path.join(__dirname, '..', 'screenshots');
const REPORT_FILE = path.join(__dirname, '..', 'mobile-navigation-test-report.json');

// Test viewports
const VIEWPORTS = [{
  name: 'Desktop',
  width: 1920,
  height: 1080
}, {
  name: 'Tablet',
  width: 768,
  height: 1024
}, {
  name: 'iPhone SE',
  width: 375,
  height: 667
}, {
  name: 'iPhone 12',
  width: 390,
  height: 844
}];

// Helper function to check if two elements overlap
function checkOverlap(box1, box2) {
  if (!box1 || !box2) return false;
  return !(box1.x + box1.width <= box2.x || box2.x + box2.width <= box1.x || box1.y + box1.height <= box2.y || box2.y + box2.height <= box1.y);
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
  test(`Mobile Navigation - ${viewport.name} (${viewport.width}x${viewport.height})`, async ({
    browser
  }) => {
    console.log(`\n${'='.repeat(60)}`);
    console.log(`Testing viewport: ${viewport.name} (${viewport.width}x${viewport.height})`);
    console.log('='.repeat(60));
    const context = await browser.newContext({
      viewport: {
        width: viewport.width,
        height: viewport.height
      },
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
    await page.waitForSelector('.nav-container', {
      timeout: 5000
    });

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
  console.log(`Success Rate: ${(passedTests / totalTests * 100).toFixed(1)}%\n`);

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
      successRate: (passedTests / totalTests * 100).toFixed(1) + '%'
    },
    results: testResults
  };
  await fs.writeFile(REPORT_FILE, JSON.stringify(report, null, 2));
  console.log(`\n✓ Detailed report saved: ${REPORT_FILE}`);
  console.log(`\n${passedTests === totalTests ? '✓ ALL TESTS PASSED!' : '✗ SOME TESTS FAILED'}`);
});
//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJuYW1lcyI6WyJ0ZXN0IiwiZXhwZWN0IiwicmVxdWlyZSIsImZzIiwicHJvbWlzZXMiLCJwYXRoIiwiQkFTRV9VUkwiLCJwcm9jZXNzIiwiZW52IiwiVEVTVF9VUkwiLCJQQUdFX1BBVEgiLCJTQ1JFRU5TSE9UU19ESVIiLCJqb2luIiwiX19kaXJuYW1lIiwiUkVQT1JUX0ZJTEUiLCJWSUVXUE9SVFMiLCJuYW1lIiwid2lkdGgiLCJoZWlnaHQiLCJjaGVja092ZXJsYXAiLCJib3gxIiwiYm94MiIsIngiLCJ5IiwiY2FsY3VsYXRlT3ZlcmxhcEFyZWEiLCJ4T3ZlcmxhcCIsIk1hdGgiLCJtaW4iLCJtYXgiLCJ5T3ZlcmxhcCIsInRlc3RSZXN1bHRzIiwidmlld3BvcnQiLCJicm93c2VyIiwiY29uc29sZSIsImxvZyIsInJlcGVhdCIsImNvbnRleHQiLCJuZXdDb250ZXh0IiwiZGV2aWNlU2NhbGVGYWN0b3IiLCJwYWdlIiwibmV3UGFnZSIsImdvdG8iLCJ3YWl0VW50aWwiLCJ0aW1lb3V0Iiwid2FpdEZvclNlbGVjdG9yIiwic2NyZWVuc2hvdFBhdGgiLCJyZXBsYWNlIiwic2NyZWVuc2hvdCIsImZ1bGxQYWdlIiwibG9nb0JvdW5kcyIsImxvY2F0b3IiLCJib3VuZGluZ0JveCIsInNlYXJjaENvbnRhaW5lckJvdW5kcyIsInNlYXJjaElucHV0Qm91bmRzIiwiZ29CdXR0b25Cb3VuZHMiLCJuYXZNZW51Qm91bmRzIiwibWV0cmljcyIsImV2YWx1YXRlIiwibG9nbyIsImRvY3VtZW50IiwicXVlcnlTZWxlY3RvciIsInNlYXJjaENvbnRhaW5lciIsImdvQnV0dG9uIiwibmF2TWVudSIsIm5hdkNvbnRhaW5lciIsImxvZ29WaXNpYmxlIiwid2luZG93IiwiZ2V0Q29tcHV0ZWRTdHlsZSIsImRpc3BsYXkiLCJzZWFyY2hWaXNpYmxlIiwiZ29CdXR0b25WaXNpYmxlIiwibmF2TWVudVZpc2libGUiLCJjb250YWluZXJGbGV4V3JhcCIsImZsZXhXcmFwIiwic2VhcmNoT3JkZXIiLCJvcmRlciIsInZpZXdwb3J0V2lkdGgiLCJpbm5lcldpZHRoIiwidmlld3BvcnRIZWlnaHQiLCJpbm5lckhlaWdodCIsImlzc3VlcyIsIm92ZXJsYXBzIiwib3ZlcmxhcCIsIm92ZXJsYXBBcmVhIiwibXNnIiwidG9GaXhlZCIsInB1c2giLCJlbGVtZW50cyIsImFyZWEiLCJzZXZlcml0eSIsInRvQmUiLCJyb3VuZCIsImxlbmd0aCIsImZvckVhY2giLCJpc3N1ZSIsInJlc3VsdCIsImJvdW5kcyIsInNlYXJjaElucHV0IiwicGFzc2VkIiwiZmlsdGVyIiwiaSIsInN0YXJ0c1dpdGgiLCJvIiwiY2xvc2UiLCJhZnRlckFsbCIsInRvdGFsVGVzdHMiLCJwYXNzZWRUZXN0cyIsInIiLCJzdGF0dXMiLCJzbGljZSIsInJlcG9ydCIsInRpbWVzdGFtcCIsIkRhdGUiLCJ0b0lTT1N0cmluZyIsImJhc2VVcmwiLCJwYWdlUGF0aCIsInN1bW1hcnkiLCJmYWlsZWRUZXN0cyIsInN1Y2Nlc3NSYXRlIiwicmVzdWx0cyIsIndyaXRlRmlsZSIsIkpTT04iLCJzdHJpbmdpZnkiXSwic291cmNlcyI6WyJtb2JpbGUtbmF2aWdhdGlvbi5zcGVjLmpzIl0sInNvdXJjZXNDb250ZW50IjpbIi8qKlxuICogVmVyaUJpdHMgTW9iaWxlIE5hdmlnYXRpb24gTGF5b3V0IFRlc3QgLSBQbGF5d3JpZ2h0IFZlcnNpb25cbiAqXG4gKiBUZXN0cyB0aGUgcmVzcG9uc2l2ZSBuYXZpZ2F0aW9uIGJhciBhdCBtdWx0aXBsZSB2aWV3cG9ydCBzaXplc1xuICogdG8gZW5zdXJlIG5vIG92ZXJsYXBwaW5nIGJldHdlZW4gbG9nbywgc2VhcmNoIGJveCwgYW5kIFwiR29cIiBidXR0b24uXG4gKlxuICogVGVzdCB2aWV3cG9ydHM6XG4gKiAtIERlc2t0b3A6IDE5MjB4MTA4MFxuICogLSBUYWJsZXQ6IDc2OHgxMDI0XG4gKiAtIE1vYmlsZTogMzc1eDY2NyAoaVBob25lIFNFKVxuICogLSBNb2JpbGU6IDM5MHg4NDQgKGlQaG9uZSAxMilcbiAqL1xuXG5jb25zdCB7IHRlc3QsIGV4cGVjdCB9ID0gcmVxdWlyZSgnQHBsYXl3cmlnaHQvdGVzdCcpO1xuY29uc3QgZnMgPSByZXF1aXJlKCdmcycpLnByb21pc2VzO1xuY29uc3QgcGF0aCA9IHJlcXVpcmUoJ3BhdGgnKTtcblxuY29uc3QgQkFTRV9VUkwgPSBwcm9jZXNzLmVudi5URVNUX1VSTCB8fCAnaHR0cDovL2xvY2FsaG9zdDo4MDgwJztcbmNvbnN0IFBBR0VfUEFUSCA9ICcvaG9tZS5waHAnO1xuY29uc3QgU0NSRUVOU0hPVFNfRElSID0gcGF0aC5qb2luKF9fZGlybmFtZSwgJy4uJywgJ3NjcmVlbnNob3RzJyk7XG5jb25zdCBSRVBPUlRfRklMRSA9IHBhdGguam9pbihfX2Rpcm5hbWUsICcuLicsICdtb2JpbGUtbmF2aWdhdGlvbi10ZXN0LXJlcG9ydC5qc29uJyk7XG5cbi8vIFRlc3Qgdmlld3BvcnRzXG5jb25zdCBWSUVXUE9SVFMgPSBbXG4gICAgeyBuYW1lOiAnRGVza3RvcCcsIHdpZHRoOiAxOTIwLCBoZWlnaHQ6IDEwODAgfSxcbiAgICB7IG5hbWU6ICdUYWJsZXQnLCB3aWR0aDogNzY4LCBoZWlnaHQ6IDEwMjQgfSxcbiAgICB7IG5hbWU6ICdpUGhvbmUgU0UnLCB3aWR0aDogMzc1LCBoZWlnaHQ6IDY2NyB9LFxuICAgIHsgbmFtZTogJ2lQaG9uZSAxMicsIHdpZHRoOiAzOTAsIGhlaWdodDogODQ0IH1cbl07XG5cbi8vIEhlbHBlciBmdW5jdGlvbiB0byBjaGVjayBpZiB0d28gZWxlbWVudHMgb3ZlcmxhcFxuZnVuY3Rpb24gY2hlY2tPdmVybGFwKGJveDEsIGJveDIpIHtcbiAgICBpZiAoIWJveDEgfHwgIWJveDIpIHJldHVybiBmYWxzZTtcbiAgICByZXR1cm4gIShcbiAgICAgICAgYm94MS54ICsgYm94MS53aWR0aCA8PSBib3gyLnggfHxcbiAgICAgICAgYm94Mi54ICsgYm94Mi53aWR0aCA8PSBib3gxLnggfHxcbiAgICAgICAgYm94MS55ICsgYm94MS5oZWlnaHQgPD0gYm94Mi55IHx8XG4gICAgICAgIGJveDIueSArIGJveDIuaGVpZ2h0IDw9IGJveDEueVxuICAgICk7XG59XG5cbi8vIEhlbHBlciBmdW5jdGlvbiB0byBjYWxjdWxhdGUgb3ZlcmxhcCBhcmVhXG5mdW5jdGlvbiBjYWxjdWxhdGVPdmVybGFwQXJlYShib3gxLCBib3gyKSB7XG4gICAgaWYgKCFib3gxIHx8ICFib3gyIHx8ICFjaGVja092ZXJsYXAoYm94MSwgYm94MikpIHtcbiAgICAgICAgcmV0dXJuIDA7XG4gICAgfVxuXG4gICAgY29uc3QgeE92ZXJsYXAgPSBNYXRoLm1pbihib3gxLnggKyBib3gxLndpZHRoLCBib3gyLnggKyBib3gyLndpZHRoKSAtIE1hdGgubWF4KGJveDEueCwgYm94Mi54KTtcbiAgICBjb25zdCB5T3ZlcmxhcCA9IE1hdGgubWluKGJveDEueSArIGJveDEuaGVpZ2h0LCBib3gyLnkgKyBib3gyLmhlaWdodCkgLSBNYXRoLm1heChib3gxLnksIGJveDIueSk7XG5cbiAgICByZXR1cm4geE92ZXJsYXAgKiB5T3ZlcmxhcDtcbn1cblxuLy8gU3RvcmUgcmVzdWx0cyBmb3IgZmluYWwgcmVwb3J0XG5jb25zdCB0ZXN0UmVzdWx0cyA9IFtdO1xuXG4vLyBSdW4gdGVzdHMgZm9yIGVhY2ggdmlld3BvcnRcbmZvciAoY29uc3Qgdmlld3BvcnQgb2YgVklFV1BPUlRTKSB7XG4gICAgdGVzdChgTW9iaWxlIE5hdmlnYXRpb24gLSAke3ZpZXdwb3J0Lm5hbWV9ICgke3ZpZXdwb3J0LndpZHRofXgke3ZpZXdwb3J0LmhlaWdodH0pYCwgYXN5bmMgKHsgYnJvd3NlciB9KSA9PiB7XG4gICAgICAgIGNvbnNvbGUubG9nKGBcXG4keyc9Jy5yZXBlYXQoNjApfWApO1xuICAgICAgICBjb25zb2xlLmxvZyhgVGVzdGluZyB2aWV3cG9ydDogJHt2aWV3cG9ydC5uYW1lfSAoJHt2aWV3cG9ydC53aWR0aH14JHt2aWV3cG9ydC5oZWlnaHR9KWApO1xuICAgICAgICBjb25zb2xlLmxvZygnPScucmVwZWF0KDYwKSk7XG5cbiAgICAgICAgY29uc3QgY29udGV4dCA9IGF3YWl0IGJyb3dzZXIubmV3Q29udGV4dCh7XG4gICAgICAgICAgICB2aWV3cG9ydDogeyB3aWR0aDogdmlld3BvcnQud2lkdGgsIGhlaWdodDogdmlld3BvcnQuaGVpZ2h0IH0sXG4gICAgICAgICAgICBkZXZpY2VTY2FsZUZhY3RvcjogMVxuICAgICAgICB9KTtcblxuICAgICAgICBjb25zdCBwYWdlID0gYXdhaXQgY29udGV4dC5uZXdQYWdlKCk7XG5cbiAgICAgICAgLy8gTmF2aWdhdGUgdG8gcGFnZVxuICAgICAgICBjb25zb2xlLmxvZyhgTmF2aWdhdGluZyB0byAke0JBU0VfVVJMfSR7UEFHRV9QQVRIfS4uLmApO1xuICAgICAgICBhd2FpdCBwYWdlLmdvdG8oYCR7QkFTRV9VUkx9JHtQQUdFX1BBVEh9YCwge1xuICAgICAgICAgICAgd2FpdFVudGlsOiAnbmV0d29ya2lkbGUnLFxuICAgICAgICAgICAgdGltZW91dDogMzAwMDBcbiAgICAgICAgfSk7XG5cbiAgICAgICAgLy8gV2FpdCBmb3IgbmF2aWdhdGlvbiB0byBiZSB2aXNpYmxlXG4gICAgICAgIGF3YWl0IHBhZ2Uud2FpdEZvclNlbGVjdG9yKCcubmF2LWNvbnRhaW5lcicsIHsgdGltZW91dDogNTAwMCB9KTtcblxuICAgICAgICAvLyBUYWtlIHNjcmVlbnNob3RcbiAgICAgICAgY29uc3Qgc2NyZWVuc2hvdFBhdGggPSBwYXRoLmpvaW4oU0NSRUVOU0hPVFNfRElSLCBgJHt2aWV3cG9ydC5uYW1lLnJlcGxhY2UoL1xccysvZywgJy0nKX0tJHt2aWV3cG9ydC53aWR0aH14JHt2aWV3cG9ydC5oZWlnaHR9LnBuZ2ApO1xuICAgICAgICBhd2FpdCBwYWdlLnNjcmVlbnNob3Qoe1xuICAgICAgICAgICAgcGF0aDogc2NyZWVuc2hvdFBhdGgsXG4gICAgICAgICAgICBmdWxsUGFnZTogZmFsc2VcbiAgICAgICAgfSk7XG4gICAgICAgIGNvbnNvbGUubG9nKGDinJMgU2NyZWVuc2hvdCBzYXZlZDogJHtzY3JlZW5zaG90UGF0aH1gKTtcblxuICAgICAgICAvLyBHZXQgYm91bmRpbmcgYm94ZXMgZm9yIGNyaXRpY2FsIGVsZW1lbnRzXG4gICAgICAgIGNvbnNvbGUubG9nKCdcXG5HZXR0aW5nIGVsZW1lbnQgYm91bmRpbmcgYm94ZXMuLi4nKTtcblxuICAgICAgICBjb25zdCBsb2dvQm91bmRzID0gYXdhaXQgcGFnZS5sb2NhdG9yKCcubmF2LWxvZ28nKS5ib3VuZGluZ0JveCgpO1xuICAgICAgICBjb25zdCBzZWFyY2hDb250YWluZXJCb3VuZHMgPSBhd2FpdCBwYWdlLmxvY2F0b3IoJy5uYXYtc2VhcmNoJykuYm91bmRpbmdCb3goKTtcbiAgICAgICAgY29uc3Qgc2VhcmNoSW5wdXRCb3VuZHMgPSBhd2FpdCBwYWdlLmxvY2F0b3IoJyN0b29sLXNlYXJjaCcpLmJvdW5kaW5nQm94KCk7XG4gICAgICAgIGNvbnN0IGdvQnV0dG9uQm91bmRzID0gYXdhaXQgcGFnZS5sb2NhdG9yKCcjc2VhcmNoLWdvJykuYm91bmRpbmdCb3goKTtcbiAgICAgICAgY29uc3QgbmF2TWVudUJvdW5kcyA9IGF3YWl0IHBhZ2UubG9jYXRvcignLm5hdi1tZW51JykuYm91bmRpbmdCb3goKTtcblxuICAgICAgICAvLyBHZXQgYWRkaXRpb25hbCBtZXRyaWNzXG4gICAgICAgIGNvbnN0IG1ldHJpY3MgPSBhd2FpdCBwYWdlLmV2YWx1YXRlKCgpID0+IHtcbiAgICAgICAgICAgIGNvbnN0IGxvZ28gPSBkb2N1bWVudC5xdWVyeVNlbGVjdG9yKCcubmF2LWxvZ28nKTtcbiAgICAgICAgICAgIGNvbnN0IHNlYXJjaENvbnRhaW5lciA9IGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3IoJy5uYXYtc2VhcmNoJyk7XG4gICAgICAgICAgICBjb25zdCBnb0J1dHRvbiA9IGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3IoJyNzZWFyY2gtZ28nKTtcbiAgICAgICAgICAgIGNvbnN0IG5hdk1lbnUgPSBkb2N1bWVudC5xdWVyeVNlbGVjdG9yKCcubmF2LW1lbnUnKTtcbiAgICAgICAgICAgIGNvbnN0IG5hdkNvbnRhaW5lciA9IGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3IoJy5uYXYtY29udGFpbmVyJyk7XG5cbiAgICAgICAgICAgIHJldHVybiB7XG4gICAgICAgICAgICAgICAgbG9nb1Zpc2libGU6IGxvZ28gPyB3aW5kb3cuZ2V0Q29tcHV0ZWRTdHlsZShsb2dvKS5kaXNwbGF5ICE9PSAnbm9uZScgOiBmYWxzZSxcbiAgICAgICAgICAgICAgICBzZWFyY2hWaXNpYmxlOiBzZWFyY2hDb250YWluZXIgPyB3aW5kb3cuZ2V0Q29tcHV0ZWRTdHlsZShzZWFyY2hDb250YWluZXIpLmRpc3BsYXkgIT09ICdub25lJyA6IGZhbHNlLFxuICAgICAgICAgICAgICAgIGdvQnV0dG9uVmlzaWJsZTogZ29CdXR0b24gPyB3aW5kb3cuZ2V0Q29tcHV0ZWRTdHlsZShnb0J1dHRvbikuZGlzcGxheSAhPT0gJ25vbmUnIDogZmFsc2UsXG4gICAgICAgICAgICAgICAgbmF2TWVudVZpc2libGU6IG5hdk1lbnUgPyB3aW5kb3cuZ2V0Q29tcHV0ZWRTdHlsZShuYXZNZW51KS5kaXNwbGF5ICE9PSAnbm9uZScgOiBmYWxzZSxcbiAgICAgICAgICAgICAgICBjb250YWluZXJGbGV4V3JhcDogbmF2Q29udGFpbmVyID8gd2luZG93LmdldENvbXB1dGVkU3R5bGUobmF2Q29udGFpbmVyKS5mbGV4V3JhcCA6ICd1bmtub3duJyxcbiAgICAgICAgICAgICAgICBzZWFyY2hPcmRlcjogc2VhcmNoQ29udGFpbmVyID8gd2luZG93LmdldENvbXB1dGVkU3R5bGUoc2VhcmNoQ29udGFpbmVyKS5vcmRlciA6ICd1bmtub3duJyxcbiAgICAgICAgICAgICAgICB2aWV3cG9ydFdpZHRoOiB3aW5kb3cuaW5uZXJXaWR0aCxcbiAgICAgICAgICAgICAgICB2aWV3cG9ydEhlaWdodDogd2luZG93LmlubmVySGVpZ2h0XG4gICAgICAgICAgICB9O1xuICAgICAgICB9KTtcblxuICAgICAgICBjb25zb2xlLmxvZygnXFxuRWxlbWVudCBWaXNpYmlsaXR5OicpO1xuICAgICAgICBjb25zb2xlLmxvZyhgICBMb2dvOiAke21ldHJpY3MubG9nb1Zpc2libGUgPyAn4pyTJyA6ICfinJcnfWApO1xuICAgICAgICBjb25zb2xlLmxvZyhgICBTZWFyY2ggQ29udGFpbmVyOiAke21ldHJpY3Muc2VhcmNoVmlzaWJsZSA/ICfinJMnIDogJ+Kclyd9YCk7XG4gICAgICAgIGNvbnNvbGUubG9nKGAgIEdvIEJ1dHRvbjogJHttZXRyaWNzLmdvQnV0dG9uVmlzaWJsZSA/ICfinJMnIDogJ+Kclyd9YCk7XG4gICAgICAgIGNvbnNvbGUubG9nKGAgIE5hdiBNZW51OiAke21ldHJpY3MubmF2TWVudVZpc2libGUgPyAn4pyTJyA6ICfinJcnfWApO1xuICAgICAgICBjb25zb2xlLmxvZyhgICBDb250YWluZXIgRmxleCBXcmFwOiAke21ldHJpY3MuY29udGFpbmVyRmxleFdyYXB9YCk7XG4gICAgICAgIGNvbnNvbGUubG9nKGAgIFNlYXJjaCBPcmRlcjogJHttZXRyaWNzLnNlYXJjaE9yZGVyfWApO1xuXG4gICAgICAgIC8vIENoZWNrIGZvciBvdmVybGFwc1xuICAgICAgICBjb25zdCBpc3N1ZXMgPSBbXTtcbiAgICAgICAgY29uc3Qgb3ZlcmxhcHMgPSBbXTtcblxuICAgICAgICAvLyBDcml0aWNhbCBjaGVjazogTG9nbyBhbmQgR28gYnV0dG9uXG4gICAgICAgIGlmIChsb2dvQm91bmRzICYmIGdvQnV0dG9uQm91bmRzKSB7XG4gICAgICAgICAgICBjb25zdCBvdmVybGFwID0gY2hlY2tPdmVybGFwKGxvZ29Cb3VuZHMsIGdvQnV0dG9uQm91bmRzKTtcbiAgICAgICAgICAgIGNvbnN0IG92ZXJsYXBBcmVhID0gY2FsY3VsYXRlT3ZlcmxhcEFyZWEobG9nb0JvdW5kcywgZ29CdXR0b25Cb3VuZHMpO1xuXG4gICAgICAgICAgICBpZiAob3ZlcmxhcCkge1xuICAgICAgICAgICAgICAgIGNvbnN0IG1zZyA9IGBMb2dvIGFuZCBHbyBidXR0b24gb3ZlcmxhcCBkZXRlY3RlZCEgKCR7b3ZlcmxhcEFyZWEudG9GaXhlZCgwKX1weMKyKWA7XG4gICAgICAgICAgICAgICAgaXNzdWVzLnB1c2goYENSSVRJQ0FMOiAke21zZ31gKTtcbiAgICAgICAgICAgICAgICBvdmVybGFwcy5wdXNoKHtcbiAgICAgICAgICAgICAgICAgICAgZWxlbWVudHM6IFsnTG9nbycsICdHbyBCdXR0b24nXSxcbiAgICAgICAgICAgICAgICAgICAgYXJlYTogb3ZlcmxhcEFyZWEsXG4gICAgICAgICAgICAgICAgICAgIHNldmVyaXR5OiAnQ1JJVElDQUwnXG4gICAgICAgICAgICAgICAgfSk7XG4gICAgICAgICAgICAgICAgY29uc29sZS5sb2coYFxcbuKclyBGQUlMOiAke21zZ31gKTtcbiAgICAgICAgICAgIH0gZWxzZSB7XG4gICAgICAgICAgICAgICAgY29uc29sZS5sb2coJ1xcbuKckyBQQVNTOiBObyBvdmVybGFwIGJldHdlZW4gTG9nbyBhbmQgR28gYnV0dG9uJyk7XG4gICAgICAgICAgICB9XG4gICAgICAgIH1cblxuICAgICAgICAvLyBXYXJuaW5nIGNoZWNrOiBMb2dvIGFuZCBTZWFyY2ggaW5wdXRcbiAgICAgICAgaWYgKGxvZ29Cb3VuZHMgJiYgc2VhcmNoSW5wdXRCb3VuZHMpIHtcbiAgICAgICAgICAgIGNvbnN0IG92ZXJsYXAgPSBjaGVja092ZXJsYXAobG9nb0JvdW5kcywgc2VhcmNoSW5wdXRCb3VuZHMpO1xuICAgICAgICAgICAgY29uc3Qgb3ZlcmxhcEFyZWEgPSBjYWxjdWxhdGVPdmVybGFwQXJlYShsb2dvQm91bmRzLCBzZWFyY2hJbnB1dEJvdW5kcyk7XG5cbiAgICAgICAgICAgIGlmIChvdmVybGFwKSB7XG4gICAgICAgICAgICAgICAgY29uc3QgbXNnID0gYExvZ28gYW5kIHNlYXJjaCBpbnB1dCBvdmVybGFwIGRldGVjdGVkISAoJHtvdmVybGFwQXJlYS50b0ZpeGVkKDApfXB4wrIpYDtcbiAgICAgICAgICAgICAgICBpc3N1ZXMucHVzaChgV0FSTklORzogJHttc2d9YCk7XG4gICAgICAgICAgICAgICAgb3ZlcmxhcHMucHVzaCh7XG4gICAgICAgICAgICAgICAgICAgIGVsZW1lbnRzOiBbJ0xvZ28nLCAnU2VhcmNoIElucHV0J10sXG4gICAgICAgICAgICAgICAgICAgIGFyZWE6IG92ZXJsYXBBcmVhLFxuICAgICAgICAgICAgICAgICAgICBzZXZlcml0eTogJ1dBUk5JTkcnXG4gICAgICAgICAgICAgICAgfSk7XG4gICAgICAgICAgICAgICAgY29uc29sZS5sb2coYOKclyBXQVJOOiAke21zZ31gKTtcbiAgICAgICAgICAgIH0gZWxzZSB7XG4gICAgICAgICAgICAgICAgY29uc29sZS5sb2coJ+KckyBQQVNTOiBObyBvdmVybGFwIGJldHdlZW4gTG9nbyBhbmQgc2VhcmNoIGlucHV0Jyk7XG4gICAgICAgICAgICB9XG4gICAgICAgIH1cblxuICAgICAgICAvLyBJbmZvIGNoZWNrOiBTZWFyY2ggYW5kIE5hdiBtZW51XG4gICAgICAgIGlmIChzZWFyY2hJbnB1dEJvdW5kcyAmJiBuYXZNZW51Qm91bmRzKSB7XG4gICAgICAgICAgICBjb25zdCBvdmVybGFwID0gY2hlY2tPdmVybGFwKHNlYXJjaElucHV0Qm91bmRzLCBuYXZNZW51Qm91bmRzKTtcbiAgICAgICAgICAgIGNvbnN0IG92ZXJsYXBBcmVhID0gY2FsY3VsYXRlT3ZlcmxhcEFyZWEoc2VhcmNoSW5wdXRCb3VuZHMsIG5hdk1lbnVCb3VuZHMpO1xuXG4gICAgICAgICAgICBpZiAob3ZlcmxhcCkge1xuICAgICAgICAgICAgICAgIGNvbnN0IG1zZyA9IGBTZWFyY2ggaW5wdXQgYW5kIG5hdiBtZW51IG92ZXJsYXAgZGV0ZWN0ZWQgKCR7b3ZlcmxhcEFyZWEudG9GaXhlZCgwKX1weMKyKWA7XG4gICAgICAgICAgICAgICAgaXNzdWVzLnB1c2goYElORk86ICR7bXNnfWApO1xuICAgICAgICAgICAgICAgIG92ZXJsYXBzLnB1c2goe1xuICAgICAgICAgICAgICAgICAgICBlbGVtZW50czogWydTZWFyY2ggSW5wdXQnLCAnTmF2IE1lbnUnXSxcbiAgICAgICAgICAgICAgICAgICAgYXJlYTogb3ZlcmxhcEFyZWEsXG4gICAgICAgICAgICAgICAgICAgIHNldmVyaXR5OiAnSU5GTydcbiAgICAgICAgICAgICAgICB9KTtcbiAgICAgICAgICAgICAgICBjb25zb2xlLmxvZyhg4oS5IElORk86ICR7bXNnfWApO1xuICAgICAgICAgICAgfSBlbHNlIHtcbiAgICAgICAgICAgICAgICBjb25zb2xlLmxvZygn4pyTIFBBU1M6IE5vIG92ZXJsYXAgYmV0d2VlbiBzZWFyY2ggaW5wdXQgYW5kIG5hdiBtZW51Jyk7XG4gICAgICAgICAgICB9XG4gICAgICAgIH1cblxuICAgICAgICAvLyBWaXNpYmlsaXR5IGNoZWNrc1xuICAgICAgICBleHBlY3QobWV0cmljcy5sb2dvVmlzaWJsZSwgJ0xvZ28gc2hvdWxkIGJlIHZpc2libGUnKS50b0JlKHRydWUpO1xuICAgICAgICBleHBlY3QobWV0cmljcy5zZWFyY2hWaXNpYmxlLCAnU2VhcmNoIGNvbnRhaW5lciBzaG91bGQgYmUgdmlzaWJsZScpLnRvQmUodHJ1ZSk7XG4gICAgICAgIGV4cGVjdChtZXRyaWNzLmdvQnV0dG9uVmlzaWJsZSwgJ0dvIGJ1dHRvbiBzaG91bGQgYmUgdmlzaWJsZScpLnRvQmUodHJ1ZSk7XG4gICAgICAgIGV4cGVjdChtZXRyaWNzLm5hdk1lbnVWaXNpYmxlLCAnTmF2aWdhdGlvbiBtZW51IHNob3VsZCBiZSB2aXNpYmxlJykudG9CZSh0cnVlKTtcblxuICAgICAgICAvLyBDaGVjayBpZiBlbGVtZW50cyBhcmUgd2l0aGluIHZpZXdwb3J0XG4gICAgICAgIGlmIChsb2dvQm91bmRzICYmIGxvZ29Cb3VuZHMueCArIGxvZ29Cb3VuZHMud2lkdGggPiB2aWV3cG9ydC53aWR0aCkge1xuICAgICAgICAgICAgaXNzdWVzLnB1c2goJ0VSUk9SOiBMb2dvIGV4dGVuZHMgYmV5b25kIHZpZXdwb3J0IHdpZHRoJyk7XG4gICAgICAgIH1cblxuICAgICAgICBpZiAoZ29CdXR0b25Cb3VuZHMgJiYgZ29CdXR0b25Cb3VuZHMueCArIGdvQnV0dG9uQm91bmRzLndpZHRoID4gdmlld3BvcnQud2lkdGgpIHtcbiAgICAgICAgICAgIGlzc3Vlcy5wdXNoKCdFUlJPUjogR28gYnV0dG9uIGV4dGVuZHMgYmV5b25kIHZpZXdwb3J0IHdpZHRoJyk7XG4gICAgICAgIH1cblxuICAgICAgICBpZiAobmF2TWVudUJvdW5kcyAmJiBuYXZNZW51Qm91bmRzLnggKyBuYXZNZW51Qm91bmRzLndpZHRoID4gdmlld3BvcnQud2lkdGgpIHtcbiAgICAgICAgICAgIGlzc3Vlcy5wdXNoKCdXQVJOSU5HOiBOYXZpZ2F0aW9uIG1lbnUgZXh0ZW5kcyBiZXlvbmQgdmlld3BvcnQgd2lkdGgnKTtcbiAgICAgICAgfVxuXG4gICAgICAgIC8vIFByaW50IGVsZW1lbnQgcG9zaXRpb25zXG4gICAgICAgIGNvbnNvbGUubG9nKCdcXG5FbGVtZW50IEJvdW5kaW5nIEJveGVzOicpO1xuICAgICAgICBpZiAobG9nb0JvdW5kcykge1xuICAgICAgICAgICAgY29uc29sZS5sb2coYCAgTG9nbzogeD0ke01hdGgucm91bmQobG9nb0JvdW5kcy54KX0sIHk9JHtNYXRoLnJvdW5kKGxvZ29Cb3VuZHMueSl9LCB3aWR0aD0ke01hdGgucm91bmQobG9nb0JvdW5kcy53aWR0aCl9LCBoZWlnaHQ9JHtNYXRoLnJvdW5kKGxvZ29Cb3VuZHMuaGVpZ2h0KX1gKTtcbiAgICAgICAgfSBlbHNlIHtcbiAgICAgICAgICAgIGNvbnNvbGUubG9nKCcgIExvZ286IE5PVCBGT1VORCcpO1xuICAgICAgICB9XG5cbiAgICAgICAgaWYgKHNlYXJjaENvbnRhaW5lckJvdW5kcykge1xuICAgICAgICAgICAgY29uc29sZS5sb2coYCAgU2VhcmNoIENvbnRhaW5lcjogeD0ke01hdGgucm91bmQoc2VhcmNoQ29udGFpbmVyQm91bmRzLngpfSwgeT0ke01hdGgucm91bmQoc2VhcmNoQ29udGFpbmVyQm91bmRzLnkpfSwgd2lkdGg9JHtNYXRoLnJvdW5kKHNlYXJjaENvbnRhaW5lckJvdW5kcy53aWR0aCl9LCBoZWlnaHQ9JHtNYXRoLnJvdW5kKHNlYXJjaENvbnRhaW5lckJvdW5kcy5oZWlnaHQpfWApO1xuICAgICAgICB9IGVsc2Uge1xuICAgICAgICAgICAgY29uc29sZS5sb2coJyAgU2VhcmNoIENvbnRhaW5lcjogTk9UIEZPVU5EJyk7XG4gICAgICAgIH1cblxuICAgICAgICBpZiAoc2VhcmNoSW5wdXRCb3VuZHMpIHtcbiAgICAgICAgICAgIGNvbnNvbGUubG9nKGAgIFNlYXJjaCBJbnB1dDogeD0ke01hdGgucm91bmQoc2VhcmNoSW5wdXRCb3VuZHMueCl9LCB5PSR7TWF0aC5yb3VuZChzZWFyY2hJbnB1dEJvdW5kcy55KX0sIHdpZHRoPSR7TWF0aC5yb3VuZChzZWFyY2hJbnB1dEJvdW5kcy53aWR0aCl9LCBoZWlnaHQ9JHtNYXRoLnJvdW5kKHNlYXJjaElucHV0Qm91bmRzLmhlaWdodCl9YCk7XG4gICAgICAgIH0gZWxzZSB7XG4gICAgICAgICAgICBjb25zb2xlLmxvZygnICBTZWFyY2ggSW5wdXQ6IE5PVCBGT1VORCcpO1xuICAgICAgICB9XG5cbiAgICAgICAgaWYgKGdvQnV0dG9uQm91bmRzKSB7XG4gICAgICAgICAgICBjb25zb2xlLmxvZyhgICBHbyBCdXR0b246IHg9JHtNYXRoLnJvdW5kKGdvQnV0dG9uQm91bmRzLngpfSwgeT0ke01hdGgucm91bmQoZ29CdXR0b25Cb3VuZHMueSl9LCB3aWR0aD0ke01hdGgucm91bmQoZ29CdXR0b25Cb3VuZHMud2lkdGgpfSwgaGVpZ2h0PSR7TWF0aC5yb3VuZChnb0J1dHRvbkJvdW5kcy5oZWlnaHQpfWApO1xuICAgICAgICB9IGVsc2Uge1xuICAgICAgICAgICAgY29uc29sZS5sb2coJyAgR28gQnV0dG9uOiBOT1QgRk9VTkQnKTtcbiAgICAgICAgfVxuXG4gICAgICAgIGlmIChuYXZNZW51Qm91bmRzKSB7XG4gICAgICAgICAgICBjb25zb2xlLmxvZyhgICBOYXYgTWVudTogeD0ke01hdGgucm91bmQobmF2TWVudUJvdW5kcy54KX0sIHk9JHtNYXRoLnJvdW5kKG5hdk1lbnVCb3VuZHMueSl9LCB3aWR0aD0ke01hdGgucm91bmQobmF2TWVudUJvdW5kcy53aWR0aCl9LCBoZWlnaHQ9JHtNYXRoLnJvdW5kKG5hdk1lbnVCb3VuZHMuaGVpZ2h0KX1gKTtcbiAgICAgICAgfSBlbHNlIHtcbiAgICAgICAgICAgIGNvbnNvbGUubG9nKCcgIE5hdiBNZW51OiBOT1QgRk9VTkQnKTtcbiAgICAgICAgfVxuXG4gICAgICAgIC8vIFByaW50IGlzc3Vlc1xuICAgICAgICBpZiAoaXNzdWVzLmxlbmd0aCA+IDApIHtcbiAgICAgICAgICAgIGNvbnNvbGUubG9nKCdcXG7imqAgSVNTVUVTIERFVEVDVEVEOicpO1xuICAgICAgICAgICAgaXNzdWVzLmZvckVhY2goaXNzdWUgPT4gY29uc29sZS5sb2coYCAgLSAke2lzc3VlfWApKTtcbiAgICAgICAgfSBlbHNlIHtcbiAgICAgICAgICAgIGNvbnNvbGUubG9nKCdcXG7inJMgQUxMIENIRUNLUyBQQVNTRUQgLSBObyBsYXlvdXQgaXNzdWVzIGRldGVjdGVkIScpO1xuICAgICAgICB9XG5cbiAgICAgICAgLy8gU3RvcmUgdGVzdCByZXN1bHRcbiAgICAgICAgY29uc3QgcmVzdWx0ID0ge1xuICAgICAgICAgICAgdmlld3BvcnQ6IHtcbiAgICAgICAgICAgICAgICBuYW1lOiB2aWV3cG9ydC5uYW1lLFxuICAgICAgICAgICAgICAgIHdpZHRoOiB2aWV3cG9ydC53aWR0aCxcbiAgICAgICAgICAgICAgICBoZWlnaHQ6IHZpZXdwb3J0LmhlaWdodFxuICAgICAgICAgICAgfSxcbiAgICAgICAgICAgIHNjcmVlbnNob3Q6IHNjcmVlbnNob3RQYXRoLFxuICAgICAgICAgICAgYm91bmRzOiB7XG4gICAgICAgICAgICAgICAgbG9nbzogbG9nb0JvdW5kcyA/IHtcbiAgICAgICAgICAgICAgICAgICAgeDogTWF0aC5yb3VuZChsb2dvQm91bmRzLngpLFxuICAgICAgICAgICAgICAgICAgICB5OiBNYXRoLnJvdW5kKGxvZ29Cb3VuZHMueSksXG4gICAgICAgICAgICAgICAgICAgIHdpZHRoOiBNYXRoLnJvdW5kKGxvZ29Cb3VuZHMud2lkdGgpLFxuICAgICAgICAgICAgICAgICAgICBoZWlnaHQ6IE1hdGgucm91bmQobG9nb0JvdW5kcy5oZWlnaHQpXG4gICAgICAgICAgICAgICAgfSA6IG51bGwsXG4gICAgICAgICAgICAgICAgc2VhcmNoQ29udGFpbmVyOiBzZWFyY2hDb250YWluZXJCb3VuZHMgPyB7XG4gICAgICAgICAgICAgICAgICAgIHg6IE1hdGgucm91bmQoc2VhcmNoQ29udGFpbmVyQm91bmRzLngpLFxuICAgICAgICAgICAgICAgICAgICB5OiBNYXRoLnJvdW5kKHNlYXJjaENvbnRhaW5lckJvdW5kcy55KSxcbiAgICAgICAgICAgICAgICAgICAgd2lkdGg6IE1hdGgucm91bmQoc2VhcmNoQ29udGFpbmVyQm91bmRzLndpZHRoKSxcbiAgICAgICAgICAgICAgICAgICAgaGVpZ2h0OiBNYXRoLnJvdW5kKHNlYXJjaENvbnRhaW5lckJvdW5kcy5oZWlnaHQpXG4gICAgICAgICAgICAgICAgfSA6IG51bGwsXG4gICAgICAgICAgICAgICAgc2VhcmNoSW5wdXQ6IHNlYXJjaElucHV0Qm91bmRzID8ge1xuICAgICAgICAgICAgICAgICAgICB4OiBNYXRoLnJvdW5kKHNlYXJjaElucHV0Qm91bmRzLngpLFxuICAgICAgICAgICAgICAgICAgICB5OiBNYXRoLnJvdW5kKHNlYXJjaElucHV0Qm91bmRzLnkpLFxuICAgICAgICAgICAgICAgICAgICB3aWR0aDogTWF0aC5yb3VuZChzZWFyY2hJbnB1dEJvdW5kcy53aWR0aCksXG4gICAgICAgICAgICAgICAgICAgIGhlaWdodDogTWF0aC5yb3VuZChzZWFyY2hJbnB1dEJvdW5kcy5oZWlnaHQpXG4gICAgICAgICAgICAgICAgfSA6IG51bGwsXG4gICAgICAgICAgICAgICAgZ29CdXR0b246IGdvQnV0dG9uQm91bmRzID8ge1xuICAgICAgICAgICAgICAgICAgICB4OiBNYXRoLnJvdW5kKGdvQnV0dG9uQm91bmRzLngpLFxuICAgICAgICAgICAgICAgICAgICB5OiBNYXRoLnJvdW5kKGdvQnV0dG9uQm91bmRzLnkpLFxuICAgICAgICAgICAgICAgICAgICB3aWR0aDogTWF0aC5yb3VuZChnb0J1dHRvbkJvdW5kcy53aWR0aCksXG4gICAgICAgICAgICAgICAgICAgIGhlaWdodDogTWF0aC5yb3VuZChnb0J1dHRvbkJvdW5kcy5oZWlnaHQpXG4gICAgICAgICAgICAgICAgfSA6IG51bGwsXG4gICAgICAgICAgICAgICAgbmF2TWVudTogbmF2TWVudUJvdW5kcyA/IHtcbiAgICAgICAgICAgICAgICAgICAgeDogTWF0aC5yb3VuZChuYXZNZW51Qm91bmRzLngpLFxuICAgICAgICAgICAgICAgICAgICB5OiBNYXRoLnJvdW5kKG5hdk1lbnVCb3VuZHMueSksXG4gICAgICAgICAgICAgICAgICAgIHdpZHRoOiBNYXRoLnJvdW5kKG5hdk1lbnVCb3VuZHMud2lkdGgpLFxuICAgICAgICAgICAgICAgICAgICBoZWlnaHQ6IE1hdGgucm91bmQobmF2TWVudUJvdW5kcy5oZWlnaHQpXG4gICAgICAgICAgICAgICAgfSA6IG51bGxcbiAgICAgICAgICAgIH0sXG4gICAgICAgICAgICBtZXRyaWNzLFxuICAgICAgICAgICAgb3ZlcmxhcHMsXG4gICAgICAgICAgICBpc3N1ZXMsXG4gICAgICAgICAgICBwYXNzZWQ6IGlzc3Vlcy5maWx0ZXIoaSA9PiBpLnN0YXJ0c1dpdGgoJ0NSSVRJQ0FMJykgfHwgaS5zdGFydHNXaXRoKCdFUlJPUicpKS5sZW5ndGggPT09IDBcbiAgICAgICAgfTtcblxuICAgICAgICB0ZXN0UmVzdWx0cy5wdXNoKHJlc3VsdCk7XG5cbiAgICAgICAgLy8gTWFpbiBhc3NlcnRpb246IE5vIGNyaXRpY2FsIG92ZXJsYXBzXG4gICAgICAgIGV4cGVjdChvdmVybGFwcy5maWx0ZXIobyA9PiBvLnNldmVyaXR5ID09PSAnQ1JJVElDQUwnKS5sZW5ndGgsICdObyBjcml0aWNhbCBvdmVybGFwcyBzaG91bGQgZXhpc3QnKS50b0JlKDApO1xuXG4gICAgICAgIGF3YWl0IGNvbnRleHQuY2xvc2UoKTtcbiAgICB9KTtcbn1cblxuLy8gR2VuZXJhdGUgcmVwb3J0IGFmdGVyIGFsbCB0ZXN0c1xudGVzdC5hZnRlckFsbChhc3luYyAoKSA9PiB7XG4gICAgY29uc29sZS5sb2coJ1xcbuKVlOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVkOKVlycpO1xuICAgIGNvbnNvbGUubG9nKCfilZEgICAgICAgICAgICAgICAgICAgICBURVNUIFNVTU1BUlkgICAgICAgICAgICAgICAgICAgICAgICAgIOKVkScpO1xuICAgIGNvbnNvbGUubG9nKCfilZrilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZDilZ1cXG4nKTtcblxuICAgIGNvbnN0IHRvdGFsVGVzdHMgPSB0ZXN0UmVzdWx0cy5sZW5ndGg7XG4gICAgY29uc3QgcGFzc2VkVGVzdHMgPSB0ZXN0UmVzdWx0cy5maWx0ZXIociA9PiByLnBhc3NlZCkubGVuZ3RoO1xuXG4gICAgY29uc29sZS5sb2coYFRvdGFsIFRlc3RzOiAke3RvdGFsVGVzdHN9YCk7XG4gICAgY29uc29sZS5sb2coYFBhc3NlZDogJHtwYXNzZWRUZXN0c31gKTtcbiAgICBjb25zb2xlLmxvZyhgRmFpbGVkOiAke3RvdGFsVGVzdHMgLSBwYXNzZWRUZXN0c31gKTtcbiAgICBjb25zb2xlLmxvZyhgU3VjY2VzcyBSYXRlOiAkeygocGFzc2VkVGVzdHMgLyB0b3RhbFRlc3RzKSAqIDEwMCkudG9GaXhlZCgxKX0lXFxuYCk7XG5cbiAgICAvLyBEZXRhaWxlZCByZXN1bHRzXG4gICAgdGVzdFJlc3VsdHMuZm9yRWFjaChyZXN1bHQgPT4ge1xuICAgICAgICBjb25zdCBzdGF0dXMgPSByZXN1bHQucGFzc2VkID8gJ+KckyBQQVNTJyA6ICfinJcgRkFJTCc7XG4gICAgICAgIGNvbnN0IHZpZXdwb3J0ID0gcmVzdWx0LnZpZXdwb3J0O1xuICAgICAgICBjb25zb2xlLmxvZyhgJHtzdGF0dXN9IC0gJHt2aWV3cG9ydC5uYW1lfSAoJHt2aWV3cG9ydC53aWR0aH14JHt2aWV3cG9ydC5oZWlnaHR9KWApO1xuXG4gICAgICAgIGlmIChyZXN1bHQub3ZlcmxhcHMgJiYgcmVzdWx0Lm92ZXJsYXBzLmxlbmd0aCA+IDApIHtcbiAgICAgICAgICAgIHJlc3VsdC5vdmVybGFwcy5mb3JFYWNoKG92ZXJsYXAgPT4ge1xuICAgICAgICAgICAgICAgIGNvbnNvbGUubG9nKGAgICAgICAgWyR7b3ZlcmxhcC5zZXZlcml0eX1dICR7b3ZlcmxhcC5lbGVtZW50cy5qb2luKCcgPC0+ICcpfTogJHtvdmVybGFwLmFyZWEudG9GaXhlZCgwKX1weMKyIG92ZXJsYXBgKTtcbiAgICAgICAgICAgIH0pO1xuICAgICAgICB9XG5cbiAgICAgICAgaWYgKHJlc3VsdC5pc3N1ZXMgJiYgcmVzdWx0Lmlzc3Vlcy5sZW5ndGggPiAwKSB7XG4gICAgICAgICAgICByZXN1bHQuaXNzdWVzLnNsaWNlKDAsIDMpLmZvckVhY2goaXNzdWUgPT4ge1xuICAgICAgICAgICAgICAgIGNvbnNvbGUubG9nKGAgICAgICAgJHtpc3N1ZX1gKTtcbiAgICAgICAgICAgIH0pO1xuICAgICAgICAgICAgaWYgKHJlc3VsdC5pc3N1ZXMubGVuZ3RoID4gMykge1xuICAgICAgICAgICAgICAgIGNvbnNvbGUubG9nKGAgICAgICAgLi4uIGFuZCAke3Jlc3VsdC5pc3N1ZXMubGVuZ3RoIC0gM30gbW9yZSBpc3N1ZXNgKTtcbiAgICAgICAgICAgIH1cbiAgICAgICAgfVxuICAgIH0pO1xuXG4gICAgLy8gU2F2ZSBkZXRhaWxlZCBKU09OIHJlcG9ydFxuICAgIGNvbnN0IHJlcG9ydCA9IHtcbiAgICAgICAgdGltZXN0YW1wOiBuZXcgRGF0ZSgpLnRvSVNPU3RyaW5nKCksXG4gICAgICAgIGJhc2VVcmw6IEJBU0VfVVJMLFxuICAgICAgICBwYWdlUGF0aDogUEFHRV9QQVRILFxuICAgICAgICBzdW1tYXJ5OiB7XG4gICAgICAgICAgICB0b3RhbFRlc3RzLFxuICAgICAgICAgICAgcGFzc2VkVGVzdHMsXG4gICAgICAgICAgICBmYWlsZWRUZXN0czogdG90YWxUZXN0cyAtIHBhc3NlZFRlc3RzLFxuICAgICAgICAgICAgc3VjY2Vzc1JhdGU6ICgocGFzc2VkVGVzdHMgLyB0b3RhbFRlc3RzKSAqIDEwMCkudG9GaXhlZCgxKSArICclJ1xuICAgICAgICB9LFxuICAgICAgICByZXN1bHRzOiB0ZXN0UmVzdWx0c1xuICAgIH07XG5cbiAgICBhd2FpdCBmcy53cml0ZUZpbGUoUkVQT1JUX0ZJTEUsIEpTT04uc3RyaW5naWZ5KHJlcG9ydCwgbnVsbCwgMikpO1xuICAgIGNvbnNvbGUubG9nKGBcXG7inJMgRGV0YWlsZWQgcmVwb3J0IHNhdmVkOiAke1JFUE9SVF9GSUxFfWApO1xuICAgIGNvbnNvbGUubG9nKGBcXG4ke3Bhc3NlZFRlc3RzID09PSB0b3RhbFRlc3RzID8gJ+KckyBBTEwgVEVTVFMgUEFTU0VEIScgOiAn4pyXIFNPTUUgVEVTVFMgRkFJTEVEJ31gKTtcbn0pO1xuIl0sIm1hcHBpbmdzIjoiOztBQUFBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7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