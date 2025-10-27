#!/usr/bin/env node

/**
 * VeriBits CLI
 * Official Node.js command-line interface for VeriBits.com
 *
 * Copyright (c) After Dark Systems, LLC
 */

const { program } = require('commander');
const VeriBitsCLI = require('../lib/cli');

const version = require('../package.json').version;

program
  .name('veribits')
  .description('VeriBits CLI - Security and Developer Tools')
  .version(version)
  .option('--api-key <key>', 'API key for authentication (optional)');

// IAM Policy Analyzer
program
  .command('iam-analyze <policy-file>')
  .description('Analyze AWS IAM policy for security issues')
  .action(async (policyFile) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.iamAnalyze(policyFile);
  });

// Secrets Scanner
program
  .command('secrets-scan <file>')
  .description('Scan file for exposed secrets and credentials')
  .action(async (file) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.secretsScan(file);
  });

// Database Connection Auditor
program
  .command('db-audit <connection-string>')
  .description('Audit database connection string for security issues')
  .action(async (connectionString) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.dbAudit(connectionString);
  });

// Security Headers Analyzer
program
  .command('security-headers <url>')
  .description('Analyze HTTP security headers')
  .action(async (url) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.securityHeaders(url);
  });

// JWT Decoder
program
  .command('jwt-decode <token>')
  .description('Decode and analyze JWT token')
  .option('-s, --secret <secret>', 'Secret for verification')
  .option('-v, --verify', 'Verify token signature')
  .action(async (token, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.jwtDecode(token, options);
  });

// Hash Generator
program
  .command('hash <text>')
  .description('Generate cryptographic hashes')
  .option('-a, --algorithm <algo>', 'Hash algorithm (md5, sha1, sha256, sha512)', 'sha256')
  .action(async (text, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.hash(text, options);
  });

// Regex Tester
program
  .command('regex <pattern> <text>')
  .description('Test regular expression pattern')
  .option('-f, --flags <flags>', 'Regex flags (g, i, m, s)', '')
  .action(async (pattern, text, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.regex(pattern, text, options);
  });

// PGP Validator
program
  .command('pgp-validate <key-or-file>')
  .description('Validate PGP key')
  .action(async (keyOrFile) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.pgpValidate(keyOrFile);
  });

// File Magic Detector
program
  .command('file-magic <file>')
  .description('Detect file type by magic number')
  .action(async (file) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.fileMagic(file);
  });

// DNS Validator
program
  .command('dns-validate <domain>')
  .description('Validate DNS records for a domain')
  .option('-t, --type <type>', 'Record type (A, AAAA, MX, TXT, etc.)', 'A')
  .action(async (domain, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.dnsValidate(domain, options);
  });

// Zone Validator
program
  .command('zone-validate <zone-file>')
  .description('Validate DNS zone file')
  .action(async (zoneFile) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.zoneValidate(zoneFile);
  });

// IP Calculator
program
  .command('ip-calc <ip-or-cidr>')
  .description('Calculate IP subnet information')
  .action(async (ipOrCidr) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.ipCalculate(ipOrCidr);
  });

// RBL Check
program
  .command('rbl-check <ip>')
  .description('Check if IP is listed in RBL/DNSBL')
  .action(async (ip) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.rblCheck(ip);
  });

// Email Verification
program
  .command('email-verify <email>')
  .description('Comprehensive email verification (SPF, DKIM, DMARC, MX)')
  .action(async (email) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.emailVerify(email);
  });

// Traceroute
program
  .command('traceroute <host>')
  .description('Perform visual traceroute to host')
  .action(async (host) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.traceroute(host);
  });

// URL Encoder
program
  .command('url-encode <text>')
  .description('URL encode/decode text')
  .option('-d, --decode', 'Decode instead of encode')
  .action(async (text, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.urlEncode(text, options);
  });

// Base64 Encoder
program
  .command('base64 <text>')
  .description('Base64 encode/decode text')
  .option('-d, --decode', 'Decode instead of encode')
  .action(async (text, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.base64(text, options);
  });

// SSL Certificate Check
program
  .command('ssl-check <host>')
  .description('Check SSL/TLS certificate')
  .option('-p, --port <port>', 'Port number', '443')
  .action(async (host, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.sslCheck(host, options);
  });

// Certificate Converter
program
  .command('cert-convert <file>')
  .description('Convert certificate formats (PEM, DER, P12, JKS)')
  .option('-f, --format <format>', 'Target format', 'PEM')
  .action(async (file, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.certConvert(file, options);
  });

// SSL Certificate Converter (OpenSSL-like syntax)
program
  .command('ssl-convert')
  .description('Convert SSL certificates (OpenSSL-like syntax)')
  .requiredOption('-in <file>', 'Input certificate file')
  .option('-inform <format>', 'Input format (PEM, DER, P12, JKS)', 'auto')
  .option('-outform <format>', 'Output format (PEM, DER, P12, JKS)', 'PEM')
  .option('-out <file>', 'Output file (optional, defaults to stdout)')
  .action(async (options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.sslConvert(options);
  });

// Crypto Validator
program
  .command('crypto-validate <address>')
  .description('Validate cryptocurrency address')
  .option('-t, --type <type>', 'Crypto type (bitcoin, ethereum)', 'bitcoin')
  .action(async (address, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.cryptoValidate(address, options);
  });

// Hash Validator
program
  .command('hash-validate <hash>')
  .description('Validate and identify hash type')
  .action(async (hash) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.hashValidate(hash);
  });

// Steganography Detector
program
  .command('steg-detect <file>')
  .description('Detect hidden data in images')
  .action(async (file) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.stegDetect(file);
  });

// BGP AS Lookup
program
  .command('bgp-lookup <as-number>')
  .description('Lookup BGP AS information')
  .action(async (asNumber) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.bgpLookup(asNumber);
  });

// CSR Validator
program
  .command('csr-validate <file>')
  .description('Validate Certificate Signing Request')
  .action(async (file) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.csrValidate(file);
  });

// Have I Been Pwned - Email Breach Check
program
  .command('hibp-email <email>')
  .description('Check if email appears in data breaches')
  .action(async (email) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.hibpEmail(email);
  });

// Have I Been Pwned - Password Breach Check
program
  .command('hibp-password <password>')
  .description('Check if password has been compromised')
  .action(async (password) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.hibpPassword(password);
  });

// Cloud Storage Search
program
  .command('cloud-storage-search <query>')
  .description('Search for publicly exposed cloud storage')
  .option('-p, --provider <provider>', 'Cloud provider (aws, azure, gcp, all)', 'all')
  .option('-t, --type <type>', 'Search type (filename, content)', 'filename')
  .action(async (query, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.cloudStorageSearch(query, options);
  });

// Cloud Storage Security Scan
program
  .command('cloud-storage-scan <bucket>')
  .description('Analyze cloud storage bucket security')
  .option('-p, --provider <provider>', 'Cloud provider (aws, azure, gcp)', 'aws')
  .action(async (bucket, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.cloudStorageScan(bucket, options);
  });

// Cloud Storage List Buckets
program
  .command('cloud-storage-buckets')
  .description('List accessible cloud storage buckets')
  .option('-p, --provider <provider>', 'Cloud provider (aws, azure, gcp)', 'aws')
  .action(async (options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.cloudStorageBuckets(options);
  });

// Email SPF Analysis
program
  .command('email-spf <domain>')
  .description('Analyze SPF records for domain')
  .action(async (domain) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.emailSpf(domain);
  });

// Email DKIM Analysis
program
  .command('email-dkim <domain>')
  .description('Analyze DKIM configuration for domain')
  .option('-s, --selector <selector>', 'DKIM selector', 'default')
  .action(async (domain, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.emailDkim(domain, options);
  });

// Email DMARC Analysis
program
  .command('email-dmarc <domain>')
  .description('Analyze DMARC policy for domain')
  .action(async (domain) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.emailDmarc(domain);
  });

// Email MX Check
program
  .command('email-mx <domain>')
  .description('Check MX records for domain')
  .action(async (domain) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.emailMx(domain);
  });

// Email Disposable Check
program
  .command('email-disposable <email>')
  .description('Check if email is from disposable provider')
  .action(async (email) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.emailDisposable(email);
  });

// Email Blacklist Check
program
  .command('email-blacklist <domain>')
  .description('Check if domain is blacklisted')
  .action(async (domain) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.emailBlacklist(domain);
  });

// Email Deliverability Score
program
  .command('email-score <domain>')
  .description('Calculate email deliverability score')
  .action(async (domain) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.emailScore(domain);
  });

// SSL Chain Resolution
program
  .command('ssl-resolve-chain <cert-or-url>')
  .description('Resolve SSL certificate chain')
  .option('-f, --file', 'Input is a file path')
  .action(async (certOrUrl, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.sslResolveChain(certOrUrl, options);
  });

// SSL Key Pair Verification
program
  .command('ssl-verify-keypair <cert-file> <key-file>')
  .description('Verify SSL certificate and key pair match')
  .action(async (certFile, keyFile) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.sslVerifyKeypair(certFile, keyFile);
  });

// File Verification
program
  .command('verify-file <file>')
  .description('Verify file integrity and authenticity')
  .option('-h, --hash <hash>', 'Expected hash for verification')
  .option('-s, --signature <sig>', 'Signature file for verification')
  .action(async (file, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.verifyFile(file, options);
  });

// Email Verification (comprehensive)
program
  .command('verify-email <email>')
  .description('Comprehensive email verification')
  .action(async (email) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.verifyEmail(email);
  });

// Transaction Verification
program
  .command('verify-tx <tx-hash>')
  .description('Verify blockchain transaction')
  .option('-c, --chain <chain>', 'Blockchain (bitcoin, ethereum)', 'bitcoin')
  .action(async (txHash, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.verifyTransaction(txHash, options);
  });

// Tool Search
program
  .command('tool-search <query>')
  .description('Search available security tools')
  .option('-c, --category <category>', 'Tool category filter')
  .action(async (query, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.toolSearch(query, options);
  });

// Tool List
program
  .command('tool-list')
  .description('List all available tools')
  .option('-c, --category <category>', 'Filter by category')
  .action(async (options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.toolList(options);
  });

// Health Check
program
  .command('health')
  .description('Check API health status')
  .action(async () => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.healthCheck();
  });

// WHOIS Lookup
program
  .command('whois <domain>')
  .description('Perform WHOIS domain lookup')
  .action(async (domain) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.whoisLookup(domain);
  });

// Malware Scanner
program
  .command('malware-scan <file>')
  .description('Scan file for malware')
  .action(async (file) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.malwareScan(file);
  });

// Archive Inspector
program
  .command('inspect-archive <file>')
  .description('Inspect archive file contents')
  .action(async (file) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.inspectArchive(file);
  });

// Interactive Console (REPL)
program
  .command('console')
  .description('Start interactive console with command completion')
  .action(async () => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.startConsole();
  });

// PCAP Analyzer
program
  .command('pcap-analyze <file>')
  .description('Analyze PCAP network capture file')
  .action(async (file) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.pcapAnalyze(file);
  });

// Firewall Editor - Upload
program
  .command('firewall-upload <file>')
  .description('Upload and parse firewall configuration')
  .option('-t, --type <type>', 'Firewall type (iptables, ip6tables, ebtables)', 'iptables')
  .option('-n, --name <name>', 'Device name', 'CLI Upload')
  .action(async (file, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.firewallUpload(file, options);
  });

// Firewall Editor - List
program
  .command('firewall-list')
  .description('List saved firewall configurations')
  .option('-d, --device <name>', 'Filter by device name')
  .option('-t, --type <type>', 'Filter by firewall type')
  .action(async (options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.firewallList(options);
  });

// Firewall Editor - Get
program
  .command('firewall-get <id>')
  .description('Get specific firewall configuration')
  .action(async (id) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.firewallGet(id);
  });

// Hash Generator (API-based with more algorithms)
program
  .command('hash-generate <text>')
  .description('Generate cryptographic hashes (supports bcrypt, argon2, etc)')
  .option('-a, --algorithm <algo>', 'Hash algorithm (sha256, md5, sha512, bcrypt, argon2)', 'sha256')
  .action(async (text, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.hashGenerate(text, options);
  });

// JSON/YAML Validator
program
  .command('json-validate <input>')
  .description('Validate and format JSON or YAML')
  .option('-f, --format <format>', 'Format type (json, yaml)', 'json')
  .option('--pretty', 'Pretty print output')
  .action(async (input, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.jsonValidate(input, options);
  });

// Base64 Encode
program
  .command('base64-encode <input>')
  .description('Encode text or file to Base64')
  .option('-f, --file', 'Input is a file path')
  .action(async (input, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.base64Encode(input, options);
  });

// Base64 Decode
program
  .command('base64-decode <input>')
  .description('Decode Base64 to text or file')
  .option('-o, --output <file>', 'Output file path')
  .action(async (input, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.base64Decode(input, options);
  });

// Docker Scanner
program
  .command('docker-scan <file>')
  .description('Scan Dockerfile for security issues')
  .action(async (file) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.dockerScan(file);
  });

// Terraform Scanner
program
  .command('terraform-scan <file>')
  .description('Scan Terraform configuration for security issues')
  .action(async (file) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.terraformScan(file);
  });

// Kubernetes Validator
program
  .command('k8s-validate <file>')
  .description('Validate Kubernetes manifest file')
  .action(async (file) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.k8sValidate(file);
  });

// DNSSEC Validator
program
  .command('dnssec-validate <domain>')
  .description('Validate DNSSEC configuration for domain')
  .action(async (domain) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.dnssecValidate(domain);
  });

// DNS Propagation Checker
program
  .command('dns-propagation <domain>')
  .description('Check DNS propagation across global servers')
  .option('-t, --type <type>', 'Record type (A, AAAA, MX, TXT, etc.)', 'A')
  .action(async (domain, options) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.dnsPropagation(domain, options);
  });

// Reverse DNS Lookup
program
  .command('reverse-dns <ip>')
  .description('Perform reverse DNS (PTR) lookup')
  .action(async (ip) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.reverseDns(ip);
  });

program.parse(process.argv);

// Show help if no command provided
if (!process.argv.slice(2).length) {
  program.outputHelp();
}
