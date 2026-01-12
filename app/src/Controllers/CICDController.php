<?php

namespace App\Controllers;

use App\Utils\Auth;
use App\Utils\Database;
use App\Utils\Logger;

/**
 * CI/CD Integration Controller
 *
 * Provides CI/CD integration endpoints including:
 * - GitHub Actions integration
 * - GitLab CI integration
 * - SBOM generation and validation
 * - Build artifact scanning
 * - Webhook handlers for CI/CD pipelines
 */
class CICDController
{
    private $db;
    private $auth;
    private $logger;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
        $this->logger = new Logger('CICD');
    }

    /**
     * Generate SBOM (Software Bill of Materials)
     *
     * POST /api/v1/ci/sbom/generate
     * Body: {
     *   "format": "cyclonedx|spdx",
     *   "directory": "/path/to/project",
     *   "include_dev_dependencies": true,
     *   "include_transitive": true
     * }
     */
    public function generateSBOM()
    {
        try {
            $user = $this->auth->getUserFromToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $format = $input['format'] ?? 'cyclonedx';
            $directory = $input['directory'] ?? '.';
            $includeDev = $input['include_dev_dependencies'] ?? true;
            $includeTransitive = $input['include_transitive'] ?? true;

            if (!in_array($format, ['cyclonedx', 'spdx'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid format. Use cyclonedx or spdx']);
                return;
            }

            // Detect package managers and generate SBOM
            $sbom = $this->detectAndGenerateSBOM($directory, $format, $includeDev, $includeTransitive);

            // Save SBOM to database
            $sbomId = $this->saveSBOM($user['id'], $sbom, $format);

            // Generate download URL
            $downloadUrl = getenv('API_URL') . '/api/v1/ci/sbom/download/' . $sbomId;

            echo json_encode([
                'id' => $sbomId,
                'format' => $format,
                'components_count' => count($sbom['components'] ?? []),
                'url' => $downloadUrl,
                'sbom' => $sbom,
                'created_at' => date('c')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('SBOM generation failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'SBOM generation failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Validate SBOM
     *
     * POST /api/v1/ci/sbom/validate
     * Body: {
     *   "sbom": {...},  // SBOM JSON
     *   "format": "cyclonedx|spdx",
     *   "check_vulnerabilities": true,
     *   "check_licenses": true
     * }
     */
    public function validateSBOM()
    {
        try {
            $user = $this->auth->getUserFromToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $sbom = $input['sbom'] ?? null;
            $format = $input['format'] ?? 'cyclonedx';
            $checkVulns = $input['check_vulnerabilities'] ?? true;
            $checkLicenses = $input['check_licenses'] ?? true;

            if (!$sbom) {
                http_response_code(400);
                echo json_encode(['error' => 'SBOM data required']);
                return;
            }

            // Validate SBOM format
            $formatValidation = $this->validateSBOMFormat($sbom, $format);

            // Check for vulnerabilities if requested
            $vulnerabilities = [];
            if ($checkVulns) {
                $vulnerabilities = $this->checkSBOMVulnerabilities($sbom);
            }

            // Check licenses if requested
            $licenseIssues = [];
            if ($checkLicenses) {
                $licenseIssues = $this->checkSBOMLicenses($sbom);
            }

            $isValid = $formatValidation['valid']
                && (count($vulnerabilities) === 0 || !$checkVulns)
                && (count($licenseIssues) === 0 || !$checkLicenses);

            echo json_encode([
                'valid' => $isValid,
                'format_validation' => $formatValidation,
                'vulnerabilities' => $vulnerabilities,
                'vulnerability_count' => count($vulnerabilities),
                'license_issues' => $licenseIssues,
                'license_issue_count' => count($licenseIssues),
                'timestamp' => date('c')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('SBOM validation failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'SBOM validation failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Scan build artifacts
     *
     * POST /api/v1/ci/artifacts/scan
     * Body: {
     *   "artifacts": ["path/to/artifact1", "path/to/artifact2"],
     *   "scan_types": ["malware", "secrets", "vulnerabilities"]
     * }
     */
    public function scanArtifacts()
    {
        try {
            $user = $this->auth->getUserFromToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $artifacts = $input['artifacts'] ?? [];
            $scanTypes = $input['scan_types'] ?? ['malware', 'secrets', 'vulnerabilities'];

            if (empty($artifacts)) {
                http_response_code(400);
                echo json_encode(['error' => 'At least one artifact path required']);
                return;
            }

            $results = [];
            foreach ($artifacts as $artifact) {
                if (!file_exists($artifact)) {
                    $results[$artifact] = ['error' => 'File not found'];
                    continue;
                }

                $artifactResults = [
                    'file' => $artifact,
                    'size' => filesize($artifact),
                    'hash' => hash_file('sha256', $artifact),
                    'scans' => []
                ];

                // Run requested scans
                foreach ($scanTypes as $scanType) {
                    switch ($scanType) {
                        case 'malware':
                            $artifactResults['scans']['malware'] = $this->scanForMalware($artifact);
                            break;
                        case 'secrets':
                            $artifactResults['scans']['secrets'] = $this->scanForSecrets($artifact);
                            break;
                        case 'vulnerabilities':
                            $artifactResults['scans']['vulnerabilities'] = $this->scanForVulnerabilities($artifact);
                            break;
                    }
                }

                // Calculate overall risk score
                $artifactResults['risk_score'] = $this->calculateArtifactRiskScore($artifactResults['scans']);
                $artifactResults['passed'] = $artifactResults['risk_score'] < 50;

                $results[$artifact] = $artifactResults;
            }

            // Determine overall pass/fail
            $overallPassed = true;
            foreach ($results as $result) {
                if (!($result['passed'] ?? false)) {
                    $overallPassed = false;
                    break;
                }
            }

            echo json_encode([
                'passed' => $overallPassed,
                'artifacts_scanned' => count($artifacts),
                'results' => $results,
                'timestamp' => date('c')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Artifact scan failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'Artifact scan failed: ' . $e->getMessage()]);
        }
    }

    /**
     * CI/CD Webhook handler
     *
     * POST /api/v1/ci/webhook
     * Headers: X-CI-Provider: github|gitlab|jenkins|circleci
     * Body: CI/CD webhook payload
     */
    public function webhook()
    {
        try {
            $provider = $_SERVER['HTTP_X_CI_PROVIDER'] ?? 'unknown';
            $payload = json_decode(file_get_contents('php://input'), true);

            // Validate webhook signature (provider-specific)
            if (!$this->validateWebhookSignature($provider, $payload)) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid webhook signature']);
                return;
            }

            // Process webhook based on provider
            $result = $this->processWebhook($provider, $payload);

            // Save webhook event
            $this->saveWebhookEvent($provider, $payload, $result);

            echo json_encode([
                'status' => 'processed',
                'provider' => $provider,
                'result' => $result,
                'timestamp' => date('c')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Webhook processing failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'Webhook processing failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Get CI/CD integration status and statistics
     *
     * GET /api/v1/ci/stats
     */
    public function getStats()
    {
        try {
            $user = $this->auth->getUserFromToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                return;
            }

            $stats = $this->db->query(
                "SELECT
                    COUNT(*) as total_scans,
                    COUNT(DISTINCT project_name) as total_projects,
                    SUM(CASE WHEN passed = TRUE THEN 1 ELSE 0 END) as passed_scans,
                    SUM(CASE WHEN passed = FALSE THEN 1 ELSE 0 END) as failed_scans,
                    AVG(scan_duration_ms) as avg_scan_duration
                FROM ci_scans
                WHERE user_id = ?
                AND created_at > NOW() - INTERVAL '30 days'",
                [$user['id']]
            )->fetch();

            echo json_encode([
                'stats' => $stats,
                'period' => 'last_30_days',
                'timestamp' => date('c')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Stats retrieval failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'Stats retrieval failed: ' . $e->getMessage()]);
        }
    }

    // ===== PRIVATE HELPER METHODS =====

    private function detectAndGenerateSBOM($directory, $format, $includeDev, $includeTransitive)
    {
        // Detect package manager files
        $packageManagers = $this->detectPackageManagers($directory);

        $components = [];
        foreach ($packageManagers as $pm) {
            $deps = $this->extractDependencies($pm, $directory, $includeDev, $includeTransitive);
            $components = array_merge($components, $deps);
        }

        // Build SBOM based on format
        if ($format === 'cyclonedx') {
            return $this->buildCycloneDXSBOM($components);
        } else {
            return $this->buildSPDXSBOM($components);
        }
    }

    private function detectPackageManagers($directory)
    {
        $managers = [];
        $files = [
            'package.json' => 'npm',
            'composer.json' => 'composer',
            'requirements.txt' => 'pip',
            'Pipfile' => 'pipenv',
            'Gemfile' => 'bundler',
            'go.mod' => 'go',
            'Cargo.toml' => 'cargo',
            'pom.xml' => 'maven',
            'build.gradle' => 'gradle'
        ];

        foreach ($files as $file => $manager) {
            if (file_exists($directory . '/' . $file)) {
                $managers[] = $manager;
            }
        }

        return $managers;
    }

    private function extractDependencies($packageManager, $directory, $includeDev, $includeTransitive)
    {
        // TODO: Implement actual dependency extraction for each package manager
        return [];
    }

    private function buildCycloneDXSBOM($components)
    {
        return [
            'bomFormat' => 'CycloneDX',
            'specVersion' => '1.4',
            'version' => 1,
            'metadata' => [
                'timestamp' => date('c'),
                'tools' => [
                    ['vendor' => 'VeriBits', 'name' => 'SBOM Generator', 'version' => '1.0.0']
                ]
            ],
            'components' => $components
        ];
    }

    private function buildSPDXSBOM($components)
    {
        return [
            'spdxVersion' => 'SPDX-2.3',
            'dataLicense' => 'CC0-1.0',
            'SPDXID' => 'SPDXRef-DOCUMENT',
            'name' => 'VeriBits Generated SBOM',
            'documentNamespace' => 'https://veribits.com/sbom/' . uniqid(),
            'creationInfo' => [
                'created' => date('c'),
                'creators' => ['Tool: VeriBits-SBOM-Generator-1.0.0']
            ],
            'packages' => $components
        ];
    }

    private function validateSBOMFormat($sbom, $format)
    {
        // TODO: Implement SBOM format validation
        return ['valid' => true, 'errors' => []];
    }

    private function checkSBOMVulnerabilities($sbom)
    {
        // TODO: Check components against vulnerability databases
        return [];
    }

    private function checkSBOMLicenses($sbom)
    {
        // TODO: Check license compliance
        return [];
    }

    private function scanForMalware($artifact)
    {
        // TODO: Integrate with malware scanning
        return ['clean' => true, 'threats' => []];
    }

    private function scanForSecrets($artifact)
    {
        // TODO: Scan for hardcoded secrets
        return ['secrets_found' => false, 'matches' => []];
    }

    private function scanForVulnerabilities($artifact)
    {
        // TODO: Scan for known vulnerabilities
        return ['vulnerabilities' => []];
    }

    private function calculateArtifactRiskScore($scans)
    {
        $score = 0;
        if (!empty($scans['malware']['threats'])) $score += 50;
        if (!empty($scans['secrets']['matches'])) $score += 30;
        if (!empty($scans['vulnerabilities'])) $score += 20;
        return min($score, 100);
    }

    private function validateWebhookSignature($provider, $payload)
    {
        // TODO: Implement provider-specific signature validation
        return true;
    }

    private function processWebhook($provider, $payload)
    {
        // TODO: Process webhook based on provider
        return ['processed' => true];
    }

    private function saveSBOM($userId, $sbom, $format)
    {
        $result = $this->db->query(
            "INSERT INTO sboms (user_id, format, content, created_at) VALUES (?, ?, ?, NOW()) RETURNING id",
            [$userId, $format, json_encode($sbom)]
        );
        return $result->fetch()['id'];
    }

    private function saveWebhookEvent($provider, $payload, $result)
    {
        $this->db->insert('webhook_events', [
            'provider' => $provider,
            'payload' => json_encode($payload),
            'result' => json_encode($result),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
