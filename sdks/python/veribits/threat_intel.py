"""Threat Intelligence API"""

from typing import List, Dict, Any


class ThreatIntelAPI:
    """Threat intelligence operations"""

    def __init__(self, client):
        self.client = client

    def lookup(self, hash: str, sources: List[str] = None, include_metadata: bool = True) -> Dict[str, Any]:
        """
        Lookup file hash against threat databases

        Args:
            hash: File hash (MD5, SHA1, or SHA256)
            sources: List of sources to check (default: all)
            include_metadata: Include detailed metadata (default: True)

        Returns:
            Dict with threat intelligence results

        Example:
            result = client.threat_intel.lookup("abc123...")
            print(f"Threat score: {result['threat_score']}")
        """
        return self.client.post('/api/v1/threat-intel/lookup', json={
            'hash': hash,
            'sources': sources or ['virustotal', 'malwarebazaar'],
            'include_metadata': include_metadata
        })

    def fingerprint(self, file_path: str, include_entropy: bool = True) -> Dict[str, Any]:
        """Get threat ensemble fingerprint for file"""
        return self.client.post('/api/v1/threat-intel/fingerprint', json={
            'file_path': file_path,
            'include_entropy': include_entropy,
            'include_strings': True,
            'include_packers': True
        })

    def yara_scan(self, file_path: str, rules: List[str] = None) -> Dict[str, Any]:
        """Scan file with YARA rules"""
        return self.client.post('/api/v1/threat-intel/yara-scan', json={
            'file_path': file_path,
            'rules': rules or ['default']
        })
