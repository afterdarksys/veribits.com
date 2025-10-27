const { test, expect } = require('@playwright/test');

// Test configuration
const BASE_URL = process.env.BASE_URL || 'http://localhost';

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

test.describe('Unauthenticated Pages', () => {
    test('Homepage loads successfully', async ({ page }) => {
        const response = await page.goto(BASE_URL);
        expect(response.status()).toBe(200);
        await expect(page).toHaveTitle(/VeriBits/);
    });

    test('Login page loads successfully', async ({ page }) => {
        const response = await page.goto(`${BASE_URL}/login.php`);
        expect(response.status()).toBe(200);
        await expect(page.locator('#email')).toBeVisible();
        await expect(page.locator('#password')).toBeVisible();
    });

    test('Signup page loads successfully', async ({ page }) => {
        const response = await page.goto(`${BASE_URL}/signup.php`);
        expect(response.status()).toBe(200);
    });

    test('Pricing page loads successfully', async ({ page }) => {
        const response = await page.goto(`${BASE_URL}/pricing.php`);
        expect(response.status()).toBe(200);
    });

    test('Tools page loads successfully', async ({ page }) => {
        const response = await page.goto(`${BASE_URL}/tools.php`);
        expect(response.status()).toBe(200);
    });

    test('About page loads successfully', async ({ page }) => {
        const response = await page.goto(`${BASE_URL}/about.php`);
        expect(response.status()).toBe(200);
    });
});

test.describe('Developer Tool Pages', () => {
    const tools = [
        'hash-validator',
        'url-encoder',
        'pgp-validate',
        'security-headers',
        'base64-encoder',
        'jwt-decoder',
        'regex-tester',
        'json-validator',
        'yaml-parser',
        'secret-scanner'
    ];

    for (const tool of tools) {
        test(`Tool page: ${tool} loads successfully`, async ({ page }) => {
            const response = await page.goto(`${BASE_URL}/tool/${tool}.php`);
            expect(response.status()).toBe(200);
        });
    }
});

test.describe('API Health and Status', () => {
    test('API health endpoint returns healthy status', async ({ request }) => {
        const response = await request.get(`${BASE_URL}/api/v1/health`);
        expect(response.status()).toBe(200);

        const json = await response.json();
        expect(json.status).toBe('healthy');
    });
});

test.describe('User Authentication - Admin', () => {
    test('Admin user can login successfully', async ({ page }) => {
        await page.goto(`${BASE_URL}/login.php`);

        await page.fill('#email', TEST_ACCOUNTS.admin.email);
        await page.fill('#password', TEST_ACCOUNTS.admin.password);
        await page.click('button[type="submit"]');

        await page.waitForURL(/dashboard/);
        expect(page.url()).toContain('/dashboard');
    });

    test('Admin user can access dashboard', async ({ page }) => {
        // Login first
        await page.goto(`${BASE_URL}/login.php`);
        await page.fill('#email', TEST_ACCOUNTS.admin.email);
        await page.fill('#password', TEST_ACCOUNTS.admin.password);
        await page.click('button[type="submit"]');
        await page.waitForURL(/dashboard/);

        // Access dashboard
        const response = await page.goto(`${BASE_URL}/dashboard.php`);
        expect(response.status()).toBe(200);
    });
});

test.describe('User Authentication - Developer', () => {
    test('Developer user can login successfully', async ({ page }) => {
        await page.goto(`${BASE_URL}/login.php`);

        await page.fill('#email', TEST_ACCOUNTS.developer.email);
        await page.fill('#password', TEST_ACCOUNTS.developer.password);
        await page.click('button[type="submit"]');

        await page.waitForURL(/dashboard/);
        expect(page.url()).toContain('/dashboard');
    });

    test('Developer user can access dashboard', async ({ page }) => {
        await page.goto(`${BASE_URL}/login.php`);
        await page.fill('#email', TEST_ACCOUNTS.developer.email);
        await page.fill('#password', TEST_ACCOUNTS.developer.password);
        await page.click('button[type="submit"]');
        await page.waitForURL(/dashboard/);

        const response = await page.goto(`${BASE_URL}/dashboard.php`);
        expect(response.status()).toBe(200);
    });
});

test.describe('User Authentication - Professional', () => {
    test('Professional user can login successfully', async ({ page }) => {
        await page.goto(`${BASE_URL}/login.php`);

        await page.fill('#email', TEST_ACCOUNTS.professional.email);
        await page.fill('#password', TEST_ACCOUNTS.professional.password);
        await page.click('button[type="submit"]');

        await page.waitForURL(/dashboard/);
        expect(page.url()).toContain('/dashboard');
    });
});

test.describe('User Authentication - Enterprise', () => {
    test('Enterprise user can login successfully', async ({ page }) => {
        await page.goto(`${BASE_URL}/login.php`);

        await page.fill('#email', TEST_ACCOUNTS.enterprise.email);
        await page.fill('#password', TEST_ACCOUNTS.enterprise.password);
        await page.click('button[type="submit"]');

        await page.waitForURL(/dashboard/);
        expect(page.url()).toContain('/dashboard');
    });
});

test.describe('User Authentication - Suspended Account', () => {
    test('Suspended user cannot login', async ({ page }) => {
        await page.goto(`${BASE_URL}/login.php`);

        await page.fill('#email', TEST_ACCOUNTS.suspended.email);
        await page.fill('#password', TEST_ACCOUNTS.suspended.password);
        await page.click('button[type="submit"]');

        // Should see error message about suspension
        await expect(page.locator('text=/suspended|inactive|disabled/i')).toBeVisible();
    });
});

test.describe('API Key Authentication', () => {
    test('Admin API key authenticates successfully', async ({ request }) => {
        const response = await request.get(`${BASE_URL}/api/v1/auth/verify`, {
            headers: {
                'X-API-Key': TEST_API_KEYS.admin
            }
        });

        expect(response.status()).toBe(200);
        const json = await response.json();
        expect(json.success).toBe(true);
    });

    test('Developer API key authenticates successfully', async ({ request }) => {
        const response = await request.get(`${BASE_URL}/api/v1/auth/verify`, {
            headers: {
                'X-API-Key': TEST_API_KEYS.developer
            }
        });

        expect(response.status()).toBe(200);
        const json = await response.json();
        expect(json.success).toBe(true);
    });

    test('Professional API key authenticates successfully', async ({ request }) => {
        const response = await request.get(`${BASE_URL}/api/v1/auth/verify`, {
            headers: {
                'X-API-Key': TEST_API_KEYS.professional
            }
        });

        expect(response.status()).toBe(200);
        const json = await response.json();
        expect(json.success).toBe(true);
    });

    test('Enterprise API key authenticates successfully', async ({ request }) => {
        const response = await request.get(`${BASE_URL}/api/v1/auth/verify`, {
            headers: {
                'X-API-Key': TEST_API_KEYS.enterprise
            }
        });

        expect(response.status()).toBe(200);
        const json = await response.json();
        expect(json.success).toBe(true);
    });

    test('Invalid API key is rejected', async ({ request }) => {
        const response = await request.get(`${BASE_URL}/api/v1/auth/verify`, {
            headers: {
                'X-API-Key': 'vb_invalid_key_12345'
            }
        });

        expect(response.status()).toBe(401);
    });
});

test.describe('Developer Tools API - URL Encoder', () => {
    test('URL encode operation works', async ({ request }) => {
        const response = await request.post(`${BASE_URL}/api/v1/tools/url/encode`, {
            data: {
                text: 'Hello World!',
                operation: 'encode'
            }
        });

        expect(response.status()).toBe(200);
        const json = await response.json();
        expect(json.success).toBe(true);
        expect(json.data.result).toBe('Hello+World%21');
    });

    test('URL decode operation works', async ({ request }) => {
        const response = await request.post(`${BASE_URL}/api/v1/tools/url/encode`, {
            data: {
                text: 'Hello+World%21',
                operation: 'decode'
            }
        });

        expect(response.status()).toBe(200);
        const json = await response.json();
        expect(json.success).toBe(true);
        expect(json.data.result).toBe('Hello World!');
    });
});

test.describe('Developer Tools API - Hash Validator', () => {
    test('MD5 hash validation works', async ({ request }) => {
        const response = await request.post(`${BASE_URL}/api/v1/tools/hash/validate`, {
            data: {
                hash: '5d41402abc4b2a76b9719d911017c592',
                type: 'md5'
            }
        });

        expect(response.status()).toBe(200);
        const json = await response.json();
        expect(json.success).toBe(true);
        expect(json.data.valid).toBe(true);
    });

    test('SHA-256 hash validation works', async ({ request }) => {
        const response = await request.post(`${BASE_URL}/api/v1/tools/hash/validate`, {
            data: {
                hash: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                type: 'sha256'
            }
        });

        expect(response.status()).toBe(200);
        const json = await response.json();
        expect(json.success).toBe(true);
        expect(json.data.valid).toBe(true);
    });

    test('Invalid hash is detected', async ({ request }) => {
        const response = await request.post(`${BASE_URL}/api/v1/tools/hash/validate`, {
            data: {
                hash: 'invalid_hash_123',
                type: 'md5'
            }
        });

        expect(response.status()).toBe(200);
        const json = await response.json();
        expect(json.success).toBe(true);
        expect(json.data.valid).toBe(false);
    });
});

test.describe('Developer Tools API - Base64 Encoder', () => {
    test('Base64 encode operation works', async ({ request }) => {
        const response = await request.post(`${BASE_URL}/api/v1/tools/base64/encode`, {
            data: {
                text: 'Hello World',
                operation: 'encode'
            }
        });

        expect(response.status()).toBe(200);
        const json = await response.json();
        expect(json.success).toBe(true);
        expect(json.data.result).toBe('SGVsbG8gV29ybGQ=');
    });

    test('Base64 decode operation works', async ({ request }) => {
        const response = await request.post(`${BASE_URL}/api/v1/tools/base64/encode`, {
            data: {
                text: 'SGVsbG8gV29ybGQ=',
                operation: 'decode'
            }
        });

        expect(response.status()).toBe(200);
        const json = await response.json();
        expect(json.success).toBe(true);
        expect(json.data.result).toBe('Hello World');
    });
});

test.describe('Developer Tools API - Security Headers', () => {
    test('Security headers analysis works', async ({ request }) => {
        const response = await request.post(`${BASE_URL}/api/v1/tools/security-headers`, {
            data: {
                url: 'https://www.google.com'
            }
        });

        expect(response.status()).toBe(200);
        const json = await response.json();
        expect(json.success).toBe(true);
        expect(json.data.headers).toBeDefined();
    });
});

test.describe('Rate Limiting', () => {
    test('Anonymous users are rate limited', async ({ request }) => {
        const requests = [];

        // Make 30 rapid requests (should hit limit)
        for (let i = 0; i < 30; i++) {
            requests.push(
                request.post(`${BASE_URL}/api/v1/tools/url/encode`, {
                    data: { text: `test${i}`, operation: 'encode' }
                })
            );
        }

        const responses = await Promise.all(requests);
        const statusCodes = responses.map(r => r.status());

        // At least one request should be rate limited (429)
        expect(statusCodes).toContain(429);
    });
});

test.describe('Error Handling', () => {
    test('404 for non-existent endpoints', async ({ request }) => {
        const response = await request.get(`${BASE_URL}/api/v1/nonexistent/endpoint`);
        expect(response.status()).toBe(404);
    });

    test('400 for malformed requests', async ({ request }) => {
        const response = await request.post(`${BASE_URL}/api/v1/tools/url/encode`, {
            data: {} // Missing required 'text' field
        });
        expect(response.status()).toBe(400);
    });
});

test.describe('Audit Logs', () => {
    test('Authenticated user can access audit logs', async ({ request }) => {
        const response = await request.get(`${BASE_URL}/api/v1/audit/logs`, {
            headers: {
                'X-API-Key': TEST_API_KEYS.developer
            }
        });

        expect(response.status()).toBe(200);
        const json = await response.json();
        expect(json.success).toBe(true);
        expect(Array.isArray(json.data.logs)).toBe(true);
    });

    test('Unauthenticated user cannot access audit logs', async ({ request }) => {
        const response = await request.get(`${BASE_URL}/api/v1/audit/logs`);
        expect(response.status()).toBe(401);
    });
});
