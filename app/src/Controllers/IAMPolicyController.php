<?php
namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Database;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\AuditLog;

class IAMPolicyController {

    public function analyze(): void {
        $clientIp = RateLimit::getClientIp();

        // Check rate limit (10 per hour for anonymous, unlimited for authenticated)
        $user = Auth::getUserFromToken(false);
        if (!$user) {
            if (!RateLimit::check("iam_policy_analyze:$clientIp", 10, 3600)) {
                Response::error('Rate limit exceeded. Please authenticate for unlimited access.', 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['policy_document'])) {
            Response::error('policy_document is required', 422, [
                'validation_errors' => ['policy_document' => ['The policy document field is required']]
            ]);
            return;
        }

        $policyDoc = $input['policy_document'];
        $policyName = $input['policy_name'] ?? 'Unnamed Policy';

        // Parse policy document if it's a string
        if (is_string($policyDoc)) {
            $policyDoc = json_decode($policyDoc, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Response::error('Invalid JSON in policy document', 422);
                return;
            }
        }

        // Analyze the policy
        $analysis = $this->analyzePolicy($policyDoc, $policyName);

        // Store in database if authenticated
        if ($user) {
            $this->storeAnalysis($user['id'], $policyName, $policyDoc, $analysis);

            AuditLog::log(
                $user['id'],
                'iam_policy_analyze',
                'IAM Policy Analyzed',
                ['policy_name' => $policyName, 'risk_score' => $analysis['risk_score']]
            );
        }

        Response::success([
            'policy_name' => $policyName,
            'risk_score' => $analysis['risk_score'],
            'risk_level' => $analysis['risk_level'],
            'findings' => $analysis['findings'],
            'statistics' => $analysis['statistics'],
            'recommendations' => $analysis['recommendations']
        ]);
    }

    private function analyzePolicy(array $policy, string $name): array {
        $findings = [];
        $riskScore = 0;
        $statistics = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'has_wildcards' => false,
            'has_public_access' => false,
            'has_admin_access' => false,
            'affected_resources' => []
        ];

        // Validate policy structure
        if (!isset($policy['Statement']) || !is_array($policy['Statement'])) {
            $findings[] = [
                'severity' => 'critical',
                'issue' => 'Invalid policy structure',
                'recommendation' => 'Policy must contain a Statement array',
                'line' => null
            ];
            $riskScore += 100;
            $statistics['critical']++;

            return [
                'risk_score' => min(100, $riskScore),
                'risk_level' => $this->getRiskLevel($riskScore),
                'findings' => $findings,
                'statistics' => $statistics,
                'recommendations' => $this->generateRecommendations($findings)
            ];
        }

        // Analyze each statement
        foreach ($policy['Statement'] as $index => $statement) {
            $statementFindings = $this->analyzeStatement($statement, $index);
            $findings = array_merge($findings, $statementFindings);
        }

        // Calculate risk score and statistics
        foreach ($findings as $finding) {
            switch ($finding['severity']) {
                case 'critical':
                    $riskScore += 25;
                    $statistics['critical']++;
                    break;
                case 'high':
                    $riskScore += 15;
                    $statistics['high']++;
                    break;
                case 'medium':
                    $riskScore += 8;
                    $statistics['medium']++;
                    break;
                case 'low':
                    $riskScore += 3;
                    $statistics['low']++;
                    break;
            }
        }

        // Check for wildcards, public access, admin access
        $statistics['has_wildcards'] = $this->hasWildcards($policy);
        $statistics['has_public_access'] = $this->hasPublicAccess($policy);
        $statistics['has_admin_access'] = $this->hasAdminAccess($policy);
        $statistics['affected_resources'] = $this->getAffectedResources($policy);

        return [
            'risk_score' => min(100, $riskScore),
            'risk_level' => $this->getRiskLevel($riskScore),
            'findings' => $findings,
            'statistics' => $statistics,
            'recommendations' => $this->generateRecommendations($findings)
        ];
    }

    private function analyzeStatement(array $statement, int $index): array {
        $findings = [];
        $effect = $statement['Effect'] ?? '';
        $actions = $statement['Action'] ?? [];
        $resources = $statement['Resource'] ?? [];
        $principal = $statement['Principal'] ?? null;

        // Normalize to arrays
        if (!is_array($actions)) {
            $actions = [$actions];
        }
        if (!is_array($resources)) {
            $resources = [$resources];
        }

        // Check for overly permissive wildcards
        if (in_array('*', $actions) && in_array('*', $resources) && $effect === 'Allow') {
            $findings[] = [
                'severity' => 'critical',
                'issue' => 'Administrator access granted (Action: *, Resource: *)',
                'statement_index' => $index,
                'recommendation' => 'Follow principle of least privilege. Specify exact actions and resources needed.'
            ];
        }

        // Check for wildcard principals
        if ($principal === '*' || (is_array($principal) && in_array('*', $principal))) {
            $findings[] = [
                'severity' => 'critical',
                'issue' => 'Public access allowed (Principal: *)',
                'statement_index' => $index,
                'recommendation' => 'Restrict access to specific principals. Never use "*" in Principal field.'
            ];
        }

        // Check for dangerous actions
        $dangerousActions = [
            'iam:CreateAccessKey' => 'high',
            'iam:CreateUser' => 'high',
            'iam:PutUserPolicy' => 'critical',
            'iam:AttachUserPolicy' => 'critical',
            'iam:PassRole' => 'high',
            'sts:AssumeRole' => 'medium',
            's3:DeleteBucket' => 'high',
            's3:PutBucketPolicy' => 'high',
            'ec2:RunInstances' => 'medium',
            'lambda:CreateFunction' => 'medium',
            'lambda:UpdateFunctionCode' => 'high'
        ];

        foreach ($actions as $action) {
            // Check for wildcard in action
            if (strpos($action, '*') !== false) {
                $severity = 'medium';
                if ($action === '*') {
                    $severity = 'critical';
                } elseif (preg_match('/^[a-z]+:\*$/', $action)) {
                    $severity = 'high';
                }

                $findings[] = [
                    'severity' => $severity,
                    'issue' => "Wildcard action: {$action}",
                    'statement_index' => $index,
                    'recommendation' => 'Specify exact actions instead of using wildcards'
                ];
            }

            // Check for specific dangerous actions
            if (isset($dangerousActions[$action])) {
                $findings[] = [
                    'severity' => $dangerousActions[$action],
                    'issue' => "Dangerous action allowed: {$action}",
                    'statement_index' => $index,
                    'recommendation' => "Review if {$action} permission is absolutely necessary"
                ];
            }
        }

        // Check for wildcard resources
        foreach ($resources as $resource) {
            if ($resource === '*') {
                $findings[] = [
                    'severity' => 'high',
                    'issue' => 'All resources accessible (Resource: *)',
                    'statement_index' => $index,
                    'recommendation' => 'Limit to specific ARNs or resource patterns'
                ];
            }
        }

        // Check for missing conditions (best practice)
        if (!isset($statement['Condition']) && $effect === 'Allow') {
            $findings[] = [
                'severity' => 'low',
                'issue' => 'No conditions specified',
                'statement_index' => $index,
                'recommendation' => 'Consider adding conditions for IP restrictions, MFA, or time-based access'
            ];
        }

        return $findings;
    }

    private function hasWildcards(array $policy): bool {
        foreach ($policy['Statement'] as $statement) {
            $actions = $statement['Action'] ?? [];
            $resources = $statement['Resource'] ?? [];

            if (!is_array($actions)) {
                $actions = [$actions];
            }
            if (!is_array($resources)) {
                $resources = [$resources];
            }

            foreach ($actions as $action) {
                if (strpos($action, '*') !== false) {
                    return true;
                }
            }
            foreach ($resources as $resource) {
                if ($resource === '*') {
                    return true;
                }
            }
        }
        return false;
    }

    private function hasPublicAccess(array $policy): bool {
        foreach ($policy['Statement'] as $statement) {
            $principal = $statement['Principal'] ?? null;
            if ($principal === '*' || (is_array($principal) && in_array('*', $principal))) {
                return true;
            }
        }
        return false;
    }

    private function hasAdminAccess(array $policy): bool {
        foreach ($policy['Statement'] as $statement) {
            $effect = $statement['Effect'] ?? '';
            $actions = $statement['Action'] ?? [];
            $resources = $statement['Resource'] ?? [];

            if (!is_array($actions)) {
                $actions = [$actions];
            }
            if (!is_array($resources)) {
                $resources = [$resources];
            }

            if ($effect === 'Allow' && in_array('*', $actions) && in_array('*', $resources)) {
                return true;
            }
        }
        return false;
    }

    private function getAffectedResources(array $policy): array {
        $resources = [];
        foreach ($policy['Statement'] as $statement) {
            $statementResources = $statement['Resource'] ?? [];
            if (!is_array($statementResources)) {
                $statementResources = [$statementResources];
            }
            $resources = array_merge($resources, $statementResources);
        }
        return array_unique($resources);
    }

    private function getRiskLevel(int $score): string {
        if ($score >= 75) {
            return 'critical';
        } elseif ($score >= 50) {
            return 'high';
        } elseif ($score >= 25) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    private function generateRecommendations(array $findings): array {
        $recommendations = [];

        $criticalCount = count(array_filter($findings, fn($f) => $f['severity'] === 'critical'));
        $highCount = count(array_filter($findings, fn($f) => $f['severity'] === 'high'));

        if ($criticalCount > 0) {
            $recommendations[] = "Address {$criticalCount} critical issue(s) immediately - these pose severe security risks";
        }
        if ($highCount > 0) {
            $recommendations[] = "Review {$highCount} high-severity finding(s) - these could lead to security breaches";
        }

        $recommendations[] = "Follow AWS IAM best practices: principle of least privilege, use managed policies, enable MFA";
        $recommendations[] = "Regularly audit IAM policies and remove unused permissions";
        $recommendations[] = "Consider using AWS Access Analyzer for continuous monitoring";

        return $recommendations;
    }

    private function storeAnalysis(string $userId, string $policyName, array $policyDoc, array $analysis): void {
        try {
            $conn = Database::getConnection();

            // Create parent scan record
            $stmt = $conn->prepare("
                INSERT INTO security_scans (user_id, scan_type, status, severity, score, findings_count, completed_at, metadata)
                VALUES ($1, $2, $3, $4, $5, $6, NOW(), $7)
                RETURNING id
            ");

            $metadata = json_encode([
                'policy_name' => $policyName,
                'has_wildcards' => $analysis['statistics']['has_wildcards'],
                'has_public_access' => $analysis['statistics']['has_public_access'],
                'has_admin_access' => $analysis['statistics']['has_admin_access']
            ]);

            $stmt->execute([
                $userId,
                'iam_policy',
                'completed',
                $analysis['risk_level'],
                $analysis['risk_score'],
                count($analysis['findings']),
                $metadata
            ]);

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $scanId = $result['id'];

            // Store detailed IAM policy analysis
            $stmt = $conn->prepare("
                INSERT INTO iam_policy_scans (
                    scan_id, user_id, policy_name, policy_document, risk_score, findings,
                    has_wildcards, has_public_access, has_admin_access, affected_resources
                ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)
            ");

            $stmt->execute([
                $scanId,
                $userId,
                $policyName,
                json_encode($policyDoc),
                $analysis['risk_score'],
                json_encode($analysis['findings']),
                $analysis['statistics']['has_wildcards'] ? 't' : 'f',
                $analysis['statistics']['has_public_access'] ? 't' : 'f',
                $analysis['statistics']['has_admin_access'] ? 't' : 'f',
                json_encode($analysis['statistics']['affected_resources'])
            ]);

        } catch (\Exception $e) {
            // Log error but don't fail the request
            error_log("Failed to store IAM policy analysis: " . $e->getMessage());
        }
    }

    public function getHistory(): void {
        $user = Auth::getUserFromToken();
        if (!$user) {
            Response::error('Authentication required', 401);
            return;
        }

        try {
            $conn = Database::getConnection();

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

            $stmt = $conn->prepare("
                SELECT
                    s.id, s.scan_type, s.status, s.severity, s.score, s.findings_count,
                    s.created_at, s.completed_at,
                    i.policy_name, i.risk_score, i.has_wildcards, i.has_public_access, i.has_admin_access
                FROM security_scans s
                LEFT JOIN iam_policy_scans i ON s.id = i.scan_id
                WHERE s.user_id = $1 AND s.scan_type = 'iam_policy'
                ORDER BY s.created_at DESC
                LIMIT $2 OFFSET $3
            ");

            $stmt->execute([$user['id'], $limit, $offset]);
            $scans = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success([
                'scans' => $scans,
                'total' => count($scans),
                'limit' => $limit,
                'offset' => $offset
            ]);

        } catch (\Exception $e) {
            Response::error('Failed to fetch scan history: ' . $e->getMessage(), 500);
        }
    }
}
