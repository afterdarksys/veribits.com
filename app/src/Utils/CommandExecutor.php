<?php
declare(strict_types=1);

namespace VeriBits\Utils;

/**
 * Safe command execution utility with built-in sanitization
 * Prevents command injection attacks
 */
class CommandExecutor {
    // Whitelist of allowed commands
    private static array $allowedCommands = [
        'ping', 'traceroute', 'dig', 'nslookup', 'whois', 'host',
        'openssl', 'file', 'clamscan', 'unzip', 'tar', 'gpg',
        'nmap', 'curl', 'wget', 'git', 'ssh-keygen', 'keytool'
    ];

    // Maximum execution time in seconds
    private const MAX_EXECUTION_TIME = 60;

    // Maximum output size in bytes (1MB)
    private const MAX_OUTPUT_SIZE = 1048576;

    /**
     * Execute a command safely with argument validation
     *
     * @param string $command Base command (must be in whitelist)
     * @param array $args Command arguments (will be escaped)
     * @param string|null $stdin Optional stdin data to pass to command
     * @param int $timeout Timeout in seconds (max 60)
     * @return array ['stdout' => string, 'stderr' => string, 'exit_code' => int, 'output' => string, 'error' => string|null, 'execution_time' => float]
     * @throws \InvalidArgumentException if command is not allowed
     */
    public static function execute(string $command, array $args = [], ?string $stdin = null, int $timeout = 30): array {
        // Validate command is in whitelist
        if (!in_array($command, self::$allowedCommands, true)) {
            Logger::security('Attempted to execute non-whitelisted command', [
                'command' => $command,
                'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
            ]);
            throw new \InvalidArgumentException("Command not allowed: $command");
        }

        // Validate timeout
        if ($timeout < 1 || $timeout > self::MAX_EXECUTION_TIME) {
            throw new \InvalidArgumentException("Timeout must be between 1 and " . self::MAX_EXECUTION_TIME . " seconds");
        }

        // Escape all arguments
        $escapedArgs = array_map('escapeshellarg', $args);

        // Build full command
        $fullCommand = $command;
        if (!empty($escapedArgs)) {
            $fullCommand .= ' ' . implode(' ', $escapedArgs);
        }

        Logger::debug('Executing command', [
            'command' => $command,
            'args_count' => count($args),
            'timeout' => $timeout,
            'has_stdin' => $stdin !== null
        ]);

        $startTime = microtime(true);

        // Use proc_open for better stdin/stdout/stderr handling
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        $process = proc_open($fullCommand, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException("Failed to execute command: $command");
        }

        // Write stdin if provided
        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);

        // Set timeout for reading
        stream_set_timeout($pipes[1], $timeout);
        stream_set_timeout($pipes[2], $timeout);

        // Read stdout and stderr
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        // Get exit code
        $exitCode = proc_close($process);

        $executionTime = microtime(true) - $startTime;

        // Enforce output size limits
        if (strlen($stdout) > self::MAX_OUTPUT_SIZE) {
            Logger::warning('Command stdout exceeded size limit', [
                'command' => $command,
                'output_size' => strlen($stdout),
                'limit' => self::MAX_OUTPUT_SIZE
            ]);
            $stdout = substr($stdout, 0, self::MAX_OUTPUT_SIZE) . "\n[OUTPUT TRUNCATED]";
        }
        if (strlen($stderr) > self::MAX_OUTPUT_SIZE) {
            Logger::warning('Command stderr exceeded size limit', [
                'command' => $command,
                'error_size' => strlen($stderr),
                'limit' => self::MAX_OUTPUT_SIZE
            ]);
            $stderr = substr($stderr, 0, self::MAX_OUTPUT_SIZE) . "\n[OUTPUT TRUNCATED]";
        }

        Logger::debug('Command executed', [
            'command' => $command,
            'exit_code' => $exitCode,
            'execution_time' => round($executionTime, 3),
            'stdout_size' => strlen($stdout),
            'stderr_size' => strlen($stderr)
        ]);

        // Return format compatible with both old and new usage patterns
        $outputString = !empty($stdout) ? $stdout : $stderr;

        return [
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exit_code' => $exitCode,
            'output' => $outputString,  // Backwards compatibility
            'error' => $exitCode !== 0 ? $stderr : null,
            'execution_time' => $executionTime
        ];
    }

    /**
     * Validate an IP address (IPv4 or IPv6)
     */
    public static function validateIpAddress(string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Validate a domain name
     */
    public static function validateDomain(string $domain): bool {
        // Remove protocol if present
        $domain = preg_replace('#^https?://#i', '', $domain);

        // Remove path if present
        $domain = explode('/', $domain)[0];

        // Validate domain format
        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain) === 1;
    }

    /**
     * Validate a hostname (domain or IP)
     */
    public static function validateHostname(string $hostname): bool {
        return self::validateDomain($hostname) || self::validateIpAddress($hostname);
    }

    /**
     * Sanitize a file path to prevent directory traversal
     */
    public static function sanitizeFilePath(string $path, string $allowedDir): string {
        // Resolve to absolute path
        $realPath = realpath($path);
        $realAllowedDir = realpath($allowedDir);

        if ($realPath === false) {
            throw new \InvalidArgumentException("Invalid file path: $path");
        }

        if ($realAllowedDir === false) {
            throw new \InvalidArgumentException("Invalid allowed directory: $allowedDir");
        }

        // Check if path is within allowed directory
        if (strpos($realPath, $realAllowedDir) !== 0) {
            Logger::security('Directory traversal attempt detected', [
                'attempted_path' => $path,
                'real_path' => $realPath,
                'allowed_dir' => $realAllowedDir
            ]);
            throw new \InvalidArgumentException("Path outside allowed directory");
        }

        return $realPath;
    }

    /**
     * Execute a network command (ping, traceroute, dig, etc.)
     * Automatically validates the target hostname
     */
    public static function executeNetworkCommand(string $command, string $target, array $extraArgs = [], int $timeout = 30): array {
        if (!self::validateHostname($target)) {
            throw new \InvalidArgumentException("Invalid hostname or IP address: $target");
        }

        $args = array_merge([$target], $extraArgs);
        return self::execute($command, $args, $timeout);
    }

    /**
     * Execute OpenSSL command safely
     */
    public static function executeOpenSSL(array $args, int $timeout = 30): array {
        return self::execute('openssl', $args, $timeout);
    }

    /**
     * Execute file inspection command
     */
    public static function executeFileCommand(string $filePath, int $timeout = 10): array {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File does not exist: $filePath");
        }

        return self::execute('file', ['-b', '--mime-type', $filePath], $timeout);
    }

    /**
     * Check if a command is available on the system
     */
    public static function isCommandAvailable(string $command): bool {
        if (!in_array($command, self::$allowedCommands, true)) {
            return false;
        }

        $result = shell_exec(sprintf('command -v %s', escapeshellarg($command)));
        return !empty($result);
    }

    /**
     * Get list of allowed commands
     */
    public static function getAllowedCommands(): array {
        return self::$allowedCommands;
    }

    /**
     * Add a command to the whitelist (use with caution)
     */
    public static function addAllowedCommand(string $command): void {
        if (!in_array($command, self::$allowedCommands, true)) {
            self::$allowedCommands[] = $command;
            Logger::info('Command added to whitelist', ['command' => $command]);
        }
    }
}
