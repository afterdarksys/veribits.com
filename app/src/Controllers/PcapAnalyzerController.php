<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;

class PcapAnalyzerController
{
    private const MAX_FILE_SIZE = 104857600; // 100MB
    private const ALLOWED_EXTENSIONS = ['pcap', 'pcapng', 'cap'];
    private const PYTHON_SCRIPT = __DIR__ . '/../../../scripts/pcap_analyzer.py';

    /**
     * Analyze uploaded PCAP file
     */
    public function analyze(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        // Check if file was uploaded
        if (!isset($_FILES['pcap_file'])) {
            Response::error('No PCAP file uploaded', 400);
            return;
        }

        $file = $_FILES['pcap_file'];

        // Validate file upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('File upload error: ' . $this->getUploadErrorMessage($file['error']), 400);
            return;
        }

        // Check file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            Response::error('File size exceeds maximum limit of 100MB', 400);
            return;
        }

        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            Response::error('Invalid file type. Allowed: ' . implode(', ', self::ALLOWED_EXTENSIONS), 400);
            return;
        }

        try {
            $tmpFile = $file['tmp_name'];
            $useAI = isset($_POST['use_ai']) && $_POST['use_ai'] === 'true';

            // Run Python PCAP analyzer
            $analysisResults = $this->runPcapAnalyzer($tmpFile);

            if (!$analysisResults['success']) {
                Response::error($analysisResults['error'] ?? 'PCAP analysis failed', 500);
                return;
            }

            // Get AI insights if requested
            $aiInsights = null;
            if ($useAI) {
                $aiInsights = $this->getAIInsights($analysisResults);
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('PCAP analysis completed', [
                'analysis' => $analysisResults,
                'ai_insights' => $aiInsights,
                'file_name' => $file['name'],
                'file_size' => $file['size']
            ]);

        } catch (\Exception $e) {
            Logger::error('PCAP analysis error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            Response::error('PCAP analysis failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Run Python PCAP analyzer script
     */
    private function runPcapAnalyzer(string $pcapFile): array
    {
        // Check if Python script exists
        if (!file_exists(self::PYTHON_SCRIPT)) {
            return [
                'success' => false,
                'error' => 'PCAP analyzer script not found'
            ];
        }

        // Check if Python is available
        $pythonCmd = 'python3';
        exec("which python3 2>/dev/null", $output, $returnVar);
        if ($returnVar !== 0) {
            $pythonCmd = 'python';
            exec("which python 2>/dev/null", $output, $returnVar);
            if ($returnVar !== 0) {
                return [
                    'success' => false,
                    'error' => 'Python not found. Please install Python 3.'
                ];
            }
        }

        // Run the analyzer
        $cmd = sprintf(
            "%s %s %s 2>&1",
            escapeshellcmd($pythonCmd),
            escapeshellarg(self::PYTHON_SCRIPT),
            escapeshellarg($pcapFile)
        );

        exec($cmd, $output, $returnVar);

        $jsonOutput = implode("\n", $output);
        $results = json_decode($jsonOutput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::error('Failed to parse PCAP analyzer output', [
                'output' => $jsonOutput,
                'json_error' => json_last_error_msg()
            ]);
            return [
                'success' => false,
                'error' => 'Failed to parse analyzer output. scapy may not be installed. Install with: pip3 install scapy'
            ];
        }

        return $results;
    }

    /**
     * Get AI-powered insights using OpenAI API
     */
    private function getAIInsights(array $analysisResults): ?array
    {
        // Check for OpenAI API key
        $apiKey = getenv('OPENAI_API_KEY');
        if (empty($apiKey)) {
            Logger::warning('OpenAI API key not configured');
            return [
                'error' => 'AI analysis not available - API key not configured',
                'summary' => null,
                'root_cause' => null,
                'recommendations' => []
            ];
        }

        try {
            // Prepare summary of findings for AI
            $summary = $this->prepareSummaryForAI($analysisResults);

            // Call OpenAI API
            $prompt = $this->buildAIPrompt($summary);

            $response = $this->callOpenAI($apiKey, $prompt);

            if ($response === null) {
                return [
                    'error' => 'Failed to get AI insights',
                    'summary' => null,
                    'root_cause' => null,
                    'recommendations' => []
                ];
            }

            return [
                'summary' => $response['summary'] ?? null,
                'root_cause' => $response['root_cause'] ?? null,
                'recommendations' => $response['recommendations'] ?? [],
                'troubleshooting_steps' => $response['troubleshooting_steps'] ?? [],
                'severity' => $response['severity'] ?? 'info'
            ];

        } catch (\Exception $e) {
            Logger::error('AI insights error', ['error' => $e->getMessage()]);
            return [
                'error' => 'AI analysis failed: ' . $e->getMessage(),
                'summary' => null,
                'root_cause' => null,
                'recommendations' => []
            ];
        }
    }

    /**
     * Prepare summary for AI analysis
     */
    private function prepareSummaryForAI(array $results): string
    {
        $summary = "PCAP Analysis Results:\n\n";

        // Metadata
        if (isset($results['metadata'])) {
            $meta = $results['metadata'];
            $summary .= "=== Capture Metadata ===\n";
            $summary .= "Total Packets: " . ($meta['total_packets'] ?? 0) . "\n";
            $summary .= "Duration: " . round($meta['capture_duration'] ?? 0, 2) . " seconds\n";
            $summary .= "Packets/second: " . round($meta['packets_per_second'] ?? 0, 2) . "\n\n";
        }

        // DNS Analysis
        if (isset($results['dns_analysis'])) {
            $dns = $results['dns_analysis'];
            $summary .= "=== DNS Analysis ===\n";
            $summary .= "Total Queries: " . ($dns['total_queries'] ?? 0) . "\n";
            $summary .= "Failed Queries: " . ($dns['failed_query_count'] ?? 0) . "\n";
            $summary .= "Average Response Time: " . round($dns['average_response_time_ms'] ?? 0, 2) . " ms\n";
            $summary .= "Queries Without Response: " . ($dns['queries_without_response'] ?? 0) . "\n";

            if (!empty($dns['failed_queries'])) {
                $summary .= "\nFailed Queries:\n";
                foreach (array_slice($dns['failed_queries'], 0, 5) as $fail) {
                    $summary .= "  - {$fail['query']} ({$fail['query_type']}): {$fail['error_name']}\n";
                }
            }
            $summary .= "\n";
        }

        // Security Analysis
        if (isset($results['security_analysis'])) {
            $sec = $results['security_analysis'];
            $summary .= "=== Security Analysis ===\n";
            $summary .= "Port Scans Detected: " . ($sec['port_scan_count'] ?? 0) . "\n";
            $summary .= "DDoS Suspects: " . ($sec['ddos_suspect_count'] ?? 0) . "\n";
            $summary .= "ACL/Firewall Blocks: " . ($sec['acl_block_count'] ?? 0) . "\n";
            $summary .= "SYN Floods: " . ($sec['syn_flood_count'] ?? 0) . "\n";
            $summary .= "TCP RST Count: " . ($sec['tcp_rst_count'] ?? 0) . "\n\n";
        }

        // Routing Analysis
        if (isset($results['routing_analysis'])) {
            $route = $results['routing_analysis'];
            $summary .= "=== Routing Analysis ===\n";
            $summary .= "OSPF Packets: " . ($route['ospf_packets_detected'] ?? 0) . "\n";
            $summary .= "BGP Packets: " . ($route['bgp_packets_detected'] ?? 0) . "\n";
            $summary .= "Asymmetric Routing: " . ($route['asymmetric_routing_detected'] ? 'Yes' : 'No') . "\n\n";
        }

        // ICMP Analysis
        if (isset($results['icmp_analysis'])) {
            $icmp = $results['icmp_analysis'];
            $summary .= "=== ICMP Analysis ===\n";
            $summary .= "Ping Requests: " . ($icmp['ping_requests'] ?? 0) . "\n";
            $summary .= "Ping Replies: " . ($icmp['ping_replies'] ?? 0) . "\n";
            $summary .= "Unreachable Count: " . ($icmp['unreachable_count'] ?? 0) . "\n";
            $summary .= "Average Latency: " . round($icmp['average_ping_latency_ms'] ?? 0, 2) . " ms\n\n";
        }

        return $summary;
    }

    /**
     * Build AI prompt for network analysis
     */
    private function buildAIPrompt(string $summary): string
    {
        return <<<PROMPT
You are an expert network engineer analyzing a PCAP capture. Based on the following analysis results, provide:

1. A brief summary of the network health and key findings
2. Root cause analysis for any issues detected
3. Specific remediation recommendations
4. Step-by-step troubleshooting guide

Analysis Results:
{$summary}

Please respond in JSON format with the following structure:
{
  "summary": "Brief summary of findings",
  "root_cause": "Root cause analysis",
  "recommendations": ["rec1", "rec2", "rec3"],
  "troubleshooting_steps": ["step1", "step2", "step3"],
  "severity": "low|medium|high|critical"
}

Focus on DNS issues, routing problems, security threats, and performance bottlenecks. Be specific and actionable.
PROMPT;
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI(string $apiKey, string $prompt): ?array
    {
        $url = 'https://api.openai.com/v1/chat/completions';

        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert network engineer specializing in DNS, routing protocols, and network security.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1500,
            'response_format' => ['type' => 'json_object']
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Logger::error('OpenAI API error', [
                'http_code' => $httpCode,
                'response' => $response
            ]);
            return null;
        }

        $result = json_decode($response, true);

        if (!isset($result['choices'][0]['message']['content'])) {
            return null;
        }

        $content = $result['choices'][0]['message']['content'];
        $parsed = json_decode($content, true);

        return $parsed ?? null;
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];

        return $errors[$errorCode] ?? 'Unknown upload error';
    }
}
