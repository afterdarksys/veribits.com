/**
 * VeriBits CLI - Main implementation
 * Copyright (c) After Dark Systems, LLC
 */

const axios = require('axios');
const fs = require('fs').promises;
const chalk = require('chalk');
const crypto = require('crypto');

const API_BASE = process.env.VERIBITS_API_URL || 'https://veribits.com/api/v1';

class VeriBitsCLI {
  constructor(apiKey) {
    this.apiKey = apiKey || process.env.VERIBITS_API_KEY;
    this.headers = {
      'Content-Type': 'application/json',
    };
    if (this.apiKey) {
      this.headers['Authorization'] = `Bearer ${this.apiKey}`;
    }
  }

  async _request(method, endpoint, data = null) {
    const url = `${API_BASE}${endpoint}`;

    try {
      const response = await axios({
        method,
        url,
        headers: this.headers,
        data,
      });

      if (!response.data.success) {
        const errorMsg = response.data.error?.message || 'Unknown error';
        console.error(chalk.red(`Error: ${errorMsg}`));
        process.exit(1);
      }

      return response.data.data || response.data;
    } catch (error) {
      if (error.response) {
        const errorMsg = error.response.data?.error?.message || error.message;
        console.error(chalk.red(`Error: ${errorMsg}`));
      } else if (error.request) {
        console.error(chalk.red('Network error: Unable to reach VeriBits API'));
      } else {
        console.error(chalk.red(`Error: ${error.message}`));
      }
      process.exit(1);
    }
  }

  async iamAnalyze(policyFile) {
    try {
      const content = await fs.readFile(policyFile, 'utf8');
      const policy = JSON.parse(content);

      const result = await this._request('POST', '/security/iam-policy/analyze', {
        policy_document: policy,
        policy_name: policyFile,
      });

      console.log(chalk.bold(`\n🔐 IAM Policy Analysis: ${policyFile}`));
      console.log(`Risk Score: ${chalk.yellow(result.risk_score)}/100`);
      console.log(`Risk Level: ${this._colorizeRisk(result.risk_level)}`);
      console.log(`Findings: ${result.findings.length}`);

      if (result.findings && result.findings.length > 0) {
        console.log(chalk.bold('\n⚠️  Issues Found:'));
        result.findings.forEach((finding, i) => {
          const icon = this._getSeverityIcon(finding.severity);
          console.log(`\n${i + 1}. ${icon} ${finding.issue}`);
          console.log(`   Severity: ${this._colorizeSeverity(finding.severity)}`);
          console.log(chalk.blue(`   💡 ${finding.recommendation}`));
        });
      }

      if (result.recommendations && result.recommendations.length > 0) {
        console.log(chalk.bold('\n📋 Recommendations:'));
        result.recommendations.forEach(rec => {
          console.log(`  • ${rec}`);
        });
      }
    } catch (error) {
      if (error.code === 'ENOENT') {
        console.error(chalk.red(`Error: File not found: ${policyFile}`));
      } else if (error instanceof SyntaxError) {
        console.error(chalk.red(`Error: Invalid JSON in ${policyFile}`));
      } else {
        throw error;
      }
      process.exit(1);
    }
  }

  async secretsScan(file) {
    try {
      const content = await fs.readFile(file, 'utf8');

      const result = await this._request('POST', '/security/secrets/scan', {
        content,
        source_name: file,
        source_type: 'file',
      });

      console.log(chalk.bold(`\n🔑 Secrets Scan: ${file}`));
      console.log(`Secrets Found: ${chalk.yellow(result.secrets_found)}`);
      console.log(`Risk Level: ${this._colorizeRisk(result.risk_level)}`);

      if (result.secrets && result.secrets.length > 0) {
        console.log(chalk.bold('\n⚠️  Detected Secrets:'));
        result.secrets.forEach((secret, i) => {
          const icon = this._getSeverityIcon(secret.severity);
          console.log(`\n${i + 1}. ${icon} ${secret.name}`);
          console.log(`   Line: ${secret.line}`);
          console.log(`   Value: ${chalk.gray(secret.value)}`);
          console.log(`   Severity: ${this._colorizeSeverity(secret.severity)}`);
        });
      } else {
        console.log(chalk.green('\n✓ No secrets detected!'));
      }
    } catch (error) {
      if (error.code === 'ENOENT') {
        console.error(chalk.red(`Error: File not found: ${file}`));
        process.exit(1);
      }
      throw error;
    }
  }

  async dbAudit(connectionString) {
    const result = await this._request('POST', '/security/db-connection/audit', {
      connection_string: connectionString,
    });

    console.log(chalk.bold('\n🗄️  Database Connection Audit'));
    console.log(`Database Type: ${result.db_type}`);
    console.log(`Risk Score: ${chalk.yellow(result.risk_score)}/100`);
    console.log(`Risk Level: ${this._colorizeRisk(result.risk_level)}`);

    if (result.issues && result.issues.length > 0) {
      console.log(chalk.bold(`\n⚠️  Issues Found: ${result.issues.length}`));
      result.issues.forEach((issue, i) => {
        const icon = this._getSeverityIcon(issue.severity);
        console.log(`\n${i + 1}. ${icon} ${issue.issue}`);
        console.log(chalk.blue(`   💡 ${issue.recommendation}`));
      });
    } else {
      console.log(chalk.green('\n✓ No security issues found!'));
    }

    if (result.secure_alternative) {
      console.log(chalk.bold(`\n🔒 Secure Alternative:\n${result.secure_alternative}`));
    }
  }

  async securityHeaders(url) {
    const result = await this._request('POST', '/tools/security-headers', {
      url,
    });

    console.log(chalk.bold(`\n🛡️  Security Headers Analysis: ${url}`));
    console.log(`Score: ${chalk.yellow(result.score)}/100`);
    console.log(`Grade: ${this._colorizeGrade(result.grade)}`);

    if (result.headers) {
      console.log(chalk.bold('\n📋 Headers:'));
      Object.entries(result.headers).forEach(([header, status]) => {
        const icon = status.present ? '✓' : '✗';
        const color = status.present ? chalk.green : chalk.red;
        console.log(`  ${color(icon)} ${header}: ${status.value || 'Missing'}`);
      });
    }

    if (result.recommendations && result.recommendations.length > 0) {
      console.log(chalk.bold('\n💡 Recommendations:'));
      result.recommendations.forEach(rec => {
        console.log(`  • ${rec}`);
      });
    }
  }

  async jwtDecode(token, options = {}) {
    const data = {
      token,
    };

    if (options.secret) {
      data.secret = options.secret;
      data.verify = true;
    } else if (options.verify) {
      data.verify = true;
    }

    const result = await this._request('POST', '/jwt/decode', data);

    console.log(chalk.bold('\n🔑 JWT Token Analysis'));

    if (result.header) {
      console.log(chalk.bold('\nHeader:'));
      console.log(JSON.stringify(result.header, null, 2));
    }

    if (result.payload) {
      console.log(chalk.bold('\nPayload:'));
      console.log(JSON.stringify(result.payload, null, 2));
    }

    if (result.signature) {
      console.log(chalk.bold('\nSignature:'));
      console.log(result.signature);
    }

    if (result.verified !== undefined) {
      const status = result.verified ? chalk.green('✓ Valid') : chalk.red('✗ Invalid');
      console.log(chalk.bold(`\nVerification: ${status}`));
    }

    if (result.warnings && result.warnings.length > 0) {
      console.log(chalk.bold('\n⚠️  Warnings:'));
      result.warnings.forEach(warning => {
        console.log(chalk.yellow(`  • ${warning}`));
      });
    }
  }

  async hash(text, options = {}) {
    const algorithm = options.algorithm || 'sha256';

    // Generate hash locally for common algorithms
    const supportedAlgorithms = ['md5', 'sha1', 'sha256', 'sha512'];

    if (supportedAlgorithms.includes(algorithm)) {
      const hash = crypto.createHash(algorithm).update(text).digest('hex');
      console.log(chalk.bold(`\n🔐 Hash (${algorithm.toUpperCase()})`));
      console.log(chalk.cyan(hash));
    } else {
      console.error(chalk.red(`Error: Unsupported algorithm: ${algorithm}`));
      console.log(`Supported algorithms: ${supportedAlgorithms.join(', ')}`);
      process.exit(1);
    }
  }

  async regex(pattern, text, options = {}) {
    const result = await this._request('POST', '/tools/regex-test', {
      pattern,
      text,
      flags: options.flags || '',
    });

    console.log(chalk.bold('\n📝 Regex Test Results'));
    console.log(`Pattern: ${chalk.cyan(pattern)}`);
    console.log(`Flags: ${options.flags || 'none'}`);
    console.log(`Match: ${result.match ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);

    if (result.matches && result.matches.length > 0) {
      console.log(chalk.bold('\n📋 Matches:'));
      result.matches.forEach((match, i) => {
        console.log(`  ${i + 1}. ${chalk.yellow(match)}`);
      });
    }

    if (result.groups && Object.keys(result.groups).length > 0) {
      console.log(chalk.bold('\n👥 Capture Groups:'));
      Object.entries(result.groups).forEach(([name, value]) => {
        console.log(`  ${name}: ${chalk.yellow(value)}`);
      });
    }
  }

  async pgpValidate(keyOrFile) {
    try {
      let keyData = keyOrFile;

      // Try to read as file if it exists
      try {
        const stats = await fs.stat(keyOrFile);
        if (stats.isFile()) {
          keyData = await fs.readFile(keyOrFile, 'utf8');
        }
      } catch (e) {
        // Not a file, treat as raw key data
      }

      const result = await this._request('POST', '/tools/pgp-validate', {
        key: keyData,
      });

      console.log(chalk.bold('\n🔐 PGP Key Validation'));
      console.log(`Valid: ${result.valid ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);

      if (result.key_info) {
        console.log(chalk.bold('\n📋 Key Information:'));
        console.log(`  Algorithm: ${result.key_info.algorithm}`);
        console.log(`  Key Size: ${result.key_info.key_size} bits`);
        console.log(`  Fingerprint: ${chalk.cyan(result.key_info.fingerprint)}`);
        if (result.key_info.user_id) {
          console.log(`  User ID: ${result.key_info.user_id}`);
        }
        if (result.key_info.created) {
          console.log(`  Created: ${result.key_info.created}`);
        }
        if (result.key_info.expires) {
          console.log(`  Expires: ${result.key_info.expires}`);
        }
      }

      if (result.warnings && result.warnings.length > 0) {
        console.log(chalk.bold('\n⚠️  Warnings:'));
        result.warnings.forEach(warning => {
          console.log(chalk.yellow(`  • ${warning}`));
        });
      }
    } catch (error) {
      if (error.code === 'ENOENT') {
        console.error(chalk.red(`Error: File not found: ${keyOrFile}`));
        process.exit(1);
      }
      throw error;
    }
  }

  async fileMagic(file) {
    try {
      const content = await fs.readFile(file);
      const base64 = content.toString('base64');

      const result = await this._request('POST', '/file-magic', {
        file_data: base64,
        filename: file,
      });

      console.log(chalk.bold(`\n🔍 File Magic Detection: ${file}`));
      console.log(`File Type: ${chalk.cyan(result.file_type)}`);
      console.log(`MIME Type: ${result.mime_type}`);
      console.log(`Extension: ${result.extension}`);

      if (result.magic_number) {
        console.log(`Magic Number: ${chalk.gray(result.magic_number)}`);
      }

      if (result.description) {
        console.log(`\nDescription: ${result.description}`);
      }

      if (result.warnings && result.warnings.length > 0) {
        console.log(chalk.bold('\n⚠️  Warnings:'));
        result.warnings.forEach(warning => {
          console.log(chalk.yellow(`  • ${warning}`));
        });
      }
    } catch (error) {
      if (error.code === 'ENOENT') {
        console.error(chalk.red(`Error: File not found: ${file}`));
        process.exit(1);
      }
      throw error;
    }
  }

  async dnsValidate(domain, options = {}) {
    const result = await this._request('POST', '/tools/dns-validate', {
      domain,
      record_type: options.type || 'A',
    });

    console.log(chalk.bold(`\n🌐 DNS Validation: ${domain}`));
    console.log(`Record Type: ${options.type || 'A'}`);

    if (result.records && result.records.length > 0) {
      console.log(chalk.bold('\n📋 Records:'));
      result.records.forEach((record, i) => {
        console.log(`\n${i + 1}. ${chalk.cyan(record.type)}`);
        console.log(`   Value: ${record.value}`);
        if (record.ttl) console.log(`   TTL: ${record.ttl}`);
        if (record.priority) console.log(`   Priority: ${record.priority}`);
      });
    } else {
      console.log(chalk.yellow('\nNo records found'));
    }

    if (result.dnssec) {
      console.log(chalk.bold('\n🔐 DNSSEC:'));
      console.log(`   Enabled: ${result.dnssec.enabled ? chalk.green('Yes') : chalk.red('No')}`);
    }
  }

  async zoneValidate(zoneFile) {
    try {
      const content = await fs.readFile(zoneFile, 'utf8');

      const result = await this._request('POST', '/zone-validate', {
        zone_data: content,
        zone_name: zoneFile,
      });

      console.log(chalk.bold(`\n📝 Zone File Validation: ${zoneFile}`));
      console.log(`Valid: ${result.valid ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);

      if (result.errors && result.errors.length > 0) {
        console.log(chalk.bold('\n❌ Errors:'));
        result.errors.forEach(error => {
          console.log(chalk.red(`  • ${error}`));
        });
      }

      if (result.warnings && result.warnings.length > 0) {
        console.log(chalk.bold('\n⚠️  Warnings:'));
        result.warnings.forEach(warning => {
          console.log(chalk.yellow(`  • ${warning}`));
        });
      }

      if (result.records_count) {
        console.log(chalk.bold(`\n📊 Statistics:`));
        console.log(`   Total Records: ${result.records_count}`);
      }
    } catch (error) {
      if (error.code === 'ENOENT') {
        console.error(chalk.red(`Error: File not found: ${zoneFile}`));
        process.exit(1);
      }
      throw error;
    }
  }

  async ipCalculate(ipOrCidr) {
    const result = await this._request('POST', '/tools/ip-calculate', {
      ip: ipOrCidr,
    });

    console.log(chalk.bold('\n🔢 IP Calculator'));
    console.log(`IP Address: ${chalk.cyan(result.ip_address)}`);
    console.log(`Network: ${result.network}`);
    console.log(`Netmask: ${result.netmask}`);
    console.log(`CIDR: ${result.cidr}`);
    console.log(`Wildcard: ${result.wildcard}`);
    console.log(`First Host: ${result.first_host}`);
    console.log(`Last Host: ${result.last_host}`);
    console.log(`Broadcast: ${result.broadcast}`);
    console.log(`Total Hosts: ${chalk.yellow(result.total_hosts)}`);
    console.log(`IP Version: ${result.ip_version}`);

    if (result.ip_class) {
      console.log(`IP Class: ${result.ip_class}`);
    }
  }

  async rblCheck(ip) {
    const result = await this._request('POST', '/tools/rbl-check', {
      ip: ip,
    });

    console.log(chalk.bold(`\n🛡️  RBL/DNSBL Check: ${ip}`));
    console.log(`Listed: ${result.is_listed ? chalk.red('✗ Yes') : chalk.green('✓ No')}`);
    console.log(`Lists Checked: ${result.lists_checked}`);
    console.log(`Lists Found: ${chalk.yellow(result.lists_found)}`);

    if (result.blacklists && result.blacklists.length > 0) {
      console.log(chalk.bold('\n❌ Found on Blacklists:'));
      result.blacklists.forEach((bl, i) => {
        console.log(`\n${i + 1}. ${chalk.red(bl.name)}`);
        if (bl.reason) console.log(`   Reason: ${bl.reason}`);
        if (bl.url) console.log(`   Info: ${chalk.blue(bl.url)}`);
      });
    }
  }

  async emailVerify(email) {
    const result = await this._request('POST', '/tools/smtp-relay-check', {
      email,
    });

    console.log(chalk.bold(`\n📧 Email Verification: ${email}`));
    console.log(`Valid: ${result.valid ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);
    console.log(`Deliverability Score: ${chalk.yellow(result.deliverability_score)}/100`);

    if (result.mx_records) {
      console.log(chalk.bold('\n📮 MX Records:'));
      console.log(`   Valid: ${result.mx_records.valid ? chalk.green('✓') : chalk.red('✗')}`);
      if (result.mx_records.servers) {
        result.mx_records.servers.forEach(server => {
          console.log(`   • ${server}`);
        });
      }
    }

    if (result.spf) {
      console.log(chalk.bold('\n🔐 SPF:'));
      console.log(`   Valid: ${result.spf.valid ? chalk.green('✓') : chalk.red('✗')}`);
      if (result.spf.record) console.log(`   Record: ${result.spf.record}`);
    }

    if (result.dkim) {
      console.log(chalk.bold('\n🔏 DKIM:'));
      console.log(`   Valid: ${result.dkim.valid ? chalk.green('✓') : chalk.red('✗')}`);
    }

    if (result.dmarc) {
      console.log(chalk.bold('\n🛡️  DMARC:'));
      console.log(`   Valid: ${result.dmarc.valid ? chalk.green('✓') : chalk.red('✗')}`);
      if (result.dmarc.policy) console.log(`   Policy: ${result.dmarc.policy}`);
    }

    if (result.warnings && result.warnings.length > 0) {
      console.log(chalk.bold('\n⚠️  Warnings:'));
      result.warnings.forEach(warning => {
        console.log(chalk.yellow(`  • ${warning}`));
      });
    }
  }

  async traceroute(host) {
    const result = await this._request('POST', '/tools/traceroute', {
      host,
    });

    console.log(chalk.bold(`\n🗺️  Traceroute to ${host}`));
    console.log(`Destination: ${result.destination_ip}`);
    console.log(`Hops: ${result.hop_count}`);

    if (result.hops && result.hops.length > 0) {
      console.log(chalk.bold('\n📍 Route:'));
      result.hops.forEach((hop) => {
        const time = hop.rtt ? `${hop.rtt}ms` : '*';
        console.log(`  ${hop.hop}. ${chalk.cyan(hop.ip || '*')} (${hop.hostname || 'unknown'}) - ${time}`);
        if (hop.location) {
          console.log(`     ${hop.location}`);
        }
      });
    }
  }

  async urlEncode(text, options = {}) {
    if (options.decode) {
      const decoded = decodeURIComponent(text);
      console.log(chalk.bold('\n🔗 URL Decoded:'));
      console.log(chalk.cyan(decoded));
    } else {
      const encoded = encodeURIComponent(text);
      console.log(chalk.bold('\n🔗 URL Encoded:'));
      console.log(chalk.cyan(encoded));
    }
  }

  async base64(text, options = {}) {
    if (options.decode) {
      const decoded = Buffer.from(text, 'base64').toString('utf8');
      console.log(chalk.bold('\n📦 Base64 Decoded:'));
      console.log(chalk.cyan(decoded));
    } else {
      const encoded = Buffer.from(text).toString('base64');
      console.log(chalk.bold('\n📦 Base64 Encoded:'));
      console.log(chalk.cyan(encoded));
    }
  }

  async sslCheck(host, options = {}) {
    const result = await this._request('POST', '/ssl/validate', {
      url: `${host}:${options.port || '443'}`,
    });

    console.log(chalk.bold(`\n🔐 SSL/TLS Certificate Check: ${host}:${options.port || 443}`));
    console.log(`Valid: ${result.valid ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);

    if (result.certificate) {
      const cert = result.certificate;
      console.log(chalk.bold('\n📜 Certificate Details:'));
      console.log(`  Subject: ${cert.subject}`);
      console.log(`  Issuer: ${cert.issuer}`);
      console.log(`  Valid From: ${cert.valid_from}`);
      console.log(`  Valid To: ${cert.valid_to}`);
      console.log(`  Days Remaining: ${chalk.yellow(cert.days_remaining)}`);

      if (cert.sans && cert.sans.length > 0) {
        console.log(`  SANs: ${cert.sans.join(', ')}`);
      }
    }

    if (result.chain_valid !== undefined) {
      console.log(`\nChain Valid: ${result.chain_valid ? chalk.green('✓') : chalk.red('✗')}`);
    }

    if (result.warnings && result.warnings.length > 0) {
      console.log(chalk.bold('\n⚠️  Warnings:'));
      result.warnings.forEach(warning => {
        console.log(chalk.yellow(`  • ${warning}`));
      });
    }
  }

  async certConvert(file, options = {}) {
    try {
      const content = await fs.readFile(file);
      const base64 = content.toString('base64');

      const result = await this._request('POST', '/tools/cert-convert', {
        certificate_data: base64,
        source_format: 'auto',
        target_format: options.format || 'PEM',
      });

      console.log(chalk.bold(`\n🔄 Certificate Converted to ${options.format || 'PEM'}`));
      console.log(result.converted_data);
    } catch (error) {
      if (error.code === 'ENOENT') {
        console.error(chalk.red(`Error: File not found: ${file}`));
        process.exit(1);
      }
      throw error;
    }
  }

  async sslConvert(options = {}) {
    // OpenSSL-like certificate conversion
    // Usage: vb ssl-convert -inform DER -outform PEM -in cert.der [-out cert.pem]
    const inputFile = options.in;
    const outputFile = options.out;
    const inputFormat = (options.inform || 'auto').toUpperCase();
    const outputFormat = (options.outform || 'PEM').toUpperCase();

    if (!inputFile) {
      console.error(chalk.red('Error: Input file required (-in <file>)'));
      process.exit(1);
    }

    try {
      const content = await fs.readFile(inputFile);
      const base64 = content.toString('base64');

      const result = await this._request('POST', '/tools/cert-convert', {
        certificate_data: base64,
        source_format: inputFormat,
        target_format: outputFormat,
      });

      console.log(chalk.bold(`\n🔐 SSL Certificate Conversion`));
      console.log(`Format: ${inputFormat} → ${outputFormat}`);

      if (outputFile) {
        await fs.writeFile(outputFile, result.converted_data);
        console.log(chalk.green(`✓ Converted certificate saved to: ${outputFile}`));
      } else {
        console.log('\nConverted Certificate:');
        console.log(result.converted_data);
      }
    } catch (error) {
      if (error.code === 'ENOENT') {
        console.error(chalk.red(`Error: File not found: ${inputFile}`));
        process.exit(1);
      }
      throw error;
    }
  }

  async cryptoValidate(address, options = {}) {
    const result = await this._request('POST', '/crypto/validate', {
      address,
      crypto_type: options.type || 'bitcoin',
    });

    console.log(chalk.bold(`\n💰 Cryptocurrency Address Validation`));
    console.log(`Type: ${(options.type || 'bitcoin').toUpperCase()}`);
    console.log(`Valid: ${result.valid ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);

    if (result.address_type) {
      console.log(`Address Type: ${result.address_type}`);
    }

    if (result.network) {
      console.log(`Network: ${result.network}`);
    }

    if (result.warnings && result.warnings.length > 0) {
      console.log(chalk.bold('\n⚠️  Warnings:'));
      result.warnings.forEach(warning => {
        console.log(chalk.yellow(`  • ${warning}`));
      });
    }
  }

  async hashValidate(hash) {
    const result = await this._request('POST', '/tools/hash-validator', {
      hash,
    });

    console.log(chalk.bold('\n🔍 Hash Validation'));
    console.log(`Valid: ${result.valid ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);
    console.log(`Hash Type: ${chalk.cyan(result.hash_type || 'Unknown')}`);
    console.log(`Length: ${result.length} characters`);

    if (result.possible_types && result.possible_types.length > 0) {
      console.log(`\nPossible Types: ${result.possible_types.join(', ')}`);
    }
  }

  async stegDetect(file) {
    try {
      const content = await fs.readFile(file);
      const base64 = content.toString('base64');

      const result = await this._request('POST', '/steganography-detect', {
        file_data: base64,
        filename: file,
      });

      console.log(chalk.bold(`\n🎭 Steganography Detection: ${file}`));
      console.log(`Suspicious: ${result.suspicious ? chalk.red('✓ Yes') : chalk.green('✗ No')}`);
      console.log(`Confidence: ${chalk.yellow(result.confidence)}%`);

      if (result.indicators && result.indicators.length > 0) {
        console.log(chalk.bold('\n🔍 Indicators:'));
        result.indicators.forEach(indicator => {
          console.log(chalk.yellow(`  • ${indicator}`));
        });
      }

      if (result.analysis) {
        console.log(chalk.bold('\n📊 Analysis:'));
        console.log(`  ${result.analysis}`);
      }
    } catch (error) {
      if (error.code === 'ENOENT') {
        console.error(chalk.red(`Error: File not found: ${file}`));
        process.exit(1);
      }
      throw error;
    }
  }

  async bgpLookup(asNumber) {
    const result = await this._request('POST', '/bgp/asn', {
      asn: asNumber.replace(/^AS/i, ''),
    });

    console.log(chalk.bold(`\n🌐 BGP AS Lookup: ${asNumber}`));

    if (result.as_info) {
      const info = result.as_info;
      console.log(`AS Number: ${chalk.cyan(info.as_number)}`);
      console.log(`AS Name: ${info.as_name}`);
      if (info.country) console.log(`Country: ${info.country}`);
      if (info.registry) console.log(`Registry: ${info.registry}`);
      if (info.allocated) console.log(`Allocated: ${info.allocated}`);
    }

    if (result.prefixes && result.prefixes.length > 0) {
      console.log(chalk.bold(`\n📋 Prefixes (${result.prefixes.length}):`));
      result.prefixes.slice(0, 10).forEach(prefix => {
        console.log(`  • ${prefix}`);
      });
      if (result.prefixes.length > 10) {
        console.log(chalk.gray(`  ... and ${result.prefixes.length - 10} more`));
      }
    }

    if (result.peers && result.peers.length > 0) {
      console.log(chalk.bold(`\n🔗 Peers (${result.peers.length}):`));
      result.peers.slice(0, 5).forEach(peer => {
        console.log(`  • AS${peer}`);
      });
      if (result.peers.length > 5) {
        console.log(chalk.gray(`  ... and ${result.peers.length - 5} more`));
      }
    }
  }

  async csrValidate(file) {
    try {
      const content = await fs.readFile(file, 'utf8');

      const result = await this._request('POST', '/ssl/validate-csr', {
        csr: content,
      });

      console.log(chalk.bold('\n📝 CSR Validation'));
      console.log(`Valid: ${result.valid ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);

      if (result.csr_info) {
        const info = result.csr_info;
        console.log(chalk.bold('\n📋 CSR Details:'));
        console.log(`  Subject: ${info.subject}`);
        console.log(`  Public Key Algorithm: ${info.public_key_algorithm}`);
        console.log(`  Key Size: ${info.key_size} bits`);
        console.log(`  Signature Algorithm: ${info.signature_algorithm}`);

        if (info.san && info.san.length > 0) {
          console.log(`  SANs: ${info.san.join(', ')}`);
        }
      }

      if (result.warnings && result.warnings.length > 0) {
        console.log(chalk.bold('\n⚠️  Warnings:'));
        result.warnings.forEach(warning => {
          console.log(chalk.yellow(`  • ${warning}`));
        });
      }
    } catch (error) {
      if (error.code === 'ENOENT') {
        console.error(chalk.red(`Error: File not found: ${file}`));
        process.exit(1);
      }
      throw error;
    }
  }

  // Have I Been Pwned
  async hibpEmail(email) {
    const result = await this._request('POST', '/hibp/check-email', {
      email,
    });

    console.log(chalk.bold(`\n🔓 Data Breach Check: ${email}`));
    console.log(`Found in Breaches: ${result.breach_count}`);

    if (result.breaches && result.breaches.length > 0) {
      console.log(chalk.bold('\n⚠️  Breached Services:'));
      result.breaches.forEach((breach, i) => {
        console.log(`\n${i + 1}. ${chalk.red(breach.name)} (${breach.date})`);
        console.log(`   Compromised Data: ${breach.data_classes.join(', ')}`);
      });
    } else {
      console.log(chalk.green('\n✓ No breaches found!'));
    }
  }

  async hibpPassword(password) {
    const result = await this._request('POST', '/hibp/check-password', {
      password,
    });

    console.log(chalk.bold('\n🔐 Password Breach Check'));
    console.log(`Times Seen: ${chalk.yellow(result.count)}`);

    if (result.count > 0) {
      console.log(chalk.red(`\n⚠️  This password has been seen ${result.count} times in data breaches!`));
      console.log(chalk.yellow('Recommendation: Change this password immediately.'));
    } else {
      console.log(chalk.green('\n✓ Password not found in known breaches'));
    }
  }

  // Cloud Storage Security
  async cloudStorageSearch(query, options = {}) {
    const result = await this._request('POST', '/tools/cloud-storage/search', {
      query,
      provider: options.provider || 'all',
      search_type: options.searchType || 'filename',
    });

    console.log(chalk.bold(`\n☁️  Cloud Storage Search: ${query}`));
    console.log(`Results Found: ${result.results_count}`);

    if (result.results && result.results.length > 0) {
      console.log(chalk.bold('\n📦 Found:'));
      result.results.forEach((item, i) => {
        console.log(`\n${i + 1}. ${chalk.cyan(item.name)}`);
        console.log(`   Provider: ${item.provider}`);
        console.log(`   URL: ${item.url}`);
        if (item.public) console.log(chalk.red('   ⚠️  Public Access'));
      });
    }
  }

  async cloudStorageScan(bucket, options = {}) {
    const result = await this._request('POST', '/tools/cloud-storage/analyze-security', {
      bucket,
      provider: options.provider || 'aws',
    });

    console.log(chalk.bold(`\n☁️  Cloud Storage Security Scan: ${bucket}`));
    console.log(`Risk Score: ${chalk.yellow(result.risk_score)}/100`);
    console.log(`Public Access: ${result.public_access ? chalk.red('✗ Yes') : chalk.green('✓ No')}`);

    if (result.issues && result.issues.length > 0) {
      console.log(chalk.bold('\n⚠️  Security Issues:'));
      result.issues.forEach(issue => {
        console.log(chalk.yellow(`  • ${issue}`));
      });
    }
  }

  async cloudStorageBuckets(options = {}) {
    const result = await this._request('POST', '/tools/cloud-storage/list-buckets', {
      provider: options.provider || 'aws',
    });

    console.log(chalk.bold('\n☁️  Cloud Storage Buckets'));
    console.log(`Total: ${result.count}`);

    if (result.buckets && result.buckets.length > 0) {
      result.buckets.forEach((bucket, i) => {
        console.log(`\n${i + 1}. ${chalk.cyan(bucket.name)}`);
        console.log(`   Region: ${bucket.region}`);
        console.log(`   Public: ${bucket.public ? chalk.red('Yes') : chalk.green('No')}`);
      });
    }
  }

  // Granular Email Verification
  async emailSpf(domain) {
    const result = await this._request('POST', '/email/analyze-spf', {
      domain,
    });

    console.log(chalk.bold(`\n📧 SPF Analysis: ${domain}`));
    console.log(`Valid: ${result.valid ? chalk.green('✓') : chalk.red('✗')}`);

    if (result.record) {
      console.log(`\nSPF Record:\n${chalk.cyan(result.record)}`);
    }

    if (result.mechanisms) {
      console.log(chalk.bold('\n📋 Mechanisms:'));
      result.mechanisms.forEach(m => console.log(`  • ${m}`));
    }
  }

  async emailDkim(domain, options = {}) {
    const result = await this._request('POST', '/email/analyze-dkim', {
      domain,
      selector: options.selector || 'default',
    });

    console.log(chalk.bold(`\n📧 DKIM Analysis: ${domain}`));
    console.log(`Valid: ${result.valid ? chalk.green('✓') : chalk.red('✗')}`);

    if (result.public_key) {
      console.log(`\nPublic Key Found: ${chalk.green('✓')}`);
    }
  }

  async emailDmarc(domain) {
    const result = await this._request('POST', '/email/analyze-dmarc', {
      domain,
    });

    console.log(chalk.bold(`\n📧 DMARC Analysis: ${domain}`));
    console.log(`Valid: ${result.valid ? chalk.green('✓') : chalk.red('✗')}`);

    if (result.policy) {
      console.log(`Policy: ${chalk.cyan(result.policy)}`);
      console.log(`Percentage: ${result.percentage}%`);
    }
  }

  async emailMx(domain) {
    const result = await this._request('POST', '/email/analyze-mx', {
      domain,
    });

    console.log(chalk.bold(`\n📧 MX Records: ${domain}`));
    console.log(`Valid: ${result.valid ? chalk.green('✓') : chalk.red('✗')}`);

    if (result.records && result.records.length > 0) {
      console.log(chalk.bold('\n📬 Mail Servers:'));
      result.records.forEach((mx, i) => {
        console.log(`  ${i + 1}. ${mx.host} (Priority: ${mx.priority})`);
      });
    }
  }

  async emailDisposable(email) {
    const result = await this._request('POST', '/email/check-disposable', {
      email,
    });

    console.log(chalk.bold(`\n📧 Disposable Email Check: ${email}`));
    console.log(`Disposable: ${result.is_disposable ? chalk.red('✗ Yes') : chalk.green('✓ No')}`);

    if (result.provider) {
      console.log(`Provider: ${result.provider}`);
    }
  }

  async emailBlacklist(domain) {
    const result = await this._request('POST', '/email/check-blacklists', {
      domain,
    });

    console.log(chalk.bold(`\n📧 Email Blacklist Check: ${domain}`));
    console.log(`Listed: ${result.is_listed ? chalk.red('✗ Yes') : chalk.green('✓ No')}`);
    console.log(`Lists Checked: ${result.lists_checked}`);

    if (result.blacklists && result.blacklists.length > 0) {
      console.log(chalk.bold('\n❌ Found on Blacklists:'));
      result.blacklists.forEach(bl => {
        console.log(chalk.red(`  • ${bl.name}`));
      });
    }
  }

  async emailScore(domain) {
    const result = await this._request('POST', '/email/deliverability-score', {
      domain,
    });

    console.log(chalk.bold(`\n📧 Email Deliverability Score: ${domain}`));
    console.log(`Score: ${chalk.yellow(result.score)}/100`);
    console.log(`Grade: ${this._colorizeGrade(result.grade)}`);

    if (result.factors) {
      console.log(chalk.bold('\n📊 Factors:'));
      Object.entries(result.factors).forEach(([key, value]) => {
        console.log(`  ${key}: ${value}`);
      });
    }
  }

  // SSL/TLS Tools
  async sslResolveChain(urlOrFile, options = {}) {
    const data = options.file ?
      { certificate_file: urlOrFile } :
      { url: urlOrFile, port: options.port || 443 };

    const result = await this._request('POST', '/ssl/resolve-chain', data);

    console.log(chalk.bold('\n🔐 SSL Chain Resolution'));
    console.log(`Chain Complete: ${result.complete ? chalk.green('✓') : chalk.red('✗')}`);
    console.log(`Certificate Count: ${result.chain_length}`);

    if (result.chain && result.chain.length > 0) {
      console.log(chalk.bold('\n📜 Certificate Chain:'));
      result.chain.forEach((cert, i) => {
        console.log(`\n${i + 1}. ${chalk.cyan(cert.subject)}`);
        console.log(`   Issuer: ${cert.issuer}`);
        console.log(`   Valid: ${cert.valid_from} to ${cert.valid_to}`);
      });
    }
  }

  async sslVerifyKeypair(certFile, keyFile) {
    const certData = await fs.readFile(certFile, 'utf8');
    const keyData = await fs.readFile(keyFile, 'utf8');

    const result = await this._request('POST', '/ssl/verify-key-pair', {
      certificate: certData,
      private_key: keyData,
    });

    console.log(chalk.bold('\n🔐 SSL Key Pair Verification'));
    console.log(`Match: ${result.match ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);

    if (!result.match && result.reason) {
      console.log(chalk.red(`\nReason: ${result.reason}`));
    }
  }

  // File/Transaction Verification
  async verifyFile(file, options = {}) {
    const content = await fs.readFile(file);
    const base64 = content.toString('base64');

    const result = await this._request('POST', '/verify/file', {
      file_data: base64,
      filename: file,
      expected_hash: options.hash,
    });

    console.log(chalk.bold(`\n✓ File Verification: ${file}`));
    console.log(`Hash (SHA256): ${chalk.cyan(result.hash)}`);

    if (options.hash) {
      console.log(`Match: ${result.hash_match ? chalk.green('✓') : chalk.red('✗')}`);
    }

    console.log(`VeriScore: ${chalk.yellow(result.veri_score)}/100`);
  }

  async verifyEmail(email) {
    const result = await this._request('POST', '/verify/email', {
      email,
    });

    console.log(chalk.bold(`\n✓ Email Verification: ${email}`));
    console.log(`Valid Format: ${result.valid_format ? chalk.green('✓') : chalk.red('✗')}`);
    console.log(`VeriScore: ${chalk.yellow(result.veri_score)}/100`);
  }

  async verifyTransaction(txHash, options = {}) {
    const result = await this._request('POST', '/verify/tx', {
      tx: txHash,
      network: options.network || 'bitcoin',
    });

    console.log(chalk.bold(`\n✓ Transaction Verification: ${txHash}`));
    console.log(`Network: ${result.network}`);
    console.log(`Confirmed: ${result.confirmed ? chalk.green('✓') : chalk.yellow('Pending')}`);
    console.log(`VeriScore: ${chalk.yellow(result.veri_score)}/100`);
  }

  // Tool Discovery
  async toolSearch(query, options = {}) {
    const result = await this._request('GET', `/tools/search?q=${encodeURIComponent(query)}`);

    console.log(chalk.bold(`\n🔍 Tool Search: ${query}`));
    console.log(`Results: ${result.count}`);

    if (result.tools && result.tools.length > 0) {
      console.log(chalk.bold('\n📋 Found:'));
      result.tools.forEach((tool, i) => {
        console.log(`\n${i + 1}. ${chalk.cyan(tool.name)}`);
        console.log(`   Category: ${tool.category}`);
        console.log(`   ${tool.description}`);
        if (options.verbose) {
          console.log(`   CLI: ${chalk.yellow(tool.cli_command)}`);
        }
      });
    }
  }

  async toolList(options = {}) {
    const result = await this._request('GET', '/tools/list');

    console.log(chalk.bold('\n📋 Available Tools'));
    console.log(`Total: ${result.count}`);

    if (result.tools && result.tools.length > 0) {
      const grouped = {};
      result.tools.forEach(tool => {
        if (!grouped[tool.category]) grouped[tool.category] = [];
        grouped[tool.category].push(tool);
      });

      Object.entries(grouped).forEach(([category, tools]) => {
        console.log(chalk.bold(`\n${category}:`));
        tools.forEach(tool => {
          console.log(`  • ${tool.name}`);
          if (options.verbose) {
            console.log(`    ${tool.description}`);
          }
        });
      });
    }
  }

  async healthCheck() {
    const result = await this._request('GET', '/health');

    console.log(chalk.bold('\n🏥 API Health Check'));
    console.log(`Status: ${result.status === 'ok' ? chalk.green('✓ Healthy') : chalk.red('✗ Down')}`);

    if (result.timestamp) {
      console.log(`Time: ${result.timestamp}`);
    }
  }

  // WHOIS Lookup
  async whoisLookup(domain) {
    const result = await this._request('POST', '/tools/whois', {
      domain,
    });

    console.log(chalk.bold(`\n🔍 WHOIS Lookup: ${domain}`));

    if (result.domain_info) {
      const info = result.domain_info;
      console.log(`\nRegistrar: ${info.registrar}`);
      console.log(`Created: ${info.created_date}`);
      console.log(`Expires: ${info.expiry_date}`);
      console.log(`Status: ${info.status}`);

      if (info.nameservers) {
        console.log(chalk.bold('\n📡 Nameservers:'));
        info.nameservers.forEach(ns => console.log(`  • ${ns}`));
      }
    }
  }

  // Malware Scan
  async malwareScan(file) {
    const content = await fs.readFile(file);
    const base64 = content.toString('base64');

    const result = await this._request('POST', '/verify/malware', {
      file_data: base64,
      filename: file,
    });

    console.log(chalk.bold(`\n🛡️  Malware Scan: ${file}`));
    console.log(`Clean: ${result.is_clean ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);
    console.log(`Threats Found: ${result.threats_found}`);

    if (result.threats && result.threats.length > 0) {
      console.log(chalk.bold('\n⚠️  Detected Threats:'));
      result.threats.forEach(threat => {
        console.log(chalk.red(`  • ${threat.name} (${threat.type})`));
      });
    }
  }

  // Archive Inspection
  async inspectArchive(file) {
    const content = await fs.readFile(file);
    const base64 = content.toString('base64');

    const result = await this._request('POST', '/inspect/archive', {
      file_data: base64,
      filename: file,
    });

    console.log(chalk.bold(`\n📦 Archive Inspection: ${file}`));
    console.log(`Type: ${result.archive_type}`);
    console.log(`Files: ${result.file_count}`);
    console.log(`Total Size: ${result.total_size_bytes} bytes`);

    if (result.files && result.files.length > 0) {
      console.log(chalk.bold('\n📄 Contents:'));
      result.files.slice(0, 20).forEach(f => {
        console.log(`  • ${f.name} (${f.size} bytes)`);
      });
      if (result.files.length > 20) {
        console.log(chalk.gray(`  ... and ${result.files.length - 20} more files`));
      }
    }
  }

  // PCAP Analyzer
  async pcapAnalyze(file) {
    try {
      const content = await fs.readFile(file);
      const base64 = content.toString('base64');

      const result = await this._request('POST', '/tools/pcap-analyze', {
        file_data: base64,
        filename: file,
      });

      console.log(chalk.bold(`\n📊 PCAP Analysis: ${file}`));
      console.log(`Total Packets: ${chalk.yellow(result.total_packets)}`);
      console.log(`File Size: ${result.file_size} bytes`);
      console.log(`Duration: ${result.duration || 'N/A'}`);

      if (result.protocols) {
        console.log(chalk.bold('\n📋 Protocols:'));
        Object.entries(result.protocols).forEach(([proto, count]) => {
          console.log(`  ${proto}: ${count} packets`);
        });
      }

      if (result.top_talkers && result.top_talkers.length > 0) {
        console.log(chalk.bold('\n👥 Top Talkers:'));
        result.top_talkers.slice(0, 10).forEach((talker, i) => {
          console.log(`  ${i + 1}. ${chalk.cyan(talker.ip)} - ${talker.packets} packets (${talker.bytes} bytes)`);
        });
      }

      if (result.suspicious_activity && result.suspicious_activity.length > 0) {
        console.log(chalk.bold('\n⚠️  Suspicious Activity:'));
        result.suspicious_activity.forEach(activity => {
          console.log(chalk.yellow(`  • ${activity}`));
        });
      }
    } catch (error) {
      if (error.code === 'ENOENT') {
        console.error(chalk.red(`Error: File not found: ${file}`));
        process.exit(1);
      }
      throw error;
    }
  }

  // Firewall Editor - Upload
  async firewallUpload(file, options = {}) {
    try {
      const FormData = require('form-data');
      const formData = new FormData();

      const fileStream = require('fs').createReadStream(file);
      formData.append('config_file', fileStream);
      formData.append('firewall_type', options.type || 'iptables');
      formData.append('device_name', options.name || 'CLI Upload');

      const url = `${API_BASE}/firewall/upload`;
      const response = await axios.post(url, formData, {
        headers: {
          ...this.headers,
          ...formData.getHeaders(),
        },
      });

      const result = response.data.data;

      console.log(chalk.bold(`\n🔥 Firewall Configuration Upload`));
      console.log(`Type: ${result.type}`);
      console.log(`Device: ${result.deviceName}`);
      console.log(`Total Chains: ${result.stats.total_chains}`);
      console.log(`Total Rules: ${result.stats.total_rules}`);

      if (result.chains) {
        console.log(chalk.bold('\n📋 Chains:'));
        Object.entries(result.chains).forEach(([name, chain]) => {
          console.log(`  ${chalk.cyan(name)}: ${chain.policy} (${chain.rules.length} rules)`);
        });
      }
    } catch (error) {
      if (error.code === 'ENOENT') {
        console.error(chalk.red(`Error: File not found: ${file}`));
        process.exit(1);
      }
      throw error;
    }
  }

  // Firewall Editor - List
  async firewallList(options = {}) {
    const params = new URLSearchParams();
    if (options.device) params.append('device_name', options.device);
    if (options.type) params.append('config_type', options.type);

    const result = await this._request('GET', `/firewall/list?${params.toString()}`);

    console.log(chalk.bold('\n🔥 Firewall Configurations'));
    console.log(`Total: ${result.total}`);

    if (result.configs && result.configs.length > 0) {
      console.log(chalk.bold('\n📋 Saved Configs:'));
      result.configs.forEach((config, i) => {
        console.log(`\n${i + 1}. ${chalk.cyan(config.device_name)} (${config.config_type})`);
        console.log(`   ID: ${config.id}`);
        console.log(`   Version: ${config.version}`);
        console.log(`   Created: ${config.created_at}`);
        if (config.description) console.log(`   Description: ${config.description}`);
      });
    } else {
      console.log(chalk.gray('\nNo configurations found'));
    }
  }

  // Firewall Editor - Get
  async firewallGet(id) {
    const result = await this._request('GET', `/firewall/get?id=${id}`);

    console.log(chalk.bold('\n🔥 Firewall Configuration'));
    console.log(`Device: ${result.device_name}`);
    console.log(`Type: ${result.config_type}`);
    console.log(`Version: ${result.version}`);
    console.log(`Created: ${result.created_at}`);
    if (result.description) console.log(`Description: ${result.description}`);

    console.log(chalk.bold('\n📄 Configuration:'));
    console.log(result.config_data);
  }

  // Hash Generator
  async hashGenerate(text, options = {}) {
    const algorithm = options.algorithm || 'sha256';

    const result = await this._request('POST', '/tools/generate-hash', {
      input: text,
      algorithm: algorithm,
    });

    console.log(chalk.bold(`\n🔐 Hash Generated (${algorithm.toUpperCase()})`));
    console.log(chalk.cyan(result.hash));

    if (result.time_ms) {
      console.log(chalk.gray(`\nComputed in ${result.time_ms}ms`));
    }
  }

  // JSON/YAML Validator
  async jsonValidate(input, options = {}) {
    let content = input;

    // Check if input is a file
    try {
      const stats = await fs.stat(input);
      if (stats.isFile()) {
        content = await fs.readFile(input, 'utf8');
      }
    } catch (e) {
      // Not a file, treat as raw input
    }

    const format = options.format || 'json';

    const result = await this._request('POST', '/tools/validate-data', {
      content,
      format,
    });

    console.log(chalk.bold(`\n📝 ${format.toUpperCase()} Validation`));
    console.log(`Valid: ${result.valid ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);

    if (result.errors && result.errors.length > 0) {
      console.log(chalk.bold('\n❌ Errors:'));
      result.errors.forEach(error => {
        console.log(chalk.red(`  • ${error}`));
      });
    }

    if (result.formatted && options.format) {
      console.log(chalk.bold('\n📄 Formatted Output:'));
      console.log(result.formatted);
    }

    if (result.stats) {
      console.log(chalk.bold('\n📊 Stats:'));
      Object.entries(result.stats).forEach(([key, value]) => {
        console.log(`  ${key}: ${value}`);
      });
    }
  }

  // Base64 Encode
  async base64Encode(input, options = {}) {
    let content = input;

    // Check if input is a file
    if (options.file) {
      content = await fs.readFile(input);
      const encoded = content.toString('base64');
      console.log(chalk.bold('\n📦 Base64 Encoded:'));
      console.log(chalk.cyan(encoded));
      return;
    }

    const result = await this._request('POST', '/tools/base64-encoder', {
      input: content,
      operation: 'encode',
    });

    console.log(chalk.bold('\n📦 Base64 Encoded:'));
    console.log(chalk.cyan(result.output));
  }

  // Base64 Decode
  async base64Decode(input, options = {}) {
    const result = await this._request('POST', '/tools/base64-encoder', {
      input,
      operation: 'decode',
    });

    console.log(chalk.bold('\n📦 Base64 Decoded:'));
    console.log(chalk.cyan(result.output));

    if (options.output) {
      await fs.writeFile(options.output, result.output);
      console.log(chalk.green(`\n✓ Saved to: ${options.output}`));
    }
  }

  // Docker Scan (placeholder - implementation depends on backend)
  async dockerScan(file) {
    try {
      const content = await fs.readFile(file, 'utf8');

      // Note: Adjust endpoint when backend is available
      const result = await this._request('POST', '/tools/docker-scan', {
        dockerfile: content,
        filename: file,
      });

      console.log(chalk.bold(`\n🐳 Dockerfile Security Scan: ${file}`));
      console.log(`Risk Score: ${chalk.yellow(result.risk_score || 0)}/100`);

      if (result.issues && result.issues.length > 0) {
        console.log(chalk.bold('\n⚠️  Issues Found:'));
        result.issues.forEach((issue, i) => {
          const icon = this._getSeverityIcon(issue.severity);
          console.log(`\n${i + 1}. ${icon} ${issue.title}`);
          console.log(`   Severity: ${this._colorizeSeverity(issue.severity)}`);
          console.log(chalk.gray(`   ${issue.description}`));
          if (issue.recommendation) {
            console.log(chalk.blue(`   💡 ${issue.recommendation}`));
          }
        });
      } else {
        console.log(chalk.green('\n✓ No security issues found!'));
      }
    } catch (error) {
      if (error.code === 'ENOENT') {
        console.error(chalk.red(`Error: File not found: ${file}`));
        process.exit(1);
      }
      throw error;
    }
  }

  // Terraform Scan (placeholder - implementation depends on backend)
  async terraformScan(file) {
    try {
      const content = await fs.readFile(file, 'utf8');

      // Note: Adjust endpoint when backend is available
      const result = await this._request('POST', '/tools/terraform-scan', {
        terraform_config: content,
        filename: file,
      });

      console.log(chalk.bold(`\n🏗️  Terraform Security Scan: ${file}`));
      console.log(`Risk Score: ${chalk.yellow(result.risk_score || 0)}/100`);

      if (result.issues && result.issues.length > 0) {
        console.log(chalk.bold('\n⚠️  Issues Found:'));
        result.issues.forEach((issue, i) => {
          const icon = this._getSeverityIcon(issue.severity);
          console.log(`\n${i + 1}. ${icon} ${issue.title}`);
          console.log(`   Severity: ${this._colorizeSeverity(issue.severity)}`);
          console.log(`   Resource: ${issue.resource || 'N/A'}`);
          console.log(chalk.gray(`   ${issue.description}`));
          if (issue.recommendation) {
            console.log(chalk.blue(`   💡 ${issue.recommendation}`));
          }
        });
      } else {
        console.log(chalk.green('\n✓ No security issues found!'));
      }
    } catch (error) {
      if (error.code === 'ENOENT') {
        console.error(chalk.red(`Error: File not found: ${file}`));
        process.exit(1);
      }
      throw error;
    }
  }

  // Kubernetes Validate (placeholder - implementation depends on backend)
  async k8sValidate(file) {
    try {
      const content = await fs.readFile(file, 'utf8');

      // Note: Adjust endpoint when backend is available
      const result = await this._request('POST', '/tools/k8s-validate', {
        manifest: content,
        filename: file,
      });

      console.log(chalk.bold(`\n☸️  Kubernetes Manifest Validation: ${file}`));
      console.log(`Valid: ${result.valid ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);

      if (result.errors && result.errors.length > 0) {
        console.log(chalk.bold('\n❌ Errors:'));
        result.errors.forEach(error => {
          console.log(chalk.red(`  • ${error}`));
        });
      }

      if (result.warnings && result.warnings.length > 0) {
        console.log(chalk.bold('\n⚠️  Warnings:'));
        result.warnings.forEach(warning => {
          console.log(chalk.yellow(`  • ${warning}`));
        });
      }

      if (result.security_issues && result.security_issues.length > 0) {
        console.log(chalk.bold('\n🔒 Security Issues:'));
        result.security_issues.forEach(issue => {
          console.log(chalk.red(`  • ${issue}`));
        });
      }

      if (result.valid && (!result.warnings || result.warnings.length === 0)) {
        console.log(chalk.green('\n✓ Manifest is valid!'));
      }
    } catch (error) {
      if (error.code === 'ENOENT') {
        console.error(chalk.red(`Error: File not found: ${file}`));
        process.exit(1);
      }
      throw error;
    }
  }

  // DNSSEC Validate
  async dnssecValidate(domain) {
    const result = await this._request('POST', '/tools/dnssec-validate', {
      domain,
    });

    console.log(chalk.bold(`\n🔐 DNSSEC Validation: ${domain}`));
    console.log(`DNSSEC Enabled: ${result.dnssec_enabled ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);

    if (result.dnssec_enabled) {
      console.log(`Valid: ${result.valid ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);

      if (result.ds_records && result.ds_records.length > 0) {
        console.log(chalk.bold('\n📋 DS Records:'));
        result.ds_records.forEach((record, i) => {
          console.log(`  ${i + 1}. Key Tag: ${record.key_tag}, Algorithm: ${record.algorithm}`);
        });
      }

      if (result.dnskey_records && result.dnskey_records.length > 0) {
        console.log(chalk.bold('\n🔑 DNSKEY Records:'));
        result.dnskey_records.forEach((record, i) => {
          console.log(`  ${i + 1}. Flags: ${record.flags}, Protocol: ${record.protocol}, Algorithm: ${record.algorithm}`);
        });
      }
    }

    if (result.errors && result.errors.length > 0) {
      console.log(chalk.bold('\n❌ Errors:'));
      result.errors.forEach(error => {
        console.log(chalk.red(`  • ${error}`));
      });
    }
  }

  // DNS Propagation
  async dnsPropagation(domain, options = {}) {
    const result = await this._request('POST', '/tools/dns-propagation', {
      domain,
      record_type: options.type || 'A',
    });

    console.log(chalk.bold(`\n🌍 DNS Propagation Check: ${domain}`));
    console.log(`Record Type: ${options.type || 'A'}`);
    console.log(`Propagated: ${result.propagated ? chalk.green('✓ Yes') : chalk.yellow('⚠ Partial')}`);
    console.log(`Locations Checked: ${result.locations_checked}`);
    console.log(`Consistent: ${result.consistent ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);

    if (result.results && result.results.length > 0) {
      console.log(chalk.bold('\n📍 Results by Location:'));
      result.results.forEach((loc, i) => {
        const status = loc.success ? chalk.green('✓') : chalk.red('✗');
        console.log(`\n${i + 1}. ${status} ${loc.location} (${loc.server})`);
        if (loc.records && loc.records.length > 0) {
          loc.records.forEach(record => {
            console.log(`   ${chalk.cyan(record)}`);
          });
        } else if (loc.error) {
          console.log(chalk.red(`   Error: ${loc.error}`));
        }
      });
    }
  }

  // Reverse DNS
  async reverseDns(ip) {
    const result = await this._request('POST', '/tools/reverse-dns', {
      ip,
    });

    console.log(chalk.bold(`\n🔄 Reverse DNS Lookup: ${ip}`));
    console.log(`Has PTR: ${result.has_ptr ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);

    if (result.ptr_records && result.ptr_records.length > 0) {
      console.log(chalk.bold('\n📋 PTR Records:'));
      result.ptr_records.forEach((record, i) => {
        console.log(`  ${i + 1}. ${chalk.cyan(record)}`);
      });
    }

    if (result.forward_match !== undefined) {
      console.log(`\nForward Match: ${result.forward_match ? chalk.green('✓ Yes') : chalk.red('✗ No')}`);
      if (result.forward_ips && result.forward_ips.length > 0) {
        console.log('Forward IPs:');
        result.forward_ips.forEach(ip => {
          console.log(`  • ${ip}`);
        });
      }
    }

    if (result.warnings && result.warnings.length > 0) {
      console.log(chalk.bold('\n⚠️  Warnings:'));
      result.warnings.forEach(warning => {
        console.log(chalk.yellow(`  • ${warning}`));
      });
    }
  }

  // Helper methods
  _getSeverityIcon(severity) {
    const icons = {
      critical: '🔴',
      high: '🟠',
      medium: '🟡',
      low: '🟢',
    };
    return icons[severity] || '⚪';
  }

  _colorizeSeverity(severity) {
    const colors = {
      critical: chalk.red,
      high: chalk.red,
      medium: chalk.yellow,
      low: chalk.green,
    };
    const color = colors[severity] || chalk.white;
    return color(severity.toUpperCase());
  }

  _colorizeRisk(level) {
    const colors = {
      critical: chalk.red,
      high: chalk.red,
      medium: chalk.yellow,
      low: chalk.green,
    };
    const color = colors[level.toLowerCase()] || chalk.white;
    return color(level.toUpperCase());
  }

  _colorizeGrade(grade) {
    const colors = {
      'A': chalk.green,
      'B': chalk.green,
      'C': chalk.yellow,
      'D': chalk.red,
      'F': chalk.red,
    };
    const color = colors[grade] || chalk.white;
    return color(grade);
  }

  async startConsole() {
    const readline = require('readline');

    // Available commands for autocomplete
    const commands = [
      'iam-analyze', 'secrets-scan', 'db-audit', 'security-headers',
      'jwt-decode', 'hash', 'regex', 'pgp-validate', 'file-magic',
      'dns-validate', 'zone-validate', 'ip-calc', 'rbl-check', 'email-verify',
      'traceroute', 'url-encode', 'base64', 'ssl-check', 'cert-convert', 'ssl-convert',
      'crypto-validate', 'hash-validate', 'steg-detect', 'bgp-lookup', 'csr-validate',
      'hibp-email', 'hibp-password',
      'cloud-storage-search', 'cloud-storage-scan', 'cloud-storage-buckets',
      'email-spf', 'email-dkim', 'email-dmarc', 'email-mx',
      'email-disposable', 'email-blacklist', 'email-score',
      'ssl-resolve-chain', 'ssl-verify-keypair',
      'verify-file', 'verify-email', 'verify-tx',
      'tool-search', 'tool-list', 'health', 'whois', 'malware-scan', 'inspect-archive',
      'help', 'exit', 'quit', 'clear'
    ];

    const rl = readline.createInterface({
      input: process.stdin,
      output: process.stdout,
      prompt: chalk.cyan('veribits> '),
      completer: (line) => {
        const hits = commands.filter((c) => c.startsWith(line));
        return [hits.length ? hits : commands, line];
      }
    });

    console.log(chalk.bold.cyan('\n╔════════════════════════════════════════════════════════════╗'));
    console.log(chalk.bold.cyan('║           VeriBits Interactive Console v2.0.0              ║'));
    console.log(chalk.bold.cyan('╚════════════════════════════════════════════════════════════╝'));
    console.log(chalk.gray('\nType ') + chalk.white('help') + chalk.gray(' for available commands or ') + chalk.white('exit') + chalk.gray(' to quit\n'));

    if (this.apiKey) {
      console.log(chalk.green('✓ Authenticated with API key\n'));
    } else {
      console.log(chalk.yellow('⚠ Running without API key (rate limits apply)\n'));
    }

    rl.prompt();

    rl.on('line', async (line) => {
      const trimmed = line.trim();

      if (!trimmed) {
        rl.prompt();
        return;
      }

      const args = trimmed.split(/\s+/);
      const cmd = args[0];

      try {
        // Built-in console commands
        if (cmd === 'exit' || cmd === 'quit') {
          console.log(chalk.cyan('\n👋 Goodbye!\n'));
          process.exit(0);
        }

        if (cmd === 'clear' || cmd === 'cls') {
          console.clear();
          rl.prompt();
          return;
        }

        if (cmd === 'help') {
          this._showConsoleHelp();
          rl.prompt();
          return;
        }

        // Execute command based on input
        await this._executeCommand(cmd, args.slice(1));

      } catch (error) {
        if (error.message !== 'EXIT_CONSOLE') {
          console.error(chalk.red(`Error: ${error.message}`));
        }
      }

      console.log(); // Empty line for readability
      rl.prompt();
    });

    rl.on('close', () => {
      console.log(chalk.cyan('\n👋 Goodbye!\n'));
      process.exit(0);
    });
  }

  _showConsoleHelp() {
    console.log(chalk.bold('\n📚 VeriBits Console Commands\n'));

    console.log(chalk.bold.cyan('Security & IAM:'));
    console.log('  iam-analyze <file>          Analyze AWS IAM policy');
    console.log('  secrets-scan <file>         Scan for exposed secrets');
    console.log('  db-audit <connection>       Audit database connection');
    console.log('  security-headers <url>      Analyze HTTP security headers');

    console.log(chalk.bold.cyan('\nDeveloper Tools:'));
    console.log('  jwt-decode <token>          Decode JWT token');
    console.log('  hash <text>                 Generate hash');
    console.log('  regex <pattern> <text>      Test regex pattern');
    console.log('  url-encode <text>           URL encode/decode');
    console.log('  base64 <text>               Base64 encode/decode');

    console.log(chalk.bold.cyan('\nNetwork Tools:'));
    console.log('  dns-validate <domain>       Validate DNS records');
    console.log('  ip-calc <cidr>              Calculate IP subnet');
    console.log('  rbl-check <ip>              Check RBL/DNSBL');
    console.log('  traceroute <host>           Visual traceroute');
    console.log('  bgp-lookup <asn>            BGP AS lookup');

    console.log(chalk.bold.cyan('\nEmail Tools:'));
    console.log('  email-verify <email>        Comprehensive email check');
    console.log('  email-spf <domain>          SPF analysis');
    console.log('  email-dmarc <domain>        DMARC analysis');
    console.log('  hibp-email <email>          Check data breaches');

    console.log(chalk.bold.cyan('\nSSL/TLS:'));
    console.log('  ssl-check <host>            Check SSL certificate');
    console.log('  ssl-convert -in <file>      Convert certificate format');
    console.log('  ssl-resolve-chain <url>     Resolve certificate chain');

    console.log(chalk.bold.cyan('\nCloud Security:'));
    console.log('  cloud-storage-scan <bucket> Scan cloud storage');
    console.log('  malware-scan <file>         Scan for malware');

    console.log(chalk.bold.cyan('\nUtilities:'));
    console.log('  tool-search <query>         Search tools');
    console.log('  tool-list                   List all tools');
    console.log('  health                      API health check');
    console.log('  whois <domain>              WHOIS lookup');

    console.log(chalk.bold.cyan('\nConsole Commands:'));
    console.log('  help                        Show this help');
    console.log('  clear                       Clear screen');
    console.log('  exit, quit                  Exit console');

    console.log(chalk.gray('\nTip: Use TAB for command completion\n'));
  }

  async _executeCommand(cmd, args) {
    // Map console commands to methods
    switch (cmd) {
      case 'iam-analyze':
        if (!args[0]) throw new Error('Usage: iam-analyze <policy-file>');
        await this.iamAnalyze(args[0]);
        break;

      case 'secrets-scan':
        if (!args[0]) throw new Error('Usage: secrets-scan <file>');
        await this.secretsScan(args[0]);
        break;

      case 'db-audit':
        if (!args[0]) throw new Error('Usage: db-audit <connection-string>');
        await this.dbAudit(args[0]);
        break;

      case 'security-headers':
        if (!args[0]) throw new Error('Usage: security-headers <url>');
        await this.securityHeaders(args[0]);
        break;

      case 'jwt-decode':
        if (!args[0]) throw new Error('Usage: jwt-decode <token>');
        await this.jwtDecode(args[0], {});
        break;

      case 'hash':
        if (!args[0]) throw new Error('Usage: hash <text> [-a algorithm]');
        const hashAlgo = args.includes('-a') ? args[args.indexOf('-a') + 1] : 'sha256';
        await this.hash(args[0], { algorithm: hashAlgo });
        break;

      case 'regex':
        if (!args[0] || !args[1]) throw new Error('Usage: regex <pattern> <text>');
        await this.regex(args[0], args[1], {});
        break;

      case 'dns-validate':
        if (!args[0]) throw new Error('Usage: dns-validate <domain>');
        await this.dnsValidate(args[0], {});
        break;

      case 'ip-calc':
        if (!args[0]) throw new Error('Usage: ip-calc <ip-or-cidr>');
        await this.ipCalculate(args[0]);
        break;

      case 'rbl-check':
        if (!args[0]) throw new Error('Usage: rbl-check <ip>');
        await this.rblCheck(args[0]);
        break;

      case 'email-verify':
        if (!args[0]) throw new Error('Usage: email-verify <email>');
        await this.emailVerify(args[0]);
        break;

      case 'email-spf':
        if (!args[0]) throw new Error('Usage: email-spf <domain>');
        await this.emailSpf(args[0]);
        break;

      case 'email-dmarc':
        if (!args[0]) throw new Error('Usage: email-dmarc <domain>');
        await this.emailDmarc(args[0]);
        break;

      case 'hibp-email':
        if (!args[0]) throw new Error('Usage: hibp-email <email>');
        await this.hibpEmail(args[0]);
        break;

      case 'ssl-check':
        if (!args[0]) throw new Error('Usage: ssl-check <host>');
        await this.sslCheck(args[0], {});
        break;

      case 'cloud-storage-scan':
        if (!args[0]) throw new Error('Usage: cloud-storage-scan <bucket>');
        await this.cloudStorageScan(args[0], {});
        break;

      case 'tool-search':
        if (!args[0]) throw new Error('Usage: tool-search <query>');
        await this.toolSearch(args[0], {});
        break;

      case 'tool-list':
        await this.toolList({});
        break;

      case 'health':
        await this.healthCheck();
        break;

      case 'whois':
        if (!args[0]) throw new Error('Usage: whois <domain>');
        await this.whoisLookup(args[0]);
        break;

      case 'url-encode':
        if (!args[0]) throw new Error('Usage: url-encode <text>');
        await this.urlEncode(args[0], {});
        break;

      case 'base64':
        if (!args[0]) throw new Error('Usage: base64 <text>');
        await this.base64(args[0], {});
        break;

      default:
        console.log(chalk.yellow(`Unknown command: ${cmd}`));
        console.log(chalk.gray('Type "help" for available commands'));
    }
  }
}

module.exports = VeriBitsCLI;
