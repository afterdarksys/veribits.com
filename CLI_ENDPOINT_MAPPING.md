# VeriBits CLI to API Endpoint Mapping

## Endpoint Corrections Needed

### ✅ Already Correct
- `iam-analyze` → `/api/v1/security/iam-policy/analyze`
- `secrets-scan` → `/api/v1/security/secrets/scan`
- `db-audit` → `/api/v1/security/db-connection/audit`
- `dns-validate` → `/api/v1/tools/dns-validate`
- `ip-calc` → `/api/v1/tools/ip-calculate`
- `rbl-check` → `/api/v1/tools/rbl-check`
- `traceroute` → `/api/v1/tools/traceroute`
- `cert-convert` → `/api/v1/tools/cert-convert`
- `crypto-validate` → `/api/v1/crypto/validate`
- `zone-validate` → `/api/v1/zone-validate`
- `csr-validate` → `/api/v1/ssl/validate-csr`

### ❌ Need to Fix

| CLI Command | Current Path | Correct Path |
|------------|--------------|--------------|
| `security-headers` | `/security/security-headers/analyze` | `/api/v1/tools/security-headers` |
| `jwt-decode` | `/tools/jwt-debugger/decode` | `/api/v1/jwt/decode` |
| `hash` | Local (no API) | Local (no API needed) |
| `regex` | `/tools/regex-tester/test` | `/api/v1/tools/regex-test` |
| `pgp-validate` | `/tools/pgp-validator/validate` | `/api/v1/tools/pgp-validate` |
| `file-magic` | `/tools/file-magic/detect` | `/api/v1/file-magic` |
| `url-encode` | Local (no API) | `/api/v1/tools/url-encoder` (optional) |
| `base64` | Local (no API) | Local (no API needed) |
| `ssl-check` | `/tools/ssl/check` | `/api/v1/ssl/validate` |
| `hash-validate` | `/tools/hash/validate` | `/api/v1/tools/hash-validator` |
| `steg-detect` | `/tools/steganography/detect` | `/api/v1/steganography-detect` |
| `bgp-lookup` | `/tools/network/bgp-lookup` | `/api/v1/bgp/asn` |
| `email-verify` | `/tools/email/verify` | `/api/v1/tools/smtp-relay-check` |

## Missing Features

These CLI commands reference endpoints that don't exist:
- None - all have endpoints!

## Implementation Notes

1. **BGP Lookup**: Uses `/api/v1/bgp/asn` + POST with `as_number` parameter
2. **Email Verify**: Use `/api/v1/tools/smtp-relay-check` which does comprehensive checking
3. **SSL Check**: Use `/api/v1/ssl/validate` endpoint
4. **Steganography**: Use `/api/v1/steganography-detect`
5. **Hash/Base64/URL**: Keep as local operations (faster, no API needed)
