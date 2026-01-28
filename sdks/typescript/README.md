# VeriBits TypeScript/JavaScript SDK

Official TypeScript/JavaScript SDK for the [VeriBits](https://veribits.com) security API.

## Installation

```bash
npm install veribits
# or
yarn add veribits
```

## Quick Start

```typescript
import { VeriBits } from 'veribits';

// Initialize client
const client = new VeriBits({ apiKey: 'your_api_key' });

// Threat intelligence lookup
const result = await client.threatIntel.lookup('d41d8cd98f00b204e9800998ecf8427e');
console.log(`Threat score: ${result.threat_score}`);

// Submit file to sandbox
const scan = await client.sandbox.submit('/path/to/file');
console.log(`Task ID: ${scan.task_id}`);

// Generate SBOM
const sbom = await client.cicd.generateSBOM('cyclonedx');
```

## Features

### Threat Intelligence

```typescript
// Lookup hash
const result = await client.threatIntel.lookup('sha256_hash', {
  sources: ['virustotal', 'malwarebazaar'],
  includeMetadata: true
});

console.log(`Is malicious: ${result.is_malicious}`);
console.log(`Confidence: ${result.confidence}`);
```

### Malware Sandbox

```typescript
// Submit file for analysis
const submission = await client.sandbox.submit('/path/to/file', 'full');

// Analysis types: 'static', 'dynamic', 'full'
```

### CI/CD Integration

```typescript
// Generate Software Bill of Materials
const sbom = await client.cicd.generateSBOM('cyclonedx');
// Formats: 'cyclonedx', 'spdx'
```

### Cryptographic Services

```typescript
// Publish a public key
const keyInfo = await client.crypto.publishKey(pgpKey, 'pgp');
// Key types: 'pgp', 'ssh', 'x509'
```

## Configuration

```typescript
const client = new VeriBits({
  apiKey: 'your_api_key',
  apiUrl: 'https://custom.veribits.com' // Optional custom endpoint
});
```

## TypeScript Support

This SDK is written in TypeScript and includes full type definitions:

```typescript
import { VeriBits, ThreatLookupResult, VeriBitsConfig } from 'veribits';

const config: VeriBitsConfig = {
  apiKey: process.env.VERIBITS_API_KEY!
};

const client = new VeriBits(config);
const result: ThreatLookupResult = await client.threatIntel.lookup('hash');
```

## Requirements

- Node.js 16+
- TypeScript 4.5+ (for TypeScript users)

## License

MIT License - see [LICENSE](LICENSE) for details.

## Links

- [VeriBits Platform](https://veribits.com)
- [API Documentation](https://docs.veribits.com)
- [GitHub](https://github.com/veribits/typescript-sdk)
