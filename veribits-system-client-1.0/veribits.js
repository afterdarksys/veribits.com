#!/usr/bin/env node
/**
 * VeriBits CLI - Professional Security & Forensics Toolkit (Node.js Edition)
 * Unified command-line interface for all VeriBits tools
 */

const fs = require('fs');
const path = require('path');
const https = require('https');
const http = require('http');
const { URL } = require('url');

const VERSION = '1.0.0';
const DEFAULT_CONFIG = path.join(require('os').homedir(), '.veribits', 'config.json');
const DEFAULT_API_URL = 'https://veribits.com';

class VeriBitsCLI {
    constructor() {
        this.config = this.loadConfig();
        this.apiUrl = this.config.api_url || DEFAULT_API_URL;
        this.apiKey = this.config.api_key || '';
    }

    loadConfig() {
        try {
            if (fs.existsSync(DEFAULT_CONFIG)) {
                return JSON.parse(fs.readFileSync(DEFAULT_CONFIG, 'utf8'));
            }
        } catch (error) {
            console.error('Warning: Failed to load config:', error.message);
        }
        return {};
    }

    saveConfig(config) {
        const dir = path.dirname(DEFAULT_CONFIG);
        if (!fs.existsSync(dir)) {
            fs.mkdirSync(dir, { recursive: true });
        }
        fs.writeFileSync(DEFAULT_CONFIG, JSON.stringify(config, null, 2));
    }

    async apiRequest(endpoint, options = {}) {
        const url = new URL(endpoint, this.apiUrl + '/api/v1');
        const isHttps = url.protocol === 'https:';
        const client = isHttps ? https : http;

        const requestOptions = {
            hostname: url.hostname,
            port: url.port || (isHttps ? 443 : 80),
            path: url.pathname + url.search,
            method: options.method || 'GET',
            headers: options.headers || {}
        };

        if (this.apiKey) {
            requestOptions.headers['Authorization'] = `Bearer ${this.apiKey}`;
        }

        if (options.json) {
            requestOptions.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.json);
        }

        if (options.body) {
            requestOptions.headers['Content-Length'] = Buffer.byteLength(options.body);
        }

        return new Promise((resolve, reject) => {
            const req = client.request(requestOptions, (res) => {
                let data = '';

                res.on('data', (chunk) => {
                    data += chunk;
                });

                res.on('end', () => {
                    try {
                        const parsed = JSON.parse(data);
                        if (res.statusCode !== 200) {
                            reject(new Error(parsed.error?.message || `HTTP ${res.statusCode}`));
                        } else {
                            resolve(parsed);
                        }
                    } catch (error) {
                        reject(new Error('Invalid JSON response'));
                    }
                });
            });

            req.on('error', (error) => {
                reject(error);
            });

            if (options.body) {
                req.write(options.body);
            }

            req.end();
        });
    }

    // ========== HASH COMMANDS ==========

    async hashLookup(args) {
        const hash = args._[0];
        if (!hash) {
            this.error('Hash required');
        }

        console.log(`🔍 Looking up hash: ${hash}`);

        const result = await this.apiRequest('/tools/hash-lookup', {
            method: 'POST',
            json: {
                hash: hash,
                hash_type: args.type || 'auto'
            }
        });

        if (result.success) {
            const data = result.data;
            if (data.found) {
                console.log('\n✓ Hash Found!');
                console.log(`Hash Type: ${data.hash_type.toUpperCase()}`);
                console.log(`Plaintext: ${data.plaintext}`);
                console.log(`\nSources checked: ${data.sources_queried}`);
                console.log(`Sources found: ${data.sources_found}`);

                if (args.verbose) {
                    console.log('\nSource Details:');
                    data.sources.forEach(source => {
                        const status = source.found ? '✓' : '✗';
                        console.log(`  ${status} ${source.source}`);
                    });
                }
            } else {
                console.log('\n✗ Hash not found in any database');
                console.log(`Checked ${data.sources_queried} sources`);
            }
        }
    }

    async hashBatch(args) {
        const file = args._[0];
        if (!file || !fs.existsSync(file)) {
            this.error(`File not found: ${file}`);
        }

        console.log(`📋 Loading hashes from: ${file}`);
        const hashes = fs.readFileSync(file, 'utf8')
            .split('\n')
            .map(line => line.trim())
            .filter(line => line.length > 0);

        console.log(`Found ${hashes.length} hashes to lookup\n`);

        const result = await this.apiRequest('/tools/hash-lookup/batch', {
            method: 'POST',
            json: { hashes }
        });

        if (result.success) {
            const data = result.data;
            console.log(`Total: ${data.total}`);
            console.log(`Found: ${data.found}`);
            console.log(`Not Found: ${data.not_found}\n`);

            data.results.forEach(r => {
                const status = r.found ? '✓' : '✗';
                const plaintext = r.found ? r.plaintext : 'Not found';
                const hashShort = r.hash.substring(0, 16);
                console.log(`${status} ${hashShort}... → ${plaintext}`);
            });

            if (args.output) {
                fs.writeFileSync(args.output, JSON.stringify(data.results, null, 2));
                console.log(`\n💾 Results saved to: ${args.output}`);
            }
        }
    }

    async hashIdentify(args) {
        const hash = args._[0];
        if (!hash) {
            this.error('Hash required');
        }

        console.log(`🔎 Identifying hash: ${hash}`);

        const result = await this.apiRequest('/tools/hash-lookup/identify', {
            method: 'POST',
            json: { hash }
        });

        if (result.success) {
            const data = result.data;
            console.log(`\nLength: ${data.length} characters`);
            console.log(`Most Likely: ${data.most_likely.toUpperCase()}`);
            console.log('\nPossible Types:');
            data.possible_types.forEach(type => {
                console.log(`  • ${type}`);
            });
        }
    }

    // ========== PASSWORD COMMANDS ==========

    async passwordAnalyze(args) {
        const file = args._[0];
        if (!file || !fs.existsSync(file)) {
            this.error(`File not found: ${file}`);
        }

        console.log(`📊 Analyzing file: ${file}`);

        // For file uploads, we'd need FormData which requires additional packages
        // For simplicity, showing the concept
        console.log('File upload via Node.js CLI requires additional setup.');
        console.log(`Use web interface: ${this.apiUrl}/tool/password-recovery.php`);
    }

    // ========== NETCAT COMMANDS ==========

    async netcat(args) {
        const host = args._[0];
        const port = args._[1];

        if (!host || !port) {
            this.error('Host and port required');
        }

        console.log(`🔌 Connecting to ${host}:${port}...`);

        const payload = {
            host: host,
            port: parseInt(port),
            protocol: args.protocol || 'tcp',
            data: args.data || null,
            timeout: args.timeout || 5,
            wait_time: args['wait-time'] || 2,
            verbose: args.verbose || false,
            zero_io: args['zero-io'] || false,
            source_port: args['source-port'] || null
        };

        const result = await this.apiRequest('/tools/netcat', {
            method: 'POST',
            json: payload
        });

        if (result.success) {
            const data = result.data;

            console.log(`\nStatus: ${data.connected ? '✓ Connected' : '✗ Connection Failed'}`);
            console.log(`Host: ${data.host}`);
            console.log(`Port: ${data.port}`);
            console.log(`Protocol: ${data.protocol.toUpperCase()}`);

            if (data.connection_time) {
                console.log(`Connection Time: ${data.connection_time}ms`);
            }

            if (data.response) {
                console.log('\nResponse:');
                console.log(data.response);
                console.log(`\nReceived ${data.bytes_received} bytes`);
            }

            if (data.banner) {
                console.log(`\nBanner: ${data.banner}`);
            }

            if (data.service_name) {
                console.log(`\nDetected Service: ${data.service_name}`);
                if (data.service_description) {
                    console.log(`Description: ${data.service_description}`);
                }
            }

            if (data.error) {
                console.error(`\nError: ${data.error}`);
            }

            if (data.verbose_output && args.verbose) {
                console.log('\nVerbose Output:');
                console.log(data.verbose_output);
            }
        }
    }

    // ========== OSQUERY COMMANDS ==========

    async osqueryRun(args) {
        const query = args._[0];
        if (!query) {
            this.error('Query required');
        }

        const queryShort = query.substring(0, 50);
        console.log(`📊 Executing query: ${queryShort}...\n`);

        const result = await this.apiRequest('/osquery/execute', {
            method: 'POST',
            json: {
                query: query,
                timeout: args.timeout || 30
            }
        });

        if (result.success) {
            const data = result.data;
            console.log(`Rows: ${data.row_count}`);
            console.log(`Time: ${data.execution_time}s\n`);

            if (data.row_count > 0) {
                const columns = data.columns;
                const rows = data.rows;

                // Print header
                console.log(columns.join(' | '));
                console.log('-'.repeat(columns.length * 20));

                // Print rows (limit to 20)
                rows.slice(0, 20).forEach(row => {
                    const values = columns.map(col => row[col] || '');
                    console.log(values.join(' | '));
                });

                if (rows.length > 20) {
                    console.log(`\n... and ${rows.length - 20} more rows`);
                }
            }

            if (args.output) {
                fs.writeFileSync(args.output, JSON.stringify(data.rows, null, 2));
                console.log(`\n💾 Results saved to: ${args.output}`);
            }
        }
    }

    async osqueryTables(args) {
        const result = await this.apiRequest('/osquery/tables', {
            method: 'GET'
        });

        if (result.success) {
            const tables = result.data.tables;
            console.log(`Available osquery tables (${tables.length}):\n`);
            tables.forEach(table => {
                console.log(`  • ${table.name}`);
                if (args.verbose && table.description) {
                    console.log(`    ${table.description}`);
                }
            });
        }
    }

    // ========== CONFIG COMMANDS ==========

    configSet(args) {
        const key = args._[0];
        const value = args._[1];

        if (!key || !value) {
            this.error('Key and value required');
        }

        const config = this.loadConfig();
        config[key] = value;
        this.saveConfig(config);
        console.log(`✓ Set ${key} = ${value}`);
    }

    configShow() {
        const config = this.loadConfig();
        console.log('Current configuration:');
        for (const [key, value] of Object.entries(config)) {
            // Hide sensitive values
            let displayValue = value;
            if (key.toLowerCase().includes('key') || key.toLowerCase().includes('password')) {
                displayValue = '***' + value.slice(-4);
            }
            console.log(`  ${key}: ${displayValue}`);
        }
    }

    // ========== HELPER METHODS ==========

    error(message) {
        console.error(`Error: ${message}`);
        process.exit(1);
    }
}

// ========== MAIN CLI ROUTER ==========

function parseArgs(argv) {
    const args = { _: [] };
    for (let i = 0; i < argv.length; i++) {
        const arg = argv[i];
        if (arg.startsWith('--')) {
            const key = arg.slice(2);
            if (i + 1 < argv.length && !argv[i + 1].startsWith('-')) {
                args[key] = argv[++i];
            } else {
                args[key] = true;
            }
        } else if (arg.startsWith('-')) {
            const key = arg.slice(1);
            if (i + 1 < argv.length && !argv[i + 1].startsWith('-')) {
                args[key] = argv[++i];
            } else {
                args[key] = true;
            }
        } else {
            args._.push(arg);
        }
    }
    return args;
}

function showHelp() {
    console.log(`VeriBits CLI (Node.js Edition) v${VERSION}
Professional Security & Forensics Toolkit

Usage:
  veribits.js <command> [subcommand] [options]

Commands:
  hash               Hash lookup and analysis
    lookup <hash>      Lookup hash in databases
      --type TYPE      Hash type (auto, md5, sha1, sha256)
      --verbose        Show detailed source information
    batch <file>       Batch lookup from file
      --output FILE    Output file for results (JSON)
    identify <hash>    Identify hash type

  password           Password recovery and cracking
    analyze <file>     Analyze password-protected file
    remove <file>      Remove password from file
    crack <file>       Crack password

  netcat <host> <port>  Network connection utility
    --protocol PROTO   Protocol (tcp or udp, default: tcp)
    --data TEXT        Data to send
    --timeout SEC      Connection timeout (default: 5)
    --wait-time SEC    Wait time for response (default: 2)
    --verbose          Verbose output
    --zero-io          Zero I/O mode (scan only)
    --source-port N    Source port

  osquery            osquery SQL interface
    run <query>        Execute SQL query
      --timeout SEC    Timeout in seconds (default: 30)
      --output FILE    Output file (JSON)
    tables             List available tables
      --verbose        Show descriptions

  config             Configuration
    set <key> <value>  Set configuration value
    show               Show current configuration

Options:
  --version          Show version
  --help             Show this help

Examples:
  veribits.js hash lookup 5f4dcc3b5aa765d61d8327deb882cf99
  veribits.js netcat example.com 80 --data "GET / HTTP/1.1\\nHost: example.com\\n\\n"
  veribits.js osquery run "SELECT * FROM processes LIMIT 10"
  veribits.js config set api_key YOUR_KEY
`);
}

async function main() {
    const argv = process.argv.slice(2);

    if (argv.length === 0) {
        showHelp();
        process.exit(0);
    }

    const command = argv[0];
    const args = parseArgs(argv.slice(1));

    if (command === '--version') {
        console.log(`VeriBits CLI (Node.js) v${VERSION}`);
        process.exit(0);
    }

    if (command === '--help' || command === 'help') {
        showHelp();
        process.exit(0);
    }

    const cli = new VeriBitsCLI();

    try {
        switch (command) {
            case 'hash':
                const hashCmd = args._[0];
                args._ = args._.slice(1);
                switch (hashCmd) {
                    case 'lookup':
                        await cli.hashLookup(args);
                        break;
                    case 'batch':
                        await cli.hashBatch(args);
                        break;
                    case 'identify':
                        await cli.hashIdentify(args);
                        break;
                    default:
                        console.error(`Unknown hash command: ${hashCmd}`);
                        process.exit(1);
                }
                break;

            case 'password':
                const passCmd = args._[0];
                args._ = args._.slice(1);
                switch (passCmd) {
                    case 'analyze':
                        await cli.passwordAnalyze(args);
                        break;
                    default:
                        console.error(`Unknown password command: ${passCmd}`);
                        process.exit(1);
                }
                break;

            case 'netcat':
                await cli.netcat(args);
                break;

            case 'osquery':
                const osqCmd = args._[0];
                args._ = args._.slice(1);
                switch (osqCmd) {
                    case 'run':
                        await cli.osqueryRun(args);
                        break;
                    case 'tables':
                        await cli.osqueryTables(args);
                        break;
                    default:
                        console.error(`Unknown osquery command: ${osqCmd}`);
                        process.exit(1);
                }
                break;

            case 'config':
                const cfgCmd = args._[0];
                args._ = args._.slice(1);
                switch (cfgCmd) {
                    case 'set':
                        cli.configSet(args);
                        break;
                    case 'show':
                        cli.configShow();
                        break;
                    default:
                        console.error(`Unknown config command: ${cfgCmd}`);
                        process.exit(1);
                }
                break;

            default:
                console.error(`Unknown command: ${command}`);
                showHelp();
                process.exit(1);
        }
    } catch (error) {
        console.error(`Error: ${error.message}`);
        process.exit(1);
    }
}

main();
