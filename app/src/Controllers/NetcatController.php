<?php

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\RateLimiter;
use VeriBits\Utils\Validator;

class NetcatController extends BaseController
{
    private const MAX_WAIT_TIME = 30;
    private const MAX_TIMEOUT = 60;
    private const MAX_RESPONSE_SIZE = 1048576; // 1MB

    /**
     * Execute netcat connection
     * POST /api/v1/tools/netcat
     */
    public function execute(): void
    {
        // Rate limiting
        if (!RateLimiter::check('netcat', 50, 3600)) {
            Response::error('Rate limit exceeded. Please try again later.', 429);
            return;
        }

        // Get input
        $input = $this->getJsonInput();

        // Validate required fields
        if (!isset($input['host']) || !isset($input['port'])) {
            Response::error('Host and port are required', 400);
            return;
        }

        $host = trim($input['host']);
        $port = (int) $input['port'];
        $protocol = strtolower($input['protocol'] ?? 'tcp');
        $data = $input['data'] ?? null;
        $timeout = min((int) ($input['timeout'] ?? 5), self::MAX_TIMEOUT);
        $waitTime = min((int) ($input['wait_time'] ?? 2), self::MAX_WAIT_TIME);
        $verbose = (bool) ($input['verbose'] ?? false);
        $zeroIO = (bool) ($input['zero_io'] ?? false);
        $sourcePort = isset($input['source_port']) ? (int) $input['source_port'] : null;

        // Validate inputs
        if (!$this->validateHost($host)) {
            Response::error('Invalid host', 400);
            return;
        }

        if ($port < 1 || $port > 65535) {
            Response::error('Port must be between 1 and 65535', 400);
            return;
        }

        if (!in_array($protocol, ['tcp', 'udp'])) {
            Response::error('Protocol must be tcp or udp', 400);
            return;
        }

        // Block potentially dangerous operations
        if ($this->isBlockedHost($host)) {
            Response::error('Access to this host is not allowed', 403);
            return;
        }

        // Execute connection
        try {
            $result = $this->connect($host, $port, $protocol, $data, $timeout, $waitTime, $verbose, $zeroIO, $sourcePort);
            Response::success($result);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Connect to remote host
     */
    private function connect(
        string $host,
        int $port,
        string $protocol,
        ?string $data,
        int $timeout,
        int $waitTime,
        bool $verbose,
        bool $zeroIO,
        ?int $sourcePort
    ): array {
        $startTime = microtime(true);
        $result = [
            'host' => $host,
            'port' => $port,
            'protocol' => $protocol,
            'connected' => false,
            'response' => null,
            'banner' => null,
            'bytes_received' => 0,
            'connection_time' => null,
            'service_name' => null,
            'service_description' => null,
            'error' => null,
            'verbose_output' => null
        ];

        $verboseLog = [];

        try {
            // Resolve hostname if needed
            $ip = gethostbyname($host);
            if ($verbose) {
                $verboseLog[] = "Resolving $host...";
                if ($ip !== $host) {
                    $verboseLog[] = "Resolved to $ip";
                }
            }

            // Create connection string
            if ($protocol === 'tcp') {
                $target = "tcp://$ip:$port";
            } else {
                $target = "udp://$ip:$port";
            }

            if ($verbose) {
                $verboseLog[] = "Connecting to $target (timeout: {$timeout}s)...";
            }

            // Create socket context
            $context = stream_context_create();
            if ($sourcePort) {
                stream_context_set_option($context, 'socket', 'bindto', "0:$sourcePort");
                if ($verbose) {
                    $verboseLog[] = "Using source port: $sourcePort";
                }
            }

            // Connect
            $errno = 0;
            $errstr = '';
            $socket = @stream_socket_client(
                $target,
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                $result['error'] = "Connection failed: $errstr ($errno)";
                if ($verbose) {
                    $verboseLog[] = "Connection failed: $errstr";
                    $result['verbose_output'] = implode("\n", $verboseLog);
                }
                return $result;
            }

            $connectionTime = round((microtime(true) - $startTime) * 1000, 2);
            $result['connected'] = true;
            $result['connection_time'] = $connectionTime;

            if ($verbose) {
                $verboseLog[] = "Connected successfully in {$connectionTime}ms";
            }

            // If zero I/O mode, just close and return
            if ($zeroIO) {
                if ($verbose) {
                    $verboseLog[] = "Zero I/O mode: closing connection";
                }
                fclose($socket);
                $result['verbose_output'] = implode("\n", $verboseLog);
                return $result;
            }

            // Set read timeout
            stream_set_timeout($socket, $waitTime);

            // Send data if provided
            if ($data) {
                $bytesWritten = fwrite($socket, $data);
                if ($verbose) {
                    $verboseLog[] = "Sent $bytesWritten bytes";
                }
            }

            // Read response
            $response = '';
            $banner = '';
            $firstRead = true;

            while (!feof($socket)) {
                $chunk = fread($socket, 8192);
                if ($chunk === false || $chunk === '') {
                    break;
                }

                $response .= $chunk;

                // Capture banner (first line/chunk)
                if ($firstRead) {
                    $banner = trim(strtok($chunk, "\n"));
                    $firstRead = false;
                }

                // Check response size limit
                if (strlen($response) > self::MAX_RESPONSE_SIZE) {
                    $response .= "\n[Output truncated - max size reached]";
                    break;
                }

                // Check for timeout
                $info = stream_get_meta_data($socket);
                if ($info['timed_out']) {
                    if ($verbose) {
                        $verboseLog[] = "Read timeout after {$waitTime}s";
                    }
                    break;
                }
            }

            $result['response'] = $response;
            $result['bytes_received'] = strlen($response);
            $result['banner'] = $banner;

            if ($verbose) {
                $verboseLog[] = "Received {$result['bytes_received']} bytes";
            }

            // Identify service
            $serviceInfo = $this->identifyService($port, $banner);
            if ($serviceInfo) {
                $result['service_name'] = $serviceInfo['name'];
                $result['service_description'] = $serviceInfo['description'];
                if ($verbose) {
                    $verboseLog[] = "Identified service: {$serviceInfo['name']}";
                }
            }

            fclose($socket);

            if ($verbose) {
                $verboseLog[] = "Connection closed";
                $result['verbose_output'] = implode("\n", $verboseLog);
            }

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            if ($verbose) {
                $verboseLog[] = "Error: " . $e->getMessage();
                $result['verbose_output'] = implode("\n", $verboseLog);
            }
        }

        return $result;
    }

    /**
     * Identify service based on port and banner
     */
    private function identifyService(int $port, ?string $banner): ?array
    {
        // Common port-to-service mappings
        $portMap = [
            20 => ['name' => 'FTP-DATA', 'description' => 'File Transfer Protocol (Data)'],
            21 => ['name' => 'FTP', 'description' => 'File Transfer Protocol'],
            22 => ['name' => 'SSH', 'description' => 'Secure Shell'],
            23 => ['name' => 'TELNET', 'description' => 'Telnet'],
            25 => ['name' => 'SMTP', 'description' => 'Simple Mail Transfer Protocol'],
            53 => ['name' => 'DNS', 'description' => 'Domain Name System'],
            80 => ['name' => 'HTTP', 'description' => 'Hypertext Transfer Protocol'],
            110 => ['name' => 'POP3', 'description' => 'Post Office Protocol v3'],
            143 => ['name' => 'IMAP', 'description' => 'Internet Message Access Protocol'],
            443 => ['name' => 'HTTPS', 'description' => 'HTTP over TLS/SSL'],
            465 => ['name' => 'SMTPS', 'description' => 'SMTP over TLS/SSL'],
            587 => ['name' => 'SMTP', 'description' => 'SMTP (Submission)'],
            993 => ['name' => 'IMAPS', 'description' => 'IMAP over TLS/SSL'],
            995 => ['name' => 'POP3S', 'description' => 'POP3 over TLS/SSL'],
            3306 => ['name' => 'MySQL', 'description' => 'MySQL Database'],
            5432 => ['name' => 'PostgreSQL', 'description' => 'PostgreSQL Database'],
            6379 => ['name' => 'Redis', 'description' => 'Redis Key-Value Store'],
            8080 => ['name' => 'HTTP-ALT', 'description' => 'HTTP Alternate'],
            27017 => ['name' => 'MongoDB', 'description' => 'MongoDB Database'],
        ];

        $service = $portMap[$port] ?? null;

        // Try to identify from banner if service not found by port
        if ($banner) {
            if (stripos($banner, 'SSH') !== false) {
                $service = ['name' => 'SSH', 'description' => 'Secure Shell - ' . $banner];
            } elseif (stripos($banner, 'SMTP') !== false) {
                $service = ['name' => 'SMTP', 'description' => 'Simple Mail Transfer Protocol - ' . $banner];
            } elseif (stripos($banner, 'FTP') !== false) {
                $service = ['name' => 'FTP', 'description' => 'File Transfer Protocol - ' . $banner];
            } elseif (stripos($banner, 'HTTP') !== false) {
                $service = ['name' => 'HTTP', 'description' => 'Hypertext Transfer Protocol'];
            } elseif (stripos($banner, 'MySQL') !== false) {
                $service = ['name' => 'MySQL', 'description' => 'MySQL Database'];
            } elseif (stripos($banner, 'Redis') !== false) {
                $service = ['name' => 'Redis', 'description' => 'Redis Key-Value Store'];
            }
        }

        return $service;
    }

    /**
     * Validate host
     */
    private function validateHost(string $host): bool
    {
        // Check if valid IP or hostname
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Validate hostname
        if (preg_match('/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $host)) {
            return true;
        }

        // Allow localhost
        if ($host === 'localhost') {
            return true;
        }

        return false;
    }

    /**
     * Check if host is blocked
     */
    private function isBlockedHost(string $host): bool
    {
        $ip = gethostbyname($host);

        // Block internal/private IPs (except localhost for testing)
        if ($host !== 'localhost' && $host !== '127.0.0.1') {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                // Check for private IP ranges
                $parts = explode('.', $ip);
                $first = (int) $parts[0];
                $second = (int) $parts[1];

                // 10.0.0.0/8
                if ($first === 10) {
                    return true;
                }

                // 172.16.0.0/12
                if ($first === 172 && $second >= 16 && $second <= 31) {
                    return true;
                }

                // 192.168.0.0/16
                if ($first === 192 && $second === 168) {
                    return true;
                }

                // 127.0.0.0/8 (loopback)
                if ($first === 127) {
                    return true;
                }

                // 169.254.0.0/16 (link-local)
                if ($first === 169 && $second === 254) {
                    return true;
                }
            }
        }

        return false;
    }
}
