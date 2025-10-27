<?php
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Auth;
use VeriBits\Utils\Response;
use VeriBits\Utils\Validator;
use VeriBits\Utils\Logger;

class SecurityHeadersController {
    public function analyze(): void {
        $auth = Auth::optionalAuth();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = new Validator($body);
        $validator->required('url')->url('url');

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        $url = $body['url'];

        try {
            // Fetch headers from URL
            $headers = $this->fetchHeaders($url);

            if ($headers === null) {
                Response::error('Failed to fetch headers from URL', 400);
                return;
            }

            // Analyze security headers
            $analysis = $this->analyzeHeaders($headers);

            // Calculate security score
            $score = $this->calculateScore($analysis);

            Response::success([
                'url' => $url,
                'headers' => $headers,
                'analysis' => $analysis,
                'score' => $score,
                'grade' => $this->calculateGrade($score),
                'recommendations' => $this->getRecommendations($analysis)
            ]);
        } catch (\Exception $e) {
            Logger::error('Security headers analysis failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to analyze security headers: ' . $e->getMessage(), 500);
        }
    }

    private function fetchHeaders(string $url): ?array {
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 30,
                'user_agent' => 'VeriBits-SecurityHeadersAnalyzer/1.0',
                'follow_location' => true,
                'max_redirects' => 3
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);

        try {
            $headers = get_headers($url, true, $context);

            if ($headers === false) {
                return null;
            }

            // Normalize header names to lowercase
            $normalized = [];
            foreach ($headers as $key => $value) {
                if (is_string($key)) {
                    $normalized[strtolower($key)] = is_array($value) ? end($value) : $value;
                }
            }

            return $normalized;
        } catch (\Exception $e) {
            Logger::warning('Failed to fetch headers', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function analyzeHeaders(array $headers): array {
        $analysis = [];

        // Strict-Transport-Security (HSTS)
        $analysis['hsts'] = $this->analyzeHSTS($headers);

        // Content-Security-Policy (CSP)
        $analysis['csp'] = $this->analyzeCSP($headers);

        // X-Frame-Options
        $analysis['x_frame_options'] = $this->analyzeXFrameOptions($headers);

        // X-Content-Type-Options
        $analysis['x_content_type_options'] = $this->analyzeXContentTypeOptions($headers);

        // X-XSS-Protection
        $analysis['x_xss_protection'] = $this->analyzeXXSSProtection($headers);

        // Referrer-Policy
        $analysis['referrer_policy'] = $this->analyzeReferrerPolicy($headers);

        // Permissions-Policy
        $analysis['permissions_policy'] = $this->analyzePermissionsPolicy($headers);

        // Cross-Origin headers
        $analysis['cross_origin_embedder_policy'] = $this->analyzeHeader($headers, 'cross-origin-embedder-policy', ['require-corp']);
        $analysis['cross_origin_opener_policy'] = $this->analyzeHeader($headers, 'cross-origin-opener-policy', ['same-origin', 'same-origin-allow-popups']);
        $analysis['cross_origin_resource_policy'] = $this->analyzeHeader($headers, 'cross-origin-resource-policy', ['same-origin', 'same-site', 'cross-origin']);

        return $analysis;
    }

    private function analyzeHSTS(array $headers): array {
        $headerKey = 'strict-transport-security';
        $present = isset($headers[$headerKey]);

        if (!$present) {
            return [
                'present' => false,
                'status' => 'missing',
                'severity' => 'high',
                'message' => 'HSTS header is missing'
            ];
        }

        $value = $headers[$headerKey];
        $hasMaxAge = preg_match('/max-age=(\d+)/', $value, $matches);
        $maxAge = $hasMaxAge ? (int)$matches[1] : 0;
        $includeSubDomains = stripos($value, 'includeSubDomains') !== false;
        $preload = stripos($value, 'preload') !== false;

        $issues = [];
        if ($maxAge < 31536000) {
            $issues[] = 'max-age should be at least 31536000 (1 year)';
        }
        if (!$includeSubDomains) {
            $issues[] = 'Consider adding includeSubDomains directive';
        }

        return [
            'present' => true,
            'value' => $value,
            'max_age' => $maxAge,
            'include_subdomains' => $includeSubDomains,
            'preload' => $preload,
            'status' => empty($issues) ? 'good' : 'warning',
            'severity' => empty($issues) ? 'none' : 'medium',
            'issues' => $issues
        ];
    }

    private function analyzeCSP(array $headers): array {
        $headerKey = 'content-security-policy';
        $present = isset($headers[$headerKey]);

        if (!$present) {
            return [
                'present' => false,
                'status' => 'missing',
                'severity' => 'high',
                'message' => 'CSP header is missing'
            ];
        }

        $value = $headers[$headerKey];
        $hasUnsafeInline = stripos($value, "'unsafe-inline'") !== false;
        $hasUnsafeEval = stripos($value, "'unsafe-eval'") !== false;
        $hasDefaultSrc = stripos($value, 'default-src') !== false;

        $issues = [];
        if ($hasUnsafeInline) {
            $issues[] = "Contains 'unsafe-inline' which reduces XSS protection";
        }
        if ($hasUnsafeEval) {
            $issues[] = "Contains 'unsafe-eval' which allows code execution";
        }
        if (!$hasDefaultSrc) {
            $issues[] = "Missing 'default-src' directive";
        }

        return [
            'present' => true,
            'value' => $value,
            'has_unsafe_inline' => $hasUnsafeInline,
            'has_unsafe_eval' => $hasUnsafeEval,
            'has_default_src' => $hasDefaultSrc,
            'status' => empty($issues) ? 'good' : 'warning',
            'severity' => empty($issues) ? 'none' : 'medium',
            'issues' => $issues
        ];
    }

    private function analyzeXFrameOptions(array $headers): array {
        $headerKey = 'x-frame-options';
        $present = isset($headers[$headerKey]);

        if (!$present) {
            return [
                'present' => false,
                'status' => 'missing',
                'severity' => 'high',
                'message' => 'X-Frame-Options header is missing (clickjacking risk)'
            ];
        }

        $value = strtoupper($headers[$headerKey]);
        $validValues = ['DENY', 'SAMEORIGIN'];

        return [
            'present' => true,
            'value' => $value,
            'status' => in_array($value, $validValues) ? 'good' : 'warning',
            'severity' => in_array($value, $validValues) ? 'none' : 'medium',
            'message' => in_array($value, $validValues) ? 'Properly configured' : 'Invalid value'
        ];
    }

    private function analyzeXContentTypeOptions(array $headers): array {
        $headerKey = 'x-content-type-options';
        $present = isset($headers[$headerKey]);

        return [
            'present' => $present,
            'value' => $present ? $headers[$headerKey] : null,
            'status' => $present && strtolower($headers[$headerKey]) === 'nosniff' ? 'good' : 'missing',
            'severity' => $present ? 'none' : 'medium',
            'message' => $present ? 'Properly configured' : 'Missing X-Content-Type-Options header'
        ];
    }

    private function analyzeXXSSProtection(array $headers): array {
        $headerKey = 'x-xss-protection';
        $present = isset($headers[$headerKey]);

        return [
            'present' => $present,
            'value' => $present ? $headers[$headerKey] : null,
            'status' => $present ? 'good' : 'info',
            'severity' => 'low',
            'message' => $present ? 'Present (legacy header, CSP is preferred)' : 'Missing (CSP is the modern alternative)'
        ];
    }

    private function analyzeReferrerPolicy(array $headers): array {
        $headerKey = 'referrer-policy';
        $present = isset($headers[$headerKey]);
        $goodValues = ['no-referrer', 'no-referrer-when-downgrade', 'strict-origin', 'strict-origin-when-cross-origin'];

        if (!$present) {
            return [
                'present' => false,
                'status' => 'missing',
                'severity' => 'low',
                'message' => 'Referrer-Policy header is missing'
            ];
        }

        $value = strtolower($headers[$headerKey]);

        return [
            'present' => true,
            'value' => $value,
            'status' => in_array($value, $goodValues) ? 'good' : 'warning',
            'severity' => in_array($value, $goodValues) ? 'none' : 'low'
        ];
    }

    private function analyzePermissionsPolicy(array $headers): array {
        $headerKey = 'permissions-policy';
        $present = isset($headers[$headerKey]);

        return [
            'present' => $present,
            'value' => $present ? $headers[$headerKey] : null,
            'status' => $present ? 'good' : 'info',
            'severity' => $present ? 'none' : 'low',
            'message' => $present ? 'Configured' : 'Missing (recommended for controlling browser features)'
        ];
    }

    private function analyzeHeader(array $headers, string $headerName, array $validValues = []): array {
        $present = isset($headers[$headerName]);

        if (!$present) {
            return [
                'present' => false,
                'status' => 'missing',
                'severity' => 'low'
            ];
        }

        $value = $headers[$headerName];
        $isValid = empty($validValues) || in_array(strtolower($value), array_map('strtolower', $validValues));

        return [
            'present' => true,
            'value' => $value,
            'status' => $isValid ? 'good' : 'warning',
            'severity' => $isValid ? 'none' : 'low'
        ];
    }

    private function calculateScore(array $analysis): int {
        $score = 100;

        foreach ($analysis as $header => $data) {
            if ($data['status'] === 'missing' || $data['status'] === 'bad') {
                switch ($data['severity']) {
                    case 'high':
                        $score -= 15;
                        break;
                    case 'medium':
                        $score -= 10;
                        break;
                    case 'low':
                        $score -= 5;
                        break;
                }
            } elseif ($data['status'] === 'warning') {
                $score -= 5;
            }
        }

        return max(0, $score);
    }

    private function calculateGrade(int $score): string {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }

    private function getRecommendations(array $analysis): array {
        $recommendations = [];

        foreach ($analysis as $header => $data) {
            if ($data['status'] === 'missing') {
                $recommendations[] = [
                    'header' => $header,
                    'severity' => $data['severity'],
                    'message' => $data['message'] ?? "Add $header header",
                    'example' => $this->getHeaderExample($header)
                ];
            } elseif (!empty($data['issues'])) {
                foreach ($data['issues'] as $issue) {
                    $recommendations[] = [
                        'header' => $header,
                        'severity' => $data['severity'],
                        'message' => $issue
                    ];
                }
            }
        }

        return $recommendations;
    }

    private function getHeaderExample(string $header): ?string {
        $examples = [
            'hsts' => 'Strict-Transport-Security: max-age=31536000; includeSubDomains; preload',
            'csp' => "Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'",
            'x_frame_options' => 'X-Frame-Options: DENY',
            'x_content_type_options' => 'X-Content-Type-Options: nosniff',
            'referrer_policy' => 'Referrer-Policy: strict-origin-when-cross-origin',
            'permissions_policy' => 'Permissions-Policy: geolocation=(), microphone=(), camera=()'
        ];

        return $examples[$header] ?? null;
    }
}
