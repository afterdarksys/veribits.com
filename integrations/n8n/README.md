# VeriBits n8n Integration

Official n8n nodes for VeriBits security and verification tools.

## Installation

### Community Nodes (Recommended)

1. Go to **Settings** > **Community Nodes** in n8n
2. Click **Install a community node**
3. Enter: `n8n-nodes-veribits`
4. Click **Install**

### Manual Installation

```bash
cd ~/.n8n/nodes
npm install n8n-nodes-veribits
```

## Available Nodes

### VeriBits

The main VeriBits node with multiple operations:

#### File Verification
- **Verify File Hash** - Check if a file hash is known/safe
- **Malware Scan** - Scan file for malware signatures

#### DNS Tools
- **DNS Lookup** - Query DNS records (A, AAAA, MX, TXT, etc.)
- **DNS Propagation** - Check DNS propagation globally
- **DNSSEC Validation** - Validate DNSSEC configuration
- **Reverse DNS** - Perform reverse DNS lookup

#### SSL/TLS Tools
- **SSL Validation** - Validate SSL certificate
- **Certificate Chain** - Resolve certificate chain
- **CSR Validation** - Validate Certificate Signing Request

#### Security Tools
- **Secrets Scanner** - Scan code for exposed secrets
- **IAM Policy Analyzer** - Analyze AWS/Azure/GCP IAM policies
- **Hash Lookup** - Look up hash in breach databases

#### Developer Tools
- **Hash Generator** - Generate MD5/SHA1/SHA256/SHA512 hashes
- **JWT Decoder** - Decode and validate JWT tokens
- **Regex Tester** - Test regular expressions
- **Base64 Encoder/Decoder** - Encode/decode Base64

### VeriBits Trigger

Webhook trigger for VeriBits events:

- `verification.completed` - File verification completed
- `scan.completed` - Security scan completed
- `certificate.expiring` - SSL certificate expiring soon
- `quota.warning` - Usage quota warning

## Configuration

### Credentials

1. In n8n, go to **Credentials** > **New**
2. Search for **VeriBits API**
3. Enter your API key from https://veribits.com/dashboard

### API Key Permissions

Your API key needs these scopes:
- `verify:*` - File verification
- `dns:*` - DNS tools
- `ssl:*` - SSL/TLS tools
- `security:*` - Security scanning
- `tools:*` - Developer tools

## Example Workflows

### 1. Daily SSL Certificate Check

```json
{
  "nodes": [
    {
      "name": "Schedule",
      "type": "n8n-nodes-base.scheduleTrigger",
      "parameters": {
        "rule": { "interval": [{"field": "days", "daysInterval": 1}] }
      }
    },
    {
      "name": "VeriBits SSL Check",
      "type": "n8n-nodes-veribits.veribits",
      "parameters": {
        "operation": "sslValidate",
        "host": "example.com"
      }
    },
    {
      "name": "Filter Expiring",
      "type": "n8n-nodes-base.if",
      "parameters": {
        "conditions": {
          "number": [{"value1": "={{$json.days_remaining}}", "operation": "smallerEqual", "value2": 30}]
        }
      }
    },
    {
      "name": "Send Alert",
      "type": "n8n-nodes-base.slack",
      "parameters": {
        "channel": "#alerts",
        "text": "SSL certificate for {{$json.host}} expires in {{$json.days_remaining}} days!"
      }
    }
  ]
}
```

### 2. Git Commit Secrets Scanner

```json
{
  "nodes": [
    {
      "name": "GitHub Trigger",
      "type": "n8n-nodes-base.githubTrigger",
      "parameters": {
        "events": ["push"]
      }
    },
    {
      "name": "Get Changed Files",
      "type": "n8n-nodes-base.github",
      "parameters": {
        "operation": "getRepositoryContent"
      }
    },
    {
      "name": "VeriBits Secrets Scan",
      "type": "n8n-nodes-veribits.veribits",
      "parameters": {
        "operation": "secretsScan",
        "content": "={{$json.content}}"
      }
    },
    {
      "name": "If Secrets Found",
      "type": "n8n-nodes-base.if",
      "parameters": {
        "conditions": {
          "number": [{"value1": "={{$json.secrets_found}}", "operation": "larger", "value2": 0}]
        }
      }
    },
    {
      "name": "Create Issue",
      "type": "n8n-nodes-base.github",
      "parameters": {
        "operation": "createIssue",
        "title": "SECURITY: Exposed secrets detected",
        "body": "Secrets found in commit: {{$json.findings}}"
      }
    }
  ]
}
```

### 3. Batch DNS Health Check

```json
{
  "nodes": [
    {
      "name": "Domain List",
      "type": "n8n-nodes-base.spreadsheetFile",
      "parameters": {
        "filePath": "/data/domains.csv"
      }
    },
    {
      "name": "VeriBits DNS Check",
      "type": "n8n-nodes-veribits.veribits",
      "parameters": {
        "operation": "dnsLookup",
        "domain": "={{$json.domain}}",
        "recordType": "A"
      }
    },
    {
      "name": "Aggregate Results",
      "type": "n8n-nodes-base.itemLists",
      "parameters": {
        "operation": "summarize"
      }
    },
    {
      "name": "Send Report",
      "type": "n8n-nodes-base.emailSend",
      "parameters": {
        "to": "ops@example.com",
        "subject": "Daily DNS Health Report"
      }
    }
  ]
}
```

## Rate Limits

| Plan | Requests/min | Batch Size |
|------|--------------|------------|
| Free | 10 | 10 |
| Pro | 100 | 50 |
| Enterprise | 1000 | 100 |

## Support

- **Documentation**: https://veribits.com/docs
- **API Reference**: https://veribits.com/api/docs
- **Support**: support@veribits.com
- **GitHub**: https://github.com/afterdarksystems/n8n-nodes-veribits

## License

MIT License - see LICENSE file
