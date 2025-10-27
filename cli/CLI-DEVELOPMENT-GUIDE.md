# VeriBits CLI Development Guide

## Overview

VeriBits provides three CLI implementations with comprehensive API coverage:

- **Node.js CLI**: 24 commands via npm (`veribits` / `vb`)
- **Python CLI**: 24 commands via pip (`veribits` / `vb`)
- **PHP CLI**: 32 commands (standalone script)

## Feature Summary

### Expanded Features (Node.js & Python)

Both Node.js and Python CLIs were expanded from 9 to 24 commands, adding:

1. **Network Tools**
   - `dns-validate` - Validate DNS records
   - `zone-validate` - Validate DNS zone files
   - `ip-calc` - Calculate IP subnets
   - `rbl-check` - Check RBL/DNSBL blacklists
   - `traceroute` - Visual traceroute
   - `bgp-lookup` - BGP AS information

2. **Email Verification**
   - `email-verify` - Comprehensive email verification (SPF, DKIM, DMARC, MX)

3. **Encoding Tools**
   - `url-encode` - URL encode/decode
   - `base64` - Base64 encode/decode

4. **SSL/TLS Tools**
   - `ssl-check` - SSL certificate checking
   - `cert-convert` - Certificate format conversion
   - `csr-validate` - CSR validation

5. **Security Tools**
   - `crypto-validate` - Cryptocurrency address validation
   - `hash-validate` - Hash type identification
   - `steg-detect` - Steganography detection

### Existing Features (All CLIs)

- `iam-analyze` - AWS IAM policy analysis
- `secrets-scan` - Secrets detection
- `db-audit` - Database connection auditing
- `security-headers` - HTTP security headers analysis
- `jwt-decode` - JWT token decoding
- `hash` - Cryptographic hash generation
- `regex` - Regular expression testing
- `pgp-validate` - PGP key validation
- `file-magic` - File type detection

## Directory Structure

```
cli/
├── nodejs/               # Node.js/npm distribution
│   ├── bin/
│   │   └── veribits.js   # Main CLI entry point
│   ├── lib/
│   │   └── cli.js        # Implementation
│   ├── package.json      # npm package config
│   └── README.md
│
├── python/               # Python/pip distribution
│   ├── veribits.py       # Main script
│   ├── setup.py          # pip package config
│   └── README.md
│
├── veribits.php          # PHP standalone CLI
│
├── publish-cli.sh        # Publishing script
├── test-all-clis.sh      # Test suite
└── update-cli-repos.sh   # Repository sync script
```

## Development Workflow

### 1. Testing

Test all CLIs locally:

```bash
# Make sure Node.js CLI is linked
cd cli/nodejs && npm link && cd ../..

# Run comprehensive tests
./cli/test-all-clis.sh
```

### 2. Publishing

When ready to publish a new version:

```bash
# Publish to npm and PyPI
./cli/publish-cli.sh 1.0.1

# This will:
# - Update version numbers
# - Test installations
# - Publish to npm (Node.js)
# - Publish to PyPI (Python)
# - Show PHP distribution instructions
```

### 3. Repository Sync

If you maintain separate repositories for distribution:

```bash
# Configure repositories
export NODEJS_REPO='git@github.com:afterdarksystems/veribits-cli-node.git'
export PYTHON_REPO='git@github.com:afterdarksystems/veribits-cli-python.git'
export PHP_REPO='git@github.com:afterdarksystems/veribits-cli-php.git'

# Sync code to repositories
./cli/update-cli-repos.sh
```

## Installation

### For Users

```bash
# Node.js
npm install -g veribits

# Python
pip install veribits

# PHP
wget https://www.veribits.com/cli/veribits.php
chmod +x veribits.php
```

### For Developers

```bash
# Node.js - local development
cd cli/nodejs
npm install
npm link

# Python - local development
cd cli/python
pip install -e .

# PHP - direct usage
php cli/veribits.php <command>
```

## Adding New Commands

### 1. Add to Node.js CLI

**cli/nodejs/bin/veribits.js:**
```javascript
program
  .command('new-command <arg>')
  .description('Description of command')
  .action(async (arg) => {
    const cli = new VeriBitsCLI(program.opts().apiKey);
    await cli.newCommand(arg);
  });
```

**cli/nodejs/lib/cli.js:**
```javascript
async newCommand(arg) {
  const result = await this._request('POST', '/api/endpoint', {
    argument: arg,
  });

  console.log(chalk.bold('Result:'));
  console.log(result.data);
}
```

### 2. Add to Python CLI

**cli/python/veribits.py - Add method:**
```python
def new_command(self, arg: str):
    """Command description"""
    result = self._request("POST", "/api/endpoint", {
        "argument": arg
    })

    print("\nResult:")
    print(result.get('data'))
```

**Add argument parser:**
```python
# In main()
new_parser = subparsers.add_parser("new-command", help="Description")
new_parser.add_argument("arg", help="Argument description")
```

**Add handler:**
```python
# In main()
elif args.command == "new-command":
    cli.new_command(args.arg)
```

### 3. Add to PHP CLI

**cli/veribits.php:**
```php
case 'new-command':
    $arg = $opts['arg'] ?? null;
    if (!$arg) {
        fwrite(STDERR, "Missing --arg\n");
        exit(1);
    }

    $result = apiRequest('/api/endpoint', 'POST', ['argument' => $arg]);
    printJson($result);
    break;
```

## API Endpoints

All CLI commands communicate with the VeriBits API:

- Base URL: `https://www.veribits.com/api/v1`
- Authentication: Optional API key via `--api-key` flag or `VERIBITS_API_KEY` env var
- Rate limiting: Applied for anonymous requests

Common endpoint patterns:
- `/security/*` - Security scanning tools
- `/tools/network/*` - Network utilities
- `/tools/ssl/*` - SSL/TLS tools
- `/tools/email/*` - Email verification
- `/tools/crypto/*` - Cryptocurrency validation

## Testing

### Unit Tests

```bash
# Node.js
cd cli/nodejs
npm test

# Python
cd cli/python
python -m pytest

# PHP
php cli/veribits.php health
```

### Integration Tests

```bash
# Run full test suite
./cli/test-all-clis.sh
```

### Manual Testing

```bash
# Test a specific command
vb hash "test" --algorithm sha256

# Test with API key
vb secrets-scan myfile.js --api-key YOUR_KEY

# Test verbose output
vb --help
```

## Versioning

All three CLIs should maintain version parity:

- Update version in `cli/nodejs/package.json`
- Update version in `cli/python/setup.py`
- Update version in `cli/python/veribits.py`

Current version: **1.0.0**

## Distribution Checklist

Before publishing a new version:

- [ ] Update version numbers in all three CLIs
- [ ] Run test suite: `./cli/test-all-clis.sh`
- [ ] Test local installation (npm link, pip install -e)
- [ ] Update CHANGELOG.md
- [ ] Test sample commands manually
- [ ] Run publish script: `./cli/publish-cli.sh X.Y.Z`
- [ ] Verify published packages
- [ ] Tag release in git
- [ ] Update documentation

## Troubleshooting

### Node.js CLI not found after npm link

```bash
cd cli/nodejs
npm unlink
npm link
```

### Python CLI not found after pip install

```bash
cd cli/python
pip uninstall veribits
pip install -e .
```

### Permission denied errors

```bash
chmod +x cli/*.sh
chmod +x cli/veribits.php
```

## Support

- Documentation: https://www.veribits.com/cli.php
- Issues: https://github.com/afterdarksystems/veribits-cli/issues
- Email: support@afterdarksys.com

## License

MIT License - © After Dark Systems, LLC
