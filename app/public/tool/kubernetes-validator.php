<?php
// Kubernetes Validator - Server-side processing
$result = null;
$error = null;

// Simple YAML parser for Kubernetes manifests
function parseYAML($yaml) {
    $lines = explode("\n", $yaml);
    $result = [];
    $stack = [&$result];
    $currentIndent = 0;
    $inList = false;

    foreach ($lines as $lineNum => $line) {
        // Skip comments and empty lines
        if (preg_match('/^\s*#/', $line) || trim($line) === '') {
            continue;
        }

        // Get indentation level
        preg_match('/^(\s*)/', $line, $indentMatch);
        $indent = strlen($indentMatch[1]);
        $trimmedLine = trim($line);

        // Handle key-value pairs
        if (preg_match('/^([a-zA-Z0-9_-]+):\s*(.*)$/', $trimmedLine, $matches)) {
            $key = $matches[1];
            $value = trim($matches[2]);

            // Handle empty value (nested structure)
            if ($value === '') {
                $result[$key] = [];
            } else {
                // Remove quotes if present
                $value = trim($value, '"\'');

                // Try to convert to appropriate type
                if ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                } elseif (is_numeric($value)) {
                    $value = strpos($value, '.') !== false ? (float)$value : (int)$value;
                }

                $result[$key] = $value;
            }
        }
        // Handle list items
        elseif (preg_match('/^-\s+(.+)$/', $trimmedLine, $matches)) {
            $value = trim($matches[1]);

            // List item with key-value
            if (preg_match('/^([a-zA-Z0-9_-]+):\s*(.*)$/', $value, $kvMatch)) {
                $key = $kvMatch[1];
                $val = trim($kvMatch[2], '"\'');

                // Convert types
                if ($val === 'true') {
                    $val = true;
                } elseif ($val === 'false') {
                    $val = false;
                } elseif (is_numeric($val)) {
                    $val = strpos($val, '.') !== false ? (float)$val : (int)$val;
                }

                if (!isset($result[0]) || !is_array($result[0])) {
                    $result[] = [$key => $val];
                } else {
                    $result[count($result) - 1][$key] = $val;
                }
            } else {
                $result[] = trim($value, '"\'');
            }
        }
    }

    return $result;
}

// Recursive YAML parser for complex structures
function parseYAMLRecursive($yaml) {
    $lines = explode("\n", $yaml);
    $documents = [];
    $currentDoc = [];

    foreach ($lines as $line) {
        if (trim($line) === '---') {
            if (!empty($currentDoc)) {
                $documents[] = $currentDoc;
                $currentDoc = [];
            }
            continue;
        }

        if (trim($line) !== '') {
            $currentDoc[] = $line;
        }
    }

    if (!empty($currentDoc)) {
        $documents[] = $currentDoc;
    }

    $parsed = [];
    foreach ($documents as $doc) {
        $parsed[] = parseYAMLDocument(implode("\n", $doc));
    }

    return count($parsed) === 1 ? $parsed[0] : $parsed;
}

function parseYAMLDocument($yaml) {
    $lines = explode("\n", $yaml);
    $root = [];
    $stack = [['data' => &$root, 'indent' => -1]];

    foreach ($lines as $line) {
        if (preg_match('/^\s*#/', $line) || trim($line) === '') {
            continue;
        }

        preg_match('/^(\s*)/', $line, $match);
        $indent = strlen($match[1]);
        $trimmed = trim($line);

        // Pop stack to correct level
        while (count($stack) > 1 && $indent <= $stack[count($stack) - 1]['indent']) {
            array_pop($stack);
        }

        $current = &$stack[count($stack) - 1]['data'];

        // Handle list items
        if (preg_match('/^-\s+(.+)$/', $trimmed, $matches)) {
            $content = $matches[1];

            if (preg_match('/^([a-zA-Z0-9_-]+):\s*(.*)$/', $content, $kvMatch)) {
                $item = [];
                $key = $kvMatch[1];
                $value = parseValue($kvMatch[2]);

                if ($value === '') {
                    $item[$key] = [];
                    $current[] = $item;
                    $stack[] = ['data' => &$item[$key], 'indent' => $indent];
                } else {
                    $item[$key] = $value;
                    $current[] = $item;
                }
            } else {
                $current[] = parseValue($content);
            }
        }
        // Handle key-value pairs
        elseif (preg_match('/^([a-zA-Z0-9_-]+):\s*(.*)$/', $trimmed, $matches)) {
            $key = $matches[1];
            $value = $matches[2];

            if (trim($value) === '' || trim($value) === '|' || trim($value) === '>') {
                $current[$key] = [];
                $stack[] = ['data' => &$current[$key], 'indent' => $indent];
            } else {
                $current[$key] = parseValue($value);
            }
        }
    }

    return $root;
}

function parseValue($value) {
    $value = trim($value);
    $value = trim($value, '"\'');

    if ($value === 'true' || $value === 'True') {
        return true;
    } elseif ($value === 'false' || $value === 'False') {
        return false;
    } elseif ($value === 'null' || $value === 'Null' || $value === '~') {
        return null;
    } elseif (is_numeric($value)) {
        return strpos($value, '.') !== false ? (float)$value : (int)$value;
    }

    return $value;
}

// Security validation functions
function validateKubernetesManifest($yaml) {
    $issues = [];
    $warnings = [];
    $info = [];

    try {
        $manifest = parseYAMLRecursive($yaml);

        // Handle multiple documents
        $manifests = [];
        if (isset($manifest['kind'])) {
            $manifests = [$manifest];
        } else {
            $manifests = is_array($manifest) ? $manifest : [$manifest];
        }

        foreach ($manifests as $doc) {
            validateDocument($doc, $issues, $warnings, $info);
        }

        return [
            'valid' => true,
            'issues' => $issues,
            'warnings' => $warnings,
            'info' => $info,
            'manifest' => $manifest
        ];

    } catch (Exception $e) {
        return [
            'valid' => false,
            'error' => 'Failed to parse YAML: ' . $e->getMessage()
        ];
    }
}

function validateDocument($manifest, &$issues, &$warnings, &$info) {
    if (!isset($manifest['kind'])) {
        return;
    }

    $kind = $manifest['kind'];
    $name = $manifest['metadata']['name'] ?? 'unnamed';

    // Check for Pod or Deployment
    if (in_array($kind, ['Pod', 'Deployment', 'StatefulSet', 'DaemonSet', 'Job', 'CronJob'])) {
        $spec = $kind === 'Pod' ? $manifest['spec'] : ($manifest['spec']['template']['spec'] ?? []);

        if (!empty($spec)) {
            validatePodSpec($spec, $kind, $name, $issues, $warnings, $info);
        }
    }

    // Check RBAC
    if (in_array($kind, ['Role', 'ClusterRole'])) {
        validateRBAC($manifest, $kind, $name, $issues, $warnings, $info);
    }

    // Check Service Account
    if ($kind === 'ServiceAccount') {
        validateServiceAccount($manifest, $name, $issues, $warnings, $info);
    }

    // Check Network Policy
    if ($kind === 'NetworkPolicy') {
        $info[] = [
            'type' => 'network_policy',
            'message' => "Network policy '{$name}' found (good for network segmentation)"
        ];
    }
}

function validatePodSpec($spec, $kind, $name, &$issues, &$warnings, &$info) {
    $containers = $spec['containers'] ?? [];
    $initContainers = $spec['initContainers'] ?? [];
    $allContainers = array_merge($containers, $initContainers);

    // Check hostNetwork
    if (isset($spec['hostNetwork']) && $spec['hostNetwork'] === true) {
        $issues[] = [
            'severity' => 'high',
            'type' => 'host_network',
            'resource' => "$kind/$name",
            'message' => 'Using hostNetwork: true (pods can access host network)',
            'remediation' => 'Remove hostNetwork or set to false unless absolutely necessary'
        ];
    }

    // Check hostPID
    if (isset($spec['hostPID']) && $spec['hostPID'] === true) {
        $issues[] = [
            'severity' => 'high',
            'type' => 'host_pid',
            'resource' => "$kind/$name",
            'message' => 'Using hostPID: true (pods can see host processes)',
            'remediation' => 'Remove hostPID or set to false'
        ];
    }

    // Check hostIPC
    if (isset($spec['hostIPC']) && $spec['hostIPC'] === true) {
        $issues[] = [
            'severity' => 'high',
            'type' => 'host_ipc',
            'resource' => "$kind/$name",
            'message' => 'Using hostIPC: true (pods can access host IPC)',
            'remediation' => 'Remove hostIPC or set to false'
        ];
    }

    // Check volumes for hostPath
    if (isset($spec['volumes']) && is_array($spec['volumes'])) {
        foreach ($spec['volumes'] as $idx => $volume) {
            if (isset($volume['hostPath'])) {
                $path = $volume['hostPath']['path'] ?? 'unknown';
                $issues[] = [
                    'severity' => 'high',
                    'type' => 'host_path',
                    'resource' => "$kind/$name",
                    'message' => "Using hostPath mount: $path",
                    'remediation' => 'Use PersistentVolumeClaim, ConfigMap, or Secret instead of hostPath'
                ];
            }
        }
    }

    // Check pod security context
    if (!isset($spec['securityContext'])) {
        $warnings[] = [
            'severity' => 'medium',
            'type' => 'missing_pod_security_context',
            'resource' => "$kind/$name",
            'message' => 'Pod security context is not defined',
            'remediation' => 'Add spec.securityContext with runAsNonRoot: true, runAsUser, fsGroup'
        ];
    } else {
        $secCtx = $spec['securityContext'];

        if (!isset($secCtx['runAsNonRoot']) || $secCtx['runAsNonRoot'] !== true) {
            $warnings[] = [
                'severity' => 'medium',
                'type' => 'root_allowed',
                'resource' => "$kind/$name",
                'message' => 'Pod may run as root (runAsNonRoot not set to true)',
                'remediation' => 'Set spec.securityContext.runAsNonRoot: true'
            ];
        }

        if (!isset($secCtx['runAsUser'])) {
            $warnings[] = [
                'severity' => 'low',
                'type' => 'no_user_id',
                'resource' => "$kind/$name",
                'message' => 'runAsUser not specified',
                'remediation' => 'Set spec.securityContext.runAsUser to a non-zero value'
            ];
        } elseif ($secCtx['runAsUser'] === 0) {
            $issues[] = [
                'severity' => 'high',
                'type' => 'run_as_root',
                'resource' => "$kind/$name",
                'message' => 'Pod configured to run as root (runAsUser: 0)',
                'remediation' => 'Set runAsUser to a non-zero value (e.g., 1000)'
            ];
        }
    }

    // Check containers
    foreach ($allContainers as $idx => $container) {
        $containerName = $container['name'] ?? "container-$idx";
        validateContainer($container, $kind, $name, $containerName, $issues, $warnings, $info);
    }
}

function validateContainer($container, $kind, $podName, $containerName, &$issues, &$warnings, &$info) {
    $resource = "$kind/$podName/$containerName";

    // Check for privileged
    if (isset($container['securityContext']['privileged']) && $container['securityContext']['privileged'] === true) {
        $issues[] = [
            'severity' => 'critical',
            'type' => 'privileged_container',
            'resource' => $resource,
            'message' => 'Container is running in privileged mode',
            'remediation' => 'Remove privileged: true or set to false'
        ];
    }

    // Check container security context
    if (!isset($container['securityContext'])) {
        $warnings[] = [
            'severity' => 'medium',
            'type' => 'missing_security_context',
            'resource' => $resource,
            'message' => 'Container security context is not defined',
            'remediation' => 'Add securityContext with allowPrivilegeEscalation: false, capabilities.drop: [ALL], readOnlyRootFilesystem: true'
        ];
    } else {
        $secCtx = $container['securityContext'];

        // Check allowPrivilegeEscalation
        if (!isset($secCtx['allowPrivilegeEscalation']) || $secCtx['allowPrivilegeEscalation'] !== false) {
            $warnings[] = [
                'severity' => 'medium',
                'type' => 'privilege_escalation',
                'resource' => $resource,
                'message' => 'allowPrivilegeEscalation not set to false',
                'remediation' => 'Set securityContext.allowPrivilegeEscalation: false'
            ];
        }

        // Check readOnlyRootFilesystem
        if (!isset($secCtx['readOnlyRootFilesystem']) || $secCtx['readOnlyRootFilesystem'] !== true) {
            $warnings[] = [
                'severity' => 'low',
                'type' => 'writable_root_fs',
                'resource' => $resource,
                'message' => 'Root filesystem is not read-only',
                'remediation' => 'Set securityContext.readOnlyRootFilesystem: true'
            ];
        }

        // Check capabilities
        if (!isset($secCtx['capabilities']['drop']) || !in_array('ALL', $secCtx['capabilities']['drop'])) {
            $warnings[] = [
                'severity' => 'medium',
                'type' => 'capabilities_not_dropped',
                'resource' => $resource,
                'message' => 'Linux capabilities not dropped',
                'remediation' => 'Set securityContext.capabilities.drop: [ALL]'
            ];
        }

        // Check for added capabilities
        if (isset($secCtx['capabilities']['add']) && is_array($secCtx['capabilities']['add'])) {
            foreach ($secCtx['capabilities']['add'] as $cap) {
                if (in_array($cap, ['SYS_ADMIN', 'NET_ADMIN', 'SYS_PTRACE', 'SYS_MODULE'])) {
                    $issues[] = [
                        'severity' => 'high',
                        'type' => 'dangerous_capability',
                        'resource' => $resource,
                        'message' => "Dangerous capability added: $cap",
                        'remediation' => "Remove capability $cap unless absolutely necessary"
                    ];
                }
            }
        }

        // Check runAsNonRoot at container level
        if (isset($secCtx['runAsUser']) && $secCtx['runAsUser'] === 0) {
            $issues[] = [
                'severity' => 'high',
                'type' => 'run_as_root',
                'resource' => $resource,
                'message' => 'Container running as root (runAsUser: 0)',
                'remediation' => 'Set runAsUser to a non-zero value'
            ];
        }
    }

    // Check resource limits/requests
    if (!isset($container['resources'])) {
        $warnings[] = [
            'severity' => 'medium',
            'type' => 'missing_resources',
            'resource' => $resource,
            'message' => 'No resource limits or requests defined',
            'remediation' => 'Add resources.limits and resources.requests for cpu and memory'
        ];
    } else {
        if (!isset($container['resources']['limits'])) {
            $warnings[] = [
                'severity' => 'medium',
                'type' => 'missing_limits',
                'resource' => $resource,
                'message' => 'No resource limits defined',
                'remediation' => 'Add resources.limits for cpu and memory'
            ];
        }

        if (!isset($container['resources']['requests'])) {
            $warnings[] = [
                'severity' => 'low',
                'type' => 'missing_requests',
                'resource' => $resource,
                'message' => 'No resource requests defined',
                'remediation' => 'Add resources.requests for cpu and memory'
            ];
        }
    }

    // Check image tag
    if (isset($container['image'])) {
        $image = $container['image'];

        if (strpos($image, ':latest') !== false || strpos($image, ':') === false) {
            $warnings[] = [
                'severity' => 'low',
                'type' => 'latest_tag',
                'resource' => $resource,
                'message' => 'Using :latest tag or no tag specified',
                'remediation' => 'Use specific version tags for images (e.g., nginx:1.21.0)'
            ];
        }
    }
}

function validateRBAC($manifest, $kind, $name, &$issues, &$warnings, &$info) {
    $rules = $manifest['rules'] ?? [];

    foreach ($rules as $idx => $rule) {
        $verbs = $rule['verbs'] ?? [];
        $resources = $rule['resources'] ?? [];
        $apiGroups = $rule['apiGroups'] ?? [];

        // Check for wildcard permissions
        if (in_array('*', $verbs)) {
            $issues[] = [
                'severity' => 'high',
                'type' => 'rbac_wildcard_verbs',
                'resource' => "$kind/$name",
                'message' => 'RBAC rule grants all verbs (*)',
                'remediation' => 'Specify explicit verbs instead of using wildcard'
            ];
        }

        if (in_array('*', $resources)) {
            $issues[] = [
                'severity' => 'high',
                'type' => 'rbac_wildcard_resources',
                'resource' => "$kind/$name",
                'message' => 'RBAC rule grants access to all resources (*)',
                'remediation' => 'Specify explicit resources instead of using wildcard'
            ];
        }

        if (in_array('*', $apiGroups)) {
            $warnings[] = [
                'severity' => 'medium',
                'type' => 'rbac_wildcard_api_groups',
                'resource' => "$kind/$name",
                'message' => 'RBAC rule grants access to all API groups (*)',
                'remediation' => 'Specify explicit API groups'
            ];
        }

        // Check for dangerous permissions
        if (in_array('secrets', $resources) && (in_array('*', $verbs) || in_array('get', $verbs) || in_array('list', $verbs))) {
            $warnings[] = [
                'severity' => 'high',
                'type' => 'rbac_secrets_access',
                'resource' => "$kind/$name",
                'message' => 'RBAC rule grants access to secrets',
                'remediation' => 'Limit secret access to specific service accounts and namespaces'
            ];
        }

        if (in_array('pods/exec', $resources)) {
            $warnings[] = [
                'severity' => 'high',
                'type' => 'rbac_pod_exec',
                'resource' => "$kind/$name",
                'message' => 'RBAC rule grants pod exec permission',
                'remediation' => 'Pod exec should be limited to debugging purposes only'
            ];
        }
    }
}

function validateServiceAccount($manifest, $name, &$issues, &$warnings, &$info) {
    // Check if automountServiceAccountToken is disabled
    if (!isset($manifest['automountServiceAccountToken'])) {
        $warnings[] = [
            'severity' => 'low',
            'type' => 'auto_mount_token',
            'resource' => "ServiceAccount/$name",
            'message' => 'automountServiceAccountToken not explicitly set',
            'remediation' => 'Set automountServiceAccountToken: false if not needed'
        ];
    } elseif ($manifest['automountServiceAccountToken'] === true) {
        $info[] = [
            'type' => 'auto_mount_token_enabled',
            'message' => "ServiceAccount '$name' has token auto-mounting enabled"
        ];
    }
}

function calculateSecurityScore($issues, $warnings) {
    $score = 100;

    foreach ($issues as $issue) {
        switch ($issue['severity']) {
            case 'critical':
                $score -= 15;
                break;
            case 'high':
                $score -= 10;
                break;
            case 'medium':
                $score -= 5;
                break;
        }
    }

    foreach ($warnings as $warning) {
        switch ($warning['severity']) {
            case 'high':
                $score -= 5;
                break;
            case 'medium':
                $score -= 3;
                break;
            case 'low':
                $score -= 1;
                break;
        }
    }

    return max(0, $score);
}

function getScoreGrade($score) {
    if ($score >= 90) return 'A';
    if ($score >= 80) return 'B';
    if ($score >= 70) return 'C';
    if ($score >= 60) return 'D';
    return 'F';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['yaml'])) {
    $yaml = $_POST['yaml'];

    if (!empty($yaml)) {
        $validation = validateKubernetesManifest($yaml);

        if ($validation['valid']) {
            $result = [
                'valid' => true,
                'issues' => $validation['issues'],
                'warnings' => $validation['warnings'],
                'info' => $validation['info'],
                'score' => calculateSecurityScore($validation['issues'], $validation['warnings'])
            ];
            $result['grade'] = getScoreGrade($result['score']);
        } else {
            $error = $validation['error'];
        }
    } else {
        $error = 'Please provide a Kubernetes manifest to validate';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kubernetes Validator - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
    <style>
        .score-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 2rem;
            font-weight: bold;
            margin: 1rem 0;
        }
        .grade-a { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .grade-b { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .grade-c { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #333; }
        .grade-d { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); color: #333; }
        .grade-f { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; }

        .issue-card {
            background: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--border-color);
        }
        .issue-card.critical { border-left-color: #dc2626; }
        .issue-card.high { border-left-color: #ea580c; }
        .issue-card.medium { border-left-color: #f59e0b; }
        .issue-card.low { border-left-color: #3b82f6; }

        .severity-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .severity-critical { background: #dc2626; color: white; }
        .severity-high { background: #ea580c; color: white; }
        .severity-medium { background: #f59e0b; color: #000; }
        .severity-low { background: #3b82f6; color: white; }

        .remediation-box {
            margin-top: 0.75rem;
            padding: 0.75rem;
            background: var(--dark-bg);
            border-radius: 4px;
            border-left: 3px solid var(--success-color);
        }

        .example-card {
            background: var(--darker-bg);
            padding: 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            margin-bottom: 1rem;
        }
        .example-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        .example-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        .example-code {
            background: var(--dark-bg);
            padding: 0.75rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            overflow-x: auto;
            white-space: pre;
        }

        textarea {
            width: 100%;
            min-height: 400px;
            padding: 1rem;
            background: var(--darker-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            resize: vertical;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--darker-bg);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <nav>
        <div class="container">
            <a href="/" class="logo">VeriBits</a>
            <ul>
                <li><a href="/tools.php">Tools</a></li>
                <li><a href="/pricing.php">Pricing</a></li>
                <li><a href="/about.php">About</a></li>
                <li data-auth-item="true"><a href="/login.php">Login</a></li>
                <li data-auth-item="true"><a href="/signup.php" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <section style="padding: 8rem 2rem 4rem;">
        <div class="container" style="max-width: 1200px;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">Kubernetes Security Validator</h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 3rem;">
                Analyze Kubernetes manifests for security vulnerabilities and best practices
            </p>

            <?php if ($error): ?>
                <div class="feature-card" style="margin-bottom: 2rem; border-left: 4px solid var(--error-color);">
                    <h3 style="color: var(--error-color);">Error</h3>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <!-- Input Section -->
            <div class="feature-card">
                <h2 style="margin-bottom: 1.5rem;">Paste Your Kubernetes Manifest (YAML)</h2>

                <form method="POST">
                    <textarea name="yaml" placeholder="Paste your Kubernetes YAML manifest here...&#10;&#10;Example:&#10;apiVersion: v1&#10;kind: Pod&#10;metadata:&#10;  name: my-pod&#10;spec:&#10;  containers:&#10;  - name: nginx&#10;    image: nginx:1.21&#10;..."><?php echo isset($_POST['yaml']) ? htmlspecialchars($_POST['yaml']) : ''; ?></textarea>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        Validate Manifest
                    </button>
                </form>
            </div>

            <?php if ($result && $result['valid']): ?>
                <!-- Security Score -->
                <div class="feature-card" style="margin-top: 2rem; text-align: center;">
                    <h2 style="margin-bottom: 1rem;">Security Score</h2>
                    <div class="score-badge grade-<?php echo strtolower($result['grade']); ?>">
                        <?php echo $result['score']; ?>/100 (Grade <?php echo $result['grade']; ?>)
                    </div>
                    <p style="color: var(--text-secondary); margin-top: 1rem;">
                        <?php
                        if ($result['score'] >= 90) {
                            echo 'Excellent! Your Kubernetes manifest follows security best practices.';
                        } elseif ($result['score'] >= 70) {
                            echo 'Good, but there are some security improvements needed.';
                        } elseif ($result['score'] >= 50) {
                            echo 'Fair. Several security issues should be addressed.';
                        } else {
                            echo 'Poor. Critical security issues detected that require immediate attention.';
                        }
                        ?>
                    </p>
                </div>

                <!-- Statistics -->
                <div class="feature-card" style="margin-top: 2rem;">
                    <h2 style="margin-bottom: 1.5rem;">Summary</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value" style="color: #dc2626;">
                                <?php echo count(array_filter($result['issues'], fn($i) => in_array($i['severity'], ['critical', 'high']))); ?>
                            </div>
                            <div class="stat-label">Critical/High Issues</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" style="color: #f59e0b;">
                                <?php echo count(array_filter($result['issues'], fn($i) => $i['severity'] === 'medium')); ?>
                            </div>
                            <div class="stat-label">Medium Issues</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" style="color: #3b82f6;">
                                <?php echo count($result['warnings']); ?>
                            </div>
                            <div class="stat-label">Warnings</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" style="color: var(--success-color);">
                                <?php echo $result['score']; ?>
                            </div>
                            <div class="stat-label">Security Score</div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($result['issues'])): ?>
                    <!-- Security Issues -->
                    <div class="feature-card" style="margin-top: 2rem;">
                        <h2 style="margin-bottom: 1.5rem;">Security Issues</h2>

                        <?php foreach ($result['issues'] as $issue): ?>
                            <div class="issue-card <?php echo $issue['severity']; ?>">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                    <div>
                                        <span class="severity-badge severity-<?php echo $issue['severity']; ?>">
                                            <?php echo strtoupper($issue['severity']); ?>
                                        </span>
                                    </div>
                                    <code style="color: var(--text-secondary); font-size: 0.85rem;">
                                        <?php echo htmlspecialchars($issue['resource']); ?>
                                    </code>
                                </div>

                                <h3 style="margin: 0.75rem 0 0.5rem; color: var(--text-primary);">
                                    <?php
                                    $icons = [
                                        'critical' => '🔴',
                                        'high' => '🟠',
                                        'medium' => '🟡',
                                        'low' => '🔵'
                                    ];
                                    echo $icons[$issue['severity']] ?? '⚠️';
                                    ?>
                                    <?php echo htmlspecialchars($issue['message']); ?>
                                </h3>

                                <?php if (isset($issue['remediation'])): ?>
                                    <div class="remediation-box">
                                        <strong style="color: var(--success-color);">✓ Remediation:</strong><br>
                                        <span style="color: var(--text-secondary);">
                                            <?php echo htmlspecialchars($issue['remediation']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($result['warnings'])): ?>
                    <!-- Warnings -->
                    <div class="feature-card" style="margin-top: 2rem;">
                        <h2 style="margin-bottom: 1.5rem;">Warnings</h2>

                        <?php foreach ($result['warnings'] as $warning): ?>
                            <div class="issue-card <?php echo $warning['severity']; ?>">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                    <div>
                                        <span class="severity-badge severity-<?php echo $warning['severity']; ?>">
                                            <?php echo strtoupper($warning['severity']); ?>
                                        </span>
                                    </div>
                                    <code style="color: var(--text-secondary); font-size: 0.85rem;">
                                        <?php echo htmlspecialchars($warning['resource']); ?>
                                    </code>
                                </div>

                                <h3 style="margin: 0.75rem 0 0.5rem; color: var(--text-primary);">
                                    ⚠️ <?php echo htmlspecialchars($warning['message']); ?>
                                </h3>

                                <?php if (isset($warning['remediation'])): ?>
                                    <div class="remediation-box">
                                        <strong style="color: var(--success-color);">✓ Remediation:</strong><br>
                                        <span style="color: var(--text-secondary);">
                                            <?php echo htmlspecialchars($warning['remediation']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($result['issues']) && empty($result['warnings'])): ?>
                    <div class="feature-card" style="margin-top: 2rem; text-align: center; padding: 3rem;">
                        <h2 style="color: var(--success-color); margin-bottom: 1rem;">🎉 Perfect!</h2>
                        <p style="color: var(--text-secondary);">
                            No security issues or warnings found. Your Kubernetes manifest follows security best practices.
                        </p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Examples Section -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Example Manifests</h2>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <div class="example-card" onclick="loadExample('insecure-pod')">
                        <div class="example-title">🔴 Insecure Pod (Multiple Issues)</div>
                        <div class="example-code">apiVersion: v1
kind: Pod
metadata:
  name: insecure-app
spec:
  hostNetwork: true
  hostPID: true
  containers:
  - name: app
    image: nginx:latest
    securityContext:
      privileged: true
      runAsUser: 0</div>
                    </div>

                    <div class="example-card" onclick="loadExample('secure-pod')">
                        <div class="example-title">🟢 Secure Pod (Best Practices)</div>
                        <div class="example-code">apiVersion: v1
kind: Pod
metadata:
  name: secure-app
spec:
  securityContext:
    runAsNonRoot: true
    runAsUser: 1000
    fsGroup: 1000
  containers:
  - name: app
    image: nginx:1.21.0
    securityContext:
      allowPrivilegeEscalation: false
      readOnlyRootFilesystem: true
      capabilities:
        drop:
        - ALL
    resources:
      limits:
        cpu: "1"
        memory: "512Mi"
      requests:
        cpu: "100m"
        memory: "128Mi"</div>
                    </div>

                    <div class="example-card" onclick="loadExample('deployment')">
                        <div class="example-title">🟡 Deployment (Needs Improvement)</div>
                        <div class="example-code">apiVersion: apps/v1
kind: Deployment
metadata:
  name: web-app
spec:
  replicas: 3
  selector:
    matchLabels:
      app: web
  template:
    metadata:
      labels:
        app: web
    spec:
      containers:
      - name: web
        image: nginx:latest
        ports:
        - containerPort: 80</div>
                    </div>

                    <div class="example-card" onclick="loadExample('rbac')">
                        <div class="example-title">🔴 Overly Permissive RBAC</div>
                        <div class="example-code">apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRole
metadata:
  name: admin-role
rules:
- apiGroups: ["*"]
  resources: ["*"]
  verbs: ["*"]</div>
                    </div>
                </div>
            </div>

            <!-- Security Checks Info -->
            <div class="feature-card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">Security Checks Performed</h2>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <div>
                        <h3 style="color: var(--primary-color); margin-bottom: 0.75rem;">Container Security</h3>
                        <ul style="margin-left: 1.5rem; color: var(--text-secondary);">
                            <li>Privileged containers</li>
                            <li>Running as root user</li>
                            <li>Privilege escalation</li>
                            <li>Root filesystem access</li>
                            <li>Dangerous capabilities</li>
                        </ul>
                    </div>

                    <div>
                        <h3 style="color: var(--primary-color); margin-bottom: 0.75rem;">Host Access</h3>
                        <ul style="margin-left: 1.5rem; color: var(--text-secondary);">
                            <li>hostNetwork usage</li>
                            <li>hostPID usage</li>
                            <li>hostIPC usage</li>
                            <li>hostPath mounts</li>
                        </ul>
                    </div>

                    <div>
                        <h3 style="color: var(--primary-color); margin-bottom: 0.75rem;">Resource Management</h3>
                        <ul style="margin-left: 1.5rem; color: var(--text-secondary);">
                            <li>CPU limits and requests</li>
                            <li>Memory limits and requests</li>
                            <li>Image tag best practices</li>
                        </ul>
                    </div>

                    <div>
                        <h3 style="color: var(--primary-color); margin-bottom: 0.75rem;">RBAC & Permissions</h3>
                        <ul style="margin-left: 1.5rem; color: var(--text-secondary);">
                            <li>Wildcard permissions</li>
                            <li>Secret access</li>
                            <li>Pod exec permissions</li>
                            <li>Service account tokens</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2025 VeriBits. All rights reserved.</p>
            <p style="margin-top: 0.5rem;">
                A service from <a href="https://www.afterdarksys.com/" target="_blank" rel="noopener">After Dark Systems, LLC</a>
            </p>
        </div>
    </footer>

    <script>
        function loadExample(type) {
            const examples = {
                'insecure-pod': `apiVersion: v1
kind: Pod
metadata:
  name: insecure-app
spec:
  hostNetwork: true
  hostPID: true
  containers:
  - name: app
    image: nginx:latest
    securityContext:
      privileged: true
      runAsUser: 0`,

                'secure-pod': `apiVersion: v1
kind: Pod
metadata:
  name: secure-app
spec:
  securityContext:
    runAsNonRoot: true
    runAsUser: 1000
    fsGroup: 1000
  containers:
  - name: app
    image: nginx:1.21.0
    securityContext:
      allowPrivilegeEscalation: false
      readOnlyRootFilesystem: true
      capabilities:
        drop:
        - ALL
    resources:
      limits:
        cpu: "1"
        memory: "512Mi"
      requests:
        cpu: "100m"
        memory: "128Mi"`,

                'deployment': `apiVersion: apps/v1
kind: Deployment
metadata:
  name: web-app
spec:
  replicas: 3
  selector:
    matchLabels:
      app: web
  template:
    metadata:
      labels:
        app: web
    spec:
      containers:
      - name: web
        image: nginx:latest
        ports:
        - containerPort: 80`,

                'rbac': `apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRole
metadata:
  name: admin-role
rules:
- apiGroups: ["*"]
  resources: ["*"]
  verbs: ["*"]`
            };

            const textarea = document.querySelector('textarea[name="yaml"]');
            if (textarea && examples[type]) {
                textarea.value = examples[type];
                textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    </script>
</body>
</html>
