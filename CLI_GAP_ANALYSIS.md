# VeriBits CLI Gap Analysis

## Problem Statement

The three CLI implementations have different command sets:
- **PHP CLI**: 32 commands (original)
- **Node.js CLI**: 24 commands (expanded, but missing PHP features)
- **Python CLI**: 24 commands (expanded, but missing PHP features)

**Total API Endpoints Available**: 107

This means ALL CLIs are missing significant functionality!

## Commands in PHP CLI NOT in Node/Python

### Have I Been Pwned Integration
1. `breach:email` → `/api/v1/hibp/check-email`
2. `breach:password` → `/api/v1/hibp/check-password`
3. `hibp:email` → `/api/v1/hibp/check-email` (alias)
4. `hibp:password` → `/api/v1/hibp/check-password` (alias)

### Cloud Storage Security
5. `cloud-storage` → `/api/v1/tools/cloud-storage/search`
6. `cloud-storage-scan` → `/api/v1/tools/cloud-storage/analyze-security`
7. `cloud-storage-buckets` → `/api/v1/tools/cloud-storage/list-buckets`

### Email Verification (Granular)
8. `email:spf` → `/api/v1/email/analyze-spf`
9. `email:dkim` → `/api/v1/email/analyze-dkim`
10. `email:dmarc` → `/api/v1/email/analyze-dmarc`
11. `email:mx` → `/api/v1/email/analyze-mx`
12. `email:disposable` → `/api/v1/email/check-disposable`
13. `email:blacklist` → `/api/v1/email/check-blacklists`
14. `email:score` → `/api/v1/email/deliverability-score`

### SSL/TLS Tools
15. `ssl:resolve-chain` → `/api/v1/ssl/resolve-chain`
16. `ssl:verify-keypair` → `/api/v1/ssl/verify-key-pair`

### File/Transaction Verification
17. `verify:file` → `/api/v1/verify/file`
18. `verify:email` → `/api/v1/verify/email`
19. `verify:tx` → `/api/v1/verify/tx`

### Tool Discovery
20. `tool-search` → `/api/v1/tools/search`
21. `tool-list` → `/api/v1/tools/list`

### Health Check
22. `health` → `/api/v1/health`

## Commands in Node/Python NOT in PHP CLI

1. `dns-validate`
2. `zone-validate`
3. `ip-calc`
4. `rbl-check`
5. `traceroute`
6. `bgp-lookup`
7. `crypto-validate`
8. `hash-validate`
9. `steg-detect`
10. `csr-validate`
11. `cert-convert`

## API Endpoints NOT in ANY CLI

Looking at 107 total endpoints, there are many unused:

### Archive/Malware
- `/api/v1/inspect/archive`
- `/api/v1/verify/malware`

### Code Signing
- `/api/v1/code-signing/sign`
- `/api/v1/code-signing/quota`

### JWT (partial)
- `/api/v1/jwt/sign` (only decode exists)
- `/api/v1/jwt/validate`

### SSL (partial)
- `/api/v1/ssl/build-bundle`
- `/api/v1/ssl/fetch-missing`
- `/api/v1/ssl/generate-csr`

### DNS (partial)
- `/api/v1/dns/check`
- `/api/v1/verify/dns`

### ID Verification
- `/api/v1/verify/id`

### Keystore Management
- `/api/v1/tools/keystore/jks-to-pkcs12`
- `/api/v1/tools/keystore/pkcs12-to-jks`
- `/api/v1/tools/keystore/extract`

### WHOIS
- `/api/v1/tools/whois`
- `/api/v1/lookup` (WHOIS lookup)

### And many more...

## Recommended Action

### Phase 1: Achieve Parity (Priority 1)
Add all PHP commands to Node/Python CLIs:
- 22 missing commands × 2 CLIs = 44 additions needed

### Phase 2: Add PHP-Missing Commands (Priority 2)
Add Node/Python commands to PHP CLI:
- 11 missing commands in PHP

### Phase 3: Add Unused Endpoints (Priority 3)
Implement remaining ~40 unused endpoints across ALL CLIs

## Target Command Count

After full implementation:
- **All CLIs should have**: ~50-60 commands (complete API coverage)
- **Current maximum**: 32 (PHP)
- **Gap**: 18-28 commands per CLI

## Conclusion

**Current situation**: Each CLI covers different parts of the API
**Goal**: 100% feature parity with complete API coverage across all three CLIs
