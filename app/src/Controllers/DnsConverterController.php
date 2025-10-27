<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;

class DnsConverterController
{
    /**
     * Convert djbdns/dnscache configuration to Unbound format
     */
    public function convertDnscache(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        if (!isset($_FILES['archive'])) {
            Response::error('No archive file uploaded', 400);
            return;
        }

        $file = $_FILES['archive'];
        $convertTinydns = isset($_POST['convert_tinydns']) && $_POST['convert_tinydns'] === 'true';

        try {
            $tmpDir = sys_get_temp_dir() . '/dnscache_' . uniqid();
            mkdir($tmpDir, 0700, true);

            // Extract archive
            $archivePath = $file['tmp_name'];
            $this->extractArchive($archivePath, $tmpDir);

            // Parse dnscache configuration
            $config = $this->parseDnscacheConfig($tmpDir);

            // Generate Unbound configuration
            $unboundConf = $this->generateUnboundConf($config);

            // Also convert tinydns data if requested
            $nsdConfig = null;
            $zoneFiles = [];

            if ($convertTinydns) {
                $tinydnsDataPath = $this->findTinydnsData($tmpDir);
                if ($tinydnsDataPath) {
                    $tinydnsData = $this->parseTinydnsData($tinydnsDataPath);
                    $nsdConfig = $this->generateNsdConf($tinydnsData);
                    $zoneFiles = $tinydnsData['zones'];
                }
            }

            // Cleanup
            $this->recursiveDelete($tmpDir);

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('DNS configuration converted successfully', [
                'unbound_conf' => $unboundConf,
                'nsd_conf' => $nsdConfig,
                'zone_files' => $zoneFiles,
                'config_details' => [
                    'upstream_servers' => $config['forward_zones'],
                    'client_ips' => $config['access_control'],
                    'cache_size' => $config['cache_size'] ?? '50m',
                    'num_threads' => $config['num_threads'] ?? 2
                ]
            ]);

        } catch (\Exception $e) {
            Logger::error('DNS conversion failed', ['error' => $e->getMessage()]);
            Response::error('Conversion failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Convert BIND configuration to NSD format
     */
    public function convertBind(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        if (!isset($_FILES['archive'])) {
            Response::error('No archive file uploaded', 400);
            return;
        }

        $file = $_FILES['archive'];

        try {
            $tmpDir = sys_get_temp_dir() . '/bind_' . uniqid();
            mkdir($tmpDir, 0700, true);

            // Extract archive
            $archivePath = $file['tmp_name'];
            $this->extractArchive($archivePath, $tmpDir);

            // Parse BIND configuration
            $bindConfig = $this->parseBindConfig($tmpDir);

            // Generate NSD configuration
            $nsdConf = $this->generateNsdConfFromBind($bindConfig);

            // Validate and convert zone files
            $convertedZones = $this->convertZoneFiles($bindConfig['zones'], $tmpDir);

            // Cleanup
            $this->recursiveDelete($tmpDir);

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('BIND configuration converted to NSD successfully', [
                'nsd_conf' => $nsdConf,
                'zones' => $convertedZones,
                'config_details' => [
                    'total_zones' => count($bindConfig['zones']),
                    'master_zones' => count(array_filter($bindConfig['zones'], fn($z) => $z['type'] === 'master')),
                    'slave_zones' => count(array_filter($bindConfig['zones'], fn($z) => $z['type'] === 'slave')),
                    'tsig_keys' => count($bindConfig['tsig_keys'] ?? [])
                ]
            ]);

        } catch (\Exception $e) {
            Logger::error('BIND conversion failed', ['error' => $e->getMessage()]);
            Response::error('Conversion failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Extract tar.gz or zip archive
     */
    private function extractArchive(string $archivePath, string $destDir): void
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $archivePath);
        finfo_close($finfo);

        if (strpos($mimeType, 'gzip') !== false || strpos($mimeType, 'tar') !== false) {
            // Extract tar.gz
            exec(sprintf(
                'tar -xzf %s -C %s 2>&1',
                escapeshellarg($archivePath),
                escapeshellarg($destDir)
            ), $output, $returnVar);

            if ($returnVar !== 0) {
                throw new \Exception('Failed to extract tar.gz archive: ' . implode("\n", $output));
            }
        } elseif (strpos($mimeType, 'zip') !== false) {
            // Extract zip
            $zip = new \ZipArchive();
            if ($zip->open($archivePath) === true) {
                $zip->extractTo($destDir);
                $zip->close();
            } else {
                throw new \Exception('Failed to open zip archive');
            }
        } else {
            throw new \Exception('Unsupported archive format. Please upload .tar.gz or .zip');
        }
    }

    /**
     * Parse djbdns/dnscache directory structure
     */
    private function parseDnscacheConfig(string $dir): array
    {
        $config = [
            'forward_zones' => [],
            'access_control' => [],
            'cache_size' => '50m',
            'num_threads' => 2
        ];

        // Look for dnscache directory
        $dnscacheDir = $this->findDirectory($dir, ['dnscache', 'service/dnscache', 'var/dnscache']);

        if (!$dnscacheDir) {
            throw new \Exception('Could not find dnscache directory in archive');
        }

        // Parse root/servers/* - upstream DNS servers
        $serversDir = $dnscacheDir . '/root/servers';
        if (is_dir($serversDir)) {
            $files = glob($serversDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $zone = basename($file);
                    $servers = array_filter(array_map('trim', file($file)));

                    if ($zone === '@') {
                        // Root servers - use for forward-zone: "."
                        $config['forward_zones']['.'] = $servers;
                    } else {
                        $config['forward_zones'][$zone] = $servers;
                    }
                }
            }
        }

        // Parse root/ip/* - allowed client IPs
        $ipDir = $dnscacheDir . '/root/ip';
        if (is_dir($ipDir)) {
            $files = glob($ipDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $ip = basename($file);
                    $config['access_control'][] = $ip;
                }
            }
        }

        // Parse env/ variables
        $envDir = $dnscacheDir . '/env';
        if (is_dir($envDir)) {
            // CACHESIZE
            if (file_exists($envDir . '/CACHESIZE')) {
                $cacheSize = (int)trim(file_get_contents($envDir . '/CACHESIZE'));
                // Convert bytes to MB for Unbound
                $config['cache_size'] = max(1, floor($cacheSize / 1048576)) . 'm';
            }
        }

        return $config;
    }

    /**
     * Find tinydns data file
     */
    private function findTinydnsData(string $dir): ?string
    {
        $possiblePaths = [
            $dir . '/tinydns/root/data',
            $dir . '/service/tinydns/root/data',
            $dir . '/var/tinydns/root/data',
            $dir . '/data'
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Search recursively
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'data') {
                return $file->getPathname();
            }
        }

        return null;
    }

    /**
     * Parse tinydns data file
     */
    public function parseTinydnsData(string $dataFile): array
    {
        $zones = [];
        $currentZone = null;
        $zoneRecords = [];

        $lines = file($dataFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            $type = $line[0];
            $parts = explode(':', substr($line, 1));

            switch ($type) {
                case '.': // NS + A records (SOA implied)
                case '&': // NS + A records
                    $domain = $parts[0] ?? '';
                    $ip = $parts[1] ?? '';
                    $ttl = $parts[3] ?? '3600';

                    if ($domain !== $currentZone) {
                        if ($currentZone !== null) {
                            $zones[$currentZone] = $this->generateZoneFile($currentZone, $zoneRecords);
                        }
                        $currentZone = $domain;
                        $zoneRecords = [];

                        // Add SOA record for new zone
                        $zoneRecords[] = [
                            'type' => 'SOA',
                            'name' => '@',
                            'mname' => 'ns1.' . $domain . '.',
                            'rname' => 'hostmaster.' . $domain . '.',
                            'serial' => date('Ymd') . '01',
                            'refresh' => 3600,
                            'retry' => 900,
                            'expire' => 604800,
                            'minimum' => 86400
                        ];
                    }

                    if ($ip) {
                        $zoneRecords[] = [
                            'type' => 'NS',
                            'name' => '@',
                            'value' => 'ns1.' . $domain . '.',
                            'ttl' => $ttl
                        ];
                        $zoneRecords[] = [
                            'type' => 'A',
                            'name' => 'ns1',
                            'value' => $ip,
                            'ttl' => $ttl
                        ];
                    }
                    break;

                case '+': // A record
                case '=': // A + PTR records
                    $name = $parts[0] ?? '';
                    $ip = $parts[1] ?? '';
                    $ttl = $parts[2] ?? '3600';

                    if ($name && $ip) {
                        // Extract zone from FQDN
                        $zoneName = $this->extractZone($name, $currentZone);
                        $recordName = $this->getRecordName($name, $zoneName);

                        if ($zoneName !== $currentZone) {
                            if ($currentZone !== null) {
                                $zones[$currentZone] = $this->generateZoneFile($currentZone, $zoneRecords);
                            }
                            $currentZone = $zoneName;
                            $zoneRecords = [];
                        }

                        $zoneRecords[] = [
                            'type' => 'A',
                            'name' => $recordName,
                            'value' => $ip,
                            'ttl' => $ttl
                        ];
                    }
                    break;

                case '@': // MX record
                    $domain = $parts[0] ?? '';
                    $mx = $parts[1] ?? '';
                    $priority = $parts[3] ?? '10';
                    $ttl = $parts[4] ?? '3600';

                    if ($domain && $mx) {
                        $zoneName = $this->extractZone($domain, $currentZone);
                        $recordName = $this->getRecordName($domain, $zoneName);

                        if ($zoneName !== $currentZone) {
                            if ($currentZone !== null) {
                                $zones[$currentZone] = $this->generateZoneFile($currentZone, $zoneRecords);
                            }
                            $currentZone = $zoneName;
                            $zoneRecords = [];
                        }

                        $zoneRecords[] = [
                            'type' => 'MX',
                            'name' => $recordName,
                            'value' => $mx,
                            'priority' => $priority,
                            'ttl' => $ttl
                        ];
                    }
                    break;

                case '\'': // TXT record
                case '^': // PTR record
                case 'C': // CNAME record
                    // Additional record types can be implemented here
                    break;
            }
        }

        // Add last zone
        if ($currentZone !== null) {
            $zones[$currentZone] = $this->generateZoneFile($currentZone, $zoneRecords);
        }

        return ['zones' => $zones];
    }

    /**
     * Generate Unbound configuration
     */
    public function generateUnboundConf(array $config): string
    {
        $conf = "# Generated by VeriBits DNS Converter\n";
        $conf .= "# From djbdns/dnscache configuration\n";
        $conf .= "# " . date('Y-m-d H:i:s') . "\n\n";

        $conf .= "server:\n";
        $conf .= "    # Network settings\n";
        $conf .= "    interface: 0.0.0.0\n";
        $conf .= "    interface: ::\n";
        $conf .= "    port: 53\n";
        $conf .= "    do-ip4: yes\n";
        $conf .= "    do-ip6: yes\n";
        $conf .= "    do-udp: yes\n";
        $conf .= "    do-tcp: yes\n\n";

        $conf .= "    # Access control\n";
        if (!empty($config['access_control'])) {
            foreach ($config['access_control'] as $ip) {
                $conf .= "    access-control: {$ip} allow\n";
            }
        } else {
            $conf .= "    access-control: 127.0.0.0/8 allow\n";
            $conf .= "    access-control: ::1 allow\n";
        }
        $conf .= "    access-control: 0.0.0.0/0 refuse\n";
        $conf .= "    access-control: ::/0 refuse\n\n";

        $conf .= "    # Performance settings\n";
        $conf .= "    num-threads: " . ($config['num_threads'] ?? 2) . "\n";
        $conf .= "    msg-cache-size: " . ($config['cache_size'] ?? '50m') . "\n";
        $conf .= "    rrset-cache-size: " . ($config['cache_size'] ?? '50m') . "\n";
        $conf .= "    cache-min-ttl: 3600\n";
        $conf .= "    cache-max-ttl: 86400\n\n";

        $conf .= "    # Security settings\n";
        $conf .= "    hide-identity: yes\n";
        $conf .= "    hide-version: yes\n";
        $conf .= "    harden-glue: yes\n";
        $conf .= "    harden-dnssec-stripped: yes\n";
        $conf .= "    use-caps-for-id: yes\n";
        $conf .= "    private-address: 10.0.0.0/8\n";
        $conf .= "    private-address: 172.16.0.0/12\n";
        $conf .= "    private-address: 192.168.0.0/16\n";
        $conf .= "    private-address: 169.254.0.0/16\n";
        $conf .= "    private-address: fd00::/8\n";
        $conf .= "    private-address: fe80::/10\n\n";

        $conf .= "    # DNSSEC validation\n";
        $conf .= "    auto-trust-anchor-file: \"/var/lib/unbound/root.key\"\n";
        $conf .= "    val-clean-additional: yes\n\n";

        $conf .= "    # Logging\n";
        $conf .= "    verbosity: 1\n";
        $conf .= "    log-queries: no\n";
        $conf .= "    log-replies: no\n\n";

        // Forward zones
        if (!empty($config['forward_zones'])) {
            foreach ($config['forward_zones'] as $zone => $servers) {
                $conf .= "forward-zone:\n";
                $conf .= "    name: \"{$zone}\"\n";
                foreach ($servers as $server) {
                    $conf .= "    forward-addr: {$server}\n";
                }
                $conf .= "\n";
            }
        }

        return $conf;
    }

    /**
     * Generate NSD configuration from tinydns data
     */
    public function generateNsdConf(array $tinydnsData): string
    {
        $conf = "# Generated by VeriBits DNS Converter\n";
        $conf .= "# From tinydns data file\n";
        $conf .= "# " . date('Y-m-d H:i:s') . "\n\n";

        $conf .= "server:\n";
        $conf .= "    server-count: 2\n";
        $conf .= "    ip-address: 0.0.0.0\n";
        $conf .= "    ip-address: ::\n";
        $conf .= "    port: 53\n";
        $conf .= "    do-ip4: yes\n";
        $conf .= "    do-ip6: yes\n";
        $conf .= "    hide-version: yes\n";
        $conf .= "    identity: \"\"\n";
        $conf .= "    zonesdir: \"/etc/nsd/zones\"\n\n";

        // Add zones
        foreach ($tinydnsData['zones'] as $zoneName => $zoneData) {
            $conf .= "zone:\n";
            $conf .= "    name: \"{$zoneName}\"\n";
            $conf .= "    zonefile: \"{$zoneName}.zone\"\n\n";
        }

        return $conf;
    }

    /**
     * Parse BIND configuration
     */
    private function parseBindConfig(string $dir): array
    {
        $config = [
            'zones' => [],
            'tsig_keys' => [],
            'acls' => [],
            'options' => []
        ];

        // Find named.conf
        $namedConf = $this->findFile($dir, ['named.conf', 'named.conf.local']);

        if (!$namedConf) {
            throw new \Exception('Could not find named.conf in archive');
        }

        $content = file_get_contents($namedConf);

        // Parse zones
        preg_match_all('/zone\s+"([^"]+)"\s+{([^}]+)}/s', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $zoneName = $match[1];
            $zoneBlock = $match[2];

            $zone = ['name' => $zoneName];

            // Parse zone type
            if (preg_match('/type\s+(\w+);/', $zoneBlock, $typeMatch)) {
                $zone['type'] = $typeMatch[1];
            }

            // Parse zone file
            if (preg_match('/file\s+"([^"]+)";/', $zoneBlock, $fileMatch)) {
                $zone['file'] = $fileMatch[1];
            }

            // Parse masters (for slave zones)
            if (preg_match('/masters\s+{([^}]+)};/', $zoneBlock, $mastersMatch)) {
                $masters = array_filter(array_map('trim', explode(';', $mastersMatch[1])));
                $zone['masters'] = $masters;
            }

            // Parse allow-transfer
            if (preg_match('/allow-transfer\s+{([^}]+)};/', $zoneBlock, $transferMatch)) {
                $transfers = array_filter(array_map('trim', explode(';', $transferMatch[1])));
                $zone['allow_transfer'] = $transfers;
            }

            $config['zones'][] = $zone;
        }

        // Parse TSIG keys
        preg_match_all('/key\s+"([^"]+)"\s+{([^}]+)}/s', $content, $keyMatches, PREG_SET_ORDER);

        foreach ($keyMatches as $match) {
            $keyName = $match[1];
            $keyBlock = $match[2];

            $key = ['name' => $keyName];

            if (preg_match('/algorithm\s+([^;]+);/', $keyBlock, $algoMatch)) {
                $key['algorithm'] = trim($algoMatch[1]);
            }

            if (preg_match('/secret\s+"([^"]+)";/', $keyBlock, $secretMatch)) {
                $key['secret'] = $secretMatch[1];
            }

            $config['tsig_keys'][] = $key;
        }

        return $config;
    }

    /**
     * Generate NSD configuration from BIND
     */
    private function generateNsdConfFromBind(array $bindConfig): string
    {
        $conf = "# Generated by VeriBits DNS Converter\n";
        $conf .= "# From BIND named.conf\n";
        $conf .= "# " . date('Y-m-d H:i:s') . "\n\n";

        $conf .= "server:\n";
        $conf .= "    server-count: 2\n";
        $conf .= "    ip-address: 0.0.0.0\n";
        $conf .= "    ip-address: ::\n";
        $conf .= "    port: 53\n";
        $conf .= "    do-ip4: yes\n";
        $conf .= "    do-ip6: yes\n";
        $conf .= "    hide-version: yes\n";
        $conf .= "    identity: \"\"\n";
        $conf .= "    zonesdir: \"/etc/nsd/zones\"\n";
        $conf .= "    logfile: \"/var/log/nsd.log\"\n";
        $conf .= "    pidfile: \"/var/run/nsd.pid\"\n\n";

        // Add TSIG keys
        foreach ($bindConfig['tsig_keys'] as $key) {
            $conf .= "key:\n";
            $conf .= "    name: \"{$key['name']}\"\n";
            $conf .= "    algorithm: {$key['algorithm']}\n";
            $conf .= "    secret: \"{$key['secret']}\"\n\n";
        }

        // Add zones
        foreach ($bindConfig['zones'] as $zone) {
            $conf .= "zone:\n";
            $conf .= "    name: \"{$zone['name']}\"\n";
            $conf .= "    zonefile: \"{$zone['name']}.zone\"\n";

            // Add notify for master zones
            if ($zone['type'] === 'master') {
                if (!empty($zone['allow_transfer'])) {
                    foreach ($zone['allow_transfer'] as $slave) {
                        $conf .= "    notify: {$slave} NOKEY\n";
                    }
                    $conf .= "    provide-xfr: 0.0.0.0/0 NOKEY\n";
                }
            }

            // Add request-xfr for slave zones
            if ($zone['type'] === 'slave' && !empty($zone['masters'])) {
                foreach ($zone['masters'] as $master) {
                    $conf .= "    request-xfr: {$master} NOKEY\n";
                }
            }

            $conf .= "\n";
        }

        return $conf;
    }

    /**
     * Convert zone files
     */
    private function convertZoneFiles(array $zones, string $baseDir): array
    {
        $converted = [];

        foreach ($zones as $zone) {
            if (empty($zone['file'])) {
                continue;
            }

            // Try to find the zone file
            $zonePath = $baseDir . '/' . $zone['file'];
            if (!file_exists($zonePath)) {
                // Try alternative locations
                $zonePath = $this->findFile($baseDir, [
                    $zone['file'],
                    'zones/' . $zone['file'],
                    'var/named/' . $zone['file']
                ]);
            }

            if ($zonePath && file_exists($zonePath)) {
                $content = file_get_contents($zonePath);

                // Basic validation and cleanup
                $content = $this->validateAndCleanZoneFile($content, $zone['name']);

                $converted[$zone['name']] = [
                    'content' => $content,
                    'type' => $zone['type'],
                    'original_file' => $zone['file']
                ];
            }
        }

        return $converted;
    }

    /**
     * Validate and clean zone file content
     */
    private function validateAndCleanZoneFile(string $content, string $zoneName): string
    {
        // Remove any BIND-specific directives that NSD doesn't support
        $content = preg_replace('/\$GENERATE.*$/m', '', $content);

        // Ensure proper formatting
        $lines = explode("\n", $content);
        $cleaned = [];

        foreach ($lines as $line) {
            $line = rtrim($line);
            if (!empty($line)) {
                $cleaned[] = $line;
            }
        }

        return implode("\n", $cleaned) . "\n";
    }

    /**
     * Generate zone file from records
     */
    private function generateZoneFile(string $zoneName, array $records): string
    {
        $zone = "; Zone file for {$zoneName}\n";
        $zone .= "; Generated by VeriBits DNS Converter\n";
        $zone .= "; " . date('Y-m-d H:i:s') . "\n\n";

        $zone .= "\$ORIGIN {$zoneName}.\n";
        $zone .= "\$TTL 3600\n\n";

        foreach ($records as $record) {
            switch ($record['type']) {
                case 'SOA':
                    $zone .= sprintf(
                        "@    IN    SOA    %s %s (\n" .
                        "                    %s    ; serial\n" .
                        "                    %d    ; refresh\n" .
                        "                    %d    ; retry\n" .
                        "                    %d    ; expire\n" .
                        "                    %d )  ; minimum\n\n",
                        $record['mname'],
                        $record['rname'],
                        $record['serial'],
                        $record['refresh'],
                        $record['retry'],
                        $record['expire'],
                        $record['minimum']
                    );
                    break;

                case 'NS':
                    $zone .= sprintf(
                        "%-20s IN    NS    %s\n",
                        $record['name'],
                        $record['value']
                    );
                    break;

                case 'A':
                    $zone .= sprintf(
                        "%-20s IN    A     %s\n",
                        $record['name'],
                        $record['value']
                    );
                    break;

                case 'MX':
                    $zone .= sprintf(
                        "%-20s IN    MX    %d %s\n",
                        $record['name'],
                        $record['priority'],
                        $record['value']
                    );
                    break;

                case 'TXT':
                    $zone .= sprintf(
                        "%-20s IN    TXT   \"%s\"\n",
                        $record['name'],
                        $record['value']
                    );
                    break;

                case 'CNAME':
                    $zone .= sprintf(
                        "%-20s IN    CNAME %s\n",
                        $record['name'],
                        $record['value']
                    );
                    break;
            }
        }

        return $zone;
    }

    /**
     * Helper: Find directory
     */
    private function findDirectory(string $baseDir, array $possibleNames): ?string
    {
        foreach ($possibleNames as $name) {
            $path = $baseDir . '/' . $name;
            if (is_dir($path)) {
                return $path;
            }
        }

        // Search recursively
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                foreach ($possibleNames as $name) {
                    if (strpos($file->getPathname(), $name) !== false) {
                        return $file->getPathname();
                    }
                }
            }
        }

        return null;
    }

    /**
     * Helper: Find file
     */
    private function findFile(string $baseDir, array $possibleNames): ?string
    {
        foreach ($possibleNames as $name) {
            $path = $baseDir . '/' . $name;
            if (file_exists($path)) {
                return $path;
            }
        }

        // Search recursively
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getFilename(), $possibleNames)) {
                return $file->getPathname();
            }
        }

        return null;
    }

    /**
     * Helper: Extract zone name from FQDN
     */
    private function extractZone(string $fqdn, ?string $currentZone): string
    {
        if ($currentZone && strpos($fqdn, $currentZone) !== false) {
            return $currentZone;
        }

        // Extract last two parts as zone
        $parts = explode('.', $fqdn);
        if (count($parts) >= 2) {
            return $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
        }

        return $fqdn;
    }

    /**
     * Helper: Get record name relative to zone
     */
    private function getRecordName(string $fqdn, string $zone): string
    {
        if ($fqdn === $zone) {
            return '@';
        }

        $suffix = '.' . $zone;
        if (substr($fqdn, -strlen($suffix)) === $suffix) {
            return substr($fqdn, 0, -strlen($suffix));
        }

        return $fqdn;
    }

    /**
     * Helper: Recursive delete
     */
    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }
}
