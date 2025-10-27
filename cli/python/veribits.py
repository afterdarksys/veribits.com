#!/usr/bin/env python3
"""
VeriBits CLI - Security and Developer Tools
Official Python CLI for VeriBits.com
"""

import argparse
import json
import sys
import requests
import cmd
import readline
from typing import Dict, Any, Optional

API_BASE = "https://www.veribits.com/api/v1"

class VeriBitsCLI:
    def __init__(self, api_key: Optional[str] = None):
        self.api_key = api_key
        self.headers = {"Content-Type": "application/json"}
        if api_key:
            self.headers["Authorization"] = f"Bearer {api_key}"

    def _request(self, method: str, endpoint: str, data: Optional[Dict] = None) -> Dict[str, Any]:
        """Make API request"""
        url = f"{API_BASE}{endpoint}"
        try:
            if method == "GET":
                response = requests.get(url, headers=self.headers)
            elif method == "POST":
                response = requests.post(url, json=data, headers=self.headers)
            else:
                raise ValueError(f"Unsupported method: {method}")

            result = response.json()
            if not result.get("success"):
                error_msg = result.get("error", {}).get("message", "Unknown error")
                print(f"Error: {error_msg}", file=sys.stderr)
                sys.exit(1)

            return result.get("data", {})

        except requests.exceptions.RequestException as e:
            print(f"Network error: {e}", file=sys.stderr)
            sys.exit(1)
        except json.JSONDecodeError:
            print("Error: Invalid JSON response from server", file=sys.stderr)
            sys.exit(1)

    def iam_analyze(self, policy_file: str):
        """Analyze AWS IAM policy"""
        try:
            with open(policy_file, 'r') as f:
                policy = json.load(f)
        except FileNotFoundError:
            print(f"Error: File not found: {policy_file}", file=sys.stderr)
            sys.exit(1)
        except json.JSONDecodeError:
            print(f"Error: Invalid JSON in {policy_file}", file=sys.stderr)
            sys.exit(1)

        result = self._request("POST", "/security/iam-policy/analyze", {
            "policy_document": policy,
            "policy_name": policy_file
        })

        print(f"\n🔐 IAM Policy Analysis: {policy_file}")
        print(f"Risk Score: {result['risk_score']}/100")
        print(f"Risk Level: {result['risk_level'].upper()}")
        print(f"Findings: {len(result['findings'])}")

        if result['findings']:
            print("\n⚠️  Issues Found:")
            for i, finding in enumerate(result['findings'], 1):
                severity_color = {'critical': '🔴', 'high': '🟠', 'medium': '🟡', 'low': '🟢'}
                print(f"\n{i}. {severity_color.get(finding['severity'], '⚪')} {finding['issue']}")
                print(f"   Severity: {finding['severity'].upper()}")
                print(f"   💡 {finding['recommendation']}")

        if result.get('recommendations'):
            print("\n📋 Recommendations:")
            for rec in result['recommendations']:
                print(f"  • {rec}")

    def secrets_scan(self, file_path: str):
        """Scan file for secrets"""
        try:
            with open(file_path, 'r') as f:
                content = f.read()
        except FileNotFoundError:
            print(f"Error: File not found: {file_path}", file=sys.stderr)
            sys.exit(1)

        result = self._request("POST", "/security/secrets/scan", {
            "content": content,
            "source_name": file_path,
            "source_type": "file"
        })

        print(f"\n🔑 Secrets Scan: {file_path}")
        print(f"Secrets Found: {result['secrets_found']}")
        print(f"Risk Level: {result['risk_level'].upper()}")

        if result['secrets']:
            print("\n⚠️  Detected Secrets:")
            for i, secret in enumerate(result['secrets'], 1):
                severity_color = {'critical': '🔴', 'high': '🟠', 'medium': '🟡', 'low': '🟢'}
                print(f"\n{i}. {severity_color.get(secret['severity'], '⚪')} {secret['name']}")
                print(f"   Line: {secret['line']}")
                print(f"   Value: {secret['value']}")
                print(f"   Severity: {secret['severity'].upper()}")
        else:
            print("\n✓ No secrets detected!")

    def db_audit(self, connection_string: str):
        """Audit database connection string"""
        result = self._request("POST", "/security/db-connection/audit", {
            "connection_string": connection_string
        })

        print(f"\n🗄️  Database Connection Audit")
        print(f"Database Type: {result['db_type']}")
        print(f"Risk Score: {result['risk_score']}/100")
        print(f"Risk Level: {result['risk_level'].upper()}")

        if result['issues']:
            print(f"\n⚠️  Issues Found: {len(result['issues'])}")
            for i, issue in enumerate(result['issues'], 1):
                severity_color = {'critical': '🔴', 'high': '🟠', 'medium': '🟡', 'low': '🟢'}
                print(f"\n{i}. {severity_color.get(issue['severity'], '⚪')} {issue['issue']}")
                print(f"   💡 {issue['recommendation']}")
        else:
            print("\n✓ No security issues found!")

        if result.get('secure_alternative'):
            print(f"\n🔒 Secure Alternative:\n{result['secure_alternative']}")

    def security_headers(self, url: str):
        """Analyze HTTP security headers"""
        result = self._request("POST", "/tools/security-headers", {
            "url": url
        })

        print(f"\n🛡️  Security Headers Analysis: {url}")
        print(f"Score: {result['score']}/100")
        print(f"Grade: {result['grade']}")

        if result.get('headers'):
            print("\n📋 Headers:")
            for header, status in result['headers'].items():
                icon = '✓' if status.get('present') else '✗'
                value = status.get('value', 'Missing')
                print(f"  {icon} {header}: {value}")

        if result.get('recommendations'):
            print("\n💡 Recommendations:")
            for rec in result['recommendations']:
                print(f"  • {rec}")

    def jwt_decode(self, token: str, secret: Optional[str] = None, verify: bool = False):
        """Decode and analyze JWT token"""
        data = {"token": token}

        if secret:
            data['secret'] = secret
            data['verify'] = True
        elif verify:
            data['verify'] = True

        result = self._request("POST", "/jwt/decode", data)

        print("\n🔑 JWT Token Analysis")

        if result.get('header'):
            print("\nHeader:")
            print(json.dumps(result['header'], indent=2))

        if result.get('payload'):
            print("\nPayload:")
            print(json.dumps(result['payload'], indent=2))

        if result.get('signature'):
            print(f"\nSignature:\n{result['signature']}")

        if 'verified' in result:
            status = '✓ Valid' if result['verified'] else '✗ Invalid'
            print(f"\nVerification: {status}")

        if result.get('warnings'):
            print("\n⚠️  Warnings:")
            for warning in result['warnings']:
                print(f"  • {warning}")

    def hash_text(self, text: str, algorithm: str = 'sha256'):
        """Generate cryptographic hash"""
        import hashlib

        supported_algorithms = ['md5', 'sha1', 'sha256', 'sha512']

        if algorithm not in supported_algorithms:
            print(f"Error: Unsupported algorithm: {algorithm}", file=sys.stderr)
            print(f"Supported algorithms: {', '.join(supported_algorithms)}")
            sys.exit(1)

        hash_obj = hashlib.new(algorithm)
        hash_obj.update(text.encode('utf-8'))
        hash_value = hash_obj.hexdigest()

        print(f"\n🔐 Hash ({algorithm.upper()})")
        print(hash_value)

    def regex_test(self, pattern: str, text: str, flags: str = ''):
        """Test regular expression"""
        result = self._request("POST", "/tools/regex-test", {
            "pattern": pattern,
            "text": text,
            "flags": flags
        })

        print("\n📝 Regex Test Results")
        print(f"Pattern: {pattern}")
        print(f"Flags: {flags or 'none'}")
        print(f"Match: {'✓ Yes' if result.get('match') else '✗ No'}")

        if result.get('matches'):
            print("\n📋 Matches:")
            for i, match in enumerate(result['matches'], 1):
                print(f"  {i}. {match}")

        if result.get('groups'):
            print("\n👥 Capture Groups:")
            for name, value in result['groups'].items():
                print(f"  {name}: {value}")

    def pgp_validate(self, key_or_file: str):
        """Validate PGP key"""
        try:
            with open(key_or_file, 'r') as f:
                key_data = f.read()
        except FileNotFoundError:
            # Treat as raw key data
            key_data = key_or_file

        result = self._request("POST", "/tools/pgp-validate", {
            "key": key_data
        })

        print("\n🔐 PGP Key Validation")
        print(f"Valid: {'✓ Yes' if result.get('valid') else '✗ No'}")

        if result.get('key_info'):
            info = result['key_info']
            print("\n📋 Key Information:")
            print(f"  Algorithm: {info.get('algorithm')}")
            print(f"  Key Size: {info.get('key_size')} bits")
            print(f"  Fingerprint: {info.get('fingerprint')}")
            if info.get('user_id'):
                print(f"  User ID: {info['user_id']}")
            if info.get('created'):
                print(f"  Created: {info['created']}")
            if info.get('expires'):
                print(f"  Expires: {info['expires']}")

        if result.get('warnings'):
            print("\n⚠️  Warnings:")
            for warning in result['warnings']:
                print(f"  • {warning}")

    def file_magic(self, file_path: str):
        """Detect file type by magic number"""
        import base64

        try:
            with open(file_path, 'rb') as f:
                content = f.read()
                base64_data = base64.b64encode(content).decode('utf-8')
        except FileNotFoundError:
            print(f"Error: File not found: {file_path}", file=sys.stderr)
            sys.exit(1)

        result = self._request("POST", "/file-magic", {
            "file_data": base64_data,
            "filename": file_path
        })

        print(f"\n🔍 File Magic Detection: {file_path}")
        print(f"File Type: {result.get('file_type')}")
        print(f"MIME Type: {result.get('mime_type')}")
        print(f"Extension: {result.get('extension')}")

        if result.get('magic_number'):
            print(f"Magic Number: {result['magic_number']}")

        if result.get('description'):
            print(f"\nDescription: {result['description']}")

        if result.get('warnings'):
            print("\n⚠️  Warnings:")
            for warning in result['warnings']:
                print(f"  • {warning}")

    def dns_validate(self, domain: str, record_type: str = 'A'):
        """Validate DNS records"""
        result = self._request("POST", "/tools/dns-validate", {
            "domain": domain,
            "record_type": record_type
        })

        print(f"\n🌐 DNS Validation: {domain}")
        print(f"Record Type: {record_type}")

        if result.get('records'):
            print("\n📋 Records:")
            for i, record in enumerate(result['records'], 1):
                print(f"\n{i}. {record.get('type')}")
                print(f"   Value: {record.get('value')}")
                if record.get('ttl'):
                    print(f"   TTL: {record['ttl']}")
                if record.get('priority'):
                    print(f"   Priority: {record['priority']}")
        else:
            print("\nNo records found")

        if result.get('dnssec'):
            print("\n🔐 DNSSEC:")
            print(f"   Enabled: {'Yes' if result['dnssec'].get('enabled') else 'No'}")

    def zone_validate(self, zone_file: str):
        """Validate DNS zone file"""
        try:
            with open(zone_file, 'r') as f:
                content = f.read()
        except FileNotFoundError:
            print(f"Error: File not found: {zone_file}", file=sys.stderr)
            sys.exit(1)

        result = self._request("POST", "/zone-validate", {
            "zone_data": content,
            "zone_name": zone_file
        })

        print(f"\n📝 Zone File Validation: {zone_file}")
        print(f"Valid: {'✓ Yes' if result.get('valid') else '✗ No'}")

        if result.get('errors'):
            print("\n❌ Errors:")
            for error in result['errors']:
                print(f"  • {error}")

        if result.get('warnings'):
            print("\n⚠️  Warnings:")
            for warning in result['warnings']:
                print(f"  • {warning}")

        if result.get('records_count'):
            print(f"\n📊 Statistics:")
            print(f"   Total Records: {result['records_count']}")

    def ip_calculate(self, ip_or_cidr: str):
        """Calculate IP subnet information"""
        result = self._request("POST", "/tools/ip-calculate", {
            "ip": ip_or_cidr
        })

        print("\n🔢 IP Calculator")
        print(f"IP Address: {result.get('ip_address')}")
        print(f"Network: {result.get('network')}")
        print(f"Netmask: {result.get('netmask')}")
        print(f"CIDR: {result.get('cidr')}")
        print(f"Wildcard: {result.get('wildcard')}")
        print(f"First Host: {result.get('first_host')}")
        print(f"Last Host: {result.get('last_host')}")
        print(f"Broadcast: {result.get('broadcast')}")
        print(f"Total Hosts: {result.get('total_hosts')}")
        print(f"IP Version: {result.get('ip_version')}")

        if result.get('ip_class'):
            print(f"IP Class: {result['ip_class']}")

    def rbl_check(self, ip: str):
        """Check if IP is listed in RBL/DNSBL"""
        result = self._request("POST", "/tools/rbl-check", {
            "ip": ip
        })

        print(f"\n🛡️  RBL/DNSBL Check: {ip}")
        print(f"Listed: {'✗ Yes' if result.get('is_listed') else '✓ No'}")
        print(f"Lists Checked: {result.get('lists_checked')}")
        print(f"Lists Found: {result.get('lists_found')}")

        if result.get('blacklists'):
            print("\n❌ Found on Blacklists:")
            for i, bl in enumerate(result['blacklists'], 1):
                print(f"\n{i}. {bl.get('name')}")
                if bl.get('reason'):
                    print(f"   Reason: {bl['reason']}")
                if bl.get('url'):
                    print(f"   Info: {bl['url']}")

    def email_verify(self, email: str):
        """Comprehensive email verification"""
        result = self._request("POST", "/tools/smtp-relay-check", {
            "email": email
        })

        print(f"\n📧 Email Verification: {email}")
        print(f"Valid: {'✓ Yes' if result.get('valid') else '✗ No'}")
        print(f"Deliverability Score: {result.get('deliverability_score')}/100")

        if result.get('mx_records'):
            print("\n📮 MX Records:")
            print(f"   Valid: {'✓' if result['mx_records'].get('valid') else '✗'}")
            if result['mx_records'].get('servers'):
                for server in result['mx_records']['servers']:
                    print(f"   • {server}")

        if result.get('spf'):
            print("\n🔐 SPF:")
            print(f"   Valid: {'✓' if result['spf'].get('valid') else '✗'}")
            if result['spf'].get('record'):
                print(f"   Record: {result['spf']['record']}")

        if result.get('dkim'):
            print("\n🔏 DKIM:")
            print(f"   Valid: {'✓' if result['dkim'].get('valid') else '✗'}")

        if result.get('dmarc'):
            print("\n🛡️  DMARC:")
            print(f"   Valid: {'✓' if result['dmarc'].get('valid') else '✗'}")
            if result['dmarc'].get('policy'):
                print(f"   Policy: {result['dmarc']['policy']}")

        if result.get('warnings'):
            print("\n⚠️  Warnings:")
            for warning in result['warnings']:
                print(f"  • {warning}")

    def traceroute(self, host: str):
        """Perform visual traceroute"""
        result = self._request("POST", "/tools/traceroute", {
            "host": host
        })

        print(f"\n🗺️  Traceroute to {host}")
        print(f"Destination: {result.get('destination_ip')}")
        print(f"Hops: {result.get('hop_count')}")

        if result.get('hops'):
            print("\n📍 Route:")
            for hop in result['hops']:
                time = f"{hop.get('rtt')}ms" if hop.get('rtt') else '*'
                ip = hop.get('ip', '*')
                hostname = hop.get('hostname', 'unknown')
                print(f"  {hop.get('hop')}. {ip} ({hostname}) - {time}")
                if hop.get('location'):
                    print(f"     {hop['location']}")

    def url_encode(self, text: str, decode: bool = False):
        """URL encode/decode"""
        import urllib.parse

        if decode:
            decoded = urllib.parse.unquote(text)
            print("\n🔗 URL Decoded:")
            print(decoded)
        else:
            encoded = urllib.parse.quote(text)
            print("\n🔗 URL Encoded:")
            print(encoded)

    def base64_encode(self, text: str, decode: bool = False):
        """Base64 encode/decode"""
        import base64

        if decode:
            decoded = base64.b64decode(text).decode('utf-8')
            print("\n📦 Base64 Decoded:")
            print(decoded)
        else:
            encoded = base64.b64encode(text.encode('utf-8')).decode('utf-8')
            print("\n📦 Base64 Encoded:")
            print(encoded)

    def ssl_check(self, host: str, port: int = 443):
        """Check SSL/TLS certificate"""
        result = self._request("POST", "/ssl/validate", {
            "host": host,
            "port": port
        })

        print(f"\n🔐 SSL/TLS Certificate Check: {host}:{port}")
        print(f"Valid: {'✓ Yes' if result.get('valid') else '✗ No'}")

        if result.get('certificate'):
            cert = result['certificate']
            print("\n📜 Certificate Details:")
            print(f"  Subject: {cert.get('subject')}")
            print(f"  Issuer: {cert.get('issuer')}")
            print(f"  Valid From: {cert.get('valid_from')}")
            print(f"  Valid To: {cert.get('valid_to')}")
            print(f"  Days Remaining: {cert.get('days_remaining')}")

            if cert.get('sans'):
                print(f"  SANs: {', '.join(cert['sans'])}")

        if 'chain_valid' in result:
            print(f"\nChain Valid: {'✓' if result['chain_valid'] else '✗'}")

        if result.get('warnings'):
            print("\n⚠️  Warnings:")
            for warning in result['warnings']:
                print(f"  • {warning}")

    def cert_convert(self, file_path: str, target_format: str = 'PEM'):
        """Convert certificate formats"""
        import base64

        try:
            with open(file_path, 'rb') as f:
                content = f.read()
                base64_data = base64.b64encode(content).decode('utf-8')
        except FileNotFoundError:
            print(f"Error: File not found: {file_path}", file=sys.stderr)
            sys.exit(1)

        result = self._request("POST", "/tools/cert-convert", {
            "certificate_data": base64_data,
            "source_format": "auto",
            "target_format": target_format
        })

        print(f"\n🔄 Certificate Converted to {target_format}")
        print(result.get('converted_data'))

    def ssl_convert(self, input_file: str, input_format: str = 'auto', output_format: str = 'PEM', output_file: str = None):
        """Convert SSL certificates (OpenSSL-like interface)"""
        import base64

        input_format = input_format.upper()
        output_format = output_format.upper()

        try:
            with open(input_file, 'rb') as f:
                content = f.read()
                base64_data = base64.b64encode(content).decode('utf-8')
        except FileNotFoundError:
            print(f"Error: File not found: {input_file}", file=sys.stderr)
            sys.exit(1)

        result = self._request("POST", "/tools/cert-convert", {
            "certificate_data": base64_data,
            "source_format": input_format,
            "target_format": output_format
        })

        print("\n🔐 SSL Certificate Conversion")
        print(f"Format: {input_format} → {output_format}")

        if output_file:
            with open(output_file, 'w') as f:
                f.write(result.get('converted_data'))
            print(f"✓ Converted certificate saved to: {output_file}")
        else:
            print("\nConverted Certificate:")
            print(result.get('converted_data'))

    def crypto_validate(self, address: str, crypto_type: str = 'bitcoin'):
        """Validate cryptocurrency address"""
        result = self._request("POST", "/crypto/validate", {
            "address": address,
            "crypto_type": crypto_type
        })

        print("\n💰 Cryptocurrency Address Validation")
        print(f"Type: {crypto_type.upper()}")
        print(f"Valid: {'✓ Yes' if result.get('valid') else '✗ No'}")

        if result.get('address_type'):
            print(f"Address Type: {result['address_type']}")

        if result.get('network'):
            print(f"Network: {result['network']}")

        if result.get('warnings'):
            print("\n⚠️  Warnings:")
            for warning in result['warnings']:
                print(f"  • {warning}")

    def hash_validate(self, hash_value: str):
        """Validate and identify hash type"""
        result = self._request("POST", "/tools/hash-validator", {
            "hash": hash_value
        })

        print("\n🔍 Hash Validation")
        print(f"Valid: {'✓ Yes' if result.get('valid') else '✗ No'}")
        print(f"Hash Type: {result.get('hash_type', 'Unknown')}")
        print(f"Length: {result.get('length')} characters")

        if result.get('possible_types'):
            print(f"\nPossible Types: {', '.join(result['possible_types'])}")

    def steg_detect(self, file_path: str):
        """Detect hidden data in images"""
        import base64

        try:
            with open(file_path, 'rb') as f:
                content = f.read()
                base64_data = base64.b64encode(content).decode('utf-8')
        except FileNotFoundError:
            print(f"Error: File not found: {file_path}", file=sys.stderr)
            sys.exit(1)

        result = self._request("POST", "/steganography-detect", {
            "file_data": base64_data,
            "filename": file_path
        })

        print(f"\n🎭 Steganography Detection: {file_path}")
        print(f"Suspicious: {'✓ Yes' if result.get('suspicious') else '✗ No'}")
        print(f"Confidence: {result.get('confidence')}%")

        if result.get('indicators'):
            print("\n🔍 Indicators:")
            for indicator in result['indicators']:
                print(f"  • {indicator}")

        if result.get('analysis'):
            print(f"\n📊 Analysis:")
            print(f"  {result['analysis']}")

    def bgp_lookup(self, as_number: str):
        """Lookup BGP AS information"""
        result = self._request("POST", "/bgp/asn", {
            "asn": as_number.replace("AS", "").replace("as", "")
        })

        print(f"\n🌐 BGP AS Lookup: {as_number}")

        if result.get('as_info'):
            info = result['as_info']
            print(f"AS Number: {info.get('as_number')}")
            print(f"AS Name: {info.get('as_name')}")
            if info.get('country'):
                print(f"Country: {info['country']}")
            if info.get('registry'):
                print(f"Registry: {info['registry']}")
            if info.get('allocated'):
                print(f"Allocated: {info['allocated']}")

        if result.get('prefixes'):
            print(f"\n📋 Prefixes ({len(result['prefixes'])}):")
            for prefix in result['prefixes'][:10]:
                print(f"  • {prefix}")
            if len(result['prefixes']) > 10:
                print(f"  ... and {len(result['prefixes']) - 10} more")

        if result.get('peers'):
            print(f"\n🔗 Peers ({len(result['peers'])}):")
            for peer in result['peers'][:5]:
                print(f"  • AS{peer}")
            if len(result['peers']) > 5:
                print(f"  ... and {len(result['peers']) - 5} more")

    def csr_validate(self, file_path: str):
        """Validate Certificate Signing Request"""
        try:
            with open(file_path, 'r') as f:
                content = f.read()
        except FileNotFoundError:
            print(f"Error: File not found: {file_path}", file=sys.stderr)
            sys.exit(1)

        result = self._request("POST", "/ssl/validate-csr", {
            "csr": content
        })

        print("\n📝 CSR Validation")
        print(f"Valid: {'✓ Yes' if result.get('valid') else '✗ No'}")

        if result.get('csr_info'):
            info = result['csr_info']
            print("\n📋 CSR Details:")
            print(f"  Subject: {info.get('subject')}")
            print(f"  Public Key Algorithm: {info.get('public_key_algorithm')}")
            print(f"  Key Size: {info.get('key_size')} bits")
            print(f"  Signature Algorithm: {info.get('signature_algorithm')}")

            if info.get('san'):
                print(f"  SANs: {', '.join(info['san'])}")

        if result.get('warnings'):
            print("\n⚠️  Warnings:")
            for warning in result['warnings']:
                print(f"  • {warning}")

    def hibp_email(self, email: str):
        """Check if email appears in data breaches"""
        result = self._request("POST", "/hibp/check-email", {"email": email})

        print(f"\n🔓 Data Breach Check: {email}")
        print(f"Breaches Found: {result.get('breach_count', 0)}")

        if result.get('breaches'):
            print("\n⚠️  Compromised In:")
            for breach in result['breaches'][:10]:
                print(f"  • {breach['name']} ({breach['breach_date']})")
                print(f"    Data: {', '.join(breach['data_classes'])}")
            if len(result['breaches']) > 10:
                print(f"  ... and {len(result['breaches']) - 10} more")

    def hibp_password(self, password: str):
        """Check if password has been compromised"""
        result = self._request("POST", "/hibp/check-password", {"password": password})

        print("\n🔐 Password Breach Check")
        print(f"Times Seen in Breaches: {result.get('count', 0)}")

        if result.get('count', 0) > 0:
            print("⚠️  This password has been compromised!")
            print("Recommendation: Change this password immediately")
        else:
            print("✓ Password not found in known breaches")

    def cloud_storage_search(self, query: str, provider: str = 'all', search_type: str = 'filename'):
        """Search for publicly exposed cloud storage"""
        result = self._request("POST", "/tools/cloud-storage/search", {
            "query": query,
            "provider": provider,
            "search_type": search_type
        })

        print(f"\n☁️  Cloud Storage Search: {query}")
        print(f"Results Found: {result.get('result_count', 0)}")

        if result.get('results'):
            print("\n📦 Found:")
            for item in result['results'][:20]:
                print(f"  • {item['bucket']}/{item['key']}")
                print(f"    Provider: {item['provider']} | Size: {item.get('size', 'Unknown')}")
            if len(result['results']) > 20:
                print(f"  ... and {len(result['results']) - 20} more")

    def cloud_storage_scan(self, bucket: str, provider: str = 'aws'):
        """Analyze cloud storage bucket security"""
        result = self._request("POST", "/tools/cloud-storage/analyze-security", {
            "bucket": bucket,
            "provider": provider
        })

        print(f"\n☁️  Security Scan: {bucket}")
        print(f"Provider: {provider.upper()}")
        print(f"Risk Score: {result.get('risk_score', 0)}/100")
        print(f"Public Access: {'✗ Yes' if result.get('public_access') else '✓ No'}")

        if result.get('findings'):
            print("\n⚠️  Security Issues:")
            for finding in result['findings']:
                print(f"  • {finding['severity'].upper()}: {finding['issue']}")

    def cloud_storage_buckets(self, provider: str = 'aws'):
        """List accessible cloud storage buckets"""
        result = self._request("POST", "/tools/cloud-storage/list-buckets", {"provider": provider})

        print(f"\n☁️  Accessible Buckets ({provider.upper()})")
        print(f"Total: {result.get('bucket_count', 0)}")

        if result.get('buckets'):
            for bucket in result['buckets']:
                print(f"  • {bucket['name']} - {bucket.get('region', 'Unknown')}")

    def email_spf(self, domain: str):
        """Analyze SPF records for domain"""
        result = self._request("POST", "/email/analyze-spf", {"domain": domain})

        print(f"\n📧 SPF Analysis: {domain}")
        print(f"Valid: {'✓ Yes' if result.get('valid') else '✗ No'}")

        if result.get('record'):
            print(f"Record: {result['record']}")

        if result.get('mechanisms'):
            print("\nMechanisms:")
            for mech in result['mechanisms']:
                print(f"  • {mech}")

    def email_dkim(self, domain: str, selector: str = 'default'):
        """Analyze DKIM configuration for domain"""
        result = self._request("POST", "/email/analyze-dkim", {
            "domain": domain,
            "selector": selector
        })

        print(f"\n🔐 DKIM Analysis: {domain}")
        print(f"Selector: {selector}")
        print(f"Valid: {'✓ Yes' if result.get('valid') else '✗ No'}")

        if result.get('public_key'):
            print(f"Public Key Found: Yes")

    def email_dmarc(self, domain: str):
        """Analyze DMARC policy for domain"""
        result = self._request("POST", "/email/analyze-dmarc", {"domain": domain})

        print(f"\n🛡️  DMARC Analysis: {domain}")
        print(f"Valid: {'✓ Yes' if result.get('valid') else '✗ No'}")

        if result.get('policy'):
            print(f"Policy: {result['policy']}")

        if result.get('record'):
            print(f"Record: {result['record']}")

    def email_mx(self, domain: str):
        """Check MX records for domain"""
        result = self._request("POST", "/email/analyze-mx", {"domain": domain})

        print(f"\n📬 MX Records: {domain}")
        print(f"Records Found: {len(result.get('mx_records', []))}")

        if result.get('mx_records'):
            for mx in result['mx_records']:
                print(f"  • {mx['priority']}: {mx['server']}")

    def email_disposable(self, email: str):
        """Check if email is from disposable provider"""
        result = self._request("POST", "/email/check-disposable", {"email": email})

        print(f"\n🗑️  Disposable Email Check: {email}")
        print(f"Is Disposable: {'✓ Yes' if result.get('is_disposable') else '✗ No'}")

        if result.get('provider'):
            print(f"Provider: {result['provider']}")

    def email_blacklist(self, domain: str):
        """Check if domain is blacklisted"""
        result = self._request("POST", "/email/check-blacklists", {"domain": domain})

        print(f"\n🚫 Blacklist Check: {domain}")
        print(f"Listed: {'✗ Yes' if result.get('blacklisted') else '✓ No'}")

        if result.get('blacklists'):
            print("\nFound on:")
            for bl in result['blacklists']:
                print(f"  • {bl}")

    def email_score(self, domain: str):
        """Calculate email deliverability score"""
        result = self._request("POST", "/email/deliverability-score", {"domain": domain})

        print(f"\n📊 Deliverability Score: {domain}")
        print(f"Score: {result.get('score', 0)}/100")
        print(f"Grade: {result.get('grade', 'N/A')}")

        if result.get('factors'):
            print("\nFactors:")
            for factor in result['factors']:
                print(f"  • {factor['name']}: {factor['status']}")

    def ssl_resolve_chain(self, cert_or_url: str, is_file: bool = False):
        """Resolve SSL certificate chain"""
        import base64

        data = {"input": cert_or_url}

        if is_file:
            try:
                with open(cert_or_url, 'r') as f:
                    data["certificate"] = f.read()
                data["input_type"] = "file"
            except FileNotFoundError:
                print(f"Error: File not found: {cert_or_url}", file=sys.stderr)
                sys.exit(1)
        else:
            data["input_type"] = "url"

        result = self._request("POST", "/ssl/resolve-chain", data)

        print("\n🔗 Certificate Chain")
        print(f"Chain Length: {len(result.get('chain', []))}")

        if result.get('chain'):
            for i, cert in enumerate(result['chain'], 1):
                print(f"\n{i}. {cert['subject']}")
                print(f"   Issuer: {cert['issuer']}")
                print(f"   Valid: {cert['not_before']} to {cert['not_after']}")

    def ssl_verify_keypair(self, cert_file: str, key_file: str):
        """Verify SSL certificate and key pair match"""
        try:
            with open(cert_file, 'r') as f:
                cert_data = f.read()
            with open(key_file, 'r') as f:
                key_data = f.read()
        except FileNotFoundError as e:
            print(f"Error: {e}", file=sys.stderr)
            sys.exit(1)

        result = self._request("POST", "/ssl/verify-key-pair", {
            "certificate": cert_data,
            "private_key": key_data
        })

        print("\n🔑 Key Pair Verification")
        print(f"Match: {'✓ Yes' if result.get('match') else '✗ No'}")

        if result.get('details'):
            print(f"Algorithm: {result['details'].get('algorithm')}")
            print(f"Key Size: {result['details'].get('key_size')} bits")

    def verify_file(self, file_path: str, expected_hash: str = None, signature: str = None):
        """Verify file integrity and authenticity"""
        import base64

        try:
            with open(file_path, 'rb') as f:
                file_data = base64.b64encode(f.read()).decode('utf-8')
        except FileNotFoundError:
            print(f"Error: File not found: {file_path}", file=sys.stderr)
            sys.exit(1)

        data = {"file_data": file_data, "filename": file_path}
        if expected_hash:
            data["expected_hash"] = expected_hash
        if signature:
            data["signature"] = signature

        result = self._request("POST", "/verify/file", data)

        print(f"\n✓ File Verification: {file_path}")
        print(f"Integrity: {'✓ Valid' if result.get('valid') else '✗ Invalid'}")

        if result.get('hashes'):
            print("\nHashes:")
            for algo, hash_val in result['hashes'].items():
                print(f"  {algo.upper()}: {hash_val}")

    def verify_email(self, email: str):
        """Comprehensive email verification"""
        result = self._request("POST", "/verify/email", {"email": email})

        print(f"\n✓ Email Verification: {email}")
        print(f"Valid Format: {'✓ Yes' if result.get('valid_format') else '✗ No'}")
        print(f"Domain Valid: {'✓ Yes' if result.get('domain_valid') else '✗ No'}")
        print(f"MX Records: {'✓ Yes' if result.get('has_mx') else '✗ No'}")
        print(f"Deliverable: {'✓ Yes' if result.get('deliverable') else '✗ No'}")

    def verify_transaction(self, tx_hash: str, chain: str = 'bitcoin'):
        """Verify blockchain transaction"""
        result = self._request("POST", "/verify/tx", {
            "transaction_hash": tx_hash,
            "blockchain": chain
        })

        print(f"\n₿ Transaction Verification: {tx_hash[:16]}...")
        print(f"Blockchain: {chain.upper()}")
        print(f"Confirmed: {'✓ Yes' if result.get('confirmed') else '✗ No'}")

        if result.get('confirmations'):
            print(f"Confirmations: {result['confirmations']}")

        if result.get('block_height'):
            print(f"Block: {result['block_height']}")

    def tool_search(self, query: str, category: str = None):
        """Search available security tools"""
        data = {"query": query}
        if category:
            data["category"] = category

        result = self._request("POST", "/tools/search", data)

        print(f"\n🔍 Tool Search: {query}")
        print(f"Results: {len(result.get('tools', []))}")

        if result.get('tools'):
            for tool in result['tools'][:10]:
                print(f"\n• {tool['name']}")
                print(f"  Category: {tool.get('category')}")
                print(f"  {tool.get('description')}")

    def tool_list(self, category: str = None):
        """List all available tools"""
        data = {}
        if category:
            data["category"] = category

        result = self._request("POST", "/tools/list", data)

        print("\n🧰 Available Tools")
        print(f"Total: {result.get('tool_count', 0)}")

        if result.get('categories'):
            for cat, tools in result['categories'].items():
                print(f"\n{cat.upper()}:")
                for tool in tools:
                    print(f"  • {tool}")

    def health_check(self):
        """Check API health status"""
        result = self._request("GET", "/health", None)

        print("\n💚 API Health Check")
        print(f"Status: {result.get('status', 'Unknown').upper()}")
        print(f"Version: {result.get('version')}")

        if result.get('uptime'):
            print(f"Uptime: {result['uptime']}")

    def whois_lookup(self, domain: str):
        """Perform WHOIS domain lookup"""
        result = self._request("POST", "/lookup", {"domain": domain})

        print(f"\n🌐 WHOIS Lookup: {domain}")

        if result.get('registrar'):
            print(f"Registrar: {result['registrar']}")

        if result.get('created'):
            print(f"Created: {result['created']}")

        if result.get('expires'):
            print(f"Expires: {result['expires']}")

        if result.get('nameservers'):
            print("\nNameservers:")
            for ns in result['nameservers']:
                print(f"  • {ns}")

    def malware_scan(self, file_path: str):
        """Scan file for malware"""
        import base64

        try:
            with open(file_path, 'rb') as f:
                file_data = base64.b64encode(f.read()).decode('utf-8')
        except FileNotFoundError:
            print(f"Error: File not found: {file_path}", file=sys.stderr)
            sys.exit(1)

        result = self._request("POST", "/verify/malware", {
            "file_data": file_data,
            "filename": file_path
        })

        print(f"\n🦠 Malware Scan: {file_path}")
        print(f"Malicious: {'✗ Yes' if result.get('malicious') else '✓ No'}")

        if result.get('threats'):
            print("\n⚠️  Threats Detected:")
            for threat in result['threats']:
                print(f"  • {threat}")

    def inspect_archive(self, file_path: str):
        """Inspect archive file contents"""
        import base64

        try:
            with open(file_path, 'rb') as f:
                file_data = base64.b64encode(f.read()).decode('utf-8')
        except FileNotFoundError:
            print(f"Error: File not found: {file_path}", file=sys.stderr)
            sys.exit(1)

        result = self._request("POST", "/inspect/archive", {
            "archive_data": file_data,
            "filename": file_path
        })

        print(f"\n📦 Archive Inspection: {file_path}")
        print(f"Type: {result.get('archive_type', 'Unknown')}")
        print(f"Files: {result.get('file_count', 0)}")

        if result.get('files'):
            print("\nContents:")
            for file in result['files'][:50]:
                print(f"  • {file['name']} ({file.get('size', 'Unknown')})")
            if result.get('file_count', 0) > 50:
                print(f"  ... and {result['file_count'] - 50} more")


class VeriBitsConsole(cmd.Cmd):
    """Interactive VeriBits Console"""

    intro = """
╔════════════════════════════════════════════════════════════╗
║           VeriBits Interactive Console v3.0.0              ║
╚════════════════════════════════════════════════════════════╝

Type 'help' for available commands or 'exit' to quit
"""
    prompt = '\033[96mveribits> \033[0m'  # Cyan prompt

    def __init__(self, cli: VeriBitsCLI):
        super().__init__()
        self.cli = cli

        if cli.api_key:
            print("\033[92m✓ Authenticated with API key\033[0m\n")
        else:
            print("\033[93m⚠ Running without API key (rate limits apply)\033[0m\n")

    def do_iam_analyze(self, arg):
        """Analyze AWS IAM policy: iam-analyze <policy-file>"""
        if not arg:
            print("Usage: iam-analyze <policy-file>")
            return
        try:
            self.cli.iam_analyze(arg)
        except Exception as e:
            print(f"\033[91mError: {e}\033[0m")

    def do_secrets_scan(self, arg):
        """Scan file for secrets: secrets-scan <file>"""
        if not arg:
            print("Usage: secrets-scan <file>")
            return
        try:
            self.cli.secrets_scan(arg)
        except Exception as e:
            print(f"\033[91mError: {e}\033[0m")

    def do_security_headers(self, arg):
        """Analyze HTTP security headers: security-headers <url>"""
        if not arg:
            print("Usage: security-headers <url>")
            return
        try:
            self.cli.security_headers(arg)
        except Exception as e:
            print(f"\033[91mError: {e}\033[0m")

    def do_dns_validate(self, arg):
        """Validate DNS records: dns-validate <domain>"""
        if not arg:
            print("Usage: dns-validate <domain>")
            return
        try:
            self.cli.dns_validate(arg, 'A')
        except Exception as e:
            print(f"\033[91mError: {e}\033[0m")

    def do_ip_calc(self, arg):
        """Calculate IP subnet: ip-calc <ip-or-cidr>"""
        if not arg:
            print("Usage: ip-calc <ip-or-cidr>")
            return
        try:
            self.cli.ip_calculate(arg)
        except Exception as e:
            print(f"\033[91mError: {e}\033[0m")

    def do_rbl_check(self, arg):
        """Check RBL/DNSBL: rbl-check <ip>"""
        if not arg:
            print("Usage: rbl-check <ip>")
            return
        try:
            self.cli.rbl_check(arg)
        except Exception as e:
            print(f"\033[91mError: {e}\033[0m")

    def do_email_verify(self, arg):
        """Comprehensive email verification: email-verify <email>"""
        if not arg:
            print("Usage: email-verify <email>")
            return
        try:
            self.cli.email_verify(arg)
        except Exception as e:
            print(f"\033[91mError: {e}\033[0m")

    def do_email_spf(self, arg):
        """Analyze SPF records: email-spf <domain>"""
        if not arg:
            print("Usage: email-spf <domain>")
            return
        try:
            self.cli.email_spf(arg)
        except Exception as e:
            print(f"\033[91mError: {e}\033[0m")

    def do_hibp_email(self, arg):
        """Check data breaches: hibp-email <email>"""
        if not arg:
            print("Usage: hibp-email <email>")
            return
        try:
            self.cli.hibp_email(arg)
        except Exception as e:
            print(f"\033[91mError: {e}\033[0m")

    def do_ssl_check(self, arg):
        """Check SSL certificate: ssl-check <host>"""
        if not arg:
            print("Usage: ssl-check <host>")
            return
        try:
            self.cli.ssl_check(arg, 443)
        except Exception as e:
            print(f"\033[91mError: {e}\033[0m")

    def do_hash(self, arg):
        """Generate hash: hash <text>"""
        if not arg:
            print("Usage: hash <text>")
            return
        try:
            self.cli.hash_text(arg, 'sha256')
        except Exception as e:
            print(f"\033[91mError: {e}\033[0m")

    def do_health(self, arg):
        """Check API health"""
        try:
            self.cli.health_check()
        except Exception as e:
            print(f"\033[91mError: {e}\033[0m")

    def do_whois(self, arg):
        """WHOIS lookup: whois <domain>"""
        if not arg:
            print("Usage: whois <domain>")
            return
        try:
            self.cli.whois_lookup(arg)
        except Exception as e:
            print(f"\033[91mError: {e}\033[0m")

    def do_tool_search(self, arg):
        """Search tools: tool-search <query>"""
        if not arg:
            print("Usage: tool-search <query>")
            return
        try:
            self.cli.tool_search(arg)
        except Exception as e:
            print(f"\033[91mError: {e}\033[0m")

    def do_tool_list(self, arg):
        """List all tools"""
        try:
            self.cli.tool_list()
        except Exception as e:
            print(f"\033[91mError: {e}\033[0m")

    def do_clear(self, arg):
        """Clear screen"""
        import os
        os.system('clear' if os.name != 'nt' else 'cls')

    def do_exit(self, arg):
        """Exit console"""
        print("\n\033[96m👋 Goodbye!\033[0m\n")
        return True

    def do_quit(self, arg):
        """Exit console"""
        return self.do_exit(arg)

    def do_EOF(self, arg):
        """Exit on Ctrl+D"""
        return self.do_exit(arg)

    def emptyline(self):
        """Do nothing on empty line"""
        pass

    def default(self, line):
        """Handle unknown commands"""
        print(f"\033[93mUnknown command: {line}\033[0m")
        print("Type 'help' for available commands")


def main():
    parser = argparse.ArgumentParser(
        description="VeriBits CLI - Security and Developer Tools",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  veribits iam-analyze policy.json
  veribits secrets-scan app.js
  veribits db-audit "postgresql://user:pass@host/db"
  veribits security-headers https://example.com
  veribits jwt-decode <token> --secret mysecret
  veribits hash "text to hash" --algorithm sha256
  veribits regex "\\d+" "test 123"
  veribits pgp-validate key.asc
  veribits file-magic document.pdf

For more information, visit https://www.veribits.com/cli.php
        """
    )

    parser.add_argument("--api-key", help="API key for authentication (optional)")
    parser.add_argument("--version", action="version", version="veribits 3.0.0")

    subparsers = parser.add_subparsers(dest="command", help="Available commands")

    # IAM Policy Analyzer
    iam_parser = subparsers.add_parser("iam-analyze", help="Analyze AWS IAM policy")
    iam_parser.add_argument("policy_file", help="Path to IAM policy JSON file")

    # Secrets Scanner
    secrets_parser = subparsers.add_parser("secrets-scan", help="Scan file for secrets")
    secrets_parser.add_argument("file", help="Path to file to scan")

    # Database Connection Auditor
    db_parser = subparsers.add_parser("db-audit", help="Audit database connection string")
    db_parser.add_argument("connection_string", help="Database connection string")

    # Security Headers Analyzer
    headers_parser = subparsers.add_parser("security-headers", help="Analyze HTTP security headers")
    headers_parser.add_argument("url", help="URL to analyze")

    # JWT Decoder
    jwt_parser = subparsers.add_parser("jwt-decode", help="Decode and analyze JWT token")
    jwt_parser.add_argument("token", help="JWT token to decode")
    jwt_parser.add_argument("-s", "--secret", help="Secret for verification")
    jwt_parser.add_argument("-v", "--verify", action="store_true", help="Verify token signature")

    # Hash Generator
    hash_parser = subparsers.add_parser("hash", help="Generate cryptographic hashes")
    hash_parser.add_argument("text", help="Text to hash")
    hash_parser.add_argument("-a", "--algorithm", default="sha256",
                            choices=['md5', 'sha1', 'sha256', 'sha512'],
                            help="Hash algorithm (default: sha256)")

    # Regex Tester
    regex_parser = subparsers.add_parser("regex", help="Test regular expression pattern")
    regex_parser.add_argument("pattern", help="Regular expression pattern")
    regex_parser.add_argument("text", help="Text to test against")
    regex_parser.add_argument("-f", "--flags", default="", help="Regex flags (g, i, m, s)")

    # PGP Validator
    pgp_parser = subparsers.add_parser("pgp-validate", help="Validate PGP key")
    pgp_parser.add_argument("key_or_file", help="PGP key or path to key file")

    # File Magic Detector
    magic_parser = subparsers.add_parser("file-magic", help="Detect file type by magic number")
    magic_parser.add_argument("file", help="Path to file to analyze")

    # DNS Validator
    dns_parser = subparsers.add_parser("dns-validate", help="Validate DNS records")
    dns_parser.add_argument("domain", help="Domain to validate")
    dns_parser.add_argument("-t", "--type", default="A", help="Record type (A, AAAA, MX, TXT, etc.)")

    # Zone Validator
    zone_parser = subparsers.add_parser("zone-validate", help="Validate DNS zone file")
    zone_parser.add_argument("zone_file", help="Path to zone file")

    # IP Calculator
    ip_parser = subparsers.add_parser("ip-calc", help="Calculate IP subnet information")
    ip_parser.add_argument("ip_or_cidr", help="IP address or CIDR notation")

    # RBL Check
    rbl_parser = subparsers.add_parser("rbl-check", help="Check if IP is listed in RBL/DNSBL")
    rbl_parser.add_argument("ip", help="IP address to check")

    # Email Verification
    email_parser = subparsers.add_parser("email-verify", help="Comprehensive email verification")
    email_parser.add_argument("email", help="Email address to verify")

    # Traceroute
    trace_parser = subparsers.add_parser("traceroute", help="Perform visual traceroute")
    trace_parser.add_argument("host", help="Host to trace")

    # URL Encoder
    url_parser = subparsers.add_parser("url-encode", help="URL encode/decode text")
    url_parser.add_argument("text", help="Text to encode/decode")
    url_parser.add_argument("-d", "--decode", action="store_true", help="Decode instead of encode")

    # Base64 Encoder
    base64_parser = subparsers.add_parser("base64", help="Base64 encode/decode text")
    base64_parser.add_argument("text", help="Text to encode/decode")
    base64_parser.add_argument("-d", "--decode", action="store_true", help="Decode instead of encode")

    # SSL Check
    ssl_parser = subparsers.add_parser("ssl-check", help="Check SSL/TLS certificate")
    ssl_parser.add_argument("host", help="Host to check")
    ssl_parser.add_argument("-p", "--port", type=int, default=443, help="Port number (default: 443)")

    # Certificate Converter
    cert_parser = subparsers.add_parser("cert-convert", help="Convert certificate formats")
    cert_parser.add_argument("file", help="Certificate file")
    cert_parser.add_argument("-f", "--format", default="PEM", help="Target format (default: PEM)")

    # SSL Certificate Converter (OpenSSL-like syntax)
    ssl_conv_parser = subparsers.add_parser("ssl-convert", help="Convert SSL certificates (OpenSSL-like syntax)")
    ssl_conv_parser.add_argument("-in", dest="input_file", required=True, help="Input certificate file")
    ssl_conv_parser.add_argument("-inform", dest="input_format", default="auto", help="Input format (PEM, DER, P12, JKS)")
    ssl_conv_parser.add_argument("-outform", dest="output_format", default="PEM", help="Output format (PEM, DER, P12, JKS)")
    ssl_conv_parser.add_argument("-out", dest="output_file", help="Output file (optional, defaults to stdout)")

    # Crypto Validator
    crypto_parser = subparsers.add_parser("crypto-validate", help="Validate cryptocurrency address")
    crypto_parser.add_argument("address", help="Cryptocurrency address")
    crypto_parser.add_argument("-t", "--type", default="bitcoin", choices=["bitcoin", "ethereum"], help="Crypto type (default: bitcoin)")

    # Hash Validator
    hash_val_parser = subparsers.add_parser("hash-validate", help="Validate and identify hash type")
    hash_val_parser.add_argument("hash", help="Hash value to validate")

    # Steganography Detector
    steg_parser = subparsers.add_parser("steg-detect", help="Detect hidden data in images")
    steg_parser.add_argument("file", help="Image file to analyze")

    # BGP Lookup
    bgp_parser = subparsers.add_parser("bgp-lookup", help="Lookup BGP AS information")
    bgp_parser.add_argument("as_number", help="AS number (e.g., AS15169)")

    # CSR Validator
    csr_parser = subparsers.add_parser("csr-validate", help="Validate Certificate Signing Request")
    csr_parser.add_argument("file", help="CSR file")

    # Have I Been Pwned - Email
    hibp_email_parser = subparsers.add_parser("hibp-email", help="Check if email appears in data breaches")
    hibp_email_parser.add_argument("email", help="Email address to check")

    # Have I Been Pwned - Password
    hibp_pass_parser = subparsers.add_parser("hibp-password", help="Check if password has been compromised")
    hibp_pass_parser.add_argument("password", help="Password to check")

    # Cloud Storage Search
    cloud_search_parser = subparsers.add_parser("cloud-storage-search", help="Search for publicly exposed cloud storage")
    cloud_search_parser.add_argument("query", help="Search query")
    cloud_search_parser.add_argument("-p", "--provider", default="all", choices=["aws", "azure", "gcp", "all"], help="Cloud provider (default: all)")
    cloud_search_parser.add_argument("-t", "--type", dest="search_type", default="filename", choices=["filename", "content"], help="Search type (default: filename)")

    # Cloud Storage Scan
    cloud_scan_parser = subparsers.add_parser("cloud-storage-scan", help="Analyze cloud storage bucket security")
    cloud_scan_parser.add_argument("bucket", help="Bucket name")
    cloud_scan_parser.add_argument("-p", "--provider", default="aws", choices=["aws", "azure", "gcp"], help="Cloud provider (default: aws)")

    # Cloud Storage Buckets
    cloud_buckets_parser = subparsers.add_parser("cloud-storage-buckets", help="List accessible cloud storage buckets")
    cloud_buckets_parser.add_argument("-p", "--provider", default="aws", choices=["aws", "azure", "gcp"], help="Cloud provider (default: aws)")

    # Email SPF
    email_spf_parser = subparsers.add_parser("email-spf", help="Analyze SPF records for domain")
    email_spf_parser.add_argument("domain", help="Domain to analyze")

    # Email DKIM
    email_dkim_parser = subparsers.add_parser("email-dkim", help="Analyze DKIM configuration for domain")
    email_dkim_parser.add_argument("domain", help="Domain to analyze")
    email_dkim_parser.add_argument("-s", "--selector", default="default", help="DKIM selector (default: default)")

    # Email DMARC
    email_dmarc_parser = subparsers.add_parser("email-dmarc", help="Analyze DMARC policy for domain")
    email_dmarc_parser.add_argument("domain", help="Domain to analyze")

    # Email MX
    email_mx_parser = subparsers.add_parser("email-mx", help="Check MX records for domain")
    email_mx_parser.add_argument("domain", help="Domain to check")

    # Email Disposable
    email_disp_parser = subparsers.add_parser("email-disposable", help="Check if email is from disposable provider")
    email_disp_parser.add_argument("email", help="Email address to check")

    # Email Blacklist
    email_bl_parser = subparsers.add_parser("email-blacklist", help="Check if domain is blacklisted")
    email_bl_parser.add_argument("domain", help="Domain to check")

    # Email Score
    email_score_parser = subparsers.add_parser("email-score", help="Calculate email deliverability score")
    email_score_parser.add_argument("domain", help="Domain to score")

    # SSL Resolve Chain
    ssl_chain_parser = subparsers.add_parser("ssl-resolve-chain", help="Resolve SSL certificate chain")
    ssl_chain_parser.add_argument("cert_or_url", help="Certificate file or URL")
    ssl_chain_parser.add_argument("-f", "--file", action="store_true", help="Input is a file path")

    # SSL Verify Key Pair
    ssl_keypair_parser = subparsers.add_parser("ssl-verify-keypair", help="Verify SSL certificate and key pair match")
    ssl_keypair_parser.add_argument("cert_file", help="Certificate file")
    ssl_keypair_parser.add_argument("key_file", help="Private key file")

    # File Verification
    verify_file_parser = subparsers.add_parser("verify-file", help="Verify file integrity and authenticity")
    verify_file_parser.add_argument("file", help="File to verify")
    verify_file_parser.add_argument("--hash", dest="expected_hash", help="Expected hash for verification")
    verify_file_parser.add_argument("-s", "--signature", help="Signature file for verification")

    # Email Verification
    verify_email_parser = subparsers.add_parser("verify-email", help="Comprehensive email verification")
    verify_email_parser.add_argument("email", help="Email address to verify")

    # Transaction Verification
    verify_tx_parser = subparsers.add_parser("verify-tx", help="Verify blockchain transaction")
    verify_tx_parser.add_argument("tx_hash", help="Transaction hash")
    verify_tx_parser.add_argument("-c", "--chain", default="bitcoin", choices=["bitcoin", "ethereum"], help="Blockchain (default: bitcoin)")

    # Tool Search
    tool_search_parser = subparsers.add_parser("tool-search", help="Search available security tools")
    tool_search_parser.add_argument("query", help="Search query")
    tool_search_parser.add_argument("-c", "--category", help="Tool category filter")

    # Tool List
    tool_list_parser = subparsers.add_parser("tool-list", help="List all available tools")
    tool_list_parser.add_argument("-c", "--category", help="Filter by category")

    # Health Check
    health_parser = subparsers.add_parser("health", help="Check API health status")

    # WHOIS Lookup
    whois_parser = subparsers.add_parser("whois", help="Perform WHOIS domain lookup")
    whois_parser.add_argument("domain", help="Domain to lookup")

    # Malware Scan
    malware_parser = subparsers.add_parser("malware-scan", help="Scan file for malware")
    malware_parser.add_argument("file", help="File to scan")

    # Archive Inspector
    archive_parser = subparsers.add_parser("inspect-archive", help="Inspect archive file contents")
    archive_parser.add_argument("file", help="Archive file to inspect")

    # Interactive Console
    console_parser = subparsers.add_parser("console", help="Start interactive console with command completion")

    args = parser.parse_args()

    if not args.command:
        parser.print_help()
        sys.exit(0)

    cli = VeriBitsCLI(api_key=args.api_key)

    # Handle console mode
    if args.command == "console":
        console = VeriBitsConsole(cli)
        console.cmdloop()
        sys.exit(0)

    if args.command == "iam-analyze":
        cli.iam_analyze(args.policy_file)
    elif args.command == "secrets-scan":
        cli.secrets_scan(args.file)
    elif args.command == "db-audit":
        cli.db_audit(args.connection_string)
    elif args.command == "security-headers":
        cli.security_headers(args.url)
    elif args.command == "jwt-decode":
        cli.jwt_decode(args.token, args.secret, args.verify)
    elif args.command == "hash":
        cli.hash_text(args.text, args.algorithm)
    elif args.command == "regex":
        cli.regex_test(args.pattern, args.text, args.flags)
    elif args.command == "pgp-validate":
        cli.pgp_validate(args.key_or_file)
    elif args.command == "file-magic":
        cli.file_magic(args.file)
    elif args.command == "dns-validate":
        cli.dns_validate(args.domain, args.type)
    elif args.command == "zone-validate":
        cli.zone_validate(args.zone_file)
    elif args.command == "ip-calc":
        cli.ip_calculate(args.ip_or_cidr)
    elif args.command == "rbl-check":
        cli.rbl_check(args.ip)
    elif args.command == "email-verify":
        cli.email_verify(args.email)
    elif args.command == "traceroute":
        cli.traceroute(args.host)
    elif args.command == "url-encode":
        cli.url_encode(args.text, args.decode)
    elif args.command == "base64":
        cli.base64_encode(args.text, args.decode)
    elif args.command == "ssl-check":
        cli.ssl_check(args.host, args.port)
    elif args.command == "cert-convert":
        cli.cert_convert(args.file, args.format)
    elif args.command == "ssl-convert":
        cli.ssl_convert(args.input_file, args.input_format, args.output_format, args.output_file)
    elif args.command == "crypto-validate":
        cli.crypto_validate(args.address, args.type)
    elif args.command == "hash-validate":
        cli.hash_validate(args.hash)
    elif args.command == "steg-detect":
        cli.steg_detect(args.file)
    elif args.command == "bgp-lookup":
        cli.bgp_lookup(args.as_number)
    elif args.command == "csr-validate":
        cli.csr_validate(args.file)
    elif args.command == "hibp-email":
        cli.hibp_email(args.email)
    elif args.command == "hibp-password":
        cli.hibp_password(args.password)
    elif args.command == "cloud-storage-search":
        cli.cloud_storage_search(args.query, args.provider, args.search_type)
    elif args.command == "cloud-storage-scan":
        cli.cloud_storage_scan(args.bucket, args.provider)
    elif args.command == "cloud-storage-buckets":
        cli.cloud_storage_buckets(args.provider)
    elif args.command == "email-spf":
        cli.email_spf(args.domain)
    elif args.command == "email-dkim":
        cli.email_dkim(args.domain, args.selector)
    elif args.command == "email-dmarc":
        cli.email_dmarc(args.domain)
    elif args.command == "email-mx":
        cli.email_mx(args.domain)
    elif args.command == "email-disposable":
        cli.email_disposable(args.email)
    elif args.command == "email-blacklist":
        cli.email_blacklist(args.domain)
    elif args.command == "email-score":
        cli.email_score(args.domain)
    elif args.command == "ssl-resolve-chain":
        cli.ssl_resolve_chain(args.cert_or_url, args.file)
    elif args.command == "ssl-verify-keypair":
        cli.ssl_verify_keypair(args.cert_file, args.key_file)
    elif args.command == "verify-file":
        cli.verify_file(args.file, args.expected_hash, args.signature)
    elif args.command == "verify-email":
        cli.verify_email(args.email)
    elif args.command == "verify-tx":
        cli.verify_transaction(args.tx_hash, args.chain)
    elif args.command == "tool-search":
        cli.tool_search(args.query, args.category)
    elif args.command == "tool-list":
        cli.tool_list(args.category)
    elif args.command == "health":
        cli.health_check()
    elif args.command == "whois":
        cli.whois_lookup(args.domain)
    elif args.command == "malware-scan":
        cli.malware_scan(args.file)
    elif args.command == "inspect-archive":
        cli.inspect_archive(args.file)


if __name__ == "__main__":
    main()
